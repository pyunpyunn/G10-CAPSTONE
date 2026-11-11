<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // These account identities were explicitly confirmed by the system owner.
            foreach ([
                ['USR-CHAIR-0001', 'BDRRM-HQCC-11000', 'chair'],
                ['USR-OPS-0001', 'BDRRM-HQCC-11001', 'operations_admin'],
            ] as [$userId, $accountId, $roleKey]) {
                $user = DB::table('users')->where('user_id', $userId)->lockForUpdate()->first();
                if (! $user) continue;
                $profile = DB::table('responders')->where('user_id', $userId)->lockForUpdate()->first();
                if ($user->username !== $accountId || ! $profile || $profile->username !== $accountId) {
                    throw new RuntimeException("Account identity mismatch for {$userId}; no roles were changed.");
                }
                $role = DB::table('roles')->where('role_key', $roleKey)->first();
                if (! $role) throw new RuntimeException("Required role {$roleKey} is missing.");
                DB::table('users')->where('user_id', $userId)->update(['role_id' => $role->role_id, 'updated_at' => now()]);
                DB::table('responders')->where('responder_id', $profile->responder_id)
                    ->update(['title' => $role->role_name, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Do not restore the incorrect privileges.
    }
};
