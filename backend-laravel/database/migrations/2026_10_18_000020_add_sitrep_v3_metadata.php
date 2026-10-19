<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('situation_reports', function (Blueprint $table) {
            $table->string('schema_version', 8)->default('2.0');
            $table->string('sitrep_type', 16)->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->json('operator_inputs')->nullable();
        });

    }
    public function down(): void
    {
        Schema::table('situation_reports', fn (Blueprint $table) => $table->dropColumn(['schema_version','sitrep_type','approved_by','operator_inputs']));
    }
};
