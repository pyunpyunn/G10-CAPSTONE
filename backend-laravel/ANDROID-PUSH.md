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
The local Windows account now starts the dedicated broadcast worker automatically at sign-in through `RESQPERATIONBroadcastWorker`. The launcher checks for an existing broadcast worker to avoid duplicates. The startup command runs `start-broadcast-worker.ps1`, which consumes only the `broadcasts` queue.

For a dedicated worker on another machine:

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

Every newly saved broadcast uses `channel=onesignal`. Provider message IDs, errors, and accepted counts are stored on the broadcast and shown in its web history. Message IDs identify the corresponding request in OneSignal reports. An empty provider ID is never treated as a successful push.

Latest audit: both the OneSignal account and Expo Android credentials lack an FCM v1 credential. No Firebase/gcloud login or service-account JSON was found locally. Actual delivery requires authenticating the Firebase integration or supplying an existing service-account file; the Expo build URL is an APK build, not a OneSignal account or Firebase credential.
