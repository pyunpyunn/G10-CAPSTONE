<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_requests')) {
            throw new RuntimeException('Install the existing password_reset_requests table before adding SMS recovery.');
        }
        foreach (['otp_subject_key' => fn ($t) => $t->string('otp_subject_key', 64)->nullable()->unique(),
            'household_id' => fn ($t) => $t->string('household_id')->nullable(),
            'code_hash' => fn ($t) => $t->string('code_hash')->nullable(),
            'sent_at' => fn ($t) => $t->timestamp('sent_at')->nullable()] as $column => $add) {
            if (! Schema::hasColumn('password_reset_requests', $column)) Schema::table('password_reset_requests', $add);
        }
        if (DB::getDriverName() === 'mysql') {
            $column = collect(Schema::getColumns('password_reset_requests'))->firstWhere('name', 'verification_method');
            if (str_starts_with($column['type'], 'enum(') && ! str_contains($column['type'], "'sms_otp'")) {
                $type = substr($column['type'], 0, -1).",'sms_otp')";
                DB::statement("ALTER TABLE `password_reset_requests` MODIFY COLUMN `verification_method` {$type} NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // Preserve shared password recovery records.
    }
};
