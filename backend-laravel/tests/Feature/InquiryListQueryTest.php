<?php

namespace Tests\Feature;

use App\Services\Web\InquiryService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryListQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('landing_inquiries', function (Blueprint $table): void {
            $table->id('inquiry_id');
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('status')->default('new');
            $table->string('source_page')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('handled_by_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 150);
            $table->string('organization', 150);
            $table->string('email', 255);
            $table->string('message', 100);
            $table->timestamp('created_at')->nullable();
        });

        \DB::table('landing_inquiries')->insert([
            [
                'name' => 'Older matching inquiry',
                'organization' => null,
                'email' => 'older@example.test',
                'message' => 'Need assistance',
                'status' => 'new',
                'source_page' => 'landing_page',
                'created_at' => '2026-09-01 10:00:00',
                'updated_at' => '2026-09-01 10:00:00',
            ],
            [
                'name' => 'Newer matching inquiry',
                'organization' => null,
                'email' => 'newer@example.test',
                'message' => 'Need assistance now',
                'status' => 'new',
                'source_page' => 'landing_page',
                'created_at' => '2026-09-02 10:00:00',
                'updated_at' => '2026-09-02 10:00:00',
            ],
            [
                'name' => 'Closed inquiry',
                'organization' => null,
                'email' => 'closed@example.test',
                'message' => 'Other issue',
                'status' => 'closed',
                'source_page' => 'landing_page',
                'created_at' => '2026-09-03 10:00:00',
                'updated_at' => '2026-09-03 10:00:00',
            ],
        ]);
    }

    public function test_index_preserves_filtered_pagination_shape_and_newest_first_order(): void
    {
        $request = Request::create('/api/v1/inquiries?status=new&search=assistance&per_page=1', 'GET');
        $response = (new \App\Http\Resources\InquiryWorkspaceResource(app(InquiryService::class)->index($request)))->response($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'data',
            'current_page',
            'per_page',
            'last_page',
            'total',
            'from',
            'to',
        ], array_keys($payload['data']['inquiries']));
        $this->assertSame(2, $payload['data']['inquiries']['total']);
        $this->assertSame(1, $payload['data']['inquiries']['per_page']);
        $this->assertSame(2, $payload['data']['inquiries']['last_page']);
        $this->assertSame('Newer matching inquiry', $payload['data']['inquiries']['data'][0]['name']);
        $this->assertSame('Sep 02, 2026 10:00 AM', $payload['data']['inquiries']['data'][0]['created_at']);
        $this->assertSame(['new' => 2, 'in_review' => 0, 'responded' => 0, 'closed' => 1], $payload['data']['summary']);
    }

    public function test_public_store_keeps_inquiry_response_shape(): void
    {
        $this->postJson('/api/v1/inquiries', ['name' => 'Requester', 'message' => 'Please help us.'])
            ->assertCreated()
            ->assertJsonPath('message', 'Inquiry sent.')
            ->assertJsonPath('data.inquiry.name', 'Requester');

        $this->assertDatabaseHas('inquiries', [
            'full_name' => 'Requester',
            'organization' => '',
            'email' => '',
            'message' => 'Please help us.',
        ]);
    }

    public function test_account_rows_support_users_table_without_login_id_column(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('role_id')->primary();
            $table->string('role_key');
            $table->string('role_name');
            $table->string('base_role_key')->nullable();
            $table->json('web_modules')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('username');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->integer('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        \DB::table('roles')->insert([
            'role_id' => 1,
            'role_key' => 'operations_admin',
            'role_name' => 'Operations/Admin',
        ]);
        \DB::table('users')->insert([
            'user_id' => 'USR-ADMIN',
            'username' => 'OPS-0001',
            'name' => 'Admin User',
            'role_id' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accounts = app(\App\Queries\InquiryListQuery::class)->accountRows();

        $this->assertCount(1, $accounts);
        $this->assertSame('OPS-0001', $accounts->first()->username);
        $this->assertSame('operations_admin', $accounts->first()->role->role_key);
    }

    public function test_admin_status_update_keeps_inquiry_response_shape(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('user_id')->primary();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Sanctum::actingAs(User::query()->create(['user_id' => 'ADMIN-INQUIRY', 'role' => 'admin']));
        $id = (int) \DB::table('landing_inquiries')->where('name', 'Older matching inquiry')->value('inquiry_id');
        $this->patchJson('/api/v1/inquiries/'.$id, ['status' => 'responded'])
            ->assertOk()
            ->assertJsonPath('message', 'Inquiry updated.')
            ->assertJsonPath('data.inquiry.status', 'responded');
    }
}
