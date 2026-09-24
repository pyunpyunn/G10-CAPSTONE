<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $columns = DB::select('SHOW COLUMNS FROM personal_access_tokens');
        $types = collect($columns)->keyBy('Field');

        if ($types->has('tokenable_type') && ! str_contains(strtolower((string) $types->get('tokenable_type')->Type), 'varchar')) {
            DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_type VARCHAR(255) NOT NULL');
        }

        if ($types->has('tokenable_id') && ! str_contains(strtolower((string) $types->get('tokenable_id')->Type), 'varchar')) {
            DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id VARCHAR(255) NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            $columns = DB::select('SHOW COLUMNS FROM personal_access_tokens');
            $types = collect($columns)->keyBy('Field');

            if ($types->has('tokenable_id') && str_contains(strtolower((string) $types->get('tokenable_id')->Type), 'varchar')) {
                DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id BIGINT UNSIGNED NOT NULL');
            }
        }
    }
};
