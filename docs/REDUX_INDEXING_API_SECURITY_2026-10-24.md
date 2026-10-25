# Redux, operational indexes and API request protection

Implemented on 24 October 2026 for the web and Expo mobile applications.

## Shared application data

Both app roots now mount a React Redux Provider. Both central Axios clients use a Redux Toolkit store for reusable server responses, cache invalidation, request counts and concurrent-read sharing. This covers the application API modules without requiring separate caching implementations in every page. The old mobile read cache and web session-storage cache now delegate to Redux. The web personnel module's separate request-sharing map was removed.

Identical concurrent reads share one network request. Keys distinguish server, URL, filters, pagination and language; changing the bearer session or mobile device resets the store. Tokens and request configurations never enter Redux. Logout clears the data, and results from an earlier invalidated generation cannot repopulate the cache. Cancelling one screen does not cancel another screen's shared request.

Operational overview reads expire after three seconds, other ordinary reads after ten seconds, and reference-option reads after sixty seconds. Successful writes and web realtime events invalidate cached data. GPS writes invalidate map/route/dispatch reads without evicting unrelated directory data. Authentication, live navigation polling, binary downloads and explicitly fresh requests bypass reusable response caching. Redux records request activity for these paths, but never caches login credentials or tokens.

The store retains at most fifty API responses, each smaller than approximately 512 KB, and thirty compatibility view entries. Data stays in session memory; no Redux persistence or DevTools capture is enabled. Large response bodies bypass caching. Local form inputs, map gestures and component display state remain local UI state. SecureStore credentials and the SQLite offline trip/outbox database retain their existing security and durable offline responsibilities. Third-party map tiles, geocoding and downloaded SQLite file bytes are not put into Redux.

## Database indexes

Applied migrations:

- `2026_10_24_000002_add_operational_query_indexes.php`
- `2026_10_24_000003_add_notification_feed_indexes.php`

Indexes support address/purok lookup, active household membership, event/household report history, reporter history, event/responder/team dispatch filtering, latest GPS, assignment routes, route-coordinate ordering, field-report joins, device membership, resource-request feeds, notification/audit feeds and broadcasts. Migrations skip missing legacy columns and existing indexes with an equivalent leading column sequence. No operational records were deleted or replaced.

Live MySQL EXPLAIN checks show covering index lookups using `op_status_event_latest`, `op_gps_responder_latest` and `op_dispatch_event_status`. These are query-plan checks on current data, not a claim of a measured 30,000-household load benchmark. Pagination and bounded map reads remain necessary alongside indexes and Redux.

## API headers and bot resistance

The API group now runs `GuardApiHeaders` before database access. It checks the configured API Host, exact browser Origin, Referer origin when Origin is absent, cross-site Fetch Metadata, content type and declared body size. CORS uses the same exact origin allowlist. Wildcard/subdomain lookalikes and `Origin: null` are rejected. Existing web and mobile endpoints use the guard, including login and public integration endpoints.

Native mobile and server integrations commonly send no browser Origin. They remain supported through the existing route-level bearer authentication, role/assignment ownership, device binding, external API keys, signed download URLs and SMS HMAC signatures. An Origin, Referer, User-Agent or custom client header can be forged; none is treated as a secret or proof of identity. These controls do not claim to eliminate all bots or replace edge DDoS protection.

A database-independent file-cache rate limiter provides a configurable perimeter limit. The local setting is 6,000 requests per minute per connecting IP to accommodate shared disaster-site Wi-Fi and GPS traffic; the existing stricter login, public-form, routing and authenticated-user limits remain in force. For multiple API instances, configure `API_RATE_LIMIT_STORE=redis` with shared Redis. Configure trusted reverse proxies explicitly at deployment before relying on forwarded client IPs.

Deployment settings are documented in `.env.example`: `API_ALLOWED_ORIGINS`, `API_ALLOWED_HOSTS`, `API_EDGE_REQUESTS_PER_MINUTE`, `API_RATE_LIMIT_STORE`. The live local allowlist includes the current web address and configured tunnel. When the public RESQPERATION domain changes, update these exact origins/hosts and clear/rebuild Laravel configuration. Do not add wildcard tunnel domains. Use HTTPS for the public deployment.

Route inventory: 198 API routes, 178 behind Sanctum. The remaining twenty are intentionally public bootstrap/health/login/password recovery/inquiry routes or integration/download routes with API keys, HMAC signatures or signed URLs. Full route middleware inventory is included in the evidence folder.

## Verification and practical limits

- Ten concurrent equivalent reads produced one network request in each app's Redux transport test.
- Cache tests cover pagination/filter separation, invalidation during a pending read, account/server isolation, credential exclusion, cancellation, uncached live/auth/download paths and GPS invalidation.
- Selected mobile suite: 27 tests passed, including Redux, logout, GPS and offline navigation storage.
- Complete web test suite: 30 tests passed. Web production build, targeted lint and mobile TypeScript passed.
- Targeted backend suite: 50 tests, 321 assertions passed, including request guards, indexes, household reporting, field reports and personnel access.
- Live HTTP checks: approved browser 200, foreign browser 403, protected native/browser requests without tokens 401. MySQL migrations and indexed query-plan checks succeeded.
- Broad backend suite: 413/428 passed; twelve failures and three errors remain. A diagnostic control run of the affected older suites with the new header guard disabled reproduced all fifteen failures (39/54 passed). They include missing geographic fixture tables, inactive legacy user fixtures, older role/approval expectations, a report-status-filter expectation and older response contracts. The diagnostic bypass was deleted and is not part of production code. Full failure details are preserved rather than reporting the entire suite as clean.
- No physical Android device was attached. Android release build 25 contains the mobile Redux changes; phone installation and on-device acceptance remain necessary.

Android build 25 is signed with the existing release certificate and verified as non-debuggable. Final SHA-256: `145F35461A19269428BFB97886B494B9F6F3D5E421CE8FE835C0FA217B98846C`.

Evidence: `backend-laravel/docs/verification/redux-api-2026-10-24/`.

References consulted: [Redux Toolkit](https://redux-toolkit.js.org/introduction/getting-started), [Laravel rate limiting](https://laravel.com/docs/12.x/rate-limiting), [Expo SDK documentation](https://docs.expo.dev/versions/v54.0.0/).
