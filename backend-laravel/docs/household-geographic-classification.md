# Household geographic classification

Current eligibility: a non-deleted household must link through `households.address_id` → `addresses.purok_id` → `puroks.sitio_id` → the configured barangay. Address text and GPS alone do not establish a registered purok.

The read-only `GET /api/v1/households/directory` data-management endpoint is separate from operational eligibility. It includes every non-deleted household and loads its address, registered sitio/purok, household members, and household accounts through their ID relationships. Records with missing or invalid geographic IDs stay visible and are labeled; this does not make them eligible for dispatch or operational reports. Account-to-member links are reported only when `users.member_id` exactly matches a member in that household. No names, addresses, or labels are used to infer missing IDs.

Saving a household home pin during setup or `PUT /api/v1/household/geotag` calls the backend geographic classifier in the same transaction. Device heartbeat locations do not change the household home address. A unique purok polygon match saves its registered `purok_id`, parent `sitio_id`, barangay ID, and registry label. Shared addresses are copied before updating a single household. A moved pin that no longer matches clears its previous automatic area assignment. Responses include `data.geographic_classification`.

The mobile profile map is hidden until opened. Its pin stays fixed at the map center while the user pans, then the map center is sent to the server when panning stops. The geotag endpoint validates coordinates against the registered Mambaling boundary rather than the app-wide active barangay profile, which may be configured to another area. The client does not use a separate polygon approximation that could disagree with server validation. The map offers street/satellite basemaps. Automatic sitio/purok assignment requires verified purok polygons; without them the geotag is saved but the response reports `purok_boundaries_missing` and neither `sitio_id` nor `purok_id` can be inferred.

## Required boundary data

The existing database has the Mambaling barangay boundary, sitio names, and 52 registered puroks, but no purok polygons. The barangay polygon alone cannot identify an exact sitio/purok. Obtain verified polygons covering the registered puroks; each polygon implies its parent sitio through the existing registry. Do not substitute nearest household, a name lookup, or generated regions.

Import a GeoJSON FeatureCollection. Each feature must have `properties.purok_id` matching a registered purok in the configured barangay, and Polygon or MultiPolygon geometry in WGS84 longitude/latitude order. Rings must be closed. Include official source provenance.

```powershell
php artisan geography:import-puroks 'C:\path\official-puroks.geojson' --source='Official dataset provenance'
php artisan geography:classify-households
php artisan geography:classify-households --apply
```

The classification command previews by default. Import validates the complete file before writing. Review the preview before applying. Re-run after correcting boundary data; current household pins are selected by latest saved timestamp and location ID.

Statuses: `matched`, `outside_barangay`, `barangay_boundary_missing`, `purok_boundaries_missing`, `no_boundary_match`, `ambiguous_boundary`, `household_unavailable`. Overlap and shared-edge matches remain unresolved. GPS measurement accuracy still limits physical precision; polygon matching classifies the saved coordinate, not an independently verified property location.

Run the additive migration `2026_10_15_000001_create_household_geographic_classification_tables.php` in other environments before using the import/classification commands. It creates `purok_boundaries` and `household_geographic_classifications`; it contains no invented boundary data or household assignments.

## Public-source research, 15 October 2026

- PSA PSGC identifies Mambaling as `0730600049` (old code `072217049`): https://psa.gov.ph/classification/psgc/barangays/0730600000
- The GeoRisk PSA Barangay layer describes indicative barangay boundaries as of June 2016. It is not a sitio/purok dataset: https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/layers
- The Mambaling barangay website mentions individual sitios in activity posts; those mentions do not define their polygons: https://mambalingcebu.com/
- Cebu City publishes GIS map provision as a city service: https://www.cebucity.gov.ph/city-services/
- A Cebu City procurement record refers to Sitio Lawis, Mambaling. A project location label alone cannot establish an area polygon or resolve similarly named database sitios: https://www.cebucity.gov.ph/project-no-3-road-concreting-and-drainage-system-slum-improvement-resettlement-brgy-mambaling-cebu-city/

No verified downloadable Mambaling sitio/purok polygon dataset was found in these searches. No online place names, map pins, or inferred regions were imported as administrative boundaries. The database currently contains 13 sitio records and 52 purok records for Mambaling; those counts describe the local registry, not independently verified geographic coverage. Imported geometry must map to existing database purok IDs. The parent sitio is always read from the database, ignoring external sitio IDs and labels.
