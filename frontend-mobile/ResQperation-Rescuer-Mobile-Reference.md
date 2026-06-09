# ResQperation Rescuer Mobile Reference

Source scanned from:

`C:\Users\Kathleen Barro\Downloads\ResQperation-System.zip`

The rescuer mobile app is inside the archive at:

`ResQperation-System/mobile`

The rescuer-specific pages are here:

`ResQperation-System/mobile/src/pages/rescuer`

Files found:

- `RescuerHomePage.jsx` - main rescuer shell, dashboard/home tab, bottom tab navigation
- `RescueMap.jsx` - map/GPS tracking tab
- `FieldReport.jsx` - field report form and local report list
- `ResquestLogistics.jsx` - logistics/resource request form and local request list
- `Profile.jsx` - rescuer profile/settings page

Supporting files:

- `mobile/App.js` - exports `src/App.jsx`
- `mobile/src/App.jsx` - app entry and role-based screen selection
- `mobile/src/pages/auth/LoginPage.jsx` - shared mobile login page
- `mobile/src/services/api.js` - Axios setup, API base URL detection, login call
- `mobile/src/components/AppHeader.jsx` - shared top header
- `mobile/src/components/LogoutButton.jsx` - shared logout button
- `mobile/src/theme/colors.js` and `mobile/src/theme/spacing.js` - shared tokens

## Tech Stack

The mobile folder is an Expo React Native app.

Package location:

`ResQperation-System/mobile/package.json`

Main dependencies:

- `expo`
- `react`
- `react-native`
- `axios`
- `expo-location`
- `expo-router`
- `react-native-maps`
- `react-native-safe-area-context`
- `react-native-screens`

The app imports icons from `@expo/vector-icons`. If this is moved into a clean project and icons fail, install it with:

```powershell
npx expo install @expo/vector-icons
```

## High-Level App Flow

### 1. App Entry

File:

`mobile/App.js`

Logic:

- Exports the actual app from `src/App.jsx`.

```js
export { default } from './src/App.jsx';
```

### 2. Role-Based Mobile App Controller

File:

`mobile/src/App.jsx`

State:

- `session` - stores the logged-in response from the backend.
- `screen` - controls which main screen is rendered.

Role route map:

```js
const ROUTES_BY_ROLE = {
  rescuer: "rescuer",
  household_resident: "household",
};
```

Flow:

1. App starts on `LoginPage`.
2. User logs in.
3. `handleLogin(nextSession)` checks `nextSession.user.role`.
4. If role is `rescuer`, it renders `RescuerHomePage`.
5. If role is `household_resident`, it renders `HouseholdHomePage`.
6. Any other role throws:

```text
This mobile app is only for rescuer and household resident accounts.
```

Rescuer render:

```jsx
<RescuerHomePage user={session.user} onLogout={handleLogout} />
```

Logout flow:

- `handleLogout()` clears `session`.
- Sets `screen` back to `login`.
- It does not currently call the backend logout endpoint.

Important for your actual project:

- Add token persistence with `AsyncStorage` or SecureStore if you need the user to stay logged in after closing the app.
- On logout, call `/api/auth/logout`, then clear local storage and navigation state.

## Authentication

### Mobile Login Page

File:

`mobile/src/pages/auth/LoginPage.jsx`

UI content:

- Brand kicker: `RESQPERATION`
- Main title: `Mobile Response`
- Subtitle: `Sign in with the account issued by HQ.`
- Inputs:
  - `User ID`
  - `Password`
- Button:
  - `Log in`
- Footer hint:
  - `API: {apiBaseUrl}`

State:

- `loginId`
- `password`
- `error`
- `isSubmitting`

Logic:

1. If already submitting, ignore duplicate press.
2. Clear previous error.
3. Validate that `loginId` and `password` are not empty.
4. Call `login(loginId, password)` from `services/api.js`.
5. On success, call `onLogin(session)`.
6. On failure, show `nextError.message`.

Current validation:

- Only checks empty fields.
- Backend handles invalid credentials.

### API Service

File:

`mobile/src/services/api.js`

Main exported items:

- `getApiBaseUrl()`
- `api`
- `login(loginId, password)`
- `checkApiHealth()`

API base URL flow:

1. If `EXPO_PUBLIC_API_BASE_URL` exists, use it.
2. Otherwise detect the Expo host/laptop IP.
3. Return:

```text
http://YOUR_LAPTOP_WIFI_IP:8000/api
```

Fallback:

```text
http://127.0.0.1:8000/api
```

Login request:

```js
POST /auth/login
{
  login_id: loginId.trim(),
  password
}
```

Expected backend response:

```js
{
  token: "...",
  user: {
    id: "...",
    name: "...",
    login_id: "...",
    email: "...",
    role: "rescuer",
    role_name: "Rescuer"
  }
}
```

On successful login:

- The token is stored only in Axios defaults:

```js
api.defaults.headers.common.Authorization = `Bearer ${data.token}`;
```

Important gap:

- Token is not persisted.
- Refreshing/restarting the app loses the session.

### Backend Login Endpoint

Backend file:

`backend/routes/api.php`

Endpoint:

