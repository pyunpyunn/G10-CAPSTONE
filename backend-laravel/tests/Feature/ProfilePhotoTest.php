<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->string('user_id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->integer('role_id')->nullable();
                $table->string('member_id')->nullable();
                $table->boolean('is_active')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('users', 'member_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('member_id')->nullable());
        }

        if (! Schema::hasTable('household_members')) {
            Schema::create('household_members', function (Blueprint $table): void {
                $table->string('member_id')->primary();
                $table->string('household_id')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('profile_photo')->nullable();
                $table->boolean('can_report_for_household')->default(false);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('household_members', 'profile_photo')) {
            Schema::table('household_members', fn (Blueprint $table) => $table->string('profile_photo')->nullable());
        }

        if (! Schema::hasColumn('household_members', 'can_report_for_household')) {
            Schema::table('household_members', fn (Blueprint $table) => $table->boolean('can_report_for_household')->default(false));
        }
    }

    public function test_authenticated_user_can_upload_replace_remove_and_fetch_profile_photo(): void
    {
        $member = ['member_id' => 'MEM-PHOTO-1', 'household_id' => 'HH-PHOTO', 'first_name' => 'Photo', 'last_name' => 'One', 'can_report_for_household' => true];
        $otherMember = ['member_id' => 'MEM-PHOTO-2', 'household_id' => 'HH-PHOTO', 'first_name' => 'Photo', 'last_name' => 'Two', 'can_report_for_household' => true];
        \Illuminate\Support\Facades\DB::table('household_members')->insert([$member, $otherMember]);
        $user = User::forceCreate(['user_id' => 'USR-PHOTO-1', 'member_id' => $member['member_id'], 'username' => 'photo.one', 'is_active' => true]);
        $other = User::forceCreate(['user_id' => 'USR-PHOTO-2', 'member_id' => $otherMember['member_id'], 'username' => 'photo.two', 'is_active' => true]);

        $first = $this->actingAs($user)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('first.png', 40, 40),
            'user_id' => $other->user_id,
        ]);
        $first->assertOk()->assertJsonPath('data.profile_photo_url', 'http://localhost/storage/'.$first->json('data.profile_photo'));
        $firstPath = $first->json('data.profile_photo');
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNull($other->member()->first()->profile_photo);

        $this->assertSame($firstPath, $user->member()->first()->profile_photo);
        $second = $this->actingAs($user)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('second.webp', 40, 40),
        ]);
        $secondPath = $second->json('data.profile_photo');
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertSame($secondPath, $user->member()->first()->profile_photo);

        $this->actingAs($user)->deleteJson('/api/v1/profile/photo')->assertOk();
        $this->assertNull($user->member()->first()->profile_photo);
        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNull(User::findOrFail($user->user_id)->member()->first()->profile_photo);
    }

    public function test_photo_upload_requires_authentication_and_valid_raster_size(): void
    {
        $this->post('/api/v1/profile/photo', ['photo' => UploadedFile::fake()->image('photo.png')])->assertUnauthorized();

        \Illuminate\Support\Facades\DB::table('household_members')->insert(['member_id' => 'MEM-PHOTO-3', 'household_id' => 'HH-PHOTO', 'can_report_for_household' => true]);
        $user = User::forceCreate(['user_id' => 'USR-PHOTO-3', 'member_id' => 'MEM-PHOTO-3', 'username' => 'photo.three', 'is_active' => true]);
        $this->actingAs($user)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable();
        $this->actingAs($user)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ])->assertUnprocessable();
        $this->assertNull($user->member()->first()->profile_photo);
    }

    public function test_member_without_household_reporting_permission_cannot_change_photo(): void
    {
        \Illuminate\Support\Facades\DB::table('household_members')->insert(['member_id' => 'MEM-PHOTO-DENIED', 'household_id' => 'HH-PHOTO', 'can_report_for_household' => false]);
        $user = User::forceCreate(['user_id' => 'USR-PHOTO-DENIED', 'member_id' => 'MEM-PHOTO-DENIED', 'username' => 'photo.denied', 'is_active' => true]);

        $this->actingAs($user)->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('denied.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }
}
