# 04 - Disaster, Household Status, Dispatch, and Mapping

## 4.1 Disaster Event Lifecycle

```mermaid
stateDiagram-v2
  [*] --> NormalMode
  NormalMode: No active disaster
  NormalMode: Dashboard shows normal overview
  NormalMode: Household status is historical/standby
  NormalMode: Dispatch teams are available if no active assignment

  NormalMode --> Declared: HQ/Admin creates disaster event
  Declared: disaster_events row saved
  Declared: event status active/open

  Declared --> Broadcasted: HQ/Admin sends broadcast
  Broadcasted: disaster_broadcasts row saved
  Broadcasted: household/rescuer notifications can be created

  Broadcasted --> Monitoring: Household and responder reports arrive
  Monitoring: household_disasters or household_status_logs updated
  Monitoring: weather_logs saved
  Monitoring: mapping reads geotags and statuses

  Monitoring --> Dispatching: HQ/Admin creates dispatch
  Dispatching: responder_assignments saved
  Dispatching: responder status becomes busy/en route/on-scene
  Dispatching: route/location logs are saved

  Dispatching --> Monitoring: Assignment completed
  Monitoring --> Reporting: HQ/Admin generates SitRep
  Reporting: situation_reports saved
  Reporting --> Monitoring

  Monitoring --> Closing: HQ/Admin closes event
  Dispatching --> Closing: HQ/Admin closes event after operations are complete

  Closing: event ended_at saved
  Closing: current_event_id nullified
  Closing: active responder references cleared
  Closing: incident archive summary written

  Closing --> Archived
  Archived: event appears in Archive
  Archived: new event can start cleanly
  Archived --> NormalMode
```

## 4.2 Household Status Data Flow

```mermaid
flowchart TD
  EventCheck{"Active disaster event exists?"}
  NoEvent["No event\nHousehold Status page shows standby data only\nNo false active disaster counts"]
  Event["Active event"]

  HHMobile["Household mobile user"]
  RescuerMobile["Rescuer mobile user"]
  HQReview["HQ/Admin web review"]

  HHStatus["Household submits status\nsafe / evacuated / unsafe"]
  MemberStatus["Household edits family member status\nmember-level context"]
  FieldReport["Responder submits field report or status observation"]

  Logs[("household_status_logs")]
  Current[("household_disasters\nlatest per event/household")]
  Household[("households\nfamily/address/purok")]
  Members[("household_members")]
  Geo[("geotagged_locations")]

  Table["Household Status page\n10 rows/page\nreview, history, dispatch-to-purok when purok filter selected"]
  Detail["Household detail modal\nfamily members + linked devices + status history"]
  DispatchShortcut["Dispatch team to selected purok"]

  EventCheck -- No --> NoEvent
  EventCheck -- Yes --> Event
  Event --> HHMobile --> HHStatus --> Logs
  Event --> HHMobile --> MemberStatus --> Members
  Event --> RescuerMobile --> FieldReport --> Logs
  Logs --> Current
  Household --> Table
  Members --> Detail
  Current --> Table
  Logs --> Detail
  Geo --> Table
  HQReview --> Table --> Detail
  Table --> DispatchShortcut
```

## 4.3 Rescue Dispatch Workflow

```mermaid
sequenceDiagram
  participant Admin as HQ/Admin Web
  participant API as RescueDispatchController
  participant Service as RescueDispatchService
  participant DB as Active DB
  participant Rescuer as Rescuer Mobile
  participant Map as Mapping/Routing View

  Admin->>API: GET /api/v1/dispatches
  API->>Service: Build dispatch dashboard
  Service->>DB: Load active event, teams, responders, risk areas, assignments
  DB-->>Service: Available responders and affected areas
  Service-->>Admin: Team cards, availability, risk areas, dispatch log

  Admin->>API: POST /api/v1/dispatches
  API->>Service: Validate selected purok/team/responders/priority
  Service->>DB: Confirm active disaster event exists
  Service->>DB: Confirm selected responders belong to team
  Service->>DB: Confirm responders have no active assignment
  Service->>DB: Confirm household target is not already actively assigned
  Service->>DB: Create responder_assignment transaction
  Service->>DB: Mark selected responders busy/dispatched
  DB-->>Service: Assignment saved
  Service-->>Admin: New dispatch saved

  Rescuer->>API: GET /api/v1/rescuer/assignments
  API->>Service: Load assignments for authenticated responder
  Service->>DB: Read current responder assignments
  Service-->>Rescuer: Current assignment card

  Rescuer->>API: PATCH /api/v1/rescuer/assignments/{id}/status
  API->>Service: Accept / en route / complete status update
  Service->>DB: Save assignment status and timestamps
  Service->>DB: Update responder duty status
  Service-->>Rescuer: Updated assignment

  Rescuer->>API: POST /api/v1/rescuer/assignments/{id}/location
  API->>Service: Store GPS point
  Service->>DB: Save responder_location_logs or route coordinate
  Admin->>Map: Open mapping page
  Map->>API: GET /api/v1/map/overview
  API->>Service: Load responder route and live point
  Service->>DB: Read responder routes/location logs
  Service-->>Map: Route polyline and responder marker
```

## 4.4 Mapping Layer and Routing Flow

```mermaid
flowchart TD
  MapPage["HQ Mapping page"]
  LayerControls["Layer controls\nhousehold GPS, evacuation pins,\nrescue teams, dispatch routes"]
  Fullscreen["Fullscreen map mode\nmap fits below header, exit/back button visible"]
  Legend["Legend\nright/bottom, not blocking zoom controls"]

  API["/api/v1/map/overview"]
  HHGeo[("geotagged_locations\nhousehold GPS only")]
  HHStatus[("household_disasters/status logs\nactive-event colors only")]
  EvacSites[("evacuation_centers/sites")]
  Responders[("responders + responder_location_logs")]
  Assignments[("responder_assignments")]
  Routes[("responder_routes + route_coordinates")]

  PlainMap["No active disaster\nplain barangay map focus only"]
  ActiveMap["Active disaster\nstatus colors and rescue routes enabled"]

  MapPage --> API
  MapPage --> LayerControls
  MapPage --> Fullscreen
  MapPage --> Legend
  API --> HHGeo
  API --> HHStatus
  API --> EvacSites
  API --> Responders
  API --> Assignments
  API --> Routes

  HHGeo --> ActiveMap
  HHStatus --> ActiveMap
  EvacSites --> ActiveMap
  Responders --> ActiveMap
  Assignments --> ActiveMap
  Routes --> ActiveMap

  API --> PlainMap
  PlainMap -->|when no active event| MapPage
  ActiveMap -->|when active event exists| MapPage
```

## 4.5 Map Marker Meaning

| Marker/Layer | Source | Display Rule |
| --- | --- | --- |
| Plain barangay map | Map tiles + barangay profile | Always visible |
| Household GPS markers | `geotagged_locations` linked to households | Only if household has saved GPS |
| Safe marker | Latest active-event household status | Green/safe tone |
| Evacuated marker | Latest active-event household status | Blue/evacuated tone |
| Unsafe marker | Latest active-event household status | Red/urgent tone |
| Unchecked marker | Household in scope without current report | Neutral tone |
| Rescue team marker | Responder location logs | Shows active responder/team movement |
| Dispatch route | Route/coordinate tables or route service result | Drawn as polyline with direction hints |
| Evacuation pin | Evacuation center/site tables | Route target and reference point |

