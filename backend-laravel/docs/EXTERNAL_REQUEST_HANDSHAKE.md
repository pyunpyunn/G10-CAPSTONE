# External requests API handshake

Only the resource requests module is covered. External senders such as EvaTrack authenticate with a shared integration key. The existing intake endpoint already checked this key on each submission; the handshake endpoint adds an authenticated preflight check without creating a resource request.

## Configure

Set `EXTERNAL_REQUEST_INTAKE_KEY` in the backend `.env` to a random secret shared with the sending system. Generate one with `php -r "echo bin2hex(random_bytes(32));"`. Run `php artisan config:clear` after changing configuration (or rebuild the production configuration cache). Empty keys and the example `change-this-shared-request-key` are rejected with 503.

Keep this key in the external system's server configuration, and use HTTPS in deployment. The HQ web app continues using its existing user authentication. All external senders currently share one key; this does not provide separate identities or permissions per system.

## Handshake and submit

In PowerShell, replace the base URL with your running backend address. This example reads the integration key from a shell environment variable; set it to the same secret configured on the backend.

```powershell
$baseUrl = 'http://localhost:8000/api/v1'
$headers = @{
    Accept = 'application/json'
    'X-RESQPERATION-INTEGRATION-KEY' = $env:EXTERNAL_REQUEST_INTAKE_KEY
}
Invoke-RestMethod -Method Post -Uri "$baseUrl/external/resource-requests/handshake" -Headers $headers -ContentType 'application/json' -Body '{}'

$payload = @{
    source_system = 'evatrack'
    external_request_id = 'EVA-HANDSHAKE-DEMO-001'
    requested_item = 'Drinking water'
    quantity = 10
    unit = 'boxes'
    requested_by = 'EvaTrack operator'
    priority = 'high'
} | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$baseUrl/external/resource-requests" -Headers $headers -ContentType 'application/json' -Body $payload
```

`Authorization: Bearer <integration-key>` is also supported. When both headers are present, the custom integration-key header takes precedence.

The handshake returns 200 with `data.authenticated: true`, `data.request_storage_ready: true`, the intake URL, and server time. It checks database connectivity and the presence of the request, status, urgency, validation-history, and audit tables. It does not prove that a subsequent database write or TrackingAid forwarding will succeed.

Send the key on **every** submission. There is no session token: the handshake is an optional preflight, and authentication remains mandatory on every intake call. Call the handshake when connecting or troubleshooting; it can also precede each submission if your external system needs that sequence. Both endpoints are throttled to 60 calls per minute.

## How to verify

From the backend directory, run `php artisan requests:verify-integration` to check the running local HTTP server, authentication rejection, and storage readiness automatically. Use `--url=https://your-backend.example` for another backend origin. The command reads the configured secret without printing it and will not follow redirects.

Run `php artisan requests:verify-integration --submit` against this backend to also create/update the clearly marked `EVA-INTEGRATION-VERIFICATION` test request, verify its database row, and confirm retries return the same request ID. This leaves one test request in the queue; do not forward or fulfill it. The database verification uses this command's local backend database, so `--submit` should target the backend connected to that same database.

- Successful handshake: HTTP 200 and both readiness flags are true. No request is created.
- Missing or wrong key: HTTP 401, on both handshake and intake.
- Unconfigured/example server key, missing tables, or inaccessible database during handshake: HTTP 503. Resolve configuration/storage before retrying.
- Valid submission: HTTP 201, with `data.request` containing the saved request. Confirm it appears in the HQ Resources & Requests validation queue as `needs_validation`.
- Retry the same source and `external_request_id`: HTTP 200 updates the existing request. Use a stable external ID to avoid creating a new request on retries.
- Invalid payload with a valid key: HTTP 422 and validation errors.
- Too many calls: HTTP 429; back off before retrying.

Run `php artisan test --filter=ResourceRequestWriteWorkflowTest` for automated checks of handshake success, Bearer authentication, rejected credentials, unconfigured keys, unavailable storage, actual intake writes, and retry behavior. Passing local tests does not establish connectivity from a separately deployed EvaTrack server; run the calls above from that server to verify the integration end to end.
