<?php

use App\Services\Shared\OperationalSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('households') || ! Schema::hasTable('device_tokens')) {
            throw new RuntimeException('Households and device_tokens are required before consolidating household access.');
        }
        if (! Schema::hasColumn('households', 'login_user_id')) {
            Schema::table('households', fn (Blueprint $t) => $t->string('login_user_id', 255)->nullable()->unique());
        }
        if (! Schema::hasColumn('device_tokens', 'selection_token_id')) {
            Schema::table('device_tokens', fn (Blueprint $t) => $t->unsignedBigInteger('selection_token_id')->nullable());
        }
        if (! Schema::hasColumn('device_tokens', 'owner_selected_at')) {
            Schema::table('device_tokens', fn (Blueprint $t) => $t->timestamp('owner_selected_at')->nullable());
        }
        $hasDeviceLookup = collect(Schema::getIndexes('device_tokens'))->contains(fn (array $index): bool => array_slice($index['columns'], 0, 2) === ['household_id', 'device_uuid']);
        if (! $hasDeviceLookup) {
            Schema::table('device_tokens', fn (Blueprint $t) => $t->index(['household_id', 'device_uuid'], 'household_device_lookup'));
        }
        DB::transaction(function (): void {
            if (Schema::hasTable('household_login_accounts')) {
                foreach (DB::table('household_login_accounts')->get() as $link) {
                    $household = DB::table('households')->where('household_id', $link->household_id)->lockForUpdate()->first();
                    if (! $household || ($household->login_user_id && $household->login_user_id !== $link->user_id)) {
                        throw new RuntimeException('Household login conflict; original tables were retained.');
                    }
                    DB::table('households')->where('household_id', $link->household_id)->update(['login_user_id' => $link->user_id]);
                }
            }
            if (Schema::hasTable('household_device_owners')) {
                foreach (DB::table('household_device_owners')->get() as $owner) {
                    $devices = DB::table('device_tokens')->where('household_id', $owner->household_id)->where('device_uuid', $owner->device_uuid)->lockForUpdate()->get();
                    if ($devices->count() > 1) throw new RuntimeException('Duplicate household devices require review; original tables were retained.');
                    $user = DB::table('users')->where('household_id', $owner->household_id)->where('member_id', $owner->member_id)->first();
                    if (! $user) throw new RuntimeException('Device owner account is missing; original tables were retained.');
                    $values = ['user_id' => $user->user_id, 'member_id' => $owner->member_id, 'selection_token_id' => $owner->selection_token_id,
                        'owner_selected_at' => $owner->created_at ?? now(), 'updated_at' => $owner->updated_at ?? now()];
                    if ($device = $devices->first()) {
                        if ($device->owner_selected_at && ($device->member_id !== $owner->member_id || $device->selection_token_id != $owner->selection_token_id)) {
                            throw new RuntimeException('Device ownership conflict; original tables were retained.');
                        }
                        DB::table('device_tokens')->where('id', $device->id)->update($values);
                    } else {
                        $values += ['household_id' => $owner->household_id, 'device_uuid' => $owner->device_uuid,
                            'user_id' => $user->user_id, 'device_name' => 'Household mobile', 'platform' => 'mobile',
                            'app_role' => 'household', 'push_provider' => 'onesignal', 'player_id' => 'unavailable:'.$owner->device_uuid,
                            'battery_level' => 0, 'signal_strength' => 0, 'is_active' => true, 'logged_at' => now(), 'created_at' => $owner->created_at ?? now()];
                        $values = array_intersect_key($values, array_flip(Schema::getColumnListing('device_tokens')));
                        $values['id'] = app(OperationalSequence::class)->nextNumericId('device_tokens', 'id');
                        DB::table('device_tokens')->insert($values);
                    }
                }
            }
            foreach (DB::table('households')->whereNull('login_user_id')->pluck('household_id') as $id) {
                $user = app(\App\Queries\AuthAccountQuery::class)->householdAccount((string) $id);
                if ($user) DB::table('households')->where('household_id', $id)->update(['login_user_id' => $user->user_id]);
            }
        });
        // Preserve legacy tables for other applications; ResQperation uses the consolidated fields.
    }

    public function down(): void
    {
        throw new RuntimeException('Household access consolidation requires an explicit data-preserving restore.');
    }
};
