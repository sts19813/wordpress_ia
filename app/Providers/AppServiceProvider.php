<?php

namespace App\Providers;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Models\Company;
use App\Models\Publication;
use App\Models\Scheduler;
use App\Models\WordPressSite;
use App\Observers\SystemActivityObserver;
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
        Publication::observe(SystemActivityObserver::class);
        Scheduler::observe(SystemActivityObserver::class);
        AiArticle::observe(SystemActivityObserver::class);
        AiImage::observe(SystemActivityObserver::class);
        WordPressSite::observe(SystemActivityObserver::class);
        Company::observe(SystemActivityObserver::class);
    }
}
