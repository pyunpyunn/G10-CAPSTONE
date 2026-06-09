# ResQperation Household Mobile Reference

Source scanned from:

`C:\Users\Kathleen Barro\Downloads\mobile app - Copy\mobile app - Copy`

Related suggestion file scanned:

`C:\Users\Kathleen Barro\.codex\attachments\95c16247-8ab7-4ad0-a264-fc7acb197330\pasted-text.txt`

This document separates three things:

1. What the current household mobile prototype actually contains.
2. How it maps to your checklist.
3. What should be built or changed for the actual capstone household mobile app.

## Project Location

Project root:

`C:\Users\Kathleen Barro\Downloads\mobile app - Copy\mobile app - Copy`

Main entry:

`App.js`

Screens:

`src/screens`

Components:

`src/components`

Static mock data:

`src/data.js`

Theme/design tokens:

`src/theme.js`

## Tech Stack Found

From `package.json`, this is an Expo React Native app.

Current dependencies:

- `expo`
- `react`
- `react-native`
- `@expo-google-fonts/inter`
- `expo-blur`
- `expo-font`
- `expo-haptics`
- `expo-image-picker`
- `expo-linear-gradient`
- `expo-location`
- `expo-status-bar`
- `lucide-react-native`
- `react-native-qrcode-svg`
- `react-native-safe-area-context`
- `react-native-svg`
- `react-native-web`

Current scripts:

```bash
npm start
npm run android
npm run ios
npm run web
```

Important missing packages for a backend-connected final app:

- No `axios`
- No `expo-secure-store`
- No `expo-router`
- No `zustand`
- No `react-hook-form`
- No `zod`
- No push notification package
- No real map package such as `react-native-maps`

The Claude suggestion file recommends adding those for the final build.

## App Permissions

File:

`app.json`

iOS permission text:

- Location: used to pin household during response coordination.
- Photo library: used to choose a family display photo during setup.

Android permissions:

- `ACCESS_COARSE_LOCATION`
- `ACCESS_FINE_LOCATION`
- `READ_MEDIA_IMAGES`

## Current File Map

### Screens

- `src/screens/LoginScreen.js`
- `src/screens/OnboardingScreen.js`
- `src/screens/DashboardScreen.js`
- `src/screens/MapScreen.js`
- `src/screens/ReportsScreen.js`
- `src/screens/AlertsScreen.js`

### Components

- `src/components/ActionButton.js`
- `src/components/AppText.js`
- `src/components/BottomNav.js`
- `src/components/BottomSheet.js`
- `src/components/Card.js`
- `src/components/EmergencyFAB.js`
- `src/components/FieldInput.js`
- `src/components/ListItem.js`
- `src/components/MemberList.js`
- `src/components/QrPanel.js`
- `src/components/StatusActionGrid.js`
- `src/components/StatusBanner.js`
- `src/components/StatusChip.js`

### Other

- `src/data.js`
- `src/theme.js`
- `src/hooks/useReducedMotion.js`

## High-Level Current Flow

The whole app flow is controlled by `App.js`.

State in `AppShell`:

```js
const [phase, setPhase] = useState("login");
const [activeTab, setActiveTab] = useState("dashboard");
const [disasterActive, setDisasterActive] = useState(false);
const [trustedList, setTrustedList] = useState(trustedHouseholds);
const [trustedPin, setTrustedPin] = useState("");
const [trustedSheetMode, setTrustedSheetMode] = useState("hidden");
const [trustedFlow, setTrustedFlow] = useState("pin");
const [afterPinAction, setAfterPinAction] = useState("detail");
const [selectedHousehold, setSelectedHousehold] = useState(trustedHouseholds[0]);
const [pinEntry, setPinEntry] = useState("");
const [pinError, setPinError] = useState("");
const [newPin, setNewPin] = useState("");
const [newHouseholdId, setNewHouseholdId] = useState("HH-9130");
const [newReason, setNewReason] = useState("Shared shelter route during flood response.");
const [qrMode, setQrMode] = useState("hidden");
const [emergencyMode, setEmergencyMode] = useState("hidden");
const [emergencyStatus, setEmergencyStatus] = useState("critical");
```

Phase flow:

```text
phase = "login"
  -> LoginScreen
  -> pressing Log in sets phase = "onboarding"

phase = "onboarding"
  -> OnboardingScreen
  -> finishing setup sets phase = "app"

phase = "app"
  -> Dashboard / Map / Reports / Alerts tabs
  -> BottomNav visible
  -> EmergencyFAB visible
  -> TrustedHouseholdSheet, QR sheet, emergency sheet available
```

Current important limitation:

- There is no real login API.
- There is no first-login flag from the backend.
- The app always sends the user from login to onboarding.
- There is no persisted session.
- All data is mock/local state.

## Navigation

The current app does not use React Navigation or Expo Router. It uses local state.

Bottom tabs:

```js
[
  { id: "dashboard", label: "Dashboard" },
  { id: "map", label: "Map" },
  { id: "reports", label: "Reports" },
  { id: "alerts", label: "Alerts" }
]
```

Defined in:

`src/components/BottomNav.js`

The rendered screen is selected in `App.js`:

- `activeTab === "map"` renders `MapScreen`
- `activeTab === "reports"` renders `ReportsScreen`
- `activeTab === "alerts"` renders `AlertsScreen`
- anything else renders `DashboardScreen`

Recommended final navigation:

- Keep the simple state navigation if you want a fast prototype.
- Use `expo-router` or React Navigation if the final app needs separate screens for forgot password, first-time setup steps, QR, trusted-household details, profile, etc.

## Mock Data

File:

`src/data.js`

### Family Members

Current `familyMembers` array:

- Mara Santos
  - relationship: Mother
  - device: active
  - battery: 82
  - location: Home pin
  - status: safe
- Leo Santos
  - relationship: Brother
  - device: active
  - battery: 46
  - location: Evacuation route
  - status: info
