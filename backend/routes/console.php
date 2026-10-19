<?php

use App\Jobs\SubmitETaxDocument;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\ETax\ETaxProviderGatewayService;
use App\Services\Finance\CashFlowForecastService;
use App\Services\NotificationOutboxService;
use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('etax:dispatch-pending {--limit=100}', function () {
    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 1000) {
        $this->error('Limit must be between 1 and 1000.');

        return self::FAILURE;
    }
    $ids = ETaxDocument::where('status', 'queued')
        ->whereIn('org_id', ETaxConfig::where('mode', 'mock')->select('org_id'))
        ->orderBy('created_at')->limit($limit)->pluck('id');
    foreach ($ids as $id) {
        SubmitETaxDocument::dispatch($id);
    }
    $this->info('Dispatched '.$ids->count().' mock simulation job(s).');

    return self::SUCCESS;
})->purpose('Recover pending local e-Tax simulations; never sends to an external provider.');

Artisan::command('invoices:mark-overdue', function () {
    $count = 0;
    $notificationCount = 0;
    $notifications = app(NotificationService::class);

    Invoice::whereIn('status', ['sent', 'partially_paid'])
        ->where('balance_due', '>', 0)
        ->whereNotNull('due_date')
        ->whereDate('due_date', '<', now()->toDateString())
        ->orderBy('id')
        ->chunkById(100, function ($invoices) use (&$count, &$notificationCount, $notifications): void {
            foreach ($invoices as $invoice) {
                $before = $invoice->only(['invoice_no', 'status', 'due_date', 'balance_due']);
                $invoice->update(['status' => 'overdue']);
                $count++;

                AuditLog::create([
                    'org_id' => $invoice->org_id,
                    'actor_user_id' => null,
                    'action' => 'invoice.mark_overdue',
                    'entity_type' => 'invoice',
                    'entity_id' => $invoice->id,
                    'before_json' => $before,
                    'after_json' => $invoice->fresh()->only(['invoice_no', 'status', 'due_date', 'balance_due']),
                ]);

                $notificationCount += notifyFinanceUsers($invoice, $notifications, 'invoice.overdue', "invoice.overdue:{$invoice->id}", "Invoice {$invoice->invoice_no} is overdue");
            }
        });

    $this->info("Marked {$count} invoice(s) overdue.");
    $this->info("Queued {$notificationCount} overdue notification(s).");

    return self::SUCCESS;
})->purpose('Mark overdue invoices from sent/partially paid invoices.');

Artisan::command('invoices:notify-due-soon {--days=3}', function () {
    $days = max(0, (int) $this->option('days'));
    $count = 0;
    $notifications = app(NotificationService::class);

    Invoice::whereIn('status', ['sent', 'partially_paid'])
        ->where('balance_due', '>', 0)
        ->whereNotNull('due_date')
        ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
        ->orderBy('id')
        ->chunkById(100, function ($invoices) use (&$count, $notifications): void {
            foreach ($invoices as $invoice) {
                $count += notifyFinanceUsers($invoice, $notifications, 'invoice.due_soon', "invoice.due_soon:{$invoice->id}", "Invoice {$invoice->invoice_no} is due soon");
            }
        });

    $this->info("Queued {$count} due-soon notification(s).");

    return self::SUCCESS;
})->purpose('Queue due-soon invoice notifications for finance users.');

Schedule::command('invoices:mark-overdue')->daily();
Schedule::command('invoices:notify-due-soon')->dailyAt('08:00');
Schedule::command('assets:depreciate')->monthlyOn(1, '01:00');
Schedule::command('fx:reverse-revaluations')->monthlyOn(1, '01:15');
Schedule::command('fx:revalue')->lastDayOfMonth('23:30');

Artisan::command('documents:check-expiry', function () {
    $notifications = app(NotificationService::class);
    $count = 0;
    Document::where('status', 'active')->whereHas('category', fn ($query) => $query->where('expiry_tracking_enabled', true))->whereNotNull('expires_at')->whereNotNull('renewal_alert_days')->orderBy('id')->chunkById(100, function ($documents) use ($notifications, &$count): void {
        foreach ($documents as $document) {
            if (now()->startOfDay()->gt($document->expires_at) || now()->addDays($document->renewal_alert_days)->startOfDay()->lt($document->expires_at)) {
                continue;
            }
            $owner = User::where('id', $document->owner_user_id)->where('org_id', $document->org_id)->first();
            if ($owner && $notifications->notify($owner, 'document.expiry', "document.expiry:{$document->id}:{$document->expires_at->toDateString()}", 'Document expiry reminder', "{$document->title} expires on {$document->expires_at->toDateString()}.", route('documents.index', [], false))) {
                $count++;
            }
        }
    });
    $this->info("Queued {$count} document expiry notification(s).");
})->purpose('Notify document owners about category-enabled expiry dates.');

