<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('member_statuses')) return;
        $columns = array_flip(Schema::getColumnListing('member_statuses'));
        foreach (['safe' => 'Safe', 'unsafe' => 'Unsafe', 'needs_help' => 'Needs help', 'evacuated' => 'Evacuated'] as $key => $label) {
            if (DB::table('member_statuses')->where('status_key', $key)->exists()) continue;
            DB::table('member_statuses')->insert(array_intersect_key([
                'status_key' => $key, 'status_label' => $label,
                'description' => $label, 'severity_rank' => in_array($key, ['unsafe', 'needs_help'], true) ? 3 : 0,
                'requires_rescue' => in_array($key, ['unsafe', 'needs_help'], true),
                'is_terminal' => false, 'color_hex' => in_array($key, ['unsafe', 'needs_help'], true) ? '#dc2626' : '#16a34a',
                'sort_order' => array_search($key, ['safe', 'unsafe', 'needs_help', 'evacuated'], true),
                'is_active' => true, 'counts_as_death' => false, 'counts_as_injury' => false,
                'created_at' => now(), 'updated_at' => now(),
            ], $columns));
        }
    }

    public function down(): void
    {
        // Status rows may be referenced by reports; preserve them on rollback.
    }
};
