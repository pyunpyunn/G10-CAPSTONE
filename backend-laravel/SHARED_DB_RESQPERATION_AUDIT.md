# Resqperation shared_db schema audit

Verified October 31, 2026 against `shared_db` on the supplied Tailscale server. This is the current target; older Klint reports describe historical work and are not verification of this database.

## Scope and comparison

The shared database's existing structures were compared with the current application, its consolidation specification, migrations and Eloquent/query-builder mappings. Existing shared primary keys, IDs, foreign keys and business records were retained. No local business records were imported, and no unrelated EvaTrack/BIDRCS tables were altered. The local operational connection remains `ResQperation` / `resq_local`. Only `SHARED_DB_DATABASE` in the private `.env` was changed to `shared_db`; the local selection and credentials were not changed.

The initial shared schema was stronger than the obsolete local-table mapping in several areas. Navigation already used `device_tokens`, household login used `households.login_user_id`, phone ownership used `device_tokens`, recovery used `password_reset_requests`, advisories used `notifications`, and bases used `rescue_teams`. Those implementations were retained. The stale audit manifest and navigation Eloquent model were corrected instead of recreating `devices`, `team_bases`, `household_login_accounts`, `household_device_owners`, `household_password_recovery_otps` or `official_advisory_notifications`.

| Check | Before | After |
|---|---:|---:|
| Entire shared database: base tables | 111 | 121 |
| Entire shared database: FK column mappings | 160 | 200 |
| Entire shared database: index column mappings | 457 | 513 |
| Missing current mapped tables / composite query table | 10 | 0 |
| Missing mapped writable columns | 8 | 0 |
| Missing / wrong reviewed FK relationships | 35 findings including missing tables | 0 |
| Pending guarded repair statements | — | 0 |
| Blocked repairs / shared orphan warnings | — | 0 |

These totals describe schema objects, not business records or distinct multi-column indexes. The schema export covers **60 canonical mapped tables**, including consolidated shared dependencies and the audit-log mapping added during the isolated-object follow-up. Every one of the original 160 FK column mappings retains its original name, target and deletion/update rules. No original table or column was removed. All original index requirements remain covered. MySQL replaced the redundant single-column `responder_check_ins.fk_rci_responder` index with the new covering `(responder_id, checked_in_at)` index; this is a stronger lookup path, not a lost responder index.

## Applied changes

Only these four scoped migrations were executed against `shared_resq` targeting `shared_db`:

- `2026_10_31_000001_finalize_resq_shared_modules.php`
- `2026_10_31_000002_reconcile_resq_owned_lookup_catalogs.php`
- `2026_10_31_000003_enforce_consolidated_resq_references.php`
- `2026_10_31_000004_link_resq_damage_and_audit_actors.php` — follow-up on the 21 apparently disconnected objects; see the [object-by-object audit](ISOLATED_DATABASE_OBJECT_AUDIT.md).

They created the following missing Resqperation module tables:

| Table | Purpose |
|---|---|
| `external_report_requests` | External report request/decision workflow |
| `household_geographic_classifications` | Household geographic classification |
| `household_mobile_notifications` | Household notification inbox |
| `purok_boundaries` | Geographic boundary geometry |
| `rescue_criteria_contact_baselines` | Event/household contact baseline, composite key |
| `rescue_criteria_line_snapshots` | Historical rescue-criteria buckets |
| `rescue_criteria_snapshots` | Versioned rescue-criteria measurements |
| `resq_orphan_references` | Recovery evidence for future reference repair; no identities were guessed |
| `situation_report_submissions` | Situation-report delivery records |
| `urgency_levels` | Persisted request urgency catalog |

Missing criteria definition/settings-version fields, rescue priority settings, situation-report metadata and external request contract fields were added. Parent-compatible types/collations and FK indexes were checked before constraints were added. `mapping_hazard_layers.barangay_id` was an empty text column; it now uses the actual integer barangay parent type. The guarded planner also refuses conversion of populated noncanonical or out-of-range text IDs.

Additional references now have physical constraints, including historical member-status links, criteria event/settings links and three consolidated household-login/device-selection/recovery references. New constraints restrict parent removal to preserve referenced history. Browser subscriptions and device owner selections use `ON DELETE SET NULL` for revoked access tokens so logout/token revocation remains possible. Independent caches, queues and polymorphic recovery journals are not given artificial parent relationships.

The owned dispatch, damage and urgency catalogs use existing application definitions. Missing business keys were inserted; existing shared labels, codes and active flags were preserved. No guessed numeric status IDs, demo accounts, households or operational records were created. Shared request/household/member status catalogs were not populated from invented values.

## Verification

- A fresh live `resq:database-audit --database=shared_resq` returned empty missing/warning/relationship/orphan/blocked lists and zero pending repair statements.
- Fingerprints of all original row projections across the 111 pre-existing tables were captured before the upgrade. The preservation check passed: no original row values were lost or changed. Scalars are normalized across PDO string/integer representations; new columns and new lookup rows are additive.
- Two transaction-only live MySQL tests attempted invalid criteria-snapshot/contact-baseline parent links. Both were rejected with MySQL error 1452, and the transactions were rolled back; no test rows were committed.
- Ten authenticated read endpoints returned HTTP 200 with valid JSON: profile, dashboard summary, resource requests, dispatches, rescue teams, field reports, map overview, situation reports, radio archives and saved archive groups. An existing eligible account was used without creating a login token or account.
- Backend full suite: **523 tests passed, 4,612 assertions**. The final focused upgrade/mapping/review/push checks passed **14 tests, 60 assertions**, including preservation of customized shared labels/codes and repeat execution. Tests use SQLite in memory, not either persistent database. PHP syntax and whitespace checks passed.

Schema metadata and the current table-by-table mapping are in [resqperation-erd.json](database/schema/resqperation-erd.json) and the [mapping index](database/schema/README.md). Private comparison, preservation, migration, API and test artifacts are kept in ignored `storage/resq-audit/`; no credentials or business row contents are included in this report/export.

## Data readiness and deployment limits

The target initially had eight user records but no households, responders, rescue teams or resource-request status rows. The schema audit being clean does not supply those missing business records or shared lookup values. Screens can correctly show empty results. Populating those records requires an authoritative, identity-verified source; this upgrade deliberately does not copy local records or unrelated systems' catalogs.

The application's selected local database was not switched. Deployments intending to serve shared data must select the verified shared connection in their own deployment configuration. Reverse-engineer **shared_db**, including referenced shared parent tables, in MySQL Workbench to see the applied physical relationships; this work did not operate the Workbench UI or certify a manually arranged diagram.
