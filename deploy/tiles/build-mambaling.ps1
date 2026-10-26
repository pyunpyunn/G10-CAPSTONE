param(
    [double]$BufferDegrees = 0.003,
    [string]$PackVersion = 'v1',
    [string]$TileServerBaseUrl = 'http://localhost:8080'
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Install Docker Engine and ensure `docker` is on PATH before building the map.'
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$dataRoot = Join-Path $repoRoot 'data'
$buildRoot = Join-Path $dataRoot 'build'
$tileRoot = Join-Path $dataRoot 'tiles'
$valhallaRoot = Join-Path $dataRoot 'valhalla'
$boundaryPath = Join-Path $repoRoot 'backend-laravel/database/boundaries/0730600049.geojson'
$philippinesPath = Join-Path $buildRoot 'philippines-latest.osm.pbf'
$cebuPath = Join-Path $buildRoot 'cebu-city-buffer.osm.pbf'
$mambalingPath = Join-Path $buildRoot 'mambaling-buffer.osm.pbf'
$mbtilesPath = Join-Path $tileRoot 'mambaling.mbtiles'
$packRoot = Join-Path $tileRoot 'offline-packs'
$stageRoot = Join-Path $tileRoot '.pack-stage'

New-Item -ItemType Directory -Force -Path $buildRoot, $tileRoot, $valhallaRoot, (Join-Path $tileRoot 'styles'), (Join-Path $tileRoot 'sprites'), (Join-Path $tileRoot 'fonts/Noto Sans Regular'), $packRoot | Out-Null

if (-not (Test-Path $philippinesPath)) {
    Invoke-WebRequest 'https://download.geofabrik.de/asia/philippines-latest.osm.pbf' -OutFile $philippinesPath
}

$cebuBbox = '123.72,10.20,123.95,10.45'
$feature = Get-Content $boundaryPath -Raw | ConvertFrom-Json
$ring = if ($feature.geometry.type -eq 'Polygon') {
    $feature.geometry.coordinates[0]
} else {
    $feature.geometry.coordinates[0][0]
}
$longitudes = @($ring | ForEach-Object { [double] $_[0] })
$latitudes = @($ring | ForEach-Object { [double] $_[1] })
$west = ($longitudes | Measure-Object -Minimum).Minimum - $BufferDegrees
$south = ($latitudes | Measure-Object -Minimum).Minimum - $BufferDegrees
$east = ($longitudes | Measure-Object -Maximum).Maximum + $BufferDegrees
$north = ($latitudes | Measure-Object -Maximum).Maximum + $BufferDegrees
$culture = [System.Globalization.CultureInfo]::InvariantCulture
$mambalingBbox = '{0},{1},{2},{3}' -f $west.ToString('0.000000', $culture), $south.ToString('0.000000', $culture), $east.ToString('0.000000', $culture), $north.ToString('0.000000', $culture)

docker run --rm --memory=2g --cpus=2 -v "${dataRoot}:/data" debian:bookworm-slim sh -ec 'apt-get update -qq && apt-get install -y -qq osmium-tool >/dev/null && osmium extract --overwrite --strategy complete_ways --bbox "$1" --output /data/build/cebu-city-buffer.osm.pbf /data/build/philippines-latest.osm.pbf && cp /data/build/cebu-city-buffer.osm.pbf /data/valhalla/cebu-city-buffer.osm.pbf && osmium extract --overwrite --strategy complete_ways --bbox "$2" --output /data/build/mambaling-buffer.osm.pbf /data/build/cebu-city-buffer.osm.pbf' osmium $cebuBbox $mambalingBbox
if ($LASTEXITCODE -ne 0) { throw 'The temporary osmium container failed to create the Cebu and Mambaling extracts.' }

Copy-Item (Join-Path $PSScriptRoot 'mambaling.json') (Join-Path $tileRoot 'styles/mambaling.json') -Force
Copy-Item (Join-Path $PSScriptRoot 'config.json') (Join-Path $tileRoot 'config.json') -Force

foreach ($stylePath in @((Join-Path $tileRoot 'styles/mambaling.json'), (Join-Path $tileRoot 'config.json'))) {
    $styleConfig = Get-Content $stylePath -Raw | ConvertFrom-Json
    if ($stylePath -like '*styles*') {
        $styleConfig.sources.mambaling.bounds = @($west, $south, $east, $north)
    } else {
        $styleConfig.styles.mambaling.tilejson.bounds = @($west, $south, $east, $north)
    }
    $styleConfig | ConvertTo-Json -Depth 30 | Set-Content -Encoding utf8 $stylePath
}

$fontBase = 'https://fonts.openmaptiles.org/Noto%20Sans%20Regular'
$fontDirectory = Join-Path $tileRoot 'fonts/Noto Sans Regular'
foreach ($range in @('0-255', '256-511')) {
    Invoke-WebRequest "$fontBase/$range.pbf" -OutFile (Join-Path $fontDirectory "$range.pbf")
}

docker run --rm --memory=4g --cpus=2 -e JAVA_TOOL_OPTIONS='-Xmx2g' -v "${dataRoot}:/data" -v "${PSScriptRoot}:/schema:ro" ghcr.io/onthegomap/planetiler:latest generate-custom --schema=/schema/mambaling.yml --output=/data/tiles/mambaling.mbtiles --force
if ($LASTEXITCODE -ne 0) { throw 'Planetiler failed to build the Mambaling MBTiles file.' }

$packFiles = @($mbtilesPath) + @(Get-ChildItem $fontDirectory -File | ForEach-Object { $_.FullName })
$packBytes = ($packFiles | Get-Item | Measure-Object -Property Length -Sum).Sum
Write-Output ("Mambaling tiles plus Latin glyphs: {0:N2} MiB" -f ($packBytes / 1MB))
Write-Output ("Tiles: {0:N2} MiB; glyph files: {1}" -f ((Get-Item $mbtilesPath).Length / 1MB), (Get-ChildItem $fontDirectory -File).Count)

$versionStage = Join-Path $stageRoot $PackVersion
New-Item -ItemType Directory -Force -Path (Join-Path $versionStage 'styles'), (Join-Path $versionStage 'fonts/Noto Sans Regular') | Out-Null
Copy-Item $mbtilesPath $versionStage -Force
Copy-Item (Join-Path $tileRoot 'styles/mambaling.json') (Join-Path $versionStage 'styles/mambaling.json') -Force
Copy-Item (Join-Path $fontDirectory '*') (Join-Path $versionStage 'fonts/Noto Sans Regular') -Force
$packPath = Join-Path $packRoot "$PackVersion.zip"
Compress-Archive -Path (Join-Path $versionStage '*') -DestinationPath $packPath -Force
$checksum = (Get-FileHash -Path $packPath -Algorithm SHA256).Hash.ToLowerInvariant()
$packManifest = [ordered]@{
    version = $PackVersion
    bbox = @($west, $south, $east, $north)
    style_url = "$( $TileServerBaseUrl.TrimEnd('/') )/styles/mambaling/style.json"
    size_bytes = (Get-Item $packPath).Length
    checksum = $checksum
    pack_file = "$PackVersion.zip"
}
$packManifest | ConvertTo-Json -Depth 5 | Set-Content -Encoding utf8 (Join-Path $packRoot "$PackVersion.json")
Write-Output ("Offline pack {0}: {1:N2} MiB; SHA-256 {2}" -f $PackVersion, ((Get-Item $packPath).Length / 1MB), $checksum)