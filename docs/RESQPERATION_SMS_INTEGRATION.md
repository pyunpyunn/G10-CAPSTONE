# Household SMS integration

The SMS transport uses SMS Gateway for Android in Local Server mode. The gateway runs on a SIM-equipped Android phone; Laravel sends SMS through its authenticated local API, while inbound `sms:received` webhooks post back to the Laravel endpoint.

## Configure the Android gateway

1. Install SMS Gateway for Android on a spare phone, insert the SIM, grant SMS permissions, and enable Local Server mode.
2. Start the local server and copy its displayed IP address and Basic Auth credentials.
3. In the gateway app, set a webhook signing key and register a webhook for `sms:received` at `https://<backend-host>/api/v1/sms/inbound`.
4. Point the webhook URL at a backend address reachable from the phone. For a phone and laptop on one Wi-Fi network, use the laptop's LAN address. The gateway requires HTTPS for private-LAN webhook URLs with a trusted certificate. Use the documented insecure app build only on a trusted local development network; do not expose it publicly.
5. Keep the gateway phone powered, connected to Wi-Fi and the mobile network, and exempt the app from battery optimization while testing.

## Laravel environment

Set these values in `backend-laravel/.env`; do not commit the credentials:

```dotenv
SMS_GATEWAY_BASE_URL=http://<gateway-phone-lan-ip>:8080
SMS_GATEWAY_USERNAME=<local-server-username>
SMS_GATEWAY_PASSWORD=<local-server-password>
SMS_GATEWAY_MESSAGE_PATH=/message
SMS_GATEWAY_BATCH_SIZE=20
SMS_GATEWAY_WEBHOOK_SIGNING_KEY=<same-signing-key-configured-in-the-gateway-app>
```

`SMS_GATEWAY_MESSAGE_PATH` is configurable because gateway API versions may expose different local paths. Check the phone's local Swagger page at `http://<gateway-phone-lan-ip>:8080/docs` and set the matching message endpoint.

The inbound endpoint verifies the gateway's `X-Signature` and `X-Timestamp` HMAC-SHA256 headers against the raw request body, rejects timestamps older than five minutes, and accepts only `sms:received`. It reads the sender/message from `payload.phoneNumber` and `payload.message`, then checks the sender against the household's saved contact number. The text format is `STATUS <household_code> <status>`, with supported statuses SAFE, EVACUATED, UNSAFE, and HELP/NEEDHELP/RESCUE.

After changing `.env`, clear Laravel's config cache. No database migration is needed for the Android transport; the existing `sms_status` column is used for broadcast delivery state.

## Cost and scale

This removes the aggregator's per-message API charge, but it does not make SMS free: messages still use the SIM's carrier plan, and promos may limit automated or high-volume traffic. The phone is one gateway and a single point of failure. The Android gateway documentation cautions against batch sending because carriers may restrict it. Keep tests and defense demonstrations to a few consenting test numbers, and check the carrier's plan terms before wider use.

The Android gateway accepts multiple recipients in a request, but sends them sequentially from the device. Its API accepting a request is not proof that every phone received the SMS; check the device's message status and the test phones.

## Local-mode network limits

Local mode avoids the gateway provider's cloud for the Laravel-to-phone API call. The Laravel server and gateway phone must be able to reach one another over the LAN. For inbound webhooks, the phone must also be able to reach the Laravel endpoint over a supported HTTPS route; if the backend is not reachable from the phone, the inbound leg cannot work in local mode.
