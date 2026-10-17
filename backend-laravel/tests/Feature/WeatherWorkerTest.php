<?php

namespace Tests\Feature;

use App\Console\Commands\WorkWeatherUpdates;
use App\Services\Shared\WeatherSnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class WeatherWorkerTest extends TestCase
{
    public function test_worker_captures_due_weather_but_keeps_fresh_weather_without_an_extra_provider_call(): void
    {
        Schema::create('weather_logs', fn (Blueprint $t) => $t->timestamp('created_at'));
        foreach ([1 => 0, 4 => 1] as $hoursAgo => $expected) {
            DB::table('weather_logs')->delete();
            DB::table('weather_logs')->insert(['created_at' => now()->subHours($hoursAgo)]);
            $command = \Mockery::mock(WorkWeatherUpdates::class)->makePartial();
            $command->shouldReceive('call')->once()->with('queue:work', \Mockery::on(fn ($args) => $args['--queue'] === 'weather' && $args['connection'] === 'operations_outbox'))->andReturn(0);
            $command->shouldReceive('option')->with('once')->andReturn(true);
            $command->shouldReceive('line')->zeroOrMoreTimes();
            $weather = \Mockery::mock(WeatherSnapshotService::class);
            $weather->shouldReceive('saveLatestSnapshot')->times($expected)->with(null)->andReturn(['message'=>'Weather snapshot saved.']);
            $this->assertSame(0, $command->handle($weather));
        }
    }
}
