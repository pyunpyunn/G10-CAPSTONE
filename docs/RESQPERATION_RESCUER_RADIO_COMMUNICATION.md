# RESQPERATION Rescuer Radio Communication

Prepared: June 13, 2026

## Current Implementation

The rescuer mobile radio uses a tap-to-record flow:

1. Rescuer taps the telephone button.
2. The app starts recording and saves a `ptt_start` log.
3. Other rescuers in the same team can see a floating "is recording" indicator.
4. Rescuer taps again to stop.
5. The app uploads the audio clip to Laravel.
6. Laravel saves the file under public storage and records the log in `responder_communication_logs`.
7. Same-team devices refresh the radio feed and can play the voice clip.

This is not live WebRTC streaming. It is a practical Expo Go-compatible voice clip radio. It is easier to test, works with the current Laravel/MySQL setup, and keeps logs for archive.

## Why This Design

True live push-to-talk needs a signaling server, media server, or WebRTC/native setup. That is harder to prove in Expo Go and can become unstable during defense. The voice-clip model is safer for the current project because:

- no new database table is required;
- the existing `responder_communication_logs` table is reused;
- voice records can be archived after the disaster event;
- two devices can test it through the same Laravel API;
- the team can still upgrade later to LiveKit/WebRTC if required.

## Backend Files

- `backend-laravel/routes/api.php`
- `backend-laravel/app/Http/Controllers/Api/RescuerMobileController.php`
- `backend-laravel/app/Services/RescuerMobileService.php`
- `backend-laravel/app/Services/ArchiveService.php`

## Mobile Files

- `frontend-mobile/components/rescuer/RadioCommunicationScreen.tsx`
- `frontend-mobile/api/rescuer.ts`
- `frontend-mobile/api/client.ts`

## Required Local Setup

Run once after pulling the changes:

```bash
cd backend-laravel
php artisan storage:link
```

The mobile API URL must use the laptop network IP, not `127.0.0.1`, when testing from Expo Go on another phone.

Example:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.x.x:8000/api/v1
```

## Test Steps

1. Start Laravel on the network:

```bash
cd backend-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

2. Start Expo mobile.
3. Log in two rescuer accounts from the same team.
4. Open Radio / Team Radio on both phones.
5. On phone A, tap the telephone button and speak.
6. Tap again to stop and send.
7. Phone B should show the new radio log and play the clip.
8. If it does not play automatically, tap the play button on the log row.

## Known Limitation

Automatic playback depends on the phone being able to reach:

```text
http://<laptop-ip>:8000/storage/<audio-path>
```

If audio does not play, check:

- Laravel is running on `0.0.0.0`;
- Windows Firewall allows port `8000`;
- `php artisan storage:link` was already run;
- both phones are on the same Wi-Fi;
- `EXPO_PUBLIC_API_BASE_URL` uses the laptop IP address.
