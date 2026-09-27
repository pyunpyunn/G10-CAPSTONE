<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disaster_broadcasts') && ! Schema::hasColumn('disaster_broadcasts', 'sms_status')) {
            Schema::table('disaster_broadcasts', function (Blueprint $table): void {
                $table->string('sms_status', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('disaster_broadcasts') && Schema::hasColumn('disaster_broadcasts', 'sms_status')) {
            Schema::table('disaster_broadcasts', function (Blueprint $table): void {
                $table->dropColumn('sms_status');
            });
        }
    }
};
