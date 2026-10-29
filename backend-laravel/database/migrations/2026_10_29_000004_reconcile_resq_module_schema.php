<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reuse the project's verified definitions; never run the shared member-ID rewrite.
        foreach ([
            '2026_10_04_000006_create_rescue_criteria_snapshots_table.php',
            '2026_10_05_000001_create_rescue_criteria_line_snapshots.php',
            '2026_10_05_000002_create_rescue_criteria_contact_baselines.php',
            '2026_10_08_000002_create_household_mobile_notifications_table.php',
            '2026_10_09_000001_pin_rescue_priority_settings_to_events.php',
            '2026_10_09_000003_version_rescue_bands_and_allow_empty_measurements.php',
            '2026_10_15_000001_create_household_geographic_classification_tables.php',
            '2026_10_17_000001_create_external_report_requests_table.php',
            '2026_10_18_000021_create_situation_report_submissions.php',
            '2026_10_18_000031_create_navigation_tables.php',
            '2026_10_21_000001_create_web_push_tables.php',
            '2026_10_25_000001_create_household_password_recovery_otps.php',
            '2026_10_26_000003_create_household_login_and_device_owners.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $definitions = [
            'schema_version' => fn (Blueprint $t) => $t->string('schema_version', 8)->default('2.0'),
            'sitrep_type' => fn (Blueprint $t) => $t->string('sitrep_type', 16)->nullable(),
            'approved_by' => fn (Blueprint $t) => $t->string('approved_by', 150)->nullable(),
            'operator_inputs' => fn (Blueprint $t) => $t->json('operator_inputs')->nullable(),
        ];
        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn('situation_reports', $column)) {
                Schema::table('situation_reports', $definition);
            }
        }
        if (! Schema::hasColumn('external_report_requests', 'api_version')) {
            (require database_path('migrations/2026_10_18_000022_add_external_report_contract_version.php'))->up();
        }
        if (! Schema::hasColumn('operational_sequences', 'sequence_prefix')) {
            (require database_path('migrations/2026_10_02_000003_add_sequence_prefix_to_operational_sequences.php'))->up();
        }
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Imported navigation tables referenced push devices; current sync middleware binds navigation devices.
        foreach (['dispatch_trips', 'sync_events'] as $table) {
            $foreign = collect(Schema::getForeignKeys($table))->first(fn ($fk) => $fk['columns'] === ['device_id']);
            if (($foreign['foreign_table'] ?? null) === 'devices') {
                continue;
            }
            if (DB::table($table)->exists()) {
                throw new RuntimeException("$table contains legacy device references; explicit identity mapping is required before changing its foreign key.");
            }
            if ($foreign) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign($foreign['name']));
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('device_id')->change();
                $t->foreign('device_id')->references('id')->on('devices')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Operational tables, account links and history must survive rollback.
    }
};
