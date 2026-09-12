# 05 - Mobile Workflows: Household, Rescuer, and Radio

## 5.1 Household Mobile First-Time Setup

```mermaid
flowchart TD
  Login["Household logs in with shared DB credentials"]
  SetupCheck{"Setup complete?"}
  Home["Open household home"]
  RequireLocation["Request location permission\nunskippable for first setup"]
  MapPin["Open map\nuser pins household location"]
  ReverseGeo["Auto-fill detected address from map coordinate"]
  AddressForm["User enters house/unit/street number"]
  MemberSelect["Select which family member uses this device"]
  Relationship["Set relationship to family"]
  Photo["Upload photo or skip for now"]
  SaveSetup["POST /api/v1/household/setup"]
  DeviceLog["POST /api/v1/household/device-location"]
  DB[("households, household_members,\ngeotagged_locations, device_tracking_logs")]

  Login --> SetupCheck
  SetupCheck -- Yes --> Home
  SetupCheck -- No --> RequireLocation --> MapPin --> ReverseGeo --> AddressForm --> MemberSelect --> Relationship --> Photo --> SaveSetup --> DB --> Home
  Home --> DeviceLog --> DB
```

## 5.2 Household Home: No Active Disaster

```mermaid
flowchart TD
  Overview["GET /api/v1/household/overview"]
  ActiveCheck{"Active disaster?"}
  NoDisaster["Display: No current disaster"]
  Family["Family members section"]
  MemberCard["Member card\nname, relationship, device status,\nbattery if device has real value,\nlocation sharing, active/inactive"]
  NoDevice["If no device: No device registered"]
  TrustedPage["Trusted households page\nseparate from home"]
  QR["QR for evacuation update"]
  Profile["Profile page\nedit profile, logout"]

  Overview --> ActiveCheck
  ActiveCheck -- No --> NoDisaster
  NoDisaster --> Family --> MemberCard
  MemberCard --> NoDevice
  NoDisaster --> QR
  NoDisaster --> TrustedPage
  NoDisaster --> Profile
```

## 5.3 Household Home: Active Disaster Status Update

```mermaid
stateDiagram-v2
  [*] --> ActiveDisasterLoaded
  ActiveDisasterLoaded: Show disaster type, date, message, additional info
  ActiveDisasterLoaded --> NoStatusSelected

  NoStatusSelected: Status buttons enabled
  NoStatusSelected --> SelectedSafe: tap Safe
  NoStatusSelected --> SelectedEvacuated: tap Evacuated
  NoStatusSelected --> SelectedUnsafe: tap Unsafe

  SelectedSafe --> SavePending
  SelectedEvacuated --> SavePending
  SelectedUnsafe --> SavePending

  SavePending: Save button visible
  SavePending --> Saved: POST /household/status or /members/{id}/status

  Saved: Last saved status visible
  Saved: Status buttons gray/locked
  Saved --> Editing: tap Edit
  Editing: Previous saved value preselected
  Editing --> SavePending: choose new status and save

  Saved --> History: tap history icon
  History: GET /household/status-history
  History --> Saved
```

## 5.4 Trusted Household Flow

```mermaid
flowchart TD
  TrustedTab["Open Trusted Households page"]
  PinCheck{"Trusted PIN already set?"}
  SetPin["Create 4-digit PIN\nstored locally on device"]
  List["List trusted household cards"]
  Add["Add trusted household"]
  InputHH["Input household ID"]
  Lookup["GET /household/trusted-households/lookup/{id}"]
  Preview["Show household family name and household ID"]
  Reason["Input reason for connection"]
  Submit["POST /household/trusted-households"]
  Pending["Request saved as pending until validated/accepted"]
  OpenCard["Tap trusted household card"]
  VerifyPin["Enter 4-digit PIN"]
  ShowMembers["Show trusted household members and status interface"]

  TrustedTab --> PinCheck
  PinCheck -- No --> SetPin --> List
  PinCheck -- Yes --> List
  List --> Add --> InputHH --> Lookup --> Preview --> Reason --> Submit --> Pending --> List
  List --> OpenCard --> VerifyPin --> ShowMembers
```

## 5.5 Rescuer Mobile Assignment Flow

```mermaid
stateDiagram-v2
  [*] --> Standby
  Standby: No active assignment
  Standby: Can receive dispatch

  Standby --> Assigned: HQ/Admin dispatches responder/team
  Assigned: Assignment appears on home
  Assigned: Buttons shown: Accept, Complete

  Assigned --> Accepted: tap Accept
  Accepted: system records accepted_at
  Accepted --> EnRoute: tap En route
  EnRoute: route/map becomes primary
  EnRoute: GPS trail starts saving

  EnRoute --> OnScene: system detects arrival or responder confirms near target
  OnScene: active on-scene status
  OnScene --> Completed: tap Complete
  Completed: assignment completed_at saved
  Completed: responder becomes available again
  Completed --> Standby
```

## 5.6 Rescuer Mobile Data Flow

```mermaid
sequenceDiagram
  actor Rescuer
  participant Mobile as Rescuer Mobile
  participant API as Laravel API
  participant Service as RescuerMobileService / RescueDispatchService
  participant DB as Active DB
  participant HQ as HQ/Admin Web

  Rescuer->>Mobile: Opens field console
  Mobile->>API: GET /api/v1/rescuer/overview
  API->>Service: Load profile, assignment, team, event, stats
  Service->>DB: Read responder, team, active assignment
  Service-->>Mobile: Current assignment and status

  Rescuer->>Mobile: Accept / En route / Complete
  Mobile->>API: PATCH /rescuer/assignments/{id}/status
  API->>Service: Validate transition
  Service->>DB: Save assignment state
  Service-->>Mobile: Updated assignment state

  Mobile->>API: POST /rescuer/assignments/{id}/location
  API->>Service: Validate assignment ownership
  Service->>DB: Save GPS trail
  HQ->>API: GET /map/overview
  API->>DB: Read latest route/location
  API-->>HQ: Responder marker and route
```

## 5.7 Rescuer Radio / Push-to-Talk Clip Flow

```mermaid
flowchart TD
  Team["Responder belongs to rescue team"]
  RadioPage["Radio communication page"]
  Members["Horizontal team member avatars"]
  Record["Tap telephone/PTT button once to start recording"]
  Stop["Tap again to stop recording"]
  Upload["POST /api/v1/rescuer/radio/clip\nvoice clip upload"]
  Logs[("responder_communication_logs")]
  Feed["GET /api/v1/rescuer/radio\npaginated feed"]
  Indicator["Team avatars show unread clip count\nactive speaker indicator while transmitting"]
  Play["Tap avatar\nplay clips one by one"]
  Decrement["Unread count decreases after clip is heard"]
  Archive["When disaster closes,\nradio logs appear in Archive radio tab"]

  Team --> RadioPage --> Members
  RadioPage --> Record --> Stop --> Upload --> Logs
  Logs --> Feed --> Indicator --> Play --> Decrement
  Logs --> Archive
```