- Nina Santos
  - relationship: Daughter
  - device: inactive
  - battery: 18
  - location: Last active 12 min ago
  - status: evacuate
- Roberto Santos
  - relationship: Grandfather
  - device: none
  - battery: null
  - location: No device registered
  - status: critical

### Trusted Households

Current `trustedHouseholds`:

1. `HH-2048`
   - familyName: Rivera
   - relationship: Aunt household
   - status: safe
   - validation: Validated
   - idCard: ID-RIVERA-88
   - reason: Shared evacuation transport
   - members: Celina Rivera, Marco Rivera

2. `HH-7781`
   - familyName: Dela Cruz
   - relationship: Neighbor household
   - status: info
   - validation: Pending validation
   - idCard: ID-DELA-41
   - reason: Mutual check-in during flood alerts
   - members: Paolo Dela Cruz, Ana Dela Cruz

### Active Disaster

Current static disaster:

```js
{
  type: "Flood warning",
  date: "May 8, 2026",
  message: "River level rising near Barangay 7.",
  additionalInfo: "Move essential documents and medicine above waist height. Use Route B if water reaches the main road."
}
```

### Map Locations

Current `mapLocations` are fake absolute-position markers on a custom visual map:

- Home pin
- Evacuation route
- Medical request
- Supply note

These are not real latitude/longitude markers.

### Report History

Current `reportHistory`:

- Water entered ground floor
- Medication packed

## Page 1: Login

File:

`src/screens/LoginScreen.js`

Purpose:

- User login screen.
- Has forgot-password behavior.

Current UI:

- Small label: `Field access`
- Page title: `Log in`
- Card title: `ResQperation`
- Subtitle: `Coordinate. Respond. Recover.`
- Email input
- Password input
- `Log in` button
- `Forgot password` button

Current state:

```js
const [email, setEmail] = useState("responder@resqperation.app");
const [password, setPassword] = useState("secure-login");
const [recoveryNotice, setRecoveryNotice] = useState("");
```

Current login logic:

```js
onPress={() => onLogin({ email, password })}
```

In `App.js`, `onLogin` is:

```js
onLogin={() => setPhase("onboarding")}
```

So pressing login always moves to onboarding.

Current forgot-password logic:

```js
setRecoveryNotice("Password recovery sent to the registered contact.")
```

Important gaps:

- No backend credential validation.
- The field is called email, but your checklist says login credentials. If final backend uses household user ID, update label and payload.
- No loading state.
- No validation error.
- No actual forgot-password API.
- Uses responder-like default email even though this is household mobile.

Checklist mapping:

- "User can log-in with their login credentials" - visually covered, not functionally wired.
- "Have forgot password?" - visually covered, not functionally wired.

Recommended final implementation:

```js
POST /api/auth/login
{
  login_id: "HHR-24001",
  password: "..."
}
```

Expected response:

```js
{
  token: "...",
  user: {
    id: "...",
    name: "...",
    login_id: "HHR-24001",
    role: "household_resident"
  },
  household: {
    id: "HH-2048",
    family_name: "Santos",
    is_setup_complete: false
  }
}
```

Login routing:

```text
if household.is_setup_complete === false:
  go to first-time setup
else:
  go to dashboard
```

Recommended components:

- `LoginScreen`
- `AuthCard`
- `CredentialInput`
- `PasswordInput`
- `ForgotPasswordLink`
- `LoginSubmitButton`
- `InlineAuthError`

## Page 2: First-Time Login / Onboarding

File:

`src/screens/OnboardingScreen.js`

Purpose:

- Required first-time setup.
- Collects home address.
- Confirms geotag.
- Collects phone, family relationship, household role, and family photo.

Current step state:

```js
const [step, setStep] = useState("address");
```

Current steps:

1. `address`
2. `details`

This is unskippable in the current prototype because the app cannot enter the main app until `onComplete` is called.

### Step 1: Address and Geotag

Current fields:

- Purok / building name
- Street name
- Barangay
- Province

Current derived value:

```js
const fullAddress = [purokOrBuilding, streetName, barangay, province]
  .map((item) => item.trim())
  .filter(Boolean)
  .join(", ");
```

Address is considered complete when all four fields have values.

Current geotag state:

```js
const [pin, setPin] = useState(null);
const [pinConfirmedAddress, setPinConfirmedAddress] = useState("");
```

Current geotag confirmation:

```js
const geotagConfirmed = Boolean(
  pin &&
  addressComplete &&
  pinConfirmedAddress &&
  pinConfirmedAddress === fullAddress
);
```

Current location button:

`Confirm with device geotag`

Logic:

1. Validate address fields first.
2. Request location permission with `Location.requestForegroundPermissionsAsync()`.
3. If permission is granted:
   - set `pin` to `{ x: 0.52, y: 0.45 }`
   - set `pinConfirmedAddress = fullAddress`
4. If permission is denied:
   - show error: `Location permission was not granted. Tap the map to pin the household manually.`

Important:

- It requests permission but does not read actual coordinates.
- It does not call `Location.getCurrentPositionAsync`.
- The pin is a fake UI coordinate, not latitude/longitude.

Current map behavior:

- Shows a custom pseudo-map with two road views.
- User taps the map to place a pin.
- Tap coordinates are converted to normalized values:

```js
{ x: 0.12 to 0.88, y: 0.16 to 0.82 }
```

Current geotag status text:

- `Not confirmed yet`
- `Reconfirm this pin after any address changes.`
- `Confirmed for {fullAddress}`

Continue validation:

- Must have completed address.
- Must have confirmed geotag.

Checklist mapping:

- "Make the device enable their location" - permission request exists.
- "Open a map to pin their location" - simulated map exists, not real map.
- "After sharing their location, put a form with selected address on same page" - covered by address preview + geotag section.
- "Can input unit / house number / street number" - partially covered as Purok/building and Street name. Add explicit unit/house number in final.

