<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.semaphore.api_key')) !== '';
    }

    public function sendDirectSms(string $phoneNumber, string $message): array
    {
        $apiKey = trim((string) config('services.semaphore.api_key'));
        if ($apiKey === '') {
            return $this->result('not_configured', 'Semaphore API key is not configured.');
        }

        $number = $this->toLocalNumber($phoneNumber);
        if ($number === null) {
            return $this->result('invalid_number', 'The phone number is not a valid Philippine mobile number.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->connectTimeout(5)
                ->post(rtrim((string) config('services.semaphore.base_url', 'https://api.semaphore.co/api/v4'), '/').'/messages', [
                    'apikey' => $apiKey,
                    'number' => $number,
                    'message' => $message,
                    ...($this->senderName() !== '' ? ['sendername' => $this->senderName()] : []),
                ]);

            $messages = $response->json();
            $result = is_array($messages) && array_is_list($messages) ? ($messages[0] ?? null) : null;
            $status = is_array($result) ? strtolower((string) ($result['status'] ?? '')) : '';
            $messageId = is_array($result) ? ($result['message_id'] ?? null) : null;

            if ($response->successful() && $messageId && in_array($status, ['queued', 'pending', 'sent'], true)) {
                return [
                    'status' => 'accepted',
                    'recipient_count' => 1,
                    'accepted_count' => 1,
                    'provider_ids' => [(string) $messageId],
                    'message' => 'Semaphore accepted the SMS request.',
                    'errors' => [],
                ];
            }

            Log::warning('Semaphore SMS request was not accepted', [
                'status' => $response->status(),
                'provider_status' => $status ?: null,
            ]);

            return $this->result('failed', 'Semaphore did not accept the SMS request. Check the Semaphore account, API key, sender name, and balance.');
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            Log::warning('Semaphore SMS request could not connect', ['message' => $error->getMessage()]);

            return $this->result('failed', 'Could not connect to Semaphore. Please try again later.');
        }
    }

    private function senderName(): string
    {
        return trim((string) config('services.semaphore.sender_name'));
    }

    private function toLocalNumber(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number);
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $digits;
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return null;
    }

    private function result(string $status, string $message): array
    {
        return [
            'status' => $status,
            'recipient_count' => 1,
            'accepted_count' => 0,
            'provider_ids' => [],
            'message' => $message,
            'errors' => [],
        ];
    }
}
