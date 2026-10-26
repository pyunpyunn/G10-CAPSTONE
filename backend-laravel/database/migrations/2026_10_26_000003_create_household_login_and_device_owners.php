<?php

use App\Queries\AuthAccountQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('household_login_accounts')) {
            Schema::create('household_login_accounts', function (Blueprint $t): void {
                $t->string('household_id')->primary();
                $t->string('user_id')->unique();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('household_device_owners')) {
            Schema::create('household_device_owners', function (Blueprint $t): void {
                $t->id();
                $t->string('household_id');
                $t->string('device_uuid', 150);
                $t->string('member_id');
                $t->unsignedBigInteger('selection_token_id')->nullable();
                $t->timestamps();
                $t->unique(['household_id', 'device_uuid'], 'household_phone_owner_unique');
                $t->index('member_id');
            });
        }
        if (! Schema::hasTable('households')) return;
        foreach (DB::table('households')->pluck('household_id') as $id) {
            $user = app(AuthAccountQuery::class)->householdAccount((string) $id);
            if ($user) DB::table('household_login_accounts')->insertOrIgnore([
                'household_id' => $id, 'user_id' => $user->user_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve household credential links and saved phone owners.
    }
};