Schedule::command('documents:check-expiry')->dailyAt('08:15');
Schedule::command('documents:enforce-retention')->dailyAt('08:30');

Artisan::command('notifications:dispatch {--limit=100}', function () {
    $count = app(NotificationOutboxService::class)->dispatchPending(max(1, min(500, (int) $this->option('limit'))));
    $this->info("Dispatched {$count} notification(s).");

    return self::SUCCESS;
})->purpose('Dispatch pending operational notification outbox records.');

Schedule::command('notifications:dispatch --limit=100')->everyMinute()->withoutOverlapping();

Artisan::command('notifications:daily-digest', function () {
    $notifications = app(NotificationService::class);
    $count = 0;
    User::where('status', 'active')
        ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'executive.dashboard.view'))
        ->orderBy('id')
        ->chunkById(100, function ($users) use ($notifications, &$count): void {
            foreach ($users as $user) {
                $due = Invoice::where('org_id', $user->org_id)->whereIn('status', ['sent', 'partially_paid'])->whereDate('due_date', '<=', now()->toDateString())->count();
                $overdue = Invoice::where('org_id', $user->org_id)->where('status', 'overdue')->count();
                if ($notifications->notify($user, 'operational.digest', 'operational.digest:'.$user->org_id.':'.today()->toDateString(), 'Daily operations digest', "Invoices due: {$due}; overdue invoices: {$overdue}.", route('finance.dashboard', [], false))) {
                    $count++;
                }
            }
        });
    $this->info("Queued {$count} daily digest notification(s).");

    return self::SUCCESS;
})->purpose('Queue a daily operations digest without cash balance data.');

Schedule::command('notifications:daily-digest')->dailyAt('08:45')->withoutOverlapping();

if (! function_exists('notifyFinanceUsers')) {
    function notifyFinanceUsers(Invoice $invoice, NotificationService $notifications, string $type, string $dedupeKey, string $title): int
    {
        $count = 0;

        User::where('org_id', $invoice->org_id)
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'invoices.view'))
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($invoice, $notifications, $type, $dedupeKey, $title, &$count): void {
                foreach ($users as $user) {
                    $sent = $notifications->notify(
                        $user,
                        $type,
                        $dedupeKey,
                        $title,
                        'Balance due: '.number_format((float) $invoice->balance_due, 2).' / Due date: '.($invoice->due_date?->toDateString() ?: '-'),
                        route('invoices.index', [], false)
                    );

                    if ($sent) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}

Artisan::command('etax:inquire-status {--limit=50}', function () {
    $limit = max(1, min(200, (int) $this->option('limit')));
    $gateway = app(ETaxProviderGatewayService::class);
    $count = 0;

    ETaxDocument::query()
        ->whereIn('status', ['submitted', 'queued'])
        ->whereHas('attempts')
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('e_tax_configs')
                ->whereColumn('e_tax_configs.org_id', 'e_tax_documents.org_id')
                ->where('e_tax_configs.mode', 'provider');
        })
        ->take($limit)
        ->each(function (ETaxDocument $document) use ($gateway, &$count) {
            try {
                $gateway->inquire($document);
                $count++;
            } catch (Throwable $e) {
                // Keep polling others
            }
        });

    $this->info("Inquired {$count} e-Tax document(s).");

    return self::SUCCESS;
})->purpose('Inquire status of pending submitted e-Tax documents from provider gateway.');

Schedule::command('etax:inquire-status --limit=50')->everyThirtyMinutes()->withoutOverlapping();

Artisan::command('cashflow:snapshot {--org=} {--horizon=90}', function () {
    $horizon = max(14, min(180, (int) $this->option('horizon')));
    $orgId = $this->option('org');

    $orgQuery = Organization::query();
    if ($orgId) {
        $orgQuery->where('id', $orgId);
    }

    $orgs = $orgQuery->get();
    $service = app(CashFlowForecastService::class);
    $snapshotCount = 0;

    foreach ($orgs as $org) {
        $scenarios = $service->getOrCreateDefaultScenarios($org);
        foreach ($scenarios as $scenario) {
            $service->generateForecast($org, $scenario->scenario_key, $horizon);
            $snapshotCount++;
        }
        $service->calculateFinancialHealth($org);
    }

    $this->info("Generated {$snapshotCount} cash flow forecast snapshot(s) across {$orgs->count()} organization(s).");

    return self::SUCCESS;
})->purpose('Generate scheduled cash flow forecast snapshots and financial health metrics.');

Schedule::command('cashflow:snapshot --horizon=90')->dailyAt('02:00');
Schedule::command('external-api:dispatch-webhooks --limit=20')->everyMinute()->withoutOverlapping();
