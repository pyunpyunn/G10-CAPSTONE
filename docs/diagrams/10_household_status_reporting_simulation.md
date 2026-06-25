# 10 - Household Status Reporting Simulation

This diagram shows the simulated end-to-end household status reporting process during an active disaster event.

```mermaid
sequenceDiagram
  autonumber
  actor Resident as Household resident
  participant Mobile as Household mobile app
  participant Permission as Device permission layer
  participant API as Laravel API
  participant DB as Active database
  participant HQ as HQ/Admin dashboard
  participant Dispatch as Rescue dispatch module

  Resident->>Mobile: Opens app
  Mobile->>API: GET /api/v1/disaster/active
  API->>DB: Read active disaster_event

  alt No active disaster
    DB-->>API: No active event
    API-->>Mobile: active_event = null
    Mobile-->>Resident: Show no current disaster and family info
  else Active disaster exists
    DB-->>API: Active event details
    API-->>Mobile: Event type, message, severity, declared_at
    Mobile-->>Resident: Show status choices
    Resident->>Mobile: Selects family/member status
    Mobile->>Resident: Requires Save before sending
    Resident->>Mobile: Taps Save
    Mobile->>Permission: Confirm location permission if location is enabled
    Permission-->>Mobile: Current GPS or permission unavailable
    Mobile->>API: POST /api/v1/household/status
    API->>DB: Insert household_status_logs
    API->>DB: Update household_disasters current status
    API->>DB: Update latest device/location metadata if provided
    DB-->>API: Saved
    API-->>Mobile: Latest saved status and timestamp
    Mobile-->>Resident: Show last saved status
    HQ->>API: GET /api/v1/household-status
    API->>DB: Read latest household status rows
    DB-->>API: Household status list
    API-->>HQ: Updated dashboard/table data

    alt Status is unsafe or needs rescue attention
      HQ->>Dispatch: Reviews affected purok/household
      Dispatch->>API: Create dispatch request if needed
      API->>DB: Save responder assignment
    else Status is safe or evacuated
      HQ->>HQ: Count in analytics only
    end
  end
```

## Data saved by the simulation

- `household_status_logs`: every submitted status change.
- `household_disasters`: latest household status for the active event.
- `geotagged_locations` or device tracking tables: location source when available.
- Notification/audit tables: only if the action creates an actionable follow-up.

