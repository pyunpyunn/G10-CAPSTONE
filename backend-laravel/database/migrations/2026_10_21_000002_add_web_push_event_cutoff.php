<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('web_push_subscriptions', function (Blueprint $table) {
            $table->timestamp('push_after')->nullable();
        });
        // Existing subscriptions start fresh after the repair; old inbox records remain readable.
        DB::table('web_push_subscriptions')->update(['push_after' => now()]);
    }
    public function down(): void
    {
        Schema::table('web_push_subscriptions', fn (Blueprint $table) => $table->dropColumn('push_after'));
    }
};
