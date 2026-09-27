# Household SMS integration

The Laravel backend supports two SMS paths:

- **Outbound broadcast:** after HQ saves a broadcast, `DisasterBroadcastService` sends it through OneSignal and Semaphore. Selected-purok broadcasts use the same purok names for both providers. Rescuer-only broadcasts skip SMS.
- **Inbound household status:** households text `STATUS <household_code> <status>` to the configured Semaphore number. Accepted keywords include `SAFE`, `EVACUATED`/`EVAC`, `UNSAFE`, and `HELP`/`NEEDHELP`/`RESCUE`.

Inbound reports require both a configured webhook secret and a sender phone number that matches the household's saved `contact_number`. The report is written with source `sms` to `household_status_logs` and updates the active household disaster status using the existing status-sync logic.

## Backend configuration

Set these values in the backend `.env` (do not commit actual keys):

```dotenv
SEMAPHORE_API_KEY=your-provider-api-key
SEMAPHORE_SENDER_NAME=ResQperation
SEMAPHORE_INBOUND_SECRET=a-long-random-webhook-secret
```

Configure Semaphore's inbound callback to send that secret in the `X-Webhook-Secret` header. The current controller accepts `from` or `sender`, and `message` or `text`; adjust those field names to the actual provider webhook payload. Keep the endpoint unavailable until a secret is set. Laravel must be able to reach Semaphore over HTTPS for outbound messages.

After deployment, run the Laravel migration to add `sms_status` to `disaster_broadcasts`. Broadcast responses and history include the OneSignal `push_status` and the new `sms_status` when the column is present.

## Operational notes

- The SMS path requires actual household contact numbers, a Semaphore account/credits, and provider approval for the sender name.
- Provider acceptance means the gateway accepted a send request; it does not prove that a handset received or read the SMS.
- Confirm the provider's accepted request size, recipient batching, webhook payload fields, and sender-name requirements before a live broadcast.
- Do not test with real recipients until provider credentials, audience targeting, and message wording are confirmed. Use test household records and numbers first.
- SMS status reporting requires an active disaster event and a matching household code/contact number.
