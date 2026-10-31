# OneSignal API Integration: RESQPERATION Notifications

This document describes the OneSignal mobile push integration used by
RESQPERATION: the required credentials, app configuration, device registration,
server-side send API, notification payloads, delivery flow, and troubleshooting.
It is self-contained; no private credentials are included.

## 1. Integration overview

The Laravel backend sends mobile push notifications to OneSignal. The mobile app
initializes the OneSignal React Native SDK with the public OneSignal App ID,
obtains a push subscription, and registers that subscription with the
authenticated Laravel API. The backend selects eligible subscriptions from
`device_tokens` and calls the OneSignal REST API.

```text
RESQPERATION mobile app
  ├─ OneSignal SDK: initialize with the public App ID; request notification permission
  └─ Laravel API: register subscription ID using the signed-in user's access token
          ↓
Laravel backend
  ├─ stores subscription ID and account/device metadata
  ├─ selects active, opted-in recipients for the notification audience
  └─ POSTs to OneSignal using the private REST API key
          ↓
OneSignal ── platform push service (FCM for Android / APNs for iOS) ──> device
```

The browser notification system is a separate Web Push integration. Its VAPID
keys and browser subscription endpoints are not OneSignal credentials and are
not used for OneSignal mobile pushes.

## 2. Credentials and where to configure them

Obtain credentials from the team's OneSignal account through the OneSignal
dashboard. Use the same OneSignal app for the backend and mobile build.
Never paste private credentials into chat, source code, a mobile app, or a
committed `.env` file.

| Value | Purpose | Where it belongs |
|---|---|---|
| **OneSignal App ID** | Identifies the OneSignal app. It is a public identifier, not an API secret. | Laravel `ONESIGNAL_APP_ID`; mobile build variable `EXPO_PUBLIC_ONESIGNAL_APP_ID`. Both must identify the same app. |
| **OneSignal REST API key** | Authorizes the backend to send notifications and query app status. | Laravel `ONESIGNAL_API_KEY` only. Keep it server-side; never add it to Expo variables, frontend code, or a public API response. |
| **OneSignal base URL** | OneSignal REST API host. | Optional Laravel `ONESIGNAL_BASE_URL`; defaults to `https://api.onesignal.com`. |
| **Firebase Cloud Messaging (FCM) v1 service-account credentials** | Lets OneSignal deliver Android push notifications through Firebase. | Configure in the OneSignal app's Android platform settings. Do not send this credential to the mobile app or commit its JSON file. |
| **Apple Push Notification service (APNs) credentials** | Required by OneSignal if iOS push delivery is enabled for this app. | Configure in the OneSignal app's iOS platform settings. Keep the signing key/certificate private. |
| **Laravel API base URL** | Mobile app's own backend URL, not a OneSignal endpoint. | Mobile `EXPO_PUBLIC_API_BASE_URL`, normally ending in `/api/v1`. |

### Backend environment

Set these in the backend's local `.env` or deployment secret manager:

```dotenv
ONESIGNAL_APP_ID=<OneSignal-App-ID>
ONESIGNAL_API_KEY=<OneSignal-REST-API-key>
# Optional; defaults to https://api.onesignal.com
ONESIGNAL_BASE_URL=https://api.onesignal.com
# Optional; defaults to 10000 eligible recipients
ONESIGNAL_MAX_RECIPIENTS=10000
```

Do not copy the live API key into this document or commit it.

After changing Laravel environment values, refresh cached configuration and
restart long-running queue workers:

```powershell
php artisan config:clear
```

Production deployments should inject secrets through the deployment platform's
secret manager and must not expose `.env` or debug configuration publicly.
If a REST API key is exposed, revoke/rotate it in OneSignal and update the
backend secret.

### Mobile app configuration

Set the public App ID when building the Expo mobile app:

```dotenv
EXPO_PUBLIC_ONESIGNAL_APP_ID=<same-OneSignal-App-ID-as-the-backend>
EXPO_PUBLIC_API_BASE_URL=<backend-origin>/api/v1
```

The App ID is public and is expected to be bundled in the app. The REST API key
is never required by the mobile app. The OneSignal native SDK is not available
in Expo Go; use a development or production native build. Native OneSignal
configuration changes require rebuilding and reinstalling the app.

### Platform push-provider setup

For Android, add valid Firebase Cloud Messaging HTTP v1 service-account
credentials to the Android configuration for the same OneSignal app. A
OneSignal App ID and REST API key alone do not enable Android delivery.
Ensure the installed build uses the Firebase project associated with those
credentials.

