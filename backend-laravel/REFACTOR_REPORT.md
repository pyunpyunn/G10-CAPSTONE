# Backend Hardening Audit and Refactor Report

Branch: `refactor/backend-hardening`

Scope: Step 0 audit and characterization only so far. No remote/shared database was connected, migrated, seeded, or modified.

## Baseline

- Laravel framework: 13.12.0; `composer.json` requires PHP ^8.3.
- `phpunit.xml` configures SQLite in-memory for tests.
- Initial suite: 5 of 6 passed. `ExampleTest` expected a missing frontend build although this workspace has `public/frontend-web/index.html`.
- Corrected the environment-dependent assertion and added unauthenticated response contract checks. Current result: `php artisan test --compact` — 7 tests, 30 assertions, all passing.

## Step 0: route and service inventory

Authenticated API endpoints are under `/api/v1` and use Sanctum. Most admin actions require `super_admin,admin`; household operations require `household`; responder actions require `rescuer`. Login/recovery, inquiry intake, external request intake, and inbound SMS are public but throttled. Laravel `/up` is the current liveness endpoint.

### Controller/service ownership map

| Route family | Controller | Main service(s) | Main data domain | Connection observed |
|---|---|---|---|---|
| auth | AuthController | AuthService | users, responders, roles, Sanctum tokens | `resq_local` / default |
| dashboard | DashboardController | DashboardService | events, households, dispatch, requests, weather, activity | `resq_local` / default |
| archive | ArchiveController | ArchiveService | operational history and archive groups | `resq_local` / default |
| disaster-events | DisasterBroadcastController | DisasterBroadcastService | events, broadcasts, notification side effects | `resq_local` / default |
| dispatches | RescueDispatchController | RescueDispatchService | assignments, responders, teams, routes | `resq_local` / default |
| households | HouseholdStatusController | HouseholdStatusService | household status, members, event logs | `resq_local` / default |
| household mobile | HouseholdMobileController | HouseholdMobileService | household/member status, devices, geotags, trusted households | `resq_local` / default |
| responder mobile | RescuerMobileController | RescuerMobileService | assignments, reports, check-ins, routes, radio, requests | `resq_local` / default |
| resource-requests | ResourceRequestController | ResourceRequestService, TrackingAidForwardingService | requests, validation, forwarding | operational default; `trackingaid` integration boundary |
| rescuers | RescuerAccountController | RescuerAccountService | responder accounts, users, teams | `resq_local` / default |
| map | MappingController | MappingService | geotags, households, evacuation sites, responder routes | `resq_local` / default |
| weather | WeatherController | WeatherSnapshotService, WeatherService | weather snapshots/logs and external weather API | `resq_local` / default |
| situation-reports | SituationReportController | SituationReportService | reports and source data | `resq_local` / default |
| notifications | NotificationController | NotificationService, OneSignalNotificationService | notifications and OneSignal | `resq_local` / default |
| SMS inbound | SmsInboundController | mobile/broadcast services | inbound messages and operational records | `resq_local` / default |
| profile | ProfileController | ProfileService, BarangayProfileService | user and barangay profile | `resq_local` / default |
| evacuation | EvacuationCheckInController | EvacuationCheckInService | evacuation check-ins and centers | `resq_local` / default |
| devices | MobileDeviceController | MobileDeviceService | device tokens and location tracking | `resq_local` / default |
| inquiries | InquiryController | InquiryService | public inquiries | `resq_local` / default |
| global-search | GlobalSearchController | GlobalSearchService | cross-domain reads | `resq_local` / default |

Connection note: these are inferred from default configuration and explicit integration boundaries, not proof that each individual query uses the intended connection. Most application queries use Laravel's default connection (`resq_local` in `.env.example`). Verify query/model connection ownership before deployment. Never span `resq_local` and `trackingaid` in one transaction.

### Service file line counts

| Service | Lines |
|---|---:|
| RescuerMobileService.php | 1,880 |
| HouseholdMobileService.php | 1,841 |
| ArchiveService.php | 1,493 |
| RescueDispatchService.php | 1,352 |
| ResourceRequestService.php | 1,307 |
| RescuerAccountService.php | 1,226 |
| HouseholdStatusService.php | 1,225 |
| DisasterBroadcastService.php | 781 |
| MappingService.php | 695 |
| SituationReportService.php | 683 |
| DashboardService.php | 651 |
| NotificationService.php | 556 |
| WeatherSnapshotService.php | 419 |
| ProfileService.php | 396 |
| InquiryService.php | 285 |
| TrackingAidForwardingService.php | 280 |
| OneSignalNotificationService.php | 234 |
| BarangayProfileService.php | 211 |
| GlobalSearchService.php | 203 |
| MobileDeviceService.php | 208 |
| EvacuationCheckInService.php | 167 |
| SmsGatewayService.php | 108 |
| WeatherService.php | 60 |
| RoutingService.php | 51 |
| AuthService.php | 347 |

### Static scan inventory

Full line-numbered matches for JSON, runtime schema probes, and query materialization are recorded in the appendices below. These are candidates, not automatic defects: `Collection::all()` after a bounded query differs from an unbounded query-builder `get()`, and static scans cannot tell whether a surrounding query has a limit.

#### `json_encode` / `json_decode`

See Appendix A. These calls are present in service classes including Archive, Dashboard, Mapping, Household Mobile, TrackingAid Forwarding, Weather Snapshot, and others. Move persisted structured fields toward model casts where schema and compatibility support it; preserve exact response strings/shapes.

#### `Schema::hasTable` / `Schema::hasColumn`

See Appendix B. Runtime checks are widespread, particularly in HouseholdMobileService, RescuerMobileService, AuthService, ArchiveService, DashboardService, and MappingService. A missing required table currently risks becoming an empty/fallback success path. Distinguish optional integration tables from core schema guaranteed by migrations before removing checks.

#### `->get()` / `->all()` / `->pluck()` candidates

See Appendix C. Review each query terminal with its surrounding builder for pagination/limit and table growth. Lookup lists need explicit maximum sizes or caching; aggregate endpoints should keep counts in SQL.

#### Writes, transactions, increments, external calls, and N+1

- Direct SQL/model writes, transaction boundaries, lock operations, and increment/decrement candidates: Appendix D.
- External boundary candidates (HTTP, OneSignal, SMS, weather, routing, dispatch) must be checked against their enclosing transaction. Keep remote effects after commit/queued where safe; keep critical dispatch/state writes synchronous and transactional.
- Multi-table writes need block-by-block review. A static grep cannot reliably infer the count of touched tables or whether operations are inside the same transaction.
- Counter/sequence candidates need review for `max()+1`, count-then-insert, and generated codes. Prefer a row lock on a dedicated indexed counter where needed, plus a unique constraint.
- N+1 candidates require tracing loops/maps and eager-load state. Highest-risk targets are both mobile services, archive, dispatch/resource workflows, and dashboard/map aggregation. Query-count baselines are not claimed because the tests do not yet have complete domain fixtures.

### Characterization coverage and limitation

Added checks covering unauthenticated JSON status/body for representative archive, dashboard, disaster event, dispatch, household status, resource request, household mobile, and responder mobile routes. Existing auth, notification, and household member status tests provide limited authenticated behavior coverage. Full authenticated success-shape characterization for each high-risk domain remains incomplete: current tests construct partial schemas independently, and a complete fixture must be based on the verified database schema rather than invented assumptions.

## Step 1: repository hygiene

- Removed the tracked `DisasterBroadcastService - Shortcut.lnk` shortcut.
- Added `/debug_auth.php` to `.gitignore` and removed it from Git tracking. Its local copy remains on disk and was not opened or printed.
- Added `/storage/**/*.sql` to `.gitignore` and removed the two tracked SQL dumps from the Git index. Both local dump files remain on disk and were not opened or printed.
- Git history still contains the previously committed debug file and SQL dumps. Treat this as a history exposure: if any credentials were embedded, rotate them; coordinate history rewriting if the repository has been shared. No secret values were inspected or copied.

## Step 2: pagination ceiling

- Added `ListRequest` with page >= 1, per_page 1–100, defaults page 1 / per_page 15, plus safe internal clamp helpers.
- Applied it to the existing paginator-backed archive collection routes, household registry, dispatch list, responder account list, resource request list, and inquiry list. Updated those internal page-size clamps so direct service callers cannot exceed 100 and may request page sizes down to 1.
- Added validation/clamp tests. Full suite: 8 tests, 36 assertions, passing.
- Not all list-shaped endpoints have been changed. Mobile feeds, notifications, map layer payloads, dashboard summaries, SitRep options, team/lookup lists, and several service methods materialize arrays with endpoint-specific shapes or bounded semantics. Changing them to paginators or adding list metadata could break mobile/web clients. These require per-endpoint contract characterization and bounded-query design; the source match appendix lists `get()` candidates. This step is partial rather than claiming every list endpoint is paginated.

## Step 3: runtime schema checks and JSON handling — skipped

This step is skipped because the repository’s checked-in migrations do not fully define the legacy operational schema consumed by the large services, and the local test suite lacks full fixtures for those tables. Removing probes could convert intentional optional-integration behavior into request failures; changing JSON fields to casts without confirming database types/nullability and historical encodings could change API values. The Archive Group Manager also changes stored sequence/archive payload flows and needs success, rollback, and legacy-data characterization before extraction. No existing migration was changed and no schema operation was run.

## Step 4: query scopes and eager loading — skipped

