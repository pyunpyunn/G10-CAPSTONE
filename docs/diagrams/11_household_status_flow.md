# 11 - Household Status Flow

This diagram focuses on the household mobile user journey from login to status reporting.

```mermaid
flowchart TD
  Start([Start])
  Login["Household logs in"]
  Auth{"Valid household account?"}
  Setup{"First-time setup complete?"}
  Permission["Request location permission"]
  Pin["Open map and pin household location"]
  Address["Auto-fill detected address"]
  Unit["Add house number, room, floor, building, or unit details"]
  Member["Select household member using this device"]
  SaveSetup["Save geotag, address, member, relationship, and device link"]
  Active{"Active disaster event?"}
  Standby["Show no current disaster"]
  Family["Show family members, device status, battery, location state"]
  Disaster["Show disaster information"]
  SelectStatus["Select status: safe, evacuated, unsafe, or needs help"]
  EditMode{"Editing previous status?"}
  SaveStatus["Save status update"]
  History["Show latest status and status history"]
  HQ["HQ/Admin sees latest household row"]
  Dispatch{"Needs dispatch?"}
  DispatchQueue["Household appears in dispatch focus"]
  End([End])

  Start --> Login --> Auth
  Auth -- No --> Login
  Auth -- Yes --> Setup
  Setup -- No --> Permission --> Pin --> Address --> Unit --> Member --> SaveSetup --> Active
  Setup -- Yes --> Active
  Active -- No --> Standby --> Family --> End
  Active -- Yes --> Disaster --> SelectStatus --> EditMode
  EditMode -- Yes --> SaveStatus
  EditMode -- No --> SaveStatus
  SaveStatus --> History --> HQ --> Dispatch
  Dispatch -- Yes --> DispatchQueue --> End
  Dispatch -- No --> End
```

## Required privacy behavior

- Location setup must ask permission before collecting GPS data.
- The address must support high-density housing such as apartments, floors, rooms, and rented units.
- Rescuers should only see the address details needed for rescue routing and identification.
- Household GPS and address data must not be shown to unauthorized roles.

