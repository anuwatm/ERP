<?php

namespace Tests\Feature;

use App\Jobs\SubmitETaxDocument;
use App\Models\Customer;
use App\Models\ETaxConfig;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\ETax\ETaxDocumentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase24MySqlQueueTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('PHASE24_MYSQL_QUEUE') !== '1' || getenv('DB_URL') !== 'mysql://root:@127.0.0.1:33073/erp_phase24_test') {
            $this->markTestSkipped('Requires the isolated Phase 24 MySQL database.');
        }
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['queue.default' => 'database']);
    }

    public function test_database_worker_drains_duplicate_jobs_with_one_simulation_and_no_accounting(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'ETQ001', 'company_name' => 'Queue buyer', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'invoice_no' => 'ETQ001', 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => today(), 'currency' => 'THB', 'subtotal' => 100, 'tax_amount' => 7, 'total' => 107, 'balance_due' => 107]);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 100, 'sort_order' => 0]);
        ETaxConfig::create(['org_id' => $user->org_id, 'mode' => 'mock', 'signature_mode' => 'none']);
        $document = app(ETaxDocumentService::class)->generate($invoice, 'tax_invoice', $user->id);
        $document->update(['status' => 'queued']);

        SubmitETaxDocument::dispatch($document->id);
        SubmitETaxDocument::dispatch($document->id);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseHas('jobs', ['queue' => 'etax']);

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'etax', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 3])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('e_tax_submission_attempts', 1);
        $this->assertSame('simulated', $document->fresh()->status);
        $this->assertNull($document->fresh()->submitted_at);
        $this->assertNull($document->fresh()->accepted_at);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        Http::assertNothingSent();
    }
}
