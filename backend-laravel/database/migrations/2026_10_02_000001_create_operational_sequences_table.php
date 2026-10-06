<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operational_sequences', function (Blueprint $table): void {
            $table->string('sequence_key')->primary();
            $table->unsignedBigInteger('next_value');
        });

        DB::table('operational_sequences')->insert([
            'sequence_key' => 'incident_archives',
            'next_value' => ((int) DB::table('incident_archives')->max('archive_id')) + 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_sequences');
    }
};
