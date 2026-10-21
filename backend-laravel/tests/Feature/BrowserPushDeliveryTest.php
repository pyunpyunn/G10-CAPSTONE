<?php
namespace Tests\Feature;

use App\Models\User;
use App\Presenters\NotificationPresenter;
use App\Queries\NotificationQuery;
use App\Services\Web\BrowserPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class BrowserPushDeliveryTest extends TestCase
{
    private array $sources;
    private BrowserPushService $push;
    private $transport;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_10_21_000001_create_web_push_tables.php'))->up();
        (require database_path('migrations/2026_10_21_000002_add_web_push_event_cutoff.php'))->up();
        Schema::create('roles', function (Blueprint $t) { $t->id('role_id'); $t->string('role_key'); });
        Schema::create('users', function (Blueprint $t) { $t->string('user_id')->primary(); $t->integer('role_id'); $t->boolean('is_active'); $t->timestamps(); });
        Schema::create('personal_access_tokens', function (Blueprint $t) { $t->id(); $t->timestamp('expires_at')->nullable(); });
        DB::table('roles')->insert(['role_id'=>1,'role_key'=>'chair']);
        DB::table('users')->insert(['user_id'=>'HQ','role_id'=>1,'is_active'=>1]);
        DB::table('personal_access_tokens')->insert(['id'=>1]);
        $this->user = User::with('role')->find('HQ');
        $this->sources = ['advisories'=>collect(), 'dispatch'=>collect(), 'requests'=>collect(), 'broadcasts'=>collect(), 'audit'=>collect()];
        $query = \Mockery::mock(NotificationQuery::class);
        $query->shouldReceive('feedSources')->andReturnUsing(fn () => $this->sources);
        $this->transport = \Mockery::mock(WebPush::class);
        $this->push = new class($query, app(NotificationPresenter::class), $this->transport) extends BrowserPushService {
            public function __construct($query, $presenter, private WebPush $sender) { parent::__construct($query, $presenter); }
            protected function transport(): WebPush { return $this->sender; }
        };
        config(['web_push.public_key'=>'test-public','web_push.private_key'=>'test-private']);
    }

    private function notice(int $id): object
    {
        return (object)['id'=>$id,'source'=>'PAGASA','title'=>'Advisory '.$id,'body'=>'Official bulletin sync','created_at'=>now(),'source_url'=>'https://publicalert.pagasa.dost.gov.ph/feeds/'];
    }
    private function subscription(): array
    {
        return ['endpoint'=>'https://fcm.googleapis.com/fcm/send/test','keys'=>['p256dh'=>rtrim(strtr(base64_encode("\x04".str_repeat('a',64)),'+/','-_'),'='),'auth'=>rtrim(strtr(base64_encode(str_repeat('b',16)),'+/','-_'),'=')]];
    }

    public function test_new_notices_deliver_once_without_replaying_existing_feed(): void
    {
        $this->sources['advisories']->push($this->notice(1));
        $this->push->subscribe($this->user, $this->subscription(), 1);
        $this->assertDatabaseCount('web_push_deliveries', 1);
        $this->sources['advisories']->push($this->notice(2));
        $this->transport->shouldReceive('sendOneNotification')->once()->andReturn(new MessageSentReport(new Request('POST', $this->subscription()['endpoint']), new Response(201)));
        $this->assertSame(1, $this->push->deliver());
        $this->assertSame(0, $this->push->deliver());
        $this->assertDatabaseCount('web_push_deliveries', 2);
    }

    public function test_revoked_login_and_module_access_stop_delivery(): void
    {
        $this->push->subscribe($this->user, $this->subscription(), 1);
        $this->sources['advisories']->push($this->notice(1));
        $this->transport->shouldNotReceive('sendOneNotification');
        DB::table('users')->where('user_id','HQ')->update(['is_active'=>0]);
        $this->assertSame(0, $this->push->deliver());
        DB::table('users')->where('user_id','HQ')->update(['is_active'=>1]);
        DB::table('personal_access_tokens')->delete();
        $this->assertSame(0, $this->push->deliver());
    }

    public function test_expired_provider_subscription_is_retired(): void
    {
        $this->push->subscribe($this->user, $this->subscription(), 1);
        $this->sources['advisories']->push($this->notice(1));
        $this->transport->shouldReceive('sendOneNotification')->once()->andReturn(new MessageSentReport(new Request('POST', $this->subscription()['endpoint']), new Response(410), false));
        $this->assertSame(0, $this->push->deliver());
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_push_endpoints_require_login_and_reject_untrusted_targets(): void
    {
        $this->getJson('/api/v1/notifications/push/config')->assertUnauthorized();
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/notifications/push/config')
            ->assertOk()->assertExactJson(['data'=>['public_key'=>'test-public']]);
        $payload = $this->subscription();
        $payload['endpoint'] = 'https://127.0.0.1/private';
        $this->postJson('/api/v1/notifications/push/subscription', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_historical_records_do_not_push_after_subscription(): void
    {
        $this->push->subscribe($this->user, $this->subscription(), 1);
        $old = $this->notice(1);
        $old->created_at = now()->subDays(30);
        $this->sources['advisories']->push($old);
        $this->transport->shouldNotReceive('sendOneNotification');
        $this->assertSame(0, $this->push->deliver());
        $this->assertSame(0, $this->push->deliver());
    }

    public function test_event_identity_does_not_change_when_presentation_changes(): void
    {
        $notice = ['id'=>'audit-123','title'=>'First title','body'=>'First body','created_at'=>'2026-10-21 12:00:00'];
        $this->assertSame($this->push->hash($notice), $this->push->hash(array_merge($notice, ['title'=>'New formatting','created_at'=>'2026-10-22 12:00:00'])));
    }
}