### Step 2: Household Details

Current fields:

- Confirmed resident address display
- Device phone number
- Relationship to family
- Household role
- Family display photo

Current relationship choices:

```js
["Parent", "Child", "Sibling", "Guardian"]
```

Current photo behavior:

- Uses `ImagePicker.launchImageLibraryAsync`.
- Allows editing.
- Aspect ratio 1:1.
- Image quality 0.75.

Finish validation:

- Phone required.
- Relationship required.
- Household role required.
- Photo required.

Current `onComplete` payload:

```js
{
  address: fullAddress,
  addressDetails: {
    barangay,
    province,
    purokOrBuilding,
    streetName
  },
  geotag: pin,
  householdRole,
  phone,
  photoUri,
  relationship
}
```

Important mismatch with your checklist:

- Your checklist says user can upload photo or skip for now.
- Current code requires photo and blocks finish if none.

Recommended final change:

- Add `Skip for now` action.
- Save `photoUri: null` if skipped.
- Do not block onboarding on photo.

Another checklist gap:

- "Ask user which family member is using the device" is not implemented as a member selector.
- Current code asks only relationship and household role.

Recommended final onboarding structure:

```text
Step 1: Enable location permission
Step 2: Real map pin
Step 3: Address confirmation + house/unit number
Step 4: Select family member using this device
Step 5: Relationship to family
Step 6: Optional photo upload
Step 7: Complete setup
```

Recommended final fields:

```js
{
  lat: number,
  lng: number,
  formatted_address: string,
  house_number: string,
  unit_number: string | null,
  street: string,
  barangay: string,
  city: string,
  province: string,
  selected_member_id: string,
  relationship_to_family: string,
  household_role: string,
  photo_uri: string | null
}
```

Recommended API calls:

```js
POST /api/household/setup/location
POST /api/household/setup/address
POST /api/household/setup/device-member
POST /api/household/setup/photo
POST /api/household/setup/complete
```

Recommended components:

- `FirstTimeSetupScreen`
- `LocationPermissionStep`
- `HouseholdMapPinStep`
- `AddressConfirmationForm`
- `FamilyMemberDeviceSelector`
- `RelationshipSelector`
- `PhotoUploadStep`
- `SetupProgressHeader`

## Page 3: Home Dashboard - No Disaster Active

File:

`src/screens/DashboardScreen.js`

Rendered when:

```js
disasterActive === false
```

Current top banner:

```text
NO CURRENT DISASTER
```

Current page title:

```text
Dashboard
Family readiness
```

Current sections:

1. Featured no-disaster card
2. Family members card
3. Trusted households card

### No-Disaster Card

Displays:

- Big `0`
- Label: `Active disasters`
- Status chip: `Ready`
- Title: `No current disaster`
- Body:
  - `Family member locations, device health, trusted households, and evacuation QR remain ready.`

Checklist mapping:

- "Should have No current disaster" - covered.

### Family Members Card

Title:

```text
Family members
Live household card
```

Component:

`MemberList`

Member display logic:

`src/components/MemberList.js`

If member has no device:

```text
No device registered
```

If active:

```text
{battery}% battery - Live location enabled
```

If inactive:

```text
{battery}% battery - Inactive device
```

Subtitle:

```text
{relationship} - {location}
```

Checklist mapping:

- Display family members - covered.
- Each member has battery level - covered if device exists.
- Live location if enabled - covered as label only.
- Active/inactive - covered.
- No device registered - covered.
- Relationship type - covered.

Important limitation:

- This uses static data from `src/data.js`.
- It does not read actual battery level.
- It does not track real live location.
- It does not register actual devices per household member.

Recommended final API:

```js
GET /api/household/members
POST /api/household/member/{id}/device
POST /api/location/update
```

### Trusted Households Card

Title:

```text
Trusted households
PIN required
```

Current trusted household list item displays:

- Family name
- Relationship
- Number of members
- Validation status
- Household ID
- Status chip

Actions:

- `Add trusted household`
- `Evacuation QR`

Clicking a trusted household:

- Calls `openTrustedDetail(household)` in `App.js`.
- Opens `TrustedHouseholdSheet`.
- If a trusted PIN exists, starts with `pin` flow.
- If no trusted PIN exists, starts with `setup` flow.

Checklist mapping:

- Display trusted households - covered.
- Card for all trusted households - covered.
- PIN before accessing trusted household - covered in prototype.
- QR access - covered.

Important mismatch:

- Your checklist says clicking trusted household should replace the family members card and show a back button.
- Current implementation opens a bottom sheet, not an in-page replacement.
- It has a `Return to family` button inside the sheet.

Recommended final behavior:

You can choose either:

1. Keep bottom sheet for a modern mobile UX.
2. Follow checklist exactly and make dashboard switch between:
   - `ownFamilyView`
   - `trustedHouseholdView`

Suggested state:

```js
const [viewingHousehold, setViewingHousehold] = useState({
  type: "own",
  household: ownHousehold
});
```

Then:

```text
Own family card
  -> tap trusted household
  -> PIN modal
  -> replace member card with trusted members
  -> show Back to family
```

## Page 4: Add Trusted Household

Implemented in:

`App.js`, inside `TrustedHouseholdSheet`

Current trigger:

`Add trusted household` button in Dashboard.

Current add flow:

```js
openAddTrusted()
```

Logic:

- Clears PIN entry and errors.
- Sets `afterPinAction = "add"`.
- If `trustedPin` exists, goes directly to add flow.
- If no PIN exists, starts setup flow.
- Opens trusted sheet.

### First Trusted Household PIN Setup

Current UI:

- Label: `Trusted household PIN`
- Title: `Set 4-digit PIN`
- Disclaimer:
  - `This PIN will be used to access all trusted households.`
- Field:
  - `4-digit pin`
- Button:
  - `Continue`

Current logic:

