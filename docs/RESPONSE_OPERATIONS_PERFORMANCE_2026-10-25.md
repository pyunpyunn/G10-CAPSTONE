# Response Operations performance review — 25 October 2026

The work began on 24 October; verification continued after midnight on 25 October, Asia/Manila.

## Findings and changes

The dispatch page originally waited for `/dispatches?per_page=5` and `/dispatches/sitio-priorities`. The first endpoint fetched a full operational workspace, including household detail lists, team cards, personnel, historical dispatch activity and coverage data. Most of this was unnecessary for the initial visible page. A three-second Axios-level Redux cache also expired quickly on navigation, and broadly invalidating every cache for operational updates defeated reuse.

The three Response Operations pages now use RTK Query subscriptions in the main Redux store. Identical subscriptions share a request; cached results survive navigation for sixty seconds after the final subscription ends. A return visit older than fifteen seconds refreshes in the background while keeping the existing data visible. Dispatch, household, disaster, account and field-report tags invalidate relevant results after changes. Browser focus and reconnect use RTK Query's standard listeners; the underlying Axios cache is cleared separately to prevent two refresh mechanisms from generating duplicate fetches.

RTK Query owns the response cache for these pages. Their Axios requests explicitly bypass the older transport TTL cache, avoiding two competing freshness policies. Authentication changes clear both Redux API stores. The cache keys include the selected view, filters and pagination. Manual query cancellation is passed to Axios. Other web/mobile API modules retain their existing Redux transport; local form inputs and durable mobile offline storage keep their appropriate responsibilities.

Backend read views now match each page:

- `view=overview`: summary plus ranked sitios and compact active-assignment information, in one request.
- `view=management`: only a paginated assignment list and active-event information. It does not read or return household queue JSON for table rows.
- `view=coverage`: team coverage and personnel, fetched when the user opens Team Coverage.
- The field-reports page requests `include_summary=0`, skipping eight unused status-summary queries while preserving its dispatch-status filters and pagination.
- The compact recordings feed returns team labels and personnel represented on the current page, instead of fetching the full roster and team deployment workspace every five seconds. Existing visible recordings remain during background refresh.

The original full workspace endpoint remains available for existing consumers that need it. These read changes do not change dispatch acceptance/completion rules, assignment ownership or member-status calculations.

## SQL and index changes

Sitio household status counts are now grouped in SQL before being materialized in PHP. The application receives area/status counts instead of one status object per household. Existing ranking/status-count regression tests continue to pass.

Migration `2026_10_24_000004_add_dispatch_lookup_indexes.php` was applied to the operational MySQL database. Missing lookup indexes were verified before adding indexes for:

- `member_vulnerable_groups.member_id`, used by the correlated vulnerability checks;
- responder account and responder ID lookup;
- team availability filtering;
- sitio/barangay and purok/sitio relationships;
- active disaster-event filtering and ordering.

These complement the previously applied household report, GPS, route, dispatch-status and notification indexes. Existing equivalent indexes are retained. The new overview avoids the full risk-area household list and full household coverage scan. Explicit sitio assignments are grouped without querying household membership; older assignments use only their referenced household IDs. Tests cover both formats and compact payloads.

## Security review

The API header guard remains enabled. Fifty isolated invocations of the actual guard, including the existing file-cache perimeter limiter, measured a median of 9.083 ms and zero SQL queries. Domain/origin checks are request security controls; they cannot accelerate expensive SQL or establish identity by themselves. Authentication, role checks, assignment ownership, integration signatures and rate limits remain active.

Invalidation topics were corrected: GPS logs and route geometry changes notify mapping. Assignment status changes still notify dispatch, field reports and mapping. A location update therefore keeps maps live without repeatedly recalculating unrelated priority/dispatch lists.

## Measurements

Measurements use the actual local application and database, not a fabricated 30,000-household population. They are not a remote-device or production-load guarantee.

| Check | Earlier path | Updated path |
| --- | ---: | ---: |
| Initial dispatch data requests | 2 | 1 |
| Populated dispatch response bytes | 57,555 across both responses | 15,514 |
| Full workspace SQL statements | 28, excluding the second priorities request | 18 including priorities |
| Authenticated API median, three populated-state samples | Workspace 143.7 ms; separate priorities 124.1 ms | Combined overview 110.3 ms |

Do not add the earlier endpoint medians to estimate browser wall time: the page requested them concurrently. Response-byte totals and request counts are directly comparable.

Actual Chrome checks against `http://100.121.155.55:5175/dispatch`:

- Populated-state first load: 1,191 ms; return visit: 83 ms; no return-visit API requests; no JavaScript errors.
- The live active event subsequently became closed. It was not reopened for testing. Final empty-state first load: 1,108 ms; return visit: 132 ms; no return-visit API requests; no JavaScript errors.
- Team Coverage made no initial request and loaded successfully when opened.
- The field-report row continued to show the saved task status **Completed**.
- The final compact recordings response was 743 bytes; the final field-report response was 1,222 bytes. These samples were taken without an active event and should not be compared directly with populated-state timings.

The first-load measurements include authentication, development-server module loading and page rendering. The return-visit measurements demonstrate actual Redux cache reuse in the browser, not only a mocked request test.

## Verification

- 70 targeted backend tests passed, with 343 assertions.
- All 32 web tests passed, including real RTK Query subscription sharing, cached navigation, filter separation, invalidation, visible data during refresh and logout clearing.
- Web production build and targeted ESLint passed.
- Legacy assignment matching, rescue priorities, household completion, API headers and field-report resources were included in regression checks.
- Temporary browser test sessions were revoked. Temporary profiling scripts were removed. No household/dispatch/event records were created or reopened for these measurements.
- The broader backend suite's previously documented older fixture/contract failures were not represented as fixed or as a clean full-suite result.

Evidence is stored under `backend-laravel/docs/verification/dispatch-performance-2026-10-24/`. The folder retains the date the measurement run began.

References used to review the implementation: [RTK Query cache behavior](https://redux-toolkit.js.org/rtk-query/usage/cache-behavior), [MySQL composite indexes](https://dev.mysql.com/doc/refman/8.4/en/multiple-column-indexes.html), and [browser CORS behavior](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CORS).
