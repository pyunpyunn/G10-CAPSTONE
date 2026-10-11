<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['push_provider_ids_json', 'push_errors_json'] as $column) {
            if (Schema::hasTable('disaster_broadcasts') && ! Schema::hasColumn('disaster_broadcasts', $column)) {
                Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->text($column)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['push_provider_ids_json', 'push_errors_json'] as $column) {
            if (Schema::hasColumn('disaster_broadcasts', $column)) {
                Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
