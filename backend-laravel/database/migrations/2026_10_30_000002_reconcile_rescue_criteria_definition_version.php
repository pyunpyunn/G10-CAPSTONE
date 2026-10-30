<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rescue_criteria_snapshots') && ! Schema::hasColumn('rescue_criteria_snapshots', 'definition_version')) {
            Schema::table('rescue_criteria_snapshots', function (Blueprint $table): void {
                // Existing measurements retain their original definition; only recomputed points use version 6.
                $table->unsignedTinyInteger('definition_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        // Preserve measurement history and its definition metadata.
    }
};
