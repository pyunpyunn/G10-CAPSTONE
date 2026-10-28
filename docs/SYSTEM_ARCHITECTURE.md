# RESQPERATION — System Architecture

**Project Title:** RESQPERATION: Barangay Disaster Risk Reduction and Management Coordination System  
**Target Operational Area:** Barangay Mambaling, Cebu City  
**Document Version:** 1.0.0  
**Last Updated:** October 28, 2026  

---

## 1. High-Level System Architecture Diagram

```mermaid
flowchart TB
    %% -------------------------------------------------------------------------
    %% 1. CLIENT TIER
    %% -------------------------------------------------------------------------
    subgraph CLIENT_TIER["1. Client Tier (Web & Mobile Presentation)"]
        direction TB

        subgraph HQ_WEB["HQ Command Web Application (React 19 + Vite 8)"]
            WEB_DASH["Live Incident & Impact Dashboard"]
            WEB_MAP["Leaflet Map & Hazard Susceptibility Layers"]
            WEB_DISPATCH["Dispatch Management & Welfare Check Queue"]
            WEB_RESOURCES["Resource Requests & TrackingAid Handoff Drawer"]
            WEB_SITREP["6-Finding NDRRMC Situation Reports & PDF Export"]
        end

        subgraph FIELD_MOBILE["Field Mobile Application (Expo SDK 54 / React Native)"]
            MOB_RESIDENT["Household Resident Mode<br/>• Safety Status Check-in<br/>• Evacuation Routing & ETA<br/>• Family QR Digital ID<br/>• Trusted Household Network"]
            MOB_RESCUER["Field Responder Mode<br/>• Turn-by-Turn GPS Dispatch Routes<br/>• Offline Map Tile Packs<br/>• Triage Field Reports<br/>• Push-to-Talk Radio Feed"]
        end
    end

    %% -------------------------------------------------------------------------
    %% 2. API & GATEWAY TIER
    %% -------------------------------------------------------------------------
    subgraph API_TIER["2. Backend API & Gateway Tier (Laravel 11+ / PHP 8.3+)"]
        direction TB
        GATEWAY["API Gateway, Middleware & Guards<br/>(Laravel Sanctum · BDRRMC RBAC Access Policies · Rate Throttling)"]

        subgraph CORE_SERVICES["Core Operational Domain Services"]
            AUTH_SRV["Auth & Trusted Pin Service"]
            DISPATCH_SRV["Dispatch & Priority Routing Engine"]
            RESOURCE_SRV["Resource Request & Validation Workflow"]
            SITREP_SRV["NDRRMC SitRep Aggregation Engine"]
            ALERT_SRV["Multi-Channel Broadcast Outbox (SMS / Push)"]
            GEO_SRV["Geospatial & Road Geometry Service"]
        end

        GATEWAY --> AUTH_SRV
        GATEWAY --> DISPATCH_SRV
        GATEWAY --> RESOURCE_SRV
        GATEWAY --> SITREP_SRV
        GATEWAY --> ALERT_SRV
        GATEWAY --> GEO_SRV
    end

    %% -------------------------------------------------------------------------
    %% 3. DATA & STORAGE TIER
    %% -------------------------------------------------------------------------
    subgraph DATA_TIER["3. Data & Persistence Tier (MySQL Relational Engine)"]
        direction TB

        LOCAL_DB[("Local Operational Database (resq_local)<br/>• Households & Geotagged Locations<br/>• Evacuation Centers (Mambaling Sites)<br/>• Responders & Field Dispatch Trips<br/>• Resource Requests & Validation Logs<br/>• Situation Reports & Disaster Events")]

        SHARED_DB[("TrackingAid Shared Database (laravel / trackingaid_db)<br/>• resqperation_forwarded_requests<br/>• Cross-System Logistics Tracking<br/>• Shared Supply Depot Inventory")]
    end

    %% -------------------------------------------------------------------------
    %% 4. EXTERNAL PARTNERS & TELEMETRY
    %% -------------------------------------------------------------------------
    subgraph EXTERNAL_TIER["4. External Partners & Services Ecosystem"]
        direction TB

        subgraph PARTNER_SYSTEMS["G10 Partner Systems"]
            PARTNER_SAFETRACK["SafeTrack<br/>(Household Identity & Census)"]
            PARTNER_EVATRACK["EvaTrack<br/>(Evacuation Centers & Evacuee Intake)"]
            PARTNER_TRACKINGAID["TrackingAid<br/>(Depot Inventory & Dispatch Fulfillment)"]
        end

        subgraph CLOUD_SERVICES["External Cloud & Telemetry APIs"]
            METEO["Open-Meteo & PAGASA Feeds<br/>(Real-Time Weather & Advisory Snapshots)"]
            OSRM["OSRM Routing Engine<br/>(Road Coordinates & Evacuation Pathing)"]
            ONESIGNAL["OneSignal & Web Push<br/>(High-Priority Emergency Broadcasts)"]
            SMS_GATEWAY["Android SMS Gateway<br/>(Offline / Direct SMS Inbound & Outbound)"]
        end
    end

    %% Connections
    HQ_WEB -->|REST API / JSON| GATEWAY
    FIELD_MOBILE -->|REST API / Sync Tokens| GATEWAY

    CORE_SERVICES -->|Eloquent ORM / PDO| LOCAL_DB
    RESOURCE_SRV -->|Cross-DB PDO Query| SHARED_DB

    GATEWAY <-->|Authenticated JSON API| PARTNER_EVATRACK
    GATEWAY <-->|Cross-System Sync| PARTNER_SAFETRACK
    SHARED_DB <-->|Shared Table Sync| PARTNER_TRACKINGAID

    ALERT_SRV --> ONESIGNAL
    ALERT_SRV --> SMS_GATEWAY
    GEO_SRV --> OSRM
    CORE_SERVICES --> METEO
```

