# Real-time notifications and operational displays

The seven requested web pages subscribe to private Laravel Echo / Reverb topics through AppShell. Committed changes refresh notification history, counts and the mounted operational views without navigation or a browser reload. Existing database records and per-user read/delete history remain authoritative. OneSignal continues to deliver closed-tab and mobile push notifications.

The current implementation uses a durable database outbox and a background relay. Production can use Redis queue workers and multiple Reverb servers. Mobile report retries use persistent idempotency keys to prevent duplicate saves. See [realtime-scaling.md](realtime-scaling.md) for deployment, retry behavior, monitoring, capacity limits and load tests.

For local development, run `./start-realtime-notifications.ps1` alongside Laravel and Vite. This starts Reverb on port **8090** and an inline background outbox worker. Use `-Restart` after changing server code. Use `-Queue` to exercise the database queue locally; production uses the supplied Redis/Supervisor configuration instead.

Frontend VITE_REVERB_APP_KEY must match the public backend key, and VITE_REVERB_PORT must be 8090 locally. Keep the Reverb app secret exclusively on the server. REVERB_ALLOWED_ORIGINS lists frontend hostnames, including the development LAN IP when needed. Reload the browser after configuration changes.

Initial load, reconnect, focus and returning to a visible tab fetch current database state. Under sustained bursts, refreshes are combined to limit API traffic. During socket outages, one shared fallback backs off with jitter; updates can take longer until the connection recovers. Live updates still have normal database, queue and network latency.
