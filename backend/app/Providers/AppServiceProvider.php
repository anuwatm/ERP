<?php

namespace App\Providers;

use App\Services\ETax\ETaxSignatureAdapter;
use App\Services\ETax\ETaxSubmissionAdapter;
use App\Services\ETax\ETaxXmlSignatureService;
use App\Services\ETax\RdApiSubmissionAdapter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ETaxSubmissionAdapter::class, RdApiSubmissionAdapter::class);
        $this->app->bind(ETaxSignatureAdapter::class, ETaxXmlSignatureService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
