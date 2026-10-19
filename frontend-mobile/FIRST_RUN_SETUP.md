# RESQPERATION APK: First-Run Server Setup

Use the existing standalone APK for household and rescuer accounts. Expo Go,
QR scanning, Metro, and an APK rebuild are not required.

## 1. Start MySQL

Open **PowerShell as Administrator**:

```powershell
Start-Service MySQL95
Get-Service MySQL95
```

The service should show `Running`. Keep the backend's existing `.env` file;
do not overwrite it or recreate the database.

## 2. Connect Tailscale

On the server computer:

```powershell
tailscale up
tailscale status
tailscale ip -4
```

The computer's current Tailscale IPv4 address is `100.121.155.55`.

On the phone:

1. Install Tailscale.
2. Sign in to the same authorized Tailscale network, or ask its administrator
   to grant access to the server.
3. Enable the Tailscale connection.

The phone and computer can use different Wi-Fi or mobile networks. Tailscale
access policies must allow the phone to reach the computer on TCP port 8000.

## 3. Allow connections through Windows Firewall

Run once in **Administrator PowerShell**:

```powershell
if (-not (Get-NetFirewallRule -DisplayName 'RESQPERATION API 8000' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule `
        -DisplayName 'RESQPERATION API 8000' `
        -Direction Inbound `
        -Action Allow `
        -Protocol TCP `
        -LocalPort 8000 `
        -RemoteAddress LocalSubnet,100.64.0.0/10 `
        -Profile Any
}
```

This permits local-subnet and Tailscale IPv4 connections to the API port.

## 4. Start Laravel — terminal 1

Open a regular PowerShell terminal:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel

$env:APP_DEBUG = 'false'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

Keep the terminal open. The `0.0.0.0` binding accepts connections through both
the LAN and Tailscale interfaces. Do not enter `0.0.0.0` as the phone's server
address.

If port 8000 is already running, do not start a duplicate server. Inspect it:

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen |
    Select-Object LocalAddress, OwningProcess
```

A listener on `0.0.0.0` accepts LAN/Tailscale connections. A listener only on
`127.0.0.1` accepts connections from the computer itself; restart that Laravel
instance using the command above.

## 5. Start the notification worker — terminal 2

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel

php artisan queue:work operations_outbox --queue=operations,broadcasts --sleep=3 --tries=3 --timeout=120
```

Keep this terminal open for queued dispatch notifications and broadcasts.
Skip starting another copy if the same worker is already running.

## 6. Start scheduled tasks — terminal 3

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel

php artisan schedule:work
```

Keep this terminal open for scheduled reminders and other scheduled work.
Use only one scheduler instance for this setup.

## 7. Test the API

On the computer:

```powershell
Invoke-RestMethod http://100.121.155.55:8000/api/v1/health/ready
```

With Tailscale connected on the phone, open this address in its browser:

```text
http://100.121.155.55:8000/api/v1/health/ready
```

Expected response:

```json
{"status":"ready"}
```

The phone browser check confirms the phone's network path; a successful check
on the computer alone does not verify phone connectivity.

## 8. Connect the installed APK

1. Open RESQPERATION.
2. On the sign-in screen, open **Server settings**.
3. Enter the following API address:

   ```text
   http://100.121.155.55:8000/api/v1
   ```

4. Tap **Save server address**.
5. Sign in with an existing household or rescuer account.
6. Allow notifications when prompted.

The saved address persists between launches. End active trips and sync saved
events before changing servers. Changing the server signs out the old session.

## Alternative: connect over the same Wi-Fi LAN

Tailscale is not required when the phone and computer share a reachable local
network. Keep Laravel running with `--host=0.0.0.0` and use this current LAN
address in the APK's **Server settings**:

```text
http://10.90.159.87:8000/api/v1
```

Test it in the phone browser first:

```text
http://10.90.159.87:8000/api/v1/health/ready
```

After changing Wi-Fi networks, find the computer's current Wi-Fi IPv4 address:

```powershell
ipconfig
```

Replace `10.90.159.87` in the saved API URL with that address. Never use
`localhost` or `127.0.0.1` on the phone: those point to the phone itself.
Use plain LAN HTTP only on a trusted local network.

## OneSignal requirements

- OneSignal is included and configured in the standalone APK.
- The phone must allow notifications and have the required Google Play Services.
- The phone and backend need internet access for push delivery, even if API
  access uses the LAN.
- Keep the notification worker and scheduler running for queued/scheduled work.
- Validate notification delivery on a test phone before broad distribution.

## Troubleshooting

| Problem | Check |
| --- | --- |
| Phone browser cannot open the health endpoint | Laravel is listening on `0.0.0.0`, the firewall rule is enabled, and the chosen IP is correct. |
| Tailscale connection times out | Tailscale is connected on both devices and access policies permit TCP 8000. |
| LAN connection times out | Both devices share the network; guest Wi-Fi or client isolation may block connections. |
| Health endpoint reports a database error | MySQL95 is running and the existing Laravel database configuration is correct. |
| APK still uses ngrok | Save the Tailscale or LAN address in **Server settings**. |
| Notifications do not arrive | Check Android permission, internet access, OneSignal/FCM configuration, worker and scheduler. |
| API stops working when the computer sleeps | Keep the computer awake while it acts as the server. |

## Each time you use the system

1. Keep the server computer awake and MySQL running.
2. Start Laravel, the notification worker, and the scheduler if they are stopped.
3. For Tailscale access, keep Tailscale connected on both devices.
4. Open the installed APK and sign in. No Metro server or QR scan is needed.
