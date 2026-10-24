<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('responder_routes', function (Blueprint $table): void {
            $table->unsignedBigInteger('responder_id')->nullable()->index();
            $table->string('household_id')->nullable()->index();
            $table->json('navigation_steps')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('responder_routes', function (Blueprint $table): void {
            $table->dropIndex(['responder_id']);
            $table->dropIndex(['household_id']);
            $table->dropColumn(['responder_id', 'household_id', 'navigation_steps']);
        });
    }
};
