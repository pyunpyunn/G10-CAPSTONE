<?php

namespace App\Data;

class DashboardSnapshot
{
    public function __construct(
        public readonly ?object $event,
        public readonly ?object $latestBroadcast,
        public readonly array $barangayProfile,
        public readonly array $households,
        public readonly ?array $dispatch = null,
        public readonly ?array $weather = null,
        public readonly ?array $requests = null,
        public readonly ?array $map = null,
        public readonly ?array $recentActivity = null,
    ) {}
}
