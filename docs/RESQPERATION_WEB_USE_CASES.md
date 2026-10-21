# ResQperation Web Use Cases

**Date:** July 1, 2026  
**Scope:** HQ/Admin / Dispatcher Web Module

## Overview

This document describes the main use cases of the ResQperation web system from the perspective of the HQ/Admin user. It focuses on the web dashboard and its core operational functions.

---

## 1. User Authentication Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Allow authorized users to log in to the web system securely.

### Main Flow
1. The user opens the web login page.
2. The user enters valid credentials.
3. The system validates the account.
4. The system grants access to the dashboard based on the user role.

### Alternative Flow
- Invalid credentials show an error message.
- Unauthenticated users are redirected to the login page.

---

## 2. Dashboard Monitoring Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Provide an overview of active disaster operations and current system status.

### Main Flow
1. The admin opens the dashboard.
2. The system displays active event information, household reports, dispatch status, weather updates, and request activity.
3. The admin can quickly navigate to other modules from the dashboard.

### Included Functions
- View incident overview
- Review active event status
- Monitor household reporting progress
- Monitor resource requests
- View weather and operational summary

---

## 3. Disaster Event Creation Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Create and manage a new disaster event.

### Main Flow
1. The admin opens the broadcast or disaster management page.
2. The admin enters event details such as event name, type, severity, duration, and message.
3. The system saves the disaster event.
4. The event becomes available for broadcasting to affected users.

### Optional Actions
- Set event severity
- Define affected purok or area
- Select status types to be requested from households

---

## 4. Disaster Broadcast Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Send official disaster alerts to household and rescue users.

### Main Flow
1. The admin creates a broadcast message.
2. The admin selects the target area or purok.
3. The admin chooses the status types to request from recipients.
4. The system sends the broadcast through the backend notification service.
5. The broadcast is logged for monitoring and future review.

### Important Data Captured
- Disaster name
- Event ID
- Severity level
- Target area
- Message content
- Recipient scope

---

## 5. Household Status Monitoring Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Monitor household safety reports and current conditions during an event.

### Main Flow
1. The admin opens the Household Status module.
2. The system displays household reports and status summaries.
3. The admin filters entries by purok, status, or search keyword.
4. The admin views household details, status history, and device information.

### Included Functions
- View reported household status
- Filter by purok and status
- View household profile and location
- Review history of updates
- Detect unsafe or high-risk households

---

## 6. Mapping and Routing Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Visualize household locations, evacuation sites, rescue teams, and assigned routes.

### Main Flow
1. The admin opens the mapping page.
2. The system loads household and evacuation site data from the database.
3. The admin views map layers for households, routes, and rescue teams.
4. The admin can filter by purok or status.
5. The admin can review routing information for response coordination.

### Supported Views
- Geo-tagged household locations
- Evacuation sites
- Rescue team positions
- Dispatch routes

---

## 7. Rescue Dispatch Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Assign rescue teams to affected areas and manage field operations.

### Main Flow
1. The admin reviews affected areas and high-risk households.
2. The admin selects a purok or area for dispatch.
3. The admin assigns rescue teams and responders.
4. The system stores the dispatch record.
5. The admin monitors team status and operational progress.

### Included Functions
- Assign teams to priority areas
- Set dispatch status
- Track team availability
- Record field outcomes
- Add notes and route instructions

---

## 8. Rescuer Account Management Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Register, manage, and validate rescuer accounts.

### Main Flow
1. The admin opens the rescuer accounts page.
2. The admin creates or edits a rescuer account.
3. The system stores the account profile and role details.
4. The admin validates or updates the account status.

### Supported Actions
- Add new rescuer account
- Assign role or team
- Update profile information
- Deactivate account if needed

---

## 9. Resource and Request Management Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Receive, validate, and coordinate incoming requests.

### Main Flow
1. The admin opens the requests module.
2. The system displays incoming requests from external systems or manual input.
3. The admin validates the request.
4. The admin approves, forwards, or returns the request as needed.
5. The request status is updated in the system.

### Request Types
- Personnel requests
- Equipment requests
- Goods/supplies requests

---

## 10. Situation Reporting Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Generate structured operational reports for current incidents.

### Main Flow
1. The admin opens the situation report module.
2. The system gathers operational and incident data from the database.
3. The admin generates or previews the SitRep.
4. The system saves and exports the report when requested.

### Output
- Situation report summary
- Incident status overview
- Operational updates for review or escalation

---

## 11. Incident Archiving Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Preserve past disaster records and reports for documentation and review.

### Main Flow
1. The admin opens the archive module.
2. The system displays historical records by category.
3. The admin searches or filters archived records.
4. The admin exports or reviews the archived information.

### Archive Content
- Disaster events
- Broadcasts
- Reports
- Request records
- Dispatch history

---

## 12. Profile and Account Settings Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- Manage the logged-in user’s profile information and account security.

### Main Flow
1. The admin opens the profile page.
2. The system displays the current user information.
3. The admin updates profile detail or password when needed.

---

## 13. Notification and Alert Management Use Case

### Primary Actor
- HQ/Admin / Dispatcher

### Goal
- View and manage system alerts and notifications.

### Main Flow
1. The admin opens the notification page.
2. The system shows important events and updates.
3. The admin reviews or manages the list of alerts.

---

## Summary of Main Web Use Cases

The ResQperation web system mainly supports these major HQ/Admin activities:
- Log in securely
- Monitor disaster operations
- Create and send disaster alerts
- Monitor household status
- View maps and routes
- Dispatch rescue teams
- Manage rescuer accounts
- Validate incoming requests
- Generate SitReps
- Archive reports and incidents
- Manage profile settings
