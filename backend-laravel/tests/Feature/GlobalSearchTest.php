<?php

namespace Tests\Feature;

use App\Services\Web\GlobalSearchService;
use App\Services\Web\WebAccessPolicy;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
            $table->string('role_name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('username')->nullable();
            $table->string('login_id')->nullable();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->integer('role_id')->nullable();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->increments('responder_id');
            $table->string('user_id');
            $table->string('username')->nullable();
            $table->string('responder_code')->nullable();
            $table->string('full_name')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('duty_status')->nullable();
            $table->softDeletes();
        });

        DB::table('roles')->insert([
            'role_id' => 8,
            'role_key' => 'operations_admin',
            'role_name' => 'Operations/Admin',
        ]);
        DB::table('users')->insert([
            'user_id' => 'USR-2024035500',
            'username' => '2024035500',
            'login_id' => '2024035500',
            'email' => 'mara@example.test',
            'first_name' => 'Mara',
            'last_name' => 'Santos',
            'role_id' => 8,
        ]);
        DB::table('responders')->insert([
            'responder_id' => 6,
            'user_id' => 'USR-2024035500',
            'username' => '2024035500',
            'responder_code' => '2024035500',
            'full_name' => 'Mara Santos',
            'duty_status' => 'on_duty',
        ]);
    }

    public function test_personnel_names_match_case_insensitively_with_middle_names_and_reordered_tokens(): void
    {
        DB::table('responders')->where('responder_id', 6)->update(['full_name' => 'Mara A. Santos']);
        foreach (['mara santos', 'SANTOS MARA', '  Mara   Santos  '] as $query) {
            $results = app(GlobalSearchService::class)->search($query, ['responders']);
            $this->assertCount(1, $results);
            $this->assertSame('/rescuers/view?id=6', $results[0]['href']);
        }
        $this->assertSame([], app(GlobalSearchService::class)->search('Mara Missing', ['responders']));
    }

    public function test_each_web_role_can_find_personnel_without_personnel_management_access(): void
    {
        foreach (WebAccessPolicy::MODULES as $roleKey => $modules) {
            if ($modules === []) continue;
            $user = new User(['is_active' => true]);
            $user->setRelation('role', new Role(['role_key' => $roleKey]));
            $request = Request::create('/api/v1/global-search', 'GET', ['q' => 'santos mara', 'types' => ['responders']]);
            $request->setUserResolver(fn (): User => $user);
            $response = app(GlobalSearchController::class)->index($request)->getData(true);
            $this->assertSame('Mara Santos', $response['data']['results'][0]['title']);
            $this->assertSame('/rescuers/view?id=6', $response['data']['results'][0]['href']);
        }
    }

    public function test_operations_admin_can_search_within_its_existing_modules(): void
    {
        $user = new User();
        $user->setAttribute('is_active', true);
        $user->setRelation('role', new Role(['role_key' => 'operations_admin']));
        $this->assertTrue(app(WebAccessPolicy::class)->allows($user, 'global_search'));
        $this->assertTrue(app(WebAccessPolicy::class)->allows($user, 'geospatial_analytics'));
    }

    public function test_team_search_links_to_the_specific_team(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table): void {
            $table->string('team_id')->primary();
            $table->string('team_code');
            $table->string('team_name');
            $table->string('team_type');
        });
        DB::table('rescue_teams')->insert(['team_id' => 'T-1', 'team_code' => 'SAR', 'team_name' => 'Search Rescue Team', 'team_type' => 'Response']);
        $results = app(GlobalSearchService::class)->search('rescue search', ['teams']);
        $this->assertCount(1, $results);
        $this->assertSame('/rescuers/teams?team_id=T-1', $results[0]['href']);
    }

    public function test_account_login_fields_find_and_open_committee_accounts_by_role(): void
    {
        $results = app(GlobalSearchService::class)->search('mara@example.test', ['responders']);

        $this->assertCount(1, $results);
        $this->assertSame('Mara Santos', $results[0]['title']);
        $this->assertSame('/rescuers/view?id=6', $results[0]['href']);
    }

    public function test_global_search_is_available_to_any_user_with_a_web_module(): void
    {
        $request = Request::create('/api/v1/global-search', 'GET');
        $policy = app(WebAccessPolicy::class);
        $user = new User();
        $user->setAttribute('is_active', true);
        $user->setRelation('role', new Role([
            'base_role_key' => 'response',
            'web_modules' => ['resources_requests'],
        ]));

        $this->assertSame('global_search', $policy->moduleForRequest($request));
        $this->assertTrue($policy->allows($user, 'global_search'));
    }

    public function test_api_only_searches_record_types_allowed_by_the_users_modules(): void
    {
        $search = $this->mock(GlobalSearchService::class);
        $search->shouldReceive('search')->once()->with('storm', ['responders', 'resources'])->andReturn([]);

        $user = new User();
        $user->setAttribute('is_active', true);
        $user->setRelation('role', new Role([
            'base_role_key' => 'response',
            'web_modules' => ['dashboard', 'resources_requests'],
        ]));
        $request = Request::create('/api/v1/global-search', 'GET', [
            'q' => 'storm',
            'types' => ['responders', 'resources'],
        ]);
        $request->setUserResolver(fn (): User => $user);

        $response = (new GlobalSearchController($search))->index($request);

        $this->assertSame(['data' => ['results' => []]], $response->getData(true));
    }
}
