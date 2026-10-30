<?php

namespace Tests\Feature;

use App\Models\RescueCriteriaSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescueCriteriaSchemaRepairTest extends TestCase
{
    public function test_imported_history_is_preserved_and_current_definition_can_be_persisted(): void
    {
        Schema::create('rescue_criteria_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
            $table->timestamp('observed_at');
        });
        DB::table('rescue_criteria_snapshots')->insert(['event_id' => 'EVENT-OLD', 'observed_at' => now()]);
        $repair = require database_path('migrations/2026_10_30_000002_reconcile_rescue_criteria_definition_version.php');
        $repair->up();
        $repair->up();
        $this->assertDatabaseHas('rescue_criteria_snapshots', ['event_id' => 'EVENT-OLD', 'definition_version' => 1]);
        RescueCriteriaSnapshot::create(['event_id' => 'EVENT-CURRENT', 'observed_at' => now(), 'definition_version' => 6]);
        $this->assertSame(6, RescueCriteriaSnapshot::where('event_id', 'EVENT-CURRENT')->sole()->definition_version);
        $this->assertDatabaseCount('rescue_criteria_snapshots', 2);
    }
}
