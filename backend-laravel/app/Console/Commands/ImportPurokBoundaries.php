<?php

namespace App\Console\Commands;

use App\Queries\HouseholdEligibilityQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPurokBoundaries extends Command
{
    protected $signature = 'geography:import-puroks {file : GeoJSON FeatureCollection with properties.purok_id} {--source= : Official source or provenance}';
    protected $description = 'Import verified purok polygons by registered ID; never infer boundaries from names';

    public function handle(): int
    {
        try {
            if (! trim((string) $this->option('source'))) throw new \InvalidArgumentException('Provide --source with the official dataset provenance.');
            $path = (string) $this->argument('file');
            if (! is_file($path)) throw new \InvalidArgumentException('GeoJSON file was not found.');
            $collection = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (($collection['type'] ?? null) !== 'FeatureCollection' || empty($collection['features'])) throw new \InvalidArgumentException('Expected a nonempty GeoJSON FeatureCollection.');
            $eligible = HouseholdEligibilityQuery::puroks()->pluck('eligible_purok.purok_id')->map(fn ($id) => (int) $id)->all();
            $rows = [];
            foreach ($collection['features'] as $feature) {
                $id = filter_var($feature['properties']['purok_id'] ?? null, FILTER_VALIDATE_INT);
                if (! $id || ! in_array($id, $eligible, true) || isset($rows[$id])) throw new \InvalidArgumentException('Each feature must identify a unique registered purok in the configured barangay.');
                $geometry = $feature['geometry'] ?? [];
                $polygons = match ($geometry['type'] ?? null) { 'Polygon' => [$geometry['coordinates'] ?? []], 'MultiPolygon' => $geometry['coordinates'] ?? [], default => [] };
                if (! $polygons) throw new \InvalidArgumentException('Each feature must contain Polygon or MultiPolygon geometry.');
                foreach ($polygons as $rings) {
                    if (! $rings) throw new \InvalidArgumentException('Polygon rings are missing.');
                    foreach ($rings as $ring) {
                        if (count($ring) < 4 || $ring[0] !== $ring[count($ring)-1]) throw new \InvalidArgumentException('Polygon rings must be closed and contain at least four coordinates.');
                        foreach ($ring as $point) {
                            if (count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1]) || abs((float) $point[0]) > 180 || abs((float) $point[1]) > 90) throw new \InvalidArgumentException('Invalid longitude/latitude coordinate.');
                        }
                    }
                }
                $rows[$id] = ['purok_id' => $id, 'geometry_json' => json_encode($geometry, JSON_THROW_ON_ERROR), 'source' => trim($this->option('source')), 'created_at' => now(), 'updated_at' => now()];
            }
            DB::transaction(fn () => DB::table('purok_boundaries')->upsert(array_values($rows), ['purok_id'], ['geometry_json', 'source', 'updated_at']));
            $this->info(count($rows).' purok boundaries imported. Run geography:classify-households --apply to classify existing household pins.');
            return self::SUCCESS;
        } catch (\JsonException|\InvalidArgumentException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