---

## 2. Architecture Layers & Component Specifications

### 2.1 Presentation & Client Tier
| Subsystem | Core Technologies | Scope & Key Functions |
| :--- | :--- | :--- |
| **HQ Command Web Application** | • React 19<br>• Vite 8<br>• React Router<br>• Leaflet / React-Leaflet<br>• Vanilla CSS Design System | • **Live Operational Dashboard:** Real-time incident counts, household safety counters, and weather snapshots.<br>• **Geospatial Mapping:** Interactive map of Barangay Mambaling sitios, hazard layers (flood/liquefaction), and active evacuation centers.<br>• **Dispatch Operations:** Turn-by-turn responder routing, welfare check prioritization, and field trip monitoring.<br>• **Logistics & Resource Requests:** Multi-channel intake, validation, stock verification, and TrackingAid handoff tracking.<br>• **Situation Reporting:** 6-Finding NDRRMC Situation Reports with automated PDF, CSV, and Excel exports. |
| **Field Mobile Application** | • Expo SDK 54<br>• React Native<br>• SQLite Local Storage<br>• Expo Location & NetInfo | • **Resident Mode:** Personal/family safety status reporting, evacuation center routing with distance and ETA calculation, digital household QR identification, and trusted household network.<br>• **Responder Mode:** Live GPS telemetry dispatch, road navigation, field report logging, offline map tile cache, and push-to-talk radio communications. |

### 2.2 Application Gateway & Domain Services Tier
| Module | Technology | Functional Architecture |
| :--- | :--- | :--- |
| **API Gateway & Security** | • Laravel 11+ / PHP 8.3+<br>• Laravel Sanctum<br>• Custom Middleware Pipeline | • Bearer token authentication for web sessions and mobile devices.<br>• Strict BDRRMC role-based access control (`operations_admin`, `chair`, `preparedness`, `response`, `recovery`, `rescuer`, `household_resident`).<br>• External intake authorization (`X-RESQPERATION-INTEGRATION-KEY`).<br>• Rate limiting and atomic mutation middleware. |
| **Core Domain Services** | • PHP 8.3 Typed Domain Workflows<br>• Eloquent ORM | • **RescueDispatchWorkflow:** Automated prioritization ranking for vulnerable households (infants, elderly, PWDs).<br>• **ResourceRequestService:** Request lifecycle management, stock limit guards, and TrackingAid shared DB handoff.<br>• **SituationReportSync:** Multi-source data synthesis across population, casualties, and infrastructure damages.<br>• **Broadcast Engine:** Multi-channel dispatch via Web Push, OneSignal mobile push, and cellular SMS gateway. |

### 2.3 Data Persistence Tier
| Database Store | Engine | Structure & Contents |
| :--- | :--- | :--- |
| **Local Operational Database (`resq_local`)** | MySQL 8.0 / InnoDB | • Complete operational dataset for Barangay Mambaling.<br>• Tables: `households`, `household_members`, `responders`, `dispatch_trips`, `evacuation_centers`, `resource_requests`, `request_validations`, `disaster_events`, `situation_reports`, `hazard_layers`. |
| **Shared Bridge Database (`trackingaid_db` / `laravel`)** | MySQL 8.0 (Cross-Host LAN) | • Dedicated cross-system integration table: `resqperation_forwarded_requests`.<br>• Links validated ResQperation relief requests to TrackingAid depot fulfillment records without code coupling. |

### 2.4 External Integration Interfaces
| External Entity | Integration Pattern | Protocol / Payload |
| :--- | :--- | :--- |
| **EvaTrack** | Authenticated REST API | HTTP `POST /api/v1/external/resource-requests` with JSON payload for camp relief intake. |
| **SafeTrack** | Data Synchronization | Shared census and household identity baselines. |
| **TrackingAid** | Relational Bridge Table | Cross-database queries on `resqperation_forwarded_requests` using unique tracking references (`TA-YYYYMMDD-XXX`). |
| **Open-Meteo & DOST-PAGASA** | REST API & Feeds | Weather forecasts, storm warnings, and seismological advisory snapshots. |
| **OSRM** | Public Routing Engine | On-demand road network geometry and routing coordinates for evacuation pathing. |
| **OneSignal & Android SMS Gateway** | Push Notification & Cellular Webhook | Automated priority alerts to mobile devices and SMS fallback for offline residents. |
