# Tailscale and LAN connections

The existing standalone APK supports both. No new APK download is necessary.
On its sign-in screen, open **Server settings**, enter one of these addresses,
and tap **Save server address** before signing in:

| Connection | Server API address |
| --- | --- |
| Tailscale | `http://100.121.155.55:8000/api/v1` |
| Current Wi-Fi LAN | `http://10.90.159.87:8000/api/v1` |

Tailscale: install Tailscale on the phone and sign in to the same authorized
tailnet as this computer (or have its administrator grant access). Keep it
connected. The devices can use different Wi-Fi/mobile networks. Access policies
must permit this server's TCP port 8000.

LAN: connect both devices to the same network. Tailscale is not required. The
LAN address can change when the computer moves to another Wi-Fi; update Server
settings to `http://<computer-current-LAN-IP>:8000/api/v1` when that happens.
Do not use `localhost`, `127.0.0.1`, or `0.0.0.0` as the address on the phone.

The computer, database and API must remain running. Start Laravel with:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

The previous ngrok process was stopped. The API was restarted on `0.0.0.0`,
which accepts both Tailscale and LAN interfaces. The mobile release config now
defaults future APK builds to the Tailscale address. The existing build 6 still
has the old default until you save the address above; this setting persists
between launches. Changing servers requires ending/syncing pending trips first
and signing in again.

To check the API from the phone browser, open the chosen address with
`/health/ready` appended. It should show `{"status":"ready"}`. If it times out,
check Tailscale access policy, Wi-Fi client isolation and Windows Firewall's
inbound rules for PHP/TCP 8000. The workspace check cannot verify a phone's
network or firewall path.

OneSignal remains configured and independent of the API transport. The phone
and backend need internet access for push delivery, even when API access is LAN.
Tailscale encrypts its network path; plain LAN HTTP should be used only on a
trusted local network.
