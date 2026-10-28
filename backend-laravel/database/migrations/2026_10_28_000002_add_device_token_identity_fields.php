<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('device_tokens', 'household_id')) {
                $table->string('household_id', 255)->nullable();
            }

            if (! Schema::hasColumn('device_tokens', 'user_id')) {
                $table->string('user_id', 255)->nullable();
            }

            if (! Schema::hasColumn('device_tokens', 'player_id')) {
                $table->string('player_id', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Preserve device identity and push registration data.
    }
};
