# RESQPERATION Mobile App

Expo React Native app for the household and rescuer mobile roles.

The mobile app does not connect directly to MySQL. It connects to Laravel through the API, and Laravel connects to the shared MySQL database.

## Current API Connection

`frontend-mobile/.env`

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.112.109:8000/api/v1
```

This IP is the laptop Wi-Fi IP. If the laptop IP changes, update this value.

## Required Backend Command For Phone Testing

Run Laravel with `0.0.0.0`, not only `127.0.0.1`.

```bash
cd backend-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

Reason: Expo Go on a phone must reach the backend through the laptop network IP.

## Start Mobile App

```bash
cd frontend-mobile
npm install
npx expo start
```

Open the QR code in Expo Go.

## Temporary Mobile Accounts

| Role | Account ID | Password |
| --- | --- | --- |
| Household Resident | `2024035501` | `password` |
| Household Resident Setup Test | `2024035503` | `password` |
| Rescuer Temp SAR Test | `BDRRM-SAR-001` | `password` |
| Rescuer Medical Test | `BDRRM-MED-001` | `password` |
| Rescuer SAR Test | `BDRRM-SAR-002` | `password` |

Rescuer mobile login uses the BDRRM account ID format. The sequence is counted per team code:

```text
BDRRM-SAR-001
BDRRM-MED-001
BDRRM-SAR-002
```

The rescuer profile also has a separate editable username, such as `vinzon.arellano` or `vince.pacillan`.

Actual mobile login is DB-driven. Household accounts from SafeTrack work in RESQPERATION when they are already saved in the shared `users` table with a `household_resident` role, a valid password hash, and a linked `household_id`. The login field can accept the household `username`, `email`, `user_id`, or `household_id`.

## Verified API Flow

- Household login uses `/api/v1/auth/login`
- Household app loads `/api/v1/household/overview`
- Rescuer login uses `/api/v1/auth/login`
- Rescuer app loads `/api/v1/rescuer/overview`

If login fails on the phone but works on the browser/laptop, check:

- phone and laptop are on the same Wi-Fi
- Laravel is running with `--host=0.0.0.0`
- Windows Firewall allows port `8000`
- `EXPO_PUBLIC_API_BASE_URL` uses the current laptop Wi-Fi IP
