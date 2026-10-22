<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'profile_photo')) {
            return;
        }

        if (! Schema::hasTable('household_members') || ! Schema::hasColumn('household_members', 'profile_photo')) {
            throw new RuntimeException('Cannot move profile photos because household_members.profile_photo is unavailable.');
        }

        $photos = DB::table('users')
            ->whereNotNull('profile_photo')
            ->where('profile_photo', '<>', '')
            ->get(['user_id', 'member_id', 'profile_photo']);

        foreach ($photos->groupBy('member_id') as $memberPhotos) {
            if ($memberPhotos->pluck('profile_photo')->unique()->count() > 1) {
                throw new RuntimeException('Cannot move profile photos because multiple users linked to one household member have different photo paths.');
            }
        }

        foreach ($photos as $photo) {
            if (! $photo->member_id || ! DB::table('household_members')->where('member_id', $photo->member_id)->exists()) {
                throw new RuntimeException("Cannot move profile photo for user {$photo->user_id}: linked household member was not found.");
            }

            $memberPhoto = DB::table('household_members')
                ->where('member_id', $photo->member_id)
                ->value('profile_photo');

            if ($memberPhoto && $memberPhoto !== $photo->profile_photo) {
                throw new RuntimeException("Cannot move profile photo for user {$photo->user_id}: the household member already has a different photo path.");
            }
        }

        foreach ($photos as $photo) {
            DB::table('household_members')
                ->where('member_id', $photo->member_id)
                ->where(function ($query) use ($photo): void {
                    $query->whereNull('profile_photo')->orWhere('profile_photo', '');
                })
                ->update(['profile_photo' => $photo->profile_photo]);
        }

        Schema::table('users', function ($table): void {
            $table->dropColumn('profile_photo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'profile_photo')) {
            Schema::table('users', function ($table): void {
                $table->string('profile_photo')->nullable();
            });
        }

        if (Schema::hasTable('household_members') && Schema::hasColumn('household_members', 'profile_photo')) {
            DB::table('household_members')
                ->whereNotNull('profile_photo')
                ->where('profile_photo', '<>', '')
                ->orderBy('member_id')
                ->each(function ($member): void {
                    DB::table('users')
                        ->where('member_id', $member->member_id)
                        ->where(function ($query): void {
                            $query->whereNull('profile_photo')->orWhere('profile_photo', '');
                        })
                        ->update(['profile_photo' => $member->profile_photo]);
                });
        }
    }
};
