<?php

namespace App\Providers;

use App\Services\ETax\DisabledETaxSignatureAdapter;
use App\Services\ETax\DisabledETaxSubmissionAdapter;
use App\Services\ETax\ETaxSignatureAdapter;
use App\Services\ETax\ETaxSubmissionAdapter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ETaxSubmissionAdapter::class, DisabledETaxSubmissionAdapter::class);
        $this->app->bind(ETaxSignatureAdapter::class, DisabledETaxSignatureAdapter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
