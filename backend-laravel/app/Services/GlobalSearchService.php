<?php

namespace App\Services;

use App\Models\DisasterBroadcast;
use App\Models\DisasterEvent;
use App\Models\Household;
use App\Models\Responder;
use App\Models\ResponderAssignment;
use App\Models\ResourceRequest;
use App\Models\SituationReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class GlobalSearchService
{
    public const TYPES = [
        'events',
        'broadcasts',
        'households',
        'responders',
        'dispatches',
        'resources',
        'sitreps',
    ];

    private const RESULT_LIMIT = 6;

    public function search(string $term, array $types): array
    {
        $term = trim($term);
        $results = [];

        if (in_array('events', $types, true) && Schema::hasTable('disaster_events')) {
            $results = array_merge($results, $this->searchEvents($term));
        }

        if (in_array('broadcasts', $types, true) && Schema::hasTable('disaster_broadcasts')) {
            $results = array_merge($results, $this->searchBroadcasts($term));
        }

        if (in_array('households', $types, true) && Schema::hasTable('households')) {
            $results = array_merge($results, $this->searchHouseholds($term));
        }

        if (in_array('responders', $types, true) && Schema::hasTable('responders')) {
            $results = array_merge($results, $this->searchResponders($term));
        }

        if (in_array('dispatches', $types, true) && Schema::hasTable('responder_assignments')) {
            $results = array_merge($results, $this->searchDispatches($term));
        }

        if (in_array('resources', $types, true) && Schema::hasTable('resource_requests')) {
            $results = array_merge($results, $this->searchResourceRequests($term));
        }

        if (in_array('sitreps', $types, true) && Schema::hasTable('situation_reports')) {
            $results = array_merge($results, $this->searchSituationReports($term));
        }

        return $results;
    }

    private function searchEvents(string $term): array
    {
        return $this->matches(DisasterEvent::query()->with('type')->whereNull('deleted_at'), [
            'event_id',
            'name',
        ], $term)
            ->orderByDesc('started_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (DisasterEvent $event): array => $this->result(
                'events',
                $event->name ?: $event->event_id,
                trim($event->event_id.' · '.($event->type?->type_name ?? 'Disaster event')),
                $event->ended_at
                    ? '/archive?search='.rawurlencode($event->event_id)
                    : '/broadcast?event_id='.rawurlencode($event->event_id)
            ))
            ->all();
    }

    private function searchBroadcasts(string $term): array
    {
        return $this->matches(DisasterBroadcast::query()->with('disaster'), [
            'broadcast_title',
            'message',
            'disaster_id',
        ], $term)
            ->orderByDesc('sent_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(function (DisasterBroadcast $broadcast): array {
                $event = $broadcast->disaster;
                $eventId = $broadcast->disaster_id;
                $href = $event && ! $event->ended_at
                    ? '/broadcast?event_id='.rawurlencode((string) $eventId)
                    : '/archive?search='.rawurlencode((string) $eventId);

                return $this->result(
                    'broadcasts',
                    $broadcast->broadcast_title ?: 'Disaster broadcast',
                    trim(($event?->name ?? $eventId ?? 'Disaster event').' · '.$broadcast->sent_at?->format('M j, Y')),
                    $href
                );
            })
            ->all();
    }

    private function searchHouseholds(string $term): array
    {
        return Household::query()
            ->whereNull('deleted_at')
            ->search($term)
            ->orderBy('household_name')
            ->limit(self::RESULT_LIMIT)
            ->get(['household_id', 'household_code', 'household_name', 'contact_number'])
            ->map(fn (Household $household): array => $this->result(
                'households',
                $household->household_name ?: $household->household_code ?: $household->household_id,
                trim(($household->household_code ?? $household->household_id).' · '.($household->contact_number ?? 'Household')),
                '/households?search='.rawurlencode((string) ($household->household_code ?: $household->household_id))
            ))
            ->all();
    }

    private function searchResponders(string $term): array
    {
        return $this->matches(Responder::query()->whereNull('deleted_at'), [
            'responder_code',
            'full_name',
            'username',
            'contact_number',
        ], $term)
            ->orderBy('full_name')
            ->limit(self::RESULT_LIMIT)
            ->get(['responder_id', 'responder_code', 'full_name', 'contact_number', 'duty_status'])
            ->map(fn (Responder $responder): array => $this->result(
                'responders',
                $responder->full_name ?: $responder->responder_code,
                trim(($responder->responder_code ?? '').' · '.($responder->duty_status ?? $responder->contact_number ?? 'Responder')),
                '/rescuers'
            ))
            ->all();
    }

    private function searchDispatches(string $term): array
    {
        return $this->matches(ResponderAssignment::query()->with(['responder', 'team']), [
            'assignment_code',
            'assigned_area',
            'route_notes',
            'dispatch_notes',
            'disaster_id',
        ], $term)
            ->orderByDesc('assigned_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (ResponderAssignment $assignment): array => $this->result(
                'dispatches',
                $assignment->assignment_code ?: $assignment->assigned_area ?: 'Rescue dispatch',
                trim(($assignment->team?->team_name ?? $assignment->responder?->full_name ?? 'Response team').' · '.($assignment->status ?? 'Assigned')),
                '/dispatch'
            ))
            ->all();
    }

    private function searchResourceRequests(string $term): array
    {
        return $this->matches(ResourceRequest::query(), [
            'request_id',
            'source_reference',
            'resource_type',
            'item_name',
            'description',
            'tracking_reference',
        ], $term)
            ->orderByDesc('created_at')
            ->limit(self::RESULT_LIMIT)
            ->get(['request_id', 'source_reference', 'resource_type', 'item_name', 'validation_status'])
            ->map(fn (ResourceRequest $request): array => $this->result(
                'resources',
                $request->item_name ?: $request->resource_type ?: $request->request_id,
                trim(($request->request_id ?? '').' · '.($request->validation_status ?? 'Resource request')),
                '/resources-requests'
            ))
            ->all();
    }

    private function searchSituationReports(string $term): array
    {
        return $this->matches(SituationReport::query(), [
            'report_number',
            'disaster_id',
            'escalated_to',
        ], $term)
            ->orderByDesc('generated_at')
            ->limit(self::RESULT_LIMIT)
            ->get(['sit_rep_id', 'report_number', 'disaster_id', 'report_status'])
            ->map(fn (SituationReport $report): array => $this->result(
                'sitreps',
                $report->report_number ?: 'Situation report '.$report->sit_rep_id,
                trim(($report->disaster_id ?? 'No linked event').' · '.($report->report_status ?? 'SitRep')),
                '/situation'
            ))
            ->all();
    }

    private function matches(Builder $query, array $columns, string $term): Builder
    {
        $pattern = '%'.$term.'%';

        return $query->where(function (Builder $search) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $search->{$method}($column, 'like', $pattern);
            }
        });
    }

    private function result(string $type, string $title, string $subtitle, string $href): array
    {
        return compact('type', 'title', 'subtitle', 'href');
    }
}