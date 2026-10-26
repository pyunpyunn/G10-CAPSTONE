<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operational_sequences', function (Blueprint $table): void {
            $table->string('sequence_prefix', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('operational_sequences', function (Blueprint $table): void {
            $table->dropColumn('sequence_prefix');
        });
    }
};
