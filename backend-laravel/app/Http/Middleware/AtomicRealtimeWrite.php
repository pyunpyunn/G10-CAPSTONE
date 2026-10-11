<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AtomicRealtimeWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('realtime.durable') || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        $isReport = (bool) preg_match('#^api/v1/(?:household/(?:status|members/[^/]+/status|trusted-households/[^/]+/members/[^/]+/status)|rescuer/(?:field-reports|resource-requests|assignments/[^/]+/status))$#', $request->path());
        $key = $isReport ? $request->header('Idempotency-Key') : null;
        if ($key !== null && ! preg_match('/^[A-Za-z0-9_-]{16,128}$/', $key)) {
            return response()->json(['message' => 'Idempotency-Key must contain 16 to 128 letters, digits, underscores or hyphens.'], 422);
        }
        $db = DB::connection(config('realtime.connection'));
        $db->beginTransaction();
        try {
            $scope = null;
            if ($key !== null) {
                $scope = hash('sha256', implode('|', [(string) $request->user()?->getAuthIdentifier(), $request->method(), $request->path(), $key]));
                $hash = hash('sha256', json_encode($this->canonical($request->all()), JSON_THROW_ON_ERROR));
                // The unique key serializes simultaneous retries across ALL API servers.
                $db->table('report_idempotency')->insertOrIgnore([
                    'scope' => $scope, 'request_hash' => $hash, 'created_at' => now(), 'expires_at' => now()->addDays(7),
                ]);
                $existing = $db->table('report_idempotency')->where('scope', $scope)->lockForUpdate()->first();
                if ($existing->request_hash !== $hash) {
                    $db->rollBack();

                    return response()->json(['message' => 'This report key was already used with different data.'], 409);
                }
                if ($existing->response_status !== null) {
                    $db->commit();

                    return response($existing->response_body, $existing->response_status)
                        ->header('Content-Type', $existing->content_type)
                        ->header('Idempotency-Replayed', 'true')->header('Cache-Control', 'no-store');
                }
            }
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                $db->rollBack();

                return $response;
            }
            if ($scope !== null) {
                $db->table('report_idempotency')->where('scope', $scope)->update([
                    'response_status' => $response->getStatusCode(), 'response_body' => $response->getContent(),
                    'content_type' => $response->headers->get('Content-Type', 'application/json'),
                ]);
            }
            // Source writes, their outbox entries and the replay response commit together.
            $db->commit();

            return $response;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    private function canonical(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['file' => hash_file('sha256', $value->getRealPath())];
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
