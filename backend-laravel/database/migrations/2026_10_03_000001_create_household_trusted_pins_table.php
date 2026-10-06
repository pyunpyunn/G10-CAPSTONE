<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('household_trusted_pins', function (Blueprint $table): void {
            $table->string('household_id', 255)->primary();
            $table->string('pin_hash', 255);
            $table->string('updated_by_user_id', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_trusted_pins');
    }
};