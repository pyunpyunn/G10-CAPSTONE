> Historical decision: superseded on 19 October 2026 by the user-requested six-finding contract 4.0. See RESQPERATION_EXTERNAL_REPORTS_API.md for the active contract.

# Partner SitRep v3 Decision Record

Confirmed by the user on 18 October 2026: update EvaTrack, SafeTrack and TrackingAid to the shared I–XII template, api_version 3.0 and twelve-section limit, while retaining the reviewed API and out-of-system city handoff. The user delegated old-client policy; implementation rejects v2/unversioned requests with a clear 422 upgrade message and requires api_version: "3.0". Historical snapshots remain stored unchanged and render through a read-only projection into the same template. Partner approved_by remains blank for wet signature.

Current contract: [RESQPERATION_EXTERNAL_REPORTS_API.md](RESQPERATION_EXTERNAL_REPORTS_API.md). Acceptance checklist: [partner-report-checklist.md](partner-report-checklist.md).
