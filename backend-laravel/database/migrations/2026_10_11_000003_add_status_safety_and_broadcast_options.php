<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('household_statuses', 'safety_basis')) {
            Schema::table('household_statuses', fn (Blueprint $table) => $table->string('safety_basis', 6)->nullable());
            foreach (DB::table('household_statuses')->get(['status_id', 'status_key']) as $status) {
                DB::table('household_statuses')->where('status_id', $status->status_id)
                    ->update(['safety_basis' => \App\Support\StatusSafety::basis($status->status_key)]);
            }
        }
        if (! Schema::hasColumn('disaster_broadcasts', 'status_options_json')) {
            Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->text('status_options_json')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('household_statuses', fn (Blueprint $table) => $table->dropColumn('safety_basis'));
        Schema::table('disaster_broadcasts', fn (Blueprint $table) => $table->dropColumn('status_options_json'));
    }
};