No query rewrites were made. The grep inventory contains many `get()` calls, but many are bounded lookup lists, page-local enrichment, collection operations, or are grouped for presentation. With incomplete local schema fixtures and no query-count baseline, moving joins to relationships or changing selected columns could silently drop fields relied on by existing presenters/mobile clients. Revisit this one endpoint at a time after success-shape tests and query logging are available.

## Step 5: API Resources and Presenters — skipped

No service response was moved to Resources/Presenters. Service methods currently construct endpoint-specific nested response structures directly; extracting them wholesale without golden responses risks changing field names, null behavior, date formatting, or nested pagination metadata. Existing characterization coverage only pins unauthenticated responses for the broad set of routes. Build fixtures and exact success JSON snapshots for each client before this extraction.

## Step 6: split oversized services — skipped

No large service was split. The line counts identify priority but do not establish safe class boundaries. Rescuer/household mobile services combine several workflows and likely share private helpers, transaction writes, audit behavior, and response formatters. Extracting them without complete method dependency maps and behavior tests could break field reports, radio, device, status, or dispatch flows. Keep the requested split order for the next implementation pass; use cohesive action/query classes and preserve the service facade only where callers require it.

## Assumptions / open decisions

- Existing route URLs, HTTP methods, field names, and JSON response shapes are compatibility constraints.
- `resq_local` is the operational connection; `trackingaid` is a separate integration connection.
- SQL dump working copies must remain on disk but must not remain tracked.
- Keep `RepositoryInterface` and `HouseholdRepository`; do not add repositories for every model.
- No before/after query count is claimed without repeatable local fixtures.

## Manual steps for me

1. Review each proposed index migration against a copy of the deployed schema and query plans before deployment.
2. Set database host/port/name/user/password, timeout, and provider CA/TLS configuration through deployment environment variables. Do not put secrets in Git.
3. Decide whether TrackingAid must be a readiness dependency or only checked by the forwarding workflow.
4. Smoke-test dashboard, archive lists/exports/saved groups, event broadcast, dispatch lifecycle, household status, resource request lifecycle, household mobile status, responder assignment/report/radio, and TrackingAid forwarding.

## Commit log

- Step 0: committed as `f029810` (`docs: audit backend hardening baseline`).
- Step 1: pending commit.
- Step 2: pending commit.
- Step 3: pending report commit; skipped as above.
- Step 4: pending report commit; skipped as above.
- Step 5: pending report commit; skipped as above.
- Step 6: pending report commit; skipped as above.
- Steps 7–10: pending or explicitly skipped/blocked in final notes.

### Appendix A. JSON encode/decode calls

```text
app/Services\DashboardService.php:724:            'old_values' => json_encode([
app/Services\DashboardService.php:727:            'new_values' => json_encode([
app/Services\BarangayProfileService.php:210:        $bounds = json_decode((string) $json, true);
app/Services\EvacuationCheckInService.php:121:        $payload = json_decode($validated['qr_payload'], true);
app/Services\DisasterBroadcastService.php:686:        $decoded = json_decode($text, true);
app/Services\DisasterBroadcastService.php:714:        $decoded = json_decode($text, true);
app/Services\DisasterBroadcastService.php:721:        return json_encode($value, JSON_UNESCAPED_SLASHES);
app/Services\ArchiveService.php:263:        $archiveNote = json_encode([
app/Services\ArchiveService.php:373:                    'archive_note' => json_encode([
app/Services\ArchiveService.php:416:        $payload = json_decode((string) ($row?->archive_note ?? ''), true);
app/Services\ArchiveService.php:470:                $payload = json_decode((string) $note, true);
app/Services\ArchiveService.php:1547:        $decoded = json_decode($value, true);
app/Services\HouseholdStatusService.php:1324:            'new_values' => json_encode([
app/Services\HouseholdStatusService.php:1345:        $decoded = json_decode($value, true);
app/Services\HouseholdMobileService.php:341:                'notes' => json_encode($notes, JSON_UNESCAPED_SLASHES),
app/Services\HouseholdMobileService.php:695:            'member_relationships' => json_encode($validated['member_relationships'] ?? [], JSON_UNESCAPED_SLASHES),
app/Services\HouseholdMobileService.php:1669:        $notes = json_encode([
app/Services\HouseholdMobileService.php:1970:        return json_encode([
app/Services\HouseholdMobileService.php:2086:        $decoded = json_decode($value, true);
app/Services\HouseholdMobileService.php:2163:            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
app/Services\MappingService.php:601:            $decoded = json_decode($route->route_polyline, true);
app/Services\NotificationService.php:505:        $decoded = json_decode($value, true);
app/Services\ProfileService.php:446:            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\ProfileService.php:447:            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\RescueDispatchService.php:1341:        return json_encode(array_merge($current, [
app/Services\RescueDispatchService.php:1354:        return json_encode(array_merge($current, [
app/Services\RescueDispatchService.php:1493:        $decoded = json_decode($value, true);
app/Services\RescueDispatchService.php:1542:            'old_values' => $oldValues ? json_encode($oldValues) : null,
app/Services\RescueDispatchService.php:1543:            'new_values' => $newValues ? json_encode($newValues) : null,
app/Services\RescuerAccountService.php:1396:            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\RescuerAccountService.php:1397:            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\RescuerAccountService.php:1417:            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\RescuerAccountService.php:1418:            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\RescuerMobileService.php:1533:            'route_polyline' => json_encode($route['coordinates'], JSON_UNESCAPED_SLASHES),
app/Services\RescuerMobileService.php:1577:        $decoded = json_decode($polyline, true);
app/Services\RescuerMobileService.php:1774:        $decoded = json_decode($value, true);
app/Services\RescuerMobileService.php:1781:        return json_encode(array_merge($this->decodeJson($existing), $data), JSON_UNESCAPED_SLASHES);
app/Services\RescuerMobileService.php:1830:            $parts[] = 'Members: ' . json_encode($validated['members'], JSON_UNESCAPED_SLASHES);
app/Services\RescuerMobileService.php:1950:            'message' => json_encode($message, JSON_UNESCAPED_SLASHES),
app/Services\RescuerMobileService.php:2200:            'new_values' => json_encode($values, JSON_UNESCAPED_SLASHES),
app/Services\ResourceRequestService.php:1496:            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\ResourceRequestService.php:1497:            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
app/Services\WeatherSnapshotService.php:246:            : json_decode((string) ($log->raw_payload ?? ''), true);
app/Services\SituationReportService.php:108:                'summary' => json_encode($summary, JSON_UNESCAPED_SLASHES),
app/Services\SituationReportService.php:180:            'summary' => json_encode($summary, JSON_UNESCAPED_SLASHES),
app/Services\SituationReportService.php:694:        $decoded = json_decode($value, true);
app/Services\SituationReportService.php:772:            'new_values' => json_encode([
app/Services\TrackingAidForwardingService.php:277:        $record['payload_json'] = json_encode($jsonRecord, JSON_UNESCAPED_SLASHES);
```

### Appendix B. Runtime schema probes

