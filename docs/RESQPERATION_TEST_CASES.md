# RESQPERATION Test Cases

This document contains manual functional test cases for the RESQPERATION Barangay Rescue Operations and Disaster Response Management System.

## Test Environment

| Item | Value |
| --- | --- |
| System | RESQPERATION |
| Backend | Laravel API |
| Frontend | React + Vite HQ/Admin web app |
| Database | MySQL shared database |
| Web URL | `http://127.0.0.1:5174/` or current Vite dev URL |
| API URL | `http://127.0.0.1:8000/api/v1` |
| Test Account | HQ/Admin account from the project README |

## Test Case Template

| Field | Description |
| --- | --- |
| Test Case ID | Unique test identifier |
| Module | System feature being tested |
| Test Scenario | What behavior is being checked |
| Preconditions | Required setup before testing |
| Test Steps | Actions performed by the tester |
| Expected Result | Correct system behavior |
| Actual Result | To be filled during testing |
| Status | Passed, Failed, or Blocked |

## Functional Test Cases

### TC-001: HQ/Admin Login With Valid Credentials

| Field | Details |
| --- | --- |
| Module | Authentication |
| Test Scenario | HQ/Admin logs in using valid credentials. |
| Preconditions | Backend API and frontend app are running. HQ/Admin account exists in the database. |
| Test Steps | 1. Open the RESQPERATION web app. 2. Click the login button. 3. Enter a valid HQ/Admin account ID and password. 4. Submit the login form. |
| Expected Result | The system accepts the credentials, stores the session token, and redirects the user to the protected dashboard. |
| Actual Result |  |
| Status |  |

### TC-002: Login With Invalid Credentials

| Field | Details |
| --- | --- |
| Module | Authentication |
| Test Scenario | User enters an invalid account ID or password. |
| Preconditions | Backend API and frontend app are running. |
| Test Steps | 1. Open the login form. 2. Enter an invalid account ID or incorrect password. 3. Submit the login form. |
| Expected Result | The system rejects the login attempt and displays a clear error message without opening the dashboard. |
| Actual Result |  |
| Status |  |

### TC-003: Protected Page Requires Login

| Field | Details |
| --- | --- |
| Module | Authentication and Route Protection |
| Test Scenario | Unauthenticated user tries to open a protected page. |
| Preconditions | User is logged out or browser session is cleared. |
| Test Steps | 1. Open a protected route such as `/dashboard`. |
| Expected Result | The system redirects the user to the login page or blocks access until a valid login is provided. |
| Actual Result |  |
| Status |  |

### TC-004: Dashboard Loads Live Operational Data

| Field | Details |
| --- | --- |
| Module | Dashboard |
| Test Scenario | HQ/Admin views the command dashboard. |
| Preconditions | User is logged in as HQ/Admin. Backend API is connected to the database. |
| Test Steps | 1. Open the Dashboard page. 2. Review the summary cards, event state, household counts, dispatch counts, and operational panels. |
| Expected Result | The dashboard loads data from the backend. If no active disaster event exists, the page shows standby or empty states instead of fake disaster data. |
| Actual Result |  |
| Status |  |

### TC-005: Disaster Broadcast Declaration

| Field | Details |
| --- | --- |
| Module | Disaster Broadcasting |
| Test Scenario | HQ/Admin declares or starts a disaster broadcast. |
| Preconditions | User is logged in as HQ/Admin. Required disaster type and severity data exist. |
| Test Steps | 1. Open Disaster Broadcasting. 2. Click the declare or compose broadcast action. 3. Fill in event type, severity, affected area, message title, and message body. 4. Save the broadcast. |
| Expected Result | The system creates or updates the active disaster event and records the broadcast message for the selected scope. |
| Actual Result |  |
| Status |  |

### TC-006: Broadcast Form Validation

| Field | Details |
| --- | --- |
| Module | Disaster Broadcasting |
| Test Scenario | HQ/Admin submits an incomplete broadcast form. |
| Preconditions | User is logged in and Disaster Broadcasting page is open. |
| Test Steps | 1. Open the broadcast form. 2. Leave required fields blank. 3. Submit the form. |
| Expected Result | The system prevents saving and displays validation messages for required fields. |
| Actual Result |  |
| Status |  |

### TC-007: Household Status List and Filters

| Field | Details |
| --- | --- |
| Module | Household Status |
| Test Scenario | HQ/Admin reviews household safety reports. |
| Preconditions | User is logged in. Household records exist in the shared database. |
| Test Steps | 1. Open Household Status. 2. Use filters such as status, purok, priority, or search. 3. Open a household detail record. |
| Expected Result | The table updates according to the selected filters, and the detail view shows household information, current status, report source, location, and status history. |
| Actual Result |  |
| Status |  |

### TC-008: Rescue Dispatch Assignment

