<?php

namespace App\Console\Commands;

use App\Services\Shared\OneSignalNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class OneSignalStatus extends Command
{
    protected $signature = 'onesignal:status';

    protected $description = 'Check Android push readiness without sending notifications or exposing credentials';

    public function handle(OneSignalNotificationService $push): int
    {
        $appId = trim((string) config('services.onesignal.app_id'));
        $key = trim((string) config('services.onesignal.api_key'));
        if ($appId === '' || $key === '') {
            $this->error('OneSignal App ID or API key is missing.');
            return self::FAILURE;
        }
        try {
            $response = Http::withHeaders(['Authorization' => 'Key '.$key])
                ->connectTimeout(5)->timeout(15)
                ->get(rtrim(config('services.onesignal.base_url'), '/').'/apps/'.$appId);
        } catch (\Throwable) {
            $this->error('OneSignal could not be reached.');
            return self::FAILURE;
        }
        if (! $response->successful()) {
            $this->error('OneSignal app check returned HTTP '.$response->status().'.');
            return self::FAILURE;
        }
        $fcm = ! empty($response->json('fcm_v1_service_account_json'));
        $audience = $push->prepareDelivery(['roles' => ['household', 'rescuer'], 'platforms' => ['android']]);
        $this->line(json_encode([
            'app_id' => $appId,
            'api_authenticated' => true,
            'android_fcm_v1_configured' => $fcm,
            'provider_messageable_devices' => (int) $response->json('messageable_players', 0),
            'locally_registered_android_subscriptions' => count($audience['recipient_ids']),
            'delivery_queue' => 'broadcasts',
        ], JSON_THROW_ON_ERROR));
        return $fcm ? self::SUCCESS : self::FAILURE;
    }
}
