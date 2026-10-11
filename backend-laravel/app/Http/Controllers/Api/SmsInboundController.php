<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mobile\HouseholdMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsInboundController extends Controller
{
    private const KEYWORDS = [
        'SAFE' => 'safe', 'OK' => 'safe', 'EVAC' => 'evacuated', 'EVACUATED' => 'evacuated',
        'UNSAFE' => 'unsafe', 'HELP' => 'needs_help', 'NEEDHELP' => 'needs_help',
        'NEEDS_HELP' => 'needs_help', 'RESCUE' => 'needs_help',
    ];

    public function __construct(private readonly HouseholdMobileService $households) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = trim((string) config('services.sms_gateway.webhook_signing_key'));
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = strtolower(trim((string) $request->header('X-Signature', '')));
        if ($secret === '') {
            return response()->json(['message' => 'Inbound SMS webhook is not configured.'], 503);
        }
        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300 || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return response()->json(['message' => 'Invalid webhook signature or timestamp.'], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent().$timestamp, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature or timestamp.'], 401);
        }

        if ($request->input('event') !== 'sms:received') {
            return response()->json(['message' => 'Unsupported webhook event.'], 422);
        }

        $from = (string) $request->input('payload.phoneNumber', '');
        $body = (string) $request->input('payload.message', '');
        $parsed = $this->parse($body);
        if (! $parsed) {
            return response()->json(['message' => 'Use STATUS <household_code> <status>.'], 422);
        }

        [$householdCode, $statusKey] = $parsed;
        $result = $this->households->storeStatusFromSms($householdCode, $statusKey, $from, $body);
        if (! $result['ok']) {
            Log::info('Inbound SMS status was rejected', ['reason' => $result['reason']]);
            return response()->json(['message' => 'Could not save status.', 'reason' => $result['reason']], 422);
        }

        return response()->json(['message' => 'Status saved.', 'data' => $result]);
    }

    private function parse(string $body): ?array
    {
        $text = Str::of($body)->trim()->squish()->upper()->toString();
        if (! Str::startsWith($text, 'STATUS ')) return null;
        $parts = preg_split('/\s+/', trim(Str::after($text, 'STATUS ')));
        if (count($parts) < 2) return null;

        $code = array_shift($parts);
        $keyword = implode('_', $parts);
        $status = self::KEYWORDS[$keyword] ?? self::KEYWORDS[str_replace('_', '', $keyword)] ?? null;
        return $status ? [$code, $status] : null;
    }
}



