<?php
namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class OfficialAdvisoryNotificationSync
{
    public function sync(): array
    {
        $results = [];
        foreach (['PAGASA', 'PHIVOLCS'] as $source) {
            try { $results[$source] = $source === 'PAGASA' ? $this->pagasa() : $this->phivolcs(); }
            catch (\Throwable $error) { report($error); $results[$source] = 'failed'; }
        }
        app(RealtimeReadCache::class)->invalidate('advisory-sync-'.now()->timestamp);
        return $results;
    }

    private function pagasa(): string
    {
        $snapshot = app(OfficialWeatherAdvisoryService::class)->overview();
        if (! in_array($snapshot['status'], ['live', 'partial'], true)) return 'unavailable';
        $alerts = $snapshot['alerts'];
        $signature = array_map(fn ($alert) => [$alert['id'], $alert['issued_at'], $alert['title']], $alerts);
        sort($signature);
        return $this->save('PAGASA', $signature, 'PAGASA advisories synchronized',
            count($alerts).' active official advisories. '.implode('; ', array_column(array_slice($alerts, 0, 3), 'title')),
            config('weather_advisories.feed_url'));
    }

    public function parsePhivolcs(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new DOMXPath($document);
        $events = [];
        foreach ($xpath->query('//tr') as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells->length !== 6) continue;
            $values = [];
            foreach ($cells as $cell) $values[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
            if (! preg_match('/\d{1,2}\s+[A-Za-z]+\s+\d{4}/', $values[0]) || ! is_numeric($values[4])) continue;
            $events[] = $values;
            if (count($events) === 10) break;
        }
        if ($events === []) throw new \RuntimeException('PHIVOLCS bulletin page contained no recognized earthquake records.');
        return $events;
    }

    private function phivolcs(): string
    {
        $url = 'https://earthquake.phivolcs.dost.gov.ph/';
        try {
            $html = Http::connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])->get($url)->throw()->body();
        } catch (\Throwable $error) {
            if (PHP_OS_FAMILY !== 'Windows' || ! str_contains($error->getMessage(), 'cURL error 60')) throw $error;
            // Schannel uses the Windows trust store and validates the official certificate chain.
            $html = (new \Symfony\Component\Process\Process(['curl.exe', '--compressed', '--fail', '--silent', '--show-error', '--max-time', '15', $url]))->mustRun()->getOutput();
        }
        $events = $this->parsePhivolcs($html);
        $latest = $events[0];
        return $this->save('PHIVOLCS', $events, 'PHIVOLCS earthquake bulletins synchronized',
            'Latest bulletin: '.$latest[0].', magnitude '.$latest[4].', '.$latest[5].'. Open the official source for details.', $url);
    }

    private function save(string $source, array $signature, string $title, string $body, string $url): string
    {
        $inserted = DB::table('official_advisory_notifications')->insertOrIgnore([
            'source' => $source, 'fingerprint' => hash('sha256', $source.json_encode($signature)),
            'title' => $title, 'body' => $body, 'source_url' => $url, 'created_at' => now(),
        ]);
        return $inserted ? 'updated' : 'unchanged';
    }
}
