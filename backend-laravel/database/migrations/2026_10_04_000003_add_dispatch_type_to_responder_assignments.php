<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('responder_assignments') && ! Schema::hasColumn('responder_assignments', 'dispatch_type')) {
            Schema::table('responder_assignments', function (Blueprint $table): void {
                $table->string('dispatch_type', 30)->default('rescue')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('responder_assignments') && Schema::hasColumn('responder_assignments', 'dispatch_type')) {
            Schema::table('responder_assignments', function (Blueprint $table): void {
                $table->dropIndex(['dispatch_type']);
                $table->dropColumn('dispatch_type');
            });
        }
    }
};
