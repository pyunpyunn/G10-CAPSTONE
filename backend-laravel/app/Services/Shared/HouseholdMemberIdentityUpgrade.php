<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class HouseholdMemberIdentityUpgrade
{
    public function run(): void
    {
        if (! Schema::hasTable('household_members')) return;
        $references = [];
        $jsonColumns = [];
        foreach (Schema::getTables(DB::getDriverName() === 'mysql' ? DB::getDatabaseName() : null) as $table) {
            $name = $table['name'];
            if ($name === 'household_member_id_mappings') continue;
            foreach (Schema::getColumns($name) as $column) {
                $key = $column['name'];
                if (in_array($key, ['member_id', 'reported_by_member_id', 'household_member_id'], true)) {
                    if (! in_array($column['type_name'], ['varchar', 'char', 'text'], true)) {
                        throw new RuntimeException("{$name}.{$key} must support string member IDs before this upgrade.");
                    }
                    $references[] = [$name, $key];
                } elseif (str_contains($column['type_name'], 'json') || str_ends_with($key, '_json') || str_ends_with($key, '_notes') || in_array($key, ['notes', 'old_values', 'new_values', 'payload'], true)) {
                    $jsonColumns[] = [$name, $key];
                }
            }
        }

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($references, $jsonColumns): void {
                $members = DB::table('household_members')->orderBy('household_id')->orderBy('member_id')->lockForUpdate()->get();
                $mapping = [];
                $prefixes = [];
                foreach ($members->groupBy('household_id') as $householdId => $family) {
                    $prefix = HouseholdMemberIdentity::prefix((string) $householdId);
                    if (isset($prefixes[$prefix])) throw new RuntimeException('Household number collision: '.$householdId);
                    $prefixes[$prefix] = true;
                    $used = [];
                    foreach ($family as $member) {
                        if (preg_match('/^'.preg_quote($prefix, '/').'([1-9]\d*)$/', $member->member_id, $m)) $used[(int) $m[1]] = true;
                    }
                    $next = $used ? max(array_keys($used)) + 1 : 1;
                    foreach ($family->sort(fn ($a, $b) => strnatcmp($a->member_id, $b->member_id)) as $member) {
                        if (preg_match('/^'.preg_quote($prefix, '/').'[1-9]\d*$/', $member->member_id)) continue;
                        $mapping[$member->member_id] = $prefix.$next++;
                    }
                }

                // Temporary IDs prevent collisions while all parent and child keys change together.
                $temporary = [];
                foreach ($mapping as $old => $new) {
                    $temporary[$old] = 'HM-MIGRATION-'.Str::uuid();
                    DB::table('household_members')->where('member_id', $old)->whereNull('legacy_member_id')->update(['legacy_member_id' => $old]);
                }
                $this->replaceIds($temporary, $references, $jsonColumns);
                $this->replaceIds(array_combine(array_values($temporary), array_values($mapping)) ?: [], $references, $jsonColumns);
                $this->linkAccounts();
            }, 3);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function replaceIds(array $mapping, array $references, array $jsonColumns): void
    {
        $grammar = DB::connection()->getQueryGrammar();
        foreach ($mapping as $old => $new) {
            foreach ($references as [$table, $column]) {
                DB::table($table)->where($column, $old)->update([$column => $new]);
            }
            // Stored report notes and audit JSON also contain member IDs.
            foreach ($jsonColumns as [$table, $column]) {
                $t = $grammar->wrapTable($table);
                $c = $grammar->wrap($column);
                DB::update("UPDATE {$t} SET {$c} = REPLACE({$c}, ?, ?) WHERE {$c} LIKE ?", ['"'.$old.'"', '"'.$new.'"', '%"'.$old.'"%']);
            }
            foreach (['username', 'login_id'] as $column) {
                if (Schema::hasColumn('users', $column)) DB::table('users')->where($column, $old)->update([$column => $new]);
            }
        }
    }

    private function linkAccounts(): void
    {
        $roleId = DB::table('roles')->where('role_key', 'household_resident')->value('role_id');
        if (! $roleId) throw new RuntimeException('The household resident role is required.');
        foreach (DB::table('household_members')->get()->groupBy('household_id') as $householdId => $members) {
            $accounts = DB::table('users')->where('household_id', $householdId)->where('role_id', $roleId)->whereNull('deleted_at')->lockForUpdate()->get();
            $unlinked = $accounts->filter(fn ($user) => empty($user->member_id));
            $head = $members->first(fn ($member) => ! empty($member->is_household_head) && empty($member->deleted_at));
            if ($head && ! $accounts->contains('member_id', $head->member_id) && $unlinked->count() === 1) {
                DB::table('users')->where('user_id', $unlinked->first()->user_id)->update(['member_id' => $head->member_id]);
            }
            $source = $accounts->first(fn ($account) => ! empty($account->password) && ($account->is_active === null || $account->is_active));
            foreach ($members as $member) {
                if (! empty($member->deleted_at)) continue;
                $linked = DB::table('users')->where('member_id', $member->member_id)->whereNull('deleted_at')->get();
                if ($linked->count() > 1) throw new RuntimeException('Multiple accounts are linked to '.$member->member_id);
                if ($linked->contains(fn ($account) => (string) $account->household_id !== (string) $householdId || (int) $account->role_id !== (int) $roleId)) {
                    throw new RuntimeException('The account for '.$member->member_id.' has an incorrect household or role.');
                }
                if ($linked->isNotEmpty()) continue;
                if (! $source) throw new RuntimeException('No existing password source for household '.$householdId);
                $values = [
                    'user_id' => (string) Str::uuid(), 'member_id' => $member->member_id,
                    'household_id' => $householdId, 'role_id' => $roleId,
                    'username' => $member->member_id, 'first_name' => $member->first_name ?? null,
                    'last_name' => $member->last_name ?? null,
                    'name' => trim(($member->first_name ?? '').' '.($member->middle_name ?? '').' '.($member->last_name ?? '')),
                    'contact_number' => $member->contact_number ?? null,
                    // Retain the existing household-issued password process; never reset existing hashes.
                    'password' => $source->password, 'temp_password' => null,
                    'must_change_password' => $source->must_change_password ?? true,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ];
                DB::table('users')->insert(array_intersect_key($values, array_flip(Schema::getColumnListing('users'))));
            }
        }
    }
}