```text
app/Services\AuthService.php:62:        if (Schema::hasTable('personal_access_tokens')) {
app/Services\AuthService.php:207:        return Schema::hasColumn('users', 'security_question_1') && Schema::hasColumn('users', 'security_answer_1') && Schema::hasColumn('users', 'security_question_2') && Schema::hasColumn('users', 'security_answer_2') && Schema::hasColumn('users', 'password_changed_at');
app/Services\AuthService.php:221:        if (Schema::hasColumn('responders', 'responder_code')) {
app/Services\AuthService.php:225:        if (Schema::hasColumn('responders', 'username')) {
app/Services\AuthService.php:229:        if (Schema::hasColumn('responders', 'user_id')) {
app/Services\AuthService.php:234:            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:242:        if (Schema::hasColumn('users', 'user_id') && ! empty($responder->user_id)) {
app/Services\AuthService.php:246:        if (Schema::hasColumn('users', 'username') && ! empty($responder->username)) {
app/Services\AuthService.php:250:        if (Schema::hasColumn('users', 'login_id') && ! empty($responder->username)) {
app/Services\AuthService.php:255:            if (Schema::hasColumn('users', 'username')) {
app/Services\AuthService.php:259:            if (Schema::hasColumn('users', 'login_id')) {
app/Services\AuthService.php:263:            if (Schema::hasColumn('users', 'email')) {
app/Services\AuthService.php:269:            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:282:        if (Schema::hasColumn('users', 'username')) {
app/Services\AuthService.php:288:        if (Schema::hasColumn('users', 'login_id')) {
app/Services\AuthService.php:292:        if (Schema::hasColumn('users', 'email')) {
app/Services\AuthService.php:296:        if (Schema::hasColumn('users', 'user_id')) {
app/Services\AuthService.php:300:        if (Schema::hasColumn('users', 'deleted_at')) {
app/Services\AuthService.php:311:        if (! Schema::hasTable('households') || ! Schema::hasColumn('users', 'household_id')) {
app/Services\AuthService.php:321:            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:330:            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:340:        if (! $user->household_id || ! Schema::hasTable('households')) {
app/Services\AuthService.php:346:            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:356:        if (! Schema::hasTable('responders') || ! Schema::hasColumn('responders', 'is_validated')) {
app/Services\AuthService.php:365:            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\AuthService.php:410:        if (Schema::hasTable('roles') && Schema::hasColumn('users', 'role_id')) {
app/Services\AuthService.php:419:        foreach ($tables as $table) if (Schema::hasTable($table)) return $table;
app/Services\AuthService.php:425:        foreach ($columns as $column) if ($value !== null && Schema::hasColumn($table, $column)) $query->orWhere($column, $value);
app/Services\ArchiveService.php:152:        if (! Schema::hasTable($table)) {
app/Services\ArchiveService.php:172:            if ($category === 'disaster-events' && Schema::hasColumn($table, 'deleted_at')) {
app/Services\ArchiveService.php:279:            if (Schema::hasColumn('incident_archives', 'archived_by_admin_id')) {
app/Services\ArchiveService.php:283:            if (Schema::hasColumn('incident_archives', 'created_at')) {
app/Services\ArchiveService.php:287:            if (Schema::hasColumn('incident_archives', 'disaster_id')) {
app/Services\ArchiveService.php:392:        if (! Schema::hasTable('incident_archives')) {
app/Services\ArchiveService.php:406:            if (! Schema::hasColumn('incident_archives', $column)) {
app/Services\DashboardService.php:180:        if (! Schema::hasTable('disaster_events')) {
app/Services\DashboardService.php:226:        if (Schema::hasTable('households')) {
app/Services\DashboardService.php:228:                ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\DashboardService.php:232:        if (! $eventId || ! Schema::hasTable('household_disasters') || ! Schema::hasTable('household_statuses')) {
app/Services\DashboardService.php:290:        if (! $eventId || ! Schema::hasTable('responder_assignments')) {
app/Services\DashboardService.php:305:        $standby = Schema::hasTable('rescue_teams')
app/Services\DashboardService.php:342:        if (! $eventId || ! Schema::hasTable('weather_logs')) {
app/Services\DashboardService.php:370:        if (! Schema::hasTable('resource_requests')) {
app/Services\DashboardService.php:416:        if (! $eventId || ! Schema::hasTable('evacuation_centers')) {
app/Services\DashboardService.php:441:        if (! $eventId || ! Schema::hasTable('household_status_logs')) {
app/Services\DashboardService.php:602:        if (Schema::hasColumn('evacuation_centers', 'updated_at')) {
app/Services\DashboardService.php:613:        if (! $this->canUpdateEvacuationCenterEventLinks() || ! Schema::hasTable('disaster_events')) {
app/Services\DashboardService.php:636:        if (Schema::hasColumn('evacuation_centers', 'updated_at')) {
app/Services\DashboardService.php:647:        return Schema::hasTable('evacuation_centers')
app/Services\DashboardService.php:648:            && Schema::hasColumn('evacuation_centers', 'current_event_id');
app/Services\DashboardService.php:653:        if (! Schema::hasTable('household_disasters')) {
app/Services\DashboardService.php:659:        if (Schema::hasColumn('household_disasters', 'needs_dispatch')) {
app/Services\DashboardService.php:663:        if (Schema::hasColumn('household_disasters', 'updated_at')) {
app/Services\DashboardService.php:676:        if (! Schema::hasTable('responder_assignments')) {
app/Services\DashboardService.php:682:        if (Schema::hasColumn('responder_assignments', 'status')) {
app/Services\DashboardService.php:688:        if (Schema::hasColumn('responder_assignments', 'status')) {
app/Services\DashboardService.php:692:        if (Schema::hasColumn('responder_assignments', 'outcome_notes')) {
app/Services\DashboardService.php:696:        if (Schema::hasColumn('responder_assignments', 'updated_at')) {
app/Services\BarangayProfileService.php:67:        if (! Schema::hasTable('barangay_profiles')) {
app/Services\BarangayProfileService.php:85:        if (! Schema::hasTable('barangays')) {
app/Services\DisasterBroadcastService.php:197:            if (Schema::hasColumn('disaster_broadcasts', 'target_area_label')) {
app/Services\DisasterBroadcastService.php:201:            if (Schema::hasColumn('disaster_broadcasts', 'direct_impact_puroks_json')) {
app/Services\DisasterBroadcastService.php:205:            if (Schema::hasColumn('disaster_broadcasts', 'allowed_statuses_json')) {
app/Services\DisasterBroadcastService.php:209:            if (Schema::hasColumn('disaster_broadcasts', 'recipient_count')) {
app/Services\DisasterBroadcastService.php:213:            if (Schema::hasColumn('disaster_broadcasts', 'push_status')) {
app/Services\DisasterBroadcastService.php:251:        if (Schema::hasTable('disaster_broadcasts') && Schema::hasColumn('disaster_broadcasts', 'sms_status')) {
app/Services\DisasterBroadcastService.php:287:        if (! Schema::hasTable('disaster_broadcasts') || ! Schema::hasColumn('disaster_broadcasts', 'push_status')) {
app/Services\DisasterBroadcastService.php:345:            if (! Schema::hasTable('households')
app/Services\DisasterBroadcastService.php:346:                || ! Schema::hasColumn('households', 'address_id')
app/Services\DisasterBroadcastService.php:347:                || ! Schema::hasTable('addresses')
app/Services\DisasterBroadcastService.php:348:                || ! Schema::hasColumn('addresses', 'purok_sitio')) {
app/Services\DisasterBroadcastService.php:364:            if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:368:            if (Schema::hasColumn('addresses', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:538:            if (Schema::hasColumn('disaster_broadcasts', $column)) {
app/Services\DisasterBroadcastService.php:584:        if (! Schema::hasTable('disaster_broadcasts')) {
app/Services\DisasterBroadcastService.php:605:        if (! Schema::hasTable('weather_logs')) {
app/Services\DisasterBroadcastService.php:736:        if (Schema::hasColumn('disaster_types', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:740:        if (Schema::hasColumn('disaster_types', 'is_active')) {
app/Services\DisasterBroadcastService.php:772:        if (! Schema::hasTable('households')
app/Services\DisasterBroadcastService.php:773:            || ! Schema::hasColumn('households', 'address_id')
app/Services\DisasterBroadcastService.php:774:            || ! Schema::hasTable('addresses')
app/Services\DisasterBroadcastService.php:775:            || ! Schema::hasColumn('addresses', 'purok_sitio')) {
app/Services\DisasterBroadcastService.php:784:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:788:        if (Schema::hasColumn('addresses', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:824:        if (! $firstName || ! Schema::hasTable('addresses') || ! Schema::hasColumn('addresses', 'purok_id')) {
app/Services\DisasterBroadcastService.php:838:        if (Schema::hasTable('responders')) {
app/Services\DisasterBroadcastService.php:841:            if (Schema::hasColumn('responders', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:850:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\DisasterBroadcastService.php:858:                || ! Schema::hasTable('addresses')
app/Services\DisasterBroadcastService.php:859:                || ! Schema::hasColumn('addresses', 'purok_sitio')) {
app/Services\DisasterBroadcastService.php:867:            if (Schema::hasColumn('addresses', 'deleted_at')) {
app/Services\GlobalSearchService.php:34:        if (in_array('events', $types, true) && Schema::hasTable('disaster_events')) {
app/Services\GlobalSearchService.php:38:        if (in_array('broadcasts', $types, true) && Schema::hasTable('disaster_broadcasts')) {
app/Services\GlobalSearchService.php:42:        if (in_array('households', $types, true) && Schema::hasTable('households')) {
app/Services\GlobalSearchService.php:46:        if (in_array('responders', $types, true) && Schema::hasTable('responders')) {
app/Services\GlobalSearchService.php:50:        if (in_array('dispatches', $types, true) && Schema::hasTable('responder_assignments')) {
app/Services\GlobalSearchService.php:54:        if (in_array('resources', $types, true) && Schema::hasTable('resource_requests')) {
app/Services\GlobalSearchService.php:58:        if (in_array('sitreps', $types, true) && Schema::hasTable('situation_reports')) {
app/Services\EvacuationCheckInService.php:15:        if (! Schema::hasTable('evacuation_records')) {
app/Services\EvacuationCheckInService.php:32:        if (Schema::hasTable('households') && ! DB::table('households')->where('household_id', $validated['household_id'])->exists()) {
app/Services\EvacuationCheckInService.php:66:            if ($statusId && Schema::hasTable('household_status_logs')) {
app/Services\EvacuationCheckInService.php:85:            if (Schema::hasTable('household_disasters')) {
app/Services\EvacuationCheckInService.php:149:        if (! Schema::hasTable('disaster_events')) {
app/Services\EvacuationCheckInService.php:155:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\EvacuationCheckInService.php:159:        if (Schema::hasColumn('disaster_events', 'ended_at')) {
app/Services\EvacuationCheckInService.php:168:        if (! Schema::hasTable('household_statuses')) {
app/Services\EvacuationCheckInService.php:197:            ->filter(fn ($value, string $column): bool => Schema::hasColumn($table, $column))
app/Services\HouseholdStatusService.php:180:            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\HouseholdStatusService.php:256:        if (! Schema::hasTable('household_status_logs')) {
app/Services\HouseholdStatusService.php:280:                'reviewed_by_user_id' => Schema::hasColumn('household_status_logs', 'reviewed_by_user_id') ? $userId : null,
app/Services\HouseholdStatusService.php:281:                'reviewed_at' => Schema::hasColumn('household_status_logs', 'reviewed_at') ? $now : null,
app/Services\HouseholdStatusService.php:282:                'updated_at' => Schema::hasColumn('household_status_logs', 'updated_at') ? $now : null,
app/Services\HouseholdStatusService.php:328:            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
app/Services\HouseholdStatusService.php:670:            ->when(Schema::hasColumn('users', 'deleted_at'), fn ($query) => $query->whereNull('u.deleted_at'))
app/Services\HouseholdStatusService.php:779:            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('hm.deleted_at'))
app/Services\HouseholdStatusService.php:843:        if (! $eventId || ! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
app/Services\HouseholdStatusService.php:852:            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('hm.deleted_at'))
app/Services\HouseholdStatusService.php:976:            ->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($query) => $query->whereNull('de.deleted_at'))
app/Services\HouseholdMobileService.php:27:        if (! Schema::hasTable('households')) {
app/Services\HouseholdMobileService.php:65:                    'is_available' => Schema::hasTable('trusted_households'),
app/Services\HouseholdMobileService.php:83:            if (! Schema::hasTable($table)) {
app/Services\HouseholdMobileService.php:140:        if (! Schema::hasTable('device_tokens')) {
app/Services\HouseholdMobileService.php:179:        if (! Schema::hasTable('household_members')) {
app/Services\HouseholdMobileService.php:192:            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\HouseholdMobileService.php:271:            if (! Schema::hasTable($table)) {
app/Services\HouseholdMobileService.php:401:            if (! Schema::hasTable($table)) {
app/Services\HouseholdMobileService.php:481:            if (! Schema::hasTable($table)) {
app/Services\HouseholdMobileService.php:486:        if (! Schema::hasColumn('households', 'household_code') || ! Schema::hasColumn('households', 'contact_number')) {
app/Services\HouseholdMobileService.php:594:                'is_available' => Schema::hasTable('trusted_households'),
app/Services\HouseholdMobileService.php:638:        if (! Schema::hasTable('trusted_households')) {
app/Services\HouseholdMobileService.php:721:        if (! Schema::hasTable('household_members')) {
app/Services\HouseholdMobileService.php:725:        if (Schema::hasColumn('household_members', 'member_id')) {
app/Services\HouseholdMobileService.php:729:        if (Schema::hasColumn('household_members', 'id')) {
app/Services\HouseholdMobileService.php:747:            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\HouseholdMobileService.php:760:        if ($input === '' || ! Schema::hasTable('households')) {
app/Services\HouseholdMobileService.php:770:        if (Schema::hasTable('users')) {
app/Services\HouseholdMobileService.php:789:        if (Schema::hasColumn('households', 'household_code')) {
app/Services\HouseholdMobileService.php:798:        if (! Schema::hasTable('households')) {
app/Services\HouseholdMobileService.php:805:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\HouseholdMobileService.php:819:        if (Schema::hasTable('addresses') && Schema::hasColumn('households', 'address_id') && Schema::hasColumn('addresses', 'address_id')) {
app/Services\HouseholdMobileService.php:839:        if (! Schema::hasTable('disaster_events')) {
app/Services\HouseholdMobileService.php:846:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\HouseholdMobileService.php:850:        if (Schema::hasColumn('disaster_events', 'ended_at')) {
app/Services\HouseholdMobileService.php:854:        if (Schema::hasTable('disaster_types')) {
app/Services\HouseholdMobileService.php:861:        if (Schema::hasTable('severity_levels')) {
app/Services\HouseholdMobileService.php:890:        if (! Schema::hasTable('device_tokens')) {
app/Services\HouseholdMobileService.php:917:        if (Schema::hasTable('household_members')) {
app/Services\HouseholdMobileService.php:918:            $memberJoinColumn = Schema::hasColumn('household_members', 'member_id')
app/Services\HouseholdMobileService.php:920:                : (Schema::hasColumn('household_members', 'id') ? 'id' : null);
app/Services\HouseholdMobileService.php:923:        if (Schema::hasTable('household_members') && Schema::hasColumn('device_tokens', 'member_id') && $memberJoinColumn) {
app/Services\HouseholdMobileService.php:936:        $orderColumn = Schema::hasColumn('device_tokens', 'last_seen_at') ? 'dt.last_seen_at' : 'dt.id';
app/Services\HouseholdMobileService.php:962:        if (! Schema::hasTable('geotagged_locations')) {
app/Services\HouseholdMobileService.php:984:        if (Schema::hasColumn('geotagged_locations', 'updated_at')) {
app/Services\HouseholdMobileService.php:986:        } elseif (Schema::hasColumn('geotagged_locations', 'created_at')) {
app/Services\HouseholdMobileService.php:988:        } elseif (Schema::hasColumn('geotagged_locations', 'location_id')) {
app/Services\HouseholdMobileService.php:1015:            ! Schema::hasTable('evacuation_centers')
app/Services\HouseholdMobileService.php:1016:            || ! Schema::hasColumn('evacuation_centers', 'latitude')
app/Services\HouseholdMobileService.php:1017:            || ! Schema::hasColumn('evacuation_centers', 'longitude')
app/Services\HouseholdMobileService.php:1026:        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
app/Services\HouseholdMobileService.php:1030:        if (Schema::hasColumn('evacuation_centers', 'current_event_id') && $eventId) {
app/Services\HouseholdMobileService.php:1092:        if (! $eventId || ! Schema::hasTable('disaster_broadcasts')) {
app/Services\HouseholdMobileService.php:1138:        if (! Schema::hasTable('household_members')) {
app/Services\HouseholdMobileService.php:1164:            ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'));
app/Services\HouseholdMobileService.php:1166:        if (Schema::hasColumn('household_members', 'name')) {
app/Services\HouseholdMobileService.php:1168:        } elseif (Schema::hasColumn('household_members', 'full_name')) {
app/Services\HouseholdMobileService.php:1211:        if (! Schema::hasTable('household_status_logs')) {
app/Services\HouseholdMobileService.php:1218:        if ($eventId && Schema::hasColumn('household_status_logs', 'disaster_id')) {
app/Services\HouseholdMobileService.php:1222:        if (Schema::hasColumn('household_status_logs', 'source')) {
app/Services\HouseholdMobileService.php:1232:        if (Schema::hasTable('household_statuses')) {
app/Services\HouseholdMobileService.php:1243:        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
app/Services\HouseholdMobileService.php:1279:        if (! $eventId || ! Schema::hasTable('household_disasters')) {
app/Services\HouseholdMobileService.php:1283:        if (Schema::hasColumn('household_disasters', 'current_status_id')) {
app/Services\HouseholdMobileService.php:1285:        } elseif (Schema::hasColumn('household_disasters', 'initial_status_id')) {
app/Services\HouseholdMobileService.php:1302:        if (Schema::hasTable('household_statuses')) {
app/Services\HouseholdMobileService.php:1337:        if (! Schema::hasTable('household_status_logs')) {
app/Services\HouseholdMobileService.php:1343:            ->when($eventId && Schema::hasColumn('household_status_logs', 'disaster_id'), fn ($query) => $query->where('hsl.disaster_id', $eventId));
app/Services\HouseholdMobileService.php:1345:        if (Schema::hasColumn('household_status_logs', 'source')) {
app/Services\HouseholdMobileService.php:1361:        if (Schema::hasTable('household_statuses')) {
app/Services\HouseholdMobileService.php:1370:        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
app/Services\HouseholdMobileService.php:1399:        if (! Schema::hasTable('trusted_households')) {
app/Services\HouseholdMobileService.php:1458:        if (! Schema::hasTable('household_statuses')) {
app/Services\HouseholdMobileService.php:1595:        if (! $deviceId || ! Schema::hasTable('device_tracking_logs')) {
app/Services\HouseholdMobileService.php:1686:        if (! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
app/Services\HouseholdMobileService.php:1736:        if (! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
app/Services\HouseholdMobileService.php:1789:        if (! $deviceId || ! Schema::hasTable('device_tokens')) {
app/Services\HouseholdMobileService.php:1811:        if (! $deviceUuid || ! Schema::hasTable('device_tokens')) {
app/Services\HouseholdMobileService.php:1828:        return Schema::hasTable('geotagged_locations')
app/Services\HouseholdMobileService.php:1834:        if (! Schema::hasTable('device_tokens')) {
app/Services\HouseholdMobileService.php:1840:        if ($userId && Schema::hasColumn('device_tokens', 'user_id')) {
app/Services\HouseholdMobileService.php:1869:                ->when(Schema::hasColumn('household_members', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\HouseholdMobileService.php:1994:        if (! $relationship || ! Schema::hasTable('relationships')) {
app/Services\HouseholdMobileService.php:2029:        if (! $gender || ! Schema::hasTable('genders')) {
app/Services\HouseholdMobileService.php:2093:        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
app/Services\HouseholdMobileService.php:2102:        if (! Schema::hasTable($table)) {
app/Services\HouseholdMobileService.php:2115:        if (Schema::hasColumn($table, $column)) {
app/Services\HouseholdMobileService.php:2127:            if (Schema::hasColumn($table, $column)) {
app/Services\HouseholdMobileService.php:2139:        if (Schema::hasColumn('household_statuses', 'status_label')) {
app/Services\HouseholdMobileService.php:2143:        if (Schema::hasColumn('household_statuses', 'status_name')) {
app/Services\HouseholdMobileService.php:2152:        if (! Schema::hasTable('audit_logs')) {
app/Services\InquiryService.php:32:        if (! Schema::hasTable(self::TABLE)) {
app/Services\InquiryService.php:84:        if (! Schema::hasTable(self::TABLE)) {
app/Services\InquiryService.php:141:        if (! Schema::hasTable(self::TABLE)) {
app/Services\InquiryService.php:198:        if (! Schema::hasTable(self::TABLE)) {
app/Services\InquiryService.php:217:        if (! Schema::hasTable('users')) {
app/Services\InquiryService.php:221:        $hasDeletedAt = Schema::hasColumn('users', 'deleted_at');
app/Services\InquiryService.php:304:            ->filter(fn ($value, string $column): bool => Schema::hasColumn(self::TABLE, $column))
app/Services\InquiryService.php:310:        if (! Schema::hasTable('audit_logs')) {
app/Services\InquiryService.php:315:            ->filter(fn ($value, string $column): bool => Schema::hasColumn('audit_logs', $column) || in_array($column, ['created_at'], true))
app/Services\MobileDeviceService.php:15:        if (! Schema::hasTable('device_tokens')) {
app/Services\MobileDeviceService.php:55:        $canLinkUser = Schema::hasColumn('device_tokens', 'user_id');
app/Services\MobileDeviceService.php:56:        $canLinkResponder = Schema::hasColumn('device_tokens', 'responder_id');
app/Services\MobileDeviceService.php:57:        $canLinkMember = Schema::hasColumn('device_tokens', 'member_id');
app/Services\MobileDeviceService.php:140:        if (Schema::hasColumn('device_tokens', 'device_uuid')) {
app/Services\MobileDeviceService.php:144:        if ($roleKey === 'household_resident' && $householdId && Schema::hasColumn('device_tokens', 'household_id')) {
app/Services\MobileDeviceService.php:152:        } elseif ($householdId && Schema::hasColumn('device_tokens', 'household_id')) {
app/Services\MobileDeviceService.php:164:            ->first(fn (string $column): bool => Schema::hasColumn('users', $column));
app/Services\MobileDeviceService.php:212:        if (Schema::hasColumn('device_tokens', 'player_id')) {
app/Services\MobileDeviceService.php:228:        if (! Schema::hasTable('responders') || ! Schema::hasColumn('responders', 'user_id')) {
app/Services\MappingService.php:134:        if (! Schema::hasTable('geotagged_locations') || ! Schema::hasTable('households')) {
app/Services\MappingService.php:199:        if (! Schema::hasTable('evacuation_centers')) {
app/Services\MappingService.php:230:        if (! Schema::hasTable('responder_location_logs')) {
app/Services\MappingService.php:274:        if (! Schema::hasTable('responder_routes')) {
app/Services\MappingService.php:322:        $totalHouseholds = Schema::hasTable('households')
app/Services\MappingService.php:326:            $gpsTagged = Schema::hasTable('geotagged_locations') && Schema::hasTable('households')
app/Services\MappingService.php:338:        if (Schema::hasTable('geotagged_locations') && $this->hasColumn('geotagged_locations', 'accuracy_m')) {
app/Services\MappingService.php:355:            || ! Schema::hasTable('evacuation_centers')
app/Services\MappingService.php:380:        if (Schema::hasTable('puroks') && Schema::hasColumn('puroks', 'purok_name')) {
app/Services\MappingService.php:394:        if ($puroks->isEmpty() && Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'purok_sitio')) {
app/Services\MappingService.php:615:        if (empty($coordinates) && Schema::hasTable('route_coordinates')) {
app/Services\MappingService.php:639:        if (! Schema::hasTable('disaster_events')) {
app/Services\MappingService.php:740:        if (! $eventId || ! Schema::hasTable('member_disaster_statuses') || ! Schema::hasTable('member_statuses')) {
app/Services\MappingService.php:786:        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
app/Services\OneSignalNotificationService.php:130:        if (! Schema::hasTable('device_tokens') || ! Schema::hasColumn('device_tokens', 'player_id')) {
app/Services\OneSignalNotificationService.php:139:        if (Schema::hasColumn('device_tokens', 'push_provider')) {
app/Services\OneSignalNotificationService.php:143:        if (Schema::hasColumn('device_tokens', 'notification_permission_status')) {
app/Services\OneSignalNotificationService.php:147:        if (Schema::hasColumn('device_tokens', 'is_active')) {
app/Services\OneSignalNotificationService.php:169:        if (empty($roles) || ! Schema::hasColumn('device_tokens', 'app_role')) {
app/Services\OneSignalNotificationService.php:199:        if (Schema::hasColumn('device_tokens', 'responder_id')) {
app/Services\OneSignalNotificationService.php:205:        if (Schema::hasColumn('device_tokens', 'user_id') && Schema::hasTable('responders') && Schema::hasColumn('responders', 'user_id')) {
app/Services\OneSignalNotificationService.php:222:            || ! Schema::hasColumn('device_tokens', 'household_id')
app/Services\OneSignalNotificationService.php:223:            || ! Schema::hasTable('households')
app/Services\OneSignalNotificationService.php:224:            || ! Schema::hasColumn('households', 'household_id')
app/Services\OneSignalNotificationService.php:225:            || ! Schema::hasColumn('households', 'address_id')
app/Services\OneSignalNotificationService.php:226:            || ! Schema::hasTable('addresses')
app/Services\OneSignalNotificationService.php:227:            || ! Schema::hasColumn('addresses', 'address_id')
app/Services\OneSignalNotificationService.php:228:            || ! Schema::hasColumn('addresses', 'purok_sitio')) {
app/Services\OneSignalNotificationService.php:237:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\OneSignalNotificationService.php:241:        if (Schema::hasColumn('addresses', 'deleted_at')) {
app/Services\NotificationService.php:141:        if (! Schema::hasTable('notifications')) {
app/Services\NotificationService.php:184:        if (! Schema::hasTable('household_status_logs')) {
app/Services\NotificationService.php:215:        if (! Schema::hasTable('responder_assignments')) {
app/Services\NotificationService.php:244:        if (! Schema::hasTable('resource_requests')) {
app/Services\NotificationService.php:296:        if (! Schema::hasTable('weather_logs')) {
app/Services\NotificationService.php:342:        if (! Schema::hasTable('disaster_broadcasts')) {
app/Services\NotificationService.php:370:        if (! Schema::hasTable('audit_logs')) {
app/Services\NotificationService.php:436:        if (! Schema::hasTable('audit_logs') || ! $request->user()) {
app/Services\NotificationService.php:617:        if (! Schema::hasTable('audit_logs')) {
app/Services\RescueDispatchService.php:611:            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\RescueDispatchService.php:616:            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\RescueDispatchService.php:661:            ->when(Schema::hasColumn('responders', 'deleted_at'), fn ($query) => $query->whereNull('r.deleted_at'))
app/Services\RescueDispatchService.php:757:            ->when(Schema::hasColumn('households', 'deleted_at'), fn ($query) => $query->whereNull('h.deleted_at'))
app/Services\RescueDispatchService.php:877:        if (empty($dispatch->household_id) || ! Schema::hasTable('household_disasters') || ! Schema::hasTable('household_statuses')) {
app/Services\RescueDispatchService.php:924:        if (Schema::hasTable('household_status_logs')) {
app/Services\RescueDispatchService.php:959:        if (! Schema::hasTable('household_statuses')) {
app/Services\RescueDispatchService.php:1441:            ->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
app/Services\RescueDispatchService.php:1476:        if (! Schema::hasTable($table)) {
app/Services\ProfileService.php:129:        if (! Schema::hasTable('barangay_profiles')) {
app/Services\ProfileService.php:138:        if (Schema::hasTable('barangays')) {
app/Services\ProfileService.php:308:        if (! Schema::hasTable('audit_logs')) {
app/Services\ProfileService.php:339:            'can_save' => Schema::hasTable('barangay_profiles'),
app/Services\ProfileService.php:340:            'save_note' => Schema::hasTable('barangay_profiles')
app/Services\ProfileService.php:348:        if (! Schema::hasTable('barangays')) {
app/Services\ProfileService.php:386:        if (! Schema::hasTable('barangays')) {
app/Services\ProfileService.php:435:        if (! Schema::hasTable('audit_logs')) {
app/Services\RescuerMobileService.php:180:        if (! Schema::hasTable('responder_assignments')) {
app/Services\RescuerMobileService.php:259:        if (! Schema::hasTable('responder_location_logs')) {
app/Services\RescuerMobileService.php:310:        if (! Schema::hasTable('household_status_logs')) {
app/Services\RescuerMobileService.php:356:        if (Schema::hasTable('households') && ! DB::table('households')->where('household_id', $validated['household_id'])->exists()) {
app/Services\RescuerMobileService.php:436:        if (! Schema::hasTable('responder_check_ins')) {
app/Services\RescuerMobileService.php:504:        if (! Schema::hasTable('resource_requests')) {
app/Services\RescuerMobileService.php:566:        if (! Schema::hasTable('resource_requests')) {
app/Services\RescuerMobileService.php:573:        if (Schema::hasColumn('resource_requests', 'handled_by')) {
app/Services\RescuerMobileService.php:610:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:658:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:698:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:734:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:770:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:828:        if (! Schema::hasTable('responder_communication_logs')) {
app/Services\RescuerMobileService.php:861:        if (! $user || ! Schema::hasTable('responders')) {
app/Services\RescuerMobileService.php:870:        if (Schema::hasTable('rescue_teams')) {
app/Services\RescuerMobileService.php:886:        if (! Schema::hasTable('disaster_events')) {
app/Services\RescuerMobileService.php:897:        if (Schema::hasColumn('disaster_events', 'ended_at')) {
app/Services\RescuerMobileService.php:901:        if (Schema::hasTable('disaster_types')) {
app/Services\RescuerMobileService.php:908:        if (Schema::hasTable('severity_levels')) {
app/Services\RescuerMobileService.php:937:        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
app/Services\RescuerMobileService.php:953:        if (! $responderId || ! Schema::hasTable('responder_assignments')) {
app/Services\RescuerMobileService.php:969:        if (Schema::hasTable('rescue_teams')) {
app/Services\RescuerMobileService.php:978:        if (Schema::hasTable('disaster_events')) {
app/Services\RescuerMobileService.php:985:        if (Schema::hasTable('geotagged_locations')) {
app/Services\RescuerMobileService.php:989:        if (Schema::hasTable('geotagged_locations')) {
app/Services\RescuerMobileService.php:1010:        if (! Schema::hasTable('responder_routes')) {
app/Services\RescuerMobileService.php:1024:        $trailCoordinates = Schema::hasTable('route_coordinates')
app/Services\RescuerMobileService.php:1046:        if (! Schema::hasTable('household_status_logs')) {
app/Services\RescuerMobileService.php:1067:        if (Schema::hasColumn('household_status_logs', 'source')) {
app/Services\RescuerMobileService.php:1071:        if (Schema::hasTable('household_statuses')) {
app/Services\RescuerMobileService.php:1080:        if (Schema::hasTable('households')) {
app/Services\RescuerMobileService.php:1089:        if ($responderId && Schema::hasColumn('household_status_logs', 'responder_id')) {
app/Services\RescuerMobileService.php:1091:        } elseif ($userId && Schema::hasColumn('household_status_logs', 'submitted_by_user_id')) {
app/Services\RescuerMobileService.php:1095:        if ($eventId !== '' && Schema::hasColumn('household_status_logs', 'disaster_id')) {
app/Services\RescuerMobileService.php:1099:        if ($statusId !== '' && Schema::hasColumn('household_status_logs', 'status_id')) {
app/Services\RescuerMobileService.php:1101:        } elseif ($status !== '' && $status !== 'all' && Schema::hasTable('household_statuses')) {
app/Services\RescuerMobileService.php:1105:        $orderColumn = Schema::hasColumn('household_status_logs', 'submitted_at')
app/Services\RescuerMobileService.php:1134:        if (! Schema::hasTable('household_status_logs')) {
app/Services\RescuerMobileService.php:1142:                if (Schema::hasColumn('household_status_logs', 'source')) {
app/Services\RescuerMobileService.php:1146:                if ($eventId !== '' && Schema::hasColumn('household_status_logs', 'disaster_id')) {
app/Services\RescuerMobileService.php:1150:                if (! empty($option['status_id']) && Schema::hasColumn('household_status_logs', 'status_id')) {
app/Services\RescuerMobileService.php:1169:        if (! Schema::hasTable('resource_requests')) {
app/Services\RescuerMobileService.php:1175:        if (Schema::hasColumn('resource_requests', 'request_source')) {
app/Services\RescuerMobileService.php:1179:        if ($userId && Schema::hasColumn('resource_requests', 'handled_by')) {
app/Services\RescuerMobileService.php:1270:        $existingResponderLogin = Schema::hasTable('responders')
app/Services\RescuerMobileService.php:1356:        if (! Schema::hasTable('household_statuses')) {
app/Services\RescuerMobileService.php:1391:        if (! Schema::hasTable('household_statuses')) {
app/Services\RescuerMobileService.php:1397:        if (Schema::hasColumn('household_statuses', 'status_label')) {
app/Services\RescuerMobileService.php:1401:        if (Schema::hasColumn('household_statuses', 'status_name')) {
app/Services\RescuerMobileService.php:1410:        if (! Schema::hasTable('urgency_levels')) {
app/Services\RescuerMobileService.php:1422:        if (! Schema::hasTable('resource_request_status')) {
app/Services\RescuerMobileService.php:1433:        if (! Schema::hasTable('responder_routes') || ! Schema::hasTable('route_coordinates')) {
app/Services\RescuerMobileService.php:1474:        if (! $responder || ! Schema::hasTable('responder_location_logs')) {
app/Services\RescuerMobileService.php:1497:        if (! Schema::hasTable('responder_routes') || ! $this->hasPoint($start)) {
app/Services\RescuerMobileService.php:1595:        if (! $responderId || ! Schema::hasTable('responders')) {
app/Services\RescuerMobileService.php:1611:        if ($teamId && Schema::hasTable('rescue_teams')) {
app/Services\RescuerMobileService.php:1623:        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
app/Services\RescuerMobileService.php:1632:        if (! Schema::hasTable('responder_check_ins')) {
app/Services\RescuerMobileService.php:1662:        if (! Schema::hasTable('responder_field_reports') || ! $responderId) {
app/Services\RescuerMobileService.php:1680:        if (! Schema::hasTable('evacuation_centers') || ! Schema::hasColumn('evacuation_centers', 'latitude')) {
app/Services\RescuerMobileService.php:1688:        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
app/Services\RescuerMobileService.php:1713:        } while (Schema::hasTable('resource_requests') && ResourceRequest::query()->where('request_id', $requestId)->exists());
app/Services\RescuerMobileService.php:1720:        if (! Schema::hasTable($table)) {
app/Services\RescuerMobileService.php:1733:        if (Schema::hasColumn($table, $column)) {
app/Services\RescuerMobileService.php:1745:            if (Schema::hasColumn($table, $column)) {
app/Services\RescuerMobileService.php:1757:        if (Schema::hasColumn('household_statuses', 'status_label')) {
app/Services\RescuerMobileService.php:1761:        if (Schema::hasColumn('household_statuses', 'status_name')) {
app/Services\RescuerMobileService.php:1856:        if (! Schema::hasTable('household_disasters')) {
app/Services\RescuerMobileService.php:1875:        if (Schema::hasColumn('household_disasters', 'last_responder_id')) {
app/Services\RescuerMobileService.php:2005:        if (! Schema::hasTable('responders')) {
app/Services\RescuerMobileService.php:2023:        if (Schema::hasTable('rescue_teams')) {
app/Services\RescuerMobileService.php:2189:        if (! Schema::hasTable('audit_logs')) {
app/Services\SituationReportService.php:151:        if (! Schema::hasTable('situation_reports')) {
app/Services\SituationReportService.php:230:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\SituationReportService.php:254:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\SituationReportService.php:314:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\SituationReportService.php:375:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\SituationReportService.php:442:        if (Schema::hasColumn('evacuation_centers', 'deleted_at')) {
app/Services\SituationReportService.php:760:        if (! Schema::hasTable('audit_logs')) {
app/Services\ResourceRequestService.php:284:        if (! Schema::hasTable('resource_requests')) {
app/Services\ResourceRequestService.php:1136:        if (! Schema::hasTable('request_validations')) {
app/Services\ResourceRequestService.php:1167:        if (! Schema::hasTable('request_validations')) {
app/Services\ResourceRequestService.php:1231:            ->when(Schema::hasColumn('evacuation_centers', 'deleted_at'), fn ($query) => $query->whereNull('ec.deleted_at'))
app/Services\ResourceRequestService.php:1303:            ->when(Schema::hasColumn('disaster_events', 'deleted_at'), fn ($query) => $query->whereNull('de.deleted_at'))
app/Services\ResourceRequestService.php:1485:        if (! Schema::hasTable('audit_logs')) {
app/Services\SmsGatewayService.php:67:        if (! Schema::hasTable('households') || ! Schema::hasColumn('households', 'contact_number')) {
app/Services\SmsGatewayService.php:72:        if (Schema::hasColumn('households', 'deleted_at')) {
app/Services\SmsGatewayService.php:77:        if ($puroks !== [] && (! Schema::hasColumn('households', 'address_id')
app/Services\SmsGatewayService.php:78:            || ! Schema::hasTable('addresses')
app/Services\SmsGatewayService.php:79:            || ! Schema::hasColumn('addresses', 'purok_sitio'))) {
app/Services\SmsGatewayService.php:88:            if (Schema::hasColumn('addresses', 'deleted_at')) {
app/Services\WeatherSnapshotService.php:34:            'has_weather_table' => Schema::hasTable('weather_logs'),
app/Services\WeatherSnapshotService.php:46:        if (! Schema::hasTable('weather_logs')) {
app/Services\WeatherSnapshotService.php:67:        if (! Schema::hasTable('weather_logs')) {
app/Services\WeatherSnapshotService.php:114:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\WeatherSnapshotService.php:139:        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
app/Services\WeatherSnapshotService.php:363:        $latestAdvisory = Schema::hasTable('disaster_broadcasts')
```

