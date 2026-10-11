<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disaster_broadcasts') && ! Schema::hasColumn('disaster_broadcasts', 'push_accepted_count')) {
            Schema::table('disaster_broadcasts', function (Blueprint $table): void {
                $table->unsignedInteger('push_accepted_count')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('disaster_broadcasts') && Schema::hasColumn('disaster_broadcasts', 'push_accepted_count')) {
            Schema::table('disaster_broadcasts', function (Blueprint $table): void {
                $table->dropColumn('push_accepted_count');
            });
        }
    }
};