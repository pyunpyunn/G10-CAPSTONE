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
For a dedicated worker after restarting the computer:

```powershell
php artisan queue:work operations_outbox --queue=broadcasts --sleep=1 --tries=3 --timeout=120
```

Read-only account readiness check:

```powershell
php artisan onesignal:status
```

The OneSignal App ID must match the mobile app. The private OneSignal API key
belongs only in Laravel's environment. Firebase HTTP v1 service-account credentials
must be configured in the OneSignal app's Android platform. The installed Android
development/production APK registers its subscription on login; Expo Go cannot.

Setup audit: the existing OneSignal API key authenticates, but Android Firebase
credentials are empty and OneSignal reports zero messageable devices. Server
delivery is wired; actual phone delivery remains blocked until Firebase credentials
are supplied and a phone obtains its Android push subscription.
