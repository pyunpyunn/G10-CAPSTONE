# Android disaster push delivery

Saving a broadcast on the web page creates a durable `DeliverDisasterBroadcast`
job in the `broadcasts` database queue. Its worker submits the saved audience to
OneSignal, using the same idempotency keys on retries. Barangay-wide broadcasts
target Android household and rescuer subscriptions. Selected-purok broadcasts
target Android households in those puroks. Devices must be active and have
granted notification permission. The web broadcast log refreshes delivery status.

`onesignal_sent` means OneSignal accepted the request, not confirmed phone receipt.
OneSignal's delivery report is needed to confirm receipt.

The local broadcast worker is started independently from the old operations
backlog. Normal `composer dev` also consumes `broadcasts` before `operations`.
The local Windows account starts the worker through `RESQPERATIONBroadcastWorker`.
The launcher checks for an existing worker to avoid duplicates.
`start-broadcast-worker.ps1` consumes `broadcasts,operations,navigation`.

For a dedicated worker on another machine:

```powershell
php artisan queue:work operations_outbox --queue=broadcasts,operations,navigation --sleep=1 --tries=3 --timeout=120
```

Read-only account readiness check:

```powershell
php artisan onesignal:status
```

The OneSignal App ID must match the mobile app. The private OneSignal API key
belongs only in Laravel's environment. Firebase HTTP v1 service-account credentials
must be configured in the OneSignal app's Android platform. The installed Android
development/production APK registers its subscription on login; Expo Go cannot.

**Readiness verified 2026-10-28:** `php artisan onesignal:status` authenticated
successfully, reported Android FCM v1 configured, 19 provider-messageable
devices, and 7 eligible locally registered Android subscriptions. These counts
change as devices register, permission changes, or subscriptions expire. This
check does not send a push and does not prove a specific phone received one.
Confirm a test device using OneSignal delivery reports and the phone itself.

Every newly saved broadcast uses `channel=onesignal`. Provider message IDs, errors, and accepted counts are stored on the broadcast and shown in its web history. Message IDs identify the corresponding request in OneSignal reports. An empty provider ID is never treated as a successful push.

Keep the OneSignal App ID identical in backend `ONESIGNAL_APP_ID` and mobile
`EXPO_PUBLIC_ONESIGNAL_APP_ID`. Keep the OneSignal REST API key and Firebase
service-account JSON in their approved secure locations only. A successful
server send is provider acceptance, not proof of OS display or user receipt.
