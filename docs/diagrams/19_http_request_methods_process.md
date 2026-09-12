# 19 - HTTP Request Methods Process

This file separates the request method flow for Household Mobile, Rescuer Mobile, and HQ/Admin Web.

## 19.1 Household Mobile HTTP Flow

```mermaid
sequenceDiagram
  autonumber
  participant HH as Household mobile
  participant API as Laravel API
  participant DB as Database

  HH->>API: POST /api/v1/auth/login
  API->>DB: Validate household credentials
  API-->>HH: 200 token + household profile
  HH->>API: GET /api/v1/disaster/active
  API->>DB: Read active disaster
  API-->>HH: 200 active event or null
  HH->>API: POST /api/v1/household/setup/location
  API->>DB: Save geotag and address
  API-->>HH: 201 setup saved
  HH->>API: GET /api/v1/household/me
  API->>DB: Read household, members, devices
  API-->>HH: 200 household data
  HH->>API: POST /api/v1/household/status
  API->>DB: Save household status log
  API-->>HH: 201 latest status
  HH->>API: PATCH /api/v1/household/members/{id}
  API->>DB: Update member details/status
  API-->>HH: 200 updated member
```

## 19.2 Rescuer Mobile HTTP Flow

```mermaid
sequenceDiagram
  autonumber
  participant R as Rescuer mobile
  participant API as Laravel API
  participant DB as Database
  participant Storage as Storage

  R->>API: POST /api/v1/auth/login
  API->>DB: Validate rescuer credentials
  API-->>R: 200 token + responder profile
  R->>API: GET /api/v1/rescuer/assignments/current
  API->>DB: Read active assignment
  API-->>R: 200 assignment or null
  R->>API: POST /api/v1/rescuer/assignments/{id}/accept
  API->>DB: Mark assignment accepted
  API-->>R: 200 accepted
  R->>API: POST /api/v1/rescuer/location
  API->>DB: Save responder GPS
  API-->>R: 201 location saved
  R->>API: POST /api/v1/rescuer/resource-requests
  API->>DB: Save request for HQ validation
  API-->>R: 201 request created
  R->>API: POST /api/v1/rescuer/radio
  API->>Storage: Store voice clip
  API->>DB: Save responder_communication_log
  API-->>R: 201 radio log
```

## 19.3 HQ/Admin Web HTTP Flow

```mermaid
sequenceDiagram
  autonumber
  participant Web as HQ web
  participant API as Laravel API
  participant DB as Database

  Web->>API: POST /api/v1/auth/login
  API->>DB: Validate HQ/Admin credentials and role
  API-->>Web: 200 token + admin profile
  Web->>API: GET /api/v1/dashboard
  API->>DB: Read active event and summaries
  API-->>Web: 200 dashboard data
  Web->>API: POST /api/v1/disaster-broadcasts
  API->>DB: Save event/broadcast
  API-->>Web: 201 broadcast saved
  Web->>API: GET /api/v1/household-status
  API->>DB: Read latest status rows
  API-->>Web: 200 status table
  Web->>API: POST /api/v1/rescue-dispatch/assignments
  API->>DB: Save dispatch assignment
  API-->>Web: 201 assignment saved
  Web->>API: PATCH /api/v1/resource-requests/{id}/validation
  API->>DB: Save validation/return/forward state
  API-->>Web: 200 request updated
  Web->>API: DELETE /api/v1/archive/{type}/{id}
  API->>DB: Delete authorized archive record
  API-->>Web: 200 deleted
```

