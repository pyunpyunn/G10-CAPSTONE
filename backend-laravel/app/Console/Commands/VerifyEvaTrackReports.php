<?php

namespace App\Console\Commands;

use App\Services\Shared\EvaTrackReportClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VerifyEvaTrackReports extends Command
{
    protected $signature = 'reports:verify-evatrack {--type=general} {--format=csv}';
    protected $description = 'Generate and download an EvaTrack report to verify the external integration';

    public function handle(EvaTrackReportClient $client): int
    {
        if (! in_array($this->option('type'), $client->configuration()['report_types'], true)
            || ! in_array($this->option('format'), ['pdf', 'csv'], true)) {
            $this->error('Select a supported EvaTrack report type and pdf or csv format.');
            return self::FAILURE;
        }
        $result = $client->generate(['report_type' => $this->option('type'), 'format' => $this->option('format')]);
        $data = $result->getData(true);
        if ($result->getStatusCode() !== 200) {
            $this->error($data['message']);
            return self::FAILURE;
        }
        try {
            $download = Http::timeout(60)->withoutRedirecting()->get($data['download_url']);
            $valid = $this->option('format') === 'pdf'
                ? str_starts_with($download->body(), '%PDF')
                : ! str_contains(strtolower((string) $download->header('Content-Type')), 'text/html') && $download->body() !== '';
            if (! $download->successful() || ! $valid) {
                $this->error('EvaTrack generated a link, but report download verification failed.');
                return self::FAILURE;
            }
            $path = 'evatrack-verification/'.$data['file_name'];
            if (! Storage::disk('local')->put($path, $download->body())) {
                $this->error('Report downloaded but could not be saved locally.');
                return self::FAILURE;
            }
            $this->info('PASS: EvaTrack authentication, generation, and signed download.');
            $this->line('Saved: '.Storage::disk('local')->path($path));
        } catch (\Illuminate\Http\Client\ConnectionException $error) {
            $this->error('EvaTrack download server could not be reached.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}
