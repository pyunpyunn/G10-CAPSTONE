<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATALOG = [
        'TYPHOON' => [
            'name' => 'Typhoon',
            'levels' => [
                ['tcws_1', 'low', 'TCWS 1 - 39-61 km/h winds'],
                ['tcws_2', 'low', 'TCWS 2 - 62-88 km/h winds'],
                ['tcws_3', 'medium', 'TCWS 3 - 89-117 km/h winds'],
                ['tcws_4', 'high', 'TCWS 4 - 118-184 km/h winds'],
                ['tcws_5', 'critical', 'TCWS 5 - 185 km/h or higher winds'],
            ],
        ],
        'EARTHQUAKE' => [
            'name' => 'Earthquake',
            'levels' => [
                ['peis_i', 'low', 'PEIS I — Scarcely Perceptible'],
                ['peis_ii', 'low', 'PEIS II — Slightly Felt'],
                ['peis_iii', 'low', 'PEIS III — Weak'],
                ['peis_iv', 'low', 'PEIS IV — Moderately Strong'],
                ['peis_v', 'medium', 'PEIS V — Strong'],
                ['peis_vi', 'medium', 'PEIS VI — Very Strong'],
                ['peis_vii', 'high', 'PEIS VII — Destructive'],
                ['peis_viii', 'high', 'PEIS VIII — Very Destructive'],
                ['peis_ix', 'critical', 'PEIS IX — Devastating'],
                ['peis_x', 'critical', 'PEIS X — Completely Devastating'],
            ],
        ],
        'FLASH_FLOOD' => [
            'name' => 'Flash Flood',
            'levels' => [
                ['flood_alert', 'low', 'PAGASA Alert - basin assessment level (~40% full)'],
                ['flood_alarm', 'medium', 'PAGASA Alarm - basin assessment level (~60% full)'],
                ['flood_critical', 'critical', 'PAGASA Critical - basin assessment level (100% full)'],
            ],
        ],
        'VOLCANIC_ERUPTION' => [
            'name' => 'Volcanic Eruption',
            'levels' => [
                ['volcano_alert_0', 'low', 'PHIVOLCS Alert Level 0 — Normal'],
                ['volcano_alert_1', 'low', 'PHIVOLCS Alert Level 1 — Abnormal'],
                ['volcano_alert_2', 'medium', 'PHIVOLCS Alert Level 2 — Increasing unrest'],
                ['volcano_alert_3', 'high', 'PHIVOLCS Alert Level 3 — Magmatic unrest'],
                ['volcano_alert_4', 'critical', 'PHIVOLCS Alert Level 4 — Hazardous eruption imminent'],
                ['volcano_alert_5', 'critical', 'PHIVOLCS Alert Level 5 — Hazardous eruption ongoing'],
            ],
        ],
        'LANDSLIDE' => [
            'name' => 'Landslide',
            'levels' => [
                ['landslide_small', 'low', 'Small-scale — localized impact'],
                ['landslide_medium', 'medium', 'Medium-scale — multi-barangay or inter-agency response'],
                ['landslide_large', 'high', 'Large-scale — widespread impact or catastrophe'],
            ],
        ],
        'FIRE' => [
            'name' => 'Fire',
            'levels' => [
                ['fire_small', 'low', 'Small-scale — localized impact'],
                ['fire_medium', 'medium', 'Medium-scale — multi-barangay or inter-agency response'],
                ['fire_large', 'high', 'Large-scale — widespread impact or catastrophe'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('disaster_types') || ! Schema::hasTable('severity_levels')) {
            throw new \RuntimeException('Disaster type and severity tables must exist before applying this catalog migration.');
        }

        if (! Schema::hasTable('disaster_type_severity_levels')) {
            Schema::create('disaster_type_severity_levels', function (Blueprint $table): void {
                $table->integer('type_id');
                $table->integer('severity_id');
                $table->primary(['type_id', 'severity_id']);
                $table->foreign('type_id')->references('type_id')->on('disaster_types')->cascadeOnDelete();
                $table->foreign('severity_id')->references('severity_id')->on('severity_levels')->cascadeOnDelete();
            });
        }

        DB::transaction(function (): void {
            $typeIds = [];
            foreach (self::CATALOG as $code => $entry) {
                $type = DB::table('disaster_types')->where('type_code', $code)->first();
                if (! $type && $code === 'TYPHOON') {
                    $type = DB::table('disaster_types')
                        ->where(function ($query): void {
                            $query->where('type_code', 'SUPER_TYPHOON')
                                ->orWhereRaw('LOWER(type_name) = ?', ['super typhoon']);
                        })
                        ->first();
                }

                if ($type) {
                    $typeIds[$code] = (int) $type->type_id;
                    DB::table('disaster_types')->where('type_id', $type->type_id)->update([
                        'type_code' => $code,
                        'type_name' => $entry['name'],
                        ...$this->activeTypeValues(),
                        ...$this->timestamps('disaster_types'),
                    ]);
                } else {
                    $typeId = ((int) DB::table('disaster_types')->lockForUpdate()->max('type_id')) + 1;
                    $typeIds[$code] = $typeId;
                    DB::table('disaster_types')->insert([
                        'type_id' => $typeId,
                        'type_code' => $code,
                        'type_name' => $entry['name'],
                        ...$this->activeTypeValues(),
                        ...$this->timestamps('disaster_types', true),
                    ]);
                }
            }

            $severityIds = [];
            foreach (self::CATALOG as $code => $entry) {
                foreach ($entry['levels'] as [$key, $severityRank, $label]) {
                    $severityKey = str_ends_with($key, '_'.$severityRank) ? $key : $key.'_'.$severityRank;
                    $severity = DB::table('severity_levels')->where('severity_key', $severityKey)->first();
                    if ($severity) {
                        $severityIds[$key] = (int) $severity->severity_id;
                        DB::table('severity_levels')->where('severity_id', $severity->severity_id)->update([
                            'severity_label' => $label,
                        ]);
                    } else {
                        $severityId = ((int) DB::table('severity_levels')->lockForUpdate()->max('severity_id')) + 1;
                        $severityIds[$key] = $severityId;
                        DB::table('severity_levels')->insert([
                            'severity_id' => $severityId,
                            'severity_key' => $severityKey,
                            'severity_label' => $label,
                        ]);
                    }

                    DB::table('disaster_type_severity_levels')->insertOrIgnore([
                        'type_id' => $typeIds[$code],
                        'severity_id' => $severityIds[$key],
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('disaster_type_severity_levels')) {
            Schema::drop('disaster_type_severity_levels');
        }
    }

    private function timestamps(string $table, bool $includeCreated = false): array
    {
        $columns = [];
        if ($includeCreated && Schema::hasColumn($table, 'created_at')) $columns['created_at'] = now();
        if (Schema::hasColumn($table, 'updated_at')) $columns['updated_at'] = now();
        return $columns;
    }

    private function activeTypeValues(): array
    {
        return Schema::hasColumn('disaster_types', 'is_active') ? ['is_active' => 1] : [];
    }
};
