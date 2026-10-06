<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rescue_criteria_snapshots', function (Blueprint $table): void {
            $table->unsignedTinyInteger('definition_version')->default(1);
        });
    }

    public function down(): void
    {
        // Measurement history is retained by policy.
    }
};
