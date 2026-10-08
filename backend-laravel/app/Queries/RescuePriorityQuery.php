<?php

namespace App\Queries;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\RescuePrioritySetting;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RescuePriorityQuery
{
    public function ranked(string $eventId, int $perPage = 15): object
    {
        [$query, $settings] = $this->scored($eventId);

        return $query->orderByDesc('urgent_tier')->orderByDesc('priority_score')
            ->orderBy('last_reported_at')->orderBy('household_id')
            ->paginate(min(100, max(1, $perPage)))
            ->through(function (object $row) use ($settings): object {
                $row->priority_score = round((float) $row->priority_score, 2);
                $row->settings_version = (int) $settings->version;
                return $row;
            });
    }

    public function scored(string $eventId, int|string|null $barangayId = null): array
    {
        $settings = $this->settingsForEvent($eventId);
        $minorPredicate = '';
        if (Schema::hasColumn('household_members', 'birth_date') && Schema::hasColumn('disaster_events', 'started_at')) {
            $eventStartedAt = DB::table('disaster_events')->where('event_id', $eventId)->value('started_at');
            if ($eventStartedAt) {
                $minorCutoff = Carbon::parse($eventStartedAt)->subYears(18)->toDateString();
                $minorPredicate = " OR (area_member.birth_date IS NOT NULL AND area_member.birth_date > '{$minorCutoff}')";
            }
        }

        $areaImpact = Household::query()->from('households as area_household')
            ->join('addresses as area_address', 'area_address.address_id', '=', 'area_household.address_id')
            ->leftJoin('household_disasters as area_disaster', fn ($join) => $join
                ->on('area_disaster.household_id', '=', 'area_household.household_id')
                ->where('area_disaster.disaster_id', $eventId))
            ->leftJoin('household_statuses as area_status', 'area_status.status_id', '=', 'area_disaster.current_status_id')
            ->whereNull('area_household.deleted_at')
            ->when($barangayId !== null, fn ($query) => $query->where('area_address.barangay_id', $barangayId))
            ->groupBy('area_address.purok_sitio')
            ->selectRaw('area_address.purok_sitio as area_name, COUNT(DISTINCT area_household.household_id) as total_households')
            ->selectRaw("COUNT(DISTINCT CASE WHEN area_status.status_key IN ('unsafe', 'needs_help', 'needs_assistance') THEN area_household.household_id END) as impacted_households");

        $areaVulnerability = HouseholdMember::query()->withoutGlobalScopes()->from('household_members as area_member')
            ->join('households as member_household', 'member_household.household_id', '=', 'area_member.household_id')
            ->join('addresses as member_address', 'member_address.address_id', '=', 'member_household.address_id')
            ->leftJoin('member_vulnerable_groups as member_group', 'member_group.member_id', '=', 'area_member.member_id')
            ->leftJoin('member_disaster_statuses as vulnerable_report', fn ($join) => $join
                ->on('vulnerable_report.member_id', '=', 'area_member.member_id')
                ->where('vulnerable_report.disaster_id', $eventId))
            ->leftJoin('member_statuses as vulnerable_status', 'vulnerable_status.status_id', '=', 'vulnerable_report.status_id')
            ->whereNull('area_member.deleted_at')->whereNull('member_household.deleted_at')
            ->when($barangayId !== null, fn ($query) => $query->where('member_address.barangay_id', $barangayId))
            ->groupBy('member_address.purok_sitio')
            ->selectRaw('member_address.purok_sitio as area_name, COUNT(DISTINCT area_member.member_id) as total_members')
            ->selectRaw("COUNT(DISTINCT CASE WHEN area_member.is_pwd = 1 OR area_member.is_senior = 1 OR area_member.is_pregnant = 1 OR member_group.member_id IS NOT NULL{$minorPredicate} THEN area_member.member_id END) as total_vulnerable_members")
            ->selectRaw("COUNT(DISTINCT CASE WHEN (area_member.is_pwd = 1 OR area_member.is_senior = 1 OR area_member.is_pregnant = 1 OR member_group.member_id IS NOT NULL{$minorPredicate})
                AND (vulnerable_status.status_key IS NULL OR vulnerable_status.status_key NOT IN ('safe', 'safe_at_home', 'evacuated'))
                THEN area_member.member_id END) as vulnerable_members");

        $memberReports = HouseholdMember::query()->withoutGlobalScopes()->from('household_members as report_member')
            ->leftJoin('member_disaster_statuses as member_report', fn ($join) => $join
                ->on('member_report.member_id', '=', 'report_member.member_id')
                ->where('member_report.disaster_id', $eventId))
            ->leftJoin('member_statuses as report_status', 'report_status.status_id', '=', 'member_report.status_id')
            ->whereNull('report_member.deleted_at')->groupBy('report_member.household_id')
            ->selectRaw('report_member.household_id, COUNT(DISTINCT report_member.member_id) as total_members')
            ->selectRaw("COUNT(DISTINCT CASE WHEN report_status.status_key IS NULL OR report_status.status_key IN ('unknown', 'unchecked', 'unreported') THEN report_member.member_id END) as unreported_members");

        $missingContact = $this->contactMissingExpression();
        $noContact = "CASE WHEN hd.last_reported_at IS NULL
            AND (hs.status_key IS NULL OR hs.status_key NOT IN ('safe', 'safe_at_home', 'evacuated', 'returned', 'relocated'))
            AND ({$missingContact}) = 1 THEN 1 ELSE 0 END";

        $base = Household::query()->from('households as h')
            ->leftJoin('addresses as a', 'a.address_id', '=', 'h.address_id')
            ->leftJoin('household_disasters as hd', fn ($join) => $join
                ->on('hd.household_id', '=', 'h.household_id')->where('hd.disaster_id', $eventId))
            ->leftJoin('household_statuses as hs', 'hs.status_id', '=', 'hd.current_status_id')
            ->leftJoinSub($areaImpact, 'area_impact', fn ($join) => $join->on('area_impact.area_name', '=', 'a.purok_sitio'))
            ->leftJoinSub($areaVulnerability, 'area_vulnerability', fn ($join) => $join->on('area_vulnerability.area_name', '=', 'a.purok_sitio'))
            ->leftJoinSub($memberReports, 'member_reports', fn ($join) => $join->on('member_reports.household_id', '=', 'h.household_id'))
            ->whereNull('h.deleted_at')
            ->when($barangayId !== null, fn ($query) => $query->where('a.barangay_id', $barangayId))
            ->select(['h.household_id', 'h.household_code', 'h.household_name', 'a.purok_id', 'a.purok_sitio as area_name',
                'hs.status_key', 'hd.last_reported_at'])
            ->selectRaw("CASE WHEN hd.needs_dispatch = 1 OR hs.status_key IN ('unsafe', 'needs_help') THEN 1 ELSE 0 END as urgent_tier")
            ->selectRaw('COALESCE(area_impact.impacted_households, 0) as impacted_households, COALESCE(area_impact.total_households, 0) as area_households')
            ->selectRaw('COALESCE(area_vulnerability.vulnerable_members, 0) as vulnerable_members, COALESCE(area_vulnerability.total_vulnerable_members, 0) as total_vulnerable_members, COALESCE(area_vulnerability.total_members, 0) as area_members')
            ->selectRaw('COALESCE(member_reports.unreported_members, 0) as unreported_members, COALESCE(member_reports.total_members, 0) as household_members')
            ->selectRaw($missingContact.' as contact_missing')
            ->selectRaw($noContact.' as no_contact_channel');

        $query = DB::query()->fromSub($base, 'priority_base')
            ->select('priority_base.*')
            ->selectRaw('( ? * 1.0 * impacted_households / CASE WHEN area_households < 1 THEN 1 ELSE area_households END
                + ? * 1.0 * vulnerable_members / CASE WHEN total_vulnerable_members < 1 THEN 1 ELSE total_vulnerable_members END
                + ? * 1.0 * unreported_members / CASE WHEN household_members < 1 THEN 1 ELSE household_members END
                + ? * no_contact_channel) as priority_score', [
                $settings->impact_weight,
                $settings->vulnerability_weight,
                $settings->unreported_weight,
                $settings->no_contact_weight,
            ]);

        return [$query, $settings];
    }

    public function settingsForEvent(string $eventId): RescuePrioritySetting
    {
        $version = Schema::hasColumn('disaster_events', 'rescue_priority_version')
            ? DB::table('disaster_events')->where('event_id', $eventId)->value('rescue_priority_version')
            : null;

        return ($version ? RescuePrioritySetting::query()->find($version) : null)
            ?? RescuePrioritySetting::query()->orderByDesc('version')->firstOrFail();
    }

    public function contactMissingExpression(): string
    {
        $hasDevices = Schema::hasTable('device_tokens') && Schema::hasColumn('device_tokens', 'household_id');
        $active = $hasDevices && Schema::hasColumn('device_tokens', 'is_active') ? ' AND dt.is_active = 1' : '';
        $ownDevice = $hasDevices ? "EXISTS (SELECT 1 FROM device_tokens dt WHERE dt.household_id = h.household_id{$active})" : '0 = 1';
        $trustedDevice = $hasDevices ? "EXISTS (SELECT 1 FROM device_tokens dt WHERE dt.household_id = trusted.household_id{$active})" : '0 = 1';

        return "CASE WHEN (h.contact_number IS NULL OR TRIM(h.contact_number) = '')
            AND NOT ({$ownDevice})
            AND NOT EXISTS (
                SELECT 1 FROM trusted_households trust JOIN households trusted
                ON ((trust.requesting_household_id = h.household_id AND trusted.household_id = trust.trusted_household_id)
                    OR (trust.trusted_household_id = h.household_id AND trusted.household_id = trust.requesting_household_id))
                WHERE trust.validation_status IN ('validated', 'approved') AND trusted.deleted_at IS NULL
                AND ((trusted.contact_number IS NOT NULL AND TRIM(trusted.contact_number) <> '') OR {$trustedDevice})
            ) THEN 1 ELSE 0 END";
    }
}