For iOS, configure the corresponding APNs credentials in OneSignal before
expecting iOS delivery. The backend supports `android` and `ios` device platform
values, but push delivery still depends on valid provider credentials and an
installed native app with notification permission.

## 3. OneSignal REST API: send a push

### Request

| Field | Value |
|---|---|
| Method | `POST` |
| URL | `https://api.onesignal.com/notifications` |
| Authentication | `Authorization: Key <ONESIGNAL_API_KEY>` |
| Content type | `application/json` |
| Accept | `application/json` |

Use the REST API key in the `Key` authorization scheme. Do not use a browser,
mobile client, or unauthenticated endpoint to send through OneSignal.

The Laravel readiness command authenticates with the same header and checks
the app with:

```http
GET https://api.onesignal.com/apps/{ONESIGNAL_APP_ID}
Authorization: Key <ONESIGNAL_API_KEY>
Accept: application/json
```

### Request body used by Laravel

```json
{
  "app_id": "<ONESIGNAL_APP_ID>",
  "idempotency_key": "11111111-2222-4333-8444-555555555555",
  "target_channel": "push",
  "include_subscription_ids": [
    "<ONESIGNAL_SUBSCRIPTION_ID>"
  ],
  "headings": {
    "en": "Example alert"
  },
  "contents": {
    "en": "This is an example mobile push notification."
  },
  "priority": 10,
  "data": {
    "type": "example",
    "reference_id": "123"
  }
}
```

Replace all placeholders with valid values. The subscription IDs are OneSignal
push subscription IDs registered by the mobile SDK, not Laravel user IDs,
Firebase registration tokens, or the OneSignal App ID. Laravel targets explicit
subscription IDs using `include_subscription_ids`; audience selection happens
on the server before this request. The `data` object is custom app data, not
display text.

The shared sender used for operational broadcasts and dispatches batches
subscriptions in groups of up to 2,000 and includes an idempotency key so that
queue retries do not create duplicate submissions for the same planned send.
Rescue-dispatch sends derive a stable key from the app, assignment, and sorted
recipient IDs. Other internal senders use the same endpoint but may omit
optional fields such as the idempotency key.

### Direct API smoke-test example

Use only a test subscription and a key stored in a local environment variable.
Never place a real key inline in shell history or a shared command:

```bash
curl --request POST "https://api.onesignal.com/notifications" \
  --header "Authorization: Key ${ONESIGNAL_API_KEY}" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "app_id": "<ONESIGNAL_APP_ID>",
    "target_channel": "push",
    "include_subscription_ids": ["<ONESIGNAL_SUBSCRIPTION_ID>"],
    "headings": {"en": "Test notification"},
    "contents": {"en": "OneSignal integration test."},
    "data": {"type": "integration_test"}
  }'
```

The direct smoke test bypasses Laravel audience selection, job queues, and
database status tracking. Prefer the Laravel status command and app flow for
normal integration checks.

### Successful response and delivery meaning

A successful OneSignal API response contains an `id` identifying the provider
notification. Laravel records provider IDs and accepted recipient counts for
broadcast delivery. An HTTP success/provider notification ID means OneSignal
accepted the request; it does **not** prove that the operating system displayed
the notification or that a user saw it. Confirm actual delivery in OneSignal's
delivery reports and on a test device.

Minimal successful-response shape:

```json
{
  "id": "<ONESIGNAL_NOTIFICATION_ID>"
}
```

If OneSignal returns invalid subscription IDs, Laravel excludes those IDs from
the accepted count and marks only those rejected subscriptions inactive when
the database supports that field. Refresh registration by opening the installed
app and signing in again.

## 4. Laravel API: mobile device registration

The mobile app registers its OneSignal push subscription against the
RESQPERATION API after login and when the subscription changes.

| Item | Value |
|---|---|
| Method | `POST` |
| Path | `/api/v1/mobile/device-token` |
| Authentication | Laravel Sanctum bearer access token |
| Allowed signed-in roles | `household_resident`, `rescuer` |
| Content type | `application/json` |

Header:

```http
Authorization: Bearer <SANCTUM_ACCESS_TOKEN>
Content-Type: application/json
Accept: application/json
```

Example request:

```json
{
  "device_uuid": "<stable-device-identifier>",
  "device_name": "RESQPERATION mobile",
  "platform": "android",
  "player_id": "<ONESIGNAL_SUBSCRIPTION_ID>",
  "push_token": "<platform-push-token>",
  "one_signal_user_id": "<ONESIGNAL_USER_ID>",
  "push_provider": "onesignal",
  "notification_permission_status": "granted"
}
```

