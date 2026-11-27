<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_request_status')) {
            return;
        }

        DB::transaction(function (): void {
            if (! DB::table('resource_request_status')->where('status_key', 'returned')->exists()) {
                $maxId = (int) DB::table('resource_request_status')->max('status_id');
                DB::table('resource_request_status')->insert([
                    'status_id' => max(6, $maxId + 1),
                    'status_key' => 'returned',
                    'status_label' => 'Returned',
                ]);
            }

            if (! DB::table('resource_request_status')->where('status_key', 'cancelled')->exists()) {
                $maxId = (int) DB::table('resource_request_status')->max('status_id');
                DB::table('resource_request_status')->insert([
                    'status_id' => max(7, $maxId + 1),
                    'status_key' => 'cancelled',
                    'status_label' => 'Cancelled',
                ]);
            }

            if (Schema::hasTable('resource_requests')) {
                $returnedId = DB::table('resource_request_status')->where('status_key', 'returned')->value('status_id');
                if ($returnedId) {
                    DB::table('resource_requests')
                        ->where('validation_status', 'returned')
                        ->update(['status_id' => $returnedId]);
                }

                $cancelledId = DB::table('resource_request_status')->where('status_key', 'cancelled')->value('status_id');
                if ($cancelledId) {
                    DB::table('resource_requests')
                        ->where('validation_status', 'cancelled')
                        ->update(['status_id' => $cancelledId]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('resource_request_status')) {
            return;
        }

        DB::transaction(function (): void {
            $rejectedId = DB::table('resource_request_status')->where('status_key', 'rejected')->value('status_id');

            if ($rejectedId && Schema::hasTable('resource_requests')) {
                $statusIds = DB::table('resource_request_status')
                    ->whereIn('status_key', ['returned', 'cancelled'])
                    ->pluck('status_id')
                    ->all();

                if (! empty($statusIds)) {
                    DB::table('resource_requests')
                        ->whereIn('status_id', $statusIds)
                        ->update(['status_id' => $rejectedId]);
                }
            }

            DB::table('resource_request_status')
                ->whereIn('status_key', ['returned', 'cancelled'])
                ->delete();
        });
    }
};
