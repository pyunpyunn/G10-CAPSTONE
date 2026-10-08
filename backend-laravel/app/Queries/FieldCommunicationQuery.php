<?php

namespace App\Queries;

use App\Http\Requests\ListRequest;
use App\Support\RequestSchema as Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldCommunicationQuery
{
    public function paginate(Request $request, ?string $eventId): LengthAwarePaginator
    {
        abort_unless(Schema::hasTable('responder_communication_logs'), 503, 'Field communication records are currently unavailable.');
        $query = DB::table('responder_communication_logs as rcl');
        $eventId ? $query->where('rcl.disaster_id', $eventId) : $query->whereNull('rcl.disaster_id');
        if ($request->boolean('recordings_only')) {
            $query->whereNotNull('rcl.message->audio_path')->where('rcl.message->audio_path', '<>', '');
        }
        if ($request->filled('team_id')) $query->where('rcl.team_id', $request->query('team_id'));
        if ($request->query('channel', 'all') !== 'all') {
            $query->where('rcl.message->channel', $request->query('channel'));
        }
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(fn ($inner) => $inner->where('rcl.team_name', 'like', "%{$search}%")
                ->orWhere('rcl.message', 'like', "%{$search}%"));
        }
        return $query->orderByDesc('rcl.timestamp')->orderByDesc('rcl.communication_id')
            ->paginate(ListRequest::clampPerPage($request->query('per_page')), ['*'], 'page', ListRequest::clampPage($request->query('page')));
    }
}
