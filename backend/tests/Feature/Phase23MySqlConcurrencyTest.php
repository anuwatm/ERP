<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentGatewayConfig;
use App\Models\User;
use App\Services\FinancialJournalService;
use App\Services\GatewaySettlementService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Phase23MySqlConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('PHASE23_MYSQL_CONCURRENCY') !== '1' || getenv('DB_URL') !== 'mysql://root:@127.0.0.1:33073/erp_phase23_test') {
            $this->markTestSkipped('Requires the isolated Phase 23 MySQL instance.');
        }
        parent::setUp();
    }

    public function test_simultaneous_signed_callbacks_commit_one_receipt_and_journal(): void
    {
        $owner = User::factory()->create();
        $bank = BankAccount::create(['org_id' => $owner->org_id, 'bank_name' => 'Test', 'account_name' => 'Test', 'account_number' => '1234567890', 'account_number_hash' => hash('sha256', '1234567890'), 'account_type' => 'savings', 'currency' => 'THB', 'status' => 'active']);
        $config = PaymentGatewayConfig::create(['org_id' => $owner->org_id, 'enabled' => true, 'provider' => 'settlement_hmac', 'qr_type' => 'biller', 'recipient_id' => '010555123456701', 'webhook_secret' => str_repeat('s', 40), 'bank_account_id' => $bank->id]);
        $customer = Customer::create(['org_id' => $owner->org_id, 'owner_id' => $owner->id, 'customer_code' => 'MYSQL', 'company_name' => 'Test', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $owner->org_id, 'customer_id' => $customer->id, 'invoice_no' => 'MYSQL1', 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'currency' => 'THB', 'base_currency' => 'THB', 'exchange_rate' => 1, 'subtotal' => 100, 'total' => 100, 'balance_due' => 100, 'base_subtotal' => 100, 'base_total' => 100, 'base_balance_due' => 100]);
        app(FinancialJournalService::class)->postInvoice($invoice, $owner->id);
        $tx = app(GatewaySettlementService::class)->intent($invoice);
        $command = [PHP_BINARY, base_path('tests/Support/gateway-settlement-worker.php'), $config->id, $tx->reference, now()->toIso8601String()];
        $workers = [new Process($command, base_path()), new Process($command, base_path())];
        foreach ($workers as $worker) {
            $worker->setTimeout(60)->start();
        }
        try {
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $this->assertSame('processed', json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertSame(1, JournalEntry::where('source_type', 'payment')->count());
        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
