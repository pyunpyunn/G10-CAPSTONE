# RESQPERATION - complete system diagrams

Open [index.html](index.html) to browse diagrams, copy Mermaid code, open a prefilled Mermaid Live editor, or download SVG. Renderer loading requires internet; source remains available without it. Mermaid source files can also be pasted directly into https://mermaid.live/edit.

## Start with the unified views

| Deliverable | Entire-system view | Context / breakdown |
| --- | --- | --- |
| Use Case | [Entire system](use-cases/00-entire-system.mmd) | 5 role/module breakdowns; 68 use cases and all 30 must-haves |
| Use Case Narrative | [Entire narrative map](narratives/00-entire-system-narrative-map.mmd) | [36 written narratives](USE_CASE_NARRATIVES.md), end-to-end sequence and 9 focused sequences |
| System Architecture | [Entire architecture](architecture/00-entire-system.mmd) | Mobile/offline; backend security/realtime; partner integration; deployment/server selection |
| ERD | [Entire deployed schema scope](erd/00-entire-system.mmd) | All columns; 9 module ERDs; source-only, full-source and local-SQLite views |
| System Design - Data Model | [Entire module interaction](data-model/00-entire-system-module-interaction.mmd) | All-table relationships; 9 module views; storage/integration boundaries |

## Scope and interpretation

This package reflects inspected source and database metadata on 28 October 2026. It does not certify that every source-defined path is deployed or that the shared database is owned exclusively by RESQPERATION. The architecture represents one Laravel monolith with logical modules, not independently deployed microservices.

The database scope is 75 verified-used existing tables out of 118 inspected; 43 existing tables are excluded. Eight source/migration-defined application tables are absent locally and are isolated/labeled rather than passed off as deployed entities. Read [DATABASE_SCOPE.md](DATABASE_SCOPE.md) before presenting the ERD.

The use-case master is a compatible Mermaid flowchart using actor boxes and oval use cases. This is a UML-style approximation. An optional native `usecase-beta` source is provided for Mermaid 12+; the viewer uses Mermaid 11.12.0 and displays the compatible version. Include/extend/generalization use explicit labels in the compatible version.

Red GAP use cases and orange partial use cases preserve the requirements without claiming finished functionality. All 30 A/B/C/D/R must-haves remain mapped in [the coverage checklist](../RESQPERATION_USE_CASE_COVERAGE.md). A required feature and a UML include dependency are different concepts; must-have labels are not automatically include arrows.

Default role associations are subject to configured modules and backend authorization. The current administrative role is Operations/Admin, including legacy Super Admin aliases. A verifier is a duty assigned to authorized HQ/rescuer accounts, not a newly invented separate login role. Team Leader alone is associated with mobile logistics submission.

The current release configuration embeds ngrok only. Tailscale/LAN fallback URLs are not configured, and saved HTTP addresses are rejected by the release policy. Architecture diagrams explicitly preserve this limitation instead of implying working three-route failover.

Known gaps/conditional paths include durable household offline status submission, full offline navigation acceptance, staff evacuation scanner/entry UI, official-ID verification, exact four-level area severity, and live partner-backed fulfillment. The older audit recorded logistics/archive regressions; this documentation task did not rerun those tests. Concurrent password-recovery edits were preserved and not modified.

## Diagram catalog

### Use Case

- [00 Entire System Native](use-cases/00-entire-system-native.mmd)
- [00 Entire System](use-cases/00-entire-system.mmd)
- [01 Accounts](use-cases/01-accounts.mmd)
- [02 Household](use-cases/02-household.mmd)
- [03 Rescue Field](use-cases/03-rescue-field.mmd)
- [04 Hq Coordination](use-cases/04-hq-coordination.mmd)
- [05 Resources Reports Integration](use-cases/05-resources-reports-integration.mmd)

### Use Case Narrative