### Appendix C. Query/materialization candidates

```text
No matches.
```

### Appendix D1. DB/model write candidates

```text
app/Services\ProfileService.php:65:            ->update([
app/Services\ProfileService.php:110:            ->update([
app/Services\ProfileService.php:188:            BarangayProfile::query()->update(['is_active' => 0]);
app/Services\ProfileService.php:193:                    ->update($values);
app/Services\ProfileService.php:203:            BarangayProfile::query()->create($values);
app/Services\ProfileService.php:439:        AuditLog::query()->create([
app/Services\MobileDeviceService.php:107:                DB::table('device_tokens')->where('id', $existing->id)->update($data);
app/Services\MobileDeviceService.php:112:            DB::table('device_tokens')->insert($this->filterColumns('device_tokens', array_merge($data, [
app/Services\MobileDeviceService.php:176:            ->update([
app/Services\EvacuationCheckInService.php:52:            DB::table('evacuation_records')->insert($this->filterColumns('evacuation_records', [
app/Services\EvacuationCheckInService.php:69:                DB::table('household_status_logs')->insert($this->filterColumns('household_status_logs', [
app/Services\EvacuationCheckInService.php:97:                    ->update($this->filterColumns('household_disasters', $update));
app/Services\DisasterBroadcastService.php:71:            DisasterEvent::query()->create([
app/Services\DisasterBroadcastService.php:118:            $event->save();
app/Services\DisasterBroadcastService.php:217:            DisasterBroadcast::query()->create($data);
app/Services\DisasterBroadcastService.php:252:            DisasterBroadcast::query()->where('broadcast_id', $broadcastId)->update([
app/Services\DisasterBroadcastService.php:293:            ->update($this->filterColumns('disaster_broadcasts', [
app/Services\NotificationService.php:621:        AuditLog::query()->create([
app/Services\InquiryService.php:53:        $inquiry = LandingInquiry::query()->create($insert);
app/Services\InquiryService.php:166:            ->update($update);
app/Services\InquiryService.php:322:        AuditLog::query()->create($logData);
app/Services\DashboardService.php:120:                ->update([
app/Services\DashboardService.php:564:        DB::table('incident_archives')->insert([
app/Services\DashboardService.php:585:            ->update([
app/Services\DashboardService.php:608:            ->update($updates);
app/Services\DashboardService.php:642:            ->update($updates);
app/Services\DashboardService.php:671:        HouseholdDisaster::query()->where('disaster_id', $eventId)->update($updates);
app/Services\DashboardService.php:704:        return $query->update($updates);
app/Services\DashboardService.php:717:        AuditLog::query()->insert([
app/Services\HouseholdMobileService.php:230:            ->update($this->filterColumns('household_members', [
app/Services\HouseholdMobileService.php:689:        DB::table('trusted_households')->insert($this->filterColumns('trusted_households', [
app/Services\HouseholdMobileService.php:1514:            DB::table('geotagged_locations')->where('household_id', $householdId)->update($data);
app/Services\HouseholdMobileService.php:1518:        DB::table('geotagged_locations')->insert($this->filterColumns('geotagged_locations', array_merge($data, [
app/Services\HouseholdMobileService.php:1582:            DB::table('device_tokens')->where('id', $existing->id)->update($data);
app/Services\HouseholdMobileService.php:1586:        DB::table('device_tokens')->insert($this->filterColumns('device_tokens', array_merge($data, [
app/Services\HouseholdMobileService.php:1603:        DB::table('device_tracking_logs')->insert($this->filterColumns('device_tracking_logs', [
app/Services\HouseholdMobileService.php:1646:                ->update($data);
app/Services\HouseholdMobileService.php:1651:        DB::table('household_disasters')->insert($this->filterColumns('household_disasters', array_merge($data, [
app/Services\HouseholdMobileService.php:1796:            ->update($this->filterColumns('device_tokens', [
app/Services\HouseholdMobileService.php:2156:        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
app/Services\HouseholdStatusService.php:279:            ->update(array_filter([
app/Services\HouseholdStatusService.php:1244:                ->update($data);
app/Services\HouseholdStatusService.php:1251:        HouseholdDisaster::query()->create(array_merge($data, [
app/Services\HouseholdStatusService.php:1269:            ->update([
app/Services\HouseholdStatusService.php:1288:        DeviceTrackingLog::query()->create([
app/Services\HouseholdStatusService.php:1316:        AuditLog::query()->create([
app/Services\RescuerAccountService.php:160:            DB::table('users')->insert([
app/Services\RescuerAccountService.php:178:            DB::table('responders')->insert([
app/Services\RescuerAccountService.php:257:                ->update($userUpdate);
app/Services\RescuerAccountService.php:287:                ->update($responderUpdate);
app/Services\RescuerAccountService.php:319:                ->update([
app/Services\RescuerAccountService.php:326:                ->update([
app/Services\RescuerAccountService.php:371:            DB::table('rescue_teams')->insert([
app/Services\RescuerAccountService.php:422:                ->update([
app/Services\RescuerAccountService.php:470:                ->update([
app/Services\RescuerAccountService.php:477:                ->delete();
app/Services\RescuerAccountService.php:759:            ->update([
app/Services\RescuerAccountService.php:767:                ->update([
app/Services\RescuerAccountService.php:776:                ->update([
app/Services\RescuerAccountService.php:966:        DB::table('rescue_teams')->insert([
app/Services\RescuerAccountService.php:1389:        DB::table('audit_logs')->insert([
app/Services\RescuerAccountService.php:1410:        DB::table('audit_logs')->insert([
app/Services\ResourceRequestService.php:260:            $resourceRequest->save();
app/Services\ResourceRequestService.php:342:                    ->update($row);
app/Services\ResourceRequestService.php:398:                ->update([
app/Services\ResourceRequestService.php:481:                    ->update([
app/Services\ResourceRequestService.php:548:                ->update([
app/Services\ResourceRequestService.php:604:                ->update([
app/Services\ResourceRequestService.php:1489:        AuditLog::query()->create([
app/Services\AuthService.php:78:        $request->user()->currentAccessToken()?->delete();
app/Services\AuthService.php:180:        User::query()->whereKey($user->user_id)->update([
app/Services\AuthService.php:191:        User::query()->whereKey($user->user_id)->update([
app/Services\SituationReportService.php:102:            DB::table('situation_reports')->insert([
app/Services\SituationReportService.php:174:        DB::table('situation_reports')->insert([
app/Services\SituationReportService.php:764:        AuditLog::query()->create([
app/Services\RescueDispatchService.php:174:            ResponderAssignment::query()->create([
app/Services\RescueDispatchService.php:286:                ->update($updates);
app/Services\RescueDispatchService.php:323:                ->update([
app/Services\RescueDispatchService.php:365:            ResponderLocationLog::query()->create([
app/Services\RescueDispatchService.php:379:                ->update([
app/Services\RescueDispatchService.php:913:                ->update($data);
app/Services\RescueDispatchService.php:915:            HouseholdDisaster::query()->create(array_merge($data, [
app/Services\RescueDispatchService.php:925:            HouseholdStatusLog::query()->create([
app/Services\RescueDispatchService.php:1012:            ResponderRoute::query()->create([
app/Services\RescueDispatchService.php:1033:        RouteCoordinate::query()->create([
app/Services\RescueDispatchService.php:1137:            ->update([
app/Services\RescueDispatchService.php:1147:                ->update([
app/Services\RescueDispatchService.php:1535:        AuditLog::query()->create([
app/Services\RescuerMobileService.php:80:                ->update($this->filterColumns('users', [
app/Services\RescuerMobileService.php:92:                ->update($this->filterColumns('responders', [
app/Services\RescuerMobileService.php:237:            ->update($updates);
app/Services\RescuerMobileService.php:369:        DB::table('household_status_logs')->insert($this->filterColumns('household_status_logs', [
app/Services\RescuerMobileService.php:464:        DB::table('responder_check_ins')->insert($this->filterColumns('responder_check_ins', [
app/Services\RescuerMobileService.php:593:            ->update($this->filterColumns('resource_requests', [
app/Services\RescuerMobileService.php:1445:            DB::table('responder_routes')->insert($this->filterColumns('responder_routes', [
app/Services\RescuerMobileService.php:1461:        DB::table('route_coordinates')->insert($this->filterColumns('route_coordinates', [
app/Services\RescuerMobileService.php:1480:        DB::table('responder_location_logs')->insert($this->filterColumns('responder_location_logs', [
app/Services\RescuerMobileService.php:1540:                ->update($data);
app/Services\RescuerMobileService.php:1545:        DB::table('responder_routes')->insert(array_merge($data, $this->filterColumns('responder_routes', [
app/Services\RescuerMobileService.php:1604:            ->update($this->filterColumns('responders', [
app/Services\RescuerMobileService.php:1614:                ->update($this->filterColumns('rescue_teams', [
app/Services\RescuerMobileService.php:1666:        DB::table('responder_field_reports')->insert($this->filterColumns('responder_field_reports', [
app/Services\RescuerMobileService.php:1887:                ->update($data);
app/Services\RescuerMobileService.php:1892:        DB::table('household_disasters')->insert($this->filterColumns('household_disasters', array_merge($data, [
app/Services\RescuerMobileService.php:1944:        DB::table('responder_communication_logs')->insert($this->filterColumns('responder_communication_logs', [
app/Services\RescuerMobileService.php:2193:        DB::table('audit_logs')->insert($this->filterColumns('audit_logs', [
app/Services\WeatherSnapshotService.php:81:        WeatherLog::query()->create([
app/Services\ArchiveService.php:175:                    ->update([
app/Services\ArchiveService.php:181:                    ->delete();
app/Services\ArchiveService.php:291:            DB::table('incident_archives')->insert($insertData);
app/Services\ArchiveService.php:323:            ->delete();
app/Services\ArchiveService.php:367:                ->delete();
app/Services\ArchiveService.php:372:                ->update([
app/Services\TrackingAidForwardingService.php:65:                ->update([
app/Services\TrackingAidForwardingService.php:187:        Schema::connection($connection)->create($table, function (Blueprint $table): void {
```

