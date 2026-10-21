<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_events', fn (Blueprint $table) => $table->json('result_json')->nullable());
    }

    public function down(): void
    {
        Schema::table('sync_events', fn (Blueprint $table) => $table->dropColumn('result_json'));
    }
};
