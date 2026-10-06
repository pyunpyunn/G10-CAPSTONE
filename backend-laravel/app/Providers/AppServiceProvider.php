<?php

namespace App\Providers;

use App\Support\RequestQueryProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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