```php
Route::post('/auth/login', [AuthController::class, 'login']);
```

Backend controller:

`backend/app/Http/Controllers/Api/AuthController.php`

Login logic:

1. Validate:
   - `login_id`
   - `password`
2. Find active user by `users.username`.
3. Check password with Laravel `Hash::check`.
4. Return Sanctum token and serialized user.

Backend test account from README/seeder:

```text
Rescuer
User ID: BDRRM-SAR-001
Password: password
```

## Rescuer Main Shell

File:

`mobile/src/pages/rescuer/RescuerHomePage.jsx`

This is the main container for all rescuer pages.

Props:

- `user` - authenticated user from login response.
- `onLogout` - callback from `App.jsx`.
- `onNavigate` - kept for backward compatibility but not used.

State:

- `activeTab` - current tab. Default: `dashboard`.
- `incidents` - incident list for dashboard.
- `loading` - dashboard loading state.
- `refreshing` - pull-to-refresh state.

Tabs:

- `dashboard` - Home / Field Console
- `map` - Rescue Map
- `report` - Field Report
- `resource` - Request Logistics
- `profile` - Responder Profile

Header:

Uses:

`mobile/src/components/AppHeader.jsx`

Header props:

```jsx
<AppHeader
  accentColor="#c01818"
  onLogout={onLogout}
  roleLabel="RESCUER"
  title={getHeaderTitle()}
  dark={true}
/>
```

Dynamic header titles:

- `dashboard` -> `Field Console`
- `map` -> `Rescue Map`
- `report` -> `Field Report`
- `resource` -> `Request Logistics`
- `profile` -> `Responder Profile`

Bottom nav:

Items:

```js
[
  { icon: "home-outline", label: "Home", route: "dashboard" },
  { icon: "map-outline", label: "Map", route: "map" },
  { icon: "add", label: "Report", route: "report", special: true },
  { icon: "cube-outline", label: "Resources", route: "resource" },
  { icon: "person-outline", label: "Profile", route: "profile" },
]
```

Navigation logic:

- Pressing a tab sets `activeTab`.
- No React Navigation stack is used.
- All pages are conditionally rendered by `renderContent()`.

Important for your actual project:

- This is simple and easy to copy.
- For a production app, prefer a proper navigator such as React Navigation bottom tabs, especially if you need nested screens like incident details, report details, edit profile, etc.

## Page 1: Rescuer Dashboard / Home

File:

`mobile/src/pages/rescuer/RescuerHomePage.jsx`

Tab key:

`dashboard`

Header title:

`Field Console`

Purpose:

- Shows the logged-in rescuer identity.
- Shows quick incident statistics.
- Shows a list of live incident reports.
- Supports pull-to-refresh.

Data source:

```js
const API_URL = "http://YOUR_IP_ADDRESS:5000/incidents";
```

Important:

- This is a placeholder URL.
- It does not use the configured Laravel Axios API service.
- It uses plain `fetch`.
- It does not attach the Sanctum token.
- It does not point to the Laravel backend in this zip.

Current dashboard fetch flow:

1. `useEffect()` calls `fetchIncidents()` once on mount.
2. `fetchIncidents()` calls `GET http://YOUR_IP_ADDRESS:5000/incidents`.
3. If response is not OK, throw an error.
4. Parse JSON.
5. Save to `incidents`.
6. Stop loading and refreshing.

Expected incident object shape:

```js
{
  id: 1,
  type: "Flood",
  status: "Active",
  location: "Barangay ...",
  affected_people: 25,
  team: "Team Alpha",
  elapsed: "5 min ago"
}
```

Computed values:

- `totalIncidents = incidents.length`
- `totalAffected = sum(incident.affected_people || 0)`
- `activeIncidents = count where incident.status === "Active"`

Status color logic:

- `Active`
  - border: red
  - badge background: translucent red
  - text: light red
- `Pending`
  - border: amber
  - badge background: translucent amber
  - text: yellow
- Anything else
  - border: green
  - badge background: translucent green
  - text: light green

Dashboard sections:

1. Welcome row
   - `Welcome, {user.name}`
   - `Login ID: {user.login_id}`
   - `CONSOLE ONLINE` badge

2. Stats row
   - Total Cases
   - Active Cases
   - Affected

3. Section title
   - `Live Incident Reports`

4. Incident list
   - Rendered with `FlatList`.
   - Uses `renderIncident`.

Incident card content:

- Title:
  - `#{item.id} • {item.type}`
- Status badge:
  - `{item.status}`
- Location row:
  - icon: `location-outline`
  - value: `{item.location}`
- Affected row:
  - icon: `people-outline`
  - value: `Affected: {item.affected_people || 0}`
- Team row:
  - icon: `shield-checkmark-outline`
  - value: `Assigned Team: {item.team || "Pending"}`
- Footer:
  - icon: `time-outline`
  - value: `{item.elapsed || "Recently reported"}`

Loading state:

- Activity indicator
- Text: `Loading incidents...`

Empty state:

- Text: `No incidents reported.`

Implementation notes for actual capstone:

