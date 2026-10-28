<?php

use App\Services\Shared\HouseholdMemberIdentityUpgrade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('household_members', 'legacy_member_id')) {
            Schema::table('household_members', fn (Blueprint $table) => $table->string('legacy_member_id')->nullable()->unique());
        }
        app(HouseholdMemberIdentityUpgrade::class)->run();
        $hasUniqueMember = collect(Schema::getIndexes('users'))->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['member_id']
        );
        if (! $hasUniqueMember) Schema::table('users', fn (Blueprint $table) => $table->unique('member_id', 'users_member_identity_unique'));
    }

    public function down(): void
    {
        throw new RuntimeException('Member identity upgrades require an explicit data restore using household_members.legacy_member_id to preserve member accounts and reports.');
    }
};
