<?php

namespace App\Jobs;

use App\Models\MemberDisasterStatus;
use App\Models\StatusReminder;
use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendStatusReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public int $reminderId) {}

    public function handle(OneSignalNotificationService $push): void
    {
        $reminder = DB::transaction(function (): ?StatusReminder {
            $row = StatusReminder::query()->lockForUpdate()->find($this->reminderId);
            if (! $row || $row->status !== 'pending') return null;

            if (MemberDisasterStatus::query()->where('disaster_id', $row->event_id)
                ->where('member_id', $row->member_id)->exists()) {
                $row->update(['status' => 'cancelled', 'stopped_at' => now()]);
                return null;
            }

            $row->update(['status' => 'sending']);
            return $row->load('member');
        }, 3);

        if (! $reminder) return;

        $data = [
            'type' => 'member_status_check_in',
            'event_id' => $reminder->event_id,
            'member_id' => $reminder->member_id,
            'household_id' => $reminder->household_id,
            'options' => trans('status_reminders.options'),
            'requires_selection' => true,
        ];
        $memberDelivery = $push->sendToMobileDevices(
            trans('status_reminders.title'), trans('status_reminders.member'), [
                'roles' => ['household'], 'household_ids' => [$reminder->household_id],
                'member_ids' => [$reminder->member_id], 'data' => $data,
            ]);
        $name = trim(($reminder->member?->first_name ?? '').' '.($reminder->member?->last_name ?? ''));
        $householdDelivery = $push->sendToMobileDevices(
            trans('status_reminders.title'), trans('status_reminders.household', ['name' => $name ?: 'A household member']), [
                'roles' => ['household'], 'household_ids' => [$reminder->household_id],
                'exclude_member_ids' => [$reminder->member_id], 'data' => $data,
            ]);

        $failed = in_array('failed', [$memberDelivery['status'], $householdDelivery['status']], true);
        StatusReminder::query()->whereKey($reminder->getKey())->where('status', 'sending')->update([
            'status' => $failed ? 'pending' : 'sent',
            'sent_at' => $failed ? null : now(),
            'member_delivery' => $memberDelivery['status'],
            'household_delivery' => $householdDelivery['status'],
        ]);

        if ($failed) throw new \RuntimeException('Status check-in push failed; retry requested.');
    }
}
