<?php
namespace Tests\Feature;
use App\Services\Shared\OfficialWeatherAdvisoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OfficialWeatherAdvisoryTest extends TestCase
{
    private string $url = 'https://publicalert.pagasa.dost.gov.ph/output/wcp/test.cap';
    protected function setUp(): void {
        parent::setUp();
        config(['weather_advisories.cache_store'=>'array']); Cache::store('array')->flush();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20T17:00:00+08:00'));
    }
    protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }
    private function cap(string $id = 'one', string $extra = '', string $type = 'Alert', string $expires = '2026-10-21T17:00:00+08:00'): string {
        return '<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>'.$id.'</identifier><sender>PAGASA-DOST</sender><sent>2026-10-20T16:00:00+08:00</sent><status>Actual</status><msgType>'.$type.'</msgType><scope>Public</scope>'.$extra.'<info><language>en-PH</language><event>Tropical Cyclone Warning</event><headline>Developing tropical cyclone</headline><description>Forecast winds within 36 hours.</description><urgency>Future</urgency><severity>Moderate</severity><certainty>Likely</certainty><onset>2026-10-21T06:00:00+08:00</onset><expires>'.$expires.'</expires><area><areaDesc>Cebu, Bohol</areaDesc></area></info></alert>';
    }
    public function test_government_forecast_preserves_timing_and_affected_areas(): void {
        $alert = (new OfficialWeatherAdvisoryService)->parseCap($this->cap(),$this->url)['alerts'][0];
        $this->assertSame('Tropical cyclone',$alert['category']);
        $this->assertSame('Future',$alert['urgency']);
        $this->assertSame('Likely',$alert['certainty']);
        $this->assertTrue($alert['mentions_cebu']);
        $this->assertSame('2026-10-21T06:00:00+08:00',$alert['onset_at']);
        $this->assertSame($this->url,$alert['source_url']);
        $national = str_replace('Cebu, Bohol','Batanes, Cagayan',$this->cap());
        $this->assertFalse((new OfficialWeatherAdvisoryService)->parseCap($national,$this->url)['alerts'][0]['mentions_cebu']);
    }
    public function test_expired_and_test_alerts_are_not_shown_as_current(): void {
        $service = new OfficialWeatherAdvisoryService;
        $this->assertSame([], $service->parseCap($this->cap(expires:'2026-10-20T16:00:00+08:00'),$this->url)['alerts']);
        $this->assertSame([], $service->parseCap(str_replace('<status>Actual','<status>Test',$this->cap()),$this->url)['alerts']);
    }
    public function test_cancellation_removes_reference_instead_of_becoming_a_warning(): void {
        $parsed = (new OfficialWeatherAdvisoryService)->parseCap($this->cap('two','<references>PAGASA-DOST,one,2026-10-20T16:00:00+08:00</references>','Cancel'),$this->url);
        $this->assertSame([], $parsed['alerts']); $this->assertSame(['one'],$parsed['superseded']);
    }
    public function test_external_document_urls_are_rejected(): void {
        $this->expectException(RuntimeException::class);
        (new OfficialWeatherAdvisoryService)->parseCap($this->cap(),'https://example.com/alert.cap');
    }
    public function test_xml_entities_are_rejected(): void {
        $this->expectException(RuntimeException::class);
        (new OfficialWeatherAdvisoryService)->parseCap('<!DOCTYPE alert [<!ENTITY x SYSTEM "file:///example">]>'.$this->cap(),$this->url);
    }
    public function test_live_feed_is_cached_and_outage_uses_stale_state(): void {
        $atom = '<feed xmlns="http://www.w3.org/2005/Atom"><updated>2026-10-20T16:00:00+08:00</updated><entry><updated>2026-10-20T16:00:00+08:00</updated><link type="application/cap+xml" href="'.$this->url.'"/></entry></feed>';
        $failing = false;
        Http::fake(function ($request) use (&$failing, $atom) {
            if ($failing) return Http::response('Unavailable',503);
            return Http::response($request->url() === $this->url ? $this->cap() : $atom);
        });
        $service = new OfficialWeatherAdvisoryService;
        $first = $service->overview(); $this->assertSame('live',$first['status']); $this->assertCount(1,$first['alerts']);
        $service->overview(); Http::assertSentCount(2);
        Cache::store('array')->forget('pagasa-official-cap-v1:checked');
        $failing = true;
        $saved = $service->overview(); $this->assertSame('stale',$saved['status']); $this->assertCount(1,$saved['alerts']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-22T17:00:00+08:00'));
        $this->assertSame([], $service->overview()['alerts']);
    }
    public function test_outage_without_saved_feed_is_unknown_not_no_hazards(): void {
        Http::fake(['*'=>Http::response('',503)]);
        $data = (new OfficialWeatherAdvisoryService)->overview();
        $this->assertSame('unavailable',$data['status']); $this->assertNull($data['synced_at']);
    }
}
