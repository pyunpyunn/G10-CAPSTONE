# Durable real-time delivery and capacity testing

## Implemented flow

An API write and its realtime_outbox entries commit on the same operational database connection. There are no socket or Redis calls in the reporting transaction. A relay claims up to 500 entries with a 120-second lease and queues one batch. A Redis worker combines the entries into one event per batch across the relevant private topic channels and one notification-feed event. Only successful broadcast completion acknowledges the entries. Socket or Redis failures leave the database changes available for retry. A crashed worker's lease expires and another worker can reclaim it. PostgreSQL and MySQL 8 support concurrent relays through SKIP LOCKED; set REALTIME_SKIP_LOCKED=false and use one relay on older MySQL, or use one relay with SQLite.

Delivery is at least once. Duplicate events are harmless invalidations, and the browser deduplicates batch IDs. Reverb accepting a broadcast is not a receipt from every browser. Initial page loads, reconnects, focus and visibility changes always fetch database state to recover missed socket messages. The existing notification records and per-user audit history remain authoritative; OneSignal remains the separate closed-tab/mobile push path.

The current mobile apps do not subscribe to administrative Echo channels. Household and rescuer users submit authenticated REST reports and receive their existing OneSignal notifications. The web dashboard sockets are restricted to admins and super admins. 30,000 registered households do not mean 30,000 administrative socket connections.

## Retry-safe reporting

Household status, member status, trusted-member status, assignment status, field reports and resource request APIs accept Idempotency-Key. The key is scoped to authenticated user, HTTP method and path. The first successful response is stored in the same transaction as the report; retries return that response without another write. Reusing a key with different data returns 409. Concurrent retries serialize through a database unique key and row lock. Failed responses roll back the key, source records and outbox records together.

The mobile helper stores the key on the device, retries transient failures up to twice with jitter, and reuses it for identical failed submissions after an app restart. Successful requests clear the key for a later intentional report. The server retains keys for seven days; the client starts a new key after six days. Legacy clients without a key still work, but cannot receive duplicate protection. This is not an offline report queue: a request that never reaches the server must still be retried by the user after the automatic attempts finish.

## Local setup

Run only the three new migrations (existing unrelated pending migrations are not part of this change):

```powershell
php artisan migrate --path=database/migrations/2026_10_09_000004_create_realtime_outbox.php
php artisan migrate --path=database/migrations/2026_10_09_000005_create_report_idempotency.php
php artisan migrate --path=database/migrations/2026_10_09_000006_add_realtime_read_indexes.php
```

Set REALTIME_DURABLE=true. Reverb uses port 8090, avoiding Laravel on 8000/8080 and Expo Metro on 8081. Match VITE_REVERB_PORT and REVERB_PORT. Set REVERB_ALLOWED_ORIGINS to the allowed frontend hostnames, including your LAN IP when testing remotely. Do not set it to a URL or include the port.

Run start-realtime-notifications.ps1 alongside the API and Vite. It starts Reverb and a separate inline outbox relay with hidden background windows and log files. Inline mode delivers FROM the background relay, never from a report request, and retries failed batches from the outbox. It does not require Redis. The realtime_database queue is also available for local queue testing. Reload the mobile bundle once after the expo-crypto dependency update.

## Production deployment

Use deploy/realtime/production.env.example, supervisor.conf.example and nginx.conf.example as templates, replacing hosts and credentials. This repository does not provision servers or guarantee a capacity figure.

* Run multiple API instances behind a load balancer with PHP-FPM or another production Laravel server. Do not use php artisan serve for this workload. Use the same operational database, APP_KEY and token storage on every node. Store uploads in shared object storage.
* Use a managed/shared Redis deployment with authentication, private networking, TLS when crossing hosts, persistence and noeviction. Do not expose Redis to the public internet. deploy/realtime/redis.compose.yml is a local single-node service, not a production HA cluster.
* Set REALTIME_QUEUE_CONNECTION=realtime_redis and CACHE_STORE=redis. Shared cache keeps rate limits consistent across API instances. Enable REALTIME_READ_CACHE=true for shared dashboard snapshots and raw notification sources, using REALTIME_CACHE_STORE=redis. Those snapshots have a two-second safety TTL and locks to combine simultaneous cache misses; the relay changes their revision before broadcasting. Per-user read/delete state is applied after the source cache and remains uncached. Give each environment a distinct REDIS_PREFIX. Predis is installed, so REDIS_CLIENT=predis works without a PHP Redis extension.
* Run supervised realtime:dispatch relays and queue:work realtime_redis --queue=realtime workers. Their 20-second timeout stays below the queue's 90-second retry_after and the 120-second lease. Start with one relay and four workers, then size using measured backlog and database pressure.
* Run multiple Reverb nodes behind a WSS load balancer, all with REVERB_SCALING_ENABLED=true and the same private Redis and Reverb credentials. Laravel publishes to the internal Reverb endpoint; browsers connect to the public WSS endpoint. Keep the /apps publishing endpoint private.
* Reverb at high connection counts needs a Linux event-loop extension, tuned file descriptor limits, reverse-proxy worker connections and enough memory. Distribute connections across nodes and size the load generator as well. Follow the first-party Reverb production guidance.
* Keep the existing operations, trackingaid and OneSignal workers/services running. This change does not make OneSignal or SMS providers infinitely scalable; their rate limits and delivery receipts remain separate.

