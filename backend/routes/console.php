<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
