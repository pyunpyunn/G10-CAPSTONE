<?php

namespace App\Services\Web;

use App\Models\User;
use App\Presenters\NotificationPresenter;
use App\Queries\NotificationQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class BrowserPushService
{
    public function __construct(private NotificationQuery $query, private NotificationPresenter $presenter) {}

    public function hash(array $notice): string
    {
        // IDs identify actual persisted source events; formatting and read time are not new events.
        return hash('sha256', 'web-event-v2:'.$notice['id']);
    }

    protected function transport(): WebPush
    {
        return new WebPush(['VAPID' => ['subject' => config('web_push.subject'), 'publicKey' => config('web_push.public_key'), 'privateKey' => config('web_push.private_key')]], ['TTL' => 3600, 'urgency' => 'high'], new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5, 'allow_redirects' => false]));
    }

    public function subscribe(User $user, array $subscription, ?int $tokenId = null): void
    {
        $hash = hash('sha256', $subscription['endpoint']);
        DB::transaction(function () use ($user, $subscription, $hash, $tokenId) {
            $existing = DB::table('web_push_subscriptions')->where('endpoint_hash', $hash)->lockForUpdate()->first();
            $sameUser = $existing && $existing->user_id === (string) $user->user_id;
            $values = ['user_id' => $user->user_id, 'token_id' => $tokenId, 'subscription' => json_encode($subscription), 'updated_at' => now(), 'expires_at' => now()->addDays(30)];
            if ($existing) {
                if (! $sameUser) $values['push_after'] = now();
                DB::table('web_push_subscriptions')->where('id', $existing->id)->update($values);
                $id = $existing->id;
            } else {
                $id = DB::table('web_push_subscriptions')->insertGetId($values + ['endpoint_hash' => $hash, 'created_at' => now(), 'push_after' => now()]);
            }
            // Baseline existing notices once, so enabling push does not replay old alerts.
            if (! $sameUser) {
                DB::table('web_push_deliveries')->where('subscription_id', $id)->delete();
                foreach ($this->presenter->forUser($this->presenter->feed($this->query->feedSources()), $user) as $notice) {
                    DB::table('web_push_deliveries')->insertOrIgnore(['subscription_id' => $id, 'notice_hash' => $this->hash($notice), 'created_at' => now()]);
                }
            }
        });
    }

    public function deliver(): int
    {
        if (! config('web_push.public_key') || ! config('web_push.private_key')) return 0;
        $lock = Cache::store('file')->lock('browser-push-delivery:'.config('app.env'), 120);
        if (! $lock->get()) return 0;
        try {
            $feed = $this->presenter->feed($this->query->feedSources());
            $push = $this->transport();
            $sent = 0;
            foreach (DB::table('web_push_subscriptions')->where('expires_at', '>', now())->cursor() as $device) {
                if (! $device->token_id || ! DB::table('personal_access_tokens')->where('id', $device->token_id)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) continue;
                $user = User::with('role')->where('user_id', $device->user_id)->first();
                if (! $user || ! app(WebAccessPolicy::class)->allows($user, 'notifications')) continue;
                foreach ($this->presenter->forUser($feed, $user)->reverse() as $notice) {
                    $cutoff = $device->push_after ?? $device->created_at;
                    if (! $notice['sort_time'] || \Carbon\Carbon::parse($notice['created_at'])->lt($cutoff)) continue;
                    $hash = $this->hash($notice);
                    if (DB::table('web_push_deliveries')->where('subscription_id', $device->id)->where('notice_hash', $hash)->exists()) continue;
                    try {
                        $report = $push->sendOneNotification(Subscription::create(json_decode($device->subscription, true)), json_encode([
                            'schema' => 2, 'occurred_at' => $notice['created_at'],
                            'id' => $notice['id'], 'type' => $notice['type'], 'title' => mb_substr($notice['title'], 0, 160),
                            'body' => mb_substr($notice['body'], 0, 650), 'url' => $notice['action_url'],
                        ]));
                    } catch (\Throwable $error) {
                        \Illuminate\Support\Facades\Log::warning('Browser push attempt could not be submitted', ['subscription_id' => $device->id, 'exception' => get_class($error)]);
                        continue;
                    }
                    if ($report->isSubscriptionExpired()) {
                        DB::table('web_push_subscriptions')->where('id', $device->id)->delete();
                        DB::table('web_push_deliveries')->where('subscription_id', $device->id)->delete();
                        break;
                    }
                    if ($report->isSuccess()) {
                        DB::table('web_push_deliveries')->insertOrIgnore(['subscription_id' => $device->id, 'notice_hash' => $hash, 'created_at' => now()]);
                        $sent++;
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Browser push delivery failed', ['subscription_id' => $device->id, 'status' => $report->getResponse()?->getStatusCode()]);
                    }
                }
            }
            return $sent;
        } finally { $lock->release(); }
    }
}
