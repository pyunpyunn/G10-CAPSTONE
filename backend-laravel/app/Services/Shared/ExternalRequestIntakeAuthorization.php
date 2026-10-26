<?php

namespace App\Services\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalRequestIntakeAuthorization
{
    public function error(Request $request): ?JsonResponse
    {
        $configured = trim((string) config('services.external_request_intake.key', ''));
        if ($configured === '' || $configured === 'change-this-shared-request-key') {
            return response()->json(['message' => 'External request intake key is not configured.'], 503);
        }

        $provided = trim((string) $request->header('X-RESQPERATION-INTEGRATION-KEY', ''));
        $provided = $provided !== '' ? $provided : (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'External request intake is not authorized.'], 401);
        }

        return null;
    }
}







