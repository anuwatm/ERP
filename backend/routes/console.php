<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('invoices:mark-overdue', function () {
    $count = 0;

    Invoice::whereIn('status', ['sent', 'partially_paid'])
        ->where('balance_due', '>', 0)
        ->whereNotNull('due_date')
        ->whereDate('due_date', '<', now()->toDateString())
        ->orderBy('id')
        ->chunkById(100, function ($invoices) use (&$count): void {
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
            }
        });

    $this->info("Marked {$count} invoice(s) overdue.");

    return self::SUCCESS;
})->purpose('Mark overdue invoices from sent/partially paid invoices.');

Schedule::command('invoices:mark-overdue')->daily();
