<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HouseholdMemberIdentity
{
    public static function prefix(string $householdId): string
    {
        if (! preg_match('/(\d+)$/', trim($householdId), $matches)) {
            throw new InvalidArgumentException('Household IDs must end in a numeric household number.');
        }
        return 'HM-'.$matches[1].'-';
    }

    public function next(string $householdId): string
    {
        return DB::transaction(function () use ($householdId): string {
            $prefix = self::prefix($householdId);
            $key = 'household_member_'.$householdId;
            // Reserve sequence numbers even when the caller inserts the member later.
            DB::table('operational_sequences')->insertOrIgnore(['sequence_key' => $key, 'next_value' => 1]);
            $sequence = DB::table('operational_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            $maximum = DB::table('household_members')->where('household_id', $householdId)->pluck('member_id')
                ->map(fn ($id) => preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $id, $m) ? (int) $m[1] : 0)->max() ?? 0;
            $number = max((int) $sequence->next_value, $maximum + 1);
            DB::table('operational_sequences')->where('sequence_key', $key)->update(['next_value' => $number + 1]);
            return $prefix.$number;
        }, 3);
    }
}
