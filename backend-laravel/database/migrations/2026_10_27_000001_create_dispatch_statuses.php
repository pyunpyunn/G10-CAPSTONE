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
        $jsonPath = database_path('data/dispatch_statuses.json');
        $rows = file_exists($jsonPath)
            ? json_decode(file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR)
            : [
                ['status_key' => 'dispatched', 'label' => 'Dispatched', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => false, 'dashboard_group' => 'dispatched', 'is_alias' => false, 'is_active' => true, 'sort_order' => 1],
                ['status_key' => 'accepted', 'label' => 'Accepted', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => true, 'dashboard_group' => 'dispatched', 'is_alias' => false, 'is_active' => true, 'sort_order' => 2],
                ['status_key' => 'en_route', 'label' => 'En route', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => true, 'dashboard_group' => 'en-route', 'is_alias' => false, 'is_active' => true, 'sort_order' => 3],
                ['status_key' => 'on_scene', 'label' => 'On-scene', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => true, 'dashboard_group' => 'on-scene', 'is_alias' => false, 'is_active' => true, 'sort_order' => 4],
                ['status_key' => 'onscene', 'label' => 'On-scene (legacy alias)', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => false, 'dashboard_group' => 'on-scene', 'is_alias' => true, 'is_active' => true, 'sort_order' => 5],
                ['status_key' => 'returning', 'label' => 'Returning', 'is_open' => true, 'allow_create' => true, 'allow_mobile' => false, 'dashboard_group' => 'other', 'is_alias' => false, 'is_active' => true, 'sort_order' => 6],
                ['status_key' => 'completed', 'label' => 'Completed', 'is_open' => false, 'allow_create' => false, 'allow_mobile' => true, 'dashboard_group' => 'completed', 'is_alias' => false, 'is_active' => true, 'sort_order' => 7],
                ['status_key' => 'cancelled', 'label' => 'Cancelled', 'is_open' => false, 'allow_create' => false, 'allow_mobile' => true, 'dashboard_group' => 'other', 'is_alias' => false, 'is_active' => true, 'sort_order' => 8],
            ];
        DB::table('dispatch_statuses')->insertOrIgnore($rows);
    }

    public function down(): void { /* Preserve the status catalog and its operational references. */ }
};
