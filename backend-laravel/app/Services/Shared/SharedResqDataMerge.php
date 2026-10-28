<?php

namespace App\Services\Shared;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Copy only records whose shared identities can be verified. Never replace shared values. */
class SharedResqDataMerge
{
    private array $report = ['inserted' => [], 'merged' => [], 'preserved' => [], 'deferred' => []];

    public function run(Connection $source, bool $write = false): array
    {
        return DB::transaction(function () use ($source, $write): array {
            foreach (['property_damage_levels' => 'damage_key', 'offline_packs' => 'version'] as $table => $key) {
                if (! $source->getSchemaBuilder()->hasTable($table)) continue;
                foreach ($source->table($table)->get() as $row) $this->insert($table, [$key => $row->$key], (array) $row, $write);
            }
            if ($source->getSchemaBuilder()->hasTable('official_advisory_notifications')) {
                foreach ($source->table('official_advisory_notifications')->get() as $row) {
                    $this->insert('notifications', ['advisory_fingerprint' => $row->fingerprint], [
                        'advisory_source' => $row->source, 'advisory_fingerprint' => $row->fingerprint,
                        'advisory_title' => $row->title, 'message' => $row->body,
                        'advisory_source_url' => $row->source_url, 'created_at' => $row->created_at,
                    ], $write);
                }
            }
            if ($source->getSchemaBuilder()->hasTable('team_bases')) {
                foreach ($source->table('team_bases')->get() as $row) {
                    $team = $source->table('rescue_teams')->where('team_id', $row->team_id)->first();
                    $shared = $team ? DB::table('rescue_teams')->where('team_code', $team->team_code)->first() : null;
                    if (! $shared) { $this->count('deferred', 'team_bases:unmatched_team'); continue; }
                    $this->fill('rescue_teams', ['team_id' => $shared->team_id], [
                        'base_name' => $row->name, 'base_latitude' => $row->lat, 'base_longitude' => $row->lng,
                    ], $write);
                }
            }
            if ($source->getSchemaBuilder()->hasTable('household_member_id_mappings')) {
                foreach ($source->table('household_member_id_mappings')->get() as $row) {
                    // Existing shared IDs remain authoritative. Do not renumber other systems' members.
                    if (DB::table('household_members')->where('member_id', $row->old_member_id)->exists()) {
                        $this->count('preserved', 'household_members:original_id');
                    } elseif (DB::table('household_members')->where('member_id', $row->member_id)->exists()) {
                        $this->fill('household_members', ['member_id' => $row->member_id], ['legacy_member_id' => $row->old_member_id], $write);
                    } else $this->count('deferred', 'member_mapping:unmatched_member');
                }
            }
            if ($source->getSchemaBuilder()->hasTable('household_login_accounts')) {
                foreach ($source->table('household_login_accounts')->get() as $row) {
                    $user = $this->user($source, $row->user_id);
                    if (! $user || (string) $user->household_id !== (string) $row->household_id) {
                        $this->count('deferred', 'household_login:unmatched_account'); continue;
                    }
                    $this->fill('households', ['household_id' => $row->household_id], ['login_user_id' => $user->user_id], $write);
                }
            }
            foreach ($source->table('device_tokens')->get() as $row) {
                if (! $row->device_uuid || ! ($user = $this->user($source, $row->user_id ?? null))) {
                    $this->count('deferred', 'device_tokens:unmatched_account'); continue;
                }
                $values = (array) $row;
                unset($values['id']);
                $values['user_id'] = $user->user_id;
                $values['household_id'] = $user->household_id;
                $values['member_id'] = $user->member_id ?? null;
                $values['selection_token_id'] = null;
                $values['owner_selected_at'] = null;
                $this->device($values, $write);
            }
            if ($source->getSchemaBuilder()->hasTable('devices')) {
                foreach ($source->table('devices')->get() as $row) {
                    $user = $this->user($source, $row->user_id);
                    $team = $source->table('rescue_teams')->where('team_id', $row->team_id)->first();
                    $sharedTeam = $team ? DB::table('rescue_teams')->where('team_code', $team->team_code)->first() : null;
                    if (! $user || ! $sharedTeam) { $this->count('deferred', 'navigation_device:unmatched_account_or_team'); continue; }
                    $this->device(['user_id' => $user->user_id, 'team_id' => $sharedTeam->team_id,
                        'device_uuid' => $row->device_uuid, 'app_role' => 'rescuer', 'last_sync_at' => $row->last_sync_at,
                        'created_at' => $row->created_at, 'updated_at' => $row->updated_at], $write);
                }
            }
            if ($source->getSchemaBuilder()->hasTable('household_device_owners')) {
                foreach ($source->table('household_device_owners')->get() as $row) {
                    $member = $this->member($source, $row->member_id, $row->household_id);
                    $users = $member ? DB::table('users')->where('household_id', $row->household_id)->where('member_id', $member->member_id)->get() : collect();
                    if ($users->count() !== 1) { $this->count('deferred', 'device_owner:unmatched_account'); continue; }
                    $user = $users->first();
                    $device = DB::table('device_tokens')->where('device_uuid', $row->device_uuid)->where('household_id', $row->household_id)->first();
                    if (! $device) { $this->count('deferred', 'device_owner:device_not_imported'); continue; }
                    if ((string) $device->member_id !== (string) $member->member_id) {
                        $this->count('deferred', 'device_owner:membership_conflict'); continue;
                    }
                    $this->fill('device_tokens', ['id' => $device->id], ['owner_selected_at' => $row->created_at ?? now()], $write);
                }
            }
            foreach ($source->table('household_statuses')->get() as $row) {
                if (DB::table('household_statuses')->where('status_key', $row->status_key)->exists()) continue;
                $values = (array) $row;
                $values['status_id'] = ((int) DB::table('household_statuses')->max('status_id')) + 1;
                $this->insert('household_statuses', ['status_key' => $row->status_key], $values, $write);
            }
            foreach (['member_statuses' => ['counts_as_death','counts_as_injury'], 'household_statuses' => ['safety_basis']] as $table => $columns) {
                foreach ($source->table($table)->get() as $row) {
                    $values = array_intersect_key((array) $row, array_flip($columns));
                    $this->fill($table, ['status_key' => $row->status_key], $values, $write);
                }
            }
            // Environment-bound sessions/OTP codes and local operational history are not shared identities.
            foreach (['household_password_recovery_otps','web_push_subscriptions','web_push_deliveries',
                'dispatch_trips','trip_points','sync_events','responder_field_report_members','cached_routes','mapping_hazard_layers'] as $table) {
                if ($source->getSchemaBuilder()->hasTable($table)) {
                    $count = $source->table($table)->count();
                    if ($count) $this->report['deferred'][$table.':environment_bound_or_operational_history'] = $count;
                }
            }
            return $this->report;
        }, 3);
    }

