<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\BrowserPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BrowserPushController extends Controller
{
    public function configuration()
    {
        return response()->json(['data' => ['public_key' => config('web_push.public_key')]]);
    }
    public function store(Request $request, BrowserPushService $push)
    {
        $v = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:200'], 'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        // Only browser push-provider endpoints may be contacted; never arbitrary URLs.
        $host = strtolower((string) parse_url($v['endpoint'], PHP_URL_HOST));
        $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com'
            || $host === 'web.push.apple.com' || str_ends_with($host, '.notify.windows.com');
        if (! $allowed || parse_url($v['endpoint'], PHP_URL_PORT) || parse_url($v['endpoint'], PHP_URL_USER)) {
            throw ValidationException::withMessages(['endpoint' => 'Unsupported browser push provider.']);
        }
        $publicKey = base64_decode(strtr($v['keys']['p256dh'], '-_', '+/'), true);
        $auth = base64_decode(strtr($v['keys']['auth'], '-_', '+/'), true);
        if ($publicKey === false || strlen($publicKey) !== 65 || $publicKey[0] !== "\x04" || $auth === false || strlen($auth) !== 16) {
            throw ValidationException::withMessages(['keys' => 'Invalid browser subscription encryption keys.']);
        }
        $push->subscribe($request->user(), $v, $request->user()->currentAccessToken()?->getKey());
        return response()->json(['message' => 'Browser notifications enabled.']);
    }
    public function destroy(Request $request)
    {
        $v = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $ids = DB::table('web_push_subscriptions')->where('user_id', $request->user()->user_id)->where('endpoint_hash', hash('sha256', $v['endpoint']))->pluck('id');
        DB::table('web_push_deliveries')->whereIn('subscription_id', $ids)->delete();
        DB::table('web_push_subscriptions')->whereIn('id', $ids)->delete();
        return response()->json(['message' => 'Browser notifications disabled.']);
    }
}
