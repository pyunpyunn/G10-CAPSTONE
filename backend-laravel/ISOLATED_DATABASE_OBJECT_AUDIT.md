# Resqperation: 21 apparently disconnected database objects

Reviewed October 31, 2026 against live `shared_db`, current models/migrations, application queries and runtime configuration. All 21 named objects exist. Two are SQL views, not base tables. Workbench foreign-key lines represent enforced constraints between base tables; neither Eloquent relationships nor view joins automatically create those lines.

## Actual repairs

The preflight identified exactly two missing constraints and zero invalid populated references for either:

- `responder_field_reports.property_damage` → `property_damage_levels.damage_key`.
- `audit_logs.user_id` → `users.user_id`.

Applied scoped migration: `2026_10_31_000004_link_resq_damage_and_audit_actors.php`. Both constraints use `ON DELETE RESTRICT ON UPDATE RESTRICT`, preserving referenced catalog entries and audit actor identities. Anonymous/system audit entries may keep a null actor. Existing polymorphic audit targets remain snapshots, not fake relationships to a single table.

`ResponderFieldReport::damageLevel()` now resolves the persisted damage catalog entry. `AuditLog::user()` already existed; audit logs are now included in the operational mapping manifest as a shared dependency, without claiming exclusive ownership of the table. No data backfill, guessed IDs, table deletions or unrelated application changes were performed.

## Object-by-object decision

“Resq support” means the current application uses the object. “Shared framework” does not imply exclusive Resq ownership. The original creator/owner of unreferenced legacy objects cannot be established from their names alone.

| Object | Classification / Resq use | Relationship decision |
|---|---|---|
| `_archive_orphan_household_disasters` | Legacy Resq-domain recovery-shaped snapshot; not in active application mappings. Exclusive ownership/provenance unconfirmed. | Preserve unchanged. Its name and copied household/disaster columns indicate orphan retention; attaching to current parents could invalidate the evidence. No invented parents or active workflow integration. |
| `operational_sequences` | Resq support; `OperationalSequence` / `OperationalCounter`. | Intentionally independent allocator. `sequence_key` names logical counters, not one parent entity. |
| `offline_packs` | Resq navigation support; `OfflinePack`. | Standalone versioned map manifest. Bounding box, checksum and URLs are values; no stored tile/region foreign-key column exists. |
| `report_idempotency` | Resq request replay support; `ReportIdempotency`. | Standalone hash-scoped response ledger. An opaque scope hash cannot be an ordinary FK to a business record. |
| `audit_logs` | Shared audit infrastructure actively written/read by Resq; `AuditLog`. | **Repaired actor FK to users.** `reference_table`/`reference_id` are polymorphic historical targets; one ordinary FK cannot point to multiple tables. |
| `sessions` | Shared Laravel session infrastructure. Current developer `.env` uses the file session driver. | A nullable actor association is possible, but shared session provider ownership/lifecycle is unconfirmed. No Resq-specific FK was imposed on other applications' sessions. Anonymous sessions are legitimate. |
| `resq_orphan_references` | Resq recovery journal; `ResqOrphanReference`. | Intentionally standalone: stores original invalid identities and variable parent/table names. Enforcing those missing parents would defeat recovery. |
| `realtime_outbox` | Resq event-delivery support; `RealtimeOutbox`. | Independent delivery queue. Topics and claim tokens are transport metadata, not entity IDs. |
| `v_member_status_monitor` | Shared Resq-domain SQL monitoring view; not referenced directly by current application queries. | **Already has SQL dependencies through its view definition.** It compiles. Views do not accept ordinary table FKs; no artificial connection was added. |
| `v_household_status_board` | Shared Resq-domain aggregate SQL view; not referenced directly by current application queries. | **Already joins underlying household/member/status sources.** It compiles. FK relationships belong on the underlying base tables. |
| `job_batches` | Shared Laravel batch metadata. | Independent batch ledger; failed job identifiers/options are serialized state. Job deletion and batch retention have different lifecycles. |
| `cache` | Shared Laravel key/value cache. Current developer default is file cache; other deployments may use database cache. | Independent expiring entries with opaque keys. No business parent ID exists. |
| `inquiries` | **Not the current Resq inquiry storage.** Current routes/services/model use `landing_inquiries`; ownership of this separate table is unconfirmed. | Left unchanged. Do not redirect Resq CRUD or add guessed relationships based on its similar name. `landing_inquiries.handled_by_user_id` already references users. |
| `jobs` | Shared Laravel queue infrastructure; explicit Resq queue connections can use it. | Independent serialized work queue. Payload contents are not typed FK columns, and jobs disappear after processing. |
| `_archive_orphan_household_status_logs` | Legacy Resq-domain recovery-shaped snapshot, outside active mappings; exclusive ownership/provenance unconfirmed. | Preserve unchanged for the same reasons as the household-disaster orphan snapshot. No fabricated replacement identities. |
| `property_damage_levels` | Resq catalog; `PropertyDamageLevel`, used by field-report validation/options. | **Repaired incoming FK from field reports.** A lookup parent does not need its own outgoing FK to be connected. |
| `cache_locks` | Shared Laravel distributed-lock infrastructure. | Independent leases. `owner` is a lock token, not a users ID; do not attach it to users or cache entries. |
| `map_tile_metadata` | Resq map-tile cache; `MapTileMetadata`. | Standalone zoom/x/y/file metadata. No pack-version FK is stored; inventing one would change the data model without evidence. |
| `migrations` | Shared Laravel schema migration ledger. | Standalone deployment metadata. Migration filenames and batches are not business entity references. |
| `password_reset_tokens` | Laravel password-broker infrastructure declared by the project. Current custom SMS recovery uses `password_reset_requests`. | Email-scoped temporary tokens, not a stored users primary key. Do not force an email FK: email mutability/nonunique shared identities and other providers require separate review. |
| `failed_jobs` | Shared Laravel failure history. | Independent exception/payload snapshots retained after queue jobs disappear. A FK to jobs would undermine failure retention. |

