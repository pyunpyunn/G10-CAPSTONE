<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rescue_criteria_line_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
            $table->unsignedInteger('bucket_hour');
            $table->timestamp('observed_at');
            foreach (['impact_open', 'household_total', 'special_open', 'vulnerable_total',
                'unreported_open', 'member_total', 'no_contact_open', 'no_contact_total'] as $column) {
                $table->unsignedInteger($column);
            }
            $table->timestamps();
            $table->unique(['event_id', 'bucket_hour'], 'rescue_criteria_event_bucket_unique');
        });
    }

    // Historical measurements are retained on rollback by the project's data-retention policy.
    public function down(): void {}
};