| Field | Details |
| --- | --- |
| Module | Rescue Dispatch |
| Test Scenario | HQ/Admin assigns or updates a rescuer team. |
| Preconditions | User is logged in. Rescuer teams or responder accounts exist. There is an active response need or disaster event. |
| Test Steps | 1. Open Rescue Dispatch. 2. Select a team or dispatch action. 3. Enter assignment details and status. 4. Save the dispatch update. |
| Expected Result | The selected team status updates, and the dispatch log reflects the assignment or status change. |
| Actual Result |  |
| Status |  |

### TC-009: Rescuer Account Creation

| Field | Details |
| --- | --- |
| Module | Rescuer Accounts |
| Test Scenario | HQ/Admin creates a rescuer account. |
| Preconditions | User is logged in as HQ/Admin. Backend API is available. |
| Test Steps | 1. Open Rescuer Accounts. 2. Click the add account action. 3. Fill in rescuer name, team, role, contact, and status fields. 4. Save the account. |
| Expected Result | The system creates a responder account with the correct BDRRM account ID format and displays the new rescuer in the roster. |
| Actual Result |  |
| Status |  |

### TC-010: Resource Request Validation

| Field | Details |
| --- | --- |
| Module | Resources and Requests |
| Test Scenario | HQ/Admin validates a resource request. |
| Preconditions | User is logged in. At least one pending resource request exists. |
| Test Steps | 1. Open Resources & Requests. 2. Select a pending request. 3. Review request details. 4. Choose a validation decision such as verified, returned, duplicate, or incomplete. 5. Save the decision. |
| Expected Result | The request status updates correctly, and the validation decision is recorded without changing unrelated requests. |
| Actual Result |  |
| Status |  |

### TC-011: Forward Verified Request to TrackingAid

| Field | Details |
| --- | --- |
| Module | Resources and Requests |
| Test Scenario | HQ/Admin forwards a verified request for external fulfillment tracking. |
| Preconditions | A resource request has verified status. |
| Test Steps | 1. Open Resources & Requests. 2. Select a verified request. 3. Click the forward or handoff action. 4. Confirm the handoff. |
| Expected Result | The system marks the request as forwarded or ready for TrackingAid and stores a handoff reference. RESQPERATION does not claim delivery fulfillment. |
| Actual Result |  |
| Status |  |

### TC-012: Weather Snapshot Refresh

| Field | Details |
| --- | --- |
| Module | Weather Updates |
| Test Scenario | HQ/Admin refreshes the weather monitoring snapshot. |
| Preconditions | User is logged in. Backend weather service is available. |
| Test Steps | 1. Open Weather Updates. 2. Click Refresh. 3. Review the latest weather data and log panel. |
| Expected Result | The system requests an updated weather snapshot, saves it through the backend, and displays the latest monitoring data. |
| Actual Result |  |
| Status |  |

### TC-013: Mapping Overview and Geotag Display

| Field | Details |
| --- | --- |
| Module | Mapping |
| Test Scenario | HQ/Admin views barangay map data and geotagged records. |
| Preconditions | User is logged in. Mapping data, household locations, or responder locations exist. |
| Test Steps | 1. Open Mapping. 2. Review map markers and summary panels. 3. Filter by purok or status if available. 4. Select a marker or record. |
| Expected Result | The map and sidebar display locations relevant to the selected filters, including household, responder, evacuation, or incident markers. |
| Actual Result |  |
| Status |  |

### TC-014: Situation Report Generation

| Field | Details |
| --- | --- |
| Module | Situation Reporting |
| Test Scenario | HQ/Admin generates a situation report for a disaster event. |
| Preconditions | User is logged in. A disaster event exists with available household, dispatch, weather, and request data. |
| Test Steps | 1. Open Situation Reporting. 2. Select an event. 3. Review the report preview. 4. Click generate. 5. Confirm the report details. |
| Expected Result | The system generates a situation report containing event overview, affected population, dispatch actions, weather source, resources, and archive-ready summary data. |
| Actual Result |  |
| Status |  |

### TC-015: Archive Search and CSV Export

| Field | Details |
| --- | --- |
| Module | Archive |
| Test Scenario | HQ/Admin searches archived records and exports CSV. |
| Preconditions | User is logged in. Archive records exist. |
| Test Steps | 1. Open Archive. 2. Select an archive category. 3. Apply search or date filters. 4. Open a record. 5. Download CSV. |
| Expected Result | The system displays filtered archive records, opens the selected record details, and downloads a CSV file for the selected archive category or record. |
| Actual Result |  |
| Status |  |

### TC-016: Notification Preview and Mark as Read

| Field | Details |
| --- | --- |
| Module | Notifications |
| Test Scenario | HQ/Admin reviews notification preview and marks notifications as read. |
| Preconditions | User is logged in. Notifications exist or the notification API is available. |
| Test Steps | 1. Click the notification icon in the topbar. 2. Review the notification popover. 3. Click Mark as read. 4. Open the Notifications page. |
| Expected Result | The unread count updates to zero, notification records show read state, and the Notifications page displays the updated list. |
| Actual Result |  |
| Status |  |

