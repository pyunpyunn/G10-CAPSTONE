<?php
namespace App\Services\Shared;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class OfficialWeatherAdvisoryService
{
    private const KEY = 'pagasa-official-cap-v1';

    public function overview(): array
    {
        $cache = Cache::store(config('weather_advisories.cache_store', 'file'));
        $snapshot = $cache->get(self::KEY);
        if (! $cache->has(self::KEY.':checked')) {
            // A short lease prevents repeated upstream calls on failures and concurrent page loads.
            if ($cache->add(self::KEY.':checked', true, 60)) {
                try {
                    $snapshot = $this->sync();
                    $cache->put(self::KEY, $snapshot, 86400);
                    $cache->put(self::KEY.':checked', true, config('weather_advisories.refresh_seconds', 600));
                } catch (Throwable $error) {
                    report($error);
                    if ($snapshot) { $snapshot['status'] = 'stale'; $cache->put(self::KEY, $snapshot, 86400); }
                }
            }
        }
        $snapshot ??= ['status'=>'unavailable', 'synced_at'=>null, 'feed_updated_at'=>null, 'alerts'=>[]];
        if ($snapshot['synced_at'] && CarbonImmutable::parse($snapshot['synced_at'])->lt(CarbonImmutable::now()->subMinutes(15))) $snapshot['status'] = 'stale';
        $snapshot['alerts'] = array_values(array_filter($snapshot['alerts'], fn ($alert) => CarbonImmutable::parse($alert['expires_at'])->isFuture()));
        return $snapshot + [
            'source'=>'DOST-PAGASA', 'source_url'=>config('weather_advisories.feed_url'),
            'license'=>'CC BY 4.0 (PAGASA Atom feed)', 'refresh_seconds'=>config('weather_advisories.refresh_seconds', 600),
            'scope_note'=>'Philippines-wide watch. Affected areas are those named by PAGASA; this does not declare a disaster in Mambaling.',
        ];
    }