- If `newPin.length !== 4`, show `Enter a 4-digit PIN.`
- Else save to local state:

```js
setTrustedPin(newPin);
```

Then continue to add/detail flow.

Important gaps:

- PIN is stored only in memory.
- Closing/reloading the app loses it.
- No confirm PIN field.
- No SecureStore.
- No lockout / retry counter.
- No disclaimer about not sharing or recovery.

Recommended final implementation:

- Use `expo-secure-store`.
- Store PIN locally only.
- Add confirm PIN.
- Do not send PIN to backend unless you intentionally design server-side PIN verification.

Recommended key:

```js
trusted_hh_pin
```

### Add Trusted Household Form

Current UI:

- Label: `Add trusted household`
- Title: `Pending validation`
- Household ID input
- Reason input
- Info block:
  - `You are about to connect with "{newHouseholdId}". Give a reason. This will be pending until validation.`
- Button:
  - `Send request`

Current local submit:

```js
const newHousehold = {
  id,
  familyName: "Pending",
  relationship: "Trusted household",
  status: "info",
  validation: "Pending validation",
  idCard: `ID-${id.replace(/[^A-Z0-9]/gi, "")}`,
  reason: newReason,
  members: []
};
```

Then:

- Prepends to `trustedList`.
- Selects it.
- Sets `trustedFlow = "detail"`.

Checklist mapping:

- Input household ID - covered.
- Reason why - covered.
- Pending validation wording - covered.

Missing from checklist:

- Does not fetch registered last name.
- Does not display only last name + household ID before connecting.
- Does not display list of names after connection.
- Does not let user add each trusted household member's relationship to the family.
- Does not save to backend.
- Does not wait for validation from the other household or admin.

Recommended final add flow:

```text
1. User taps Add trusted household.
2. If no PIN, create PIN.
3. User enters household ID.
4. App calls GET /api/households/lookup/{household_id}.
5. App displays only:
   - registered family last name
   - household ID
6. User enters reason.
7. App sends POST /api/trusted-households.
8. Status is pending.
9. Once approved, app fetches member names.
10. User adds relationship labels for each trusted member.
11. Trusted household card appears.
```

Recommended APIs:

```js
GET /api/households/lookup/{household_id}
POST /api/trusted-households
GET /api/trusted-households
GET /api/trusted-households/{id}/members
POST /api/trusted-households/{id}/member-relationships
```

## Page 5: Trusted Household Detail / Access

Implemented in:

`App.js`, `TrustedHouseholdSheet`

Current access flow:

1. User taps trusted household.
2. `openTrustedDetail(household)` sets selected household.
3. If no PIN exists, starts PIN setup.
4. If PIN exists, asks for PIN.
5. If PIN matches, shows detail.
6. If PIN fails, shows `PIN did not match.` and haptic error.

Current detail content:

- Validation label
- Household name
- Status chip
- Household ID
- Reason
- ID card
- Member list if members exist
- Empty message:
  - `Registered family names will display here after validation.`
- `Evacuation QR` button
- `Return to family` button
- `Delete trusted household` button

Delete behavior:

- Removes selected household from local `trustedList`.
- Closes sheet.

Important gaps:

- No backend validation.
- No persisted trusted list.
- No persisted PIN.
- No real access check.
- No member relationship setup.
- No approval workflow.

Recommended components:

- `TrustedHouseholdList`
- `TrustedHouseholdCard`
- `TrustedPinModal`
- `TrustedHouseholdDetail`
- `AddTrustedHouseholdForm`
- `TrustedMemberRelationshipForm`

## Page 6: QR Code

Component:

`src/components/QrPanel.js`

Shown from:

- Dashboard `Evacuation QR` button.
- Trusted household detail `Evacuation QR`.
- Alerts standby/active screens.

Implemented in:

`App.js`

Current QR sheet:

```jsx
<QrPanel title="Evacuation QR" value="RESQPERATION-HH-2048-EVAC" />
```

Current QR component:

- Uses `react-native-qrcode-svg`.
- Size: 136.
- Value: passed string.
- Text:
  - `Scan this during evacuation updates or trusted-household validation.`

Checklist mapping:

- "Should have button to access their QR for evacuation update" - covered.
- "QR codes should remain" in disaster mode - covered via global QR sheet.

Important gaps:

- QR value is hard-coded.
- QR does not include household ID from backend.
- QR does not include active disaster/event ID.
- QR does not refresh.
- No QR screen, only bottom sheet.

Recommended final QR payload:

```js
{
  household_id: "HH-2048",
  family_name: "Santos",
  event_id: "EVT-2026-001",
  purpose: "evacuation_check_in",
  issued_at: "2026-06-06T10:30:00Z"
}
```

Recommended API:

```js
GET /api/household/qr
```

Recommended QR component:

- Display QR.
- Display household name and ID.
- Include refresh button.
- Include copy/share option if needed.
- Keep accessible from both no-disaster and disaster dashboards.

## Page 7: Home Dashboard - Disaster Active

File:

`src/screens/DashboardScreen.js`

Rendered when:

```js
disasterActive === true
```

Current activation sources:

- Alerts tab: `Start disaster drill`
- Emergency FAB sheet: `Send emergency alert`

This is not connected to backend broadcast.

Current top banner:

```text
DISASTER ACTIVE - UPDATE STATUS
```

Current page title:

```text
Dashboard
Response board
```

Current sections:

1. Active disaster card
2. Household status card
3. Family status list
4. Trusted-household response card

### Active Disaster Card

Displays:

- Big `1`
- Label: `Active disaster`
- Status chip: `Critical`
- Disaster type: `Flood warning`
- Date and message
- Additional info

Checklist mapping:

- Display disaster type - covered.
- Display date - covered.
- Display message - covered.
- Display additional info - covered.

Important gap:

- Static data from `src/data.js`.
- No backend `GET /api/disaster/active`.
- No real-time broadcast from HQ.

### Household Status Card

Current state:

```js
const [currentStatus, setCurrentStatus] = useState("safe");
const [pendingStatus, setPendingStatus] = useState("safe");
const [editingStatus, setEditingStatus] = useState(false);
const [showHistory, setShowHistory] = useState(false);
const [history, setHistory] = useState([
  { id: "st-001", label: "Safe", time: "08:40 AM", status: "safe" },
  { id: "st-002", label: "Info", time: "09:10 AM", status: "info" }
]);
```

Current status options:

Defined in:

`src/components/StatusActionGrid.js`

```js
[
  { status: "safe", label: "Safe" },
  { status: "critical", label: "Critical" },
  { status: "evacuate", label: "Evacuate" },
  { status: "info", label: "Info" }
]
```

Current status behavior:

- Initially `editingStatus` is false.
- Status buttons are disabled until user taps `Edit status`.
- When editing:
  - user selects one of four statuses.
  - `pendingStatus` changes.
  - button text changes to `{Label} selected`.
  - user taps `Save update`.
- Save behavior:
  - `currentStatus = pendingStatus`
  - `editingStatus = false`
  - prepend new history item with time `Now`

Current history behavior:

- User taps `Status history`.
- A local inline history block appears.
- Displays rows with clock icon, label/time, status chip.

Checklist mapping:

- Four status toggle - covered.
- User must click save - covered.
- Edit then save cycle - covered.
- Status history icon/button - covered as button, not icon-only.

Important mismatches:

- Your checklist says after save, button colors should turn gray, save button colors, and edit repeats. Current buttons become disabled with opacity, not specifically gray.
- Current UI does not show explicit text like `Last saved status: Safe at 09:10 AM`; it shows only current chip and history.
- Current statuses are `safe`, `critical`, `evacuate`, `info`. Claude suggestion recommends `safe`, `evacuated`, `unsafe`, and optional none. Final statuses should be standardized with backend.
- Status is only local state, not saved to backend.

Recommended final status values:

Choose one consistent set. A good household set:

```js
[
  { value: "safe", label: "Safe" },
  { value: "evacuated", label: "Evacuated" },
  { value: "needs_help", label: "Needs Help" },
  { value: "unsafe", label: "Unsafe" }
]
```

Or if you want to preserve current prototype names:

```js
[
  "safe",
  "evacuate",
  "critical",
  "info"
]
```

Recommended final save flow:

```text
No saved status:
  -> user selects status
  -> Save button becomes enabled
  -> user taps Save
  -> status is sent to backend
  -> buttons become gray/locked
  -> show Last saved: {status} at {time}
  -> show Edit button

Saved status exists:
  -> user taps Edit
  -> buttons become active again
  -> user selects another status
  -> user taps Save
  -> backend stores status history
  -> UI locks again
```

Recommended API:

```js
GET /api/status/current
POST /api/status
GET /api/status/history
```

Recommended status payload:

```js
{
  event_id: "EVT-2026-001",
  household_id: "HH-2048",
  status: "safe",
  notes: null
}
```

### Family Status List

Current behavior:

- Shows the same local `familyMembers` list from no-disaster mode.
- Each member has status chip, relationship/location, device meta.

Recommended final behavior:

- Show current status per family member if backend supports member-level status.
- Otherwise show household-level status plus member device/location status.

### Trusted-Household Response

Current behavior:

- Shows trusted households with status chips.
- Clicking a trusted household opens PIN-protected bottom sheet.
- QR remains available.
- Main screen button sets `disasterActive` false.

Checklist mapping:

- Can do same to trusted households when disaster is broadcasted - partially covered.
- Back button/main screen - covered as `Main screen`, but it ends disaster drill instead of only navigating back.
- QR remains - covered.

Important gap:

- You likely should not let one household edit another trusted household's disaster status unless your policy requires it. Safer design: trusted household status should be read-only unless the trusted household granted delegated update permission.

## Page 8: Alerts

File:

`src/screens/AlertsScreen.js`

Tab:

`alerts`

Purpose:

- Shows alert standby when no disaster.
- Shows active alert details and update controls during disaster.
- Includes drill controls.

No-disaster mode:

- Banner: `NO CURRENT DISASTER`
- Title: `Standby`
- Current alerts: `0`
- Status chip: Safe
- Text: no current disaster.
- Button: `Start disaster drill`
- Button: `Evacuation QR`
- Prepared alert channels card:
  - Household broadcast
  - Evacuation update

Active mode:

- Banner: `ALERT BROADCAST ACTIVE`
- Title: `Broadcast status`
- Active disaster card
- Status update card:
  - status action grid
  - Save broadcast / Edit broadcast
  - Evacuation QR
- Alert actions:
  - `End drill`

Important:

- This is a drill/prototype screen.
- Not in your checklist as a required page, but useful for testing disaster activation flow.
- It controls `disasterActive` globally.

Recommended final:

- Replace drill controls with backend-driven `GET /api/disaster/active`.
- Use push notification or broadcast events from HQ.
- Keep an alert details screen if the household needs to view disaster history.

## Page 9: Map

File:

`src/screens/MapScreen.js`

Tab:

`map`

Purpose:

- Displays a full-screen map-like interface with markers and bottom sheet details.

Current implementation:

- Not a real map.
- Uses a custom `View` canvas with zones, routes, and absolute-position markers.
- Markers come from `src/data.js`.

State:

```js
const [selectedMarker, setSelectedMarker] = useState(mapLocations[0]);
const [sheetMode, setSheetMode] = useState("peek");
```

Marker selection:

- Tapping marker sets selected marker.
- Opens bottom sheet expanded.

Current top banner:

```text
MAP - FLOOD RESPONSE
```

Bottom sheet content:

- Marker name
- Marker address
- Status chip
- Field instruction:
  - `Confirm household status before moving to the next waypoint. Keep the evacuation QR ready.`
- Buttons:
  - `Route to point`
  - `Mark safe`