### TC-017: Profile View and Update

| Field | Details |
| --- | --- |
| Module | Profile |
| Test Scenario | HQ/Admin updates profile or barangay profile details. |
| Preconditions | User is logged in as HQ/Admin. Profile endpoint is available. |
| Test Steps | 1. Open Profile. 2. Click Profile settings or edit action. 3. Update allowed fields. 4. Save changes. |
| Expected Result | The profile page displays updated information after saving, and restricted fields remain protected from invalid changes. |
| Actual Result |  |
| Status |  |

### TC-018: Topbar Logo Display

| Field | Details |
| --- | --- |
| Module | User Interface |
| Test Scenario | RESQPERATION logo appears in the topbar instead of the old letter mark. |
| Preconditions | Frontend app is running with the latest source files. |
| Test Steps | 1. Log in to the web app. 2. View the topbar. 3. Resize the browser to desktop and mobile widths. |
| Expected Result | The topbar displays the RESQPERATION logo image. The old `R` text badge is not visible, and the logo remains aligned with the brand name on different screen sizes. |
| Actual Result |  |
| Status |  |

### TC-019: Logout or Session Expiration Handling

| Field | Details |
| --- | --- |
| Module | Authentication |
| Test Scenario | System handles an expired or invalid token. |
| Preconditions | User has a stored login token. Backend token is expired, removed, or invalid. |
| Test Steps | 1. Open a protected page with an invalid session. 2. Trigger an API request by refreshing or navigating. |
| Expected Result | The system prevents access to protected data and redirects the user to login or displays a session error. |
| Actual Result |  |
| Status |  |

### TC-020: Backend API Error Handling

| Field | Details |
| --- | --- |
| Module | System Reliability |
| Test Scenario | Frontend handles backend downtime or database connection failure. |
| Preconditions | User is logged in or has access to the app. Backend API is stopped or unreachable. |
| Test Steps | 1. Stop the Laravel backend. 2. Open Dashboard, Household Status, Weather Updates, or another API-backed page. |
| Expected Result | The frontend shows a clear loading failure or connection error message instead of crashing or displaying misleading data. |
| Actual Result |  |
| Status |  |

## End-to-End Test Case

### E2E-001: Disaster Response Workflow

| Field | Details |
| --- | --- |
| Module | Full System Workflow |
| Test Scenario | HQ/Admin manages a disaster response from broadcast to report archive. |
| Preconditions | Backend, frontend, and database are running. HQ/Admin user can log in. Required reference data exists. |
| Test Steps | 1. Log in as HQ/Admin. 2. Declare a disaster broadcast. 3. Review household status reports. 4. Dispatch a rescuer team to an urgent household or area. 5. Validate a resource request. 6. Refresh weather data. 7. Review mapping markers. 8. Generate a situation report. 9. Open Archive and confirm the event/report record is available. |
| Expected Result | The system supports the full headquarters workflow: event declaration, monitoring, dispatch coordination, request validation, weather review, mapping, situation reporting, and archive review. Each module saves or displays data through the backend without using hardcoded operational records. |
| Actual Result |  |
| Status |  |

## Non-Functional Test Cases

### NFT-001: Page Load Responsiveness

| Field | Details |
| --- | --- |
| Module | Performance |
| Test Scenario | Main web pages load within an acceptable time during local testing. |
| Preconditions | Backend and frontend are running locally. |
| Test Steps | 1. Log in. 2. Open Dashboard, Household Status, Dispatch, Resources, Weather, Mapping, Situation Reporting, and Archive. 3. Observe page load time and loading indicators. |
| Expected Result | Each page shows a loading state and becomes usable without browser freezing. |
| Actual Result |  |
| Status |  |

### NFT-002: Responsive Layout

| Field | Details |
| --- | --- |
| Module | User Interface |
| Test Scenario | HQ/Admin web layout adapts to smaller screens. |
| Preconditions | Frontend app is running. |
| Test Steps | 1. Open the web app. 2. Resize browser width to desktop, tablet, and mobile widths. 3. Check sidebar, topbar, tables, cards, modals, and buttons. |
| Expected Result | Text and controls remain readable. Important actions are still accessible. UI elements do not overlap. |
| Actual Result |  |
| Status |  |

### NFT-003: Role-Based Access

| Field | Details |
| --- | --- |
| Module | Security |
| Test Scenario | Non-HQ/Admin users cannot access HQ/Admin web-only pages. |
| Preconditions | Household or rescuer account exists. |
| Test Steps | 1. Attempt to log in to the web app using a household or rescuer account. 2. Attempt to access protected HQ/Admin routes. |
| Expected Result | The system rejects unauthorized web access or redirects the user according to role rules. |
| Actual Result |  |
| Status |  |

## Test Summary Sheet

| Total Test Cases | Passed | Failed | Blocked | Remarks |
| --- | --- | --- | --- | --- |
| 24 |  |  |  |  |
