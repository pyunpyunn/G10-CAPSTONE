# RESQPERATION System Test and Improvement Notes

Date tested: 2026-06-15

## Test Coverage Run

| Area | Command | Result | Notes |
| --- | --- | --- | --- |
| Web frontend lint | `npm run lint` in `frontend-web` | Passed | ESLint completed without errors. |
| Web frontend production build | `npm run build` in `frontend-web` | Passed with warning | Vite built successfully. The main JavaScript chunk is larger than 500 kB, so code splitting should be considered before deployment. |
| Mobile TypeScript check | `npx tsc --noEmit` in `frontend-mobile` | Passed | No TypeScript errors reported. |
| Mobile lint | `npm run lint` in `frontend-mobile` | Passed | Expo lint completed without errors. |
| Backend PHPUnit | `php artisan test` in `backend-laravel` | Passed | 3 tests passed with 4 assertions. Current backend test coverage is still very small. |

## Review Scope

This pass covered automated checks and a code review of the notification, modal, API client, and test coverage areas. A full live manual test with shared MySQL data, running Laravel server, running Vite server, browser login, and mobile device push delivery was not completed in this pass.

## Priority Improvements

### 1. Notification popover should close when the user clicks outside it

Current behavior:

- The notification preview opens from the topbar bell.
- It closes when the user clicks the bell again or clicks `View all`.
- It does not close when the user clicks another part of the screen.

Recommended behavior:

- Close the notification popover when the user clicks outside the popover and bell button.
- Close it when the user presses `Escape`.
- Keep clicks inside the notification popover active so `Mark as read` and `View all` still work.

Files to update:

- `frontend-web/src/components/layout/Topbar.jsx`

Suggested test case:

- Open notifications from the bell.
- Click an empty area of the dashboard.
- Expected result: notification message/popover closes immediately.

### 2. Success and error messages should be dismissible

Current behavior:

- Notification page messages such as "Notifications marked as read" stay visible until another action changes the message.
- Similar module messages exist in Resources, Situation Reports, Archive, Weather, and Profile pages.

Recommended behavior:

- Add a reusable alert/toast component with a close button.
- Auto-hide success messages after a short delay, such as 4-6 seconds.
- Keep error messages visible until dismissed or corrected.
- Let users dismiss messages by clicking the close icon, pressing `Escape`, or clicking outside the toast if it is floating.

Files to review first:

- `frontend-web/src/pages/NotificationsPage.jsx`
- `frontend-web/src/pages/ResourcesRequestsPage.jsx`
- `frontend-web/src/pages/SituationReportPage.jsx`
- `frontend-web/src/pages/ArchivePage.jsx`
- `frontend-web/src/pages/WeatherPage.jsx`
- `frontend-web/src/pages/ProfilePage.jsx`

### 3. Add stronger automated tests for real workflows

Current behavior:

- Backend tests pass, but only 3 tests run.
- There are no frontend component tests or browser workflow tests.

Recommended coverage:

- Login success and invalid login.
- Protected route redirect when unauthenticated.
- Notification popover open, outside-click close, `Escape` close, and mark-as-read behavior.
- Disaster broadcast create and validation errors.
- Resource request validation and forwarding.
- Rescue dispatch create/update/complete.
- Archive CSV export.

Suggested tools:

- Backend: PHPUnit feature tests for API endpoints.
- Web: Playwright or Vitest with React Testing Library.
- Mobile: TypeScript checks plus focused screen logic tests where practical.

### 4. Verify OneSignal push notification sending and receiving

Current behavior:

- OneSignal is installed in the mobile app.
- Mobile push token registration stores OneSignal Player IDs through the mobile device-token endpoint.
- Laravel sends OneSignal push messages for disaster broadcasts and new rescue dispatch assignments.

Remaining verification:

- Mobile app asks permission and registers the OneSignal Player ID after login.
- Backend stores the active device token per user/device.
- Backend sends push messages through OneSignal when HQ/Admin broadcasts an alert or creates a rescue dispatch.
- Mobile app displays received alerts and stores/reloads alert history from the API.
- Failed push sends should be reviewed in backend logs and OneSignal delivery reports.

Files/modules to build around:

- `frontend-mobile`
- `backend-laravel/app/Services/OneSignalNotificationService.php`
- `backend-laravel/app/Services/NotificationService.php`
- `backend-laravel/app/Services/DisasterBroadcastService.php`
- `backend-laravel/app/Services/RescueDispatchService.php`

### 5. Improve API session expiration handling on the web frontend

Current behavior:

- The web API client attaches the token to requests.
- There is no shared response interceptor that automatically handles `401 Unauthorized` responses after the token expires.

Recommended behavior:

- Add an Axios response interceptor.
- Clear the saved token when the API returns `401`.
- Redirect the user to login or show a clear session-expired message.
- Avoid leaving the dashboard visible with failing API requests.

Files to update:

- `frontend-web/src/api/client.js`
- `frontend-web/src/App.jsx`

### 6. Reduce web production bundle size

Current behavior:

- `npm run build` passes, but Vite warns that a chunk is larger than 500 kB.

Recommended behavior:

- Lazy-load large pages with `React.lazy`.
- Split map-related code so Leaflet loads only on Mapping.
- Split archive/reporting modules if build size remains high.

Files to review first:

- `frontend-web/src/App.jsx`
- `frontend-web/src/pages/MappingPage.jsx`

### 7. Make modal behavior consistent

Current behavior:

- Some modal overlays close when clicking the backdrop.
- The shared modal component does not yet provide Escape-key close by default.

Recommended behavior:

- Standardize modal behavior:
  - close on backdrop click where safe,
  - close on `Escape`,
  - trap focus while open,
  - restore focus to the opener after close.

Files to review first:

- `frontend-web/src/components/ui/Modal.jsx`
- `frontend-web/src/components/login/LoginModal.jsx`
- module-specific modal components

## Suggested Next Step

Implement priority 1 first because it is small, visible during demo testing, and matches normal user expectations: when the notification popover/message is open, clicking outside it should close it.
