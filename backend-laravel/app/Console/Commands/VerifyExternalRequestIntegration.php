<?php

namespace App\Console\Commands;

use App\Models\ResourceRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VerifyExternalRequestIntegration extends Command
{
    protected $signature = 'requests:verify-integration {--url=http://127.0.0.1:8000 : Backend origin} {--submit : Create or update a clearly marked verification request}';

    protected $description = 'Verify the external requests handshake over HTTP without displaying credentials';

    public function handle(): int
    {
        $key = trim((string) config('services.external_request_intake.key', ''));
        if ($key === '' || $key === 'change-this-shared-request-key') {
            $this->error('Configure EXTERNAL_REQUEST_INTAKE_KEY before verification.');
            return self::FAILURE;
        }

        $base = rtrim((string) $this->option('url'), '/').'/api/v1/external/resource-requests';
        // Credentials must only go to the operator-specified server, never a redirect.
        $http = fn () => Http::acceptJson()->timeout(15)->withoutRedirecting();

        try {
            foreach ([$base.'/handshake', $base] as $url) {
                foreach ([[], ['X-RESQPERATION-INTEGRATION-KEY' => 'invalid-verification-key']] as $headers) {
                    $response = $http()->withHeaders($headers)->post($url, []);
                    if ($response->status() !== 401) {
                        $this->error('Credential rejection check failed: HTTP '.$response->status());
                        return self::FAILURE;
                    }
                }
            }
            $this->info('PASS: Both endpoints reject missing and incorrect keys (401).');

            $response = $http()->withHeaders(['X-RESQPERATION-INTEGRATION-KEY' => $key])->post($base.'/handshake', []);
            if ($response->status() !== 200 || $response->json('data.authenticated') !== true || $response->json('data.request_storage_ready') !== true) {
                $this->error('Handshake failed: HTTP '.$response->status());
                return self::FAILURE;
            }
            $this->info('PASS: Authenticated handshake and request storage readiness (200).');

            if ($this->option('submit')) {
                $payload = [
                    'source_system' => 'evatrack',
                    'external_request_id' => 'EVA-INTEGRATION-VERIFICATION',
                    'requested_item' => 'API integration verification - test only',
                    'quantity' => 1,
                    'unit' => 'test',
                    'requested_by' => 'API integration verification',
                    'notes' => 'Test request created by requests:verify-integration. Do not forward or fulfill.',
                ];
                $response = $http()->withHeaders(['X-RESQPERATION-INTEGRATION-KEY' => $key])->post($base, $payload);
                if (! in_array($response->status(), [200, 201], true)) {
                    $this->error('Submission failed: HTTP '.$response->status());
                    return self::FAILURE;
                }
                $id = $response->json('data.request.request_id');
                $retry = $http()->withHeaders(['X-RESQPERATION-INTEGRATION-KEY' => $key])->post($base, $payload);
                $rows = ResourceRequest::where('request_source', 'evatrack')->where('source_reference', $payload['external_request_id']);
                if (! $id || $retry->status() !== 200 || $retry->json('data.request.request_id') !== $id || $rows->count() !== 1 || $rows->value('request_id') !== $id) {
                    $this->error('Saved request or retry verification failed.');
                    return self::FAILURE;
                }
                $this->info('PASS: Submission saved and retry updates the same request: '.$id);
                $this->line('The marked test request remains in the requests queue. Do not forward or fulfill it.');
            }
        } catch (\Throwable $error) {
            // Exception messages may contain request details; never print the secret.
            $this->error('Verification could not finish. Check the backend server and database connection.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