- Replace placeholder `API_URL` with `api.get('/rescuer/incidents')`.
- Add error UI. Current code only logs fetch errors.
- Add auth token handling by using the shared Axios instance.
- Add incident details screen if rescuers need to open an incident.
- Define exact status values between backend and mobile. Current comparison only treats exact `"Active"` as active.

Recommended component breakdown:

- `RescuerDashboardScreen`
- `DashboardSummaryStats`
- `StatCard`
- `IncidentList`
- `IncidentCard`
- `StatusBadge`
- `EmptyState`
- `LoadingState`
- `ErrorState`

Recommended API:

```js
GET /api/rescuer/incidents
GET /api/rescuer/incidents/{id}
```

## Page 2: Rescue Map

File:

`mobile/src/pages/rescuer/RescueMap.jsx`

Tab key:

`map`

Header title:

`Rescue Map`

Purpose:

- Shows a map with incident markers.
- Lets rescuer start/stop location tracking.
- Shows aggregate incident numbers.
- Can navigate back to dashboard from markers or "View All Incidents".

Props:

- `isNested`
  - `true` when rendered inside `RescuerHomePage`.
  - Changes layout to fit inside the rescuer shell.
- `onNavigate`
  - Used to switch tabs when nested.

State:

- `myPosition`
  - current rescuer location.
- `locError`
  - location permission error message.
- `tracking`
  - whether tracking is active.
- `lastUpdated`
  - timestamp displayed in tracking status.
- `usingRealGPS`
  - whether position comes from actual GPS or simulation.
- `incidents`
  - static local incident array.

Static incident data:

```js
[
  {
    id: 1,
    position: { latitude: 10.3157, longitude: 123.8854 },
    title: "Flood Incident",
    type: "Flood",
    needsHelp: 15,
    critical: 3,
    injured: 8,
    safe: 4
  },
  {
    id: 2,
    position: { latitude: 10.32, longitude: 123.89 },
    title: "Building Collapse",
    type: "Structural",
    needsHelp: 22,
    critical: 7,
    injured: 12,
    safe: 3
  },
  {
    id: 3,
    position: { latitude: 10.31, longitude: 123.88 },
    title: "Medical Emergency",
    type: "Medical",
    needsHelp: 8,
    critical: 2,
    injured: 5,
    safe: 1
  }
]
```

Computed values:

- `totalNeedsHelp`
- `totalCritical`
- `totalInjured`
- `totalSafe`

Default map center:

```js
{
  latitude: 10.3157,
  longitude: 123.8854
}
```

Location tracking flow:

1. Press `Track Me`.
2. `startTracking()` requests foreground permission with Expo Location.
3. If denied:
   - `locError = "Location permission denied"`
   - tracking does not start.
4. If granted:
   - clear location error.
   - set `tracking = true`.
   - call `Location.watchPositionAsync`.
   - accuracy: high.
   - time interval: 5000ms.
   - distance interval: 1 meter.
5. On every location update:
   - set `usingRealGPS = true`.
   - update `myPosition`.
   - update `lastUpdated`.
   - stop simulation interval if active.
6. If tracking throws an error:
   - set `usingRealGPS = false`.
   - start simulation.

Simulation flow:

- Sets base location near Cebu City.
- Every 4 seconds, moves randomly by a tiny amount.
- Updates `lastUpdated`.

Stop tracking flow:

1. Remove GPS watcher.
2. Clear simulation interval.
3. Set tracking false.
4. Clear current position.
5. Clear last updated.
6. Set `usingRealGPS = false`.

Cleanup:

- On component unmount, removes GPS watcher and simulation interval.

UI sections:

1. Status chips row
   - Need Help
   - Critical
   - Injured
   - Safe

2. Tracking button
   - `Track Me` when inactive.
   - `Stop` when active.

3. Location error strip
   - Shows when permission denied.

4. Tracking status strip
   - Shows `Real GPS · {time}` or `Simulated · {time}`.

5. Map
   - Uses `MapView`.
   - Provider: `PROVIDER_GOOGLE`.
   - Displays current rescuer location as blue marker and circle if tracking.
   - Displays incidents as red markers.

6. Bottom map info
   - Total Need Help
   - Active Incidents
   - Button: `View All Incidents →`

Marker behavior:

- In nested mode:
  - pressing an incident marker calls `onNavigate?.("dashboard")`.
- In standalone mode:
  - pushes `/dashboard?incident={id}` using expo-router.

Important gaps:

- Static incident data only.
- Does not sync rescuer location to backend.
- Does not use backend assignments/routes.
- Standalone expo-router behavior is not wired to actual route files in this project.

Recommended component breakdown:

- `RescueMapScreen`
- `MapStatusChips`
- `TrackingButton`
- `LocationStatusBanner`
- `IncidentMap`
- `MapIncidentMarker`
- `CurrentLocationMarker`
- `MapSummaryFooter`
- `useLocationTracking`

Recommended API:

```js
GET /api/rescuer/incidents/map
POST /api/rescuer/location
GET /api/rescuer/assignments/current
GET /api/rescuer/routes/current
```

Recommended location payload:

