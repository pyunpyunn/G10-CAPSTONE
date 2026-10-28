# Verify disaster notifications

The notification fix requires an updated APK. Refreshing the web dashboard or
restarting Laravel cannot update JavaScript embedded in an installed APK.

## What changed

- Explicitly display OneSignal foreground notifications. The installed native
  bridge defers display to the registered foreground handler.
- Refresh household data when a disaster push arrives and when the app resumes.
- Show the API's recent disaster alerts on the household home screen.
- While the household screen is active, check the API every 30 seconds as a
  fallback for delayed push. No checks run while the app is in the background.
- Request high-priority Android delivery for server push messages.

The API fallback updates the in-app list; it does not create an Android push or
prove that Google delivered a notification.

## Phone test

1. Install the updated signed APK over the existing app, keeping its data.
2. Start the backend, its selected database, and the ngrok tunnel.
3. Confirm the phone can open
   `https://sturdily-morse-sitter.ngrok-free.dev/api/v1/health/ready` and sees
   `{"status":"ready"}`. Use
   `https://sturdily-morse-sitter.ngrok-free.dev/api/v1` in **Server settings**.
   The current APK does not have a working Tailscale or LAN HTTPS route.
4. Log in with a household account and allow notifications.
5. From HQ, broadcast a uniquely worded alert for that household's active event
   and area. Keep RESQPERATION open on the phone for this first test.
6. Confirm the Android notification appears and Latest disaster alerts updates.
7. Press Home and send a second uniquely worded alert. Confirm the Android
   notification appears; tap it and verify the dashboard refreshes.

If Android push is delayed but the API is reachable, the foreground list should
refresh within about 30 seconds plus request time. Only alerts returned by the
server for the current event and household area are displayed.

If Android notifications still fail, compare OneSignal confirmed receipts with
the notification shade, check app notification categories, and capture device
logs. An accepted API request alone is not proof of device delivery.

## Local checks

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm run check
node --test tests/foregroundNotification.test.cjs tests/householdAlerts.test.cjs

cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan test --filter=DisasterBroadcastPushTest
```
