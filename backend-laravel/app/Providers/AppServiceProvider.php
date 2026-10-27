<?php

namespace App\Providers;

use App\Services\Shared\RealtimeOutbox;
use App\Support\RequestQueryProfile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Queries\DispatchStatusQuery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-send', function (Request $request): Limit {
            $identity = strtolower(trim((string) $request->input('login')));
            $key = hash('sha256', $identity.'|'.$request->ip());

            return Limit::perMinute(3)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Please wait before requesting another verification code.',
                        'cooldown' => true,
                        'cooldown_seconds' => max(1, (int) ($headers['Retry-After'] ?? 60)),
                    ]);
                });
        });

        // Includes query-builder writes from mobile, SMS and web workflows.
        DB::listen(fn ($query) => app(RealtimeOutbox::class)->capture($query));
        if (config('app.query_profile_enabled') && $this->app->environment('local')) {
            DB::listen(function ($query): void {
                if (! $this->app->bound('request')) {
                    return;
                }

                $profile = $this->app->make('request')->attributes->get('local_query_profile');
                if ($profile instanceof RequestQueryProfile) {
                    $profile->record($query);
                }
            });
        }
    }
}
