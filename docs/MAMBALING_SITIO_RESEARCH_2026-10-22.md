# Mambaling sitio and purok boundary research

Researched 22 October 2026. Scope: Mambaling, Cebu City, Philippines.

## Remaining-name search follow-up

Searched 39 additional query variants in Google Maps, including exact/quoted Club Kawayan names, alternate Kamansi spelling, local landmarks, and a Mambaling location bias. Query-to-CID results and sanitized place entity coordinates are in `mambaling-remaining-google-searches.json`. The returned place point was used rather than its viewport center. No inferred coordinates were added.

Six previously unavailable labels now offer **Google result candidates** in the optional pin layer. Select the sitio label, then choose the actual Google result to plot it. Candidates have an explicit unconfirmed-match notice, actual address and source link; no candidate is selected automatically. This does not assert that a similarly named place belongs to Mambaling. Existing seven reference pins are unchanged.

| Requested label | Google result candidates | Limitation |
|---|---|---|
| Camansi | Camansi, Kinasang-an Pardo (`5693486887132621654`) | Different source locality; Mambaling identity unconfirmed |
| Kawayan | Sitio Kawayan Residences, Busay (`10377377110332260220`); Sitio Kawayan Chapel (`11606411810371225608`) | Neither established as Club Kawayan |
| Kolo | SITIO KOLO, Lapu-Lapu (`11627603300981526017`); Sitio Colo, Talisay (`5336067005303207493`) | Different source cities; Mambaling identity unconfirmed |
| Manggasan | 248-X Sitio Mangga San Roque (`3820140353994659384`) | Google fuzzy-name result; equivalence not established |
| San | San Juan (`9543713594786635167`); San Roque Rd, Mambaling (`1430105002068006327`) | Incomplete requested name; user chooses a result rather than automatic aliasing |
| Tupas | Tupas St, Cebu City (`11224806431228396653`) | Street reference; intended sitio identity unconfirmed |

Club remains unavailable: no matching Club/Club Kawayan listing or address was obtained. Results for pickleball clubs, bars, Kawayan Cove in Batangas, and resorts were excluded because they do not establish the requested place. No fabricated Club marker was inserted. There are seven existing references plus nine selectable search-candidate pins across six additional labels, making 13 of the 14 labels capable of showing a result location.

Validation: production build, ESLint for changed files, and a catalog check against saved Google evidence. The check confirms that ambiguous labels resolve to no marker until a listed candidate is chosen, invalid result IDs cannot create pins, and every candidate coordinate/source URL matches the saved place record.

## User clarification: plot the supplied Google result

The user explicitly confirmed the Paglaum listing using a screenshot showing `8V6P+WF Cebu City, Cebu`. The catalog now includes that exact Google place at latitude `10.3123253`, longitude `123.8862491`, CID `1377466696183318961`, regardless of the barangay outline. Its plus code appears in the controls and popup. There are now seven plotted references; the earlier exclusion of Paglaum below is superseded. The layer stays off on initial load.

## Follow-up: optional Google Maps place-pin layer

A later search on the same date obtained readable place details using Google Maps' public embed responses (`https://maps.google.com/maps?cid=...&output=embed&hl=en`). This supersedes the initial coordinate-access findings below; it does not change the boundary conclusion. Sanitized title/address/coordinate/source evidence is saved in `mambaling-google-place-candidates.json`. Google result coordinates were read from the place entity, not the map viewport.

The mapping page now has an optional **Sitio pins · Google Maps** layer, off on every initial page load. Enable it and select a sitio to show only its reference pin. It works without an active event and independently of household/purok filters. Every popup gives the Google result title, source link, coordinates, research date, and a statement that the point is not a boundary. No coordinate-entry form or browser-local pin editor was added.

