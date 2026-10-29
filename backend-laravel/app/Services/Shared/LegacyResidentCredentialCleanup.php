<?php

namespace App\Services\Shared;

use Illuminate\Database\Connection;

class LegacyResidentCredentialCleanup
{
    /** Retain working password hashes; never touch staff or partner-specific roles. */
    public function run(Connection $db): array
    {
        $schema = $db->getSchemaBuilder();
        if (! $schema->hasColumn('users', 'temp_password') || ! $schema->hasTable('roles')) {
            return ['cleared' => 0, 'unresolved' => 0];
        }
        $roleIds = $db->table('roles')->where('role_key', 'household_resident')->pluck('role_id');
        $cleared = 0;
        $unresolved = 0;
        $db->table('users')->whereIn('role_id', $roleIds)->whereNotNull('temp_password')
            ->select(['user_id', 'password'])->chunkById(100, function ($users) use ($db, &$cleared, &$unresolved): void {
                foreach ($users as $user) {
                    if (! password_get_info((string) $user->password)['algo']) {
                        $unresolved++;

                        continue;
                    }
                    $cleared += $db->table('users')->where('user_id', $user->user_id)->where('password', $user->password)
                        ->whereNotNull('temp_password')->update(['temp_password' => null]);
                }
            }, 'user_id');

        return ['cleared' => $cleared, 'unresolved' => $unresolved];
    }
}