```js
{
  latitude: 10.3157,
  longitude: 123.8854,
  battery_level: 82,
  signal_strength: 4,
  logged_at: "2026-06-06T10:30:00Z"
}
```

Backend tables that match this page:

- `responders`
- `responder_location_logs`
- `responder_assignments`
- `responder_routes`
- `route_coordinates`
- `affected_areas`
- `disaster_events`

## Page 3: Field Report

File:

`mobile/src/pages/rescuer/FieldReport.jsx`

Tab key:

`report`

Header title:

`Field Report`

Purpose:

- Allows rescuer to submit household/member status report.
- Shows a tally of submitted reports.
- Shows local report cards.

Props:

- `isNested`
  - when true, page is rendered inside `RescuerHomePage`.

State:

```js
const [reports, setReports] = useState([]);
const [showTally, setShowTally] = useState(false);
const [form, setForm] = useState({
  responder: "",
  barangay: "",
  householdHead: "",
  address: "",
  members: [{ name: "", age: "", status: "safe" }],
  notes: "",
});
```

Status options:

```js
[
  { value: "safe", label: "Safe" },
  { value: "evacuated", label: "Evacuated" },
  { value: "injured", label: "Injured" },
  { value: "deceased", label: "Deceased" },
]
```

Form fields:

- Responder Name
- Barangay
- Household Head Name
- Full Address
- Household Members
  - Name
  - Age
  - Status
- Additional notes

Actions:

- `Tally` toggle
- `Add Member`
- remove member
- `Submit Report`
- delete report card

Current logic:

### Add Member

Adds:

```js
{ name: "", age: "", status: "safe" }
```

### Remove Member

- Does nothing if there is only one member.
- Otherwise removes the selected index.

### Update Member

- Copies the members array.
- Updates the selected member field.
- Saves updated members into `form`.

### Submit Report

Creates local object:

```js
{
  id: Date.now(),
  responder: form.responder,
  barangay: form.barangay,
  householdHead: form.householdHead,
  address: form.address,
  members: form.members,
  notes: form.notes,
  time: new Date().toLocaleString()
}
```

Then:

- prepends it to `reports`
- resets form

Important:

- No backend request.
- No validation.
- Report remains only in memory.
- Closing/reloading the app loses submitted reports.

### Delete Report

- Filters local `reports` by id.

### Tally

Computed from local `reports`.

Counts:

- total members
- safe
- evacuated
- injured
- deceased

UI sections:

1. Page action row
   - Right-aligned `Tally` button.

2. Tally panel
   - Only visible when `showTally` is true.
   - Cards:
     - Total
     - Safe
     - Evac'd
     - Injured
     - Deceased

3. Report form
   - Responder Name + Barangay row
   - Household Head Name
   - Full Address
   - Household Members box
   - Notes textarea
   - Submit Report button

4. Report list
   - Empty state if no reports.
   - Report cards if there are reports.

Report card content:

- Household head
- Barangay
- Delete button
- Responder name
- Address
- Member table:
  - Name
  - Age
  - Status badge
- Mini tally chips per report
- Notes box if present
- Timestamp

Critical implementation gap:

`renderStatusPicker()` creates a touchable status selector but has no `onPress`.

Current effect:

- Member status cannot actually be changed through the UI.
- Every member remains `safe` unless changed by code.

Fix in actual capstone:

- Replace with one of:
  - segmented status chips
  - bottom sheet picker
  - dropdown modal
  - radio group

Recommended validation:

- `responder` required or auto-filled from logged-in user.
- `barangay` required.
- `householdHead` required.
- `address` required.
- each member name required.
- each member age numeric.
- each member status required.
- notes optional.

Recommended component breakdown:

- `FieldReportScreen`
- `ReportTallyPanel`
- `FieldReportForm`
- `HouseholdMemberList`
- `HouseholdMemberRow`
- `StatusPicker`
- `ReportCard`
- `MemberStatusBadge`
- `ReportMiniTally`
- `NotesBox`

Recommended API:

```js
GET /api/rescuer/field-reports
POST /api/rescuer/field-reports
GET /api/rescuer/field-reports/{id}
DELETE /api/rescuer/field-reports/{id}
```

Recommended submit payload:

```js
{
  disaster_id: "DIS-001",
  household_id: "HH-001",
  household_head: "Maria Santos",
  barangay: "Barangay Luz",
  address: "Full address here",
  latitude: 10.3157,
  longitude: 123.8854,
  notes: "Needs medicine",
  members: [
    {
      name: "Juan Santos",
      age: 42,
      status: "injured"
    }
  ]
}
```

Backend tables that match this page:

- `responder_field_reports`
- `responder_field_report_details`
- `field_report_categories`
- `responder_check_ins`
- `households`
- `household_members`
- `household_status`
- `disaster_events`

## Page 4: Request Logistics / Resources

File:

`mobile/src/pages/rescuer/ResquestLogistics.jsx`

Note:

- File name is misspelled as `ResquestLogistics.jsx`.
- Exported component name is `ResourceDashboard`.
- `RescuerHomePage.jsx` imports it as:

```js
import ResquestLogistics from "./ResquestLogistics.jsx";
```

Tab key:

