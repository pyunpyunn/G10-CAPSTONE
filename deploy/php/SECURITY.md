# PHP runtime security update

PHP2Shell affects the PHP runtime, potentially before Laravel executes. Application
middleware cannot replace a patched runtime.

The container uses PHP 8.3.36, which fixes CVE-2026-109618 and CVE-2026-109619.
The included security.ini disables session upload progress before request parsing,
also applying PHP's mitigation for CVE-2026-115560.

Rebuild and recreate all PHP services from the repository root:

```powershell
docker compose build --pull app queue-worker scheduler
docker compose up -d --force-recreate app queue-worker scheduler
docker compose exec app php -v
docker compose exec app php -r 'var_export(ini_get("session.upload_progress.enabled"));'
```

If the navigation profile is used, also rebuild and recreate navigation-worker.
Verify each PHP service's version and setting. Existing containers are not patched
by changing the Dockerfile alone.

For Windows development or non-container hosting, update every PHP installation
and web-server handler. Use official Windows PHP 8.5.12-1 / 8.4.27-1 / 8.3.36-1
packages or newer security releases; the -1 rebuild also updates vulnerable Brotli.
Match the existing architecture and TS/NTS server requirements, preserve needed
extensions, apply security.ini to the loaded configuration, and restart all web
servers and workers. Verify the web runtime separately from the CLI. Do not expose
an unpatched development server publicly.

Keep supported PHP and dependency releases updated. These changes address the
identified runtime risks; they do not certify the entire application as secure.

Official advisory: https://www.php.net/archive/2026.php
