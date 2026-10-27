<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('dispatch_statuses')->where('status_key', 'en_route')->update(['dashboard_group' => 'en-route']);
        DB::table('dispatch_statuses')->where('status_key', 'completed')->update(['dashboard_group' => 'completed']);
    }

    public function down(): void
    {
        DB::table('dispatch_statuses')->where('status_key', 'en_route')->update(['dashboard_group' => 'dispatched']);
        DB::table('dispatch_statuses')->where('status_key', 'completed')->update(['dashboard_group' => 'other']);
    }
};