### Appendix D2. Transaction and row-lock candidates

```text
app/Services\ArchiveService.php:268:        $group = DB::transaction(function () use ($request, $validated, $groupId, $archiveNote, $now): array {
app/Services\ArchiveService.php:269:            $archiveId = ((int) DB::table('incident_archives')->lockForUpdate()->max('archive_id')) + 1;
app/Services\HouseholdMobileService.php:116:        DB::transaction(function () use ($householdId, $user, $validated, $now, &$deviceId): void {
app/Services\HouseholdMobileService.php:159:        DB::transaction(function () use ($householdId, $user, $validated, $now, &$deviceId): void {
app/Services\HouseholdMobileService.php:318:        DB::transaction(function () use ($request, $householdId, $user, $activeEvent, $validated, $status, $now, &$statusLogId, $deviceId, $memberId, $memberName): void {
app/Services\HouseholdMobileService.php:443:        DB::transaction(function () use ($request, $householdId, $user, $activeEvent, $validated, $status, $now, &$statusLogId, $deviceId): void {
app/Services\HouseholdMobileService.php:525:        $statusLogId = DB::transaction(function () use ($household, $activeEvent, $status, $validated, $now): int {
app/Services\MobileDeviceService.php:105:        DB::transaction(function () use ($existing, $data, $now): void {
app/Services\DashboardService.php:102:        $closedEvent = DB::transaction(function () use ($request): ?array {
app/Services\DashboardService.php:107:                ->lockForUpdate()
app/Services\EvacuationCheckInService.php:51:        DB::transaction(function () use ($validated, $eventId, $now, $userId, $statusId, $evacuationId): void {
app/Services\DisasterBroadcastService.php:67:        $eventId = DB::transaction(function () use ($validated): string {
app/Services\DisasterBroadcastService.php:102:        $result = DB::transaction(function () use ($eventId, $validated): string {
app/Services\DisasterBroadcastService.php:106:                ->lockForUpdate()
app/Services\DisasterBroadcastService.php:173:        $broadcastId = DB::transaction(function () use ($request, $validated, $metadata, $event): int {
app/Services\DisasterBroadcastService.php:890:            ->lockForUpdate()
app/Services\ProfileService.php:165:        DB::transaction(function () use ($request, $validated): void {
app/Services\RescuerAccountService.php:146:        $rescuer = DB::transaction(function () use ($request, $validated): array {
app/Services\RescuerAccountService.php:231:        $rescuer = DB::transaction(function () use ($request, $validated, $existing, $responderId): array {
app/Services\RescuerAccountService.php:313:        DB::transaction(function () use ($request, $existing, $responderId): void {
app/Services\RescuerAccountService.php:358:        $teamId = DB::transaction(function () use ($request, $validated): int {
app/Services\RescuerAccountService.php:407:        DB::transaction(function () use ($request, $teamId, $existing, $validated): void {
app/Services\RescuerAccountService.php:465:        DB::transaction(function () use ($request, $teamId, $existing): void {
app/Services\RescuerMobileService.php:77:        DB::transaction(function () use ($request, $user, $responder, $validated, $username, $fullName, $now): void {
app/Services\SituationReportService.php:87:        $report = DB::transaction(function () use ($request, $validated, $event): array {
app/Services\SituationReportService.php:669:        return ((int) DB::table($table)->lockForUpdate()->max($column)) + 1;
app/Services\HouseholdStatusService.php:219:        $statusLogId = DB::transaction(function () use ($validated, $activeEvent, $householdId, $user, $source, $now, $deviceId, $request): int {
app/Services\HouseholdStatusService.php:1223:            ->lockForUpdate()
app/Services\HouseholdStatusService.php:1249:        $nextId = ((int) HouseholdDisaster::query()->lockForUpdate()->max('household_disaster_id')) + 1;
app/Services\HouseholdStatusService.php:1286:        $nextTrackingId = ((int) DeviceTrackingLog::query()->lockForUpdate()->max('tracking_id')) + 1;
app/Services\ResourceRequestService.php:179:        $created = DB::transaction(function () use ($request, $validated, $activeEvent): array {
app/Services\ResourceRequestService.php:242:        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
app/Services\ResourceRequestService.php:301:        $saved = DB::transaction(function () use (
app/Services\ResourceRequestService.php:391:        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
app/Services\ResourceRequestService.php:461:            $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
app/Services\ResourceRequestService.php:542:        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
app/Services\ResourceRequestService.php:598:        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
app/Services\RescueDispatchService.php:161:        $assignmentId = DB::transaction(function () use ($request, $validated, $responderId, $selectedResponderIds, $activeEvent): int {
app/Services\RescueDispatchService.php:255:        DB::transaction(function () use ($request, $assignmentId, $dispatch, $validated): void {
app/Services\RescueDispatchService.php:318:        DB::transaction(function () use ($request, $assignmentId, $dispatch, $validated): void {
app/Services\RescueDispatchService.php:361:        DB::transaction(function () use ($request, $dispatch, $assignmentId, $validated): void {
app/Services\RescueDispatchService.php:1471:        return ((int) $query->lockForUpdate()->max($column)) + 1;
```

