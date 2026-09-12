# RESQPERATION Diagram Set

This folder contains detailed Mermaid diagrams for final defense, technical documentation, and workflow review.

Use these files with any Mermaid-compatible viewer:

- GitHub markdown preview
- VS Code Mermaid preview extension
- Mermaid Live Editor
- Documentation tools that support fenced `mermaid` blocks

## Diagram Files

1. `01_system_context_and_external_integrations.md`
   - Overall system context
   - External systems and APIs
   - Development/deployment view

2. `02_roles_use_cases_and_auth.md`
   - Role-based use cases
   - Authentication and role routing
   - Permission boundaries

3. `03_hq_web_workflows.md`
   - HQ/Admin web module map
   - Dashboard and disaster command workflow
   - Web navigation and API usage

4. `04_disaster_status_dispatch_mapping.md`
   - Disaster lifecycle
   - Household status flow
   - Rescue dispatch and map/routing flow

5. `05_mobile_workflows_household_rescuer_radio.md`
   - Household mobile first-time setup
   - Household disaster status workflow
   - Rescuer mobile assignment workflow
   - Rescuer radio communication flow

6. `06_resource_requests_sitrep_archive_notifications.md`
   - Resource request validation and handoff
   - Situation reporting
   - Archiving and saved groups
   - Notification flow

7. `07_data_model_and_database_scope.md`
   - Database scope
   - ERD-style data relationships
   - Shared DB connection and data source rule

8. `08_ssdlc_security_privacy.md`
   - SSDLC process
   - Security architecture
   - Data privacy flow
   - Threat model summary

9. `10_household_status_reporting_simulation.md`
   - End-to-end household status reporting simulation
   - Active disaster and no-disaster branches

10. `11_household_status_flow.md`
    - Household login, setup, geotagging, and status update flow

11. `12_household_analytics_formula.md`
    - Household analytics formulas and status counting logic

12. `13_rescuer_dispatching_flow.md`
    - HQ dispatch assignment, responder acceptance, routing, and completion

13. `14_resqperation_scope_erd.md`
    - RESQPERATION ERD scope with external system connections only

14. `15_household_use_case.md`
    - Household mobile use case diagram

15. `16_rescuer_use_case.md`
    - Rescuer mobile use case diagram

16. `17_hq_admin_web_use_case.md`
    - HQ/Admin web use case diagram

17. `18_system_architecture.md`
    - Web, mobile, API, database, storage, and external systems architecture

18. `19_http_request_methods_process.md`
    - HTTP method flows for household mobile, rescuer mobile, and HQ web

19. `20_data_flow_diagram.md`
    - Detailed system data flow diagram

20. `21_radio_communication_flow.md`
    - Rescuer radio communication and archive flow

## Maintenance Rules

- Keep diagrams aligned with `backend-laravel/routes/api.php`.
- Keep data diagrams aligned with the active shared database schema.
- Do not document prototype-only behavior as final system behavior.
- If a feature is still pending external integration, label it as pending instead of showing it as fully complete.
- Keep root `README.md` short; detailed diagrams stay here.
