<?php

namespace Tests\Feature;

use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Mobile\RescuerFieldReportWorkflow;
use App\Services\Mobile\RescuerMobileSupport;
use App\Queries\RescuerAssignmentQuery;
use App\Queries\RescuerFieldReportQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HouseholdProfilePhotoWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('name');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('household_id');
            $table->string('member_id')->unique();
            $table->string('profile_photo')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        DB::table('household_members')->insert([
            ['member_id' => 'HM-1-1', 'household_id' => 'HH-1', 'name' => 'First Member'],
            ['member_id' => 'HM-1-2', 'household_id' => 'HH-1', 'name' => 'Second Member'],
        ]);
        DB::table('users')->insert([
            ['user_id' => 'USER-1', 'household_id' => 'HH-1', 'member_id' => 'HM-1-1', 'username' => 'HM-1-1', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 'USER-2', 'household_id' => 'HH-1', 'member_id' => 'HM-1-2', 'username' => 'HM-1-2', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_each_household_member_can_upload_a_photo_for_their_own_linked_account(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $storedPaths = [];
        foreach ([
            ['user_id' => 'USER-1', 'member_id' => 'HM-1-1'],
            ['user_id' => 'USER-2', 'member_id' => 'HM-1-2'],
        ] as $identity) {
            $user = User::query()->where('user_id', $identity['user_id'])->firstOrFail();
            $member = HouseholdMember::query()->where('member_id', $identity['member_id'])->firstOrFail();
            $this->assertTrue($member->user->is($user));

            $request = Request::create('/api/v1/household/profile-photo', 'POST');
            $request->files->set('photo', UploadedFile::fake()->createWithContent('profile.png', $image));
            $request->setUserResolver(fn () => $user);

            $response = app(\App\Services\HouseholdMobileService::class)->uploadProfilePhoto($request);

            $this->assertSame(200, $response->getStatusCode());
            $savedUser = $response->getData(true)['data']['user'];
            $this->assertSame($identity['member_id'], $savedUser['member_id']);
            $this->assertTrue($savedUser['profile_photo_uploaded']);
            $this->assertArrayNotHasKey('profile_photo_url', $savedUser);
            $storedPath = DB::table('users')->where('user_id', $identity['user_id'])->value('profile_photo');
            $storedPaths[] = $storedPath;
            Storage::disk('local')->assertExists($storedPath);
            Storage::disk('public')->assertMissing($storedPath);
        }
        $this->assertNotSame($storedPaths[0], $storedPaths[1]);
    }

    public function test_assigned_responder_can_stream_a_household_member_photo_without_public_download_url(): void
    {
        Storage::fake('local');
        $memberId = 'HM-1-1';
        $path = 'household-profile-photos/'.hash('sha256', 'HH-1').'/'.hash('sha256', $memberId).'/'.\Illuminate\Support\Str::uuid().'.png';
        Storage::disk('local')->put($path, 'private image');
        DB::table('users')->where('member_id', $memberId)->update(['profile_photo' => $path]);

        $this->mock(RescuerMobileSupport::class, function ($mock): void {
            $mock->shouldReceive('responderForUser')->once()->andReturn((object) ['responder_id' => 17]);
            $mock->shouldReceive('activeEvent')->once()->andReturn((object) ['event_id' => 'EVT-1']);
        });
        $this->mock(RescuerAssignmentQuery::class, function ($mock): void {
            $mock->shouldReceive('find')->once()->with(9, 17)
                ->andReturn((object) ['household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'status' => 'dispatched']);
        });
        $this->mock(RescuerFieldReportQuery::class);
        $request = Request::create('/api/v1/rescuer/assignments/9/members/'.$memberId.'/profile-photo', 'GET');
        $request->setUserResolver(fn () => (object) ['user_id' => 'RESPONDER-1']);

        $response = app(RescuerFieldReportWorkflow::class)->assignedMemberProfilePhoto($request, 9, $memberId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unassigned_responder_cannot_stream_a_profile_photo(): void
    {
        $this->mock(RescuerMobileSupport::class, function ($mock): void {
            $mock->shouldReceive('responderForUser')->once()->andReturn((object) ['responder_id' => 18]);
        });
        $this->mock(RescuerAssignmentQuery::class, function ($mock): void {
            $mock->shouldReceive('find')->once()->with(9, 18)->andReturnNull();
        });
        $this->mock(RescuerFieldReportQuery::class);
        $request = Request::create('/api/v1/rescuer/assignments/9/members/HM-1-1/profile-photo', 'GET');
        $request->setUserResolver(fn () => (object) ['user_id' => 'OTHER-RESPONDER']);

        try {
            app(RescuerFieldReportWorkflow::class)->assignedMemberProfilePhoto($request, 9, 'HM-1-1');
            $this->fail('An unassigned responder must not access the photo.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
