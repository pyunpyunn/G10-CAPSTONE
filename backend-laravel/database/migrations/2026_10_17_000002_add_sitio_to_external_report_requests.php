<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_report_requests', fn (Blueprint $table) => $table->string('sitio_id', 120)->nullable());
    }

    public function down(): void
    {
        Schema::table('external_report_requests', fn (Blueprint $table) => $table->dropColumn('sitio_id'));
    }
};
