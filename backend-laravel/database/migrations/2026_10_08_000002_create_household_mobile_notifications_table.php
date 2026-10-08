<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_mobile_notifications', function (Blueprint $table): void {
            $table->bigIncrements('notification_id');
            $table->string('household_id', 255);
            $table->string('type', 80);
            $table->string('title', 180);
            $table->text('message');
            $table->string('connection_id', 100)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_mobile_notifications');
    }
};
