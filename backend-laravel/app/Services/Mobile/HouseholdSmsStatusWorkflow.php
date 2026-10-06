<?php

namespace App\Services\Mobile;

use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Facades\DB;

class HouseholdSmsStatusWorkflow
{
    public function __construct(private HouseholdMobileSupport $support, private HouseholdMobileReadQuery $readQuery, private HouseholdMobilePresenter $presenter, private HouseholdMobileStatusWriter $writer) {}

    public function storeStatusFromSms(string $householdCode, string $statusKey, string $from, string $rawText): array
    {
        foreach (['households', 'household_status_logs', 'household_statuses', 'household_disasters'] as $table) {
            if (! Schema::hasTable($table)) {
                return ['ok' => false, 'reason' => 'missing_table'];
            }
        }

        if (! Schema::hasColumn('households', 'household_code') || ! Schema::hasColumn('households', 'contact_number')) {
            return ['ok' => false, 'reason' => 'household_sms_contact_not_configured'];
        }

        $household = DB::table('households')->where('household_code', trim($householdCode))
            ->whereNotNull('contact_number')->first(['household_id', 'contact_number']);
        if (! $household) {
            return ['ok' => false, 'reason' => 'household_not_found'];
        }

        if ($this->normalizeSmsPhone($from) === '' || $this->normalizeSmsPhone($from) !== $this->normalizeSmsPhone((string) $household->contact_number)) {
            return ['ok' => false, 'reason' => 'sender_does_not_match_household_contact'];
        }

        $activeEvent = $this->readQuery->activeEvent();
        if (! $activeEvent) {
            return ['ok' => false, 'reason' => 'no_active_event'];
        }

        if (! in_array($statusKey, ['safe', 'evacuated', 'unsafe', 'needs_help'], true)) {
            return ['ok' => false, 'reason' => 'invalid_status_key'];
        }

        $status = $this->readQuery->resolveStatus($statusKey);
        if (! $status) {
            return ['ok' => false, 'reason' => 'status_not_configured'];
        }

        $now = now();
        $notes = $this->presenter->statusNotesJson($statusKey, mb_substr($rawText, 0, 500));
        $validated = [
            'status_key' => $statusKey,
            'status_source' => 'sms',
            'notes' => $notes,
            'latitude' => null,
            'longitude' => null,
            'battery_level' => null,
        ];

        $statusLogId = DB::transaction(function () use ($household, $activeEvent, $status, $validated, $now): int {
            $data = $this->support->filterColumns('household_status_logs', [
                'disaster_id' => $activeEvent['event_id'],
                'household_id' => $household->household_id,
                'status_id' => $status['status_id'],
                'source' => 'sms',
                'notes' => $validated['notes'],
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = DB::table('household_status_logs')->insertGetId($data, 'status_log_id');
            $this->writer->saveLatestDisasterStatus($activeEvent['event_id'], (string) $household->household_id,
                (int) $status['status_id'], $validated, null, null, $now);
            return (int) $id;
        });

        return ['ok' => true, 'household_id' => (string) $household->household_id,
            'status_log_id' => $statusLogId, 'status_key' => $statusKey];
    }

    private function normalizeSmsPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '63'.$digits;
        }
        return $digits;
    }

}
