# Disaster broadcasting review

Scope: disaster broadcasting and the mobile overview reads that expose the active disaster to household and rescuer users. The attached images were used as reference material for idempotency, bounded retries, cache expiry/invalidation, transactions and structured logs.

## Fixed

- OneSignal HTTP 200 without a notification ID was incorrectly counted as sent. Provider errors now produce failed/partial status and exclude explicitly invalid subscriptions from the accepted count.
- Inline HTTP retries had no idempotency UUID. Broadcast jobs now persist a sorted audience snapshot and random UUID per batch in the existing transactional database outbox. Queue retries reuse that plan, including partial submissions. Old queued jobs created before this change lack the plan: drain those before deploying.
- Role/user/responder filters could silently broaden the audience when required schema columns were missing. Those cases now select no recipients.
- Mobile External ID was the device UUID. All registration paths now resolve the authenticated Laravel user ID via `/auth/me`, retaining device UUID only for backend device registration. Subscription polling now waits for both subscription ID and token.
- Logout opts the native subscription out and clears its OneSignal identity. Login opts it back in after permission is granted.
- Production builds inherited development OneSignal plugin mode. The production EAS profile now selects production mode; the public App ID environment variable also updates Expo config.
- Broadcast creation now locks and rechecks the event within its save transaction.
- Structured submission logs include broadcast ID, status, accepted count and provider message IDs.

## Mobile caching

Both overview readers use session-memory cache entries with a five-second TTL, a 50-entry bound, and generation checks so reads started before invalidation cannot repopulate the cache. Successful writes and token changes clear the cache. Pull-to-refresh bypasses it. Foreground broadcast receipt, notification taps and app resume fetch fresh overviews.

Writes (status reports, locations and other actions) still execute against the API each time. Caching writes would suppress real actions. Emergency data is not persisted or returned as a stale fallback on network errors. Cache use is limited to disaster-related overview reads; other mobile modules remain outside this task's scope.

## Delivery verification and operations

### Local verification on October 9, 2026

The operations worker was absent and was started. PHP's CA paths were unset, causing cURL error 60. A checksum-verified Mozilla CA bundle from `https://curl.se/ca/cacert.pem` was installed at `C:/backend/php/cacert.pem`, and `curl.cainfo` / `openssl.cafile` were configured in `C:/backend/php/php.ini` (backup: `php.ini.broadcast-backup`). A read-only OneSignal app check succeeded with HTTP 200.

An attempted delivery of the saved broadcast was rejected by OneSignal with an empty message ID, invalid subscription IDs, and warnings that Android notifications must be configured in OneSignal settings. Complete Android FCM configuration and re-register the installed mobile builds before expecting receipt. The broadcast page now displays push submission status and updates it automatically every five seconds without resetting the compose form.

On Windows, run `./start-broadcast-worker.ps1` from the backend directory to start the operations worker in the background. It checks for an existing worker and writes worker output under `storage/logs/operations-worker.*.log`. Run it again after restarting your computer. Alternatively `composer dev` starts the API and workers together. The broadcast page updates delivery status automatically; closing it does not stop server delivery.

1. Set backend `ONESIGNAL_APP_ID` and `ONESIGNAL_API_KEY` and mobile `EXPO_PUBLIC_ONESIGNAL_APP_ID` to the same OneSignal app. Keep the REST key on the server.
2. Ensure Android FCM / iOS APNs credentials exist in OneSignal. Rebuild after changing native plugin configuration. Expo Go does not support this native integration.
3. Run the database operations worker: `php artisan queue:work operations_outbox --queue=operations --tries=3`. Monitor failed jobs and the submission logs. Keep the worker timeout below the connection's `retry_after`; large audiences can exceed the default worker timeout and require batching into separate jobs in a future capacity change.
4. On installed household and rescuer builds, grant permission and verify an active OneSignal subscription is saved on the backend. Confirm the dashboard External ID matches Laravel `user_id`.
5. Send a barangay-wide broadcast and confirm both roles receive it; send a selected-purok broadcast and confirm only household devices in that area receive it. Verify a denied/inactive device is excluded.
6. Check OneSignal message statistics for confirmed receipt. Local `sent` means provider accepted submission, not proof a phone displayed the alert. Force a transient provider failure and confirm the retry reuses its UUID; test missing credentials and SMS independence.
7. Test pull-to-refresh, foreground broadcast refresh, background tap, logout/account switch, and status submission followed by a fresh overview.

Remaining limits: physical-device delivery and dashboard configuration were not verified here. Subscription changes after initial registration still rely on re-registration; adding lifecycle observers with authenticated device resynchronization is a follow-up. SMS has its own retry semantics and is not made idempotent by a OneSignal UUID. Broadcast creation itself does not deduplicate repeated admin POST requests. Existing history endpoints return bounded latest records (20 events / 30 broadcasts), rather than complete paginated history.

## References

- [OneSignal React Native setup](https://documentation.onesignal.com/docs/en/react-native-sdk-setup): the application is Expo/React Native, so browser service workers and `react-onesignal` are not appropriate for its mobile subscription flow.
- [OneSignal push API](https://documentation.onesignal.com/reference/push-notification): subscription targeting, message ID validation and partial errors.
- [OneSignal idempotency](https://documentation.onesignal.com/reference/idempotent-notification-requests): reuse UUIDs on retries; provider deduplication lasts 30 days and does not guarantee delivery.
- [Laravel cache](https://laravel.com/docs/12.x/cache) and [queues](https://laravel.com/docs/12.x/queues): shared Redis is useful for future server cache/locks; no Redis dependency was introduced for these mobile reads.
- [Expo SDK 54](https://docs.expo.dev/versions/v54.0.0/).
