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
php artisan migrate --database=shared_resq --path=database/migrations/2026_10_28_000004_consolidate_household_access.php --force
php artisan migrate --database=shared_resq --path=database/migrations/2026_10_28_000006_restore_responder_user_accounts.php --force
php artisan resq:merge-local-data --source=resq_local --target=shared_resq
php artisan resq:merge-local-data --source=resq_local --target=shared_resq --apply --expect-database=klint
```

Do not run the entire backlog of old migrations on the shared database: the historical member-ID upgrade changes identifiers used by other systems. The compatibility migration is independent of that backlog and checks parent-key duplicates before making schema changes.

The household-access migration only fills an empty `households.login_user_id`
when a matching account exists; it retains the legacy source tables and the
household's original update timestamp. The responder-login migration creates
missing `users` rows only for validated responders with unique IDs/usernames,
an existing `tl` or `tm` role, and a supported password hash. It copies the
existing responder hash without resetting credentials and fails on ambiguous
identities rather than overwriting a user.

The import only inserts verified records or fills empty consolidation fields. It preserves shared credentials, existing subscription values, IDs, and original update timestamps. Missing identity matches are reported, never fabricated. Local sessions, OTPs, browser authorizations, route caches, and operational histories are excluded; sign in on the deployed application to register real devices. Historical records need a separate reference-verified transfer if required.

`disaster_broadcasts.onesignal_id` and `notifications.onesignal_id` store actual OneSignal message IDs, with JSON lists retaining multiple submission IDs. `device_tokens.player_id` remains the OneSignal subscription ID; these are different identifiers. Backend `ONESIGNAL_APP_ID` must match mobile `EXPO_PUBLIC_ONESIGNAL_APP_ID`; the REST API key remains server-side. Verify Android FCM configuration and run the `broadcasts` and `navigation` queue workers when deploying. No notification is sent by the database upgrade.

Deploy the updated backend with the compatibility schema. Web/mobile API URLs and payload shapes stay the same. Set the deployed backend database connection to the configured shared database; developer-local configuration is not switched by these scripts. Restart long-running workers and clear the schema-capability cache after deployment so the new columns are detected.

The row-preservation verifier captures hashes of all original table projections (UTC for timestamps), checks that every original row and column still exists afterward, and allows ID allocation counters only to advance.

## Applied verification - 28 October 2026

The additive compatibility migration and verified-data import were previously
applied to `klint` after rehearsal on an isolated copy. The compatibility
migration is recorded in Klint's `migrations` table (batch 20). A live
read-only audit at 23:02 +08:00 found 113 tables. Every table, column, index,
and `sms_otp` verification option declared by `shared-resq-schema.json` is
present. The seven local-only tables listed above are represented by their
shared targets; the ten separate-purpose tables in the schema specification
are also present.

At 23:17 +08:00, before login repair, a private full Klint SQL backup was
created. The household-access migration then populated 20 previously empty
login pointers for households with an unambiguous account, without changing
household timestamps. The responder-login migration added 49 missing,
validated rescuer user rows using their existing supported password hashes;
it did not change existing users or responder password hashes. Afterward,
all 52 validated responders resolved to their corresponding user accounts,
all eight active web accounts resolved, and 21 active households had valid
login pointers. No existing credentials were reset.

The `resq_local` household `HH-2026-8563` conflicts with an existing Klint
household ID belonging to a different household. That shared household was
preserved. To fulfill the household-account request without overwriting it, a
separate six-member test household for Ivan Alaud was added in Klint as
`HH-2026-8564`, with a valid address in Purok 2, Sitio Lawis, Mambaling. Its
login was verified through the local API against Klint; the temporary
verification token was removed. The account is configured to require a
password change after first login.

Klint now has 64 household-resident user rows: 27 reference household rows, of
which five are soft-deleted, and 37 have no corresponding household row.
There are also five inactive household-resident accounts. These were not
activated or fabricated into household/member records. Their source identity
and status need authoritative review before they can be made login-ready.
There are now 22 active households with valid login pointers.

The latest identity-verified local-data merge dry run found no additional inserts or
merges to apply. It preserved five existing property-damage choices, 44
matching advisory records, and 13 original shared member IDs. It deferred
three household-login links, 92 local device-token rows, 12 navigation-device
rows, and 17 device-owner links because the corresponding shared account,
member, or device identity could not be verified. Local OTPs, browser push
subscriptions/deliveries, trips, trip points, sync events, field-report
snapshots, and route-cache records are environment-bound or operational
history and are intentionally not imported by this merge command. Do not
fabricate or force identity matches; handle any records that must be migrated
through a separately reviewed, reference-verified process.

The prior applied-import report recorded five property-damage choices, five
household-status choices, 44 official advisories, two identity-verified device
records, one team-base value, one household login pointer, and classification
flags for 12 member-status entries. The earlier preservation check passed for
the then-existing 101 tables and all original row contents. That historical
table count predates the current 113-table schema audit.

The backend's active local API connection is now `shared_resq` targeting
`klint`; `resq_local` remains available as a separate source connection.
Configuration cache was cleared, and the APK 51 API tunnel remained
responsive. Focused responder/account migration and household-access tests
passed (17 tests, 120 assertions). OneSignal API authentication and Android
FCM v1 configuration passed during the prior setup check. The shared database
had zero eligible Android subscriptions at that time; deployed mobile users
must sign in, allow notifications, and register their phones against the
shared backend. No push notification is sent by the schema migration or data
merge.

## Responder data sync - 28 October 2026

The local `responders` table was synchronized additively to Klint by stable
`user_id`. All 64 local responder rows now have matching responder rows in
Klint: 51 existing rows were updated, 13 were added, and one Klint-only row
was retained. No Klint rows or tables were deleted, and the local database
was not changed. Existing Klint `responder_id` values were preserved for
matching identities; new rows received unused Klint IDs so operational
history keyed by existing responder IDs remains attached. A private backup of
the Klint `responders` rows was made before the sync, and an independent
verification found no field mismatches for the 64 local identities (excluding
the intentionally preserved/allocated shared IDs).

This was intentionally limited to the `responders` table. The `users` table
was not modified. Ten local responder `user_id` values still have no matching
Klint user account, seven source account usernames conflict with a different
Klint user identity, and one source responder references a team not present
in Klint. Those records require separate identity/account/team review before
their login or team association can be considered valid.

The seven local-only table names (`devices`, `household_device_owners`,
`household_login_accounts`, `household_member_id_mappings`,
`household_password_recovery_otps`, `official_advisory_notifications`, and
`team_bases`) were not recreated as duplicate tables: each is already
represented by its documented Klint target. No genuinely missing local
functionality table was identified in the schema comparison, so no table
schema was added in this step.
