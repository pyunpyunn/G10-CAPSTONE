<?php

namespace App\Services\Mobile;

use App\Services\Shared\BarangayProfileService;
use App\Services\Shared\HouseholdGeographicClassifier;
use App\Services\Shared\OperationalSequence;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HouseholdSetupAddress
{
    public function registered(string $householdId): ?array
    {
        $address = $this->record($householdId);
        $area = $address ? $this->area((int) ($address->purok_id ?? 0)) : null;
        return $area ? array_merge((array) $address, $area, ['source' => 'registered_address']) : null;
    }

    public function resolve(string $householdId, float $latitude, float $longitude): array
    {
        $focus = app(BarangayProfileService::class)->mambalingMapFocus();
        $classification = app(HouseholdGeographicClassifier::class)->resolve($latitude, $longitude, $focus);
        if (in_array($classification['status'], ['outside_barangay', 'barangay_boundary_missing', 'ambiguous_boundary', 'no_boundary_match'], true)) {
            throw ValidationException::withMessages(['latitude' => ['Choose a location clearly inside the verified Mambaling boundary.']]);
        }
        if ($classification['status'] === 'matched') {
            $area = $this->area((int) $classification['purok_id']);
            if ($area) return array_merge((array) ($this->record($householdId) ?? []), $area, ['source' => 'boundary']);
        }
        $registered = $this->registered($householdId);
        if ($registered && (int) $registered['barangay_id'] === (int) ($focus['barangay_id'] ?? 0)) return $registered;
        throw ValidationException::withMessages(['address' => ['The household needs a registered sitio and purok in the database. Contact HQ/Admin to correct its address before completing setup.']]);
    }

    public function save(string $householdId, array $area, array $details): string
    {
        foreach (['is_apartment', 'building_name', 'floor_number', 'unit_number', 'room_number', 'landmark'] as $column) {
            abort_unless(Schema::hasColumn('addresses', $column), 503, 'The household address update must be installed before setup can be saved.');
        }
        $apartment = (bool) $details['is_apartment'];
        $changes = [
            'barangay_id' => $area['barangay_id'], 'sitio_id' => $area['sitio_id'], 'purok_id' => $area['purok_id'],
            'barangay_name' => $area['barangay_name'], 'purok_sitio' => $area['purok_name'].', '.$area['sitio_name'],
            'house_number' => $details['house_number'] ?? null, 'is_apartment' => $apartment,
            'building_name' => $apartment ? ($details['building_name'] ?? null) : null,
            'floor_number' => $apartment ? ($details['floor_number'] ?? null) : null,
            'unit_number' => $apartment ? $details['unit_number'] : null,
            'room_number' => $apartment ? ($details['room_number'] ?? null) : null,
            'landmark' => $details['landmark'] ?? null,
        ];
        $fullAddress = implode(', ', array_filter([
            $changes['house_number'], $changes['building_name'],
            $changes['floor_number'] ? 'Floor '.$changes['floor_number'] : null,
            $changes['unit_number'] ? 'Unit '.$changes['unit_number'] : null,
            $changes['room_number'] ? 'Room '.$changes['room_number'] : null,
            $area['street'] ?? $area['street_address'] ?? null,
            $area['purok_name'], $area['sitio_name'], $area['barangay_name'], $area['city_name'] ?? null, $area['province_name'] ?? null,
            $changes['landmark'] ? 'Near '.$changes['landmark'] : null,
        ]));
        $changes['full_address'] = mb_substr($fullAddress, 0, 500);
        $household = DB::table('households')->where('household_id', $householdId)->lockForUpdate()->first();
        $address = $this->record($householdId);
        $columns = array_flip(Schema::getColumnListing('addresses'));
        if (isset($columns['updated_at'])) $changes['updated_at'] = now();
        $changes = array_intersect_key($changes, $columns);
        $shared = $address && DB::table('households')->where('address_id', $address->address_id)->where('household_id', '<>', $householdId)->exists();
        if ($address && ! $shared) {
            DB::table('addresses')->where('address_id', $address->address_id)->update($changes);
        } else {
            $data = array_merge((array) $address, $changes);
            $data['address_id'] = app(OperationalSequence::class)->nextNumericId('addresses', 'address_id');
            if (isset($columns['created_at'])) $data['created_at'] = now();
            DB::table('addresses')->insert(array_intersect_key($data, $columns));
            DB::table('households')->where('household_id', $household->household_id)->update(['address_id' => $data['address_id']]);
        }
        return $fullAddress;
    }

    private function record(string $householdId): ?object
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasColumn('households', 'address_id')) return null;
        return DB::table('households as h')->join('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->where('h.household_id', $householdId)
            ->when(Schema::hasColumn('addresses', 'deleted_at'), fn ($q) => $q->whereNull('a.deleted_at'))
            ->first('a.*');
    }

    private function area(int $purokId): ?array
    {
        foreach (['puroks', 'sitios', 'barangays'] as $table) if (! Schema::hasTable($table)) return null;
        $query = DB::table('puroks as p')->join('sitios as s', 's.sitio_id', '=', 'p.sitio_id')
            ->join('barangays as b', 'b.barangay_id', '=', 's.barangay_id')->where('p.purok_id', $purokId);
        $columns = ['p.purok_id', 'p.purok_name', 's.sitio_id', 's.sitio_name', 'b.barangay_id', 'b.barangay_name'];
        if (Schema::hasTable('cities') && Schema::hasColumn('barangays', 'city_id')) {
            $query->leftJoin('cities as c', 'c.city_id', '=', 'b.city_id');
            $columns[] = 'c.city_name';
            if (Schema::hasTable('provinces') && Schema::hasColumn('cities', 'province_id')) {
                $query->leftJoin('provinces as province', 'province.province_id', '=', 'c.province_id');
                $columns[] = 'province.province_name';
            }
        }
        $area = $query->first($columns);
        return $area ? (array) $area : null;
    }
}
