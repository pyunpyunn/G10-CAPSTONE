# Report implementation validation

## Current six-finding implementation — 19 October 2026

- Focused backend checks: **81 tests passed, 594 assertions** (Situation, ReportExchange, ExternalReports, EvaTrackReports and SafeTrackReports filters).
- Frontend production build and targeted ESLint passed.
- Git diff whitespace check passed; Git reported line-ending conversion notices only.
- Regenerated synthetic PDF: **3 pages**, exactly sections I–VII, with no additional report sections or annexes.
- Displaced Population and inside/outside/total displacement tables are excluded in all formats; retired partner key III is rejected. Damage uses actual recorded assessment categories.
- Automatic source-sync coverage includes failed-source export blocking, expired-preflight refresh, safe CSV downloads, authentication and immutable saved-snapshot exports.
- Real upstream compatibility has not been verified against live partner deployments. Configured source credentials and compatible report CSVs are required.
- Application migrations and deployment have not been run.

## Earlier full-suite baseline

Full backend suite (18 October 2026), PHP memory_limit=512M:

```json
{
  "tool": "phpunit",
  "result": "failed",
  "tests": 342,
  "passed": 329,
  "assertions": 1890,
  "duration_ms": 27448,
  "failed": 10,
  "errors": 3
}
```

Remaining failures/errors:

- Tests\Feature\AuthenticatedArchiveContractTest::test_admin_receives_saved_archive_groups_with_the_existing_json_shape: Expected response status code [200] but received 403.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_dashboard_summary_keeps_authenticated_json_shape: Expected response status code [200] but received 500.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_dashboard_full_workspace_keeps_authenticated_json_shape: Expected response status code [200] but received 500.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_profile_workspace_keeps_authenticated_json_shape: Failed asserting that two strings are identical.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_broadcast_save_attempts_push_immediately_and_queues_delivery_fallback: Expected response status code [201] but received 500.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_dashboard_clears_ended_event_references_without_loading_event_ids: Expected response status code [200] but received 500.
- Tests\Feature\InquiryListQueryTest::test_admin_status_update_keeps_inquiry_response_shape: Expected response status code [200] but received 403.
- Tests\Feature\NotificationApiTest::test_private_channels_require_auth_and_reject_another_users_channel: Expected response status code [403] but received 200.
- Tests\Feature\RescuerMobileResourceRequestWorkflowTest::test_store_keeps_success_contract_and_persists_the_composed_location: Failed asserting that a row in the table [resource_requests] matches the attributes {
- Tests\Feature\ResponseOperationsViewsTest::test_admin_field_reports_endpoint_exposes_real_pagination_metadata: Expected response status code [200] but received 403.
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_purok_filters_follow_active_household_addresses_and_catalog_fallback: SQLSTATE[HY000]: General error: 1 no such table: sitios (Connection: sqlite, Database: :memory:, SQL: select DISTINCT COALESCE(NULLIF(a.purok_sitio, ''), NULLIF(p.purok_name, '')) 
- Tests\Feature\AuthenticatedWorkspaceContractTest::test_mapping_member_status_fallback_uses_one_query_for_multiple_households: SQLSTATE[HY000]: General error: 1 no such table: sitios (Connection: sqlite, Database: :memory:, SQL: select "gl"."location_id", "gl"."household_id", "gl"."latitude", "gl"."longitu
- Tests\Feature\ResponseOperationsViewsTest::test_household_details_use_real_phone_device_and_approved_trusted_contact_channels: SQLSTATE[HY000]: General error: 1 no such table: addresses (Connection: sqlite, Database: :memory:, SQL: select "h"."household_id", NULL as household_name, NULL as household_code, 

At that earlier checkpoint, all 53 report-focused tests passed within the full run. The earlier twelve-section sample and its manual acknowledgment have been superseded by the current six-finding report described above.
