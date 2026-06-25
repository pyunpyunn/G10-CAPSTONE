# 01 - System Context and External Integrations

## 1.1 Overall System Context

This diagram shows RESQPERATION as the barangay response operations hub. The system receives and displays data from the active database connection, reads household identity from SafeTrack-related records, receives or validates requests from EvaTrack/rescuer sources, and prepares handoff records for TrackingAid/MappingAid when their integration is ready.

```mermaid
flowchart LR
  subgraph People["Primary Users"]
    Admin["HQ/Admin Web User"]
    HouseholdUser["Household Resident"]
    RescuerUser["Rescuer / Field Responder"]
    EvacUser["Evacuation Personnel"]
  end

  subgraph Frontends["RESQPERATION Frontends"]
    Web["React Web App\nHQ/Admin only\nVite port 5175"]
    Mobile["Expo Mobile App\nHousehold and Rescuer roles"]
  end

  subgraph Backend["RESQPERATION Backend"]
    API["Laravel API\nroutes/api.php\n/api/v1/*"]
    Auth["AuthService\nSanctum token login\nrole detection"]
    Services["Module Services\nDashboard, Broadcast, Weather,\nMapping, Household, Dispatch,\nRescuers, Resources, SitRep, Archive"]
    Security["Security Controls\nValidation, role middleware,\nthrottling, security headers"]
  end

  subgraph DataLayer["Active Database Connection"]
    DB[("Shared MySQL schema from .env")]
    Tokens[("personal_access_tokens")]
    Logs[("audit_logs / notifications /\narchive and operation logs")]
  end

  subgraph External["External Sources and Systems"]
    SafeTrack["SafeTrack\nhousehold accounts and barangay identity"]
    EvaTrack["EvaTrack\nevacuation/personnel/resource requests"]
    TrackingAid["TrackingAid / MappingAid\nfuture validated request handoff"]
    OpenMeteo["Open-Meteo\nweather snapshots"]
    PAGASA["PAGASA\nofficial advisory confirmation links/API later"]
    MapTiles["OpenStreetMap / Leaflet tiles\nmap display and routing base"]
  end

  Admin --> Web
  HouseholdUser --> Mobile
  RescuerUser --> Mobile
  EvacUser --> EvaTrack

  Web --> API
  Mobile --> API
  API --> Auth
  API --> Services
  API --> Security

  Auth --> DB
  Auth --> Tokens
  Services --> DB
  Services --> Logs

  DB <--> SafeTrack
  EvaTrack --> API
  API --> TrackingAid
  Services --> OpenMeteo
  Services --> PAGASA
  Web --> MapTiles
  Mobile --> MapTiles
```

## 1.2 External Integration Responsibilities

```mermaid
flowchart TD
  Start["External data or action starts"]

  SafeTrackSource["SafeTrack household or barangay data exists in shared DB"]
  SafeTrackUse["RESQPERATION reads registered household identity\nand validates household login"]
  SafeTrackNoTouch["RESQPERATION does not own SafeTrack account creation"]

  EvaTrackSource["EvaTrack or evacuation personnel creates a request"]
  RequestIntake["RESQPERATION receives request into resource request queue"]
  HQReview["HQ/Admin reviews request\nchecks identity, quantity, urgency, area"]
  Decision{"Validation decision"}
  Approved["Approved / verified for handoff"]
  Returned["Returned for missing details or duplicate"]
  Rejected["Rejected / invalid"]

  TrackingPending["TrackingAid / MappingAid integration pending"]
  HandoffDraft["Validated request is prepared for future handoff\nthrough DB/API details to be provided later"]

  WeatherSource["Open-Meteo weather snapshot fetched"]
  WeatherSaved["Snapshot saved in weather_logs"]
  WeatherShown["Weather page and dashboard display concise weather info"]
  PagasaConfirm["HQ/Admin confirms official warnings through PAGASA\nbefore broadcasting critical alerts"]

  Start --> SafeTrackSource --> SafeTrackUse --> SafeTrackNoTouch
  Start --> EvaTrackSource --> RequestIntake --> HQReview --> Decision
  Decision --> Approved --> HandoffDraft --> TrackingPending
  Decision --> Returned
  Decision --> Rejected
  Start --> WeatherSource --> WeatherSaved --> WeatherShown --> PagasaConfirm
```

## 1.3 Development and Runtime Deployment View

```mermaid
flowchart TD
  subgraph DevMachine["Developer Laptop / Local Network"]
    Browser["Browser\nhttp://127.0.0.1:5175/login"]
    ExpoGo["Expo Go iOS/Android\nuses LAN API URL"]
    Vite["Vite frontend-web server\nport 5175"]
    Laravel["Laravel backend server\nport 8000"]
    Env["backend-laravel/.env\nDB_HOST, DB_DATABASE, credentials"]
  end

  subgraph DatabaseChoices["Database Connection"]
    SharedDB[("Shared MySQL\nexample: klint on teammate laptop")]
  end

  Browser --> Vite
  Vite --> Laravel
  ExpoGo --> Laravel
  Laravel --> Env
  Env --> SharedDB

  Note1["Rule: shared DB connection must only require editing .env values.\nApplication code must read the shared schema."]
  Env --> Note1
```

## 1.4 Integration Status Summary

| Integration | Current Purpose | Ownership | Current Status |
| --- | --- | --- | --- |
| SafeTrack | Household and barangay identity source | External/shared DB | RESQPERATION reads available data |
| EvaTrack | Source of evacuation/resource requests | External system | Draft/partial request intake supported |
| TrackingAid / MappingAid | Future destination for validated requests | External system | Pending final DB/API details |
| Open-Meteo | Automated weather snapshots | Public API | Implemented as non-official weather source |
| PAGASA | Official warning confirmation | Government source | Links available; API token pending |
| Map tiles/routing | Visual map, route context | Public map resources / DB routes | Used by web/mobile mapping features |
