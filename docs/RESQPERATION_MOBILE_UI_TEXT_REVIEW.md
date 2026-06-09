# RESQPERATION Mobile UI Text Review

This note keeps the longer mobile explanations outside the app screens.

## Keep Inside The App

- Account ID, password, and forgot password controls
- Current disaster name, type, severity, and short message
- Household status buttons and last saved status
- Family member name, relationship, device status, battery, and last location
- Trusted household name, status, and validation state
- Evacuation QR button and QR code
- Route map, selected evacuation center, and available capacity
- Profile device user, real battery level, connection type, geotag, and logout

## Move To Documentation

- Why first-time location setup is required
- Why HQ uses household geotags during disasters
- Why trusted households require a PIN
- Why trusted household requests remain pending until validation
- Why evacuation QR is used at evacuation centers
- Why route map needs a saved household geotag
- Why rescuer assignments only appear after HQ dispatch

## Removed Or Shortened In The Mobile UI

| Screen | Previous Long Text | UI Replacement |
| --- | --- | --- |
| Mobile login | Role explanation and automatic view explanation | Simple sign-in form |
| Household setup | Geotag purpose paragraph | Required setup badge and location form |
| Household dashboard | Family readiness explanation | `No current disaster` badge |
| Household trusted QR | QR usage paragraph | QR code, household name, household ID |
| Trusted PIN modal | Full PIN explanation | Short PIN requirement |
| Add trusted household | Pending-validation explanation | Household lookup and reason field |
| Route map | Geotag instruction paragraph | `No household geotag yet` empty state |
| Rescuer dashboard | Future assignment explanation | `No active assignment` empty state |
| Rescuer map | Future marker explanation | `No mapped assignments` empty state |

## Device Telemetry Rule

The household mobile app should not display fake signal or battery values.

- Battery is read from `expo-battery`.
- Connection type is read from `expo-network`.
- Battery level is sent with device heartbeat and status update payloads.
- Network connection is displayed locally as Wi-Fi, Cellular, Ethernet, Online, or Offline.

## Notes For Defense

The app keeps operational screens minimal because users may be stressed during a disaster. Longer explanations belong in documentation and training materials, not in the active response interface.
