<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CORRECTIONS = [
        'flood_critical_critical' => 'flood_critical',
        'landslide_medium_medium' => 'landslide_medium',
        'fire_medium_medium' => 'fire_medium',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::CORRECTIONS as $invalidKey => $canonicalKey) {
                $severity = DB::table('severity_levels')->where('severity_key', $invalidKey)->first();
                if (! $severity) {
                    continue;
                }

                if (DB::table('severity_levels')->where('severity_key', $canonicalKey)->exists()) {
                    throw new \RuntimeException('Cannot normalize duplicate severity key '.$invalidKey.' because '.$canonicalKey.' already exists.');
                }

                DB::table('severity_levels')
                    ->where('severity_id', $severity->severity_id)
                    ->update(['severity_key' => $canonicalKey]);
            }
        });
    }

    public function down(): void
    {
        // Keep severity keys stable once they may be referenced by operational records.
    }
};
