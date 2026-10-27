<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('dispatch_statuses', 'status_code')) {
            Schema::table('dispatch_statuses', function (Blueprint $table): void {
                $table->string('status_code', 20)->nullable()->unique();
            });
        }
        $used = DB::table('dispatch_statuses')->whereNotNull('status_code')->pluck('status_code')->all();
        $number = 1;
        foreach (DB::table('dispatch_statuses')->whereNull('status_code')->orderBy('sort_order')->orderBy('status_key')->get() as $status) {
            do { $code = sprintf('DSP-%03d', $number++); } while (in_array($code, $used, true));
            DB::table('dispatch_statuses')->where('status_key', $status->status_key)->update(['status_code' => $code]);
            $used[] = $code;
        }
        Schema::table('dispatch_statuses', fn (Blueprint $table) => $table->string('status_code', 20)->nullable(false)->change());
    }

    public function down(): void { /* Preserve stable status identifiers. */ }
};