Deploy schema before enabling durable mode. Deploy the new frontend and relay/worker code together; legacy synchronous mode exists only for transition. Restart the long-lived relay, queue workers and Reverb after deploying PHP changes. Durable mode should remain enabled during Redis or socket outages so new reports keep adding retryable events.

API writes are covered by AtomicRealtimeWrite, including public inquiry/SMS/resource intake and authenticated mutations. Background jobs, CLI commands and integrations must wrap watched source writes in an operational DB transaction to achieve the same atomic guarantee. Query-builder writes are captured too. Direct SQL writes outside Laravel require a CDC/integration bridge; autocommit CLI writes have a small write-to-outbox crash window. Transactions spanning a different external database are not made globally atomic by this implementation.

## Traffic controls and monitoring

Browsers subscribe only to topics needed by the current page. Account and inquiry topics additionally require super-admin authorization. Events contain topic names and batch IDs, never household records. Frontend refreshes run immediately, then at most once per 1,250 ms during continuous bursts. Module loaders keep one request in flight and schedule a trailing refresh rather than repeatedly cancelling it. Independent one-second chart and five-second communication polling were removed. When sockets are unavailable, one shared jittered fallback backs off from about five seconds to a maximum of one minute. Reconnect performs immediate reconciliation.

The read-index migration adds missing composite indexes for household event history, field reports, member status rollups and assignments, skipping existing equivalent indexes. It does not replace EXPLAIN analysis of real data. On large production tables, plan index creation with the DBA to control DDL locks.

Monitor php artisan realtime:status and the super-admin-only GET /api/v1/realtime/health. The health endpoint returns 503 when Redis is unreachable or the oldest pending change is at least 30 seconds old. Alert on growing pending count, oldest age above 5 seconds, retry growth, worker exits, Redis memory/evictions, database lock waits, 429/5xx rates and socket subscription failures. Monitor Reverb process health and proxy connection counts separately; an empty outbox does not prove that Reverb is accepting new clients.

Schedule php artisan realtime:status --prune daily. Pruning deletes only acknowledged events older than 24 hours and expired idempotency responses; it never deletes pending events. Pending rows can grow during a prolonged outage, so alert on disk capacity and recover the relay before the database fills. Monitor operational DB backups and test restoration.

## Load-test procedure

tests/load/realtime.k6.js models private socket authentication, sustained connections, dashboard reads, optional socket-triggered API refetches, report writes and replay-safe retries. Put staging tokens in tests/load/credentials.json using credentials.example.json as the format. That real credential file and output artifacts are ignored by version control. Use many distinct test accounts; one token reused across thousands of VUs will hit per-user rate limits and misrepresent capacity. Create an active test disaster event and correctly linked household members before enabling writes.

Start with 10 sockets and 2 API requests per second. Increase through 100, 1,000, 10,000 and 30,000 connections, distributing k6 load generators for the large runs. SOCKET_USERS controls connections; REPORTS_PER_SECOND controls submission rate independently. Every write also generates one replay request, so account for that additional API traffic. RUN_ID must be unique per test and contain only letters, digits, underscores or hyphens.

```powershell
$env:TEST_ENV_ACK = 'staging'
$env:API_URL = 'https://staging.example.com/api/v1'
$env:WS_URL = 'wss://ws.staging.example.com'
$env:REVERB_APP_KEY = 'staging-public-app-key'
$env:FRONTEND_ORIGIN = 'https://staging.example.com'
$env:SOCKET_USERS = '10'
$env:REPORTS_PER_SECOND = '2'
$env:RUN_ID = 'staging-run-001'
k6 run tests/load/realtime.k6.js
# Only against an isolated staging database:
$env:WRITE_REPORTS = '1'
$env:SOCKET_REFETCH = '1'
k6 run tests/load/realtime.k6.js
```

The thresholds check p95 HTTP latency under 2 seconds, private subscription success above 99%, errors under 1%, successful idempotent replay and socket delivery latency. Socket latency is measured from the broadcast timestamp and requires synchronized clocks; it excludes submission, queue time and page-query time. Measure end-to-end report-to-display latency separately with browser sessions. The script does not run 30,000 users by default.

Test Redis outages, Reverb outages and worker termination under load. Confirm reports remain committed, duplicate retries do not add rows, backlog is retained, leases recover and the queue drains afterward. Compare accepted unique report keys against persisted reports and verify all pending events eventually acknowledge. Capacity is established only when those checks pass on production-sized infrastructure with representative data and acceptable p95/p99 latency, including a recovery test.

Official references: [Laravel queues](https://laravel.com/docs/13.x/queues), [Reverb scaling](https://laravel.com/framework/docs/reverb), [k6 WebSockets](https://grafana.com/docs/k6/latest/javascript-api/k6-ws/), [Expo Crypto SDK 54](https://docs.expo.dev/versions/v54.0.0/sdk/crypto/).
