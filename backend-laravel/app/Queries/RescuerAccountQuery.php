<?php

namespace App\Queries;

use App\Http\Requests\ListRequest;
use App\Presenters\RescuerAccountPresenter;
use App\Services\Mobile\RescuerAccountSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class RescuerAccountQuery
{
    public function __construct(private RescuerAccountSupport $support, private RescuerAccountPresenter $presenter) {}

    public function paginateResponders(array $filters, int $perPage)
    {
        $query = $this->responderQuery();
        $search = $filters['search'];
        $team = $filters['team'];
        $dutyStatus = $filters['duty_status'];
        $purok = $filters['purok'];
        if (($filters['account_type'] ?? 'all') === 'rescuer') {
            $query->where('rt.team_code', '<>', 'HQCC');
        } elseif (($filters['account_type'] ?? 'all') === 'command_center') {
            $query->where('rt.team_code', 'HQCC');
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('r.full_name', 'like', "%{$search}%")->orWhere('r.responder_code', 'like', "%{$search}%")
                    ->orWhere('r.username', 'like', "%{$search}%")->orWhere('r.title', 'like', "%{$search}%")
                    ->orWhere('r.skills', 'like', "%{$search}%")->orWhere('rt.team_name', 'like', "%{$search}%")
                    ->orWhere('rt.team_code', 'like', "%{$search}%")->orWhere('rt.team_type', 'like', "%{$search}%");
            });
        }
        if ($team !== '' && $team !== 'all') {
            $query->where(fn ($inner) => $inner->where('rt.team_name', $team)->orWhere('rt.team_code', $team)->orWhere('rt.team_type', $team)->orWhere('r.team_id', $team));
        }
        if ($dutyStatus !== '' && $dutyStatus !== 'all') {
            if ($dutyStatus === 'training_due') {
                $query->where(fn ($inner) => $inner->where('r.training_notes', 'like', '%due%')->orWhere('r.certification_reference', 'like', '%due%')->orWhere('r.certification_reference', 'like', '%expired%'));
            } elseif ($dutyStatus === 'active') {
                $query->where('u.is_active', 1);
            } elseif ($dutyStatus === 'disabled') {
                $query->where(fn ($inner) => $inner->where('u.is_active', 0)->orWhere('r.duty_status', 'disabled'));
            } else {
                $query->where('r.duty_status', $dutyStatus);
            }
        }
        if ($purok !== '' && $purok !== 'all') {
            $query->where('r.address', 'like', "%{$purok}%");
        }
        return $query->orderBy('r.full_name')->paginate(ListRequest::clampPerPage($perPage));
    }

    public function responderQuery()
    {
        return DB::table('responders as r')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user_id')
            ->leftJoin('roles as role', 'role.role_id', '=', 'u.role_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')
            ->whereNull('r.deleted_at')
            ->select(['r.*', 'u.email', 'u.first_name as user_first_name', 'u.last_name as user_last_name',
                'u.name as user_full_name', 'u.username as user_username', 'u.is_active', 'u.must_change_password',
                'role.role_key', 'role.role_id as access_role_id', 'role.role_name as access_role_name', 'rt.team_name', 'rt.team_code', 'rt.team_type']);
    }

    public function findResponder(int $responderId): ?object
    {
        return $this->responderQuery()->where('r.responder_id', $responderId)->first();
    }
    public function teamConfigWorkspace(?int $selectedTeamId = null): array
    {
        return [
            'selected_team_id' => $selectedTeamId,
            'teams' => $this->teamConfigCards(),
            'responders' => $this->teamConfigResponders(),
            'puroks' => $this->purokAddressOptions(),
            'team_types' => $this->teamTypeOptions(),
            'duty_statuses' => $this->presenter->statusOptions(config('rescuers.team_duty_statuses')),
            'form_defaults' => config('rescuers.team_defaults'),
            'form_constraints' => app(\App\Http\Requests\RescueTeamPayloadValidator::class)->formConstraints(),
            'members_per_page' => config('rescuers.members_per_page'),
            'note' => 'Team configuration uses the existing rescue_teams table. Deleting a team never deletes rescuer accounts.',
        ];
    }

    public function teamConfigCards(): array
    {
        $teams = DB::table('rescue_teams as rt')
            ->leftJoin('responders as leader', 'leader.responder_id', '=', 'rt.leader_responder_id')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'rt.assigned_purok_id')
            ->orderBy('rt.team_name')
            ->get([
                'rt.team_id',
                'rt.team_code',
                'rt.team_name',
                'rt.team_type',
                'rt.assigned_purok_id',
                'rt.leader_responder_id',
                'rt.duty_status',
                'leader.full_name as leader_name',
                'a.purok_sitio',
            ]);
        $membersByTeam = DB::table('responders')
            ->whereNotNull('team_id')
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['responder_id', 'team_id', 'full_name', 'title', 'duty_status', 'is_deployed'])
            ->groupBy('team_id');
        $activeDispatchesByTeam = DB::table('responder_assignments')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->select('team_id')
            ->selectRaw('COUNT(*) as active_dispatch_count')
            ->groupBy('team_id')
            ->pluck('active_dispatch_count', 'team_id');

        $databaseTeams = $teams->map(fn (object $team): array => $this->formatTeamConfigCard(
            $team,
            'database',
            $membersByTeam->get($team->team_id, collect()),
            (int) ($activeDispatchesByTeam[$team->team_id] ?? 0),
        ));

        return $databaseTeams->sortBy('team_name')->values()->all();
    }

    public function formatTeamConfigCard(
        object $team,
        string $source,
        ?Collection $members = null,
        ?int $activeDispatchCount = null,
    ): array
    {
        $members ??= $team->team_id
            ? DB::table('responders')
                ->where('team_id', $team->team_id)
                ->whereNull('deleted_at')
                ->orderBy('full_name')
                ->get(['responder_id', 'full_name', 'title', 'duty_status', 'is_deployed'])
            : collect();

        $activeDispatchCount ??= $team->team_id
            ? DB::table('responder_assignments')
                ->where('team_id', $team->team_id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count()
            : 0;

        return [
            'team_id' => $team->team_id,
            'team_code' => $team->team_code,
            'team_name' => $team->team_name,
            'team_type' => $team->team_type,
            'assigned_purok_id' => $team->assigned_purok_id,
            'assigned_purok' => $team->purok_sitio,
            'barangay_name' => null,
            'leader_responder_id' => $team->leader_responder_id,
            'leader_name' => $team->leader_name,
            'duty_status' => $team->duty_status,
            'duty_status_display' => $this->presenter->formatStatus($team->duty_status),
            'source' => $source,
            'is_configured' => $source === 'database',
            'can_delete' => $source === 'database' && $activeDispatchCount === 0
                && ! $members->contains(fn (object $member): bool => $this->presenter->membershipPresentation($member)['is_busy']),
            'active_dispatch_count' => $activeDispatchCount,
            'member_count' => $members->count(),
            'deployed_count' => $members->where('is_deployed', 1)->count(),
            'member_ids' => $members->pluck('responder_id')->map(fn ($id): int => (int) $id)->values()->all(),
            'members' => $members->map(fn (object $member): array => [
                'responder_id' => (int) $member->responder_id,
                'full_name' => $member->full_name,
                'title' => $member->title,
                'duty_status' => $member->duty_status,
                'is_deployed' => (bool) $member->is_deployed,
            ])->values()->all(),
        ];
    }

    public function teamConfigResponders(): array
    {
        return DB::table('responders as r')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user_id')
            ->leftJoin('roles as role', 'role.role_id', '=', 'u.role_id')
            ->leftJoin('rescue_teams as rt', 'rt.team_id', '=', 'r.team_id')
            ->whereNull('r.deleted_at')
            ->orderBy('r.full_name')
            ->get([
                'r.responder_id',
                'r.full_name',
                'r.username',
                'r.title',
                'r.team_id',
                'r.duty_status',
                'r.is_deployed',
                'rt.team_name',
                'rt.team_code',
                'role.role_key',
                'role.role_name',
            ])
            ->map(function (object $responder): array {
                $roleKey = strtolower((string) $responder->role_key);
                $isCommitteeRole = in_array($roleKey, [
                    'chair',
                    'operations_admin',
                    'mitigation_prevention',
                    'preparedness',
                    'response',
                    'recovery_rehabilitation',
                ], true);

                return [
                    'responder_id' => (int) $responder->responder_id,
                    'full_name' => $responder->full_name ?: 'Unnamed rescuer',
                    'title' => $responder->role_name ?: ($responder->title ?: 'Team Member'),
                    'role_key' => $roleKey ?: null,
                    'role_name' => $responder->role_name,
                    'account_type' => $isCommitteeRole || strtoupper((string) $responder->team_code) === 'HQCC'
                        ? 'command_center'
                        : 'rescuer',
                    'team_id' => $responder->team_id ? (int) $responder->team_id : null,
                    'team_name' => $responder->team_name ?: 'Unassigned',
                    'team_code' => $responder->team_code,
                    'duty_status' => $responder->duty_status,
                    'is_deployed' => (bool) $responder->is_deployed,
                    ...$this->presenter->membershipPresentation($responder),
                ];
            })
            ->values()
            ->all();
    }

    public function purokAddressOptions(): array
    {
        return DB::table('addresses')
            ->whereNull('deleted_at')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->groupBy('purok_sitio')
            ->orderBy('purok_sitio')
            ->get([
                DB::raw('MIN(address_id) as address_id'),
                'purok_sitio',
            ])
            ->map(fn (object $row): array => [
                'address_id' => (int) $row->address_id,
                'label' => $row->purok_sitio,
            ])
            ->values()
            ->all();
    }

    public function teamTypeOptions(): array
    {
        $databaseTypes = DB::table('rescue_teams')
            ->whereNotNull('team_type')
            ->where('team_type', '<>', '')
            ->pluck('team_type');

        return $databaseTypes
            ->push('HQCC')
            ->push('Mitigation and Prevention Sub-committee')
            ->push('Preparedness Sub-committee')
            ->push('Response Sub-committee')
            ->push('Recovery and Rehabilitation Sub-committee')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function purokOptions(): array
    {
        $puroks = DB::table('addresses')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->select('purok_sitio')
            ->distinct()
            ->orderBy('purok_sitio')
            ->pluck('purok_sitio')
            ->values()
            ->all();

        return $puroks;
    }

    public function viewData(): array
    {
        return [
            'summary' => $this->summary(),
            'teams' => $this->teamCards(),
            'team_options' => $this->teamOptions(),
            'puroks' => $this->purokOptions(),
        ];
    }

    public function summary(): array
    {
        $counts = DB::table('responders as r')
            ->leftJoin('users as u', 'u.user_id', '=', 'r.user_id')
            ->whereNull('r.deleted_at')
            ->selectRaw("COUNT(*) as registered,
                SUM(CASE WHEN r.duty_status IN ('on_duty','available','dispatched','on_scene') AND u.is_active = 1 THEN 1 ELSE 0 END) as on_duty,
                SUM(CASE WHEN r.is_deployed = 1 OR r.duty_status IN ('dispatched','on_scene') THEN 1 ELSE 0 END) as deployed,
                SUM(CASE WHEN LOWER(COALESCE(r.training_notes, '')) LIKE '%due%'
                    OR LOWER(COALESCE(r.training_notes, '')) LIKE '%expired%'
                    OR LOWER(COALESCE(r.training_notes, '')) LIKE '%refresh%'
                    OR LOWER(COALESCE(r.certification_reference, '')) LIKE '%due%'
                    OR LOWER(COALESCE(r.certification_reference, '')) LIKE '%expired%'
                    OR LOWER(COALESCE(r.certification_reference, '')) LIKE '%refresh%'
                    THEN 1 ELSE 0 END) as training_due")
            ->first();
        return [
            'registered' => (int) ($counts->registered ?? 0),
            'on_duty' => (int) ($counts->on_duty ?? 0),
            'deployed' => (int) ($counts->deployed ?? 0),
            'training_due' => (int) ($counts->training_due ?? 0),
        ];
    }

    public function teamCards(): array
    {
        $activeAssignments = DB::table('responder_assignments as ra')
            ->selectRaw('COUNT(*)')->whereColumn('ra.team_id', 'rt.team_id')
            ->whereNotIn('ra.status', ['completed', 'cancelled']);
        $teams = DB::table('rescue_teams as rt')
            ->leftJoin('responders as r', function ($join): void {
                $join->on('r.team_id', '=', 'rt.team_id')->whereNull('r.deleted_at');
            })
            ->groupBy('rt.team_id', 'rt.team_code', 'rt.team_name', 'rt.team_type', 'rt.duty_status')
            ->orderBy('rt.team_name')
            ->select([
                'rt.team_id', 'rt.team_code', 'rt.team_name', 'rt.team_type', 'rt.duty_status',
                DB::raw('COUNT(r.responder_id) as member_count'),
                DB::raw('SUM(CASE WHEN r.is_deployed = 1 THEN 1 ELSE 0 END) as deployed_count'),
            ])->selectSub($activeAssignments, 'active_dispatch_count')->get();

        return $teams->map(fn (object $team): array => [
            'team_id' => $team->team_id,
            'team_code' => $team->team_code,
            'team_name' => $team->team_name,
            'team_type' => $team->team_type,
            'duty_status' => $team->duty_status,
            'duty_status_display' => $this->presenter->formatStatus($team->duty_status),
            'active_dispatch_count' => (int) $team->active_dispatch_count,
            'member_count' => (int) $team->member_count,
            'deployed_count' => (int) $team->deployed_count,
        ])->values()->all();
    }

    public function teamOptions(): array
    {
        return DB::table('rescue_teams')->orderBy('team_name')
            ->get(['team_id', 'team_code', 'team_name', 'team_type'])
            ->map(fn (object $team): array => [
                'team_id' => $team->team_id, 'team_code' => $team->team_code,
                'team_name' => $team->team_name, 'team_type' => $team->team_type, 'source' => 'database',
            ])->values()->all();
    }

    public function accountIdChoices(array $teamOptions): array
    {
        $codes = collect($teamOptions)
            ->map(fn (array $team): string => $this->support->normalizeTeamCode(
                $team['team_code'] ?? $this->support->teamCode($team['team_name']),
            ))
            ->push('SRR')
            ->unique()
            ->values()
            ->all();
        $nextIds = $this->support->nextAccountIds($codes);
        $options = collect($teamOptions)->map(function (array $team) use ($nextIds): array {
            $code = $this->support->normalizeTeamCode($team['team_code'] ?? $this->support->teamCode($team['team_name']));
            return ['team_name' => $team['team_name'], 'team_code' => $code, 'account_id' => $nextIds[$code]];
        })->values()->all();
        return ['options' => $options, 'default' => $nextIds['SRR']];
    }


}
