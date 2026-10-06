<?php

namespace App\Http\Resources;

use App\Presenters\DashboardPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $event = $snapshot->event
            ? app(DashboardPresenter::class)->formatActiveEvent($snapshot->event, $snapshot->latestBroadcast)
            : null;
        $data = [
            'barangay_profile' => $snapshot->barangayProfile,
            'active_event' => $event,
            'households' => $snapshot->households,
        ];
        if ($snapshot->dispatch !== null) {
            $data['dispatch'] = $snapshot->dispatch;
            $data['weather'] = $snapshot->weather;
            $data['requests'] = $snapshot->requests;
            $data['map'] = $snapshot->map;
            $data['recent_activity'] = $snapshot->recentActivity;
        }
        return $data;
    }
}
