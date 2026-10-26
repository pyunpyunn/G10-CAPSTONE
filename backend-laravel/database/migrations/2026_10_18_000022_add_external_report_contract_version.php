<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_report_requests',fn (Blueprint $t) => $t->string('api_version',8)->default('2.0'));
    }
    public function down(): void
    {
        Schema::table('external_report_requests',fn (Blueprint $t) => $t->dropColumn('api_version'));
    }
};
