# Resqperation database remediation

Verified October 30, 2026 against Klint through a separate runtime connection. The backend `.env`, its selected local database, and local database records were not changed by the shared-database remediation. The user explicitly requested that local schema gaps and ambiguous shared records be left unchanged.

## Applied to Klint

- Restored 17 Resqperation primary keys and added 37 targeted indexes, including Sanctum token uniqueness and token-owner lookups. Existing identifiers were preserved.
- Added or corrected 59 `resq_*` foreign keys. Navigation trips/sync reference navigation devices rather than push subscriptions. Parent types/collations are inspected; incompatible populated data blocks automatic changes.
- Preserved 55 broken operational references in `resq_orphan_references` before clearing their invalid links. Business rows, historical logs and recordings were retained.
- Added missing operational module tables using existing project migration definitions. Added the urgency catalog expected by request workflows, without assuming lookup IDs.
- Removed qualifying household-resident plaintext temporary passwords while retaining recognized password hashes; no resident plaintext credentials remain. Disabled the test-only household status without deleting its history.
- Widened `user_notifications.user_id` to match the actual `users.user_id` definition.

The seven scoped migrations are `2026_10_29_000001` through `000006`, plus `2026_10_30_000001`. Existing module-creation migrations now tolerate imported tables. Do not run a blanket migration, seed, reset, or schema rebuild against the shared database.

## Application changes

- Added Eloquent mappings and relationships for previously unmapped operational entities. [Schema metadata](database/schema/resqperation-erd.json) and the [mapping index](database/schema/README.md) cover 63 operational tables. Shared parents remain externally owned. Composite contact-baseline keys use explicit query-builder scoping rather than pretending Eloquent supports composite keys.
- Request status labels come from `resource_request_status`; validation and partner handoff no longer replace them. Request monitoring uses persisted status/urgency options and filters urgency before pagination. Delivery totals read persisted request statuses.
- Web request and archive lists use RTK Query, with related cache invalidation after writes, focus/reconnect refresh, and background polling. Malformed successful API responses produce visible errors rather than false empty lists.
- Archives expose recording availability, playback controls, record IDs and identifiable deletion confirmations. All 37 database-listed PTT recordings were found on this backend's storage. Browser/device playback still needs user acceptance testing.
- Rescuer request submission now validates and saves the selected urgency. Locked ID sequences advance past externally inserted IDs.
- Database schema errors return a sanitized 503; recognized duplicate/foreign-key conflicts return 409. SQL and bindings are not returned to clients. The read-only `resq:database-audit` command checks schema, Eloquent aliases, and shared-reference problems.
- Daily maintenance reuses the existing pruning command with configurable seven-day retention for acknowledged outbox events. Pending events and active idempotency records are retained. This requires the existing Laravel scheduler to be running; no live pruning was executed during remediation.

## Verification

- Klint: zero missing mapped columns/tables, zero pending repair statements, zero blocked owned relationships. Shared-data warnings below intentionally keep the comprehensive audit nonzero.
- Nineteen authenticated module read endpoints returned HTTP 200, including dashboard, household directory/status, dispatch, teams, field reports, mapping, requests, weather, notifications, situation reports and all six archive categories.
- Web: 41 tests passed; production build passed. Mobile TypeScript checking passed.
- Latest full backend suite: 517 tests, 503 passed, 11 assertion failures and three errors. Outstanding tests concern sparse geography fixtures and role/team/status contract expectations. They have not been hidden or relaxed to obtain a green result. Targeted database error, mapping, archive, request and sequence checks passed.

## Explicitly retained limitations

The read-only local audit found 14 missing mapped tables and eight mapped columns in `resq_local`. It remains unchanged, as requested. Consequently, the running local application does not automatically gain Klint's schema or records. Do not interpret Klint verification as local database or physical-device acceptance.

Shared data preserved at the user's request:

| Relationship | Unresolved records |
|---|---:|
| Users referencing missing households | 37 |
| Geotags referencing missing households | 6 |
| Request referencing a missing evacuation center | 1 |

None of the 37 user links could be recovered from an existing member-to-household relationship. No replacement households, centers or guessed links were created. These records require an authoritative source before reconciliation. EvaTrack/BIDRCS-only tables, server-wide grants and partner exports were not modified.

Detailed local verification artifacts are kept in the ignored `storage/resq-audit/` directory. No credentials are included in the ERD metadata or this report.
