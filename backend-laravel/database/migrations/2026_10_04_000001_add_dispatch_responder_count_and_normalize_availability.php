<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('responder_assignments') && ! Schema::hasColumn('responder_assignments', 'responder_count')) {
            Schema::table('responder_assignments', function (Blueprint $table): void {
                $table->unsignedSmallInteger('responder_count')->default(1);
            });
        }

        foreach (['responders', 'rescue_teams'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'duty_status')) {
                // New responders remain off duty until explicitly activated.
                if ($table === 'rescue_teams') {
                    Schema::table($table, function (Blueprint $blueprint): void {
                        $blueprint->string('duty_status', 30)->default('available')->change();
                    });
                }
                DB::table($table)->whereIn('duty_status', ['standby', 'stand-by'])
                    ->update(['duty_status' => 'available']);
            }
        }
    }

    public function down(): void
    {
        // Existing available rows cannot be distinguished from converted legacy rows.
        if (Schema::hasTable('responder_assignments') && Schema::hasColumn('responder_assignments', 'responder_count')) {
            Schema::table('responder_assignments', function (Blueprint $table): void {
                $table->dropColumn('responder_count');
            });
        }
    }
};
