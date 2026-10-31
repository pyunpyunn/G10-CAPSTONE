<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = json_decode(file_get_contents(database_path('data/dispatch_statuses.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($catalog): void {
            $hasCode = Schema::hasColumn('dispatch_statuses', 'status_code');
            $used = $hasCode ? DB::table('dispatch_statuses')->whereNotNull('status_code')->pluck('status_code')->all() : [];
            $number = 1;
            foreach ($catalog as $entry) {
                if (DB::table('dispatch_statuses')->where('status_key', $entry['status_key'])->exists()) continue;
                if ($hasCode) {
                    do { $code = sprintf('DSP-%03d', $number++); } while (in_array($code, $used, true));
                    $entry['status_code'] = $code;
                    $used[] = $code;
                }
                DB::table('dispatch_statuses')->insert($entry);
            }
            // These existing application choices have business keys, never assumed numeric IDs.
            foreach (['no_damage' => 'None', 'minor_damage' => 'Minor', 'major_damage' => 'Major', 'completely_destroyed' => 'Destroyed', 'not_yet_assessed' => 'Not assessed'] as $key => $label) {
                if (! DB::table('property_damage_levels')->where('damage_key', $key)->exists()) {
                    DB::table('property_damage_levels')->insert(['damage_key' => $key, 'label' => $label, 'sort_order' => array_search($key, ['no_damage', 'minor_damage', 'major_damage', 'completely_destroyed', 'not_yet_assessed']), 'is_active' => true]);
                }
            }
        });
    }

    public function down(): void
    {
        // Catalogs may already be referenced by operational records.
    }
};
