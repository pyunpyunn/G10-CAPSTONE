<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Web\HazardLayerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HazardLayerController extends Controller
{
    public function store(Request $request, HazardLayerService $service) {
        $data = $request->validate([
            'name'=>'required|string|max:150', 'source'=>'required|string|max:255',
            'source_url'=>'nullable|url:http,https|max:255', 'assessed_at'=>'required|date|before_or_equal:today',
            'geojson.type'=>'required|in:FeatureCollection', 'geojson.features'=>'required|array|min:1|max:2000',
            'geojson.features.*.type'=>'required|in:Feature',
            'geojson.features.*.geometry.type'=>'required|in:Point,LineString,Polygon,MultiPolygon',
            'geojson.features.*.geometry.coordinates'=>'required|array',
            'geojson.features.*.properties.kind'=>'required|in:hazard,evacuation_center,safe_route',
            'geojson.features.*.properties.name'=>'required|string|max:150',
            'geojson.features.*.properties.hazard_type'=>'required|string|max:100',
            'geojson.features.*.properties.risk_level'=>'required|in:low,medium,high',
            'geojson.features.*.properties.purok_id'=>'required|integer|min:1',
            'geojson.features.*.properties.individuals'=>'nullable|integer|min:0',
            'geojson.features.*.properties.families'=>'nullable|integer|min:0',
            'geojson.features.*.properties.destination'=>'nullable|string|max:150',
            'geojson.features.*.properties.reviewed_by'=>'required|string|max:150',
        ]);
        $barangay = $service->barangayId();
        $options = app(\App\Queries\MappingQuery::class)->getPurokOptions($barangay);
        $ids = array_map(fn ($row) => (string) $row['purok_id'], $options);
        foreach ($data['geojson']['features'] as $feature) {
            $service->validateGeometry($feature);
            if ($feature['properties']['kind'] === 'safe_route' && empty($feature['properties']['destination'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['geojson'=>'Safe routes require the designated destination center name in properties.destination.']);
            }
            if (! in_array((string)$feature['properties']['purok_id'], $ids, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['geojson'=>'Every feature must belong to a purok in the configured barangay.']);
            }
        }
        $id = DB::table('mapping_hazard_layers')->insertGetId([
            'barangay_id'=>$barangay, 'name'=>$data['name'], 'source'=>$data['source'],
            'source_url'=>$data['source_url'] ?? null, 'assessed_at'=>$data['assessed_at'],
            'geojson'=>json_encode($data['geojson']), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return response()->json(['data'=>['id'=>$id]], 201);
    }
    public function destroy(string $id, HazardLayerService $service) {
        $deleted = DB::table('mapping_hazard_layers')->where('barangay_id', $service->barangayId())->where('id', $id)->delete();
        abort_unless($deleted, 404);
        return response()->json(['message'=>'Hazard layer removed.']);
    }
}
