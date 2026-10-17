# External report exchange

The complete version 2.0 partner contract is in [RESQPERATION_EXTERNAL_REPORTS_API.md](RESQPERATION_EXTERNAL_REPORTS_API.md).

External systems must select a valid disaster and explicitly select a registered sitio ID or `all` before submitting a request. The API-key-protected `GET /api/v1/external/reports/options` endpoint provides the database-backed choices. `POST /api/v1/external/report-requests` records the requested scope for operator acceptance or rejection. Approved files use the stored event, sitio and section selection.

The complete guide includes endpoints, authentication, payload requirements, report sections, scope limitations, polling, downloads, errors, integration examples and server setup. Its repository copy is a reusable template; the user-requested Downloads copy includes the configured API key.
