# 12 - Household Analytics Formula

This diagram documents how the household status dashboard counts and percentages should be computed.

```mermaid
flowchart TD
  Scope["Active disaster household scope"]
  Total["total_households = households included in current barangay/event scope"]
  Logs["latest_status_logs = latest valid status per household for active event"]
  Reported["reported = count(latest_status_logs)"]
  Safe["safe_total = count(status = safe)"]
  Evac["evacuated_total = count(status = evacuated)"]
  Unsafe["unsafe_total = count(status = unsafe or needs_help)"]
  SafeEvac["safe_or_evacuated = safe_total + evacuated_total"]
  Unchecked["unchecked = total_households - reported"]
  Response["response_rate = reported / total_households * 100"]
  UnsafeRate["unsafe_rate = unsafe_total / total_households * 100"]
  Geotag["geotag_coverage = geotagged_households / total_households * 100"]
  DispatchFocus["dispatch_focus = unsafe_total + priority unchecked households with GPS"]
  Battery["low_battery = household members/devices below configured battery threshold"]
  Dashboard["Dashboard cards, household status table, map colors, dispatch focus"]

  Scope --> Total
  Scope --> Logs
  Logs --> Reported
  Logs --> Safe
  Logs --> Evac
  Logs --> Unsafe
  Safe --> SafeEvac
  Evac --> SafeEvac
  Total --> Unchecked
  Reported --> Unchecked
  Reported --> Response
  Total --> Response
  Unsafe --> UnsafeRate
  Total --> UnsafeRate
  Scope --> Geotag
  Unsafe --> DispatchFocus
  Unchecked --> DispatchFocus
  Scope --> Battery
  Total --> Dashboard
  SafeEvac --> Dashboard
  Unsafe --> Dashboard
  Unchecked --> Dashboard
  Response --> Dashboard
  Geotag --> Dashboard
  DispatchFocus --> Dashboard
  Battery --> Dashboard
```

## Display rules

- Do not count old disaster records in active event analytics.
- Do not show prototype or fallback data as real operational data.
- If no active disaster exists, disaster-specific household status colors should not be treated as current response data.
- The map should show neutral geotagged households when there is no active disaster.

