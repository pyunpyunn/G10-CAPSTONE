<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('responder_field_reports')) return;
        $indexes = Schema::getIndexes('responder_field_reports');
        foreach ($indexes as $index) {
            if ($index['unique'] && $index['columns'] === ['assignment_id']) {
                Schema::table('responder_field_reports', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }
        Schema::table('responder_field_reports', fn (Blueprint $table) => $table->unique(
            ['assignment_id', 'responder_id', 'household_id'], 'field_report_assignment_responder_household_unique'
        ));
    }

    public function down(): void
    {
        Schema::table('responder_field_reports', fn (Blueprint $table) => $table->dropUnique('field_report_assignment_responder_household_unique'));
    }
};
