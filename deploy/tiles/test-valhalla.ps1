param(
    [Parameter(Mandatory = $true)][double]$FromLat,
    [Parameter(Mandatory = $true)][double]$FromLng,
    [Parameter(Mandatory = $true)][double]$ToLat,
    [Parameter(Mandatory = $true)][double]$ToLng,
    [string]$BaseUrl = 'http://127.0.0.1:8002'
)

$ErrorActionPreference = 'Stop'

foreach ($profile in @('auto', 'pedestrian')) {
    $body = @{
        locations = @(
            @{ lat = $FromLat; lon = $FromLng }
            @{ lat = $ToLat; lon = $ToLng }
        )
        costing = $profile
        units = 'kilometers'
        directions_options = @{ units = 'kilometers' }
    } | ConvertTo-Json -Depth 5

    $route = Invoke-RestMethod -Method Post -Uri "$BaseUrl/route" -ContentType 'application/json' -Body $body
    if ($route.trip.status -ne 0 -or $route.trip.legs.Count -lt 1) {
        throw "Valhalla returned no valid $profile route."
    }

    $leg = $route.trip.legs[0]
    Write-Output ("{0}: {1:N2} km, {2} maneuvers" -f $profile, $route.trip.summary.length, $leg.maneuvers.Count)
}