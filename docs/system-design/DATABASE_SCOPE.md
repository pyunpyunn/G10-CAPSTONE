# Strict database scope and relationship interpretation

Inspection date: 28 October 2026. The metadata was read from the currently configured `resq_local` connection using `information_schema`. No business rows, credentials or secret values were exported. No migrations, schema changes or data writes were run. Other deployments and partner databases may differ.

- Existing database tables inspected: **118**.
- Tables with verified RESQPERATION query/model usage included: **75**.
- Existing tables excluded from this business ERD: **43**.
- Source/migration-defined tables absent locally: **8**.
- Relationships documented: **156**, including **21** database-enforced foreign keys; the remaining relationships come from reviewed application/model references.

## Which ERD to use

- `erd/00-entire-system.mmd`: all 75 verified tables, showing keys/reference columns for readability.
- `erd/01-entire-system-all-columns.mmd`: those same 75 tables with every inspected column.
- `erd/02-identity.mmd` through `erd/10-platform.mmd`: module views with full columns and referenced parent context.
- `erd/11-source-defined-deployment-gaps.mmd`: 8 migration-defined tables not present locally.
- `erd/12-entire-system-source-and-deployed-scope.mmd`: unified 83-table scope; source-only entities carry explicit source-only labels. It is not a claim that 83 tables are deployed.

## Notation and accuracy limits

Solid relationship lines and `FK` tags identify metadata-confirmed foreign keys. Dotted lines identify application-level references; no enforced FK is implied. This visual distinction is a documentation convention, not a claim of identifying-versus-nonidentifying dependency. Parent-side optionality follows nullable reference columns; many-side optionality permits zero dependent rows. A single-column unique index gives at most one dependent record. Logical links describe intended application relationships and do not prove row-level referential integrity.

`PK` and `UK` tags reflect the inspected schema, not an Eloquent model assumption. Some operational tables have application identity columns without database primary/unique constraints. Column types come from database metadata; source-only types come from migrations. This is a schema document, not a data-integrity audit.

A token-to-user link is conditional on Sanctum tokenable type. `incident_archives.reference_table/reference_id` is a polymorphic generic reference, not one FK to every operational table. JSON payloads/snapshots and source/tracking references are not fabricated as relational foreign keys. Request status/event assignment labels and lookup strings are not modeled as nonexistent ID foreign keys.

Shared master tables are included only when the application actually uses them. Inclusion does not transfer ownership from another system. Unused accommodation, census enrichment, center administration and analytics tables are excluded. The presence of a model file alone does not establish an actively used feature.

## Source-defined tables absent locally

| Table | Migration evidence |
| --- | --- |
| external_report_requests | backend-laravel\database\migrations\2026_10_17_000001_create_external_report_requests_table.php |
| household_geographic_classifications | backend-laravel\database\migrations\2026_10_15_000001_create_household_geographic_classification_tables.php |
| household_mobile_notifications | backend-laravel\database\migrations\2026_10_08_000002_create_household_mobile_notifications_table.php |
| purok_boundaries | backend-laravel\database\migrations\2026_10_15_000001_create_household_geographic_classification_tables.php |
| rescue_criteria_contact_baselines | backend-laravel\database\migrations\2026_10_05_000002_create_rescue_criteria_contact_baselines.php |
| rescue_criteria_line_snapshots | backend-laravel\database\migrations\2026_10_05_000001_create_rescue_criteria_line_snapshots.php |
| rescue_criteria_snapshots | backend-laravel\database\migrations\2026_10_04_000006_create_rescue_criteria_snapshots_table.php |
| situation_report_submissions | backend-laravel\database\migrations\2026_10_18_000021_create_situation_report_submissions.php |

The inspected default connection also lacks `barangay_profiles` and `urgency_levels`, which appear in conditional compatibility code. Their authoritative deployed definitions were not established, so no guessed entities/columns were added.

## Conflicting schema/code reference evidence


