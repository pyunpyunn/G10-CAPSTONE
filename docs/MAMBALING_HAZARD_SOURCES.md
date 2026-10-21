# Mambaling government hazard snapshots

Retrieved 20 October 2026 from public GeoRisk Philippines ArcGIS services. These are published susceptibility maps, not real-time conditions or an official site certification. The deployed UI loads the committed local snapshots automatically, without needing a live connection to GeoRisk.

## Flood: DENR-MGB

https://ulap-hazards.georisk.gov.ph/arcgis/rest/services/MGBPublic/Flood/MapServer/0

Source acknowledgement: Mines and Geosciences Bureau, data as of July 2018. The public service supports map export but rejected feature queries. A transparent PNG was exported from MapServer/export using layers=show:0, bboxSR=4326, imageSR=4326, size=1200,1200, format=png32, transparent=true. Requested bbox: 123.874175989,10.28020906,123.887558982,10.29483965. The returned extent (which differs to preserve aspect ratio) is stored in database/hazards/mambaling.json and used for ImageOverlay placement. No hazard shapes were drawn or inferred by this application.

Pixels outside the PSA Mambaling polygon were made transparent using pixel-center point-in-polygon tests. Original agency colors and the Low, Moderate, High, Very High susceptibility legend are preserved. There are 443469 nontransparent pixels inside the barangay. The PNG is frontend-web/public/hazards/mambaling/flood.png, copied to both web builds.

## Liquefaction: DOST-PHIVOLCS

https://ulap-hazards.georisk.gov.ph/arcgis/rest/services/PHIVOLCSPublic/Liquefaction/MapServer/0

Queried intersecting polygons using Mambaling's WGS84 geometry and outSR=4326. Source feature IDs 1861 and 1919, Cebu Multi-Hazard Mapping, mapped 2016; source acknowledgement July 2018. Clipped the actual returned polygons to the Mambaling boundary using polygon-clipping 0.15.x. Original lccode classifications Highly Susceptible (07) and Moderately Susceptible (06), source colors, and source IDs are retained in database/hazards/mambaling.json.

## Extent and limits

Boundary: backend-laravel/database/boundaries/0730600049.geojson, PSA indicative June 2016 boundary; PSGC 0730600049 / legacy 072217049, Mambaling, Cebu City. All published overlays are clipped to this exact polygon. The backend only serves these defaults when the configured name and center match Mambaling, Cebu City; it does not substitute them for another barangay. Government layers remain barangay-wide when filtering local purok assessments because official purok boundaries and per-purok population exposure are not supplied by these services.

Rain-induced landslide export returned no rendered pixels inside Mambaling; it is omitted rather than represented as safe or low risk. Tsunami queries returned upstream errors. Storm surge was not bundled because the examined service metadata contained an inconsistent PSA boundary description. No exposure counts, evacuation centers, or safe routes are invented. Local verified planning records can still be imported alongside these government maps.
