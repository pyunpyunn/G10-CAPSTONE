<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('rescue_priority_settings')) {
            foreach (['high_percent', 'medium_percent', 'deploy_first_percent'] as $column) {
                if (! Schema::hasColumn('rescue_priority_settings', $column)) {
                    Schema::table('rescue_priority_settings', function (Blueprint $table) use ($column): void {
                        $table->decimal($column, 5, 1)->default(config('rescue_priority.'.$column));
                    });
                }
            }
        }
        if (Schema::hasTable('rescue_criteria_snapshots')) {
            Schema::table('rescue_criteria_snapshots', function (Blueprint $table): void {
                foreach (['impact', 'special_needs', 'unreported', 'no_contact'] as $column) {
                    $table->decimal($column, 5, 1)->nullable()->change();
                }
            });
        }
    }

    // Keep versioned settings and measurements, including empty denominators.
    public function down(): void {}
};
