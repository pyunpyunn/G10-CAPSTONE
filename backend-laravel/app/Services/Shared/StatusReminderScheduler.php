<?php

namespace App\Services\Shared;

use App\Jobs\SendStatusReminder;
use App\Models\DisasterEvent;
use App\Models\StatusReminder;
use App\Queries\UnreportedMemberQuery;
use Illuminate\Support\Carbon;

class StatusReminderScheduler
{
    public function __construct(private UnreportedMemberQuery $members) {}

    public function scheduleDue(): int
    {
        // A worker can be terminated after claiming a reminder. Return stale
        // claims to the queue so the same attempt can be delivered again.
        StatusReminder::query()->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->orderBy('reminder_id')->chunkById(200, function ($reminders): void {
                foreach ($reminders as $reminder) {
                    if (! StatusReminder::query()->whereKey($reminder->getKey())
                        ->where('status', 'sending')->update(['status' => 'pending'])) continue;
                    SendStatusReminder::dispatch((int) $reminder->reminder_id)
                        ->onConnection('operations_outbox')->onQueue('operations');
                }
            }, 'reminder_id');
        StatusReminder::query()->where('status', 'pending')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->orderBy('reminder_id')->chunkById(200, function ($reminders): void {
                foreach ($reminders as $reminder) {
                    SendStatusReminder::dispatch((int) $reminder->reminder_id)
                        ->onConnection('operations_outbox')->onQueue('operations');
                    $reminder->touch();
                }
            }, 'reminder_id');
        $event = DisasterEvent::query()->whereNull('ended_at')->orderByDesc('started_at')->first();
        if (! $event || ! $event->started_at) return 0;

        $firstHours = max(1, min(72, (int) config('status_reminders.first_after_hours', 3)));
        $repeatHours = max(1, min(72, (int) config('status_reminders.repeat_every_hours', 3)));
        $maximum = max(1, min(10, (int) config('status_reminders.max_attempts', 3)));
        if (Carbon::parse($event->started_at)->addHours($firstHours)->isFuture()) return 0;

        $scheduled = 0;
        $this->members->forEvent((string) $event->event_id)
            ->select(['member_id', 'household_id'])
            ->chunkById(200, function ($members) use ($event, $repeatHours, $maximum, &$scheduled): void {
                $latest = StatusReminder::query()->where('event_id', $event->event_id)
                    ->whereIn('member_id', $members->pluck('member_id'))
                    ->orderByDesc('attempt')->get()
                    ->unique('member_id')->keyBy('member_id');

                foreach ($members as $member) {
                    $last = $latest->get($member->member_id);
                    if ($last && in_array($last->status, ['pending', 'sending'], true)) continue;
                    if ($last && ((int) $last->attempt >= $maximum || ! $last->sent_at
                        || Carbon::parse($last->sent_at)->addHours($repeatHours)->isFuture())) continue;

                    $inserted = StatusReminder::query()->insertOrIgnore([
                        'event_id' => $event->event_id,
                        'household_id' => $member->household_id,
                        'member_id' => $member->member_id,
                        'attempt' => $last ? (int) $last->attempt + 1 : 1,
                        'status' => 'pending',
                        'scheduled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (! $inserted) continue;

                    $reminderId = StatusReminder::query()->where('event_id', $event->event_id)
                        ->where('member_id', $member->member_id)
                        ->where('attempt', $last ? (int) $last->attempt + 1 : 1)
                        ->value('reminder_id');
                    SendStatusReminder::dispatch((int) $reminderId)
                        ->onConnection('operations_outbox')->onQueue('operations');
                    $scheduled++;
                }
            }, 'member_id');

        return $scheduled;
    }
}
