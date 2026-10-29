<?php

use App\Services\Shared\ResqDatabaseIntegrity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_statuses') && Schema::hasColumn('household_statuses', 'is_active')) {
            // Retain the lookup and any history; exclude the audit's test-only status from new reports.
            DB::table('household_statuses')->where('status_key', 'test')->update(['is_active' => false]);
        }
        if (DB::getDriverName() !== 'mysql') return;
        $result = app(ResqDatabaseIntegrity::class)->apply(DB::connection()->getPdo());
        if ($result['blocked']) throw new RuntimeException('Some Resq references still require authoritative data: '.implode('; ', $result['blocked']));
    }

    public function down(): void
    {
        // Do not reactivate test data or narrow user identifiers.
    }
};
