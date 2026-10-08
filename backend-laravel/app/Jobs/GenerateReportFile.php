<?php

namespace App\Jobs;

use App\Services\Web\ReportGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GenerateReportFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public string $token, public array $payload) {}

    public function handle(ReportGenerationService $reports): void
    {
        $response = $reports->generate(Request::create('/', 'POST', $this->payload), false);
        if ($response->getStatusCode() !== 200) throw new \RuntimeException('Report generation failed.');
        Storage::disk('local')->put('report-jobs/'.$this->token.'.json', json_encode($response->getData(true)));
    }

    public function failed(?\Throwable $exception): void
    {
        Storage::disk('local')->put('report-jobs/'.$this->token.'.json', json_encode(['status' => 'error', 'message' => 'Report generation failed. Narrow the filters and try again.']));
    }
}