`resource`

Header title:

`Request Logistics`

Purpose:

- Lets rescuer request resources/logistics.
- Tracks request status locally.
- Allows cancellation and deletion locally.

State:

```js
const [requests, setRequests] = useState([]);
const [form, setForm] = useState({
  name: "",
  location: "",
  cluster: "",
  resources: [],
});
```

Static resource choices:

```js
[
  "Food Packs",
  "Water Bottles",
  "Medicine Kits",
  "Blankets",
  "Flashlights"
]
```

Static clusters:

```js
[
  "Search and Rescue",
  "Medical/EMS",
  "Logistics",
  "Evacuation"
]
```

Status steps:

```js
["pending", "in-transit", "delivered", "cancelled"]
```

Form fields:

- Full Name
- Location
- Select Cluster
- Select Resource
- Selected Resources with quantity controls

Actions:

- select cluster
- select resource
- increase quantity
- decrease quantity
- remove selected resource
- submit resource request
- cancel request
- delete request

Current submit validation:

```js
if (!form.name || !form.location || form.resources.length === 0) return;
```

Cluster is optional in current code.

Submit request shape:

```js
{
  id: Date.now(),
  name: form.name,
  location: form.location,
  cluster: form.cluster,
  resources: form.resources,
  status: "pending",
  time: new Date().toLocaleString()
}
```

Then:

- prepends the new request to local `requests`
- resets form

Important:

- No backend request.
- Request status is local only.
- There is no UI to progress from `pending` to `in-transit` or `delivered`.
- Only cancellation is available.

UI sections:

1. Request form
   - Full Name + Location row
   - Cluster selector
   - Resource picker
   - Selected resources quantity controls
   - Request Resource button

2. Request list
   - Section title:
     - `No Requests Yet`, or
     - `1 Active Request`, or
     - `{n} Active Requests`
   - Empty state:
     - `No requests submitted yet`
   - Request cards

Request card content:

- Title:
  - `Resource Request`
- Status badge:
  - Pending
  - In Transit
  - Delivered
  - Cancelled
- Actions:
  - Cancel
  - Delete
- Meta:
  - requester name
  - location
  - cluster if selected
- Resource chips:
  - `{resource.name} × {resource.quantity}`
- Tracker:
  - Pending
  - In Transit
  - Delivered
- Cancelled banner if cancelled
- Timestamp footer

Recommended component breakdown:

- `ResourceRequestScreen`
- `ResourceRequestForm`
- `ClusterSelector`
- `ResourcePicker`
- `SelectedResourceList`
- `QuantityStepper`
- `ResourceRequestList`
- `ResourceRequestCard`
- `RequestStatusBadge`
- `RequestProgressTracker`

Recommended API:

```js
GET /api/rescuer/resource-requests
POST /api/rescuer/resource-requests
PATCH /api/rescuer/resource-requests/{id}/cancel
DELETE /api/rescuer/resource-requests/{id}
```

Recommended submit payload:

```js
{
  incident_id: "DIS-001",
  location: "Barangay Luz",
  cluster: "Medical/EMS",
  description: "Need additional supplies for evacuees",
  resources: [
    {
      resource_type: "Medicine Kits",
      quantity: 3
    },
    {
      resource_type: "Water Bottles",
      quantity: 10
    }
  ],
  urgency: "high"
}
```

Backend table that matches this page:

- `resource_requests`
- `resource_request_status`
- `urgency_levels`
- `evacuation_centers`
- `users`

## Page 5: Profile

File:

`mobile/src/pages/rescuer/Profile.jsx`

Tab key:

`profile`

Header title:

`Responder Profile`

Purpose:

- Shows responder profile card.
- Shows emergency/rescue services promo card.
- Shows settings menu.
- Shows logout button.

Props in code:

```js
export default function Profile({
  isNested = false,
  onNavigateTab,
}) {
```

Important:

- `RescuerHomePage.jsx` passes `onLogout`, but `Profile.jsx` does not destructure or use it.
- It does not receive or use the logged-in `user`.
- User details are static.

Static user content:

- Name: `Emily Johnson`
- Role: `Search & Rescue Responder`

Promo card:

- Title: `Cebu Emergency and Rescue Services`
- Text: `Committed in saving lives and building a safer community`
- Remote image URL from encrypted-tbn0.gstatic.com

Settings items:

- Personal information
- Login & security
- Payments and payouts
- Accessibility

Logout button text:

`LogOut →`

Profile page internal nav:

- Map
- Incidents
- Reports
- Resources
- Profile

Critical code issue:

The file calls:

```js
const router = useRouter();
const pathname = usePathname();
```

But it does not import:

```js
import { useRouter, usePathname } from "expo-router";
```

Current effect:

- Opening the Profile tab is likely to crash with `ReferenceError: useRouter is not defined`.

Second navigation issue:

Profile bottom nav items use slash routes:

```js
{ icon: "map-outline", label: "Map", route: "/map" }
```

When nested, it calls:

```js
onNavigateTab(route);
```

But the parent tab keys are:

```js
"dashboard", "map", "report", "resource", "profile"
```

Current effect:

