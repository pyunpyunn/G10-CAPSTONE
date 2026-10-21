# Official weather outlook card

Adds one independent display to the existing Weather page; existing weather snapshots, forecasts, history, and local disaster advisories are unchanged.

Reference: LGA Disaster Preparedness Manual, Annex G: Weather Bulletin Sites, printed page 141 / PDF page 159: https://cdn.lga.gov.ph/publication/attachments/1590478478.pdf . The supplied reference separates formal government channels (PAGASA, NDRRMC) from private reporting channels.

## Free government integration selected

DOST-PAGASA public Atom/CAP feed: https://publicalert.pagasa.dost.gov.ph/feeds/ . It is linked from PAGASA's public website and returns machine-readable Atom entries linking to Common Alerting Protocol XML. No API key, paid subscription, or unofficial scraping service is used. The feed declares CC BY 4.0; the display credits PAGASA and links to the original documents. This is a public alert feed, rather than a promised comprehensive commercial forecast API. NDRRMC and private websites were not integrated because a freely accessible machine-readable endpoint was not verified for this implementation.

GET /api/v1/weather/official-advisories uses existing authenticated Geospatial Analytics access. PHP SimpleXML and the existing Laravel HTTP client handle the feed. No migration or additional runtime package is required. File cache is the default; WEATHER_ADVISORY_CACHE_STORE can select another configured Laravel cache store. Both the browser and backend check on a ten-minute interval; Check updates rereads the cached endpoint without bypassing the upstream rate limit. Failed upstream checks retry after one minute on subsequent requests; valid cached documents remain visibly marked stale.

Only Actual, Public, unexpired CAP Alert/Update records are shown. Cancellations and superseding references remove earlier documents. Test/exercise records, malformed XML, DTD/entity declarations, external CAP URLs, and expired alerts are rejected. Published affected areas, urgency, certainty, forecast text, instructions, issued time and expiry are preserved. Times display in Asia/Manila. A national advisory is not automatically labelled a Cebu warning or converted into a local disaster event. No future LPA or storm is invented; developing systems appear when PAGASA publishes them to this feed. The feed may not contain every PAGASA bulletin category or regional product.

## Verification

Targeted parser/cache/outage tests cover future-onset advisories, local/national distinction, expiry, test messages, cancellations, URL/XML validation and cached fallback. Live integration was checked against PAGASA's actual feed. Browser verification covers the real authenticated LAN Weather page, the retained original sections, forecast details, manual checking, mobile layout and unavailable-state wording.