### Appendix D3. Counter increments

```text
No matches.
```

### Appendix D4. External call candidates

```text
app/Services\OneSignalNotificationService.php:10:class OneSignalNotificationService
app/Services\OneSignalNotificationService.php:15:            return $this->result('not_configured', 0, 'OneSignal credentials are not configured.');
app/Services\OneSignalNotificationService.php:21:            return $this->result('no_recipients', 0, 'No OneSignal mobile recipients were found.');
app/Services\OneSignalNotificationService.php:48:                'message' => 'OneSignal did not accept the notification.',
app/Services\OneSignalNotificationService.php:58:            'message' => 'OneSignal notification submitted.',
app/Services\OneSignalNotificationService.php:86:            $response = Http::withHeaders([
app/Services\OneSignalNotificationService.php:116:            Log::warning('OneSignal push failed', [
app/Services\OneSignalNotificationService.php:140:            $query->where('dt.push_provider', 'onesignal');
app/Services\OneSignalNotificationService.php:255:        return trim((string) config('services.onesignal.app_id'));
app/Services\OneSignalNotificationService.php:260:        return trim((string) config('services.onesignal.api_key'));
app/Services\OneSignalNotificationService.php:265:        return trim((string) config('services.onesignal.base_url', 'https://api.onesignal.com'));
app/Services\MobileDeviceService.php:25:            'push_provider' => ['nullable', Rule::in(['onesignal'])],
app/Services\MobileDeviceService.php:46:        $tokenColumn = $this->oneSignalTokenColumn();
app/Services\MobileDeviceService.php:50:                'message' => 'The selected database has no OneSignal player_id column. Add device_tokens.player_id before registering mobile notifications.',
app/Services\MobileDeviceService.php:60:        $savedPlayerId = $this->oneSignalPlayerIdForStorage($validated);
app/Services\MobileDeviceService.php:86:            'push_provider' => 'onesignal',
app/Services\MobileDeviceService.php:122:                'push_token_saved' => $this->isUsableOneSignalPlayerId($savedPlayerId),
app/Services\MobileDeviceService.php:172:        $savedToken = $this->oneSignalPlayerIdForStorage($validated);
app/Services\MobileDeviceService.php:185:                'push_token_saved' => $this->isUsableOneSignalPlayerId($savedToken),
app/Services\MobileDeviceService.php:191:    private function oneSignalPlayerIdForStorage(array $validated): string
app/Services\MobileDeviceService.php:205:    private function isUsableOneSignalPlayerId(string $token): bool
app/Services\MobileDeviceService.php:210:    private function oneSignalTokenColumn(): ?string
app/Services\RescueDispatchService.php:27:    public function __construct(private OneSignalNotificationService $oneSignal) {}
app/Services\RescueDispatchService.php:203:        $pushResult = $this->oneSignal->sendToResponderIds(
app/Services\DisasterBroadcastService.php:23:        private OneSignalNotificationService $oneSignal,
app/Services\DisasterBroadcastService.php:24:        private SmsGatewayService $smsGateway,
app/Services\DisasterBroadcastService.php:243:        return $this->smsGateway->sendBroadcastSms(
app/Services\DisasterBroadcastService.php:268:        return $this->oneSignal->sendToMobileDevices(
app/Services\DisasterBroadcastService.php:294:                'push_status' => 'onesignal_'.$pushResult['status'],
app/Services\DisasterBroadcastService.php:295:                'channel' => 'onesignal',
app/Services\DisasterBroadcastService.php:303:            'sent' => 'Broadcast saved and sent through OneSignal to '.$pushResult['sent_count'].' device(s).',
app/Services\DisasterBroadcastService.php:304:            'partial' => 'Broadcast saved. OneSignal sent to '.$pushResult['sent_count'].' of '.$pushResult['recipient_count'].' device(s).',
app/Services\DisasterBroadcastService.php:305:            'no_recipients' => 'Broadcast saved, but no OneSignal-ready mobile devices were found.',
app/Services\DisasterBroadcastService.php:306:            'not_configured' => 'Broadcast saved, but OneSignal credentials are not configured.',
app/Services\DisasterBroadcastService.php:307:            default => 'Broadcast saved, but OneSignal delivery failed. Check backend logs and OneSignal credentials.',
app/Services\RoutingService.php:8:class RoutingService
app/Services\RoutingService.php:21:            $response = Http::withOptions([
app/Services\RescuerMobileService.php:18:    private RoutingService $routingService;
app/Services\RescuerMobileService.php:20:    public function __construct(RoutingService $routingService)
app/Services\RescuerMobileService.php:22:        $this->routingService = $routingService;
app/Services\RescuerMobileService.php:1507:        $route = $this->routingService->drivingRoute(
app/Services\HouseholdMobileService.php:1541:            'push_provider' => 'onesignal',
app/Services\SmsGatewayService.php:10:class SmsGatewayService
app/Services\SmsGatewayService.php:31:                $response = Http::withBasicAuth($this->username(), $this->password())
app/Services\WeatherService.php:10:class WeatherService
app/Services\WeatherSnapshotService.php:158:        $request = Http::timeout(15)->retry(1, 300);
```
