# Automatic web dashboard startup

The web dashboard now starts automatically on the server computer. No terminal commands are needed for normal use.

## Open the dashboard

Connect the viewing device to Tailscale and open:

```text
http://100.121.155.55:5175
```

Mapping page:

```text
http://100.121.155.55:5175/mapping
```

Keep the server computer powered on, awake and online. The existing Laravel API, queue worker, MySQL and Tailscale automatic startup setup is shared with the mobile app. No APK rebuild is required.

## What runs automatically

Windows Task Scheduler runs **RESQPERATION Web Dashboard** under the built-in **LOCAL SERVICE** account at startup (20-second delay) and logon. It launches the existing Vite development server on port 5175 with a strict port setting, retaining its API and storage proxies to Laravel on port 8000. Frontend source edits continue to use Vite's normal refresh behavior.

A supervisor restarts an exited Node process after five seconds. Windows retries a failed supervisor task after one minute, and duplicate task instances are ignored. Output and errors are saved under:

```text
C:\backend\G10CAPSTONE\resqperation-system\backend-laravel\storage\logs\mobile-backend
```

Look for `Web-supervisor.log`, `Web-*.stdout.log` and `Web-*.stderr.log`. Installation output is in `storage\logs\web-dashboard-install.log`.

## Check all three tasks

In PowerShell:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\get-mobile-backend-status.ps1
```

The output should show `api_ready: true`, `web_ready: true` and running processes for Api, Queue and Web. Do not run a second `npm run dev` while the dashboard task is running.

## Reinstall if paths change

Run once in **PowerShell as administrator**:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\windows\install-web-dashboard.ps1 -TestRecovery
```

Node.js and the frontend's installed dependencies are required. The installer configures the task, service-account cache access and Tailscale firewall access on port 5175, then verifies readiness and the Laravel API proxy.

Startup triggers and automatic Node-process recovery were verified on 23 October 2026. A full computer reboot was not performed during setup.
