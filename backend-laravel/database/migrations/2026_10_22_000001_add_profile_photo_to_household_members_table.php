<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_members') && ! Schema::hasColumn('household_members', 'profile_photo')) {
            Schema::table('household_members', function (Blueprint $table): void {
                $table->string('profile_photo')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('household_members') && Schema::hasColumn('household_members', 'profile_photo')) {
            Schema::table('household_members', function (Blueprint $table): void {
                $table->dropColumn('profile_photo');
            });
        }
    }
};
