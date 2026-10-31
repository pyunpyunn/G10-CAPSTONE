<?php

use App\Services\Shared\ResqDatabaseIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reuse current module definitions, excluding retired device/access/advisory tables
        // and all historical identity rewrites and data imports.
        foreach ([
            '2026_10_04_000006_create_rescue_criteria_snapshots_table.php',
            '2026_10_05_000001_create_rescue_criteria_line_snapshots.php',
            '2026_10_05_000002_create_rescue_criteria_contact_baselines.php',
            '2026_10_08_000002_create_household_mobile_notifications_table.php',
            '2026_10_15_000001_create_household_geographic_classification_tables.php',
            '2026_10_17_000001_create_external_report_requests_table.php',
            '2026_10_18_000021_create_situation_report_submissions.php',
            '2026_10_09_000001_pin_rescue_priority_settings_to_events.php',
            '2026_10_09_000003_version_rescue_bands_and_allow_empty_measurements.php',
            '2026_10_30_000002_reconcile_rescue_criteria_definition_version.php',
            '2026_10_29_000005_persist_resource_request_urgency_catalog.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        if (! Schema::hasColumn('external_report_requests', 'api_version')) {
            (require database_path('migrations/2026_10_18_000022_add_external_report_contract_version.php'))->up();
        }
        if (! Schema::hasColumn('external_report_requests', 'sitio_id')) {
            (require database_path('migrations/2026_10_17_000002_add_sitio_to_external_report_requests.php'))->up();
        }
        foreach ([
            'schema_version' => fn (Blueprint $t) => $t->string('schema_version', 8)->default('2.0'),
            'sitrep_type' => fn (Blueprint $t) => $t->string('sitrep_type', 16)->nullable(),
            'approved_by' => fn (Blueprint $t) => $t->string('approved_by', 150)->nullable(),
            'operator_inputs' => fn (Blueprint $t) => $t->json('operator_inputs')->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('situation_reports', $column)) Schema::table('situation_reports', $add);
        }
        if (! Schema::hasTable('resq_orphan_references')) {
            Schema::create('resq_orphan_references', function (Blueprint $t): void {
                $t->id();
                $t->string('source_table', 64);
                $t->string('source_key', 255);
                $t->string('source_column', 64);
                $t->string('original_value', 255);
                $t->string('parent_table', 64);
                $t->timestamp('preserved_at')->useCurrent();
                $t->unique(['source_table', 'source_key', 'source_column'], 'resq_orphan_source_unique');
            });
        }
        if (DB::getDriverName() === 'mysql') {
            $result = app(ResqDatabaseIntegrity::class)->apply(DB::connection()->getPdo());
            if ($result['blocked']) throw new RuntimeException('References require review; no identities were guessed: '.implode('; ', $result['blocked']));
        }
    }

    public function down(): void
    {
        // Preserve operational data and additive shared compatibility fields.
    }
};
