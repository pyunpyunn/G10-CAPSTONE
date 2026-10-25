# Household mobile password recovery

Household residents can recover their mobile password from the login screen using the household's registered `households.contact_number`.

The recovery flow uses these unauthenticated, rate-limited API routes:

- `POST /api/v1/auth/password-recovery/household/lookup` — resolves a household account ID and returns only a masked phone number.
- `POST /api/v1/auth/password-recovery/household/validate-contact` — compares the entered number with that household's registered number.
- `POST /api/v1/auth/password-recovery/household/send-code` — sends a six-digit, ten-minute OTP through the configured Android SMS gateway, or OneSignal SMS when the gateway is not configured.
- `POST /api/v1/auth/password-recovery/household/reset` — verifies the OTP, changes the password, and revokes existing sessions.

Run the `2026_10_25_000001_create_household_password_recovery_otps` migration before enabling the flow. Configure `SMS_GATEWAY_BASE_URL`, `SMS_GATEWAY_USERNAME`, and `SMS_GATEWAY_PASSWORD` to use the Android SMS gateway. If those settings are absent, the application uses the configured OneSignal app/API credentials and SMS channel instead; OneSignal SMS delivery must be enabled for the app/account. The code is stored only as a password hash, expires after ten minutes, and permits at most five incorrect attempts. New SMS requests are limited to one per minute per account and also use route-level throttling.

The mobile OTP field supports platform one-time-code suggestions where available; SMS retrieval remains user-controlled and does not require SMS inbox permissions.
