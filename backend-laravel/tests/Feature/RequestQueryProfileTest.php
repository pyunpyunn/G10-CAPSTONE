<?php

namespace Tests\Feature;

use App\Support\RequestQueryProfile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestQueryProfileTest extends TestCase
{
    public function test_profile_keeps_three_slowest_parameterized_queries_without_bindings(): void
    {
        $profile = new RequestQueryProfile();
        $connection = DB::connection();

        foreach ([1.0, 8.0, 3.0, 5.0] as $duration) {
            $profile->record(new QueryExecuted(
                'select * from users where email = ?',
                ['private@example.test'],
                $duration,
                $connection,
            ));
        }

        $this->assertSame(4, $profile->count);
        $this->assertSame(17.0, $profile->totalMs);
        $this->assertSame([8.0, 5.0, 3.0], array_column($profile->slowest, 'ms'));
        $this->assertSame('select * from users where email = ?', $profile->slowest[0]['sql']);
        $this->assertStringNotContainsString('private@example.test', json_encode($profile->slowest));
    }
}