Important gaps:

- No actual GPS.
- No real map tiles.
- No real household route.
- No QR button directly in map bottom sheet.
- Buttons have no handlers.

Recommended final:

- Use `react-native-maps`.
- Use `expo-location` for current location.
- Use backend household pin and trusted household pins.
- Show evacuation center and route markers if needed.

Recommended APIs:

```js
GET /api/household/map
GET /api/disaster/active
POST /api/location/update
```

Recommended map data:

```js
{
  own_household: {
    id: "HH-2048",
    lat: 10.3157,
    lng: 123.8854,
    status: "safe"
  },
  trusted_households: [
    {
      id: "HH-7781",
      family_name: "Dela Cruz",
      lat: 10.316,
      lng: 123.887,
      status: "needs_help"
    }
  ],
  evacuation_centers: []
}
```

## Page 10: Reports

File:

`src/screens/ReportsScreen.js`

Tab:

`reports`

Purpose:

- Lets household submit field-style incident/status report.
- Shows local report history.

Current state:

```js
const [incidentType, setIncidentType] = useState("Flood impact");
const [message, setMessage] = useState("Water is rising near the west gate.");
const [additionalInfo, setAdditionalInfo] = useState("Two seniors need transport if evacuation starts.");
const [selectedStatus, setSelectedStatus] = useState("info");
const [editing, setEditing] = useState(true);
const [reports, setReports] = useState(reportHistory);
```

Current form:

- Status action grid
- Disaster type
- Message
- Additional info
- Save report button

Current save:

```js
{
  id: `rp-${Date.now()}`,
  meta: "Now",
  status: selectedStatus,
  title: `${incidentType}: ${message}`
}
```

Then:

- Prepends to local report history.
- Sets editing false.

Important:

- Not in your checklist as a separate required page.
- Useful if households can send incident reports outside the disaster status toggle.
- Currently local-only.

Recommended final:

```js
POST /api/household/reports
GET /api/household/reports
```

## Page 11: Emergency FAB

Component:

`src/components/EmergencyFAB.js`

Rendered by:

`App.js`

Purpose:

- Floating emergency action button.
- Opens emergency bottom sheet.

Current behavior:

- Press FAB opens emergency sheet.
- Long press shows tooltip `Emergency trigger`.

Emergency sheet content in `App.js`:

- Title: `Emergency action`
- Heading: `ResQperation`
- Text:
  - `Send an emergency update to family members and trusted households.`
- StatusActionGrid
- Button:
  - `Send emergency alert`
- Button:
  - `Report incident`

Current send emergency alert behavior:

- Sends haptic success.
- Sets `disasterActive = true`.
- Closes emergency sheet.
- Sets active tab to dashboard.

Current report incident behavior:

- Closes emergency sheet.
- Sets active tab to reports.

Important:

- This is a local drill trigger.
- In final app, household emergency alert should probably call backend and notify HQ/rescuers/trusted households.

Recommended final API:

```js
POST /api/household/emergency-alert
```

Payload:

```js
{
  event_id: "EVT-2026-001",
  status: "needs_help",
  message: "Emergency alert from household",
  lat: 10.3157,
  lng: 123.8854
}
```

## Shared Components

### `AppText`

File:

`src/components/AppText.js`

Purpose:

- Consistent typography.
- Uses `type` styles from `src/theme.js`.
- Supports `uppercase`, `color`, and `variant`.
- Allows font scaling.

### `ActionButton`

File:

`src/components/ActionButton.js`

Purpose:

- Shared button with variants and haptics.

Props:

- `children`
- `icon`
- `variant`
- `status`
- `onPress`
- `disabled`
- `accessibilityLabel`
- `haptic`
- `style`

Variants:

- `primary`
- `secondary`
- `ghost`
- `status`

Status buttons use `statusTokens`.

### `StatusActionGrid`

File:

`src/components/StatusActionGrid.js`

Purpose:

- 2x2 grid of status buttons.

Current statuses:

- Safe
- Critical
- Evacuate
- Info

Props:

- `selectedStatus`
- `disabled`
- `onSelect`

### `StatusChip`

File:

`src/components/StatusChip.js`

Purpose:

- Compact status badge with icon and label.

### `StatusBanner`

File:

`src/components/StatusBanner.js`

Purpose:

- Top banner for app-wide disaster/no-disaster state.
- Animated into view.
- Can be swiped upward to dismiss.
- Can use blur/glass mode.

### `BottomSheet`

File:

`src/components/BottomSheet.js`

Purpose:

- Draggable bottom sheet.

Modes:

- `hidden`
- `peek`
- `expanded`

Used by:

- QR sheet
- emergency sheet
- trusted household sheet
- map location detail sheet

### `BottomNav`

File:

`src/components/BottomNav.js`

Tabs:

- Dashboard
- Map
- Reports
- Alerts

### `MemberList`

File:

`src/components/MemberList.js`

Purpose:

- Converts member data to `ListItem` rows.

Device metadata:

- `device === "none"` -> `No device registered`
- `device === "active"` -> `{battery}% battery - Live location enabled`
- otherwise -> `{battery}% battery - Inactive device`

### `QrPanel`

File:

`src/components/QrPanel.js`

Purpose:

- Displays QR code with title and description.

## Design Tokens

File:

`src/theme.js`

Main colors:

```js
primary: "#000000"
primaryContainer: "#111C2D"
onPrimary: "#FFFFFF"
surface: "#F7F9FB"
surfaceLow: "#F2F4F6"
surfaceLowest: "#FFFFFF"
surfaceHigh: "#E6E8EA"
onSurface: "#191C1E"
outlineVariant: "#C6C6CD"
safe: "#10B981"
critical: "#EF4444"
evacuate: "#F59E0B"
info: "#3B82F6"
```

Status tokens:

- `safe`
  - label: Safe
  - green background
- `critical`
  - label: Critical
  - red background
