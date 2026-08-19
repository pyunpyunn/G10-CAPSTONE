# RESQPERATION Documentation Index

This folder is the single location for maintained project documentation. Keep only the root `README.md` outside this folder.

Local-only reference folders such as `FOR DEV/` and `prototype/` are ignored from GitHub. Use them only as private prototype/reference material, not as final documentation or deployable source code.

## Main Development Docs

- `RESQPERATION_G10_STEP_BY_STEP_CHECKLIST.md` - current build checklist.
- `RESQPERATION_UPDATED_STEP_GUIDE.md` - step-by-step development guide.
- `RESQPERATION_REQUIREMENTS_BREAKDOWN.md` - module requirements.
- `RESQPERATION_ACTUAL_DEVELOPMENT_PLAN.md` - development plan.
- `RESQPERATION_SYSTEM_CLEANUP_AND_CURRENT_STATE.md` - latest cleanup notes and current implemented system status.
- `RESQPERATION_G10_BEGINNER_MODULE_PROMPTS.md` - beginner-friendly module prompts.
- `RESQPERATION_G10_DEVELOPMENT_READINESS_AUDIT.md` - readiness audit.

## Database Docs

- `RESQPERATION_G10_DB_READINESS_CHECK.md` - shared DB readiness and missing table notes.
- `RESQPERATION_G10_SAFE_SAMPLE_SEED_PLAN.md` - safe sample data plan.
- `RESQPERATION_DB_CONNECTION_SWITCH_GUIDE.md` - connect to the shared MySQL database by editing only `backend-laravel/.env`.
- `RESQPERATION_SHARED_DB_DATA_SOURCE_RULES.md` - rule that web pages must display only records from the active database connection.
- `RESQPERATION_SHARED_DB_AUDIT_AND_PUSH_TOKENS.md` - shared DB connectivity, safe seeder review, OneSignal Player ID storage, and duplicate-data checks.
- `RESQPERATION_ONESIGNAL_MOBILE_SETUP.md` - OneSignal mobile setup, Player ID flow, and authentication rules.
- `RESQPERATION_MOBILE_INSTALL_DEV_BUILD_ONESIGNAL_GUIDE.md` - complete groupmate guide for installing the mobile APK, running Expo Go/dev build, and verifying OneSignal subscriptions.
- `sql_proposals/` - review-only SQL scripts. Do not run on shared DB without DB member approval.
- `sql_proposals/2026_06_25_shared_db_read_only_audit.sql` - read-only lookup, duplicate, and device-token audit.
- `sql_proposals/initial/2026_06_12_g10_trackingaid_mappingaid_integration_draft.sql` - historical draft only; current TrackingAid handoff is documented in `RESQPERATION_TRACKINGAID_REQUEST_INTEGRATION_GUIDE.md`.
- `sql_proposals/initial/2026_06_13_mambaling_household_geotag_update.sql` - review-only Mambaling household geotag update.

## Final Defense Docs

- `RESQPERATION_FINAL_DEFENSE_STUDY_GUIDE_AND_DIAGRAMS.md` - topics to study, system concept, and Mermaid diagrams.
- `diagrams/` - detailed final defense diagram set covering system context, roles, auth, HQ workflows, mobile workflows, mapping, dispatch, resources, archive, database, and SSDLC/security.
- `diagrams/10_household_status_reporting_simulation.md` - household status reporting simulation.
- `diagrams/11_household_status_flow.md` - household status mobile flow.
- `diagrams/12_household_analytics_formula.md` - household analytics formula diagram.
- `diagrams/13_rescuer_dispatching_flow.md` - rescuer dispatching flow.
- `diagrams/14_resqperation_scope_erd.md` - RESQPERATION-only ERD with external connections.
- `diagrams/15_household_use_case.md` - household use case.
- `diagrams/16_rescuer_use_case.md` - rescuer use case.
- `diagrams/17_hq_admin_web_use_case.md` - HQ/Admin web use case.
- `diagrams/18_system_architecture.md` - system architecture.
- `diagrams/19_http_request_methods_process.md` - HTTP request method flow for household, rescuer, and HQ web.
- `diagrams/20_data_flow_diagram.md` - data flow diagram.
- `diagrams/21_radio_communication_flow.md` - radio communication flow.
- `RESQPERATION_EXTERNAL_SYSTEM_INTEGRATION_DRAFT.md` - SafeTrack, EvaTrack, TrackingAid, weather, and notification integration plan.
- `RESQPERATION_TRACKINGAID_REQUEST_INTEGRATION_GUIDE.md` - complete request intake, validation, forwarding, TrackingAid handoff table, resource inventory, and SQL verification guide.
- `RESQPERATION_RESCUER_RADIO_PTT_PLAN.md` - rescuer radio / push-to-talk plan and implementation resources.
- `RESQPERATION_RESCUER_RADIO_COMMUNICATION.md` - implemented rescuer radio voice-clip workflow and test guide.
- `RESQPERATION_SYSTEM_STACK_RESOURCES_AND_REFERENCES.md` - stack, resources, references, and legal basis.

## Scope and References

- `RESQPERATION_BARANGAY_PROFILE_SCOPE.md` - barangay profile and map/weather focus.
- `RESQPERATION_PAGASA_TENDAY_API_REQUEST_LETTER.md` - PAGASA API request draft.
- `RESQPERATION_RESOURCES.txt` - collected resources.
- `RESQPERATION_MOBILE_UI_TEXT_REVIEW.md` - mobile text reduction review.
- `RESQPERATION_UI_TEXT_AND_OPERATION_RULES_NOTE.md` - short UI labels and operational rules moved out of screens.
- `RESQPERATION_MAMBALING_GEOTAG_UPDATE_NOTE.md` - Mambaling geotag SQL proposal notes.
- `ResQperation_File_Tree.md` - generated file tree snapshot.

## Module README Backups

- `module-readmes/BACKEND_LARAVEL_README.md`
- `module-readmes/FRONTEND_WEB_README.md`

Module-local mobile references remain in `frontend-mobile/` because they are used while developing the Expo app. Maintained project-level documentation lives in `docs/`.
