<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RANKS = [
        'tcws_1' => 'low',
        'tcws_2' => 'low',
        'tcws_3' => 'medium',
        'tcws_4' => 'high',
        'tcws_5' => 'critical',
        'peis_i' => 'low',
        'peis_ii' => 'low',
        'peis_iii' => 'low',
        'peis_iv' => 'low',
        'peis_v' => 'medium',
        'peis_vi' => 'medium',
        'peis_vii' => 'high',
        'peis_viii' => 'high',
        'peis_ix' => 'critical',
        'peis_x' => 'critical',
        'flood_alert' => 'low',
        'flood_alarm' => 'medium',
        'flood_critical' => 'critical',
        'volcano_alert_0' => 'low',
        'volcano_alert_1' => 'low',
        'volcano_alert_2' => 'medium',
        'volcano_alert_3' => 'high',
        'volcano_alert_4' => 'critical',
        'volcano_alert_5' => 'critical',
        'landslide_small' => 'low',
        'landslide_medium' => 'medium',
        'landslide_large' => 'high',
        'fire_small' => 'low',
        'fire_medium' => 'medium',
        'fire_large' => 'high',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('severity_levels') || ! Schema::hasColumn('severity_levels', 'severity_key')) {
            throw new \RuntimeException('The severity catalog must exist before ranking its severity keys.');
        }

        DB::transaction(function (): void {
            foreach (self::RANKS as $key => $rank) {
                if (str_ends_with($key, '_'.$rank)) {
                    continue;
                }

                DB::table('severity_levels')
                    ->where('severity_key', $key)
                    ->update(['severity_key' => $key.'_'.$rank]);
            }
        });
    }

    public function down(): void
    {
        // Keep severity keys stable once they may be referenced by operational records.
    }
};
