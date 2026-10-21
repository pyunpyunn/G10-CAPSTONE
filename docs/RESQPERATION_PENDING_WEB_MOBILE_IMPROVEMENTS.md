# ResQperation Pending Web/Mobile Improvement Items

**Date:** June 26, 2026  
**Scope:** Web HQ/Dispatcher module and mobile app follow-up items  
**Status:** Documentation only — no code changes made

This document captures the requested improvement items that were identified for follow-up. Each item is based on the current implementation state in the existing web and mobile codebase.

---

## 1. Request count display for requests
- **Requested change:** Add or display a quantity/count for requests.
- **Current status:** Partially implemented.
- **Evidence from current code:** The resources request module already shows summary cards with counts in [frontend-web/src/components/resources/ResourceRequestStats.jsx](../frontend-web/src/components/resources/ResourceRequestStats.jsx), including:
  - Needs validation
  - Verified
  - Forwarded today
  - Returned
- **Follow-up note:** If the requirement is to show request quantities in the main queue or header view as well, this should be confirmed and expanded.

---

## 2. Add HQ / Command Center account
- **Requested change:** Add an option to create a Command Center / HQ account with ID `BDRRM-HQCC-001`.
- **Current status:** Not found in the current account management flow.
- **Evidence from current code:** No matching account ID or HQCC-related handling was found in the web, mobile, or backend source tree.
- **Follow-up note:** This will require a new account-role or account-creation path and supporting validation logic.

---

## 3. Fix Expo push token storage in the database
- **Requested change:** Ensure Expo push tokens can be saved in the database.
- **Current status:** Pending implementation.
- **Evidence from current code:** The mobile app does not currently show Expo push registration logic, and the backend has device-token related support but no clear Expo push-token registration flow was identified.
- **Follow-up note:** This should be treated as a mobile-to-backend integration task, including token registration, persistence, and retrieval for notifications.

---

## 4. Display current event information on the broadcast page
- **Requested change:** Show detailed info about the current disaster event on the broadcast page, including disaster name, disaster ID, duration, the four status types sent, and weather condition.
- **Current status:** Partially implemented.
- **Evidence from current code:** The broadcast page already shows a current alert card in [frontend-web/src/components/broadcast/BroadcastSidePanel.jsx](../frontend-web/src/components/broadcast/BroadcastSidePanel.jsx) with:
  - active event name
  - event type
  - severity label
  - start time
  - latest message
- **Missing items from the request:** Disaster ID, duration, the 4-status broadcast summary, and weather condition are not clearly displayed in the current view.

---

## 5. Improve dispatch purok selection experience
- **Requested change:** In dispatching, let the user select a purok from a dropdown instead of displaying all puroks at once. The unassigned area should be filterable, and the “Use this purok” action should be more user-friendly and clickable.
- **Current status:** Not implemented.
- **Evidence from current code:** The dispatch form in [frontend-web/src/components/dispatch/DispatchModalForm.jsx](../frontend-web/src/components/dispatch/DispatchModalForm.jsx) shows a list of risk-area cards and a “Use this purok” button, but there is no dropdown-based selector and no visible unassigned-only filter control.
- **Follow-up note:** This is a UX and workflow improvement for the dispatch assignment experience.

---

## 6. Add pagination to the purok triage display
- **Requested change:** Add pagination to the purok triage display with 5 puroks per page.
- **Current status:** Not implemented.
- **Evidence from current code:** The purok triage view in [frontend-web/src/components/households/HouseholdOpsPanels.jsx](../frontend-web/src/components/households/HouseholdOpsPanels.jsx) renders all available rows in a table with no pagination controls.
- **Follow-up note:** This is mainly a table UX enhancement for large area lists.

---

## Suggested priority
- **High priority:** HQ/Command Center account creation, Expo push token persistence, and dispatch purok selection workflow.
- **Medium priority:** Broadcast event detail display and request count visibility.
- **Medium priority:** Purok triage pagination.
