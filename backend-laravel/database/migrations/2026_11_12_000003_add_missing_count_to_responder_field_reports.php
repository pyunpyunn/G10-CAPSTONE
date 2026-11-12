<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('responder_field_reports') && ! Schema::hasColumn('responder_field_reports', 'missing_count')) {
            Schema::table('responder_field_reports', function (Blueprint $table): void {
                $table->unsignedInteger('missing_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('responder_field_reports', function (Blueprint $table): void {
            $table->dropColumn('missing_count');
        });
    }
};
