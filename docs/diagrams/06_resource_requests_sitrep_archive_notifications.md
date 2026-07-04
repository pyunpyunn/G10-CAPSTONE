# 06 - Resource Requests, Situation Reports, Archive, and Notifications

## 6.1 Resource Request Validation Flow

```mermaid
stateDiagram-v2
  [*] --> NewRequest
  NewRequest: Source can be HQ desk, rescuer mobile, shared DB, EvaTrack
  NewRequest --> NeedsValidation

  NeedsValidation: HQ/Admin reviews identity, need, quantity, area, urgency
  NeedsValidation --> Validated: Save validation as approved/verified
  NeedsValidation --> Returned: Return request for missing info or duplicate
  NeedsValidation --> Rejected: Reject invalid request

  Returned: Request is not editable as a normal validation record
  Returned: Shows return reason and missing/duplicate reference
  Returned --> NeedsValidation: Resubmitted later with corrected information

  Validated --> Forwarded: Forward to TrackingAid handoff table
  Forwarded: TrackingAid owns release, delivery, fulfillment after handoff

  Rejected --> ArchivedReview
  Forwarded --> ArchivedReview
  ArchivedReview: Appears in archive/resource logs
```

## 6.2 Resource Request System Sequence

```mermaid
sequenceDiagram
  participant Source as Request Source
  participant API as ResourceRequestController
  participant Service as ResourceRequestService
  participant DB as Active DB
  participant Admin as HQ/Admin Web
  participant Tracking as TrackingAid DB

  Source->>API: POST /api/v1/resource-requests
  API->>Service: Validate required fields
  Service->>DB: Save resource_requests row
  Service->>DB: Optional request_validations pending record
  DB-->>Service: Request ID
  Service-->>Source: Request saved

  Admin->>API: GET /api/v1/resource-requests
  API->>Service: Load paginated validation queue
  Service->>DB: Read requests, status, source, validation, handoff state
  Service-->>Admin: Queue rows

  Admin->>API: POST /resource-requests/{id}/validate
  API->>Service: Save validation decision
  Service->>DB: Update validation status and notes

  alt Forwardable request
    Admin->>API: POST /resource-requests/{id}/forward
    API->>Service: Create handoff reference
    Service->>Tracking: Upsert into resqperation_forwarded_requests
    Tracking-->>Service: Handoff row saved
    Service->>DB: Mark request forwarded with tracking reference
  else Returned request
    Admin->>API: POST /resource-requests/{id}/return
    API->>Service: Require return reason
    Service->>DB: Save returned status and notes
  end
```

## 6.3 Situation Reporting Flow

```mermaid
flowchart TD
  SitrepPage["Situation Reporting page"]
  SelectEvent["Select disaster event"]
  EventSummary["GET /disaster-events/{eventId}/situation-summary"]

  Household["Household summary\nsafe, evacuated, unsafe, unchecked,\npurok rows"]
  Weather["Weather summary\nlatest weather log for event"]
  Evac["Evacuation summary\ncenters and occupancy if available"]
  Dispatch["Dispatch summary\nassignments, outcomes, coverage"]
  Resources["Resource summary\nvalidated/forwarded/returned requests"]
  Timeline["Timeline rows\nbroadcasts, status, dispatch, reports"]
  Recommendations["Action recommendations\nbased on unresolved risks"]

  Generate["POST /situation-reports"]
  Saved[("situation_reports")]
  PDF["GET /situation-reports/{id}/pdf"]
  Archive["Archive situation reporting tab"]

  SitrepPage --> SelectEvent --> EventSummary
  EventSummary --> Household
  EventSummary --> Weather
  EventSummary --> Evac
  EventSummary --> Dispatch
  EventSummary --> Resources
  EventSummary --> Timeline
  EventSummary --> Recommendations
  Household --> Generate
  Weather --> Generate
  Dispatch --> Generate
  Resources --> Generate
  Generate --> Saved
  Saved --> PDF
  Saved --> Archive
```

## 6.4 Archive and Saved Groups Flow

```mermaid
flowchart TD
  ArchivePage["Archive page"]
  Tabs["Archive tabs\nDisaster Event, Household Status Logs,\nRescue Dispatch Logs, Radio Logs,\nResources and Requests, Situation Reporting"]
  Load["GET /api/v1/archive/{category}\n6 records/page"]
  Table["Display archive table grouped by date where applicable"]
  Select["Select individual logs or select page/date"]
  Actions{"Selected logs action"}
  SaveGroup["Save group\nPOST /archive/saved-groups"]
  DeleteForever["Delete forever\nPOST /archive/delete-selected\nrequires validation/selected IDs"]
  SavedGroups["Go to saved groups\nGET /archive/saved-groups\nmodal, clickable anytime"]
  Modal["Saved groups modal\nview full records, delete group, delete record"]
  Export["Download/export\nGET /archive/export"]
  DB[("incident_archives and source module tables")]

  ArchivePage --> Tabs --> Load --> Table
  Table --> Select --> Actions
  Actions --> SaveGroup --> DB
  Actions --> DeleteForever --> DB
  ArchivePage --> SavedGroups --> Modal --> DB
  ArchivePage --> Export
```

## 6.5 Notification Flow

```mermaid
flowchart TD
  Trigger["Actionable event occurs"]
  Types["Examples:\nnew resource request,\nunsafe household report,\nnew dispatch assignment,\nreturned request,\nactive disaster broadcast"]
  Service["NotificationService"]
  Notifications[("notifications")]
  Recipients[("notification_recipients")]
  Topbar["Topbar bell\nred unread count"]
  Panel["Notification panel/dropdown"]
  Click["Click notification"]
  Route{"Action target"}

  Dashboard["Dashboard"]
  HouseholdStatus["Household Status"]
  Dispatch["Rescue Dispatch"]
  Resources["Resources and Requests"]
  Archive["Archive"]
  NotificationsPage["Notifications page"]

  Trigger --> Types --> Service
  Service --> Notifications
  Service --> Recipients
  Notifications --> Topbar --> Panel --> Click --> Route
  Route --> Dashboard
  Route --> HouseholdStatus
  Route --> Dispatch
  Route --> Resources
  Route --> Archive
  Route --> NotificationsPage
```

## 6.6 Archive Category Sources

| Archive Tab | Main Source Tables | Why It Matters |
| --- | --- | --- |
| Disaster Event | `disaster_events`, `disaster_broadcasts`, `weather_logs`, `household_disasters` | Event summary and closure history |
| Household Status Logs | `household_status_logs`, `household_disasters`, `households` | Household safety audit trail |
| Rescue Dispatch Logs | `responder_assignments`, `responders`, `rescue_teams`, route/location logs | Response operations audit |
| Radio Logs | `responder_communication_logs` | Team coordination history during event |
| Resources and Requests | `resource_requests`, `request_validations`, handoff fields | Validation and external handoff proof |
| Situation Reporting | `situation_reports` | Official generated situation summaries |

