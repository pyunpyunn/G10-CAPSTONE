<?php

namespace Tests\Feature;

use App\Http\Requests\RescuerAccountPayloadValidator;
use App\Http\Requests\RescueTeamPayloadValidator;
use App\Presenters\RescuerAccountPresenter;
use App\Queries\RescuerAccountQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RescuerAccountMetadataTest extends TestCase
{
    public function test_account_options_and_validation_share_backend_status_definitions(): void
    {
        config(['rescuers.duty_statuses.training' => ['label' => 'In training', 'tone' => 'amber']]);
        config(['rescuers.account_statuses.reserve' => ['label' => 'Reserve account', 'tone' => 'amber']]);
        config(['rescuers.account_defaults.duty_status' => 'training']);
        $presenter = app(RescuerAccountPresenter::class);
        $options = $presenter->accountFormOptions();
        $status = ['key' => 'training', 'label' => 'In training', 'tone' => 'amber'];
        $this->assertContains($status, $options['duty_statuses']);
        $this->assertSame($status, $presenter->formatStatus('training'));
        $this->assertSame('training', $options['defaults']['duty_status']);
        $this->assertSame('Reserve account', $presenter->formatAccountStatus('reserve')['label']);
        $payload = ['first_name' => 'Alex', 'last_name' => 'Reyes', 'contact_number' => '555',
            'team_name' => 'Medical', 'title' => 'Medic', 'duty_status' => 'training', 'account_status' => 'reserve'];
        $rules = app(RescuerAccountPayloadValidator::class)->rules();
        $this->assertTrue(Validator::make($payload, $rules)->passes());
        $payload['duty_status'] = 'not_a_status';
        $this->assertTrue(Validator::make($payload, $rules)->fails());
    }

    public function test_browser_constraints_are_derived_from_create_and_update_rules(): void
    {
        $validator = app(RescuerAccountPayloadValidator::class);
        $create = $validator->formConstraints();
        $edit = $validator->formConstraints(true);
        $this->assertTrue($create['team_name']['required']);
        $this->assertFalse($edit['team_name']['required']);
        $this->assertTrue($create['contact_number']['required']);
        $this->assertSame(6, $create['password']['minLength']);
        $this->assertFalse($create['password']['required']);
        $this->assertSame(100, $edit['first_name']['maxLength']);
        $this->assertSame(255, $edit['email']['maxLength']);
        $this->assertSame(now()->subDay()->toDateString(), $edit['date_of_birth']['max']);
        $this->assertSame(8, app(RescueTeamPayloadValidator::class)->formConstraints()['team_code']['maxLength']);
    }

    public function test_team_status_choices_and_validation_follow_backend_configuration(): void
    {
        config(['rescuers.team_duty_statuses' => ['available', 'on_duty']]);
        $choices = app(RescuerAccountPresenter::class)->statusOptions(config('rescuers.team_duty_statuses'));
        $this->assertSame(['available', 'on_duty'], array_column($choices, 'key'));
        $rules = app(RescueTeamPayloadValidator::class)->rules();
        $payload = ['team_name' => 'Medical', 'team_code' => 'MED', 'team_type' => 'Medical', 'duty_status' => 'on_duty'];
        $this->assertTrue(Validator::make($payload, $rules)->passes());
        $payload['duty_status'] = 'off_duty';
        $this->assertTrue(Validator::make($payload, $rules)->fails());
    }

    public function test_team_workflow_normalizes_codes_and_checks_uniqueness_on_backend(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table) {
            $table->integer('team_id'); $table->string('team_code'); $table->string('team_name');
        });
        $workflow = app(\App\Services\Mobile\RescuerTeamWorkflow::class);
        $payload = ['team_name' => ' Medical ', 'team_code' => 'med', 'team_type' => 'Medical', 'duty_status' => 'available'];
        $validated = $workflow->validateTeamPayload(\Illuminate\Http\Request::create('/', 'POST', $payload));
        $this->assertSame('MED', $validated['team_code']);
        $this->assertSame('Medical', $validated['team_name']);
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_code' => 'MED', 'team_name' => 'Medical']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $workflow->validateTeamPayload(\Illuminate\Http\Request::create('/', 'POST', $payload));
    }

    public function test_roster_response_exposes_form_metadata_from_backend_layers(): void
    {
        $query = $this->createMock(RescuerAccountQuery::class);
        $query->method('paginateResponders')->willReturn(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15));
        $query->method('viewData')->willReturn(['summary' => [], 'teams' => [], 'team_options' => [], 'puroks' => []]);
        $query->method('accountIdChoices')->willReturn(['options' => [], 'default' => 'BDRRM-SAR-001']);
        $this->app->instance(RescuerAccountQuery::class, $query);
        $area = $this->createMock(\App\Queries\AreaCoverageQuery::class);
        $area->method('label')->willReturn('Configured coverage');
        $this->app->instance(\App\Queries\AreaCoverageQuery::class, $area);
        $response = app(\App\Services\Mobile\RescuerAccountService::class)->index(\Illuminate\Http\Request::create('/'));
        $data = $response->getData(true)['data'];
        $this->assertSame('Configured coverage', $data['area_label']);
        $this->assertSame(config('rescuers.account_defaults'), array_intersect_key($data['form_options']['defaults'], config('rescuers.account_defaults')));
        $this->assertNull($data['form_options']['defaults']['team_id']);
        $this->assertSame('', $data['form_options']['defaults']['account_id']);
        $this->assertSame(app(RescuerAccountPayloadValidator::class)->formConstraints(), $data['form_options']['constraints']['create']);
        $this->assertSame(app(RescuerAccountPayloadValidator::class)->formConstraints(true), $data['form_options']['constraints']['edit']);
        $this->assertSame(app(RescuerAccountPresenter::class)->statusOptions(array_keys(config('rescuers.duty_statuses'))), $data['form_options']['duty_statuses']);
    }

    public function test_membership_permissions_and_labels_are_presented_by_backend(): void
    {
        $presenter = app(RescuerAccountPresenter::class);
        foreach (['dispatched', 'on_scene'] as $status) {
            $data = $presenter->membershipPresentation((object) ['is_deployed' => 0, 'duty_status' => $status]);
            $this->assertFalse($data['can_change_membership']);
            $this->assertSame('Busy', $data['membership_status']['label']);
        }
        $this->assertFalse($presenter->membershipPresentation((object) ['is_deployed' => 1, 'duty_status' => 'available'])['can_change_membership']);
        $this->assertTrue($presenter->membershipPresentation((object) ['is_deployed' => 0, 'duty_status' => 'available'])['can_change_membership']);
    }

    public function test_team_cards_count_real_assignments_without_multiplying_roster_counts(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table) {
            $table->integer('team_id'); $table->string('team_code'); $table->string('team_name');
            $table->string('team_type'); $table->string('duty_status');
        });
        Schema::create('responders', function (Blueprint $table) {
            $table->integer('responder_id'); $table->integer('team_id'); $table->boolean('is_deployed');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table) {
            $table->integer('team_id'); $table->string('status');
        });
        DB::table('rescue_teams')->insert([
            ['team_id' => 1, 'team_code' => 'MED', 'team_name' => 'Medical', 'team_type' => 'Medical', 'duty_status' => 'on_duty'],
            ['team_id' => 2, 'team_code' => 'SAR', 'team_name' => 'Search', 'team_type' => 'Rescue', 'duty_status' => 'available'],
        ]);
        DB::table('responders')->insert([
            ['responder_id' => 1, 'team_id' => 1, 'is_deployed' => true, 'deleted_at' => null],
            ['responder_id' => 2, 'team_id' => 1, 'is_deployed' => false, 'deleted_at' => null],
            ['responder_id' => 3, 'team_id' => 1, 'is_deployed' => true, 'deleted_at' => now()],
        ]);
        foreach (['en_route', 'on_scene', 'completed', 'cancelled'] as $status) {
            DB::table('responder_assignments')->insert(['team_id' => 1, 'status' => $status]);
        }
        $cards = app(RescuerAccountQuery::class)->teamCards();
        $this->assertSame(2, $cards[0]['member_count']);
        $this->assertSame(1, $cards[0]['deployed_count']);
        $this->assertSame(2, $cards[0]['active_dispatch_count']);
        $this->assertSame('On duty', $cards[0]['duty_status_display']['label']);
        $this->assertSame(0, $cards[1]['active_dispatch_count']);
        $this->assertSame(0, $cards[1]['member_count']);
    }
}
