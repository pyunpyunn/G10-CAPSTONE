<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('role_id');
            $table->string('role_key');
            $table->string('role_name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('role')->nullable();
            $table->integer('role_id')->nullable();
            $table->string('profile_photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'role_id' => 1,
            'role_key' => 'household_resident',
            'role_name' => 'Household Resident',
        ]);

        $this->createUser('USER-ONE');
        Sanctum::actingAs(User::query()->findOrFail('USER-ONE'));
    }

    public function test_uploaded_photo_is_persisted_and_available_after_authenticating_again(): void
    {
        Storage::fake('public');

        $this->uploadPhoto()->assertOk();

        $path = DB::table('users')->where('user_id', 'USER-ONE')->value('profile_photo');
        $this->assertIsString($path);
        Storage::disk('public')->assertExists($path);

        $reloadedUser = User::query()->findOrFail('USER-ONE');
        Sanctum::actingAs($reloadedUser);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.profile_photo', $path);
    }

    public function test_replacing_photo_removes_the_previous_stored_file(): void
    {
        Storage::fake('public');
        $this->uploadPhoto('first.png')->assertOk();
        $firstPath = DB::table('users')->where('user_id', 'USER-ONE')->value('profile_photo');

        $this->uploadPhoto('replacement.jpg')->assertOk();
        $replacementPath = DB::table('users')->where('user_id', 'USER-ONE')->value('profile_photo');

        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);
    }

    public function test_user_can_remove_their_photo_and_return_to_the_default_avatar(): void
    {
        Storage::fake('public');
        $this->uploadPhoto()->assertOk();
        $path = DB::table('users')->where('user_id', 'USER-ONE')->value('profile_photo');

        $this->deleteJson('/api/v1/profile/photo')
            ->assertOk()
            ->assertJsonPath('data.profile_photo', null);

        $this->assertDatabaseHas('users', ['user_id' => 'USER-ONE', 'profile_photo' => null]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_photo_upload_requires_authentication_and_rejects_invalid_files(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('avatar.png');
        auth()->forgetGuards();

        $this->post('/api/v1/profile/photo', ['photo' => $photo], ['Accept' => 'application/json'])
            ->assertUnauthorized();

        Sanctum::actingAs(User::query()->findOrFail('USER-ONE'));
        $this->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('oversized.png')->size(5121),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['user_id' => 'USER-ONE', 'profile_photo' => null]);
    }

    public function test_authenticated_users_can_only_change_their_own_photo(): void
    {
        Storage::fake('public');
        $this->uploadPhoto()->assertOk();
        $firstUserPath = DB::table('users')->where('user_id', 'USER-ONE')->value('profile_photo');

        $otherUser = $this->createUser('USER-TWO');
        Sanctum::actingAs($otherUser);
        $this->deleteJson('/api/v1/profile/photo')->assertOk();

        $this->assertDatabaseHas('users', ['user_id' => 'USER-ONE', 'profile_photo' => $firstUserPath]);
        $this->assertDatabaseHas('users', ['user_id' => 'USER-TWO', 'profile_photo' => null]);
        Storage::disk('public')->assertExists($firstUserPath);
    }

    private function uploadPhoto(string $name = 'avatar.png')
    {
        return $this->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image($name),
        ], ['Accept' => 'application/json']);
    }

    private function createUser(string $userId): User
    {
        return User::query()->create([
            'user_id' => $userId,
            'role' => 'household_resident',
            'role_id' => 1,
            'is_active' => true,
        ]);
    }
}