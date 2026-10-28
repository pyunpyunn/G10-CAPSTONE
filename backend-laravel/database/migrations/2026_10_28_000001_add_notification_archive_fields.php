<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && ! Schema::hasColumn('notifications', 'source_key')) {
            Schema::table('notifications', function (Blueprint $table): void {
                // Nullable for compatibility with other applications using this shared table.
                $table->string('source_key', 120)->nullable()->unique();
                $table->string('onesignal_id', 36)->nullable();
                $table->json('onesignal_ids_json')->nullable();
                $table->json('feed_payload_json')->nullable();
            });
        }
        if (Schema::hasTable('disaster_broadcasts')) {
            foreach (['push_provider_ids_json', 'push_errors_json'] as $column) {
                if (! Schema::hasColumn('disaster_broadcasts', $column)) {
                    Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->json($column)->nullable());
                }
            }
            if (! Schema::hasColumn('disaster_broadcasts', 'push_accepted_count')) {
                Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->unsignedInteger('push_accepted_count')->nullable());
            }
        }
    }

    public function down(): void
    {
        // Preserve imported provider IDs and shared notification records on rollback.
    }
};
