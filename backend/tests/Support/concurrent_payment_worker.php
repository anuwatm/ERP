<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

[$script, $databasePath, $userId, $invoiceId, $amount, $idempotencyKey, $startFile, $holdMicros] = $argv;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$databasePath);
putenv('DB_FOREIGN_KEYS=true');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => $databasePath,
    'database.connections.sqlite.foreign_key_constraints' => true,
    'cache.default' => 'array',
    'session.driver' => 'array',
    'queue.default' => 'sync',
]);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 5000');

$deadline = microtime(true) + 10;
while (! file_exists($startFile)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, 'Timed out waiting for start file.'.PHP_EOL);
        exit(2);
    }

    usleep(10000);
}

try {
    $user = User::findOrFail($userId);

    DB::transaction(function () use ($user, $invoiceId, $amount, $idempotencyKey, $holdMicros): void {
        $lockedInvoice = Invoice::where('id', $invoiceId)
            ->where('org_id', $user->org_id)
            ->lockForUpdate()
            ->firstOrFail();

        usleep(250000);

        if (Payment::where('org_id', $user->org_id)->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        if ($lockedInvoice->status === 'void') {
            throw ValidationException::withMessages(['invoice' => 'Cannot record payment on a void invoice.']);
        }

        $receiptAmount = round((float) $amount, 2);
        $balanceDue = round((float) $lockedInvoice->balance_due, 2);

        if ($receiptAmount > $balanceDue) {
            throw ValidationException::withMessages(['amount' => 'Payment exceeds balance due.']);
        }

        Payment::create([
            'org_id' => $lockedInvoice->org_id,
            'invoice_id' => $lockedInvoice->id,
            'entry_type' => 'receipt',
            'amount' => $receiptAmount,
            'payment_date' => '2026-07-28',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'RACE-'.$idempotencyKey,
            'note' => 'Concurrent race receipt',
            'idempotency_key' => $idempotencyKey,
            'created_by' => $user->id,
        ]);

        usleep((int) $holdMicros);

        $paidAmount = (float) Payment::where('invoice_id', $lockedInvoice->id)
            ->where('entry_type', 'receipt')
            ->sum('amount');
        $reversedAmount = (float) Payment::where('invoice_id', $lockedInvoice->id)
            ->where('entry_type', 'reversal')
            ->sum('amount');
        $netPaid = round($paidAmount - $reversedAmount, 2);
        $newBalance = max(0, round((float) $lockedInvoice->total - $netPaid, 2));

        $lockedInvoice->update([
            'paid_amount' => $netPaid,
            'balance_due' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : ($netPaid > 0 ? 'partially_paid' : 'sent'),
            'paid_at' => $newBalance <= 0 ? now() : null,
            'updated_by' => $user->id,
        ]);
    });

    exit(0);
} catch (ValidationException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(22);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
