<p align="center">
<strong>UNIVERSITY OF SAN JOSE–RECOLETOS</strong><br>
School of Computer Studies<br>
Cebu City, Philippines
</p>

# RESQPERATION

## A Geo-Tagged Household Monitoring and Barangay Disaster Risk Reduction and Management Rescue Coordination System

### Software Requirements Specification

**Version 1.2**
**October 22, 2026**

<p align="center">
A Capstone Project Presented to the Faculty of the<br>
School of Computer Studies<br>
University of San Jose–Recoletos<br><br>
In Partial Fulfillment of the Requirements for<br>
Associate in Computer Technology, Major in Software Development
</p>

**Presented by**

- Niña Kathleen B. Barro
- Vinzon Arellano
- Erece Joy Eria
- Vince Pacillan

**Capstone Adviser:** Mr. Roderick A. Bandalan

---

## Revision History

| Version | Date | Description | Author(s) |
| --- | --- | --- | --- |
| 1.0 | October 22, 2026 | Initial SRS baseline documenting the project scope, operating context, and requirements identified in the RESQPERATION project sources. | All proponents |
| 1.1 | October 22, 2026 | Recast the specification as an academic capstone SRS, expanding the project context, requirements, use cases, and verification evidence. | All proponents |
| 1.2 | October 23, 2026 | Reorganized the specification to follow the supplied PIS-SRS-v1.0 section order and three-appendix structure; reconciled citations and aligned requirements with project evidence. | All proponents |

## Table of Contents

