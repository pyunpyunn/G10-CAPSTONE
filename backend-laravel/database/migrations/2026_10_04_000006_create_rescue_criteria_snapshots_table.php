<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rescue_criteria_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
            $table->timestamp('observed_at');
            $table->decimal('impact', 5, 1);
            $table->decimal('special_needs', 5, 1);
            $table->decimal('unreported', 5, 1);
            $table->decimal('no_contact', 5, 1);
            $table->unsignedInteger('purok_count');
            $table->unique(['event_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        // Historical rescue measurements are retained by policy.
    }
};
