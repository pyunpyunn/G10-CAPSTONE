<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['responders', 'rescue_teams', 'roles', 'users'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Required responder login table is missing: '.$table);
            }
        }

        foreach (['user_id', 'username', 'password', 'role_id'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                throw new RuntimeException('Required users column is missing: '.$column);
            }
        }

        $roleIds = DB::table('roles')->whereIn('role_key', ['tl', 'tm'])->pluck('role_id', 'role_key');
        if (! $roleIds->has('tl') || ! $roleIds->has('tm')) {
            throw new RuntimeException('Team leader and team member roles are required before restoring responder accounts.');
        }

        $responders = DB::table('responders as r')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user_id')
            ->whereNull('r.deleted_at')
            ->where('r.is_validated', 1)
            ->whereNull('u.user_id')
            ->select('r.*')
            ->orderBy('r.responder_id')
            ->get();

        $seenIds = [];
        $seenUsernames = [];
        $rows = [];
        $userColumns = array_flip(Schema::getColumnListing('users'));

        foreach ($responders as $responder) {
            $userId = trim((string) $responder->user_id);
            $username = trim((string) $responder->username);
            $passwordHash = (string) $responder->password_hash;
            $algorithm = password_get_info($passwordHash)['algoName'] ?? 'unknown';

            if ($userId === '' || $username === '' || $algorithm === 'unknown') {
                throw new RuntimeException('A validated responder is missing a usable login identity or password hash.');
            }
            if (isset($seenIds[$userId]) || isset($seenUsernames[strtolower($username)])) {
                throw new RuntimeException('Duplicate responder login identities require manual review.');
            }
            if (DB::table('users')->whereRaw('LOWER(username) = ?', [strtolower($username)])->exists()) {
                throw new RuntimeException('A responder username is already assigned to another user account.');
            }

            $seenIds[$userId] = true;
            $seenUsernames[strtolower($username)] = true;

            $leader = DB::table('rescue_teams')
                ->where('team_id', $responder->team_id)
                ->where('leader_responder_id', $responder->responder_id)
                ->exists();
            $name = trim((string) $responder->full_name);
            $parts = preg_split('/\s+/', $name, 2, PREG_SPLIT_NO_EMPTY) ?: [];
            $now = $responder->updated_at ?? $responder->created_at ?? now();
            $values = [
                'user_id' => $userId,
                'first_name' => $parts[0] ?? $name,
                'last_name' => $parts[1] ?? '',
                'name' => $name,
                'username' => $username,
                'password' => $passwordHash,
                'role_id' => $roleIds->get($leader ? 'tl' : 'tm'),
                'contact_number' => $responder->contact_number,
                'is_active' => true,
                'must_change_password' => false,
                'created_at' => $responder->created_at ?? $now,
                'updated_at' => $now,
            ];
            $rows[] = array_intersect_key($values, $userColumns);
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                DB::table('users')->insert($row);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restored responder login accounts must be preserved; use an explicit reviewed recovery.');
    }
};
