<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SafeTrackHouseholdSync
{
    public function sync(array $records, SafeTrackHouseholdDirectory $directory, ?string $sitioId = null): array
    {
        // Validate the complete response before changing either stored representation.
        $directory->changes($records);
        $householdIds = [];
        $memberIds = [];
        foreach ($records as $record) {
            $id = (string) $record['household_id'];
            if (isset($householdIds[$id]) || ! is_array($record['members'] ?? null)) {
                throw new RuntimeException('Invalid or duplicate SafeTrack household.');
            }
            $householdIds[$id] = true;
            foreach ($record['members'] as $member) {
                $memberId = $member['member_id'] ?? null;
                $birthDate = $member['birth_date'] ?? null;
                if (! is_scalar($memberId) || (string) $memberId === '' || isset($memberIds[(string) $memberId])
                    || ! is_string($birthDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)
                    || ! checkdate((int) substr($birthDate, 5, 2), (int) substr($birthDate, 8, 2), (int) substr($birthDate, 0, 4))) {
                    throw new RuntimeException('Invalid or duplicate SafeTrack household member.');
                }
                $memberIds[(string) $memberId] = true;
            }
        }

        return DB::transaction(function () use ($records, $directory, $memberIds, $sitioId): array {
            $existingMembers = DB::table('household_members')->whereIn('member_id', array_keys($memberIds))
                ->lockForUpdate()->get()->keyBy('member_id');
            $now = now();
            foreach ($records as $record) {
                $id = (string) $record['household_id'];
                $household = DB::table('households')->where('household_id', $id)->lockForUpdate()->first();
                if ($household && $household->deleted_at !== null) {
                    throw new RuntimeException('SafeTrack household is locally deleted; manual review required.');
                }
                $values = array_intersect_key($record, array_flip([
                    'household_name', 'email', 'contact_number', 'emergency_contact', 'member_count',
                ]));
                $values['updated_at'] = $now;
                $location = $record['location'] ?? [];
                if ($location) {
                    $address = array_intersect_key($location, array_flip(['street', 'barangay_name']));
                    foreach (['barangay_id' => 'barangays', 'sitio_id' => 'sitios'] as $key => $table) {
                        if (isset($location[$key])) {
                            if (! DB::table($table)->where($key, $location[$key])->exists()) {
                                throw new RuntimeException('SafeTrack location reference is missing; manual review required.');
                            }
                            $address[$key] = $location[$key];
                        }
                    }
                    $address['updated_at'] = $now;
                    if ($household?->address_id !== null) {
                        DB::table('addresses')->where('address_id', $household->address_id)->update($address);
                    } else {
                        $values['address_id'] = DB::table('addresses')->insertGetId($address + ['created_at' => $now], 'address_id');
                    }
                }
                if ($household) DB::table('households')->where('household_id', $id)->update($values);
                else DB::table('households')->insert($values + ['household_id' => $id, 'created_at' => $now]);

                foreach ($record['members'] as $member) {
                    $memberId = (string) $member['member_id'];
                    $existing = $existingMembers->get($memberId);
                    if ($existing && ((string) $existing->household_id !== $id || $existing->deleted_at !== null)) {
                        throw new RuntimeException('SafeTrack member identity conflicts with an existing record.');
                    }
                    $values = array_intersect_key($member, array_flip([
                        'first_name', 'middle_name', 'last_name', 'birth_date', 'gender', 'relation',
                        'is_household_head', 'is_pwd', 'is_senior', 'is_pregnant',
                    ]));
                    $values['household_id'] = $id;
                    $values['updated_at'] = $now;
                    if ($existing) DB::table('household_members')->where('member_id', $memberId)->update($values);
                    else DB::table('household_members')->insert($values + ['member_id' => $memberId, 'created_at' => $now]);
                }
            }

            $directoryResult = $sitioId === null
                ? $directory->sync($records)
                : $directory->syncSitio($records, $sitioId);
            return $directoryResult + ['database_households' => count($records), 'database_members' => count($memberIds)];
        });
    }
}
