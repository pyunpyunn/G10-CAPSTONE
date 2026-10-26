<?php

namespace Tests\Feature;

use App\Support\QueryProfileRanking;
use Tests\TestCase;

class QueryProfileRankingTest extends TestCase
{
    public function test_it_ranks_five_slowest_routes_from_profile_log(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'profiles-');
        try {
            $lines = [];
            foreach (range(1, 6) as $index) {
                $lines[] = '[local.INFO] Local API query profile '.json_encode([
                    'route' => 'route-'.$index, 'query_count' => $index,
                    'db_ms' => $index * 10, 'response_ms' => $index * 20,
                ]);
            }
            file_put_contents($path, implode(PHP_EOL, $lines));
            $ranked = (new QueryProfileRanking())->fromLog($path);
            $this->assertCount(5, $ranked);
            $this->assertSame('route-6', $ranked[0]['route']);
            $this->assertSame(120.0, $ranked[0]['response_ms']);
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}