- [00 Entire System Narrative Map](narratives/00-entire-system-narrative-map.mmd)
- [00 Entire System](narratives/00-entire-system.mmd)
- [01 Authentication And Administration](narratives/01-authentication-and-administration.mmd)
- [02 Household Safety And Trust](narratives/02-household-safety-and-trust.mmd)
- [03 Dispatch And Field Rescue](narratives/03-dispatch-and-field-rescue.mmd)
- [04 Resource Request Handoff](narratives/04-resource-request-handoff.mmd)
- [05 Situation Reports Archives And Exchange](narratives/05-situation-reports-archives-and-exchange.mmd)
- [06 Offline Page And Navigation Behavior](narratives/06-offline-page-and-navigation-behavior.mmd)
- [07 Evacuation And Qr Verification](narratives/07-evacuation-and-qr-verification.mmd)
- [08 Alert Weather And Monitoring](narratives/08-alert-weather-and-monitoring.mmd)
- [09 Public Inquiries](narratives/09-public-inquiries.mmd)

### System Architecture

- [00 Entire System](architecture/00-entire-system.mmd)
- [01 Client Mobile Offline](architecture/01-client-mobile-offline.mmd)
- [02 Backend Security And Realtime](architecture/02-backend-security-and-realtime.mmd)
- [03 Partner Integration](architecture/03-partner-integration.mmd)
- [04 Server Selection And Deployment](architecture/04-server-selection-and-deployment.mmd)

### ERD

- [00 Entire System](erd/00-entire-system.mmd)
- [01 Entire System All Columns](erd/01-entire-system-all-columns.mmd)
- [02 Identity](erd/02-identity.mmd)
- [03 Household](erd/03-household.mmd)
- [04 Disaster](erd/04-disaster.mmd)
- [05 Dispatch](erd/05-dispatch.mmd)
- [06 Field](erd/06-field.mmd)
- [07 Navigation](erd/07-navigation.mmd)
- [08 Resources](erd/08-resources.mmd)
- [09 Documentation](erd/09-documentation.mmd)
- [10 Platform](erd/10-platform.mmd)
- [11 Source Defined Deployment Gaps](erd/11-source-defined-deployment-gaps.mmd)
- [12 Entire System Source And Deployed Scope](erd/12-entire-system-source-and-deployed-scope.mmd)
- [13 Mobile Navigation Local Storage](erd/13-mobile-navigation-local-storage.mmd)

### System Design - Data Model

- [00 Entire System Module Interaction](data-model/00-entire-system-module-interaction.mmd)
- [01 Entire System Table Relationships](data-model/01-entire-system-table-relationships.mmd)
- [02 Identity](data-model/02-identity.mmd)
- [03 Household](data-model/03-household.mmd)
- [04 Disaster](data-model/04-disaster.mmd)
- [05 Dispatch](data-model/05-dispatch.mmd)
- [06 Field](data-model/06-field.mmd)
- [07 Navigation](data-model/07-navigation.mmd)
- [08 Resources](data-model/08-resources.mmd)
- [09 Documentation](data-model/09-documentation.mmd)
- [10 Platform](data-model/10-platform.mmd)
- [11 Storage And Integration Boundaries](data-model/11-storage-and-integration-boundaries.mmd)
- [12 Entire Source And Storage Scope](data-model/12-entire-source-and-storage-scope.mmd)

## Evidence and validation

- [Table inclusion/exclusion inventory](TABLE_SCOPE.csv)
- [Column metadata](SCHEMA_COLUMNS.json)
- [Relationship provenance](RELATIONSHIP_EVIDENCE.json)
- [Schema/code relationship conflicts](SCHEMA_REFERENCE_CONFLICTS.json)
- [Metadata summary](BUILD_SUMMARY.json)
- [Canonical application use cases](../USE_CASES.md)
- [Must-have implementation audit](../MUST_HAVES_IMPLEMENTATION_AUDIT.md)

Only documentation/diagram artifacts were created. No application code, database schema, business records, secrets or concurrent changes were modified. Validation results are recorded in VALIDATION.json after parser/render checks. No commit or push was performed.

## Verification completed

49 compatible Mermaid diagrams passed parsing with Mermaid 11.12.0 in Chrome. Seven full-system diagrams were rendered and exported under `exports/`. The optional native Mermaid 12+ use-case source was not parsed by the version-11 renderer. The viewer initial render, category navigation, `.mmd` download and prefilled-link compression round-trip passed. No application tests were needed or run for this documentation-only task.
