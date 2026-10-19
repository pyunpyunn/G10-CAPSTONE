<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('base_role_key', 80)->nullable();
            $table->json('web_modules')->nullable();
            $table->boolean('can_manage_personnel')->nullable();
            $table->boolean('can_approve_requests')->nullable();
        });
        foreach (['Team Leader', 'Medic', 'Driver', 'Boat Operator', 'Radio Operator', 'Logistics Officer', 'Site Coordinator'] as $name) {
            $key = 'personnel_'.strtolower(str_replace(' ', '_', $name));
            if (!DB::table('roles')->where('role_key', $key)->exists()) DB::table('roles')->insert([
                'role_id' => (int) DB::table('roles')->max('role_id') + 1, 'role_key' => $key, 'role_name' => $name, 'base_role_key' => 'rescuer',
            ]);
        }
    }
    public function down(): void
    {
        // Assigned roles must not be dropped while user records reference them.
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['base_role_key', 'web_modules', 'can_manage_personnel', 'can_approve_requests']));
    }
};