## Verification and boundaries

- Live metadata, current query/model references and configuration were checked. Before applying changes, damage references, audit actors and session actors all had zero populated references and zero orphans. Empty rows do not establish exclusive ownership of shared tables.
- Both SQL views passed `SELECT ... LIMIT 0`, validating their current definitions without exporting household/member data. Their definitions reference real shared household/member/status/event/account sources; no view was rewritten.
- Focused isolated tests passed: **7 tests, 27 assertions**, including persisted damage-label loading, prevention of deleting a referenced catalog entry, null audit actors, real audit-actor resolution and rejection of invalid actor references.
- The live MySQL audit-actor orphan probe was rejected with error 1452 and rolled back. Both previously added criteria-reference probes also passed. No test rows were committed.
- Final live schema audit: zero missing mappings, warnings, pending repairs, blocked relationships or orphan findings. Original-row preservation passed across all 111 pre-existing tables. The refreshed database has 200 FK column mappings; the two additions are the only changes applied during this follow-up.
- The targeted migration and refreshed live schema/audit checks are recorded privately in ignored `storage/resq-audit/`. The updated [schema export](database/schema/resqperation-erd.json) and [table mapping](database/schema/README.md) are the reviewable metadata artifacts.
- The local database selection/data and environment credentials were not changed. Infrastructure, views, legacy recovery snapshots and the unconfirmed `inquiries` table were not altered. Shared audit infrastructure was changed only for the verified actor relationship used by Resqperation.

Reverse-engineer `shared_db` again with `users`, `responder_field_reports`, `audit_logs` and `property_damage_levels` included to see the two new FK lines. The other objects are not defective merely because they remain isolated in an FK-only diagram. This review did not operate the Workbench UI or assert ownership of another team's unreferenced objects.