`platform` must be `android` or `ios`. `notification_permission_status` must be
one of `granted`, `denied`, `undetermined`, `unavailable`, or `error`.
`player_id` is the OneSignal push subscription ID; the mobile client can also
send null when OneSignal has not created a subscription yet. The endpoint also
accepts `push_token`, `one_signal_user_id`, `battery_level`, and
`signal_strength`; those optional values are only saved if matching columns
exist. The current `device_tokens` schema includes `player_id` but not
`push_token` or `one_signal_user_id`, so only the subscription ID is stored for
OneSignal delivery.

The backend links the registration to the authenticated user and role; clients
must not select or claim another account's recipient identity. Household
registrations inherit `household_id` from the signed-in user's account. A
registration is keyed to the device UUID and account, so another device gets
another row while an updated subscription replaces the existing device's
subscription. A successful
response has a message and a `data` object containing `permission_status`,
`push_token_saved`, and `storage_column`. A missing registration table/required
schema returns a service-unavailable error; invalid fields return validation
errors, and an unsupported role returns `403`.

After login (or household device-owner selection), the mobile app starts
registration asynchronously, logs in to the OneSignal SDK with the authenticated
RESQPERATION user ID, asks for notification permission, opts in when permitted,
and saves the current subscription. The app retries in authenticated screens
and refreshes the registration as the subscription changes or the app returns
to the foreground. Devices can have a `device_tokens` row before OneSignal has
created a usable subscription; only a real subscription ID can receive pushes.
The app does not need the REST API key.

## 5. Sending through RESQPERATION

### Disaster-event broadcast

The authenticated web/admin API creates a broadcast at:

```http
POST /api/v1/disaster-events/{eventId}/broadcasts
Authorization: Bearer <SANCTUM_ACCESS_TOKEN>
Content-Type: application/json
```

The caller must have the backend's authorized disaster-event access. The
request body follows this shape (the four status keys must be distinct and
valid; for `selected_puroks`, provide at least one valid purok):

```json
{
  "broadcast_title": "Flood warning",
  "message": "Move to a safe location now.",
  "severity_id": 1,
  "scope_type": "barangay_wide",
  "estimated_duration": "2 hours",
  "allowed_statuses": ["safe", "unsafe", "need_help", "evacuated"],
  "direct_puroks": []
}
```

`scope_type` is either `barangay_wide` or `selected_puroks`. For
`selected_puroks`, use for example:

```json
"scope_type": "selected_puroks",
"direct_puroks": [{"name": "Sitio Example", "priority": "high"}]
```

The title and message are limited to 50 characters each by the current
RESQPERATION API. `estimated_duration` must be a value such as `"2 hours"` or
`"1 day"`. `allowed_statuses` contains exactly four distinct supported status
keys. Optional fields include `severity_id`, `target_area`, and `channel`.

The backend saves the broadcast and queues delivery; a successful save is not
proof that OneSignal or a device accepted/delivered it. Delivery is reported
separately in the broadcast history. Household Android subscriptions are
filtered to the requested area when applicable. Rescuers receive the message
and broadcast metadata; their notification does not include household status
choices.

### Other notification-producing workflows

Laravel services/jobs also submit notifications for rescue dispatches, dispatch
updates, household status reminders, and trusted-household requests. These
senders use the same credentials, `/notifications` endpoint, and active-device
selection rules. Dispatch pushes target selected responder subscriptions and
include app data such as `type`, `assignment_id`, `event_id`, and dispatch alarm
settings. Mobile notification taps route by the `data.type` value.

### Queue workers

Broadcast delivery and dispatch delivery run through Laravel's
`operations_outbox` database queue. A worker must consume the relevant queues
for saved broadcasts and dispatch pushes to reach OneSignal. The local
development worker command is:

```powershell
php artisan queue:work operations_outbox --queue=broadcasts,operations,navigation --sleep=1 --tries=3 --timeout=120
```

Run an equivalent supervised worker in deployed environments. Monitor failed
jobs and queue health; stopping a worker can leave a saved notification
undelivered.

## 6. Readiness check

From the Laravel backend directory, run:

```powershell
php artisan onesignal:status
```

This read-only check validates that the backend can authenticate to the
OneSignal app and reports the app ID, whether Android FCM v1 is configured, the
provider's messageable-device count, and the number of locally registered
Android subscriptions. It does not send a notification or reveal the API key.
The command fails if the app ID/key is missing, the app cannot be checked, or
Android FCM v1 is not configured. Provider messageable-device count is the
OneSignal app's total; the local Android count includes only active,
permission-granted household/rescuer subscriptions in this backend.

