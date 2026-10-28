<?php

namespace App\Services\Mobile;

use App\Services\Shared\OperationalSequence;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HouseholdSetupAddress
{
    public function registered(string $householdId): ?array
    {
        $address = $this->record($householdId);
        return $address ? array_merge((array) $address, ['source' => 'registered_address']) : null;
    }

    public function save(string $householdId, array $area, array $details): string
    {
        foreach (['is_apartment', 'building_name', 'floor_number', 'unit_number', 'room_number', 'landmark'] as $column) {
            abort_unless(Schema::hasColumn('addresses', $column), 503, 'The household address update must be installed before setup can be saved.');
        }
        $apartment = (bool) $details['is_apartment'];
        $changes = [
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
            $area['purok_sitio'] ?? null, $area['barangay_name'] ?? null, $area['city_name'] ?? null, $area['province_name'] ?? null,
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

}
