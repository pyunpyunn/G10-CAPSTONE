<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('urgency_levels')) {
            Schema::create('urgency_levels', function (Blueprint $table): void {
                $table->increments('urgency_id');
                $table->string('urgency_key', 50)->unique();
                $table->string('urgency_label', 100);
            });
        }
        // Move the existing application's catalog into persisted lookup rows; never assume numeric IDs.
        foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $key => $label) {
            if (! DB::table('urgency_levels')->where('urgency_key', $key)->exists()) {
                DB::table('urgency_levels')->insert(['urgency_key' => $key, 'urgency_label' => $label]);
            }
        }
        if (! Schema::hasColumn('resource_requests', 'urgency_id')) {
            Schema::table('resource_requests', function (Blueprint $table): void {
                $table->unsignedInteger('urgency_id')->nullable()->index();
                $table->foreign('urgency_id')->references('urgency_id')->on('urgency_levels')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Keep assigned urgency values and their catalog.
    }
};