No relationship was accepted for nonexistent columns such as `household_member_id_mappings.household_id` or `responder_location_logs.assignment_id`. Location history is linked to its actual responder column; member-ID mapping uses its actual mapped member field.

## Excluded existing tables

| Table | Reason |
| --- | --- |
| _archive_orphan_household_disasters | Legacy orphan backup; not an active operational entity |
| _archive_orphan_household_status_logs | Legacy orphan backup; not an active operational entity |
| accommodation_types | No active RESQPERATION business-table query/model use established |
| accommodation_units | No active RESQPERATION business-table query/model use established |
| analytics | No active RESQPERATION business-table query/model use established |
| analytics_job_logs | No active RESQPERATION business-table query/model use established |
| analytics_job_status | No active RESQPERATION business-table query/model use established |
| cache | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| cache_locks | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| center_issue_categories | No active RESQPERATION business-table query/model use established |
| center_issue_report_statuses | No active RESQPERATION business-table query/model use established |
| center_issue_reports | No active RESQPERATION business-table query/model use established |
| civil_statuses | No active RESQPERATION business-table query/model use established |
| csv_uploads | No active RESQPERATION business-table query/model use established |
| data_sources | No active RESQPERATION business-table query/model use established |
| education_levels | No active RESQPERATION business-table query/model use established |
| evacuated_members | Name appears as module/output alias, not a verified table query in this application |
| event_center_history | No active RESQPERATION business-table query/model use established |
| failed_jobs | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| field_report_categories | No active RESQPERATION business-table query/model use established |
| hq_field_reports | No active RESQPERATION business-table query/model use established |
| import_logs | No active RESQPERATION business-table query/model use established |
| inquiries | Name appears as module/output alias, not a verified table query in this application |
| job_batches | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| jobs | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| map_tile_metadata | No active RESQPERATION business-table query/model use established |
| migrations | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| notification_channels | No active RESQPERATION business-table query/model use established |
| notification_logs | No active RESQPERATION business-table query/model use established |
| notification_recipients | No active RESQPERATION business-table query/model use established |
| notification_statuses | No active RESQPERATION business-table query/model use established |
| occupations | No active RESQPERATION business-table query/model use established |
| password_reset_requests | No active RESQPERATION business-table query/model use established |
| password_reset_tokens | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| recurrence_types | No active RESQPERATION business-table query/model use established |
| regions | No active RESQPERATION business-table query/model use established |
| remembered_devices | No active RESQPERATION business-table query/model use established |
| responder_field_report_details | No active RESQPERATION business-table query/model use established |
| sessions | Framework/deployment support; not a RESQPERATION business entity; current queue=sync, cache=file, session=file |
| unit_allocations | No active RESQPERATION business-table query/model use established |
| user_notifications | No active RESQPERATION business-table query/model use established |
| vulnerable_groups | No active RESQPERATION business-table query/model use established |
| zipcodes | No active RESQPERATION business-table query/model use established |

## Partner and framework boundaries

TrackingAid `inventory`, candidate inventory/catalog tables and configured `resqperation_forwarded_requests` belong to the partner connection. They appear as external integration boundaries, not as inspected RESQPERATION entities. Their full schemas were not queried or invented. SafeTrack/EvaTrack report payloads do not justify including every partner-owned table.

The current local runtime reports `queue=sync`, `cache=file`, `session=file`. Generic jobs/cache/session/migration infrastructure is excluded from the business ERD; architecture diagrams explain the optional worker/deployment distinction. This does not claim those tables could never be used under a different configuration.

Mobile navigation uses its own SQLite schema and the overview cache uses an independent SQLite key/value store. They are drawn separately from MySQL. Similar table names do not make them the same entity.

Provenance: `TABLE_SCOPE.csv` lists scope decisions and source paths; `SCHEMA_COLUMNS.json` contains inspected column metadata; `RELATIONSHIP_EVIDENCE.json` identifies relationship evidence.