- Passing `"/map"` to `setActiveTab` does not match `"map"`.
- It can break tab rendering.

Third issue:

`handleLogout()` uses:

```js
router.push("/login");
```

But this app is not actually using file-based expo-router screens for the main app flow. The real logout path should call `onLogout` from `App.jsx`.

Recommended fix:

```js
export default function Profile({
  isNested = false,
  user,
  onLogout,
  onNavigateTab,
}) {
```

Then:

- Display `user.name`.
- Display `user.role_name`.
- Display `user.login_id`.
- Make logout button call `onLogout`.
- Remove duplicate bottom nav when nested, because `RescuerHomePage` already renders the bottom nav.

Recommended component breakdown:

- `ProfileScreen`
- `ProfileSummaryCard`
- `ResponderAvatar`
- `ResponderDetails`
- `AgencyInfoCard`
- `SettingsList`
- `SettingsItem`
- `LogoutAction`

Recommended API:

```js
GET /api/auth/me
POST /api/auth/logout
GET /api/rescuer/profile
PATCH /api/rescuer/profile
```

## Shared Components

### AppHeader

File:

`mobile/src/components/AppHeader.jsx`

Props:

- `roleLabel`
- `title`
- `accentColor`
- `onLogout`
- `dark`

UI:

- Left title block:
  - role label
  - page title
- Right:
  - `LogoutButton`

Used by:

- `RescuerHomePage`
- likely household shell too

### LogoutButton

File:

`mobile/src/components/LogoutButton.jsx`

Props:

- `onPress`
- `dark`

Text:

`Logout`

Behavior:

- Calls `onPress`.
- The actual logout behavior comes from the parent.

### InfoPanel

File:

`mobile/src/components/InfoPanel.jsx`

Purpose:

- Simple reusable bordered panel with title and body.

Found usage:

- Not used in rescuer pages.

### Theme

Files:

- `mobile/src/theme/colors.js`
- `mobile/src/theme/spacing.js`

Important colors:

```js
rescuer: "#b21f2d"
rescuerPressed: "#8f1824"
background: "#f4f7f6"
surface: "#ffffff"
heading: "#12312b"
```

The actual rescuer pages override many colors directly with dark theme values:

- `#0F172A`
- `#1E293B`
- `#334155`
- `#c01818`

## Current Rescuer Flow Diagram

```text
App.js
  -> src/App.jsx
    -> LoginPage
      -> services/api.login()
        -> POST /api/auth/login
      -> onLogin(session)
        -> if user.role === "rescuer"
          -> RescuerHomePage
            -> AppHeader
            -> activeTab = "dashboard"
              -> Dashboard/Home content
            -> activeTab = "map"
              -> RescueMap
            -> activeTab = "report"
              -> FieldReport
            -> activeTab = "resource"
              -> ResquestLogistics
            -> activeTab = "profile"
              -> Profile
            -> Bottom tab nav
```

## What Is Real vs Prototype

Real/wired:

- Expo app setup.
- Login page.
- API base URL detection.
- Laravel `/api/auth/login`.
- Role-based rendering for rescuer vs household.
- Shared header/logout shell.

Prototype/local only:

- Dashboard incidents use placeholder `http://YOUR_IP_ADDRESS:5000/incidents`.
- Rescue Map incidents are hard-coded.
- Rescue Map location tracking is local only.
- Field Reports are local state only.
- Resource Requests are local state only.
- Profile is static and currently has router bugs.

Backend available:

- `/api/health`
- `/api/auth/login`
- `/api/auth/me`
- `/api/auth/logout`
- `/api/households`
- `/api/v1/health`
- `/api/v1/auth/login`
- `/api/v1/user`

Backend not yet exposed for rescuer mobile:

- incidents
- responder assignments
- responder live location logs
- responder routes
- responder field reports
- responder check-ins
- rescuer resource request endpoints

## Backend Database Tables Relevant to Rescuer Mobile

From:

`backend/database/schema/create_tables_2.sql`

Relevant tables:

### `responders`

Fields:

- `responder_id`
- `created_by_admin_id`
- `team_id`
- `username`
- `password_hash`
- `full_name`
- `title`
- `contact_number`
- `date_of_birth`
- `gender`
- `address`
- `is_validated`
- `is_deployed`
- `created_at`

Use for:

- responder profile
- team assignment
- deployment status

### `responder_location_logs`

Fields:

- `log_id`
- `responder_id`
- `latitude`
- `longitude`
- `battery_level`
- `signal_strength`
- `logged_at`

Use for:

- live tracking
- rescuer map
- HQ monitoring

### `responder_assignments`

Fields:

- `assignment_id`
- `responder_id`
- `disaster_id`
- `affected_area_id`
- `assigned_area`
- `route_notes`
- `status`
- `assigned_at`

Use for:

- dashboard assigned incidents
- map assigned area
- route notes

### `responder_routes`

Fields:

- `route_id`
- `assignment_id`
- `route_name`
- `created_at`

Use for:

- route display on map

### `route_coordinates`

Fields:

- `coordinate_id`
- `route_id`
- `latitude`
- `longitude`
- `sequence_order`

Use for:

