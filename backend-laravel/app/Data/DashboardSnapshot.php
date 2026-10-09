<?php

namespace App\Data;

class DashboardSnapshot
{
    public function toCacheArray(): array
    {
        $data = get_object_vars($this);
        $data['event'] = $this->event === null ? null : (array) $this->event;
        $data['latestBroadcast'] = $this->latestBroadcast === null ? null : (array) $this->latestBroadcast;

        return $data;
    }

    public static function fromCacheArray(array $data): self
    {
        return new self(
            $data['event'] === null ? null : (object) $data['event'],
            $data['latestBroadcast'] === null ? null : (object) $data['latestBroadcast'],
            $data['barangayProfile'],
            $data['households'],
            $data['dispatch'],
            $data['weather'],
            $data['requests'],
            $data['map'],
            $data['recentActivity'],
        );
    }

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
