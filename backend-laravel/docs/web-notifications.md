# Web notifications

## Strict notification archive

The application mirrors supported web feed events into the shared `notifications`
table only when every original field and the archive metadata have real values.
This strict policy was explicitly requested: do not substitute dates, evacuation
relationships, recurrence types, or provider IDs. Missing/inapplicable fields pause
the archive record; its existing inbox entry and delivery behavior remain intact.
One-time notices normally cannot satisfy this policy because no recurrence end or
recurrence type exists. Web-only notices also have no OneSignal message ID.

Migration `2026_10_28_000001_add_notification_archive_fields.php` adds a unique
`source_key`, `onesignal_id`, `onesignal_ids_json` (all provider message IDs for a
notice), and `feed_payload_json`. The columns remain nullable for compatibility
with other applications sharing this table; the archive writer validates every
field before inserting. Existing unrelated rows are not rewritten or deleted.
It also adds the broadcast provider-ID/error/count columns already supported by
the delivery workflow, so future push submissions retain their real provider IDs.
Provider acceptance must not be interpreted as delivery to a phone.

Run `php artisan notifications:archive --recover-onesignal --dry-run` to inspect
history without database writes, then omit `--dry-run` to save recovered IDs and
eligible notices. The command reads OneSignal message history using the configured
backend credentials; it never sends/replays a notification. Explicit broadcast
and disaster-event metadata must match. Operational log IDs are also recovered;
automated-test entries and placeholder IDs are excluded. API failures are reported
without printing credentials or response bodies. Recovery is repeatable.

The report is `storage/app/private/notification-backfill-report.json`; it lists
every paused source ID, validation failures, recovered message IDs, provenance,
and API completeness. It is private and must not be served from public storage.
Feed reads and browser-push worker reads run strict archiving; the scheduler also
runs `notifications:archive` each minute. Existing historical push cutoffs and
stable source IDs are retained. Complete unchanged notices produce no extra writes.
This does not import all OneSignal messages indiscriminately: unrelated mobile
messages, OTPs, and ambiguous matches do not become web notifications.

The inbox, bell, and browser service worker accept only these six categories:

- Disaster broadcast posts (`Broadcast`). Legacy outgoing SMS records are excluded.
- Rescue dispatch status updates (`Dispatch status`), including mobile changes.
- Dispatch logs (`Dispatch log`): assignment creation, update, completion, and mobile assignment updates. GPS samples, account changes, and unrelated audits are excluded.
- Resource requests awaiting approval (`Approval request`): pending/needs-validation requests from every source, without an inbox count limit. Processed requests disappear from this queue.
- Official PAGASA advisory sync changes (`PAGASA advisory sync`). Ordinary weather snapshots are excluded.
- Official PHIVOLCS earthquake bulletin sync changes (`PHIVOLCS advisory sync`). These sync notices describe nationwide source updates, not a local earthquake warning.

Existing role/module permissions still limit which categories each user may read. Notification permission does not grant access to other modules.

## Delivery repair

Dispatch notifications come from dated, saved dispatch audit actions. Assignment rows alone do not create assignment announcements on the web. Undated legacy rows are excluded; the presenter never substitutes the current time. One saved action has one stable notification ID. Status changes are classified as dispatch status updates; creation and other saved dispatch activity are dispatch logs.

The audit model preserves the timestamp passed by write workflows. Undated legacy clear/read markers retain explicit selected IDs but cannot acquire a moving "now" cutoff that would hide new notifications.

Browser registration is coalesced across simultaneous focus/mount events and successful registration is remembered for six hours per login. A 429 response applies a Retry-After cooldown instead of issuing more requests on each focus.

Migration `2026_10_21_000002_add_web_push_event_cutoff.php` establishes a fresh delivery cutoff for existing subscriptions. Historical records stay in the inbox, but they are not replayed as pushes after the repair. Re-registering the same browser/account does not reset this cutoff. Browser push payload format 2 rejects queued old-format messages; the service worker also remembers displayed event IDs to suppress duplicate delivery.

The bell checks the notifications module permission, including for the chair account. Its scrollable popover contains the full role-scoped feed, and `/notifications` paginates that same feed. Both share reads, read state, and refresh events after real actions. Regression checks use an isolated SQLite database, mocked transport, and mocked browser API responses; they do not send test messages to live users.

## Delivery and permission

Browser Web Push uses backend VAPID keys and a service worker. It does not depend on the page remaining active. Browsers can receive pushes with a background tab, and supported browser/platform configurations can wake the service worker after the tab closes. Force-quit browsers, OS notification blocks, offline devices, and focus/quiet modes can delay or suppress presentation.

Sign in on the secure web address, then click **Enable web notifications** above **Logout** in the sidebar and approve the browser permission prompt. The control displays a bell icon and its label, with guidance in its tooltip. On an insecure address it links to the secure site; if permission was blocked, clicking it explains how to enable the site's browser permission. The control disappears after successful setup. Logout removes the browser subscription; the backend also stops delivery when the access token expires/is revoked or the user's module access is removed. First subscription establishes a baseline instead of replaying the old inbox. Successfully delivered notices are deduplicated; HTTP 404/410 subscriptions are retired.

Remote plain-HTTP IP addresses cannot use Push/Service Worker APIs. `http://localhost:5175` is the browser's local-development exception. Remote devices must use HTTPS. The configured Tailscale currently disallows Serve, so the local setup uses a Cloudflare quick tunnel. Run:

```powershell
cd C:\backend\G10CAPSTONE\resqperation-system\backend-laravel
./start-secure-web.ps1
```

This starts/reuses the secure tunnel, records `VITE_PUBLIC_WEB_URL` in the web `.env`, starts Vite on port 5175 if needed, and starts the dedicated notification worker. Laravel must also be running on port 8000. Vite proxies `/api` to Laravel, keeping browser traffic on the HTTPS origin. The tunnel must remain running. A recreated quick tunnel has a new URL and browsers must grant permission on that new origin. A permanent HTTPS domain or enabled Tailscale Serve avoids that limitation. Quick tunnels expose the login page to the public internet; API authentication and existing authorization remain required.

## Backend deployment

Run `composer install`, the migration `2026_10_21_000001_create_web_push_tables.php`, then `php artisan web-push:keys` once. Keep the private key only in backend `.env`. Preserve keys across deployments so browser subscriptions remain valid. Configure `WEB_PUSH_SUBJECT` as the deployment contact URI. On Windows, set `OPENSSL_CONF` to PHP's `extras/ssl/openssl.cnf` before starting PHP for EC key generation; the worker startup script does this.

Run `./start-web-notifications.ps1` (Windows), or use Laravel's scheduler for `web-push:deliver` every ten seconds and `advisories:sync` every five minutes. Use one deployment mechanism; the worker's delivery lock also prevents concurrent duplicate sends. Stop/restart the dedicated worker after changing PHP notification code. Failures are logged; failed deliveries remain eligible for retry. Official sync failures do not create success notifications or erase previous advisories. Upstream content changes create notices; unchanged syncs do not spam the inbox.

Windows PHIVOLCS fetching can use Schannel through the system `curl.exe` if PHP's certificate bundle cannot build the official site's certificate chain. Certificate verification remains enabled.

Sources: [Browser Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API), [PHP Web Push](https://github.com/web-push-libs/web-push-php), [PAGASA official feed](https://publicalert.pagasa.dost.gov.ph/feeds/), [PHIVOLCS official bulletins](https://earthquake.phivolcs.dost.gov.ph/).
