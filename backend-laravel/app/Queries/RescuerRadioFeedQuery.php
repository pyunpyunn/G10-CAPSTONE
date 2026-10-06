<?php

namespace App\Queries;

use App\Presenters\RescuerRadioPresenter;
use App\Services\Mobile\RescuerMobileSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\Rule;

class RescuerRadioFeedQuery
{
    public function __construct(private RescuerMobileSupport $support, private RescuerRadioPresenter $radioPresenter) {}

    public function radioFeed(Request $request): JsonResponse|array
    {
        if (! Schema::hasTable('responder_communication_logs')) {
            return $this->missingTableResponse('responder_communication_logs');
        }

        $validated = $request->validate([
            'channel' => ['nullable', Rule::in(['command', 'team', 'event'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $user = $request->user();
        $responder = $this->support->responderForUser($user);

        if (! $responder) {
            return response()->json([
                'message' => 'Rescuer profile was not found for this account.',
            ], 404);
        }

        $channel = $validated['channel'] ?? 'team';
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = min(20, max(1, (int) ($validated['per_page'] ?? 5)));
        $radioRows = $this->radioRowsForScope($responder, 150);
        $logs = $this->radioPresenter->logs($radioRows)
            ->filter(fn (array $log): bool => ($log['channel'] ?? 'team') === $channel)
            ->values();
        $total = $logs->count();
        $pageIds = $logs->forPage($page, $perPage)->pluck('communication_id')->map(fn ($id): string => (string) $id);
        $items = $radioRows
            ->filter(fn (object $row): bool => $pageIds->contains((string) $row->communication_id))
            ->values();
        $activeTransmission = $this->activeRadioTransmission($responder, $logs);

        return [
            'channel' => $channel,
            'active_transmission' => $activeTransmission,
            'team_members' => $this->radioTeamMembers($responder, $logs, $activeTransmission),
            'logs' => [
                'data' => $items,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($page * $perPage) < $total,
            ],
            'audio_note' => 'Voice clips are saved and played through the team radio feed.',
        ];
    }

    private function radioRowsForScope(object $responder, int $limit = 120): Collection
    {
        $activeEvent = $this->support->activeEvent();
        $query = DB::table('responder_communication_logs');

        if ($responder->team_id) {
            $query->where('team_id', $responder->team_id);
        } else {
            $query->where('responder_id', $responder->responder_id);
        }

        if ($activeEvent) {
            $query->where('disaster_id', $activeEvent->event_id);
        } else {
            $query->whereNull('disaster_id');
        }

        return $query
            ->orderByDesc('timestamp')
            ->orderByDesc('communication_id')
            ->limit(max(20, min($limit, 200)))
            ->get()
            ->values();
    }

    private function radioTeamMembers(object $responder, $logs, ?array $activeTransmission): array
    {
        if (! Schema::hasTable('responders')) {
            return [];
        }

        $query = DB::table('responders as r');
        $columns = [
            'r.responder_id',
            'r.responder_code',
            'r.full_name',
            'r.team_id',
        ];

        if ($responder->team_id) {
            $query->where('r.team_id', $responder->team_id);
        } else {
            $query->where('r.responder_id', $responder->responder_id);
        }

        if (Schema::hasTable('rescue_teams')) {
            $query->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id');
            $columns[] = 'rt.team_name';
            $columns[] = 'rt.team_code';
        } else {
            $columns[] = DB::raw('NULL as team_name');
            $columns[] = DB::raw('NULL as team_code');
        }

        return $this->radioPresenter->teamMembers(
            $query
            ->orderBy('r.full_name')
            ->get($columns),
            collect($logs),
            $activeTransmission,
            $responder,
        );
    }

    private function activeRadioTransmission(object $responder, $logs = null): ?array
    {
        $radioLogs = $logs
            ? collect($logs)
            : $this->radioPresenter->logs($this->radioRowsForScope($responder, 60));

        return $this->radioPresenter->activeTransmission($responder, $radioLogs);
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => "The {$table} table is not available yet. Connect to the shared database or ask the DB member to apply the approved schema.",
        ], 503);
    }
}



