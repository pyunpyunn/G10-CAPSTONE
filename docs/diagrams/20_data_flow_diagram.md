# 20 - Data Flow Diagram

```mermaid
flowchart TD
  HH["Household mobile user"]
  R["Rescuer mobile user"]
  A["HQ/Admin web user"]
  SafeTrack["SafeTrack"]
  EvaTrack["EvaTrack"]
  TrackingAid["TrackingAid"]
  Weather["PAGASA links / Open-Meteo"]

  subgraph Processes["RESQPERATION processes"]
    P1["P1 Authentication and role routing"]
    P2["P2 Household setup and status reporting"]
    P3["P3 Disaster broadcasting"]
    P4["P4 Rescue dispatch and routing"]
    P5["P5 Resource request validation"]
    P6["P6 Weather snapshot handling"]
    P7["P7 Situation reporting"]
    P8["P8 Archive and exports"]
    P9["P9 Radio communication logs"]
    P10["P10 Notifications"]
  end

  subgraph Stores["Data stores"]
    D1[("users, roles, tokens")]
    D2[("households, members, geotags")]
    D3[("disaster events, broadcasts")]
    D4[("household status logs")]
    D5[("responders, teams, assignments, routes")]
    D6[("resource requests, validations")]
    D7[("weather logs")]
    D8[("situation reports, incident archives")]
    D9[("radio logs and voice file references")]
    D10[("notifications and recipients")]
  end

  SafeTrack -->|registered household accounts| P1
  HH -->|login, setup, status, device data| P1
  R -->|login, GPS, assignment, field reports| P1
  A -->|login and admin actions| P1
  P1 --> D1

  HH --> P2 --> D2
  P2 --> D4
  A --> P3 --> D3
  Weather --> P6 --> D7
  A --> P4 --> D5
  R --> P4 --> D5
  EvaTrack --> P5
  R --> P5
  P5 --> D6
  P5 -->|validated handoff| TrackingAid
  A --> P7 --> D8
  A --> P8 --> D8
  R --> P9 --> D9
  P3 --> P10 --> D10
  P4 --> P10 --> D10
  P5 --> P10 --> D10

  D2 --> A
  D3 --> A
  D4 --> A
  D5 --> A
  D6 --> A
  D7 --> A
  D8 --> A
  D9 --> A
  D10 --> HH
  D10 --> R
  D10 --> A
```

## Data flow constraints

- Household records originate from the active database connection and SafeTrack-shared account data.
- EvaTrack and TrackingAid are integration partners, not owned internal modules.
- Weather snapshots are operational references; official warnings still require PAGASA confirmation.
- Archive/export operations must respect role access.