- `evacuate`
  - label: Evacuate
  - amber background
- `info`
  - label: Info
  - blue background

Important note:

- Claude suggestion uses color anchor `#0f1c2d`.
- Current prototype uses `#111C2D` as primary container and `#000000` as primary.
- For final consistency, choose one palette and apply globally.

## Checklist Coverage Summary

### Login

Covered visually:

- Login screen
- Password field
- Forgot password button

Needs backend:

- credential validation
- forgot-password API
- loading/error states
- household role check

### First-Time Login

Covered:

- Unskippable flow after login
- Location permission request
- Map-like pin placement
- Address preview
- Street/building/barangay/province fields
- Relationship to family
- Photo upload

Missing or needs change:

- Real map
- Actual lat/lng
- explicit house/unit number
- selected family member using the device
- upload photo or skip option
- backend save
- first-time flag from backend

### Home Page - No Disaster

Covered:

- `No current disaster`
- family members
- relationships
- battery/device/live/inactive/no-device labels
- trusted household cards
- add trusted household
- QR access

Mismatch:

- Trusted household detail opens bottom sheet instead of replacing family member card.

Needs backend:

- real household data
- real device status
- real live location
- trusted household list from server

### Trusted Household

Covered:

- PIN before access
- PIN setup first
- household ID input
- reason input
- pending validation wording
- trusted household detail
- delete action
- QR action

Missing:

- PIN persistence
- confirm PIN
- lookup registered last name
- approval workflow
- member relationship mapping
- replacing family card with trusted household view
- backend save

### Disaster Active Home

Covered:

- disaster information
- four status buttons
- edit/save cycle
- status history
- family statuses
- trusted household status list
- QR remains accessible

Needs improvement:

- last saved status text
- exact gray/locked button behavior after save
- backend status save
- backend status history
- real disaster broadcast
- final status value names

### Additional

Covered:

- PIN protection concept
- first PIN setup on first trusted household access/add

Needs improvement:

- SecureStore
- confirm PIN
- stronger disclaimer
- recovery/reset process

## Current Prototype Gaps

The current project is a strong UI prototype, but not a final integrated app.

Major gaps:

1. No API client.
2. No Laravel connection.
3. No real auth.
4. No persisted session.
5. No persisted PIN.
6. No persisted onboarding completion.
7. No real map/geocoding.
8. No real device battery/location tracking.
9. No push notifications.
10. No real disaster broadcast.
11. No backend status history.
12. No trusted household approval workflow.
13. No QR payload from backend.
14. No actual family member device registration.

## Claude Suggestion File Summary

The attached `pasted-text.txt` is a Node script that generates a DOCX reference. It is not source code for the app. It recommends a final architecture that is more backend-ready than the current prototype.

Useful recommendations from it:

- Use Expo Router for file-based routes.
- Use Zustand for app state.
- Use Axios for API calls.
- Use Expo SecureStore for tokens and PIN.
- Use React Hook Form + Zod for validation.
- Use Laravel Sanctum for token auth.
- Use Pusher/Soketi or Laravel Broadcasting for disaster activation.
- Use Expo Notifications for push alerts.
- Use `react-native-qrcode-svg` for QR.
- Add proper API endpoints for household setup, trusted households, status, disaster, QR, and devices.

Important difference:

- The current project is plain JavaScript, single `App.js` shell, local state navigation, no API.
- Claude's suggestion is a TypeScript, Expo Router, Zustand, Axios, SecureStore architecture.

You can use Claude's suggestion as a development target, but do not treat it as current implementation.

## Recommended Final Folder Structure

If starting the actual capstone household mobile, this is a practical structure:

```text
src/
  app/
    AppRoot.jsx
  navigation/
    AuthNavigator.jsx
    HouseholdTabs.jsx
  screens/
    auth/
      LoginScreen.jsx
      ForgotPasswordScreen.jsx
    onboarding/
      LocationPermissionScreen.jsx
      HouseholdPinMapScreen.jsx
      AddressConfirmScreen.jsx
      DeviceMemberScreen.jsx
      PhotoSetupScreen.jsx
    household/
      HomeScreen.jsx
      DisasterHomeScreen.jsx
      MapScreen.jsx
      ReportsScreen.jsx
      AlertsScreen.jsx
      QrScreen.jsx
  components/
    common/
      AppText.jsx
      ActionButton.jsx
      FieldInput.jsx
      Card.jsx
      BottomSheet.jsx
      BottomNav.jsx
      StatusChip.jsx
      StatusBanner.jsx
      EmptyState.jsx
      LoadingState.jsx
      ErrorState.jsx
    household/
      FamilyMemberCard.jsx
      FamilyMemberList.jsx
      TrustedHouseholdCard.jsx
      TrustedHouseholdDetail.jsx
      AddTrustedHouseholdForm.jsx
      TrustedPinModal.jsx
      HouseholdStatusToggle.jsx
      StatusHistorySheet.jsx
      QrPanel.jsx
      DisasterInfoCard.jsx
  services/
    api.js
    authApi.js
    householdApi.js
    disasterApi.js
    trustedHouseholdApi.js
    statusApi.js
    deviceApi.js
  hooks/
    useAuthSession.js
    useHouseholdSetup.js
    useHouseholdMembers.js
    useTrustedHouseholds.js
    useDisasterStatus.js
    useLocationTracking.js
  storage/
    secureStorage.js
    pinStorage.js
  constants/
    colors.js
    statuses.js
  utils/
    validators.js
    dateFormat.js
```

## Recommended API Endpoints

These are adapted from the Claude suggestion and the current prototype needs.

### Auth

```text
POST /api/auth/login
POST /api/auth/forgot-password
GET  /api/auth/me
POST /api/auth/logout
```

### Household Setup

```text
GET  /api/household/me
POST /api/household/setup/location
POST /api/household/setup/address
POST /api/household/setup/device-member
POST /api/household/setup/photo
POST /api/household/setup/complete
```