For a complete delivery test:

1. Configure the OneSignal app's platform credentials.
2. Set matching backend and mobile App IDs; set the REST API key only on the backend.
3. Confirm the reviewed device-identity migration is applied (do not rebuild the
   shared schema), then start the backend API and queue worker.
4. Install a native development/production build (not Expo Go), sign in, and
   grant notification permission.
5. Confirm the authenticated device registration response reports
   `push_token_saved: true`.
6. Run `php artisan onesignal:status` and check for a registered, messageable device.
7. Submit a test notification/broadcast and check the API/queue result, OneSignal
   reports, and the physical device.

## 7. Recipient eligibility and platform behavior

Laravel selects registered OneSignal subscriptions that have a non-empty,
usable subscription ID. Where the database has these fields, it also filters
for `push_provider=onesignal`, granted notification permission, and active
registration. Workflow-specific filters constrain role, platform, responder,
household, member, or selected purok. A device without a usable subscription
cannot receive a push.

Android delivery requires the Firebase credential and an Android build
registered against the matching Firebase/OneSignal app. iOS delivery requires
APNs configuration and iOS notification permission. Device power settings,
OS notification settings, network connectivity, force-stop behavior, and
platform policy can affect display even after OneSignal accepts a send.

## 8. Troubleshooting

| Symptom | Check |
|---|---|
| `OneSignal App ID or API key is missing` | Set both backend environment values, clear Laravel config cache, and restart queue workers. |
| App check returns HTTP `401`/`403` | Confirm the REST API key is valid, has access to the matching OneSignal app, and is stored only on the backend. |
| Provider accepts request but there are no devices | Open the installed app, sign in, allow notifications, verify the `player_id`/subscription ID was saved, and check active/permission filters. |
| Android device is not messageable or has no delivery | Configure FCM HTTP v1 credentials in OneSignal's Android settings and verify the installed build uses the matching Firebase project. |
| iOS push is not delivered | Configure APNs credentials in OneSignal, rebuild/install the native app if needed, and confirm notification permission. |
| Broadcast saved but no push arrives | Confirm the `broadcasts` queue worker is running, inspect failed jobs/logs, then check OneSignal provider IDs/errors and delivery reports. |
| Subscription is rejected as invalid | Refresh the mobile registration by launching the installed app and signing in; the backend deactivates explicitly rejected stale subscriptions. |
| Notification accepted but not visible | Check OneSignal delivery reports and device notification settings; API acceptance is not device receipt/display confirmation. |

## 9. Security checklist for teammates

- Share the **App ID** as needed; share REST API keys only through an approved
  secret manager or other secure credential channel.
- Keep `ONESIGNAL_API_KEY`, Firebase service-account JSON, and APNs private
  keys off the client and out of Git.
- Never ask the mobile app to call OneSignal's REST send endpoint with the
  server key. Mobile registration uses its SDK and the authenticated Laravel
  endpoint.
- Use a test device/subscription for smoke tests and do not send emergency
  content to a production audience while testing.
- Rotate any credential that is accidentally exposed and update the backend
  secret store.

## October 31: household platform overwrite repair

The reported no-recipient broadcasts were in the selected resq_local database, not shared_db. Provider read-only checks accepted the API credentials, found matching backend/mobile App IDs, and confirmed Android FCM v1 configuration. The local registration was an enabled Android subscription with matching external account ownership, but household setup/location writes had replaced its android platform with the generic mobile fallback. The broadcast's Android filter therefore excluded it.

Household setup/location now preserves a known Android/iOS platform. For existing mobile-labelled rows, recipient selection verifies the exact subscription, external account, native platform and enabled notification state using the configured OneSignal app before including it. Other role, scope, permission and active-state filters still apply. The verification is cached briefly; it does not rewrite database registrations or target unrelated provider subscribers.

The repaired read-only readiness command found one locally registered eligible Android subscription. The provider reported 27 messageable subscriptions across its app, but that does not make all 27 authorized Resqperation database recipients. Shared_db had no registered devices during this check. No database connection, credentials or business rows were changed, and old disaster alerts were not resent.

Focused registration/setup/broadcast tests passed: 37 tests, 133 assertions. These checks verify eligibility and mocked provider-ID persistence; a new actual broadcast is still required to establish real message acceptance/device display. A saved broadcast that was never accepted by OneSignal cannot truthfully receive a fabricated onesignal_id. Consult OneSignal's [message API response rules](https://documentation.onesignal.com/reference/create-message) for provider message IDs and delivery outcomes.
