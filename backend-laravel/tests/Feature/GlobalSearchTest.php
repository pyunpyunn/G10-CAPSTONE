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

    public function test_account_login_fields_find_and_open_committee_accounts_by_role(): void
    {
        $results = app(GlobalSearchService::class)->search('mara@example.test', ['responders']);

        $this->assertCount(1, $results);
        $this->assertSame('Mara Santos', $results[0]['title']);
        $this->assertSame('/rescuers?id=6&type=command_center', $results[0]['href']);
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
        $search->shouldReceive('search')->once()->with('storm', ['resources'])->andReturn([]);

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