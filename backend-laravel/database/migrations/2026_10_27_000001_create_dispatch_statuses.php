<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('dispatch_statuses')) {
            Schema::create('dispatch_statuses', function (Blueprint $table): void {
                $table->string('status_key', 50)->primary();
                $table->string('label', 100);
                $table->boolean('is_open')->default(true);
                $table->boolean('allow_create')->default(false);
                $table->boolean('allow_mobile')->default(false);
                $table->string('dashboard_group', 30)->default('other');
                $table->boolean('is_alias')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
            });
        }
        DB::table('dispatch_statuses')->insertOrIgnore(json_decode(file_get_contents(database_path('data/dispatch_statuses.json')), true, 512, JSON_THROW_ON_ERROR));
    }

    public function down(): void { /* Preserve the status catalog and its operational references. */ }
};
