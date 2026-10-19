<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\StockMovement;
use App\Models\Task;
use App\Models\WorkflowInstance;
use App\Observers\ExternalWebhookObserver;
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
        foreach ([Invoice::class, Task::class, StockMovement::class, WorkflowInstance::class] as $model) {
            $model::observe(ExternalWebhookObserver::class);
        }
        Vite::prefetch(concurrency: 3);
    }
}
