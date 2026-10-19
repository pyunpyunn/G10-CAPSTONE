# Offline Navigation: Phase 1

This phase adds the container deployment baseline, local Valhalla routing, and a minimal Mambaling vector map. It does not yet add route precomputation, mobile APIs, or mobile offline navigation.

## Requirements

- Docker Engine with the Compose plugin
- PowerShell 7 or later
- `osmium-tool` on `PATH`
- At least 8 GB free RAM and 10 GB free disk while building the Philippines-derived extracts and tiles
- A server reachable from the phones on the LAN and through Tailscale

The build begins with Geofabrik's Philippines extract. It clips that source to a buffered Cebu City bounding extent for Valhalla, then clips again to the checked-in Mambaling boundary extent plus a 300 m default buffer for the offline map. Planetiler currently limits generated tiles to z16, so MapLibre should use z16 as the source max zoom and overzoom to the target display max z17. The archive is vector-only, native zoom 12-16, with roads, paths, water, and Noto Sans Latin glyph ranges. No sprites, buildings, or POI layers are generated.

## Configure And Build

From `resqperation-system/`:

```powershell
Copy-Item .env.example .env
```

Set unique random values for `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD`. Generate a fresh Laravel key on the deployment machine with `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"`, then put the output in `.env` as `APP_KEY`. Set `APP_URL` to the URL phones will use. Set `TILESERVER_PUBLIC_URL` to the URL phones will use for tiles. During a LAN-only test, these can use the server's LAN address; for remote testing, use the server's Tailscale DNS name/address. Restrict host firewall rules for ports 8000 and 8080 to the capstone LAN and Tailscale network.

Build the OSM extracts and map:

```powershell
pwsh -File .\deploy\tiles\build-mambaling.ps1
```

The script downloads `https://download.geofabrik.de/asia/philippines-latest.osm.pbf`, runs `osmium-tool` in a temporary Debian container, and invokes Planetiler's documented Docker image with a 4 GB container memory cap. `-BufferDegrees` adjusts the Mambaling buffer; its default `0.003` is roughly 300 m at this latitude. Generated PBF, MBTiles, and glyph files remain under ignored `data/` and must not be committed.

After restoring an approved operational MySQL backup into the Compose database and checking its schema/data, start the deployment:

```powershell
docker compose --profile navigation up -d --build
```

Compose uses Nginx on port 8000 for Laravel, TileServer GL on port 8080 for vector tiles and glyphs, and Valhalla on loopback port 8002 for local diagnostics. MySQL, Redis, PHP-FPM, the queue worker, and scheduler are private to the Docker network. Valhalla and TileServer GL have explicit memory/CPU limits. Do not publish the Valhalla port on the LAN or Tailscale interface.

Do not run migrations against the newly-created empty MySQL volume. The current app relies on its existing operational schema; restore a reviewed backup first, validate that the dump is intended for this server, and only then apply reviewed additive migrations. Never restore the checked-in sample SQL into a production or shared database.

## Checks

Confirm the style, vector source, and glyphs are served:

```powershell
Invoke-WebRequest http://127.0.0.1:8080/styles/mambaling/style.json
Invoke-WebRequest http://127.0.0.1:8080/data/mambaling.json
Invoke-WebRequest 'http://127.0.0.1:8080/fonts/Noto%20Sans%20Regular/0-255.pbf' -OutFile $env:TEMP\mambaling-glyphs.pbf
```

Read two household pin coordinates inside the Mambaling extent from the operational database. The query orders verified pins first and shows verification status; if a pin is unverified, keep that status visible and do not change it just for this routing test.

```sql
SELECT household_id, latitude, longitude, is_verified
FROM geotagged_locations
WHERE latitude BETWEEN 10.281 AND 10.300
  AND longitude BETWEEN 123.860 AND 123.910
  AND latitude IS NOT NULL
  AND longitude IS NOT NULL
ORDER BY is_verified DESC, household_id
LIMIT 2;
```

```powershell
pwsh -File .\deploy\tiles\test-valhalla.ps1 `
  -FromLat 10.2910 -FromLng 123.8770 `
  -ToLat 10.2920 -ToLng 123.8790
```

Replace the sample coordinates with two distinct rows selected from the database. The check fails if either `auto` or `pedestrian` returns no route and prints route distance and maneuver count when successful.

Measure the current tile-plus-glyph payload after a successful build:

```powershell
$tile = (Get-Item .\data\tiles\mambaling.mbtiles).Length
$glyphs = (Get-ChildItem '.\data\tiles\fonts\Noto Sans Regular' -File | Measure-Object Length -Sum).Sum
"Tiles: $([math]::Round($tile / 1MB, 2)) MiB; glyphs: $([math]::Round($glyphs / 1MB, 2)) MiB; total: $([math]::Round(($tile + $glyphs) / 1MB, 2)) MiB"
```

The target is under 40 MB for this phase's tiles and glyphs; the later pack manifest will checksum and version these files. Record the measured output rather than assuming the target is met.

## Image Documentation Checked

- Valhalla scripted image: [Valhalla Docker README](https://github.com/valhalla/valhalla/tree/master/docker), including `/custom_files`, `server_threads`, and the documented startup/build behavior.
- TileServer GL: [TileServer GL README](https://github.com/maptiler/tileserver-gl) and [configuration documentation](https://tileserver.readthedocs.io/en/latest/config.html), including Docker image invocation, MBTiles, style, glyph, and `public_url` configuration.
- Planetiler: [Planetiler README](https://github.com/onthegomap/planetiler), including its `ghcr.io/onthegomap/planetiler:latest` Docker usage and custom YAML profile flow.
- Nginx, PHP, MySQL, and Redis use their published Docker image docs: [nginx](https://hub.docker.com/_/nginx), [PHP](https://hub.docker.com/_/php), [MySQL](https://hub.docker.com/_/mysql), and [Redis](https://hub.docker.com/_/redis). PHP-FPM is behind Nginx and is not published directly; MySQL and Redis are not published to the host.

OpenStreetMap attribution is included in the style. The OSM data extract and generated tiles are not bundled into the mobile binary.