| Label | Latitude | Longitude | Google result / reference type |
|---|---|---|---|
| Larima | 10.2948409 | 123.8791363 | [Larima Mambaling Homeowners Association](https://www.google.com/maps?cid=5439189471944924854); outside stored indicative outline, explicitly flagged |
| Lawis | 10.2880266 | 123.8806039 | [Lawis Alaska Mambaling Cebu City Philippines](https://www.google.com/maps?cid=13143145446609449095) |
| Manga | 10.2892999 | 123.8781814 | [Mambaling Cebu City](https://www.google.com/maps?cid=1517692800786653094), address: 406 Main Rd, Sitio Manga; address reference |
| Tromar | 10.2903804 | 123.8779543 | [664 B](https://www.google.com/maps?cid=1225294149079693663), address: 664-B, Tromar, F. Vestil St; address reference |
| Viking | 10.2897254 | 123.8806118 | [210 H Alaska Viking Mambaling](https://www.google.com/maps?cid=12741785657808034142); housing development reference |
| Ybanez Compounds | 10.2892956 | 123.8801145 | [Ybanez Compound Rd](https://www.google.com/maps/search/?api=1&query=Ybanez+Compound+Rd+Mambaling+Cebu+City); road reference, not compound extent |

The supplied Paglaum share link's knowledge entity `/g/11wps284rt` appears in Google's search response alongside CID `1377466696183318961`. That entity resolves to **Sitio Paglaum, V Rama Ave**, latitude `10.3123253`, longitude `123.8862491`; excluded from plotted Mambaling points. A similarly named Camansi result at `10.2861181,123.8550809`, Kawayan results elsewhere, and Kolo results in other areas were also excluded. No accepted pin was established for Camansi, Paglaum, Club, Kawayan, Kolo, Manggasan, San or Tupas. They remain selectable with an explanation and Google source/search link, without a fabricated marker. These results may be user-contributed listings and are not official sitio centers.

Validation: frontend production build and ESLint on changed JavaScript/JSX files; catalog coordinates cross-checked against saved Google entity evidence. No boundary or household-classification data was changed.

## Result

Public sources support several requested place names, but this search did not obtain verified sitio/purok boundary vertices. No polygons were invented or imported, and no application or database behavior was changed. A name appearing in a utility advisory is evidence of usage, not proof of an administrative unit or its extent.

Google Maps search URLs were requested for all 14 names. The returned HTML had generic Google Maps titles and no extractable `!3d latitude !4d longitude` place coordinates. Search-index queries also did not produce independently verifiable Google Maps place records. This access limitation does not mean that the places are absent from Google Maps. The links below are search links, not confirmed listings.

The user's sample https://share.google/VzxkDWfSKz08GExFQ resolved to a Google Search place entity titled **Sitio Paglaum**, knowledge identifier `/g/11wps284rt`. The accessible response did not establish its Mambaling address, latitude/longitude, or boundary. Do not treat a Google map camera center, search viewport, or geocoder fallback as a sitio coordinate.

## Per-name findings

| Requested name | Evidence found | Google Maps search (not confirmation) | Verified latitude / longitude | Verified boundary |
|---|---|---|---|---|
| Camansi | VE advisory; 2024 local news lists Tribu Camansi | [Search](https://www.google.com/maps/search/?api=1&query=Camansi%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Paglaum | VE; DSWD address explicitly says Sitio Paglaum, Mambaling; court decision mentions it | [Search](https://www.google.com/maps/search/?api=1&query=Paglaum%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Club | VE lists separately; Senate record and news identify **Sitio Club Kawayan** | [Search](https://www.google.com/maps/search/?api=1&query=Club%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Kawayan | Same naming ambiguity as Club; separate identity unresolved | [Search](https://www.google.com/maps/search/?api=1&query=Kawayan%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Kolo | VE; local news lists Tribu Kolo Island | [Search](https://www.google.com/maps/search/?api=1&query=Kolo%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Larima | Barangay website explicitly mentions Sitio Larima; DSWD lists Larima association in Mambaling | [Search](https://www.google.com/maps/search/?api=1&query=Larima%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Lawis | VE; Cebu City procurement explicitly locates project at Sitio Lawis, Mambaling; DSWD corroborates | [Search](https://www.google.com/maps/search/?api=1&query=Lawis%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Manga | VE; DSWD disaster report explicitly identifies Sitio Manga, Mambaling | [Search](https://www.google.com/maps/search/?api=1&query=Manga%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Manggasan | Found in VE multi-barangay affected-area list; exact identity unresolved | [Search](https://www.google.com/maps/search/?api=1&query=Manggasan%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| San | Found in VE list; name potentially incomplete; do not automatically rename to San Roque | [Search](https://www.google.com/maps/search/?api=1&query=San%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Tromar | VE; court decision explicitly mentions Sitio Tromar, Mambaling | [Search](https://www.google.com/maps/search/?api=1&query=Tromar%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Tupas | VE list; do not substitute a Tupas Street location elsewhere in Cebu City | [Search](https://www.google.com/maps/search/?api=1&query=Tupas%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Viking | VE lists Viking and Viking Complex; local news explicitly mentions Sitio Viking, Mambaling | [Search](https://www.google.com/maps/search/?api=1&query=Viking%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |
| Ybanez Compounds | VE lists Ybanez Compounds and Ybanez Compound; OpenStreetMap-derived street directory has Ybanez Compound road; university report shows that road near Alaska school | [Search](https://www.google.com/maps/search/?api=1&query=Ybanez%20Compounds%20Mambaling%20Cebu%20City) | Not obtained | Not obtained |

**VE limitation:** the advisory covers portions of Mambaling, San Roque, and South Reclamation Area together. Its 13-name list cannot by itself assign every entry to Mambaling or establish that each is a separate sitio. Larima is independently supported; it is not in that advisory's list.

## Sources and map services

- Visayan Electric, published 26 June 2025: https://www.visayanelectric.com/post/service-interruption-june-29-july-5-2025 — lists 13 requested names in an affected-area description; no boundary geometry.
- Barangay website: https://mambalingcebu.com/ — drainage-covering project at Sitio Larima; no polygon supplied in the retrieved page.
- Senate Legislative Reference Bureau, House Bill 28106 (filed 24 January 1990): https://issuances-library.senate.gov.ph/bills/house-bill-no-28106-8th-congress-republic — explicitly names Sitio Club Kawayan, Barangay Mambaling. This is a bill record, not proof of enactment or present boundary extent.
- DSWD accredited organizations: https://caraga.dswd.gov.ph/wp-content/uploads/2021/03/Accredited-Bene-CSO-CY-2018-part-1-of-2-no-1-6826.pdf — addresses corroborate Paglaum, Larima, and Lawis in Mambaling.
- DSWD Manga fire report: https://dromic.dswd.gov.ph/wp-content/uploads/2023/04/DSWD-DROMIC-Terminal-Report-on-the-Fire-Incident-in-Brgy.-Mambaling-Cebu-City-09-February-2024-6PM.pdf — explicitly identifies Sitio Manga; no boundary vertices.
- Court decision: https://lawphil.net/judjuris/juri2010/sep2010/gr_184761_2010.html — explicitly mentions Tromar, Mambaling, and Paglaum; no boundaries.
- Cebu City project: https://www.cebucity.gov.ph/wp-content/uploads/2022/02/P3-Road-Concreting-Sitio-Lawis-Mambaling-BID-DOCS-03162022-03.pdf — indexed project location supports Lawis. No verified sitio polygon was extracted in this research.
- Local news: https://www.philstar.com/banat/balita/2024/01/28/2329104/23-ka-sitio-mosalmot-sa-sinulog-sa-mambaling — Camansi, Kolo Island and Vikinganon community names; not a geographic registry.
- Local news: https://www.philstar.com/the-freeman/cebu-news/2020/05/26/2016623/recoveries-outnumber-new-cases — explicitly mentions Viking and Larima in Mambaling.
- OpenStreetMap-derived street directory: https://philippines-streets.openalfa.com/streets/ybanez-compound-basak-san-nicolas-cebu-city and https://philippines-streets.openalfa.com/streets/larima-way-mambaling-punta-princesa-cebu-city — road references, not compound/sitio boundaries; inconsistent administrative labels require checking.
- Rotterdam university report: https://www.hogeschoolrotterdam.nl/contentassets/89e13a8066ae4eb3929be586e10e9c87/casus-1-feasibility-study.pdf — indexed figure describes Ybanez Compound Rd near Alaska Mambaling Night High School; not a boundary survey.
- GeoRisk/PSA ArcGIS: https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/layers — layer explicitly describes **indicative barangay boundaries as of June 2016**, not sitio/purok boundaries. The project already stores the Mambaling barangay polygon in `backend-laravel/database/boundaries/0730600049.geojson`.
- Geoportal Philippines: https://www.geoportal.gov.ph/ — inspected portal; no requested sitio polygon dataset obtained.
- Bing Maps and OpenStreetMap browser search pages were inaccessible through the web reader. The initial Overpass API request returned HTTP 406; alternate endpoints were also attempted. These access failures are not evidence of missing map features.

## How to finish the boundary layer

### Esri geocoder cross-check

Queried `https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates` for 13 labels (Club Kawayan combined), each with Mambaling, Cebu City, Philippines and a local location bias. Most returned the same **Mambaling locality fallback**, latitude `10.29122`, longitude `123.87795`, with another locality candidate at `10.29306`, `123.87361`. These are not sitio matches despite scores above 85. Manga returned streets in Labangon/Guadalupe, and Tupas returned streets in Pasil/Ermita: rejected as wrong-area matches. Lawis returned a street labelled **Laws**, latitude `10.28845554464`, longitude `123.881620580275`; retained here only as an unverified spelling/road candidate, not a confirmed sitio pin or boundary vertex. The alternate Overpass endpoints both timed out. No accepted sitio coordinates resulted from this cross-check.

1. Reconcile the 14 supplied labels with the existing sitio/purok registry, especially Club Kawayan, San, Manga/Mangga, and Ybanez/Ybañez. A sitio label must not be assigned an arbitrary purok ID.
2. Obtain a barangay-approved sitio/purok map, GIS file, or a boundary walk with local officials. If the source is a paper map, georeference it against identifiable control points before tracing.
3. Capture each boundary's ordered WGS84 vertices. A Google Maps place point can help locate an area, but cannot determine boundary corners. Roads, waterways and compounds need explicit evidence tying them to each boundary.
4. Keep any exploratory tracing labelled approximate in a separate review layer. Do not use inferred circles, nearest-pin partitions, or road-name lines for automatic household classification.
5. Export reviewed Polygon/MultiPolygon geometry to GeoJSON using `[longitude, latitude]` pairs, closed rings, source/date, and registered `purok_id`. Check containment against the existing indicative barangay outline and investigate disagreements with the source map rather than silently clipping them.
6. Use the existing `geography:import-puroks` and classification preview workflow described in `backend-laravel/docs/household-geographic-classification.md` once verified geometry is available.

The remaining missing input is boundary evidence, not merely a longitude/latitude lookup. This research does not establish any complete sitio/purok polygon and therefore does not complete boundary marking.
