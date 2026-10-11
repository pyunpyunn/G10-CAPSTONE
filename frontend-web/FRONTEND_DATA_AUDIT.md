# Frontend data audit — 2026-10-03

The `src` tree was scanned for seeded records, fixed household and area names, fabricated totals, example credentials, and fallback option lists. Operational pages read their records through `src/api`; there is no frontend household, purok, dispatch, request, weather, or archive fixture used as live data.

| Module | Operational data source | Audit result |
| --- | --- | --- |
| Dashboard | `dashboardApi` | Cards, activity, weather, and requests use the API response. |
| Broadcast | `broadcastApi` | Events, types, severities, puroks, and status choices use the workspace response. Default selected statuses now come from the response. |
| Weather | `weatherApi` | Snapshots, logs, location, source, and refresh frequency use the API. The UI no longer claims the feed is live when it has no proof. |
| Mapping | `mappingApi` | Markers, routes, bounds, and filters use the API. No local fallback status records are supplied. |
| Household status and review | `householdApi` | Household rows, status history, and counts use the API. Missing status or priority is shown as unavailable instead of being labelled safe or unchecked. |
| Dispatch | `dispatchApi` | Teams, assignments, areas, and counts use the API. Team filter choices are derived from returned team statuses. |
| Rescuer accounts | `rescuerApi` | Teams, account IDs, roles, and blood types use the API. The form no longer supplies a fictitious SAR team or a fixed temporary password. |
| Resource requests | `resourceRequestApi` | Requests and source, category, and status choices use the API. The form no longer invents these choices or a return reason. |
| Situation reports | `situationReportApi` | Reports and summary data use the API. Prepared and reviewed names are blank until supplied by a record or operator. |
| Archive | `archiveApi` | Records, filters, and saved groups use the API. Tab names and table columns are UI structure. |
| Notifications, Profile, Inquiries | Their API clients | Records and summaries use the API. Inquiry status filters follow response summary keys. Profile now shows unavailable states instead of assuming an active account. |
| Public landing | `inquiryApi` for submissions | Decorative artwork is hidden from assistive technology. Fabricated household totals, dispatch statuses, and an example contact address were removed. |

Fixed route names, API parameter names, form defaults, status enum values, labels, export columns, and navigation items remain in the frontend because they define the user interface and the existing API contract. They are **not database records**. A full move of these controlled vocabularies to backend metadata would require an API contract change; replacing them with guesses from existing records would hide valid choices when a table is empty.

Verification: `npm run build` succeeds. `npm run lint` currently fails because its recursive command enters `safe-route-locator`, which lacks `eslint-plugin-prettier`; `npx eslint src` also reports existing lint errors across the app. Neither lint command passed in this audit. No live browser session or backend integration test was run.
