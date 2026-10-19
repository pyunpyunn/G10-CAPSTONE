# RESQPERATION Android APK — Complete Group Setup Guide

Updated: **20 October 2026 (Asia/Manila)**.

Use this guide to install the standalone household/rescuer APK, connect phones
to the Windows server, run Laravel/MySQL and the HQ website, configure OneSignal,
test broadcasts/statuses, set up a new server, and build/share updates.
All commands use **Windows PowerShell**. Do not copy terminal prompt text.

## Contents

1. [Choose your setup](#1-choose-your-setup)
2. [Applications and connection details](#2-applications-and-connection-details)
3. [Install the APK and Tailscale](#3-install-the-apk-and-tailscale)
4. [Start the existing server](#4-start-the-existing-server)
5. [Connect the APK and log in](#5-connect-the-apk-and-log-in)
6. [Run the HQ website](#6-run-the-hq-website)
7. [OneSignal setup](#7-onesignal-setup)
8. [Test broadcasts and status buttons](#8-test-broadcasts-and-status-buttons)
9. [Daily startup and shutdown](#9-daily-startup-and-shutdown)
10. [LAN and the previous ngrok connection](#10-lan-and-the-previous-ngrok-connection)
11. [Set up a different server computer](#11-set-up-a-different-server-computer)
12. [Build and publish an updated APK](#12-build-and-publish-an-updated-apk)
13. [Permissions and optional services](#13-permissions-and-optional-services)
14. [Troubleshooting](#14-troubleshooting)
15. [Handover checklist and references](#15-handover-checklist-and-references)

## 1. Choose your setup

| Person | What to follow |
| --- | --- |
| Groupmate using only a phone | Sections 2–3 and 5; server owner runs section 4. Test section 8. |
| Person operating Kathleen's existing laptop | Sections 2–9. Preserve the existing database and `.env`. |
| Groupmate hosting another server | Section 11 first, then 3–9 with the new server's IP. |
| Developer building an APK | Section 12; needs existing Expo project/signing access. |

Phone-only users install **RESQPERATION** and **Tailscale** for remote access.
They do not need PHP, MySQL, Node.js, Android Studio, Expo Go, or source code.
The release APK includes its own application code and interface: **no QR scan,
Metro, `npm start`, or mobile frontend server is needed**. Expo is the build
framework. The same APK opens the household or rescuer console based on role.

## 2. Applications and connection details

| Item | Existing setup |
| --- | --- |
| Repository | https://github.com/pyunpyunn/G10-CAPSTONE |
| Project root | `C:\backend\G10CAPSTONE\resqperation-system` |
| Laravel directory | `C:\backend\G10CAPSTONE\resqperation-system\backend-laravel` |
| HQ web directory | `C:\backend\G10CAPSTONE\resqperation-system\frontend-web` |
| Mobile source directory | `C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile` |
| Server laptop Tailscale IP | `100.121.155.55`; verify with `tailscale ip -4` |
| APK API URL | `http://100.121.155.55:8000/api/v1` |
| Phone browser readiness URL | `http://100.121.155.55:8000/api/v1/health/ready` |
| HQ website on server laptop | `http://127.0.0.1:5175` |
| HQ website on another authorized device | `http://100.121.155.55:5175` |
| Operational database | `resq_local` |
| Windows MySQL service on original laptop | `MySQL95` |
| Android package | `com.kathlnbarro.resqperationmobile` |
| OneSignal App ID | `bc6266ac-90a4-4bfe-b4d5-59c57939e7a5` |

**Use the server IP in the APK, not the phone IP.** The phone previously had
`100.65.132.26`; that identifies the phone, not Laravel. Two `100.x` IPs do not
prove the devices are authorized on the same Tailscale network.

```text
Phone APK --Tailscale/LAN--> Laravel :8000 --> MySQL resq_local
HQ browser --> Vite :5175 --> Laravel :8000
Laravel --internet--> OneSignal/Google FCM --internet--> Android push
```

The phone never directly connects to MySQL. A local laptop database works with
a mobile-data phone because Laravel accesses it. The laptop must stay awake,
powered on, connected, and running its services. Do not expose MySQL port 3306
to phone users.

### Required software

| Device/task | Requirements |
| --- | --- |
| Android phone | Signed APK, browser, Tailscale for remote API access, functioning Google Play Services for this FCM setup |
| Existing laptop | Its configured PHP/Composer, MySQL and Tailscale; Node.js/npm for HQ web |
| New server computer | Git, PHP compatible with composer.lock (project requires `^8.3`), Composer, MySQL server/client, Node.js/npm matching package engines, Tailscale |
| APK builder | Node.js/npm, authorized Expo account/project access; Android SDK tools optional for inspection/USB install |
| Configuration owner | Authorized OneSignal/Firebase access; GitHub release access if publishing |

Official installers: [Tailscale](https://tailscale.com/download),
[PHP](https://windows.php.net/download/), [Composer](https://getcomposer.org/download/),
[Node.js](https://nodejs.org/en/download), [Git](https://git-scm.com/downloads),
[MySQL](https://dev.mysql.com/downloads/). Keep the repository lockfiles and
follow their platform/engine requirements.

## 3. Install the APK and Tailscale

### Choose the correct completed APK

The owner should supply the newest verified release. Build pages below must
show success before their APK is ready; this guide does not assert live status.

| Build | Purpose/link |
| --- | --- |
| 9 | App-code compression plus latest household fixes: [build/download](https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds/3363c586-fdab-455b-9500-3d185e0c686f) |
| 8 | Latest-alert/history modal and household refresh: [build/download](https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds/90ef47cd-4756-4d57-aaa9-a87703e9803d) |
| 7 | Foreground OneSignal fix: [build/download](https://expo.dev/accounts/kathlnbarro/projects/frontend-mobile/builds/c57f9990-8c30-4e35-99c1-a8268382b84d) |
| 6 | Older signed baseline: [GitHub APK](https://github.com/pyunpyunn/G10-CAPSTONE/releases/download/v1.0.0-build6/RESQPERATION.apk); lacks newer notification/UI fixes |

1. Download the selected completed APK on the phone.
2. Open Downloads and tap the file.
3. Allow the browser/file manager to **install unknown apps** if Android asks.
4. Install over the existing app using the same signing key for an update.
5. Preserve app data: uninstalling can remove server settings, offline maps,
   active trips and unsynced records.
6. Afterwards, the browser/file manager's installation permission can be disabled.

The release configuration targets **Android 7.0+ and arm64-v8a**. A 32-bit-only
phone cannot install this artifact. TECNO Spark 20 Pro 5G is the user's test
phone; other devices need testing.

Build 6 measured **24,303,289 bytes (24.3 MB / 23.18 MiB)**. The final build 9
size was not verified when compression was submitted. Installed storage is
larger than download size because of native extraction, caches/data and maps.
A filename alone does not identify a build: verify versionCode/size/hash.

### Join the authorized Tailscale network

1. Install **Tailscale** from Google Play.
2. Ask the owner to invite/authorize your account in the **same tailnet as the
   server**, or explicitly share/grant server access. Do not share personal passwords.
3. Sign in, approve Android's VPN prompt and enable **Connected**.
4. The owner verifies phone and server online in Tailscale's admin console and
   approves the phone if device approval is enabled.
5. Access policy must allow phone → server TCP **8000**. Remote HQ web also
   needs **5175**; optional Reverb web realtime uses **8090**.
6. Resolve competing VPN conflicts if Tailscale cannot stay connected.
7. Keep internet available on both devices for OneSignal/FCM.

The phone may use mobile data while the laptop uses Wi-Fi. No router port
forwarding, ngrok or exit node is required for this Tailscale setup.

## 4. Start the existing server

On the already-configured laptop, preserve `.env`, APP_KEY and the database.
Do not recreate the database or rerun setup seeders for daily startup.
Service/firewall commands work from any directory; Artisan commands need the
Laravel directory. Administrator rights are needed for service/firewall steps.

### A. Administrator PowerShell: MySQL and Tailscale

Open PowerShell using **Run as administrator**:

```powershell
Start-Service MySQL95
Get-Service MySQL95
tailscale up
tailscale status
tailscale ip -4
```

Expected: MySQL `Running`, Tailscale connected, server IP `100.121.155.55`.
For a different computer, discover its actual service name:

```powershell
Get-Service | Where-Object { $_.Name -match 'mysql|maria' } | Select-Object Name, Status
```

### B. Administrator PowerShell: allow API connections once

```powershell
if (-not (Get-NetFirewallRule -DisplayName 'RESQPERATION API 8000' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule `
        -DisplayName 'RESQPERATION API 8000' `
        -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8000 `
        -RemoteAddress LocalSubnet,100.64.0.0/10 -Profile Any
}
Get-NetFirewallRule -DisplayName 'RESQPERATION API 8000' | Select-Object DisplayName, Enabled, Action
```

If the existing rule is disabled:

```powershell
Enable-NetFirewallRule -DisplayName 'RESQPERATION API 8000'
```

This allows local-subnet/Tailscale IPv4 traffic. Tailscale policy still applies.

### C. Terminal 1, regular PowerShell: Laravel API

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
$env:APP_DEBUG = 'false'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
```

Keep open. `Server running on [http://0.0.0.0:8000]` means the listener started;
test DB/phone readiness separately. `0.0.0.0` accepts LAN/Tailscale connections
but is not an address to enter on the phone.

The earlier `$env:APP_ENV = 'production'` command is not required for this local
demo; keep the existing `.env` environment. `APP_DEBUG=false` hides debug details.
Before starting another API instance, check the existing listener:

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue |
    Select-Object LocalAddress, LocalPort, OwningProcess
```

Use an existing correct `0.0.0.0:8000` listener. If it is only `127.0.0.1`,
Ctrl+C in that server's own terminal and restart with the command above.

### D. Terminal 2: notification/operational worker

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan queue:work operations_outbox --queue=broadcasts,operations,weather --sleep=1 --tries=3 --timeout=120
```

Keep open for queued broadcasts, dispatch/operational jobs and weather. The
named database-backed connection uses the operational `jobs` table even if the
default `QUEUE_CONNECTION=sync`. Keep `operations_outbox` in the command.
Do not additionally start a duplicate hidden broadcast-worker helper.

### E. Terminal 3: scheduler

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan schedule:work
```

Keep one scheduler open. It schedules reminders, weather/criteria/navigation
work and queue drains. A continuous worker gives prompt queued delivery;
neither replaces the API.

Optional dedicated TrackingAid worker, if using that integration:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan queue:work trackingaid_outbox --queue=trackingaid --sleep=1 --tries=3 --timeout=120
```

The scheduler also drains this queue. TrackingAid needs its own configured
integration; it is not needed merely for mobile login/push.

### F. Readiness checks: laptop, then phone

In another PowerShell window:

```powershell
Invoke-RestMethod http://127.0.0.1:8000/api/v1/health/ready
Invoke-RestMethod http://100.121.155.55:8000/api/v1/health/ready
```

Then open this **in the phone browser**, with Tailscale connected:

```text
http://100.121.155.55:8000/api/v1/health/ready
```

Expected JSON contains `"status":"ready"`; additional fields may appear.
Laptop-only success does not verify the phone path. Fix connectivity before
diagnosing APK login.

## 5. Connect the APK and log in

1. Open **RESQPERATION**.
2. On the sign-in screen tap **Server settings**.
3. Enter the **Server API address**:

   ```text
   http://100.121.155.55:8000/api/v1
   ```

4. Tap **Save server address** and wait for **Server saved**.
5. Log in with a real household member ID/password or assigned rescuer account.
6. Allow notifications; keep open briefly while registration completes.
7. Complete household setup/location permissions if prompted. Rescuer dispatch
   and navigation require the appropriate team/device assignments.
8. Verify the intended household/rescuer console opens.

Use `/api/v1`, not `/health/ready`, `/broadcast`, or website port 5175.
Do not use `localhost`, `127.0.0.1`, `0.0.0.0` or the phone's IP. Saved settings
persist and override the embedded default. Changing a reachable API address
does not require rebuilding the APK.

Changing servers signs out the session and clears server-specific navigation
data. End trips/sync pending events first; active/unsynced navigation blocks
server changes.

### Original temporary household accounts

These exist in the original `resq_local`. Installing/cloning does not create
them. Another host needs the authorized backup or admin-created accounts.
**Use member ID as the username, not household ID.**

- Household: **Kathleen Barro Household**, **HH-2026-8561**.
- Head: **Kathleen Barro**.
- Original DB references: **purok_id 29 (Purok 3)**, **sitio_id 8 (Paglaum)**,
  **address_id 16**; IDs are specific to this database.
- Temporary password for these accounts: **`password`**. Demo use only;
  change before real use and share test access only with the group.

| Member login ID | Member | Category |
| --- | --- | --- |
| HM-000003 | Kathleen Barro | Head |
| HM-000004 | Alex Barro | Senior citizen |
| HM-000005 | Robin Barro | Senior citizen |
| HM-000006 | Jamie Barro | PWD |
| HM-000007 | Camille Barro | PWD |
| HM-000008 | Adrian Barro | PWD |
| HM-000009 | Mika Barro | Pregnant |
| HM-000010 | Sam Barro | Child |
| HM-000011 | Charlie Barro | Child |
| HM-000012 | Toni Barro | Child |
| HM-000013 | Dani Barro | Child |

Obtain separate authorized HQ/admin and rescuer credentials from the owner.
Household member accounts do not automatically have those roles.

## 6. Run the HQ website

This browser interface declares events/sends broadcasts; it is separate from
the installed mobile frontend. In another regular PowerShell terminal:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-web
npm ci
npx vite --host 0.0.0.0 --port 5175 --strictPort
```

Run `npm ci` initially/after dependencies change, not every launch. Leave Vite
open. Direct `npx vite` avoids the previous npm `Unknown cli config --host/--port`
warning. On the server laptop open **http://127.0.0.1:5175** and log in to HQ.
Select the active event through the Broadcast UI; an empty `?event_id=` does
not select an event.

For remote HQ access, authorize the device in Tailscale, allow 5175 in policy,
and run this once in Administrator PowerShell on the server:

```powershell
if (-not (Get-NetFirewallRule -DisplayName 'RESQPERATION HQ 5175' -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName 'RESQPERATION HQ 5175' `
        -Direction Inbound -Action Allow -Protocol TCP -LocalPort 5175 `
        -RemoteAddress LocalSubnet,100.64.0.0/10 -Profile Any
}
```

Open **http://100.121.155.55:5175** on that device. Existing Vite `/api` and
`/storage` proxies forward to Laravel on the server's `127.0.0.1:8000`.
Use the web `.env.example` if configuring its environment. The current web API
client converts a configured loopback API to a same-origin path for remote
browsers. Restart Vite after changing its environment.

## 7. OneSignal setup

### Existing application

The native SDK is in the APK; no separate OneSignal phone app is needed.
The mobile build and Laravel must use the same public App ID. Preserve the
private key already configured on the original server.

```dotenv
# backend-laravel/.env — server only
ONESIGNAL_APP_ID=bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
ONESIGNAL_API_KEY=OBTAIN_THE_EXISTING_APP_KEY_PRIVATELY
```

The placeholder is not a working credential. Never include the REST API key
or Firebase service-account private JSON in GitHub, this guide, the APK, or
`EXPO_PUBLIC_*`. Only the app ID is public mobile configuration.

After editing backend configuration:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan config:clear
php artisan queue:restart
```

`queue:restart` makes workers exit after their current job. With manual terminals,
**start the worker command again after it exits**; no supervisor restarts it
here. Restart the scheduler if its runtime configuration changed. A backend
REST-key change needs no APK rebuild; an embedded mobile App-ID change does.

### Owner configuration on a new environment

Do once for the intended application, not once per phone:

1. Obtain authorized access to the team's existing OneSignal/Firebase projects.
2. Verify Android package `com.kathlnbarro.resqperationmobile` and intended FCM project.
3. Follow the official credential guide below for the messaging API, service
   account and permissions required for **FCM v1**.
4. Configure **Google Android / FCM v1** in the same OneSignal app using its
   service-account JSON. Upload the private credential only to the authorized
   configuration, not the repository or APK.
5. Set the matching app ID and app REST API key in Laravel `.env`.
6. Keep the public app ID synchronized in mobile `eas.json` and
   `app.json`/`app.config.js`; rebuild if the embedded ID changed.
7. Install, log in, allow notifications and verify a subscribed device.

Follow [OneSignal FCM credentials](https://documentation.onesignal.com/docs/en/android-firebase-credentials)
and [SDK setup](https://documentation.onesignal.com/docs/en/react-native-sdk-setup)
for current console labels/requirements. Reuse the configured app/project rather
than creating a new one for each groupmate.

### Verify without sending a push

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan onesignal:status
```

Inspect `api_authenticated`, `android_fcm_v1_configured`, and
`locally_registered_android_subscriptions`. At least one eligible device is
needed for testing. This command does not send a push or prove receipt.

In OneSignal Audience/Subscriptions, check an enabled Android subscription.
Login associates the application user ID with OneSignal. Permission alone
does not prove Laravel has a valid subscription ID/token. After fixing a
permission issue, log in again and let registration finish with a working API.
Logout intentionally opts the current push identity out.

Both devices need internet for OneSignal/FCM even with LAN API access. Tailscale
connects to Laravel, not an alternative FCM service. Check Android permission,
notification categories, sound/banner settings, DND, Google Play Services and
OEM battery restrictions when diagnosing failures.

**Accepted is not confirmed device receipt.** Inspect delivery details/failures
and confirmed receipts where supported. Check the notification shade even if
there was no heads-up banner.

## 8. Test broadcasts and status buttons

Use agreed test accounts/events and inform the group before sending test alerts.

1. Verify phone-browser readiness and APK login.
2. In HQ select the active event, or declare the intended test event if needed.
   Do not close a real event for testing.
3. Enter title and official instruction.
4. Choose **exactly four distinct statuses in the required order**. Configure
   custom labels/safety classification through the existing HQ controls.
5. Select barangay-wide or the household's actual area. A household outside a
   targeted area should not receive that area's options/alert.
6. Send a unique broadcast while the app is open.
7. Expand the Android notification; check title, instruction,
   **Please report status now**, and the four exact labels/order.
8. Verify the household's four clickable buttons match the latest applicable
   broadcast. These are **in-app buttons**, not four Android notification actions.
9. Select/save a status; verify success and the corresponding HQ record.
10. In build 8/9, verify one latest alert on the home card; the top-right history
    icon opens earlier broadcasts in a modal.
11. Press Home, send another unique test, verify background receipt, then tap it
    and verify refresh.

Household data refreshes on disaster push, resume and every 30 seconds while
the household screen is active. Pull to refresh for an immediate API check.
The API fallback may update list/buttons when push is delayed; it does not
generate or prove Android push receipt. Old Android notifications are not
rewritten by server changes.

The live overview/submission paths were corrected to use broadcast-aware
options instead of hardcoded defaults. The original DB received the specific
`2026_10_11_000003_add_status_safety_and_broadcast_options.php` migration.

Developer regression commands:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan test --filter='DisasterBroadcastPushTest|HouseholdMobileStatusWorkflowTest'
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm run check
node --test tests/foregroundNotification.test.cjs tests/householdAlerts.test.cjs
```

Recorded result: **19 backend tests, 7 mobile tests and TypeScript passed**.
Real-phone tests are still part of release acceptance.

## 9. Daily startup and shutdown

### Startup

1. Plug in the server, connect internet and prevent sleep while hosting through
   Windows Settings → System → Power & battery.
2. Start MySQL; connect Tailscale on laptop and phone.
3. Start API, operational worker and scheduler in separate terminals.
4. Start HQ Vite only when using the browser console.
5. Check readiness on the phone, open the APK and log in.
6. Test one notification before a demonstration.

There is **no frontend-mobile launch command** for the installed APK. Service/
firewall commands work from any directory. Artisan runs in `backend-laravel`;
HQ npm/Vite runs in `frontend-web`.

### Shutdown

1. End navigation trips and sync pending records while the API is reachable.
2. Finish queued work before ending the demo.
3. Ctrl+C in manually running Vite/worker/scheduler/API terminals.
4. Leave services/server awake if groupmates still need live access.
5. MySQL/Tailscale may remain running. If intentionally stopping the dedicated
   demo DB after all users finish, Administrator PowerShell: `Stop-Service MySQL95`.

Avoid duplicate listeners/schedulers. Inspect PHP jobs with:

```powershell
Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' } |
    Select-Object ProcessId, CommandLine
```

## 10. LAN and the previous ngrok connection

For the same reachable Wi-Fi/LAN, Tailscale is not required for API access:

1. Run `ipconfig` on the server.
2. Find its current connected Wi-Fi/Ethernet adapter's IPv4 address.
3. Test `http://SERVER-LAN-IP:8000/api/v1/health/ready` in phone browser.
4. Save `http://SERVER-LAN-IP:8000/api/v1` in the APK.

The previous LAN IP was `10.90.159.87`; it changes with networks. Keep Laravel
on `0.0.0.0` and the scoped firewall enabled. Guest Wi-Fi/client isolation/VLANs
can block access. Plain LAN HTTP is for a trusted demo network; remote use
should use authorized Tailscale or properly deployed HTTPS.

The earlier ngrok URL was `https://sturdily-morse-sitter.ngrok-free.dev`.
Tailscale replaced it. Do not run ngrok/use that old address just because an
older APK remembers it: save the current API URL. If the owner later chooses
HTTPS hosting/tunneling, configure and verify that endpoint separately first.

## 11. Set up a different server computer

Most groupmates should use the existing shared server. A different host has a
different IP, database, service name, paths and private credentials.

### A. Install tools and obtain updated source

Install section 2's tools; reopen PowerShell after PATH changes.

```powershell
New-Item -ItemType Directory -Force C:\backend\G10CAPSTONE | Out-Null
cd C:\backend\G10CAPSTONE
git clone https://github.com/pyunpyunn/G10-CAPSTONE.git resqperation-system
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php --version
composer --version
node --version
npm --version
mysql --version
composer install
composer check-platform-reqs
```

Use an approved source revision containing all fixes. **Git clone does not copy
uncommitted changes from the owner's PC.** The owner supplies the updated source
through an authorized commit/private handover. A local APK also does not become
a GitHub release asset until explicitly uploaded.

For PHP errors, inspect `php --ini`/`php -m` and enable Composer's required
extensions, including the MySQL PDO driver. For unrecognized tools, install/fix
PATH and reopen the terminal.

### B. Obtain and restore the authorized database

Source does not contain current household/member/admin records or private
runtime configuration. Obtain an authorized `resq_local` backup. Migrations
alone are not a populated operational DB; do not invent reference IDs.

If the owner needs an export, use the actual MySQL user on the original server.
This prompts for a password and avoids PowerShell output-encoding conversion:

```powershell
New-Item -ItemType Directory -Force C:\RESQPERATION\backups | Out-Null
mysqldump --host=127.0.0.1 --port=3306 --user=root --password --single-transaction --routines --triggers --result-file=C:\RESQPERATION\backups\resq_local.sql resq_local
```

Use a new filename if retaining an earlier backup. Transfer privately; the SQL
contains personal/account records. Do not commit DB exports.
On the new host, start its actual MySQL service, copy the backup to the shown
path and restore into a **new, empty database**:

```powershell
mysql --host=127.0.0.1 --port=3306 --user=root --password --execute="CREATE DATABASE IF NOT EXISTS resq_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --host=127.0.0.1 --port=3306 --user=root --password --database=resq_local --execute="source C:/RESQPERATION/backups/resq_local.sql"
```

Check both commands succeed. Do not import over an in-use DB without an agreed
backup/restore plan. PowerShell does not support Unix `mysql ... < file.sql`;
the example uses the MySQL client's `source` command.

### C. Configure this new Laravel installation

Create `.env` only if absent:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
if (-not (Test-Path -LiteralPath .env)) { Copy-Item .env.example .env }
notepad .env
```

Use actual values; the following are placeholders, not working credentials:

```dotenv
APP_NAME=RESQPERATION
APP_ENV=local
APP_DEBUG=false
APP_URL=http://NEW-SERVER-TAILSCALE-IP:8000
APP_TIMEZONE=Asia/Manila
DB_CONNECTION=resq_local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=resq_local
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD
RESQ_LOCAL_DB_CONNECTION=mysql
RESQ_LOCAL_DB_HOST=127.0.0.1
RESQ_LOCAL_DB_PORT=3306
RESQ_LOCAL_DB_DATABASE=resq_local
RESQ_LOCAL_DB_USERNAME=YOUR_DB_USER
RESQ_LOCAL_DB_PASSWORD=YOUR_DB_PASSWORD
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
ONESIGNAL_APP_ID=bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
ONESIGNAL_API_KEY=OBTAIN_PRIVATELY_FROM_OWNER
```

`RESQ_LOCAL_DB_*` overrides corresponding `DB_*` when provided; keep both sets
consistent, especially database/user/password. Retain other authorized settings.

For a **new app installation with no APP_KEY**, generate it once:

```powershell
php artisan key:generate
php artisan config:clear
php artisan storage:link
php artisan migrate:status
```

Preserve APP_KEY for an existing installation/restoration requiring its old key.
Review migrations with the owner; do not run `migrate:fresh`, `db:wipe`, blanket
seeders or reset commands against shared data. Pending unrelated work may exist.
If this specific status migration is missing, apply the scoped reviewed file:

```powershell
php artisan migrate --path=database/migrations/2026_10_11_000003_add_status_safety_and_broadcast_options.php --force
php artisan config:clear
php artisan cache:clear
```

It is already applied on the original server. Navigation needs its reviewed
000030–000033 migrations; compare backup migration history and the navigation
progress guide before applying changes.

A database backup does not include photo/audio uploads. Obtain authorized
required `storage/app` files, restore the same layout, and retain the public
storage link. External services/map packs need their own configuration.

### D. Connect and run

Join the authorized Tailscale network on the new host; obtain `tailscale ip -4`.
Repeat sections 4–8 with that IP and the correct local service/path values.
Update each phone's Server settings. Use `100.121.155.55` only for the original
server, not every groupmate's separate laptop.

## 12. Build and publish an updated APK

Phone users do not need build tools. Builders need access to the existing
`@kathlnbarro/frontend-mobile` project and remote signing credentials.

### Prepare/build

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
npm ci
npm run check
node --test tests/foregroundNotification.test.cjs tests/householdAlerts.test.cjs
npx eas-cli login
npx eas-cli whoami
npm run build:android
```

Keep lockfiles and the existing project/key. Do not create a different project
just because another groupmate builds it. The production profile supplies:

```dotenv
EXPO_PUBLIC_API_BASE_URL=http://100.121.155.55:8000/api/v1
EXPO_PUBLIC_ONESIGNAL_APP_ID=bc6266ac-90a4-4bfe-b4d5-59c57939e7a5
```

These are public configuration. Change the default URL in `eas.json` for a new
intended host; saved phone settings override it. Never embed private credentials.
For an already authenticated/configured builder, submit without waiting:

```powershell
npx eas-cli build --platform android --profile production --non-interactive --no-wait
npx eas-cli build:list --platform android --limit 3
npx eas-cli build:view YOUR-BUILD-ID
```

Replace `YOUR-BUILD-ID`. Wait for **FINISHED**; queued/running is not a completed
release. EAS increments versionCode and signs using the existing remote key.
The source upload (~710 KB in recent submissions) is not the APK download size.
Root `.easignore` excludes secrets, backend/web, dependencies/generated natives
and release files from the source upload.

### Download and verify

Download the finished APK. If using the local handover convention, save the
verified artifact as `frontend-mobile/releases/RESQPERATION.apk` (ignored by Git).

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\frontend-mobile
Get-Item .\releases\RESQPERATION.apk | Select-Object Name, Length
Get-FileHash .\releases\RESQPERATION.apk -Algorithm SHA256
```

With Android SDK tools, adjust local tool paths/version and inspect:

```powershell
& "$env:LOCALAPPDATA\Android\Sdk\build-tools\35.0.0\apksigner.bat" verify --print-certs .\releases\RESQPERATION.apk
apkanalyzer manifest version-code .\releases\RESQPERATION.apk
apkanalyzer manifest debuggable .\releases\RESQPERATION.apk
```

Build 6 signer certificate SHA-256, for update-compatibility comparison:

```text
e1e826a1d0d11684fc0a526f8c1743df498a5b469aa8e1c78ede6beba43d6031
```

Build 6 **file hash**, not the file hash for builds 7–9:

```text
ac968ae871baf84e84066c658b8e28a2b18b2658c09918cb66f99167d3dcc91c
```

Optional USB installation: enable Developer options/USB debugging, connect the
phone and approve the laptop's debugging prompt, then:

```powershell
adb devices
adb install -r .\releases\RESQPERATION.apk
```

Keep package/signing key unchanged for updates. Perform section 8 and the
household/rescuer map/audio/photo/QR/navigation smoke tests. Signing/config
checks do not prove actual phone delivery or all feature behavior.

### Publish on GitHub

An authorized publisher should upload only a verified artifact. Example after
build 9 finishes/passes testing; substitute its actual source commit and a real
notes file, and confirm the local APK really is build 9:

```powershell
gh auth login
gh release create v1.0.0-build9 .\releases\RESQPERATION.apk --repo pyunpyunn/G10-CAPSTONE --target VERIFIED-SOURCE-COMMIT-SHA --title "RESQPERATION 1.0.0 build 9" --notes-file .\release-notes.md
```

Record versionCode, bytes, SHA-256, ABI, default API, changes and real-device
results. Verify the uploaded asset before updating README download links.
Do not silently replace an old release binary with another build. EAS artifacts
can expire; GitHub release hosting is a separate publishing step.

Google Play uses an AAB instead of this direct-install APK:

```powershell
npm run build:android:store
```

### Size settings

Release configuration keeps Hermes, arm64 only, R8 minification, resource
shrinking, compressed native libraries and (build 9) app-code compression.
No maps/audio/photo/QR/push features were removed for compression. Startup may
be slightly slower with bundle compression.

Build 6's app bundle was 3,571,080 uncompressed bytes; local deflate produced
1,679,386 bytes (~1.9 MB potential savings). This is **not a measured final
build 9 size**. Record the actual finished artifact. No mandatory extra
app-resource/OTA download was added. API data/photos/online tiles still need
network; optional offline maps add download/storage. Measure RAM/startup/battery
on the actual low-spec phones.

## 13. Permissions and optional services

### Android permissions

- Notifications: disaster/dispatch notifications.
- Location while in use: geotagging/maps/navigation.
- Background location/foreground trip service: active navigation recording;
  end the trip so GPS recording stops.
- Microphone/audio: radio/audio features.
- Photos/camera: household setup/photo workflows when requested.

Grant permissions for the feature being used; Android/OEM labels differ.
Stay logged in for push identity registration.

### Maps/offline navigation

Online maps/routes need their configured providers/network. For a Google Maps
build, `app.config.js` reads `EXPO_PUBLIC_GOOGLE_MAPS_ANDROID_API_KEY` when supplied.
Use an appropriately restricted valid key and test maps on the signed APK.

Rescuer offline navigation needs authorized dispatch/team/device, SQLite,
MapLibre, route/pack services and location permissions. Prepare optional packs
on Wi-Fi before field use; a full map is not bundled into every APK. Initial
login/new server data need connectivity.

See [OFFLINE_NAVIGATION_PROGRESS.md](../docs/OFFLINE_NAVIGATION_PROGRESS.md)
and `deploy/tiles` for tile/route deployment, artifact compatibility and remaining
phases. Docker/Valhalla/tiles are not required merely for login/push, but full
offline navigation requires its deployed services. Outstanding phases/device
measurements are not claimed complete by this setup guide.

### HQ realtime/Reverb, if enabled

Web realtime is separate from Android OneSignal. The configured helper is:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
php artisan realtime:status
.\start-realtime-notifications.ps1
```

It starts hidden Reverb on 8090 and the realtime relay, records PIDs in
`storage/app/realtime-processes.json`, and logs under `storage/logs`. Avoid a
second Reverb listener on that port. Match backend settings with web
`VITE_REVERB_*`; keep Reverb's app secret server-side. Remote realtime clients
need 8090 allowed through Tailscale and a scoped Windows Firewall rule.
Redis is needed only for an explicitly configured Redis-backed setup; inspect
the helper's `-Queue`/`-QueueConnection` options.

### SMS/external integrations

OneSignal push is not SMS. SMS also requires the configured gateway URL,
credentials and operating gateway/SIM/provider. TrackingAid, SafeTrack,
EvaTrack and reports need their authorized external services/configuration.
Installing the APK does not create those systems. Obtain owner-provided settings
and test each integration needed for the demo separately.

## 14. Troubleshooting

Check in order: **server → DB readiness → phone browser → APK URL → login →
subscription → actual push → in-app refresh/status submission**.

| Symptom | Check/action |
| --- | --- |
| MySQL95 not found | Discover actual MySQL/MariaDB service name; MySQL95 is specific to the original PC. |
| Service/firewall access denied | Use Administrator PowerShell for those steps. |
| `Could not open input file: artisan` | Run in backend-laravel, not root/frontend/Visual Studio directory. |
| Command unrecognized | Install tool/fix PATH/reopen terminal. |
| Port 8000 in use | Inspect listener; use the correct running instance or stop its own terminal, not arbitrary processes. |
| Laravel running but phone times out | Check 0.0.0.0 binding, actual server IP, tailnet authorization/policy, firewall and awake laptop. |
| ERR_NETWORK_CHANGED | Let mobile/VPN switching stabilize; confirm Tailscale connected, resolve competing VPNs, reload readiness. Not proof of a Laravel/DB error. |
| Both IPs start with 100, no access | Verify actual shared tailnet/server authorization and TCP 8000 policy. |
| Tailscale diagnostic | Laptop: tailscale status, tailscale ip -4, tailscale ping PHONE-TAILSCALE-IP. Ping alone does not prove Laravel port access. |
| Laptop works, phone fails | Test readiness on phone; loopback is a different network path. |
| DB connection/SQLSTATE errors | Check running MySQL, port/user/password/tables, both DB_* and overriding RESQ_LOCAL_DB_*; clear config after edits. |
| APK cannot connect | Correct saved /api/v1 URL, port 8000; test its /health/ready in phone browser. |
| APK remembers ngrok | Save current Tailscale/LAN API on sign-in screen. |
| Invalid credentials / 401 | Real member username/password from this DB; household ID is not the member login. |
| 403 / device not linked | Verify household linkage or rescuer team/device authorization. |
| APK cannot install/update | Check Android/arm64, full download/storage/install permission, package/version and signer. Do not immediately uninstall unsynced work. |
| APK size differs | Check build/hash; installed storage differs from APK download. Build 6 size is not every release's size. |
| HQ fails remotely | Bind Vite 0.0.0.0; allow 5175; use server IP, not remote-device localhost. |
| npm --host/--port warning | Use direct npx vite command in section 6. |
| No subscribed devices | Matching app ID/FCM, login/permission, enabled token/subscription, backend registration, role/platform and area; run onesignal:status. |
| Accepted but phone silent | Check delivery details, Google services/internet, Android categories/DND/shade; test foreground/background. |
| Old APK misses foreground push | Install updated handler build; restarting Laravel does not replace embedded APK code. |
| Push text new, buttons old | Verify updated live overview/submission paths, correct event/area and refresh. |
| Custom status cannot save | Current selected option, updated submission workflow, migration and safety/status records. |
| Old alert-history layout | Install build 8/9 or newer UI; backend changes cannot replace embedded UI. |
| Worker idle | Check connection/queue/jobs/failed jobs; no work can legitimately mean idle. |
| Worker stopped after queue:restart | Rerun manual worker; no supervisor restarts it in this setup. |
| Reminders missing | Keep one scheduler and operational processing running; check active event/eligibility. |
| Offline navigation unavailable | Authorized dispatch, GPS, compatible downloaded pack and route engine; navigation progress guide. |
| Laptop sleeps | Wake/connect and keep services running; API is unavailable while server sleeps. |

Useful diagnostics in `backend-laravel`:

```powershell
php artisan onesignal:status
php artisan migrate:status
php artisan queue:failed
php artisan schedule:list
Get-Content .\storage\logs\laravel.log -Tail 80
```

Logs can contain personal data; redact before outside sharing. Diagnose failed
jobs before retrying to avoid unintended duplicate messages/actions.

## 15. Handover checklist and references

The owner supplies these through authorized team sharing:

- [ ] Verified latest APK, build number, bytes, hash and working download.
- [ ] Matching updated source for another backend host.
- [ ] Actual server IP/hostname and approved Tailscale access.
- [ ] Readiness response verified on each phone.
- [ ] Household/rescuer test credentials and separate HQ credentials.
- [ ] Authorized DB/storage backup for a new host only.
- [ ] Private OneSignal/Firebase/integration configuration access as needed.
- [ ] Person responsible for keeping API/worker/scheduler/laptop running.
- [ ] Foreground/background notification tests completed.
- [ ] Four exact buttons verified and configured status saved successfully.
- [ ] Latest-alert card/history modal verified on installed build.
- [ ] Required maps/location/audio/photos/QR/navigation checks completed.
- [ ] Daily startup/sleep/network change/shutdown understood.

Project references:

- [Short first-run commands](./FIRST_RUN_SETUP.md)
- [Tailscale/LAN reference](./TAILSCALE_LAN.md)
- [Notification verification](./NOTIFICATION_VERIFICATION.md)
- [Offline navigation progress](../docs/OFFLINE_NAVIGATION_PROGRESS.md)

Official references:

- [Tailscale Windows](https://tailscale.com/docs/install/windows)
- [Tailscale Android](https://tailscale.com/docs/install/android)
- [Tailscale device access](https://tailscale.com/docs/use-cases/personal-or-at-home-use/access-nas-media-file-servers)
- [OneSignal Firebase credentials](https://documentation.onesignal.com/docs/en/android-firebase-credentials)
- [OneSignal SDK setup](https://documentation.onesignal.com/docs/en/react-native-sdk-setup)
- [OneSignal delivery/display troubleshooting](https://documentation.onesignal.com/docs/en/notifications-show-successful-but-are-not-being-shown)

This is the local/Tailscale group demo setup. Artisan serve/Vite are demo and
development servers. Permanent public hosting needs supervised services, HTTPS,
access controls, backups and its own deployment plan.
