<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('household_password_recovery_otps')) {
            return;
        }

        Schema::create('household_password_recovery_otps', function (Blueprint $table): void {
            $table->string('user_id', 255)->primary();
            $table->string('household_id', 255)->index();
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_password_recovery_otps');
    }
};