    private function user(Connection $source, ?string $id): ?object
    {
        if (! $id) return null;
        $local = $source->table('users')->where('user_id', $id)->first();
        if (! $local) return null;
        $shared = DB::table('users')->where('user_id', $id)->first();
        if ($shared && (string) $shared->household_id === (string) $local->household_id && (int) $shared->role_id === (int) $local->role_id) return $shared;
        if (! empty($local->member_id) && ! empty($local->household_id)) {
            $member = $this->member($source, $local->member_id, $local->household_id);
            $users = $member ? DB::table('users')->where('member_id', $member->member_id)->where('household_id', $local->household_id)->where('role_id', $local->role_id)->get() : collect();
            return $users->count() === 1 ? $users->first() : null;
        }
        return null;
    }

    private function member(Connection $source, string $id, string $householdId): ?object
    {
        $old = $source->getSchemaBuilder()->hasTable('household_member_id_mappings')
            ? $source->table('household_member_id_mappings')->where('member_id', $id)->value('old_member_id') : null;
        return DB::table('household_members')->where('household_id', $householdId)->where('member_id', $old ?? $id)->first();
    }

    private function device(array $values, bool $write): void
    {
        $existing = DB::table('device_tokens')->where('device_uuid', $values['device_uuid'])->get();
        if ($existing->isNotEmpty()) {
            if ($existing->count() !== 1 || (string) $existing->first()->user_id !== (string) $values['user_id']) {
                $this->count('deferred', 'device_tokens:existing_device_conflict'); return;
            }
            $this->fill('device_tokens', ['id' => $existing->first()->id], array_intersect_key($values, array_flip(['team_id','last_sync_at'])), $write);
            return;
        }
        if ($write) $values['id'] = app(OperationalSequence::class)->nextNumericId('device_tokens', 'id');
        $this->insert('device_tokens', ['device_uuid' => $values['device_uuid']], $values, $write);
    }

    private function insert(string $table, array $key, array $values, bool $write): void
    {
        if (DB::table($table)->where($key)->exists()) { $this->count('preserved', $table); return; }
        if ($write) DB::table($table)->insert(array_intersect_key($values, array_flip(Schema::getColumnListing($table))));
        $this->count('inserted', $table);
    }

    private function fill(string $table, array $key, array $values, bool $write): void
    {
        $row = DB::table($table)->where($key)->first();
        if (! $row) { $this->count('deferred', $table.':missing_target'); return; }
        $changes = [];
        foreach ($values as $column => $value) {
            if ($value === null) continue;
            if ($row->$column === null) $changes[$column] = $value;
            elseif ((string) $row->$column !== (string) $value) $this->count('preserved', $table.':'.$column.':existing_value');
        }
        if ($changes) {
            // MySQL ON UPDATE timestamps must not rewrite the shared record's original timestamp.
            if (property_exists($row, 'updated_at')) $changes['updated_at'] = $row->updated_at;
            if ($write) DB::table($table)->where($key)->update($changes);
            $this->count('merged', $table);
        }
    }

    private function count(string $kind, string $table): void
    {
        $this->report[$kind][$table] = ($this->report[$kind][$table] ?? 0) + 1;
    }
}
