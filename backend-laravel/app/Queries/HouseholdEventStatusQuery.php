<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

class HouseholdEventStatusQuery
{
    public const UNSAFE_KEYS = ['unsafe', 'needs_help', 'need_help', 'needs_assistance', 'not_evacuated', 'displaced', 'missing', 'injured', 'trapped', 'unreachable', 'deceased'];
    public const SAFE_KEYS = ['safe', 'safe_at_home', 'active', 'returned'];
    public const EVACUATED_KEYS = ['evacuated', 'relocated'];
    public const VALID_KEYS = [...self::UNSAFE_KEYS, ...self::SAFE_KEYS, ...self::EVACUATED_KEYS];

    public static function latestReports(?string $eventId)
    {
        return DB::table('household_status_logs as report')
            ->join('household_statuses as valid_status', 'valid_status.status_id', '=', 'report.status_id')
            ->where('report.disaster_id', $eventId)
            ->whereIn('valid_status.status_key', self::VALID_KEYS)
            ->whereNotExists(function ($newer): void {
                $newer->selectRaw('1')->from('household_status_logs as newer')
                    ->join('household_statuses as newer_status', 'newer_status.status_id', '=', 'newer.status_id')
                    ->whereColumn('newer.household_id', 'report.household_id')
                    ->whereColumn('newer.disaster_id', 'report.disaster_id')
                    ->whereIn('newer_status.status_key', self::VALID_KEYS)
                    ->where(function ($time): void {
                        $time->whereColumn('newer.submitted_at', '>', 'report.submitted_at')
                            ->orWhere(fn ($tie) => $tie->whereColumn('newer.submitted_at', 'report.submitted_at')
                                ->whereColumn('newer.status_log_id', '>', 'report.status_log_id'));
                    });
            })->select('report.household_id', 'report.status_id', 'report.status_log_id');
    }

    public static function category(?string $key): string
    {
        if (in_array($key, self::UNSAFE_KEYS, true)) return 'unsafe';
        if (in_array($key, self::SAFE_KEYS, true)) return 'safe';
        if (in_array($key, self::EVACUATED_KEYS, true)) return 'evacuated';
        return 'unchecked';
    }
}
