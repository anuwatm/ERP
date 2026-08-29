<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\FxRevaluation;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FxRateService;
use App\Services\FxRevaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase14FxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_rate_snapshot_uses_latest_rate_on_or_before_transaction_date(): void
    {
        $user = User::factory()->create();
        $this->rate($user, '2026-08-01', '36.100000');
        $this->rate($user, '2026-08-10', '36.500000');

        $snapshot = app(FxRateService::class)->snapshot($user->org_id, 'USD', '2026-08-15', ['total' => 100, 'tax_amount' => 7]);

        $this->assertSame('THB', $snapshot['base_currency']);
        $this->assertSame('36.500000', $snapshot['exchange_rate']);
        $this->assertSame(3650.0, $snapshot['base_total']);
        $this->assertSame(255.5, $snapshot['base_tax_amount']);
    }

    public function test_foreign_currency_receipt_posts_realized_fx_gain(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['payments.create']);
        $invoice = $this->invoice($user, '100.00', '3600.00');
        $this->rate($user, '2026-08-20', '37.000000');

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), ['amount' => '100.00', 'payment_date' => '2026-08-20', 'payment_method' => 'bank_transfer', 'idempotency_key' => 'phase14-gain'])
            ->assertRedirect();

        $entry = JournalEntry::where('source_type', 'payment')->firstOrFail()->load('lines.account');
        $this->assertEquals(3700.0, $entry->lines->sum('debit'));
        $this->assertEquals(3700.0, $entry->lines->sum('credit'));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '4300' && (float) $line->credit === 100.0));
    }

    public function test_month_end_revaluation_is_idempotent_and_reversible(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoice($user, '100.00', '3600.00');
        $this->rate($user, '2026-08-31', '37.000000');
        $service = app(FxRevaluationService::class);

        $this->assertSame(1, $service->revalueReceivables($user->org_id, '2026-08', $user->id));
        $this->assertSame(0, $service->revalueReceivables($user->org_id, '2026-08', $user->id));
        $invoice->refresh();
        $this->assertSame('3700.00', $invoice->base_balance_due);
        $this->assertSame(1, FxRevaluation::count());
        $this->assertSame(1, $service->reverseMonth($user->org_id, '2026-08', $user->id));
        $this->assertSame('3600.00', $invoice->fresh()->base_balance_due);
        $this->assertNotNull(FxRevaluation::firstOrFail()->reversed_at);
    }

    private function invoice(User $user, string $total, string $baseTotal): Invoice
    {
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'FX-'.Str::upper(Str::random(6)), 'company_name' => 'FX Customer', 'owner_id' => $user->id]);

        return Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'FX-'.Str::upper(Str::random(8)), 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => '2026-08-01', 'subtotal' => $total, 'total' => $total, 'paid_amount' => 0, 'balance_due' => $total, 'currency' => 'USD', 'base_currency' => 'THB', 'exchange_rate' => '36.000000', 'base_subtotal' => $baseTotal, 'base_total' => $baseTotal, 'base_paid_amount' => 0, 'base_balance_due' => $baseTotal]);
    }

    private function rate(User $user, string $date, string $rate): void
    {
        ExchangeRate::create(['org_id' => $user->org_id, 'base_currency' => 'THB', 'quote_currency' => 'USD', 'rate_date' => $date, 'rate' => $rate, 'created_by' => $user->id]);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'phase14_fx', 'name' => 'Phase 14 FX']);
        foreach ($codes as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);
    }
}
