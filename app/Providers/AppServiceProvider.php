<?php

namespace App\Providers;

use App\Models\Brand;
use App\Observers\BrandObserver;
use App\Policies\BrandPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { }
    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);
        Brand::observe(BrandObserver::class);
    }
}
