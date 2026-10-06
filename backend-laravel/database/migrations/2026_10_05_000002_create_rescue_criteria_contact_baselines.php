<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rescue_criteria_contact_baselines', function (Blueprint $table): void {
            $table->string('event_id');
            $table->string('household_id');
            $table->boolean('no_contact');
            $table->timestamp('captured_at');
            $table->primary(['event_id', 'household_id'], 'rescue_contact_baseline_pk');
        });
    }

    public function down(): void {}
};
