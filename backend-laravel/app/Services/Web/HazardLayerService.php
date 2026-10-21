<?php
namespace App\Services\Web;

use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class HazardLayerService
{
    public function barangayId(): string {
        $id = app(BarangayProfileService::class)->mapFocus()['barangay_id'];
        abort_if($id === null, 422, 'Configure the barangay before importing hazard maps.');
        return (string) $id;
    }
    public function layers(): array {
        $focus = app(BarangayProfileService::class)->mapFocus();
        $builtins = $this->governmentLayers($focus);
        if (! Schema::hasTable('mapping_hazard_layers') || ($focus['barangay_id'] ?? null) === null) return $builtins;
        $local = DB::table('mapping_hazard_layers')->where('barangay_id', (string) $focus['barangay_id'])->orderBy('name')->get()
            ->map(function ($row) { $row->geojson = json_decode($row->geojson, true); return (array) $row; })->all();
        return array_merge($builtins, $local);
    }

    public function governmentLayers(array $focus): array {
        // Ship source snapshots so government maps work without an import or live upstream connection.
        $name = preg_replace('/^barangay\s+/i', '', trim($focus['name'] ?? ''));
        $center = $focus['center'] ?? [];
        if (strcasecmp($name, 'Mambaling') !== 0
            || ($center['latitude'] ?? 0) < 10.27 || ($center['latitude'] ?? 0) > 10.31
            || ($center['longitude'] ?? 0) < 123.86 || ($center['longitude'] ?? 0) > 123.90) return [];
        return json_decode(file_get_contents(database_path('hazards/mambaling.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function validateGeometry(array $feature): void {
        $kind = $feature['properties']['kind'];
        $geometry = $feature['geometry'];
        $allowed = ['hazard' => ['Polygon','MultiPolygon'], 'evacuation_center' => ['Point'], 'safe_route' => ['LineString']];
        $fail = fn () => throw ValidationException::withMessages(['geojson' => 'Use valid WGS84 hazard polygons, center points, and reviewed route lines.']);
        if (! in_array($geometry['type'], $allowed[$kind], true)) $fail();
        $point = function ($p) use ($fail) {
            if (! is_array($p) || count($p) < 2 || ! is_numeric($p[0]) || ! is_numeric($p[1]) || abs((float)$p[0]) > 180 || abs((float)$p[1]) > 90) $fail();
        };
        $line = function ($coords, $ring = false) use ($point, $fail) {
            if (! is_array($coords) || count($coords) < ($ring ? 4 : 2)) $fail();
            foreach ($coords as $p) $point($p);
            if ($ring && $coords[0] !== $coords[count($coords)-1]) $fail();
        };
        $coords = $geometry['coordinates'];
        if ($geometry['type'] === 'Point') $point($coords);
        elseif ($geometry['type'] === 'LineString') $line($coords);
        else {
            if (! is_array($coords)) $fail();
            $polygons = $geometry['type'] === 'Polygon' ? [$coords] : $coords;
            if (! count($polygons)) $fail();
            foreach ($polygons as $polygon) { if (! is_array($polygon) || ! count($polygon)) $fail(); foreach ($polygon as $ring) $line($ring, true); }
        }
    }
}
