<?php

namespace App\Console\Commands;

use App\Services\Shared\HouseholdGeographicClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyHouseholdGeotags extends Command
{
    protected $signature = 'geography:classify-households {--apply : Persist classifications and matched address IDs}';
    protected $description = 'Classify existing saved household pins using registered purok polygons (preview by default)';

    public function handle(HouseholdGeographicClassifier $classifier): int
    {
        $counts = [];
        $households = DB::table('households')->whereNull('deleted_at')->orderBy('household_id')->get(['household_id']);
        foreach ($households as $household) {
            $pin = DB::table('geotagged_locations')->where('household_id', $household->household_id)
                ->whereNotNull('latitude')->whereNotNull('longitude')->orderByDesc('updated_at')->orderByDesc('location_id')->first();
            if (! $pin) { $counts['no_geotag'] = ($counts['no_geotag'] ?? 0) + 1; continue; }
            $result = $this->option('apply')
                ? $classifier->classify($household->household_id, (float) $pin->latitude, (float) $pin->longitude)
                : $classifier->resolve((float) $pin->latitude, (float) $pin->longitude);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }
        $this->info($this->option('apply') ? 'Classifications saved.' : 'Preview only; no records changed.');
        $this->table(['Classification', 'Households'], collect($counts)->map(fn ($count, $status) => [$status, $count])->values()->all());
        return self::SUCCESS;
    }
}
