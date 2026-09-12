# 13 - Rescuer Dispatching Flow

This diagram shows the dispatch lifecycle from HQ/Admin selection to rescuer completion.

```mermaid
sequenceDiagram
  autonumber
  actor Admin as HQ/Admin
  participant Web as HQ web app
  participant API as Laravel API
  participant DB as Active database
  participant Route as Routing service
  participant Mobile as Rescuer mobile app
  participant Map as HQ live map

  Admin->>Web: Opens Rescue Dispatch
  Web->>API: GET /api/v1/rescue-dispatch
  API->>DB: Read active event, teams, responders, household statuses
  DB-->>API: Dispatch-ready data
  API-->>Web: Purok summaries, available teams, active assignments

  Admin->>Web: Selects affected purok or GPS household
  Web->>API: POST /api/v1/rescue-dispatch/assignments
  API->>DB: Confirm active disaster event exists
  API->>DB: Confirm target household/purok belongs to active DB scope
  API->>DB: Check selected responders are available

  alt Responder already busy
    API-->>Web: Validation error: responder locked until completion
  else Valid assignment
    API->>DB: Save responder_assignment
    API->>DB: Mark selected responders dispatched or busy
    API->>Route: Build route to household/purok/evacuation target
    Route-->>API: Route coordinates if available
    API->>DB: Save responder_routes and route_coordinates
    API-->>Web: Assignment created
  end

  Mobile->>API: GET /api/v1/rescuer/assignments/current
  API->>DB: Read active assignment for responder
  API-->>Mobile: Assignment details and route
  Mobile->>API: POST accept assignment
  API->>DB: Mark accepted
  Mobile->>API: POST en route
  API->>DB: Mark en_route and save timestamp
  Mobile->>API: POST GPS updates while moving
  API->>DB: Save responder_location_logs
  Map->>API: GET /api/v1/mapping
  API->>DB: Read responder locations, route, target
  API-->>Map: Live responder marker and route
  Mobile->>API: POST complete assignment
  API->>DB: Mark assignment completed
  API->>DB: Release responder availability
```

## Dispatch rules

- Only available responders should be selectable.
- A responder with an active assignment cannot receive another assignment until completed.
- If the disaster event is closed, active assignments should be reset or closed and responders should return to normal availability.
- Purok selection should update the assigned area automatically.