### Household Members

```text
GET  /api/household/members
POST /api/household/member/{id}/device
POST /api/location/update
```

### Disaster

```text
GET /api/disaster/active
GET /api/disaster/history
```

### Status

```text
GET  /api/status/current
POST /api/status
GET  /api/status/history
```

### Trusted Households

```text
GET  /api/trusted-households
GET  /api/households/lookup/{household_id}
POST /api/trusted-households
GET  /api/trusted-households/{id}
GET  /api/trusted-households/{id}/members
GET  /api/trusted-households/{id}/status
POST /api/trusted-households/{id}/member-relationships
DELETE /api/trusted-households/{id}
```

### QR

```text
GET /api/household/qr
```

### Emergency / Reports

```text
POST /api/household/emergency-alert
POST /api/household/reports
GET  /api/household/reports
```

### Device / Notifications

```text
POST /api/device/token
POST /api/device/battery
POST /api/device/location
```

## Recommended Data Models

### Auth Session

```js
{
  token: string,
  user: {
    id: string,
    name: string,
    login_id: string,
    role: "household_resident"
  },
  household: {
    id: string,
    family_name: string,
    is_setup_complete: boolean
  }
}
```

### Household

```js
{
  id: "HH-2048",
  family_name: "Santos",
  formatted_address: "24 Mabini Street, Barangay 7",
  house_number: "24",
  unit_number: null,
  street: "Mabini Street",
  barangay: "Barangay 7",
  city: "Cebu City",
  province: "Cebu",
  lat: 10.3157,
  lng: 123.8854,
  is_setup_complete: true
}
```

### Household Member

```js
{
  id: "fm-001",
  household_id: "HH-2048",
  name: "Mara Santos",
  relationship: "Mother",
  has_device: true,
  device_status: "active",
  battery_level: 82,
  is_location_sharing: true,
  last_lat: 10.3157,
  last_lng: 123.8854,
  last_seen_at: "2026-06-06T10:20:00Z",
  status: "safe"
}
```

### Trusted Household

```js
{
  id: "link-001",
  household_id: "HH-2048",
  trusted_household_id: "HH-7781",
  family_name: "Dela Cruz",
  status: "pending",
  reason: "Mutual check-in during flood alerts",
  validation: "Pending validation",
  members: []
}
```

### Disaster

```js
{
  id: "EVT-2026-001",
  type: "Flood warning",
  date: "2026-06-06",
  message: "River level rising near Barangay 7.",
  additional_info: "Move essential documents and medicine above waist height.",
  status: "active"
}
```

### Household Status Update

```js
{
  id: "st-001",
  event_id: "EVT-2026-001",
  household_id: "HH-2048",
  status: "safe",
  reported_by: "household",
  reported_at: "2026-06-06T10:30:00Z"
}
```

## Recommended Database Tables

From the Claude suggestion, these tables match your checklist well:

### `households`

Fields:

- `id`
- `barangay_id`
- `family_name`
- `house_number`
- `street`
- `lat`
- `lng`
- `formatted_address`
- `is_setup_complete`

### `household_members`

Fields:

- `id`
- `household_id`
- `name`
- `relationship`
- `has_device`
- `device_token`
- `battery_level`
- `last_lat`
- `last_lng`
- `last_seen_at`
- `is_location_sharing`

### `household_status_reports`

Fields:

- `id`
- `event_id`
- `household_id`
- `status`
- `reported_by`
- `reporter_id`
- `reported_at`

Important:

- Keep full history.
- Do not overwrite history rows.
- For current status, query latest row by `reported_at`.

### `trusted_household_links`

Fields:

- `id`
- `household_id`
- `trusted_household_id`
- `status`
- `reason`
- `approved_at`

### `disaster_events`

Fields:

- `id`
- `barangay_id`
- `type`
- `name`
- `message`
- `status`
- `declared_at`
- `ended_at`
- `total_households`

### `device_tokens`

Fields:

- `id`
- `user_id`
- `household_member_id`
- `expo_push_token`
- `platform`
- `created_at`

## Recommended Priority Build Order

Build in this order for the actual capstone:

1. Auth API + login screen
2. Session persistence with SecureStore
3. First-time setup API and setup-complete flag
4. Household dashboard with real family members
5. QR payload from backend
6. Disaster active/no-disaster API check
7. Status toggle save/edit/history API
8. Trusted household PIN with SecureStore
9. Trusted household add/approval/member relationship flow
10. Real map and household location
11. Device battery/location updates
12. Push notifications / disaster broadcast

## Current Source Files to Reuse First

Best files to reuse:

- `src/theme.js` - design token base
- `src/components/AppText.js`
- `src/components/ActionButton.js`
- `src/components/Card.js`
- `src/components/FieldInput.js`
- `src/components/ListItem.js`
- `src/components/MemberList.js`
- `src/components/StatusChip.js`
- `src/components/StatusBanner.js`
- `src/components/StatusActionGrid.js`
- `src/components/BottomSheet.js`
- `src/components/QrPanel.js`

Screens worth reusing as UI base:

- `src/screens/LoginScreen.js` - change labels/API.
- `src/screens/OnboardingScreen.js` - split into clearer steps and add real map.
- `src/screens/DashboardScreen.js` - good checklist structure.
- `src/screens/MapScreen.js` - visual placeholder only, replace map logic.
- `src/screens/ReportsScreen.js` - optional household report page.
- `src/screens/AlertsScreen.js` - useful for alert/drill testing.

## Final Notes

The household mobile prototype is more complete visually than the rescuer prototype. It already covers most of your checklist as screens and UI states.

The main work for the actual capstone is not designing the screens from scratch. The main work is:

- replacing static data with Laravel API data
- adding secure persistence
- making onboarding truly tied to first-login backend state
- implementing real location and map behavior
- implementing real trusted household approval and PIN protection
- implementing disaster broadcast/status history with backend storage

