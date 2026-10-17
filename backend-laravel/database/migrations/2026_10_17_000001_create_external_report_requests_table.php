<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_report_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_system', 40);
            $table->string('reference', 120)->nullable();
            $table->string('event_id')->nullable();
            $table->json('sections');
            $table->string('format', 8);
            $table->text('purpose');
            $table->string('status', 16)->default('pending')->index();
            $table->text('decision_note')->nullable();
            $table->string('decided_by', 150)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('approved_summary')->nullable();
            $table->json('file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_report_requests');
    }
};
