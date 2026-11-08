<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('severity_levels', 'severity_desc')) {
            Schema::table('severity_levels', function (Blueprint $table): void {
                $table->string('severity_desc', 255)->nullable();
            });
        }

        DB::table('severity_levels')
            ->whereNull('severity_desc')
            ->orderBy('severity_id')
            ->get(['severity_id', 'severity_label'])
            ->each(function (object $severity): void {
                if (! preg_match('/^(.+?)\s[-\x{2013}\x{2014}]\s(.+)$/u', $severity->severity_label, $matches)) {
                    return;
                }

                DB::table('severity_levels')
                    ->where('severity_id', $severity->severity_id)
                    ->update([
                        'severity_label' => $matches[1],
                        'severity_desc' => $matches[2],
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('severity_levels', 'severity_desc')) {
            return;
        }

        DB::table('severity_levels')
            ->whereNotNull('severity_desc')
            ->orderBy('severity_id')
            ->get(['severity_id', 'severity_label', 'severity_desc'])
            ->each(function (object $severity): void {
                DB::table('severity_levels')
                    ->where('severity_id', $severity->severity_id)
                    ->update([
                        'severity_label' => $severity->severity_label.' - '.$severity->severity_desc,
                    ]);
            });

        Schema::table('severity_levels', function (Blueprint $table): void {
            $table->dropColumn('severity_desc');
        });
    }
};
