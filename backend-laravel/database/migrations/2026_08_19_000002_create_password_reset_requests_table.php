<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_requests')) {
            return;
        }

        Schema::create('password_reset_requests', function (Blueprint $table): void {
            $table->uuid('request_id')->primary();
            $table->string('user_id', 255)->nullable()->index();
            $table->string('login_value', 255)->nullable();
            $table->enum('verification_method', ['previous_password', 'security_questions']);
            $table->string('token_hash', 255)->nullable()->unique();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
            $table->index(['expires_at', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
