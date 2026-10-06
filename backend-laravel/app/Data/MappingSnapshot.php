<?php

namespace App\Data;

class MappingSnapshot
{
    public function __construct(
        public readonly ?object $event,
        public readonly array $barangay,
        public readonly array $summary,
        public readonly array $puroks,
        public readonly bool $full,
        public readonly array $households = [],
        public readonly array $evacuationSites = [],
        public readonly array $rescueTeams = [],
        public readonly array $dispatchRoutes = [],
    ) {}
}
