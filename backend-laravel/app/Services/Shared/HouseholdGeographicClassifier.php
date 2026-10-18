<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;

class HouseholdGeographicClassifier
{
    public function __construct(private BarangayProfileService $profile, private BarangayBoundaryService $boundaries) {}

    public function resolve(float $latitude, float $longitude): array
    {
        $focus = $this->profile->mapFocus();
        if (! $focus['boundary']) return ['status' => 'barangay_boundary_missing'];
        if (! $this->boundaries->contains($focus['boundary'], $latitude, $longitude)) return ['status' => 'outside_barangay'];
        if (! Schema::hasTable('purok_boundaries')) return ['status' => 'purok_boundaries_missing'];
        $areas = DB::table('purok_boundaries as boundary')
            ->join('puroks as p', 'p.purok_id', '=', 'boundary.purok_id')
            ->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->where('s.barangay_id', $focus['barangay_id'])
            ->get(['p.purok_id', 'p.purok_name', 's.sitio_id', 's.sitio_name', 'boundary.geometry_json']);
        if ($areas->isEmpty()) return ['status' => 'purok_boundaries_missing'];
        foreach ($areas as $area) {
            $geometry = json_decode($area->geometry_json, true);
            if (is_array($geometry) && $this->touchesBoundary($geometry, $latitude, $longitude)) return ['status' => 'ambiguous_boundary'];
        }
        $matches = $areas->filter(function ($area) use ($latitude, $longitude): bool {
            $geometry = json_decode($area->geometry_json, true);
            return is_array($geometry) && $this->boundaries->contains($geometry, $latitude, $longitude);
        });
        if ($matches->count() !== 1) return ['status' => $matches->isEmpty() ? 'no_boundary_match' : 'ambiguous_boundary'];
        $area = $matches->first();
        return ['status' => 'matched', 'barangay_id' => $focus['barangay_id'],
            'sitio_id' => (int) $area->sitio_id, 'purok_id' => (int) $area->purok_id,
            'sitio_name' => $area->sitio_name, 'purok_name' => $area->purok_name];
    }

    public function classify(string $householdId, float $latitude, float $longitude): array
    {
        $result = $this->resolve($latitude, $longitude);
        // Preserve a usable geotag even when the required official area data is unavailable.
        if (! Schema::hasTable('household_geographic_classifications')) return $result;
        return DB::transaction(function () use ($householdId, $latitude, $longitude, $result): array {
            $household = DB::table('households')->where('household_id', $householdId)->lockForUpdate()->first();
            if (! $household || ($household->deleted_at ?? null)) return ['status' => 'household_unavailable'];
            $previous = DB::table('household_geographic_classifications')->where('household_id', $householdId)->first();
            if ($result['status'] === 'matched' || ($previous->status ?? null) === 'matched') {
                $this->saveAddress($household, $result);
            }
            DB::table('household_geographic_classifications')->updateOrInsert(['household_id' => $householdId], [
                'status' => $result['status'], 'latitude' => $latitude, 'longitude' => $longitude,
                'sitio_id' => $result['sitio_id'] ?? null, 'purok_id' => $result['purok_id'] ?? null,
                'created_at' => $previous->created_at ?? now(), 'updated_at' => now(),
            ]);
            return $result;
        });
    }

    private function touchesBoundary(array $geometry, float $latitude, float $longitude): bool
    {
        $polygons = ($geometry['type'] ?? '') === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
        foreach ($polygons as $rings) foreach ($rings as $ring) {
            for ($i = 1; $i < count($ring); $i++) {
                [$x1, $y1] = $ring[$i-1]; [$x2, $y2] = $ring[$i];
                $dx = $x2-$x1; $dy = $y2-$y1;
                $length = $dx*$dx + $dy*$dy;
                $t = $length > 0 ? max(0, min(1, (($longitude-$x1)*$dx + ($latitude-$y1)*$dy)/$length)) : 0;
                if (hypot($longitude-($x1+$t*$dx), $latitude-($y1+$t*$dy)) < 0.0000001) return true;
            }
        }
        return false;
    }

    private function saveAddress(object $household, array $result): void
    {
        $address = $household->address_id ? DB::table('addresses')->where('address_id', $household->address_id)->lockForUpdate()->first() : null;
        $changes = ['purok_id' => $result['purok_id'] ?? null, 'sitio_id' => $result['sitio_id'] ?? null];
        if ($result['status'] === 'matched') {
            $changes['barangay_id'] = $result['barangay_id'];
            $changes['purok_sitio'] = $result['purok_name'].', '.$result['sitio_name'];
        } else {
            $changes['purok_sitio'] = null;
        }
        if (Schema::hasColumn('addresses', 'updated_at')) $changes['updated_at'] = now();
        $shared = $address && DB::table('households')->where('address_id', $address->address_id)->where('household_id', '<>', $household->household_id)->exists();
        if ($address && ! $shared) {
            DB::table('addresses')->where('address_id', $address->address_id)->update($changes);
            return;
        }
        $data = array_merge($address ? (array) $address : [], $changes);
        $data['address_id'] = app(OperationalSequence::class)->nextNumericId('addresses', 'address_id');
        if (Schema::hasColumn('addresses', 'created_at')) $data['created_at'] = now();
        DB::table('addresses')->insert($data);
        DB::table('households')->where('household_id', $household->household_id)->update(['address_id' => $data['address_id']]);
    }
}
