<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('remembered_devices')) {
            return;
        }

        Schema::create('remembered_devices', function (Blueprint $table): void {
            $table->uuid('remembered_device_id')->primary();
            $table->string('user_id', 255)->index();
            $table->string('token_hash', 255)->unique();
            $table->string('device_name', 150)->nullable();
            $table->string('platform', 30)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['expires_at', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remembered_devices');
    }
};