    private function sync(): array
    {
        $response = Http::connectTimeout(5)->timeout(8)->withOptions(['allow_redirects'=>false])
            ->get(config('weather_advisories.feed_url'))->throw();
        $feed = $this->xml($response->body());
        if ($feed->getName() !== 'feed') throw new RuntimeException('PAGASA returned an unexpected feed format.');
        $links = [];
        foreach ($feed->xpath('//*[local-name()="entry"]') as $entry) {
            $updated = $this->date($this->text($entry, 'updated'));
            if (! $updated || $updated->lt(CarbonImmutable::now()->subHours(72))) continue;
            foreach ($entry->xpath('./*[local-name()="link"]') as $link) {
                $url = (string) $link['href'];
                if ((string) $link['type'] === 'application/cap+xml' && $this->trustedUrl($url)) $links[$url] = $url;
            }
            if (count($links) >= 18) break;
        }
        $responses = $links ? Http::pool(function (Pool $pool) use ($links) {
            return array_map(fn ($url) => $pool->as($url)->connectTimeout(5)->timeout(8)->withOptions(['allow_redirects'=>false])->get($url), array_values($links));
        }) : [];
        $alerts = []; $superseded = []; $failed = 0;
        foreach ($links as $url) {
            try {
                $response = $responses[$url] ?? null;
                if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) throw new RuntimeException('A PAGASA alert could not be fetched.');
                $parsed = $this->parseCap($response->body(), $url);
                $alerts = array_merge($alerts, $parsed['alerts']);
                $superseded = array_merge($superseded, $parsed['superseded']);
            } catch (Throwable) { $failed++; }
        }
        if ($links && $failed === count($links)) throw new RuntimeException('PAGASA alert documents are unavailable.');
        $alerts = array_filter($alerts, fn ($alert) => ! in_array($alert['id'], $superseded, true));
        usort($alerts, fn ($a, $b) => strcmp($b['issued_at'], $a['issued_at']));
        $unique = [];
        foreach ($alerts as $alert) $unique[$alert['title'].'|'.$alert['areas']] ??= $alert;
        return ['status'=>$failed ? 'partial' : 'live', 'synced_at'=>CarbonImmutable::now()->toIso8601String(),
            'feed_updated_at'=>$this->date($this->text($feed, 'updated'))?->toIso8601String(), 'alerts'=>array_values($unique)];
    }

    public function parseCap(string $body, string $sourceUrl): array
    {
        if (! $this->trustedUrl($sourceUrl)) throw new RuntimeException('Untrusted alert URL.');
        $root = $this->xml($body);
        if ($root->getName() !== 'alert') throw new RuntimeException('Invalid CAP document.');
        $result = ['alerts'=>[], 'superseded'=>[]];
        if ($this->text($root,'status') !== 'Actual' || $this->text($root,'scope') !== 'Public') return $result;
        $type = $this->text($root,'msgType');
        if (! in_array($type, ['Alert','Update','Cancel'], true)) return $result;
        foreach (preg_split('/\s+/', $this->text($root,'references')) as $reference) {
            $parts = explode(',', $reference);
            if (isset($parts[1])) $result['superseded'][] = $parts[1];
        }
        if ($type === 'Cancel') return $result;
        $issued = $this->date($this->text($root,'sent'));
        if (! $issued || $issued->gt(CarbonImmutable::now()->addMinutes(10))) return $result;
        foreach ($root->xpath('./*[local-name()="info"]') as $info) {
            $language = $this->text($info,'language');
            if ($language !== '' && ! str_starts_with(strtolower($language), 'en')) continue;
            $expires = $this->date($this->text($info,'expires'));
            if (! $expires || ! $expires->isFuture()) continue;
            $event = $this->text($info,'event');
            $title = $this->text($info,'headline') ?: $event;
            $areas = [];
            foreach ($info->xpath('./*[local-name()="area"]') as $area) $areas[] = $this->text($area,'areaDesc');
            $description = $this->text($info,'description');
            $category = match (true) {
                (bool) preg_match('/low pressure|\bLPA\b/i', $event.' '.$title) => 'LPA',
                (bool) preg_match('/cyclone|typhoon|tropical storm/i', $event.' '.$title) => 'Tropical cyclone',
                (bool) preg_match('/flood|\bGFA\b/i', $event.' '.$title) => 'Flood',
                (bool) preg_match('/rain|thunderstorm/i', $event.' '.$title) => 'Rain / thunderstorm',
                (bool) preg_match('/gale/i', $event.' '.$title) => 'Gale', default => 'Weather advisory',
            };
            $areaText = implode('; ', array_filter($areas));
            $result['alerts'][] = [
                'id'=>$this->text($root,'identifier'), 'title'=>$title, 'category'=>$category,
                'description'=>$description, 'instruction'=>$this->text($info,'instruction'),
                'areas'=>$areaText ?: 'See official bulletin',
                'mentions_cebu'=>(bool) preg_match('/\bCebu\b|\bMambaling\b/i', $areaText),
                'issued_at'=>$issued->toIso8601String(), 'expires_at'=>$expires->toIso8601String(),
                'onset_at'=>$this->date($this->text($info,'onset'))?->toIso8601String(),
                'severity'=>$this->text($info,'severity'), 'urgency'=>$this->text($info,'urgency'),
                'certainty'=>$this->text($info,'certainty'), 'source_url'=>$sourceUrl,
            ];
        }
        return $result;
    }

    private function trustedUrl(string $url): bool
    {
        return (bool) preg_match('~^https://publicalert\.pagasa\.dost\.gov\.ph/output/[a-z0-9_-]+/[a-z0-9_-]+\.cap$~i', $url);
    }
    private function xml(string $body): SimpleXMLElement
    {
        if (strlen($body) > 2000000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) throw new RuntimeException('Invalid alert XML.');
        $previous = libxml_use_internal_errors(true);
        try { $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (! $xml) throw new RuntimeException('PAGASA XML could not be parsed.');
        return $xml;
    }
    private function text(SimpleXMLElement $node, string $field): string
    {
        $value = $node->xpath('./*[local-name()="'.$field.'"]')[0] ?? '';
        return trim(strip_tags((string) $value));
    }
    private function date(string $date): ?CarbonImmutable
    {
        if ($date === '') return null;
        try { return CarbonImmutable::parse($date); } catch (Throwable) { return null; }
    }
}