- polyline route on map

### `responder_field_reports`

Fields:

- `report_id`
- `responder_id`
- `disaster_id`
- `household_id`
- `latitude`
- `longitude`
- `notes`
- `created_at`

Use for:

- Field Report page main report submission

### `responder_field_report_details`

Fields:

- `detail_id`
- `report_id`
- `category_id`
- `value`
- `notes`

Use for:

- member counts/status categories
- report category totals

### `responder_check_ins`

Fields:

- `check_in_id`
- `responder_id`
- `disaster_id`
- `team_id`
- `affected_area_id`
- `household_id`
- `member_id`
- `latitude`
- `longitude`
- `check_in_method`
- `status_id`
- `notes`
- `checked_in_at`
- `checked_out_at`
- `verified_by`

Use for:

- check-in/check-out workflow
- verifying household or member condition

### `resource_requests`

Fields:

- `request_id`
- `evacuation_center_id`
- `requested_by`
- `handled_by`
- `resource_type`
- `quantity`
- `description`
- `urgency_id`
- `status_id`
- `created_at`
- `updated_at`

Use for:

- Request Logistics page

## Suggested Actual Capstone Structure

Recommended mobile structure:

```text
src/
  app/
    AppRoot.jsx
  navigation/
    AuthNavigator.jsx
    RescuerTabs.jsx
  screens/
    auth/
      LoginScreen.jsx
    rescuer/
      RescuerDashboardScreen.jsx
      RescueMapScreen.jsx
      FieldReportScreen.jsx
      ResourceRequestScreen.jsx
      ProfileScreen.jsx
      IncidentDetailsScreen.jsx
  components/
    common/
      AppHeader.jsx
      BottomTabBar.jsx
      LoadingState.jsx
      EmptyState.jsx
      ErrorState.jsx
      StatusBadge.jsx
    rescuer/
      IncidentCard.jsx
      DashboardSummaryStats.jsx
      MapStatusChips.jsx
      TrackingButton.jsx
      FieldReportForm.jsx
      HouseholdMemberRow.jsx
      FieldReportCard.jsx
      ResourceRequestForm.jsx
      ResourceRequestCard.jsx
      RequestProgressTracker.jsx
      ProfileSummaryCard.jsx
  services/
    api.js
    authApi.js
    rescuerApi.js
  hooks/
    useAuthSession.js
    useLocationTracking.js
    useRescuerIncidents.js
    useFieldReports.js
    useResourceRequests.js
  constants/
    colors.js
    spacing.js
    statuses.js
  utils/
    formatDate.js
    validators.js
```

## Recommended Rescuer Data Models

### User Session

```js
{
  token: string,
  user: {
    id: string,
    name: string,
    login_id: string,
    email: string,
    role: "rescuer",
    role_name: "Rescuer"
  }
}
```

### Incident

```js
{
  id: string | number,
  disaster_id: string,
  type: string,
  title: string,
  status: "Active" | "Pending" | "Resolved",
  severity: "low" | "medium" | "high" | "critical",
  location: string,
  latitude: number,
  longitude: number,
  affected_people: number,
  team: string,
  assigned_area: string,
  route_notes: string,
  elapsed: string,
  reported_at: string
}
```

### Field Report

```js
{
  id: string | number,
  responder_id: number,
  disaster_id: string,
  household_id: string | null,
  household_head: string,
  barangay: string,
  address: string,
  latitude: number | null,
  longitude: number | null,
  members: [
    {
      name: string,
      age: number,
      status: "safe" | "evacuated" | "injured" | "deceased"
    }
  ],
  notes: string,
  created_at: string
}
```

### Resource Request

```js
{
  id: string | number,
  requester_name: string,
  requester_id: string,
  location: string,
  cluster: string,
  status: "pending" | "in-transit" | "delivered" | "cancelled",
  resources: [
    {
      resource_type: string,
      quantity: number
    }
  ],
  urgency: "low" | "medium" | "high" | "critical",
  created_at: string
}
```

### Location Log

```js
{
  responder_id: number,
  latitude: number,
  longitude: number,
  battery_level: number | null,
  signal_strength: number | null,
  logged_at: string
}
```

## Minimum Pages to Build for Rescuer Mobile

These are the pages represented by the current prototype and should be included in your actual capstone implementation.

### 1. Login Screen

Must include:

- ResQperation branding
- User ID input
- Password input
- login button
- loading state
- validation errors
- backend connection/API hint only in development

Logic:

- call `POST /api/auth/login`
- check role is `rescuer`
- store token
- navigate to rescuer tabs

### 2. Rescuer Dashboard / Home

Must include:

- user welcome
- login ID or responder ID
- online/status badge
- summary stats
- live/assigned incidents list
- pull-to-refresh
- loading, empty, error states

Logic:

- fetch assigned/open incidents from backend
- compute totals
- open incident details or map marker

### 3. Rescue Map

Must include:

- incident map markers
- current rescuer location
- track me / stop tracking
- permission denied state
- GPS/simulated/dev fallback state if needed
- incident summary chips
- button to view all incidents

Logic:

