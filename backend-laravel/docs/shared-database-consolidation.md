# Shared database consolidation

The shared database is named `klint`. Existing table names, primary IDs, credentials, and business records remain authoritative. The upgrade only adds columns, indexes, enum options, and new tables. It does not drop or rename tables, delete records, reset passwords, or renumber shared members.

| Local-only table | Shared implementation |
| --- | --- |
| `devices` | `device_tokens`: reuse device identity and push registration; add `team_id` and `last_sync_at` |
| `household_device_owners` | `device_tokens`: existing household/member links plus `owner_selected_at` and `selection_token_id` |
| `household_login_accounts` | `households.login_user_id`; credentials remain in `users` |
| `household_member_id_mappings` | `household_members.legacy_member_id`; preserve current shared member IDs |
| `household_password_recovery_otps` | `password_reset_requests`: isolated `sms_otp` verification method and hashed, expiring OTP state |
| `team_bases` | `rescue_teams.base_name`, `base_latitude`, `base_longitude` |
| `official_advisory_notifications` | `notifications`: advisory source, fingerprint, title, URL, and existing message/time columns |
| `cached_routes` | Add: reusable routing cache differs from a dispatch's planned `responder_routes` |
| `dispatch_trips` | Add: actual trip sessions/statistics differ from planned routes |
| `trip_points` | Add: recorded trip samples differ from planned `route_coordinates` |
| `sync_events` | Add: durable per-device event acknowledgments differ from expiring request idempotency |
| `offline_packs` | Add: versioned downloadable pack manifests differ from individual `map_tile_metadata` tiles |
| `mapping_hazard_layers` | Add: assessed GeoJSON layers differ from incident `affected_areas` |
| `property_damage_levels` | Add: qualitative damage choices differ from count-based `field_report_categories` |
| `responder_field_report_members` | Add: historical per-member assessment snapshots differ from category totals and current member status |
| `web_push_subscriptions` | Add: encrypted browser/VAPID subscriptions differ from mobile OneSignal subscriptions |
| `web_push_deliveries` | Add: per-browser deduplication/baselines differ from provider delivery logs requiring notification, channel, and status references |

## Upgrade procedure

Configure `SHARED_DB_HOST`, `SHARED_DB_PORT`, `SHARED_DB_DATABASE`, `SHARED_DB_USERNAME`, and `SHARED_DB_PASSWORD` securely on the machine running the upgrade. No credentials are committed. Keep a private database backup and rehearse against an isolated copy first.

Run only the compatibility migration on the shared database:

```powershell
php artisan migrate --database=shared_resq --path=database/migrations/2026_10_28_000005_align_shared_resq_schema.php --force
php artisan resq:merge-local-data --source=resq_local --target=shared_resq
php artisan resq:merge-local-data --source=resq_local --target=shared_resq --apply --expect-database=klint
```

Do not run the entire backlog of old migrations on the shared database: the historical member-ID upgrade changes identifiers used by other systems. The compatibility migration is independent of that backlog and checks parent-key duplicates before making schema changes.

The import only inserts verified records or fills empty consolidation fields. It preserves shared credentials, existing subscription values, IDs, and original update timestamps. Missing identity matches are reported, never fabricated. Local sessions, OTPs, browser authorizations, route caches, and operational histories are excluded; sign in on the deployed application to register real devices. Historical records need a separate reference-verified transfer if required.

`disaster_broadcasts.onesignal_id` and `notifications.onesignal_id` store actual OneSignal message IDs, with JSON lists retaining multiple submission IDs. `device_tokens.player_id` remains the OneSignal subscription ID; these are different identifiers. Backend `ONESIGNAL_APP_ID` must match mobile `EXPO_PUBLIC_ONESIGNAL_APP_ID`; the REST API key remains server-side. Verify Android FCM configuration and run the `broadcasts` and `navigation` queue workers when deploying. No notification is sent by the database upgrade.

Deploy the updated backend with the compatibility schema. Web/mobile API URLs and payload shapes stay the same. Set the deployed backend database connection to the configured shared database; developer-local configuration is not switched by these scripts. Restart long-running workers and clear the schema-capability cache after deployment so the new columns are detected.

The row-preservation verifier captures hashes of all original table projections (UTC for timestamps), checks that every original row and column still exists afterward, and allows ID allocation counters only to advance.

## Applied verification — 28 October 2026

The additive migration and verified-data import were applied to `klint` after rehearsal on an isolated local copy. The shared database increased from 101 to 111 tables. All 101 original tables, original columns, business records, and original update timestamps passed the preservation check. No existing table was renamed or dropped, and no shared member IDs or passwords were replaced. A private SQL backup was captured immediately before applying the upgrade.

The import inserted five property-damage choices, five missing household-status choices, 44 official advisories, and two identity-verified device records. It filled one team base, one household login pointer, and new classification flags for 12 member-status entries. Unmatched local login/device/owner records and environment-specific history were left unchanged in the local database; they were not forced into shared identities.

OneSignal API authentication and Android FCM v1 configuration passed. The production mobile build App ID matches the backend. The shared database currently has zero eligible Android subscriptions: deployed mobile users must sign in, allow notifications, and register their phones against the shared backend. No push notification was sent during verification.

Affected backend tests passed, as did seven focused mobile push/alert tests. The broad backend run had 485 passing tests and 15 failing tests; those same failing test names also failed in a separate checkout of HEAD. An additional broadcast-persistence regression test passed afterward. The backend changes and APK have not been deployed or built by this task.
