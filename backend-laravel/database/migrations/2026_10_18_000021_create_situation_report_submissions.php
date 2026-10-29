<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('situation_report_submissions')) {
            Schema::create('situation_report_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sit_rep_id')->index();
                $table->timestamp('submitted_at');
                $table->string('delivered_by',150);
                $table->string('received_by',150);
                $table->string('recorded_by');
                $table->timestamps();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('situation_report_submissions'); }
};