- request location permission
- watch position
- send location logs to backend
- fetch incident coordinates from backend
- clean up watcher on unmount

### 4. Field Report

Must include:

- report form
- responder identity
- barangay/address/household info
- household members list
- member status selector
- notes
- submit button
- tally panel
- submitted reports list

Logic:

- validate required fields
- support changing member status
- submit to backend
- refresh report list after submit
- preserve local draft if needed

### 5. Request Logistics / Resources

Must include:

- request form
- requester name or auto-filled rescuer
- location
- cluster selector
- resource picker
- quantity controls
- request status tracker
- cancel request
- request list

Logic:

- validate name/location/resources
- submit to backend
- fetch request list from backend
- cancel via backend
- status updates should come from HQ/backend

### 6. Profile

Must include:

- real logged-in user details
- role/title/team/contact number if available
- deployment status
- settings list
- logout

Logic:

- fetch `/api/auth/me` or `/api/rescuer/profile`
- logout through parent auth/session flow
- do not use route strings that conflict with the parent tab state

## Current Bugs and Cleanup Checklist

Fix these before using the current code as the base of the final app:

1. Replace dashboard placeholder URL:

```js
const API_URL = "http://YOUR_IP_ADDRESS:5000/incidents";
```

Use the shared API service instead.

2. Implement Field Report status picker.

Current picker is touchable but does nothing.

3. Wire Field Report to backend.

Reports currently disappear when the app reloads.

4. Wire Resource Requests to backend.

Requests currently disappear when the app reloads.

5. Fix Profile imports.

Current file uses `useRouter` and `usePathname` without importing them.

6. Fix Profile logout.

Use parent `onLogout`, not `router.push("/login")`.

7. Remove duplicate bottom navigation inside `Profile.jsx` and standalone sections if the app uses `RescuerHomePage` as the shell.

8. Make Profile use real user data.

Current name is hard-coded as `Emily Johnson`.

9. Decide one navigation system.

Current project mixes local `activeTab` state with `expo-router` calls in some pages. For consistency, use one approach.

10. Add token persistence.

Current token is only in memory.

11. Add authenticated API endpoints for rescuer functions.

The backend schema has tables for responder/rescuer modules, but API routes are not yet implemented.

## Best Starting Point for Your New Rescuer Mobile Dev

Use this prototype as a UI and flow reference, not as final backend-connected code.

Start from these core files:

1. `mobile/src/App.jsx`
   - copy the role-based login flow idea.

2. `mobile/src/pages/auth/LoginPage.jsx`
   - copy login form structure.

3. `mobile/src/pages/rescuer/RescuerHomePage.jsx`
   - copy the five-tab rescuer layout.

4. `mobile/src/pages/rescuer/RescueMap.jsx`
   - copy GPS tracking logic, then replace hard-coded incidents with backend data.

5. `mobile/src/pages/rescuer/FieldReport.jsx`
   - copy form layout, but fix status picker and backend submit.

6. `mobile/src/pages/rescuer/ResquestLogistics.jsx`
   - copy resource request layout, but rename file and connect to backend.

7. `mobile/src/pages/rescuer/Profile.jsx`
   - use only as visual inspiration; fix the logic issues before copying.

Recommended file rename:

```text
ResquestLogistics.jsx -> RequestLogistics.jsx
```

Recommended tab names:

```js
{
  dashboard: "Home",
  map: "Map",
  report: "Report",
  resource: "Resources",
  profile: "Profile"
}
```

## Backend Endpoints You Should Add

The current backend has login, but rescuer-specific API routes are missing. Suggested route group:

```php
Route::middleware('auth:sanctum')->prefix('rescuer')->group(function () {
    Route::get('/profile', [RescuerProfileController::class, 'show']);
    Route::get('/incidents', [RescuerIncidentController::class, 'index']);
    Route::get('/incidents/{incident}', [RescuerIncidentController::class, 'show']);
    Route::post('/location', [RescuerLocationController::class, 'store']);
    Route::get('/field-reports', [RescuerFieldReportController::class, 'index']);
    Route::post('/field-reports', [RescuerFieldReportController::class, 'store']);
    Route::get('/resource-requests', [RescuerResourceRequestController::class, 'index']);
    Route::post('/resource-requests', [RescuerResourceRequestController::class, 'store']);
    Route::patch('/resource-requests/{request}/cancel', [RescuerResourceRequestController::class, 'cancel']);
});
```

Recommended mobile API service:

```js
export const rescuerApi = {
  getProfile: () => api.get("/rescuer/profile"),
  getIncidents: () => api.get("/rescuer/incidents"),
  getIncident: (id) => api.get(`/rescuer/incidents/${id}`),
  sendLocation: (payload) => api.post("/rescuer/location", payload),
  getFieldReports: () => api.get("/rescuer/field-reports"),
  createFieldReport: (payload) => api.post("/rescuer/field-reports", payload),
  getResourceRequests: () => api.get("/rescuer/resource-requests"),
  createResourceRequest: (payload) => api.post("/rescuer/resource-requests", payload),
  cancelResourceRequest: (id) => api.patch(`/rescuer/resource-requests/${id}/cancel`),
};
```
