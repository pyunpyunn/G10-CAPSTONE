# B2 list and collection audit

Reviewed `app/Http/Controllers/Api`, `app/Services`, and `app/Queries` on 2026-10-01. Growth is inferred from the table's role; no production cardinalities or remote database measurements were used. `get()` calls on an already limited query and `pluck()` over grouped status keys are bounded and omitted below. Collection `get()`/`pluck()` calls are also omitted.

2026-10-02 update: `ResourceRequestMirror` replaced the unbounded fallback request collection with `lazyById(500)` and constant-size aggregation. TrackingAid acknowledged rows are capped at 100, with a separate aggregate count for the summary. Dashboard ended-event cleanup now uses a set-based update. Map member-status fallbacks use one batched read for up to 1,500 visible households. Full-scan work can still grow with history, so these are memory bounds rather than a remote latency guarantee.

| Endpoint / operation | Table(s) and source | Growth | Current bound | Recommended fix |
| --- | --- | --- | --- | --- |
| `GET /api/v1/archive/*` and `/archive/export` | `incident_archives.archive_note` in `ArchiveQuery::savedGroupRecordIds` | Every saved archive group | None; loads every JSON blob and builds a `whereNotIn` list | Normalize saved record IDs into a child table and use `NOT EXISTS`; until migrated, avoid decoding all blobs on each list/export request. |
| `GET /api/v1/archive/*` options | `addresses.purok_sitio` in `ArchiveQuery::purokOptions` | Address count, distinct values small | No SQL limit | Cache distinct lookup and cap to the documented barangay vocabulary. |
| `GET /api/v1/dispatches` workspace data | `responders`, `households`, `responder_assignments`, `rescue_teams` in `RescueDispatchQuery` | Responders/households/assignments grow | None on responder, household, busy-assignment, team collections | Paginate household chooser; bound responder/team lookups; replace busy assignment `pluck` with an `EXISTS` or grouped subquery. |
| `GET /api/v1/disaster-events/{eventId}/situation-summary` | `households`, `responder_assignments` in `SituationReportQuery::summarySources` | Households and dispatch history grow by event | Per-purok/status counts now aggregate in SQL; 8 assignment detail rows; outcome/status scan uses `lazyById(500)` | Keep the bounded read; consider indexed SQL JSON aggregation when representative query plans justify it. |
| `GET /api/v1/notifications` | `audit_logs` in `NotificationQuery::savedViewRows` | Per-user lifetime history | Reads now use `lazyById(500)` and retain IDs only for the bounded current feed; full history is still scanned | Add an indexed compact view-state table or snapshot to avoid scanning all past actions on every request. |
| `POST /api/v1/notifications/clear-all` | Current feed IDs in `NotificationService` | Feed currently capped by source queries | Source cap of 10–20 per type | Define whether clear-all means visible feed or all history; implement a set-based update when all history is intended. |
| `GET /api/v1/resource-requests` tracking mirror | `resource_requests` in `ResourceRequestMirror::summarize` | Open requests grow indefinitely | 500-row keyset pages; fixed eight-entry accumulator | Consider a maintained aggregate or category key for constant read time on very large histories. |
| `GET /api/v1/rescuers/team-config` | `rescue_teams`, `responders`, `addresses` in `RescuerAccountQuery` | Teams and responders grow | Several unbounded `get`/`pluck`, including a per-team member query | Bound lookup lists and paginate team cards; prefetch members in one grouped query. |
| `GET /api/v1/map/overview` and `/map/household-geotags` | `geotagged_locations`, `households` in `MappingQuery::getHouseholdGeotags` | One or more geotags per household | 1,500 rows, no bounding box; member-status fallback is one batched read | Require viewport bounds or event/purok filter and keep the server cap. The web map currently calls without bounds, so the client must send viewport coordinates before this becomes required. |
| `GET /api/v1/map/dispatch-routes` | `route_coordinates` in `MappingQuery::routeCoordinates` | Coordinates per route grow | 50 routes; coordinates per route unbounded | Cap/simplify points per route or fetch a precomputed geometry. |
| `GET /api/v1/households/{householdId}` and mobile household detail | `household_members`, `device_tracking_logs`, `household_status_logs` in `HouseholdStatusQuery`/`HouseholdMobileReadQuery` | Per-household history grows | Some logs capped at 50, other member/device reads unbounded | Bound history, use latest-row subqueries, document maximum member/device lookup size. |
| Broadcast recipient selection | `households`, `addresses`, `device_tokens` in broadcast/SMS/push services | All eligible recipients | Unbounded `pluck` in several send paths | Stream or chunk recipient IDs for send jobs; never collect all tokens before delivery. |
| Small dropdowns | `puroks`, `addresses`, `urgency_levels`, `disaster_types`, `severity_levels` in several Query classes | Small reference tables except addresses | Some `get`/`pluck` unbounded | Cache and cap address-derived distinct lists; static reference tables can have a documented maximum. |

## Existing protections

- `ListRequest` validates `page >= 1` and `1 <= per_page <= 100`, with a default of 15. Archive, inquiry, household status, dispatch, rescuer account, and resource request list controllers use it.
- Their query/service pagination is clamped on the server as well; archive and direct `InquiryListQuery`/`RescuerAccountQuery` callers are now clamped.
- Dashboard summary queries mostly use SQL `COUNT`/`GROUP BY`; dashboard activity rows are bounded. Global search has a six-row limit per type. Map markers are capped at 1,500 households, 100 evacuation sites, 80 rescue teams, and 50 routes.
- `GET /api/v1/situation-reports` is a fixed latest-20 feed rather than a pageable list. Notification feed sources are individually capped, but saved-view history is not.

## API compatibility notes

The web archive client sends `per_page` and expects the current pagination object. Its CSV download uses a blob and needs no client change for `streamDownload`. The web map asks for all markers without viewport coordinates. Enforcing a required bounding box on the backend alone would empty or reject the current map view; coordinate this API change with the map client.
