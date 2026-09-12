<?php

namespace App\Providers;

use App\Repositories\HouseholdRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HouseholdRepository::class);
    }
}