1. [Introduction](#1-introduction)
   1. [Purpose](#11-purpose)
   2. [Scope](#12-scope)
   3. [Definitions, Acronyms, and Abbreviations](#13-definitions-acronyms-and-abbreviations)
   4. [References](#14-references)
   5. [Overview](#15-overview)
2. [Overall Description](#2-overall-description)
   1. [Product Perspective](#21-product-perspective)
   2. [Product Functions](#22-product-functions)
   3. [User Classes and Characteristics](#23-user-classes-and-characteristics)
   4. [Operating Environment](#24-operating-environment)
   5. [Design and Implementation Constraints](#25-design-and-implementation-constraints)
   6. [Assumptions and Dependencies](#26-assumptions-and-dependencies)
   7. [Apportioning of Requirements](#27-apportioning-of-requirements)
3. [Specific Requirements](#3-specific-requirements)
   1. [External Interface Requirements](#31-external-interface-requirements)
      1. [User Interfaces](#311-user-interfaces)
      2. [Hardware Interfaces](#312-hardware-interfaces)
      3. [Software Interfaces](#313-software-interfaces)
      4. [Communications Interfaces](#314-communications-interfaces)
   2. [Functional Requirements](#32-functional-requirements)
      1. [Authentication and User Management (AUTH)](#321-authentication-and-user-management-auth)
      2. [Household Records and Mobile Reporting (HOU)](#322-household-records-and-mobile-reporting-hou)
      3. [Rescuer Operations (RES)](#323-rescuer-operations-res)
      4. [HQ Operations, Disaster Management, and Mapping (OPS)](#324-hq-operations-disaster-management-and-mapping-ops)
      5. [Resource Requests and Partner Exchange (REQ)](#325-resource-requests-and-partner-exchange-req)
      6. [Situation Reports, Exports, and Archives (REP)](#326-situation-reports-exports-and-archives-rep)
      7. [Notifications and Communications (NOT)](#327-notifications-and-communications-not)
      8. [Audit, Administration, and System Health (ADM)](#328-audit-administration-and-system-health-adm)
   3. [Non-Functional Requirements](#33-non-functional-requirements)
      1. [Performance](#331-performance)
      2. [Security](#332-security)
      3. [Reliability and Availability](#333-reliability-and-availability)
      4. [Usability](#334-usability)
      5. [Maintainability](#335-maintainability)
      6. [Portability](#336-portability)
      7. [Data Privacy and Compliance](#337-data-privacy-and-compliance)
   4. [Database Requirements](#34-database-requirements)
4. [Appendix A. Preliminary Entity-Relationship Overview](#appendix-a-preliminary-entity-relationship-overview)
5. [Appendix B. Requirements Traceability Summary](#appendix-b-requirements-traceability-summary)
6. [Appendix C. Approval](#appendix-c-approval)

---

# 1. Introduction

## 1.1 Purpose

This Software Requirements Specification establishes the agreed subject, system boundary, actors, interfaces, functional requirements, quality requirements, data responsibilities, system flows, and acceptance conditions for RESQPERATION. Its primary audience is the capstone panel, adviser, project proponents, and any authorized stakeholder asked to review or validate the proposed system.

The SRS is intended to be used as (a) an academic description of the project’s requirements and design boundary, (b) a baseline for implementation and verification, and (c) a record of known implementation gaps requiring review. Normative statements use “shall.” The implementation-status evidence in Appendix B is informative and must not be interpreted as a substitute for test evidence or stakeholder acceptance.

## 1.2 Scope

Disaster response at the barangay level requires local officials and responders to receive, review, and act on information about affected households, responder assignments, evacuation activity, and resource needs. These activities may involve multiple user groups and data sources. RESQPERATION is proposed as a shared digital workspace that supports the recording and coordination of those workflows; it does not replace the decision-making authority of the barangay, city, or national disaster-risk-reduction and management offices.

The initial project setting is Barangay Mambaling in Cebu City. PhilAtlas reports a 2020 Census population of 30,459 and reports 7,583 households from the 2015 Census, with the underlying census figures attributed to the Philippine Statistics Authority [10]. The figures refer to different census years and are contextual references only; they are not current system records, a present-day population estimate, or a substitute for an authoritative barangay registry. Geographic susceptibility information included in the project is likewise a sourced planning layer, not a live sensor reading or a household-level risk determination [7].

The policy context includes the Philippine Disaster Risk Reduction and Management Act of 2010, which establishes national and local disaster-risk-reduction and management responsibilities [9]. RESQPERATION is designed to support coordination and information management within that broader institutional context. It is not an official warning issuer, dispatch authority, or emergency-service provider. Its privacy requirements are informed by the Data Privacy Act of 2012 [11]; reference to the law does not certify that the project or any deployment is legally compliant.

The project’s technical basis is documented in its system architecture, use cases, integration notes, and repository evidence [1–8]. The system aligns conceptually with Sustainable Development Goal 11 (sustainable cities and communities) and Goal 13 (climate action); the alignment is a project rationale, not an endorsement or verified social-impact outcome [12].

The project addresses the requirements-design problem of coordinating disaster-related records and actions across household residents, field responders, and barangay headquarters using separate client workflows and data sources. Without a shared operational workflow, information submitted by one role may be difficult for another authorized role to locate, interpret, validate, or follow through.

Specifically, the system is intended to address the following information-management and coordination needs:

1. Household safety updates need to be associated with the correct household, member, event, source, and submission time so that authorized personnel can review the reported status.
2. Rescue assignments and field updates need a traceable link between the event, responder/team, target, assignment state, and subsequent report.
3. Requests for resources or personnel need a controlled intake and review process that distinguishes internal approval from an external handoff or actual fulfillment.
4. Weather, map, hazard, and advisory information needs clear source and freshness context so users do not confuse decision-support data with official local direction.
5. Situation reports and archives need to represent recorded information and missing data faithfully, while restricting personal and operational information to authorized users.

RESQPERATION’s requirements therefore focus on controlled data access, auditable workflows, explicit system/provider outcomes, and human review. The project does not claim—without a separate evaluation—that deploying the software will reduce casualties, guarantee rescue, improve response time, or independently resolve the operational challenges described above.

**General objective.** To specify and develop a role-based, multi-client barangay disaster-response coordination system for the initial project setting of Mambaling, Cebu City, with traceable workflows for household status reporting, responder operations, HQ coordination, resource requests, and incident documentation.

**Specific objectives.** The system shall:

1. provide authenticated and role-authorized web and mobile workspaces for HQ/Admin personnel, household residents, and rescuers;
2. allow household users to view linked household information and submit event-related safety status for authorized household members;
3. allow HQ users to create and manage disaster events and broadcasts, review incoming status information, and coordinate responder assignments;
4. allow rescuers to review authorized assignments and submit supported progress, location, check-in, field-report, and resource-request information;
5. provide controlled request review and distinguish RESQPERATION approval and handoff from partner-system processing and fulfillment;
6. present map, weather, advisory, and hazard context with appropriate source attribution and limitations;
7. support the preparation, controlled exchange, retrieval, and archiving of situation reports and related operational records; and
8. define verifiable security, privacy, reliability, usability, data-integrity, and acceptance requirements for the stated scope.

These objectives describe intended system capabilities. They are not claims that every capability has passed acceptance testing or that the system has demonstrated a measurable disaster-response impact.

RESQPERATION is a barangay disaster-response and emergency-operations coordination system initially scoped to Mambaling, Cebu City. Its project boundary comprises:

- **HQ/Admin web client:** event and broadcast administration, household-status review, dispatch, operational map and weather context, request validation, situation reporting, notifications, and archive workflows, subject to the user’s assigned permissions.
- **Household mobile workflows:** authentication, linked household/member views, household setup information, event-status submission and history, trusted-household features, and supported evacuation information.
- **Rescuer mobile workflows:** authentication, assignment access, supported location and progress updates, field check-ins and reports, resource requests, and internet-dependent team communication.
- **Application services:** a Laravel JSON API responsible for authentication, authorization, validation, business rules, and access to the active configured database.
- **External interfaces:** specifically configured household-data sharing, partner request/report exchange, weather/advisory sources, maps/routing, push notifications, and optional SMS services.

The system supports information management, coordination, and recordkeeping. It does not independently make emergency decisions, certify the accuracy of source data, or guarantee a response. The following are excluded from the current requirements baseline or remain unverified:

- ownership or replacement of SafeTrack household identity/census records, EvaTrack evacuation operations, or TrackingAid inventory and fulfillment;
- an official government warning function, authoritative hazard prediction, automatic optimal dispatch, or certified life-safety operation;
- guaranteed message delivery, route safety, uninterrupted availability, response time, rescue, or resource fulfillment;
- end-to-end household reporting and navigation during loss of connectivity beyond specifically tested capabilities;
- a complete dedicated evacuation-QR scanning/operator interface;
- automatic city or national agency receipt and acknowledgement unless a separate, verified integration is established;
- production deployment service-level commitments, formal operational-readiness certification, and legal/privacy compliance certification; and
- system-wide population statistics beyond the date-identified sources and authorized operational records.

The project is initially bounded to one barangay profile and the roles/workflows identified in this document. Expansion to other barangays, jurisdictions, mobile operating systems, or partner systems requires configuration, privacy and authority review, interface validation, and acceptance testing. External-provider access, partner credentials, network availability, and third-party service terms remain dependencies outside the project’s control.

## 1.3 Definitions, Acronyms, and Abbreviations

| Term | Definition |
| --- | --- |
| API | Application Programming Interface used by web/mobile clients and approved partner systems. |
| Barangay | The local government unit targeted by this system; the initial project area is Mambaling, Cebu City. |
| BDRRM | Barangay Disaster Risk Reduction and Management. |
| BDRRMC | Barangay Disaster Risk Reduction and Management Committee. |
| CAP | Common Alerting Protocol, a structured format used by public alert feeds. |
| CRUD | Create, Read, Update, and Delete data operations. |
| ERD | Entity-Relationship Diagram showing data entities and their relationships. |
| GeoTag | Geographic coordinates associated with a household, responder, site, or operational record. |
| HQ/Admin | Authorized barangay headquarters personnel using the web operations workspace. |
| MySQL | Relational database management system used by the configured operational database. |
| ODbL | Open Database License, applicable to OpenStreetMap database data. |
| PII | Personally identifiable information. |
| PTT | Push-to-talk style communication. The documented project feature is internet-dependent and is not a hardware radio. |
| RBAC | Role-Based Access Control; permissions are associated with user roles and module/action policy. |
| RESQPERATION | The Geo-Tagged Household Monitoring and Barangay Disaster Risk Reduction and Management Rescue Coordination System specified in this document. |
| SRS | Software Requirements Specification. |
| SitRep | Situation Report, a structured summary of incident conditions and response. |
| SMS | Short Message Service. |
| UAT | User Acceptance Testing. |
| UI | User Interface. |
| Use case | A description of an actor's goal and the system interactions that accomplish or reject it. |
| VAPID | Voluntary Application Server Identification, used to identify an application server for browser push. |

## 1.4 References

### Project documents and evidence

1. RESQPERATION project team. (2026). [Project README](../README.md) [Project document].
2. RESQPERATION project team. (2026, October 20). [System use cases](USE_CASES.md) [Project document].
3. RESQPERATION project team. (2026, October 20). [Must-Haves implementation audit](MUST_HAVES_IMPLEMENTATION_AUDIT.md) [Project audit].
4. RESQPERATION project team. (2026). System architecture and file stack [Project document].
5. RESQPERATION project team. (2026). development checklist [Project planning document; entries are dated and may not reflect later work].
6. RESQPERATION project team. (2026). documentation index and diagram catalogue [Project documents].
7. RESQPERATION project team. (2026). [Mambaling hazard source notes](MAMBALING_HAZARD_SOURCES.md) [Project source notes].
8. RESQPERATION project team. (2026). [Offline navigation notes](OFFLINE_NAVIGATION.md) and [progress record](OFFLINE_NAVIGATION_PROGRESS.md) [Project documents].

### Standards, laws, and external technical sources

9. Republic of the Philippines. (2010). *Republic Act No. 10121: Philippine Disaster Risk Reduction and Management Act of 2010*. [Official Senate legislative reference](https://issuances-library.senate.gov.ph/legislative%2Bissuances/Republic%20Act%20No.%2010121).
10. PhilAtlas. (n.d.). [Mambaling, Cebu City](https://www.philatlas.com/visayas/r07/cebu-city/mambaling.html). Census statistics reported from the Philippine Statistics Authority; population and household figures in this SRS retain their respective census years. Retrieved October 22, 2026.
11. Republic of the Philippines. (2012). *Republic Act No. 10173: Data Privacy Act of 2012*. [National Privacy Commission reference](https://privacy.gov.ph/data-privacy-act/). Cited as a design and governance reference, not as evidence of compliance.
12. United Nations. (n.d.). [Sustainable Development Goals: Goals 11 and 13](https://sdgs.un.org/goals). Retrieved October 22, 2026.
13. ISO/IEC/IEEE. (2018). *ISO/IEC/IEEE 29148:2018: Systems and software engineering — Life cycle processes — Requirements engineering*.
14. IEEE. (1998). *IEEE Std 830-1998: Recommended Practice for Software Requirements Specifications*. Legacy structural reference.
15. OWASP Foundation. (n.d.). [Application Security Verification Standard](https://owasp.org/www-project-application-security-verification-standard/) and [OWASP Top 10](https://owasp.org/www-project-top-ten/). Retrieved October 22, 2026.
16. Laravel. (n.d.). [Documentation](https://laravel.com/docs); Meta. (n.d.). [React documentation](https://react.dev/); Expo. (n.d.). [Documentation](https://docs.expo.dev/); Oracle. (n.d.). [MySQL reference manual](https://dev.mysql.com/doc/). Retrieved October 22, 2026.
17. Open-Meteo. (n.d.). [API documentation](https://open-meteo.com/en/docs), [licence](https://open-meteo.com/en/licence), and [terms](https://open-meteo.com/en/terms). Retrieved October 22, 2026.
18. Department of Science and Technology–Philippine Atmospheric, Geophysical and Astronomical Services Administration. (n.d.). [Public alert feeds](https://publicalert.pagasa.dost.gov.ph/feeds/) and [products and services](https://pagasa.dost.gov.ph/products-and-services). Retrieved October 22, 2026.
19. OpenStreetMap Foundation. (n.d.). [Copyright and ODbL attribution](https://www.openstreetmap.org/copyright) and [tile usage policy](https://operations.osmfoundation.org/policies/tiles/). Retrieved October 22, 2026.

Project documents [1–8] describe the project-specific requirements and evidence. External standards, laws, and service documentation [9–19] provide contextual or technical references. The project’s broader review of related literature and studies belongs in the capstone manuscript’s literature-review chapter and is not replaced by this engineering specification.

## 1.5 Overview

Section 2 provides the overall description of the product: its context, major functions, user classes, operating environment, constraints, assumptions, and allocation of requirements. Section 3 enumerates the specific requirements: external interfaces, functional requirements organized by subsystem, non-functional requirements, and database requirements. The appendices contain the preliminary entity-relationship overview, requirements traceability summary, and approval. The use-case model and system architecture are included in Section 2; use-case narratives and user-interface screenshot placeholders are included in Section 3.

# 2. Overall Description

## 2.1 Product Perspective

RESQPERATION is a multi-client system that coordinates work across authorized HQ/Admin users and mobile household/rescuer users. The web and mobile clients call a Laravel JSON API; they must not connect directly to the operational database. Laravel applies authentication, validation, role/module permission checks, and workflow rules before reading or writing records.

The system uses an active MySQL connection selected through backend environment configuration. Household identity/member information may be shared with SafeTrack. RESQPERATION records its operational workflows and may communicate with EvaTrack and TrackingAid using configured API or database handoff mechanisms. External systems remain separate systems of record for their own domains.

**Figure 1. RESQPERATION logical system architecture**

_[Insert the exported architecture diagram here for the panel/submission copy. The diagram should distinguish client applications, backend services, owned/shared data stores, and external providers. The Mermaid block is the editable source representation.]_

```text
HQ/Admin browser ──┐
                   ├── HTTPS/JSON ── Laravel API ── Active MySQL database
Household mobile ──┤                       ├────── Weather/advisory sources
Rescuer mobile ────┘                       ├────── Push/SMS services
                                           ├────── Map/routing services
                                           └────── Partner systems
```

```mermaid
flowchart TB
  subgraph ClientApplications["Client applications"]
    WEB["HQ/Admin web\nReact 19 + Vite"]
    HOUSE["Household mobile\nExpo / React Native"]
    RESCUER["Rescuer mobile\nExpo / React Native"]
  end
  subgraph Backend["Backend application"]
    API["Laravel JSON API"]
    AUTH["Sanctum authentication\nrole/module authorization"]
    VALIDATION["Request validation"]
    WORKFLOWS["Controllers and service workflows"]
    QUEUE["Queue workers / scheduler"]
  end
  subgraph Data["Configured data services"]
    MYSQL[("Active MySQL schema")]
    TRACKDB[("Separate TrackingAid connection\nwhen configured")]
    FILES["Configured file/object storage"]
    CACHE["Configured cache"]
  end
  subgraph Providers["External sources and providers"]
    SAFE["SafeTrack"]
    EVA["EvaTrack"]
    TA["TrackingAid"]
    METEO["Open-Meteo"]
    GOV["PAGASA / PHIVOLCS"]
    OSM["OpenStreetMap / map tiles"]
    OSRM["OSRM route service"]
    PUSH["OneSignal / platform push"]
    WEBPUSH["Browser Push / VAPID"]
    SMS["Optional Android SMS gateway"]
  end
  WEB -->|"HTTPS / JSON"| API
  HOUSE -->|"HTTPS / JSON"| API
  RESCUER -->|"HTTPS / JSON"| API
  API --> AUTH --> VALIDATION --> WORKFLOWS
  WORKFLOWS --> MYSQL
  WORKFLOWS --> TRACKDB
  WORKFLOWS --> FILES
  WORKFLOWS --> CACHE
  WORKFLOWS --> QUEUE
  QUEUE --> MYSQL
  QUEUE --> PUSH
  QUEUE --> WEBPUSH
  QUEUE --> EVA
  QUEUE --> TA
  API --> SAFE
  API --> EVA
  API --> METEO
  API --> GOV
  API --> SMS
  WEB --> OSM
  WEB --> OSRM
  HOUSE --> OSM
  RESCUER --> OSM
```

Architecture constraints: clients use the API rather than database connections; external integrations remain separately configured; authorization is enforced in the backend; provider outages must be visible; and local tests do not prove deployment or partner acceptance.

The system's main operating boundary includes:

- RESQPERATION web, mobile, backend, and owned operational workflows.
- Shared database records that the project is explicitly authorized to read or update.
- External services that supply data, deliver messages, calculate routes, or exchange records.

It does not claim ownership of partner databases, government source data, map data, or provider availability.

## 2.2 Product Functions

At a summary level, RESQPERATION shall provide:

1. Account authentication, role-aware routing, authorization, session/token handling, and profile maintenance.
2. HQ/Admin event dashboards, event lifecycle actions, broadcasts, and household/responder monitoring.
3. Household first-run setup, linked household/member views, status submission, history, trusted-household functions, and supported evacuation features.
4. Rescuer assignment access, navigation, operational status/location updates, field reports, requests, and internet-based radio communication.
5. Map views showing available households, sites, responders, routes, and sourced hazard overlays.
6. Weather snapshots and official advisory references, with provider/source labels.
7. Request intake and HQ review, validation, return, forwarding, and tracking.
8. Situation report generation, partner report exchange, exports, and incident archives.
9. Mobile and browser notification registration and delivery when providers, permissions, and connectivity allow.
10. Auditability, validation, bounded data access, and explicit error handling for supported operations.

**Figure 2. RESQPERATION use-case model and system boundary**

_[Insert the exported UML use-case diagram here for the panel/submission copy. The figure should show the system boundary, human actors, external systems, and principal use cases. The Mermaid block is the editable source representation.]_

```mermaid
flowchart LR
  H[Household resident]
  R[Rescuer]
  HQ[HQ personnel / dispatcher]
  LEAD[HQ leader / approver]
  SA[Super administrator]
  ST[SafeTrack]
  EV[EvaTrack]
  TA[TrackingAid]
  WX[Weather / advisory sources]
  PS[Push / SMS providers]
  subgraph SYS[RESQPERATION]
    AUTH[Authenticate and manage access]
    HOU[Set up household and report status]
    ALERT[Review alerts and notifications]
    EVAC[View evacuation QR / routes]
    ASSIGN[Review and update assignments]
    FIELD[Submit check-in and field reports]
    RADIO[Use internet PTT communication]
    DASH[Monitor dashboard, map and weather]
    EVENT[Manage event and broadcasts]
    DISPATCH[Create and monitor dispatch]
    REVIEW[Review household reports]
    REQUEST[Submit and review resource requests]
    REPORT[Prepare and export situation reports]
    ARCHIVE[Search and export archives]
    ADMIN[Manage roles, teams and inquiries]
    EXCHANGE[Exchange approved partner data]
  end
  H --> AUTH
  H --> HOU
  H --> ALERT
  H --> EVAC
  R --> AUTH
  R --> ASSIGN
  R --> FIELD
  R --> REQUEST
  R --> RADIO
  HQ --> AUTH
  HQ --> DASH
  HQ --> EVENT
  HQ --> DISPATCH
  HQ --> REVIEW
  HQ --> REQUEST
  HQ --> REPORT
  HQ --> ARCHIVE
  LEAD --> REQUEST
  LEAD --> ADMIN
  SA --> ADMIN
  ST <--> EXCHANGE
  EV <--> EXCHANGE
  TA <--> EXCHANGE
  WX --> DASH
  PS <--> ALERT
```

The model summarizes principal actors and external systems. Role-specific diagrams are maintained in the role-specific HQ/Admin, household, and rescuer diagrams.

## 2.3 User Classes and Characteristics

| User class | Access channel | Characteristics and primary responsibilities |
| --- | --- | --- |
| Household resident | Android/mobile app | Uses linked household credentials; may have limited technical experience and may operate under stressful conditions or intermittent connectivity. Views alerts and submits household/member reports. |
| Rescuer | Android/mobile app | Uses an authorized rescuer account/device. Reviews assignments, navigates, reports field information, requests resources, and communicates with the team. |
| HQ personnel / dispatcher | Web app | Uses role-authorized operational modules to monitor events, households, dispatch, field activity, and reports. Access varies by configured permissions. |
| HQ leader / approver | Web app | Performs permitted request approvals, personnel/role administration, and incident coordination. Approval rights are separately enforced. |
| Super administrator | Web app | Performs system-level administration and inquiry management where enabled. |
| External partner system | Server-to-server API or configured connection | Uses integration-specific credentials and contracts. It is not a human UI role; external access must not inherit web-user permissions. |
| Evacuation verifier | Intended HQ/rescuer workflow | Backend verification endpoints exist, but a complete dedicated scanner/operator UI is not established. This is not specified as a separate account role. |

## 2.4 Operating Environment

### Backend

- PHP 8.3 or compatible supported version.
- Laravel 13 API application.
- MySQL database using the active approved schema.
- Configured queue workers, scheduler, cache, and storage where required by enabled workflows.
- A network endpoint reachable by web clients, mobile devices, and configured partners.

### Web client

- React 19 and Vite 8 application.
- Current desktop-class browser with JavaScript enabled.
- HTTPS is required for browser Push and service-worker behavior outside localhost.

### Mobile client

- Expo SDK 54 / React Native application.
- Android device for the verified standalone APK workflow; iOS support depends on a separately configured build and provider setup.
- Location permission, notification permission, GPS hardware, storage, and network are required only for features that use them.
- OneSignal/native map functionality requires a development or production build, not ordinary Expo Go.

### Network and providers

- Clients require IP connectivity to the API for server-backed operations.
- LAN/Tailscale or another securely managed network may be used during development/deployment.
- Weather, maps, routing, external partner, SMS, and notification functions depend on their respective service reachability and terms.

## 2.5 Design and Implementation Constraints

1. Laravel is a JSON API; the HQ/Admin user interface is a separate React web application.
2. Web and mobile clients shall use API contracts and shall not access MySQL directly.
3. API authentication uses Laravel Sanctum or the configured external integration authentication.
4. Role and module/action permissions shall be evaluated by the backend; hiding a menu item is not sufficient authorization.
5. The operational database is shared/externally governed. Destructive rebuilds, unreviewed migrations, destructive seeders, and credential exposure are prohibited.
6. The active database connection shall be configurable using local backend environment configuration. TrackingAid access, if enabled, shall use separate configuration.
7. The application shall display real records from the configured data source and shall not present fabricated fallback values as operational data.
8. Public forecast, tile, and routing endpoints have provider limits and are not guaranteed emergency-grade services.
9. External credentials, private signing keys, database credentials, and push tokens shall not be committed in source or public documentation.
10. Geographic layers must retain provenance and limitations. A susceptibility raster/polygon is not a live sensor or a guarantee of safety.
11. Offline support shall only be claimed for the specific workflows and device configurations that have been validated.
12. The product is a capstone coordination aid, not a certified emergency dispatch, warning, or life-safety system.

## 2.6 Assumptions and Dependencies

- Users have valid accounts provisioned in the active database or through an approved account workflow.
- Household users are linked to authorized household/member records; RESQPERATION does not independently establish the authoritative census.
- Operational data is accurate enough for the intended workflow and is maintained by authorized sources/operators.
- The backend, active database, network, queue workers, and scheduled tasks are available for workflows that rely on them.
- Push delivery requires valid provider configuration, a subscribed device, permission, network access, and a supported app build.
- The Open-Meteo free API is subject to non-commercial use limits and CC BY 4.0 attribution; deployment owners must review current terms.
- PAGASA and PHIVOLCS publish official information; operators remain responsible for interpretation and local decision-making.
- OpenStreetMap base map data and tile service are distinct: OSM data attribution/licence and tile usage policies both apply.
- Partner endpoints, schemas, credentials, and service uptime are outside RESQPERATION's control.
- Mambaling census statistics in project documentation are dated census references, not live household counts.
- Operators are trained and retain authority to verify, correct, or reject system-generated or partner-supplied information.

## 2.7 Apportioning of Requirements

Requirements are apportioned across the following releases/work areas:

| Release/work area | Allocation |
| --- | --- |
| Version 1 operational core | Authenticated HQ/Admin web modules, household/rescuer mobile entry, disaster/status workflows, mapping/weather context, dispatch, request review, reports, and archives. |
| Version 1 conditional integrations | OneSignal/mobile push, browser push, SMS gateway, external report exchange, and TrackingAid handoff; acceptance depends on deployment configuration and reachable partners. |
| Partial capabilities to complete | Dedicated evacuation verification UI, verified full offline household status reporting, deployment-complete offline navigation, end-to-end partner fulfillment acceptance, and issues identified in the latest audit. |
| Future extensions | Additional barangays and deployment profiles, formal service-level targets, broader agency integrations, verified persistent offline workflows, and any production certification required by stakeholders. |

Priorities and release dates are subject to adviser and stakeholder approval. No future capability is implied to be present merely because it appears in this allocation.

# 3. Specific Requirements

**Requirement notation:** Each requirement has a unique identifier and uses “shall” for a normative system obligation. The MoSCoW priority column indicates requirement importance; the separate implementation-status column is evidence, not part of the normative requirement. Priority and implementation status are assessed independently. Status values summarize the October 20, 2026 implementation audit [3]; they do not certify a later build, a deployment, user acceptance, or a live partner connection. Appendix B explains the evidence limitations.

## 3.1 External Interface Requirements

### 3.1.1 User Interfaces

#### Web interface

- The web application shall present a role-authorized HQ/Admin workspace for dashboards, disaster events and broadcasts, weather, maps, household status, dispatch, rescuer/team management, resources/requests, situation reports, archives, notifications, and profile actions where permission is granted.
- The application shall display loading, empty, success, and error states distinctly.
- Tables and large collections shall provide supported search/filter/pagination rather than requiring all operational records to be loaded at once.
- The interface shall show the source and status of external data where material to interpretation.
- Destructive or consequential actions shall use an explicit confirmation or review step appropriate to the operation.

#### Household mobile interface

- The mobile application shall provide household login and role-specific routing.
- First-run setup shall clearly communicate the location data required by enabled household features and request OS permission before using device location.
- Current alerts, household/member context, status choices, submission result, and available history shall be visible without relying on fabricated telemetry or status.
- The application shall show when a submission is pending, rejected, or saved; it shall not imply server synchronization on network failure.
- Household location, battery, network, and notification indicators shall be shown only when obtained from the corresponding device or server source.

#### Rescuer mobile interface

- The mobile application shall provide rescuer login and role-specific access.
- Assignment details, navigation status, field-report forms, request workflow, radio controls, and network/location state shall be presented according to the rescuer's authorization.
- The application shall distinguish online/available route data from locally cached/offline data.
- The application shall not label internet voice communication as offline hardware radio.

#### Screenshot evidence placeholders

Actual screenshots are intentionally not embedded in this draft. Replace each placeholder with a legible screenshot from the build used for defense or submission. Identify the client and build/version; mask real household/member personal data, credentials, device tokens, private hostnames, and other sensitive information. Do not present sample data as live operational data.

**Figure 3. RESQPERATION web login screen**
_[Insert screenshot here.]_

**Figure 4. HQ/Admin dashboard and active incident overview**
_[Insert screenshot here.]_

**Figure 5. HQ/Admin map and household/responder layers**
_[Insert screenshot here.]_

**Figure 6. Household mobile home and current disaster alert**
_[Insert screenshot here.]_

**Figure 7. Household mobile status-reporting screen**
_[Insert screenshot here.]_

**Figure 8. Rescuer mobile assignment and navigation screen**
_[Insert screenshot here.]_

**Figure 9. HQ/Admin resource-request review and validation screen**
_[Insert screenshot here.]_

**Figure 10. Situation report / archive screen**
_[Insert screenshot here.]_

### 3.1.2 Hardware Interfaces

- The system shall use a compatible server or development computer to host the Laravel API and configured supporting services.
- Mobile location functions shall interface with device GPS/location services only after applicable OS permission.
- Mobile notification delivery shall interface with supported Android/iOS notification facilities through the configured provider SDK.
- Camera access may be used for QR scanning only when the scanning interface is implemented and permission is granted.
- Optional SMS transport shall interface with a configured Android gateway phone and SIM/carrier service; this hardware is not mandatory for core web/mobile use.
- The system shall degrade gracefully if GPS, camera, notification hardware, or a gateway device is unavailable.

### 3.1.3 Software Interfaces

| Interface | Protocol / data | Purpose | Boundary / status |
| --- | --- | --- | --- |
| Web and mobile clients ↔ Laravel API | HTTPS, JSON, REST-style routes, Sanctum token | Authentication and operational workflows. | Core system interface. |
| Laravel ↔ active MySQL database | MySQL driver through Laravel data access | Operational and framework persistence. | Schema is governed; use approved database configuration. |
| Laravel ↔ TrackingAid database/API | Separately configured database/API handoff | Forward validated resource requests and reconcile status where supported. | Conditional on partner availability and configuration. |
| Laravel ↔ SafeTrack | Shared data/partner interface | Household identity and agreed household/report exchanges. | Partner remains source of its domain data. |
| Laravel ↔ EvaTrack | Authenticated JSON API | External request intake and report exchange. | Live partner endpoint must be verified separately. |
| Laravel ↔ Open-Meteo | HTTPS weather API | Forecast snapshots. | Forecast provider, not an official warning authority. |
| Laravel ↔ PAGASA/PHIVOLCS | Public advisory feeds and source links | Official-source advisory context. | Display/update depends on source availability and current integration. |
| Web/mobile ↔ OpenStreetMap and map services | HTTPS tile/vector/map requests | Base maps and geographic display. | Attribution and usage policy apply. |
| Web ↔ OSRM public demo | HTTPS route request/GeoJSON | On-demand route geometry. | Public demo, no mission-critical availability guarantee. |
| Laravel/mobile ↔ OneSignal and platform push | HTTPS REST/SDK and native push services | Mobile push subscription and delivery. | Requires private credentials, permission, subscription, and network. |
| Web browser ↔ Web Push service | Service Worker, Push API, VAPID | Browser notification subscription/delivery. | Secure origin and supported browser required. |
| Laravel ↔ SMS Gateway for Android | HTTPS/local gateway API and signed webhook | Optional inbound/outbound SMS workflow. | Optional; carrier and network dependencies apply. |

API keys and private credentials shall be supplied through protected runtime configuration and shall not appear in this SRS.

### 3.1.4 Communications Interfaces

- Client-server communication shall use HTTPS in deployed environments. Local development may use explicitly configured localhost/LAN endpoints, with no claim of production security for plain HTTP.
- REST API requests shall use JSON for supported request/response bodies and appropriate HTTP status codes for success, validation failure, authorization failure, and service failure.
- Authenticated application requests shall carry the configured bearer token using the API's supported authentication mechanism.
- Partner API calls shall use partner-specific credentials and documented versioned contracts; credentials for different partner functions shall not be conflated.
- WebSocket/realtime channels, queues, push, and scheduled jobs shall be treated as optional supporting communication paths. The application shall retain a defined refresh/failure behavior if realtime delivery is unavailable.
- SMS webhooks shall be authenticated and validated according to the configured gateway signature/timestamp contract.
- The application shall not log secrets or full authentication tokens in normal operational logs.

## 3.2 Functional Requirements

### 3.2.1 Authentication and User Management (AUTH)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| AUTH-001 | The system shall authenticate a user against a valid account identifier and password before granting a protected session. | Must | Implemented |
| AUTH-002 | The system shall identify the user's role and route the session to only the authorized web or mobile workspace. | Must | Implemented |
| AUTH-003 | The system shall deny access for invalid credentials, inactive accounts, missing required household linkage, unauthorized device state, or failed network authentication. | Must | Implemented |
| AUTH-004 | The system shall authorize each protected API action on the backend using authentication, role, module, and action rules as applicable. | Must | Implemented |
| AUTH-005 | The system shall allow an authenticated user to retrieve their current identity and end their session. | Must | Implemented |
| AUTH-006 | The system shall allow users to view and update permitted profile fields and change their password according to configured policy. | Must | Implemented |
| AUTH-007 | The system shall support configured account-recovery questions and verification before resetting a password. | Should | Conditional on recovery setup |
| AUTH-008 | The system shall allow authorized administrators to manage supported rescuer accounts, teams, HQ accounts, roles, module access, and approval rights. | Must | Partial; some registration verification fields/workflows are not established |
| AUTH-009 | The system shall maintain an audit trail for supported privileged account and operational changes. | Must | Implemented for documented audited actions |
| AUTH-010 | The system shall rate-limit selected authentication and public/external write endpoints to reduce automated abuse. | Must | Implemented; thresholds are route-specific |

#### Use Case Narrative UC-01: Sign in and enter the authorized workspace

**Primary actors:** Household resident, rescuer, HQ personnel, HQ leader, super administrator.
**Preconditions:** The account is active and the API/database are reachable.
**Trigger:** The user submits credentials.

**Main flow:** The client sends credentials to the API; the API verifies the account, password, role, and applicable account/device/household conditions; the API returns an authorized session; the client opens only the user's permitted workspace.
**Alternate flow:** Invalid credentials, an inactive account, missing household linkage, device restrictions, or service failure denies access and displays an explicit outcome.
**Postcondition:** The session is authenticated and limited to permissions associated with the user's role.

### 3.2.2 Household Records and Mobile Reporting (HOU)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| HOU-001 | The system shall display household and member records linked to the authenticated household account. | Must | Implemented |
| HOU-002 | The system shall guide the resident through required first-run household setup and capture an address/geotag only with permission or user input. | Must | Implemented |
| HOU-003 | The system shall allow a household resident to view current household/member information and supported profile details. | Must | Implemented |
| HOU-004 | The system shall display the applicable active disaster alert and status choices supplied by the backend. | Must | Implemented |
| HOU-005 | The system shall accept an authorized household/member safety-status submission only when identifiers and status are valid for the applicable event/broadcast. | Must | Implemented |
| HOU-006 | The system shall record accepted status updates with household/member/event context and submission time, and make them available to authorized HQ users. | Must | Implemented |
| HOU-007 | The system shall allow residents to view available status history. | Must | Implemented |
| HOU-008 | The system shall permit a resident to connect to or report for a trusted household only after required lookup, connection, PIN, and authorization checks succeed. | Must | Implemented |
| HOU-009 | The system shall display a household evacuation QR payload for supported evacuation workflows. | Must | Implemented |
| HOU-010 | The system shall provide backend QR verification/check-in operations only to authorized users and valid center/event records. | Should | Partial; dedicated operator scan/entry UI is not established |
| HOU-011 | The system shall report location, battery, and network information only when the corresponding value is available from a trusted source. | Must | Implemented |
| HOU-012 | The system shall communicate failed or unsynchronized status submissions without indicating successful server persistence. | Must | Implemented by online request handling; durable household offline outbox is not established |
| HOU-013 | The system shall support persistent offline household overview caching and durable status submission for later synchronization. | Could | Proposed; not established |

#### Use Case Narrative UC-02: Set up household and report safety status

**Primary actor:** Household resident.
**Preconditions:** The resident is authenticated and linked to a household; event-based reporting requires an applicable event and status set.
**Trigger:** The resident opens household setup or submits a status update.

**Main flow:** The app displays linked household/member records; requests missing setup information and location permission as needed; displays the active alert and valid status choices; submits the selected household/member status; and shows success only after the API confirms persistence.
**Alternate flow:** Permission denial records no device GPS; invalid linkage, status, event, or connectivity produces an explicit error. An unsynchronized submission is not represented as saved.
**Postcondition:** An accepted status is available to authorized HQ users with household, member, event, time, and source context.

#### Use Case Narrative UC-11: Verify an evacuation QR

**Primary actors:** Household resident and authorized evacuation verifier.
**Preconditions:** A household QR payload is available; an authorized verifier and valid evacuation center/event record are required to perform a check-in.
**Trigger:** The resident presents the QR at an evacuation center.

**Main flow:** The resident displays the supported QR payload; an authorized verifier submits the payload through an available verification interface; the backend validates the token, event, center, and authorization; a valid check-in is recorded.
**Alternate flow:** Invalid/expired data, duplicate or unauthorized requests, or missing center/event context is rejected. A dedicated operator scan/entry interface is not established in the reviewed implementation and must not be assumed available.
**Postcondition:** A valid verification is recorded with its applicable household/member, event, center, and time context.

### 3.2.3 Rescuer Operations (RES)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| RES-001 | The system shall allow an authenticated rescuer to view only assignments and household information permitted for that rescuer. | Must | Implemented |
| RES-002 | The system shall allow a rescuer to view the assignment target, relevant household details, and available route information. | Must | Implemented online |
| RES-003 | The system shall allow an authorized rescuer to update supported assignment status and operational location. | Must | Implemented; connectivity required |
| RES-004 | The system shall record supported household check-ins and member assessment information with event/assignment context. | Must | Implemented |
| RES-005 | The system shall accept authorized field reports, including supported findings, notes, timestamps, and damage/injury information. | Must | Implemented |
| RES-006 | The system shall allow a rescuer to submit a resource/personnel request and show its recorded state. | Must | Partial; current audit reports an urgency persistence issue requiring verification |
| RES-007 | The system shall allow rescuers to use the implemented internet-connected PTT/radio clip workflow when service and permissions are available. | Must | Implemented online; not offline radio |
| RES-008 | The system shall display available GPS/route status and shall not claim a route is safe or guaranteed. | Must | Implemented |
| RES-009 | The system shall allow compatible offline map/route packs and trip synchronization for configured rescuer devices. | Should | Partial; deployment, asset reachability, cached route and physical offline tests remain |
| RES-010 | The system shall queue and later synchronize all eligible offline field submissions without loss or duplication. | Could | Proposed/partial; durable support is not established for all operations |

#### Use Case Narrative UC-05: Submit field report and household assessment

**Primary actor:** Rescuer.
**Preconditions:** The rescuer is authenticated and has an authorized assignment or field-report permission.
**Trigger:** The rescuer records an on-site finding or assessment.

**Main flow:** The app presents the authorized assignment and relevant household context; the rescuer enters the supported finding, notes, and assessment details; the API validates the assignment and data; the API stores the report and makes it available to authorized HQ users.
**Alternate flow:** An unauthorized assignment, invalid data, unavailable API, or missing location is surfaced without fabricating a successful report or location.
**Postcondition:** Accepted field information is associated with its actor and operational context.

### 3.2.4 HQ Operations, Disaster Management, and Mapping (OPS)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| OPS-001 | The system shall present authorized HQ users with dashboard summaries and recent operational activity from the active data source. | Must | Implemented |
| OPS-002 | The system shall allow authorized users to create, view, update, and close disaster events under configured business rules. | Must | Implemented |
| OPS-003 | The system shall allow authorized users to create event-scoped broadcasts with target scope, instructions, and configured household status choices. | Must | Implemented |
| OPS-004 | The system shall allow HQ users to review household status, available location, status history, and relevant household details. | Must | Implemented |
| OPS-005 | The system shall allow authorized HQ users to confirm/review household status without providing an unauthorized manual status edit path. | Must | Implemented |
| OPS-006 | The system shall allow authorized HQ users to create and monitor rescuer/team assignments and supported assignment state transitions. | Must | Implemented |
| OPS-007 | The system shall display available household, evacuation-site, team/responder, route, and hazard layers on supported maps. | Must | Implemented with provider/data limitations |
| OPS-008 | The system shall preserve source attribution, source date, and interpretation limits for imported hazard layers. | Must | Implemented in hazard documentation; verify with each layer |
| OPS-009 | The system shall retrieve and store configured weather snapshots and display their provider/source and observation/forecast context. | Must | Implemented |
| OPS-010 | The system shall distinguish forecast-provider data from official warning authority and direct operators to confirm official warnings through PAGASA/appropriate authority. | Must | Implemented |
| OPS-011 | The system shall display official advisory content only when obtained through an enabled official-source feed or link and shall preserve its source/time/expiry context where supplied. | Should | Conditional on configured feed and upstream availability |
| OPS-012 | The system shall support map/routing failures by showing unavailable/error state rather than representing a missing route as safe. | Must | Not independently verified |
| OPS-013 | The system shall not claim automatic optimal dispatch, official severity classification per affected area, or complete exposure analysis unless implemented and validated. | Must | Not independently verified |

#### Use Case Narrative UC-03: Publish a disaster broadcast

**Primary actors:** Authorized HQ personnel or dispatcher.
**Preconditions:** The user has event/broadcast permission and the target event is valid.
**Trigger:** The operator creates or updates a disaster alert.

**Main flow:** The operator selects the event, target scope, instructions, and allowed household status choices; the API validates authority and event state; the system saves the broadcast and exposes it to applicable household and HQ views.
**Alternate flow:** Invalid scope, closed event, insufficient permission, or persistence failure prevents publication and returns an explicit error.
**Postcondition:** A saved broadcast is associated with its event, scope, author, and available status choices.

#### Use Case Narrative UC-04: Review household reports and dispatch a responder

**Primary actors:** HQ dispatcher and authorized rescuer.
**Preconditions:** An applicable event, household report, and authorized responder/team are available.
**Trigger:** HQ reviews a reported household condition and decides to assign a response.

**Main flow:** HQ reviews the report and its available location/history; selects an eligible team or rescuer; records the assignment; the rescuer views the assignment and updates supported status/location; HQ monitors the resulting activity.
**Alternate flow:** Missing/uncertain location is shown as unavailable; invalid assignment transitions or access are rejected; routing data is not presented as a safety guarantee.
**Postcondition:** The assignment and subsequent authorized updates are recorded against the event.

#### Use Case Narrative UC-08: Review maps, weather, and advisories

**Primary actor:** Authorized HQ user.
**Preconditions:** The user has map/dashboard access; configured geographic and weather sources are reachable or cached data is available.
**Trigger:** The user opens an event map or weather/advisory view.

**Main flow:** The system displays available household, responder, evacuation-site, route, and hazard layers with source context; retrieves configured weather snapshots; and presents official advisory links or enabled feeds with their provider and time context.
**Alternate flow:** Missing/stale layers or provider failures are identified as unavailable; forecast information is not presented as an official warning, and route availability is not represented as route safety.
**Postcondition:** The user can review sourced context while retaining responsibility for operational interpretation.

### 3.2.5 Resource Requests and Partner Exchange (REQ)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| REQ-001 | The system shall allow eligible internal users and configured external partners to submit resource/personnel requests with required validated fields. | Must | Implemented |
| REQ-002 | The system shall record request source, event/location context, requested items/personnel, urgency, status, and audit information where supported by the schema. | Must | Partial; audit identified urgency persistence regression for mobile submissions |
| REQ-003 | The system shall allow only an authorized approver to validate, return, reject/cancel, or complete supported request transitions. | Must | Implemented |
| REQ-004 | The system shall forward only validated requests to the configured TrackingAid handoff. | Should | Implemented workflow; partner delivery conditional |
| REQ-005 | The system shall preserve a durable retryable record for handoff synchronization where configured and shall not report failed synchronization as successful fulfillment. | Should | Implemented/conditional on workers and partner connectivity |
| REQ-006 | The system shall provide authenticated external resource-request intake with an integration-specific credential and validation. | Must | Implemented |
| REQ-007 | The system shall map request states to clear operational meanings such as pending review, validated, forwarded/in progress, and fulfilled/completed. | Must | Implemented |
| REQ-008 | The system shall not claim TrackingAid fulfillment or partner end-to-end acceptance until confirmed by the supported partner workflow. | Must | Not independently verified |
| REQ-009 | The system shall support external report request review, manual accept/reject, frozen report snapshots, signed status/download links, and configured file renewal. | Should | Implemented for local contract; partner acceptance conditional |
| REQ-010 | The system shall keep resource-intake and report-exchange authentication scopes separate. | Must | Implemented |

#### Use Case Narrative UC-06: Submit and review a resource/personnel request

**Primary actors:** Rescuer or configured partner as requester; authorized HQ approver.
**Preconditions:** Request intake is enabled and the event/location context is valid.
**Trigger:** A requester submits a resource or personnel need.

**Main flow:** The requester submits validated request details; the system records source, event/location, urgency, requested items, and state where supported; an authorized approver reviews and accepts, returns, rejects, or completes a permitted transition; validated requests are handed off to the configured partner workflow.
**Alternate flow:** Invalid input, unauthorized transition, unavailable partner, or failed handoff is recorded and shown as pending/failed rather than fulfilled.
**Postcondition:** The request has a traceable status and any external handoff remains distinguishable from confirmed partner fulfillment.

### 3.2.6 Situation Reports, Exports, and Archives (REP)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| REP-001 | The system shall generate situation reports from available event-scoped operational data and configured partner findings. | Should | Implemented; partner data conditional |
| REP-002 | The system shall preserve a report snapshot when a report is saved/locked where that workflow applies. | Must | Implemented |
| REP-003 | The system shall represent unknown/unavailable data as unknown or unavailable and shall not invent values to complete a report. | Must | Not independently verified |
| REP-004 | The system shall support the defined situation-report sections and available formats (PDF, CSV, XLSX) according to the authorized workflow. | Must | Implemented for documented report flows |
| REP-005 | The system shall allow an authorized operator to review an external report request and decide whether to release a report. | Must | Implemented |
| REP-006 | The system shall provide event, household status, dispatch, request, situation-report, and other supported archive views with search/filter/pagination. | Must | Partial; one legacy-admin authorization contract needs reconciliation |
| REP-007 | The system shall export only records allowed by the user's authorization and selected scope. | Must | Implemented by documented access policy; validate per role |
| REP-008 | The system shall preserve a distinction between generating/downloading a report and external agency receipt/acknowledgement. | Must | Not independently verified |

#### Use Case Narrative UC-07: Prepare and exchange a situation report

**Primary actors:** Authorized HQ report preparer and configured external report requester.
**Preconditions:** An event and permitted source records exist; report export/exchange is configured.
**Trigger:** HQ prepares a report or reviews an external report request.

**Main flow:** The system assembles available event-scoped operational data; the preparer reviews and saves a report snapshot; the system generates an authorized supported export; any external request is reviewed and manually approved before a signed exchange link is issued.
**Alternate flow:** Missing data remains unknown/unavailable; invalid authorization or export failure is reported; sending a file is not treated as proof of receipt.
**Postcondition:** The report snapshot and any approved exchange action are auditable.

#### Use Case Narrative UC-09: Search and export an incident archive

**Primary actor:** Authorized HQ user.
**Preconditions:** The user has archive access and the requested event records are available.
**Trigger:** The user searches, filters, or exports an archive.

**Main flow:** The user selects permitted filters; the system returns authorized, paginated records; the user requests an export; the backend rechecks permissions and scope before producing the file.
**Alternate flow:** Unauthorized scopes, invalid filters, or service errors are rejected explicitly.
**Postcondition:** The user can access only records permitted by their role and selected scope.

### 3.2.7 Notifications and Communications (NOT)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| NOT-001 | The system shall present role-scoped in-app notifications for supported operational events. | Must | Implemented |
| NOT-002 | The system shall register mobile push subscriptions when the user, device, app build, permission, and provider configuration permit. | Should | Conditional |
| NOT-003 | The system shall send supported broadcast/dispatch notifications through configured OneSignal services. | Should | Conditional; provider acceptance does not establish device display |
| NOT-004 | The system shall support browser push registration and delivery on supported secure browser origins when configured. | Should | Conditional |
| NOT-005 | The system shall use the configured SMS gateway for supported SMS workflows only when gateway credentials, network, device, and carrier service are available. | Could | Optional/conditional |
| NOT-006 | The system shall distinguish message submission/provider acceptance from delivery/receipt by a person. | Must | Not independently verified |
| NOT-007 | The system shall not disclose records from unauthorized modules through notification content or links. | Must | Not independently verified |
| NOT-008 | The system shall provide a recoverable refresh/fallback path when realtime notification infrastructure is unavailable. | Must | Implemented for documented areas |

#### Use Case Narrative UC-10: Register and receive notifications

**Primary actor:** Authenticated household resident, rescuer, or HQ user.
**Preconditions:** The device/browser supports the channel and provider configuration, permissions, and network are available.
**Trigger:** The user enables notifications or a supported operational event occurs.

**Main flow:** The client obtains permission and registers a subscription; the backend associates it with the authorized user/device; supported events create role-scoped notifications; the configured provider attempts delivery.
**Alternate flow:** Denied permission, invalid subscription, provider rejection, or network failure is surfaced; in-app refresh remains available where implemented.
**Postcondition:** Subscription/delivery state is recorded where supported; provider acceptance is not asserted as human receipt.

### 3.2.8 Audit, Administration, and System Health (ADM)

| ID | Requirement | Priority | Implementation Status |
| --- | --- | --- | --- |
| ADM-001 | The system shall validate user input and reject unauthorized or invalid writes with an explicit response. | Must | Implemented |
| ADM-002 | The system shall maintain audit records for supported high-impact actions and state transitions. | Must | Implemented for documented workflows |
| ADM-003 | The system shall provide health/readiness endpoints for operational monitoring without exposing secrets. | Must | Implemented |
| ADM-004 | The system shall support configured scheduled/queued work for weather refresh, notification delivery, partner synchronization, and other enabled jobs. | Should | Conditional on deployment |
| ADM-005 | The system shall allow authorized super administrators to review and manage supported inquiries. | Must | Implemented |
| ADM-006 | The system shall protect secrets through local/private environment configuration and shall avoid returning secrets in client responses. | Must | Not independently verified |
## 3.3 Non-Functional Requirements

### 3.3.1 Performance

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-PER-001 | Normal list and dashboard queries shall use server-side filtering/pagination or bounded summaries for potentially large collections. | Inspect API/query behavior and test a representative large dataset. |
| NFR-PER-002 | The application shall show a visible loading state while waiting for an asynchronous operation and a completion/error state when it ends. | UI acceptance test for representative modules. |
| NFR-PER-003 | External provider calls shall have configured timeouts and shall not block the client indefinitely. | Inspect configuration and simulate provider delay/outage. |
| NFR-PER-004 | Performance acceptance targets (response-time percentile, concurrent users, and dataset size) shall be approved and recorded before production deployment. | Signed performance test plan and test report. |

### 3.3.2 Security

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-SEC-001 | Protected API routes shall require valid authentication and shall enforce backend authorization for each operation. | Automated positive and negative role tests. |
| NFR-SEC-002 | Passwords shall be stored and verified using a suitable one-way password-hashing mechanism; plaintext passwords shall not be stored. | Review framework configuration and database handling. |
| NFR-SEC-003 | Secrets, tokens, passwords, and private keys shall not be embedded in public source, committed documentation, client bundles, or ordinary logs. | Secret scanning and source/configuration review. |
| NFR-SEC-004 | Deployed browser and client-server traffic shall use HTTPS; insecure remote origins shall not be used for browser Push. | Deployment and browser-origin checks. |
| NFR-SEC-005 | Inputs shall be validated and query operations shall avoid unsafe string-built SQL. | Automated validation tests and code review. |
| NFR-SEC-006 | Public and external write endpoints shall use appropriate authentication, authorization, rate limiting, and replay/expiry controls for their contract. | Endpoint-specific security tests. |
| NFR-SEC-007 | Operational data shall be exposed according to least privilege and role/module scope. | Authorization matrix tests. |

### 3.3.3 Reliability and Availability

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-REL-001 | API and provider failures shall be surfaced as errors or unavailable/stale states, not success-shaped fallback data. | Simulate database, provider, queue, and network failures. |
| NFR-REL-002 | Queued/retryable operations shall preserve enough state to identify failure and retry without claiming downstream completion prematurely. | Worker failure/retry test. |
| NFR-REL-003 | Data-changing operations shall use transactions or equivalent consistency controls where multi-record atomicity is required. | Review workflow and failure-injection tests. |
| NFR-REL-004 | Backups and recovery objectives (RPO/RTO) shall be defined by the deployment/data owner before production use. | Approved operational backup/recovery plan. |
| NFR-REL-005 | Availability and delivery guarantees shall not exceed those of the hosting network, database, devices, and configured third-party providers. | Deployment review. |

### 3.3.4 Usability

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-USA-001 | Core household and rescuer actions shall use concise, consistent labels and present the primary task before secondary information. | Representative task-based usability review. |
| NFR-USA-002 | Status, urgency, assignment, and notification meaning shall not be conveyed by color alone. | UI inspection and accessibility test. |
| NFR-USA-003 | Forms shall identify required fields, validation errors, and the outcome of submission. | Form acceptance tests. |
| NFR-USA-004 | User-facing language shall distinguish verified operational records, external reports, forecasts, advisories, estimates, and missing data. | Content review. |
| NFR-USA-005 | Mobile layouts shall support common Android screen sizes and touch operation for the tested device set. | Physical-device and emulator test matrix. |

### 3.3.5 Maintainability

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-MNT-001 | Backend business workflows shall remain separated from HTTP controllers sufficiently to support isolated testing and reuse. | Architecture/code review. |
| NFR-MNT-002 | API contracts, integration configuration, and module-specific setup shall be documented with the relevant source modules. | Documentation review. |
| NFR-MNT-003 | Automated tests shall cover security-sensitive authorization, status transitions, request workflows, and external failure behavior. | Test inventory and CI/test report. |
| NFR-MNT-004 | Database schema changes shall be reviewed and compatible with the active governed schema before deployment. | Migration/schema review. |
| NFR-MNT-005 | Dependencies and external APIs shall be periodically reviewed for supported versions, security advisories, and terms changes. | Periodic dependency/source review. |

### 3.3.6 Portability

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-POR-001 | Backend environment-specific values shall be externalized from source code. | Configuration review. |
| NFR-POR-002 | Mobile app builds shall support the documented target OS/device profile and declare native-feature requirements. | Build/install test on documented target. |
| NFR-POR-003 | The system shall not assume localhost is reachable from a physical mobile device or external partner. | Network configuration test. |
| NFR-POR-004 | A deployment shall document supported PHP, database, browser, and mobile OS versions. | Deployment manifest/release checklist. |

### 3.3.7 Data Privacy and Compliance

| ID | Requirement | Verification |
| --- | --- | --- |
| NFR-PRI-001 | The system shall collect and process location, household, member, and device data only for documented operational purposes and authorized workflows. | Data inventory and permission review. |
| NFR-PRI-002 | Location permission shall be requested through the platform mechanism, and denial/unavailable location shall not be replaced with fabricated coordinates. | Device permission tests. |
| NFR-PRI-003 | Access to personal and household records shall be role-restricted and auditable for supported actions. | Authorization and audit review. |
| NFR-PRI-004 | Data retention, correction, deletion, consent/notice wording, incident response, and data-subject request procedures shall be approved by the data controller before production use. | Approved privacy/governance documents. |
| NFR-PRI-005 | The system shall not claim Data Privacy Act compliance solely because these requirements appear in this SRS. | Review project claims and deployment approvals. |

## 3.4 Database Requirements

**Data store and ownership**

- The operational database shall be the active database selected by protected backend configuration.
- The database schema is shared/externally governed; RESQPERATION shall not assume it can rebuild, drop, or rename partner-owned tables.
- Laravel migrations and SQL proposals shall be reviewed against the active schema and approved by the database owner before applying.
- The application shall use explicit key mappings where the operational schema uses non-default primary-key names.
- Backend operations shall use transactions where consistency across related records is required.
- Database credentials shall be stored outside tracked application source and documentation.

**Core logical entities**

The logical data model includes, where present in the authorized active schema:

- User, role, access token, device token, notification, and audit records.
- Barangay, sitio, purok, address, household, household member, geotag, and trusted-household records.
- Disaster type, severity, event, broadcast, household-event/status, and status-history records.
- Rescue team, responder, assignment, route, route coordinate, location, check-in, field report, and communication records.
- Resource request, request status, urgency, validation, allocation, and partner-handoff records.
- Evacuation center, evacuation record, occupancy, QR verification/check-in records.
- Weather snapshot, situation report, archive, and report-exchange records.

Actual tables and columns vary with the approved shared schema and implemented migrations. The logical model is not authorization to create each entity as a new table.

**Data integrity and retention**

- Each status, dispatch, request, report, and check-in shall reference valid related records and authorized actors.
- Historical operational records shall retain event/time/source context sufficient for audit and reporting, subject to applicable retention policy.
- Foreign keys and uniqueness rules shall follow the active schema; application validation shall not assume every partner-owned table enforces the same constraints.
- Synchronization identifiers and idempotency keys shall be used only where supported by the relevant integration contract.
- Deletion, archival, export, and retention rules shall respect the data owner's approval and applicable privacy obligations.
- The system shall distinguish a numeric zero from an unknown/unavailable value in generated reports.

**Database access and protection**

- Database access shall be restricted to the backend service and authorized administrators.
- Web/mobile clients shall not receive database credentials or connect directly to the database.
- Database backups shall be encrypted and access-controlled according to the deployment owner policy.
- Seed data shall be explicitly marked as development/test data and shall not be mixed with real operational records.
- Destructive commands and unreviewed sample SQL shall not be used against a shared or production database.


# Appendix A. Preliminary Entity-Relationship Overview

**Figure 11. RESQPERATION logical entity-relationship diagram**

_[Insert the exported ERD here for the panel/submission copy. Identify the diagram as logical and show cardinality and ownership boundaries. The Mermaid block is the editable source representation.]_

The following logical ERD is scoped to RESQPERATION data and integration boundaries. It is not a migration plan or a claim that every deployment contains identical tables. External systems are represented as actors, not as internal RESQPERATION entities.

```mermaid
erDiagram
  ROLES ||--o{ USERS : assigned_to
  USERS ||--o{ PERSONAL_ACCESS_TOKENS : authenticates
  USERS ||--o{ DEVICE_TOKENS : registers
  USERS ||--o{ NOTIFICATIONS : receives_or_creates

  BARANGAYS ||--o{ SITIOS : contains
  SITIOS ||--o{ PUROKS : contains
  BARANGAYS ||--o{ HOUSEHOLDS : scopes
  PUROKS ||--o{ HOUSEHOLDS : groups
  HOUSEHOLDS ||--o{ HOUSEHOLD_MEMBERS : has
  HOUSEHOLDS ||--o{ GEOTAGGED_LOCATIONS : pins
  HOUSEHOLDS ||--o{ TRUSTED_HOUSEHOLDS : connects
  HOUSEHOLDS ||--o{ HOUSEHOLD_DISASTERS : participates
  HOUSEHOLDS ||--o{ HOUSEHOLD_STATUS_LOGS : reports
  HOUSEHOLD_MEMBERS ||--o{ HOUSEHOLD_STATUS_LOGS : subject_of

  DISASTER_TYPES ||--o{ DISASTER_EVENTS : classifies
  SEVERITY_LEVELS ||--o{ DISASTER_EVENTS : rates
  DISASTER_EVENTS ||--o{ DISASTER_BROADCASTS : announces
  DISASTER_EVENTS ||--o{ HOUSEHOLD_DISASTERS : scopes
  DISASTER_EVENTS ||--o{ HOUSEHOLD_STATUS_LOGS : receives
  DISASTER_EVENTS ||--o{ RESPONDER_ASSIGNMENTS : activates
  DISASTER_EVENTS ||--o{ RESOURCE_REQUESTS : includes
  DISASTER_EVENTS ||--o{ WEATHER_LOGS : snapshots
  DISASTER_EVENTS ||--o{ SITUATION_REPORTS : summarizes
  DISASTER_EVENTS ||--o{ INCIDENT_ARCHIVES : closes_into

  RESCUE_TEAMS ||--o{ RESPONDERS : contains
  USERS ||--o{ RESPONDERS : login_profile
  RESPONDERS ||--o{ RESPONDER_ASSIGNMENTS : receives
  RESPONDERS ||--o{ RESPONDER_LOCATION_LOGS : reports
  RESPONDERS ||--o{ RESPONDER_FIELD_REPORTS : submits
  RESPONDER_ASSIGNMENTS ||--o{ RESPONDER_ROUTES : has
  RESPONDER_ROUTES ||--o{ ROUTE_COORDINATES : contains
  RESPONDER_ASSIGNMENTS ||--o{ RESPONDER_FIELD_REPORTS : relates_to

  RESOURCE_REQUEST_STATUS ||--o{ RESOURCE_REQUESTS : marks
  URGENCY_LEVELS ||--o{ RESOURCE_REQUESTS : prioritizes
  RESOURCE_REQUESTS ||--o{ REQUEST_VALIDATIONS : reviewed_by

  EVACUATION_CENTERS ||--o{ EVACUATION_RECORDS : records
  EVACUATION_RECORDS ||--o{ EVACUATED_MEMBERS : includes
  HOUSEHOLD_MEMBERS ||--o{ EVACUATED_MEMBERS : checks_in

  SAFETRACK_EXTERNAL ||--o{ HOUSEHOLDS : provides_identity
  EVATRACK_EXTERNAL ||--o{ RESOURCE_REQUESTS : may_submit
  TRACKINGAID_EXTERNAL ||--o{ RESOURCE_REQUESTS : receives_validated_handoff
  WEATHER_SOURCES ||--o{ WEATHER_LOGS : provides
```

**Data-model notes**

- Actual key names and optional relationships are governed by the connected shared schema.
- SafeTrack, EvaTrack, and TrackingAid table internals are excluded.
- Household identity/source data and RESQPERATION operational data have distinct ownership boundaries.
- The diagram is a logical synthesis of the project scope ERD and data-model diagrams. It must be checked against the approved schema before being presented as a physical database design.

# Appendix B. Requirements Traceability Summary

The matrix maps every functional-requirement identifier in Section 3.2 to its design location, use-case narrative, and verification evidence. Each identifier in a stated inclusive range is covered. The listed project test-case inventory and automated tests are verification references; panel/user acceptance remains pending until completed and recorded for the submission build.

| Requirement identifiers | Design location / elements | Use case | Verification and acceptance evidence |
| --- | --- | --- | --- |
| AUTH-001–AUTH-010 | Sections 2.3, 2.5, 3.1, and 3.2.1; Laravel authentication, role/module authorization, account, and audit workflows | UC-01 | Authentication, role-scope, invalid-account, session, and account-management tests; map to the project test-case inventory and rerun against the submission build. |
| HOU-001–HOU-013 | Sections 2.3, 3.1.1–3.1.3, and 3.2.2; household/member records, status, trusted-household, and evacuation interfaces | UC-02, UC-11 | Household linkage, status persistence, permission-denial, QR authorization, and offline/reconnect tests; HOU-013 remains proposed and requires implementation before acceptance. |
| RES-001–RES-010 | Sections 2.3, 3.1.1–3.1.4, and 3.2.3; assignment, field reporting, location, communication, and offline navigation | UC-04, UC-05 | Assignment authorization/state, check-in, report, location, request, communication, and physical-device/offline tests; record build/device and connectivity conditions. |
| OPS-001–OPS-013 | Sections 2.1–2.3, 3.1.1–3.1.4, and 3.2.4; dashboard, event/broadcast, household review, dispatch, map, and hazard context | UC-03, UC-04, UC-08 | Role-scoped event/broadcast/dispatch tests, map/provider failure checks, source-attribution review, and acceptance of stale/unavailable states. |
| REQ-001–REQ-010 | Sections 2.1, 3.1.3–3.1.4, and 3.2.5; request validation, approval, partner handoff, and report exchange | UC-06 | Request-state and authorization tests, failure/retry tests, and live partner evidence for any partner-receipt or fulfillment claim. |
| REP-001–REP-008 | Sections 2.2, 3.1.3, and 3.2.6; report generation, export, archive, and external report approval | UC-07, UC-09 | Snapshot/content checks, unknown-data checks, export scope/role tests, and archive-role regression tests. |
| NOT-001–NOT-008 | Sections 2.2, 2.4, 3.1.3–3.1.4, and 3.2.7; in-app, mobile/browser push, and optional SMS | UC-10 | Subscription/provider tests and supported-device delivery checks; provider acceptance and human receipt must be recorded separately. |
| ADM-001–ADM-006 | Sections 2.3, 2.5, 3.1.3–3.1.4, and 3.2.8; administration, audit, health, jobs, and secrets | UC-01 and applicable operational use cases | Input-validation, authorization, audit, health/readiness, queue/scheduler, and secret-exposure checks. |

The project test inventory is a planning and verification aid; it does not establish that all cases passed. The [use-case catalog](USE_CASES.md) contains the maintained project-level use-case set. Requirement status must be reviewed when implementation, deployment configuration, or acceptance evidence changes.

### Implementation Status and Verification Evidence

The implementation evidence below is summarized from the Must-Haves audit dated October 20, 2026 [3]. The audit reviewed source, routes, tests, and selected UI flows. It expressly includes uncommitted working-tree changes and is not a certification of the public repository commit, deployed system, every installed mobile build, or operational partner availability. Its status labels describe only the scope assessed by that audit.

| Area | Audit status | Evidence and remaining acceptance condition |
| --- | --- | --- |
| HQ/Admin web workflows | Implemented in reviewed source | Dashboard, broadcast, weather, mapping, household status, dispatch, responder/team, requests, situation reports, archives, notifications, and profile paths were found. Full role-based acceptance and deployment configuration remain separate checks. |
| Household mobile workflows | Implemented with gaps | Household setup, profile, location, members, alerts, status, history, QR display, evacuation map, and trusted-household flows were evidenced. Persistent household offline overview/status outbox and reconnect/restart acceptance remain incomplete. |
| Rescuer mobile workflows | Implemented with limitations | Assignment, location, map, field-report, check-in, request, communication, profile, and push paths were evidenced. Device/build validation and the request urgency-persistence regression remain open. |
| Authentication and access control | Implemented in reviewed source | Protected API and role-access evidence was reviewed. Negative authorization and current deployment tests remain required. |
| Evacuation verification | Partial | Backend verification capability exists, but a complete authorized operator scanning/user-interface path and end-to-end test were not established. |
| Affected-area severity classification | Partial | Event-level severity exists; the audit found a mismatch between requested affected-area levels and current priority values. |
| Rescuer resource-request urgency | Partial | A targeted test reproduced `urgency_id` persistence as `NULL` where the test expected `3`; the mapping requires correction and regression verification. |
| Incident archives | Partial | Archive services and pages exist; `AuthenticatedArchiveContractTest` reproducibly received HTTP 403 for a legacy admin-role fixture. Current-role access must be reconciled and retested. |
| TrackingAid request handoff | Partial / external | Local durable handoff workflow evidence exists; no live partner-backed fulfillment round trip was established by the audit. |
| Push notifications | Implemented in code; device acceptance pending | Provider/client code was reviewed; real supported-build, permission, credential, and device-delivery acceptance was not performed by the audit. |
| Offline operation | Partial / not established end to end | The audit did not establish a persistent household status queue or verified offline navigation pack on a physical device. Online map/report functionality is not evidence of offline operation. |

The same audit reported **23 Implemented and 7 Partial** decisions among 30 reviewed checklist dropdowns. For its targeted test runs, it reported **81 passed and 2 failed tests out of 83** (429 assertions); the isolated rerun reported the same two failures, while the selected web suite reported 8 passed and the selected mobile suite reported 19 passed. These are dated audit results, not results produced by this SRS revision. Consult the linked audit for exact test commands, failure details, and scope before citing the figures in a defense presentation.

No production load test, full live partner verification, all-device usability study, or disaster-field trial is represented by these totals. The project team should rerun applicable tests on the exact submission commit and archive the output with build and environment details.

### Acceptance Criteria

The following end-to-end criteria define minimum acceptance evidence for the principal system workflows. The acceptance record must identify the test build, environment, test data, evaluator, execution date, result, and linked defect/report. A criterion marked “Pass” requires evidence for the entire described workflow, not merely the presence of a screen or endpoint.

| ID | Acceptance criterion | Required evidence | Result |
| --- | --- | --- | --- |
| AC-01 | Users with valid accounts enter only their authorized web/mobile workspace; invalid, inactive, or unauthorized accounts are denied. | Positive and negative authentication/role tests, including API authorization. | Pending panel/UAT record |
| AC-02 | A household user views only linked household/member data, submits a valid event status, receives a truthful result, and the status appears in an authorized HQ view with event and time context. | End-to-end test using non-sensitive test records and persistence verification. | Pending panel/UAT record |
| AC-03 | An authorized HQ user creates or updates an event/broadcast and the saved event and configured status choices appear to eligible clients. | API/database verification and client workflow demonstration; push delivery recorded separately. | Pending panel/UAT record |
| AC-04 | HQ creates a valid assignment, the assigned rescuer can retrieve it, and an allowed progress/report update is visible to authorized HQ users. | Assignment state-transition and role-scope tests. | Pending panel/UAT record |
| AC-05 | A resource request passes validation and approval rules; the system distinguishes local approval, queued handoff, partner receipt, and fulfillment. | Workflow tests plus live partner evidence for any claim of partner acceptance/fulfillment. | Pending panel/UAT record |
| AC-06 | Invalid, stale, unauthorized, and unavailable-provider cases produce explicit outcomes and do not create success-shaped records. | Negative, stale-data, timeout, and failure-injection tests. | Pending panel/UAT record |
| AC-07 | A situation report uses available event data, labels unavailable values appropriately, and exports only within authorized scope. | Report-content review, export test, and role-authorization tests. | Pending panel/UAT record |
| AC-08 | Archive queries and exports are restricted to authorized users and return records consistent with the selected scope. | Authenticated archive tests for each accepted role and export class. | Pending panel/UAT record |
| AC-09 | Location, household, and member data are collected and displayed only within the approved purpose and role scope; denied location permission does not yield fabricated coordinates. | Permission-denial device test, data-flow review, and privacy/security sign-off. | Pending panel/UAT record |
| AC-10 | The submission build can be installed and the selected core tasks completed on the declared target devices and browsers. | Build/version manifest, installation record, and task-based device/browser test results. | Pending panel/UAT record |

The adviser/panel may require additional acceptance criteria. Any added criterion shall receive a unique identifier and be traced to its originating requirement, use case, and test evidence.

# Appendix C. Approval

The signatures below record academic review and approval of this requirements baseline. They do not certify legal compliance, emergency-service readiness, partner acceptance, field suitability, or production availability.

| Role | Name | Signature | Date |
| --- | --- | --- | --- |
| Project Manager / Proponent | Niña Kathleen B. Barro | ____________________ | __________ |
| Proponent | Vinzon Arellano | ____________________ | __________ |
| Proponent | Erece Joy Eria | ____________________ | __________ |
| Proponent | Vince Pacillan | ____________________ | __________ |
| Capstone Adviser | Mr. Roderick A. Bandalan | ____________________ | __________ |
| Panel Chair / Representative | ____________________ | ____________________ | __________ |
