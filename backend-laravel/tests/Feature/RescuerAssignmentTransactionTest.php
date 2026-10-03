<?php

namespace Tests\Feature;

use App\Queries\RescuerAssignmentQuery;
use App\Services\Mobile\RescuerAssignmentWorkflow;
use App\Services\Mobile\RescuerMobileSupport;
use App\Services\Shared\RoutingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RescuerAssignmentTransactionTest extends TestCase
{
    public function test_assignment_and_duty_roll_back_if_a_later_write_fails(): void
    {
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->string('status');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary();
            $table->boolean('is_deployed')->default(false);
            $table->string('duty_status')->nullable();
        });
        DB::table('responder_assignments')->insert(['assignment_id' => 7, 'status' => 'dispatched']);
        DB::table('responders')->insert(['responder_id' => 5, 'is_deployed' => 0, 'duty_status' => 'available']);

        $assignment = (object) [
            'assignment_id' => 7, 'accepted_at' => null, 'en_route_at' => null,
            'arrived_at' => null, 'completed_at' => null, 'outcome_notes' => null,
        ];
        $query = Mockery::mock(RescuerAssignmentQuery::class);
        $query->shouldReceive('find')->once()->with(7, 5)->andReturn($assignment);
        $support = Mockery::mock(RescuerMobileSupport::class)->makePartial();
        $support->shouldReceive('responderForUser')->once()->andReturn((object) ['responder_id' => 5, 'team_id' => null]);
        $support->shouldReceive('writeAuditLog')->once()->andThrow(new RuntimeException('simulated final write failure'));
        $workflow = new RescuerAssignmentWorkflow(Mockery::mock(RoutingService::class), $support, $query);
        $request = Request::create('/assignments/7/status', 'PATCH', ['status' => 'accepted']);
        $request->setUserResolver(fn () => (object) ['user_id' => 'rescuer-5']);

        try {
            $workflow->updateAssignmentStatus($request, 7);
            $this->fail('Expected the simulated final write to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated final write failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('responder_assignments', ['assignment_id' => 7, 'status' => 'dispatched']);
        $this->assertDatabaseHas('responders', ['responder_id' => 5, 'is_deployed' => 0, 'duty_status' => 'available']);
    }
}
