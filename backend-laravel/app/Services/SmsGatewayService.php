<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SmsGatewayService
{
    public function sendBroadcastSms(string $message, array $options = []): array
    {
        if (! $this->baseUrl() || ! $this->username() || ! $this->password()) {
            return $this->result('not_configured', 0, 'Android SMS Gateway URL and credentials are not configured.');
        }

        $numbers = $this->recipientPhoneNumbers($options);

        if ($numbers === []) {
            return $this->result('no_recipients', 0, 'No household contact numbers were found.');
        }

        $sent = 0;
        $providerIds = [];
        $errors = [];

        $batchSize = max(1, min(100, (int) config('services.sms_gateway.batch_size', 20)));
        foreach (array_chunk($numbers, $batchSize) as $chunk) {
            try {
                $response = Http::withBasicAuth($this->username(), $this->password())
                    ->acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post($this->messageUrl(), [
                        'textMessage' => ['text' => $message],
                        'phoneNumbers' => $chunk,
                    ]);

                if (! $response->successful()) {
                    $errors[] = 'HTTP '.$response->status().': '.substr($response->body(), 0, 300);
                    continue;
                }

                $sent += count($chunk);
                $providerId = $response->json('id') ?? $response->json('messageId');
                if ($providerId) {
                    $providerIds[] = (string) $providerId;
                }
            } catch (\Throwable $error) {
                Log::warning('SMS gateway send failed', ['message' => $error->getMessage()]);
                $errors[] = $error->getMessage();
            }
        }

        if ($sent === 0) {
            return ['status' => 'failed', 'recipient_count' => count($numbers), 'accepted_count' => 0,
                'provider_ids' => [], 'message' => 'SMS gateway did not accept the message.', 'errors' => $errors];
        }

        return ['status' => $errors === [] ? 'accepted' : 'partial', 'recipient_count' => count($numbers),
            'accepted_count' => $sent, 'provider_ids' => $providerIds, 'message' => 'Android gateway accepted the SMS request(s).', 'errors' => $errors];
    }

    private function recipientPhoneNumbers(array $options): array
    {
        if (! Schema::hasTable('households') || ! Schema::hasColumn('households', 'contact_number')) {
            return [];
        }

        $query = DB::table('households as h')->whereNotNull('h.contact_number')->where('h.contact_number', '<>', '');
        if (Schema::hasColumn('households', 'deleted_at')) {
            $query->whereNull('h.deleted_at');
        }

        $puroks = collect($options['household_puroks'] ?? [])->map(fn ($name) => trim((string) $name))->filter()->unique()->values()->all();
        if ($puroks !== [] && (! Schema::hasColumn('households', 'address_id')
            || ! Schema::hasTable('addresses')
            || ! Schema::hasColumn('addresses', 'purok_sitio'))) {
            // Never broaden a scoped broadcast when the target location cannot be verified.
            return [];
        }
        if ($puroks !== []) {
            $query
                ->join('addresses as a_sms', 'a_sms.address_id', '=', 'h.address_id')
                ->whereIn('a_sms.purok_sitio', $puroks);

            if (Schema::hasColumn('addresses', 'deleted_at')) {
                $query->whereNull('a_sms.deleted_at');
            }
        }

        $householdIds = collect($options['household_ids'] ?? [])->filter()->unique()->values()->all();
        if ($householdIds !== []) {
            $query->whereIn('h.household_id', $householdIds);
        }

        return $query->distinct()->pluck('h.contact_number')
            ->map(fn ($number) => $this->normalizeNumber((string) $number))->filter()->unique()->values()->all();
    }

    private function normalizeNumber(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (! $digits) return null;
        if (str_starts_with($digits, '0') && strlen($digits) === 11) return '+63'.substr($digits, 1);
        if (str_starts_with($digits, '9') && strlen($digits) === 10) return '+63'.$digits;
        if (str_starts_with($digits, '63') && strlen($digits) === 12) return '+'.$digits;
        return null;
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('services.sms_gateway.base_url')), '/');
    }

    private function username(): string { return trim((string) config('services.sms_gateway.username')); }
    private function password(): string { return trim((string) config('services.sms_gateway.password')); }

    private function messageUrl(): string
    {
        return $this->baseUrl().'/'.ltrim((string) config('services.sms_gateway.message_path', '/message'), '/');
    }

    private function result(string $status, int $count, string $message): array
    {
        return ['status' => $status, 'recipient_count' => $count, 'accepted_count' => 0,
            'provider_ids' => [], 'message' => $message, 'errors' => []];
    }
}
