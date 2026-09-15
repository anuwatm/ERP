<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2314MasterDialogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_and_cheque_dialog_retries_preserve_rows_and_totals(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'documents', 'name' => 'Documents', 'is_system' => true]);
        foreach (['quotations.create', 'purchase_orders.create', 'cheques.manage'] as $code) {
            $role->permissions()->attach(Permission::firstOrCreate(['code' => $code], ['module' => 'finance', 'action' => 'create']));
        }
        $user->roles()->attach($role);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'RETRY-Q', 'company_name' => 'Fixture customer', 'owner_id' => $user->id, 'status' => 'active']);
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'RETRY-PO', 'name' => 'Fixture supplier', 'status' => 'active']);
        $line = ['product_id' => '', 'description' => 'Fixture line', 'quantity' => '2', 'unit' => 'each', 'unit_price' => '100', 'discount_amount' => '0', 'tax_rate' => '7'];
        $base = ['status' => 'draft', 'tax_mode' => 'exclusive', 'discount_amount' => '10', 'currency' => 'THB', 'items' => [$line]];
        $cases = [
            ['quotations.store', 'quotations', array_merge($base, ['customer_id' => $customer->id, 'deal_id' => '', 'issue_date' => '2026-09-14', 'valid_until' => '', 'notes' => 'Quotation note'])],
            ['purchase-orders.store', 'purchase_orders', array_merge($base, ['supplier_id' => $supplier->id, 'order_date' => '2026-09-14', 'expected_date' => '', 'note' => 'Purchase note'])],
            ['cheques.store', 'cheques', ['bank_account_id' => '', 'direction' => 'issued', 'cheque_no' => 'RETRY-CHQ', 'bank_name' => 'Fixture bank', 'drawer_or_payee' => 'Fixture payee', 'amount' => '203.30', 'issue_date' => '2026-09-14', 'due_date' => '2026-09-15']],
        ];
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        foreach ($cases as [$routeName, $table, $payload]) {
            $headers = ['X-Form-Submission' => (string) Str::uuid()];
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $this->post(route($routeName), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
            }
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseHas('quotations', ['total' => '203.30', 'notes' => 'Quotation note', 'valid_until' => null]);
        $this->assertDatabaseHas('purchase_orders', ['total' => '203.30', 'note' => 'Purchase note', 'expected_date' => null]);
        $this->assertDatabaseHas('cheques', ['bank_account_id' => null, 'direction' => 'issued', 'status' => 'registered']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_account_and_journal_dialog_retries_preserve_posting_contracts(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'accountant', 'name' => 'Accountant', 'is_system' => true]);
        foreach (['accounting.chart_accounts.manage', 'accounting.journals.post'] as $code) {
            $role->permissions()->attach(Permission::firstOrCreate(['code' => $code], ['module' => 'accounting', 'action' => 'manage']));
        }
        $user->roles()->attach($role);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $headers = ['X-Form-Submission' => (string) Str::uuid()];
        $account = ['code' => '1199', 'name' => 'Fixture cash', 'account_type' => 'asset', 'is_postable' => true];
        $this->post(route('accounting.chart-of-accounts.store'), array_merge($account, ['name' => '']), $headers)->assertSessionHasErrors('name');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('accounting.chart-of-accounts.store'), $account, $headers)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(1, DB::table('chart_of_accounts')->where('org_id', $user->org_id)->where('code', '1199')->count());
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '1199', 'normal_balance' => 'debit']);
        $journal = ['posting_date' => '2026-09-13', 'description' => 'Fixture posting', 'idempotency_key' => (string) Str::uuid(), 'lines' => [
            ['account_code' => '1199', 'debit' => '100.25', 'credit' => ''],
            ['account_code' => '3100', 'debit' => '', 'credit' => '100.25'],
        ]];
        $invalid = $journal;
        $invalid['lines'][0]['account_code'] = '';
        $this->post(route('accounting.journals.store'), $invalid)->assertSessionHasErrors('lines.0.account_code');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post(route('accounting.journals.store'), $journal)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertDatabaseHas('journal_entries', ['source_id' => $journal['idempotency_key'], 'status' => 'posted']);
    }

    public function test_period_retry_and_fx_settings_preserve_their_contracts(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'finance', 'name' => 'Finance', 'is_system' => true]);
        foreach (['accounting.periods.manage', 'fx.manage'] as $code) {
            $role->permissions()->attach(Permission::firstOrCreate(['code' => $code], ['module' => 'finance', 'action' => 'manage']));
        }
        $user->roles()->attach($role);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $headers = ['X-Form-Submission' => (string) Str::uuid()];
        $period = ['name' => 'September fixture', 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
        $this->post(route('accounting.periods.store'), array_merge($period, ['end_date' => '2026-08-31']), $headers)->assertSessionHasErrors('end_date');
        $this->post(route('accounting.periods.store'), $period, $headers)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('accounting.periods.store'), $period, $headers)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_periods', 1);
        $this->post(route('currencies.store'), ['code' => 'USD', 'name' => 'Dollar', 'decimal_places' => 4, 'status' => 'inactive'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('organization_currencies', ['org_id' => $user->org_id, 'code' => 'USD', 'decimal_places' => 4, 'status' => 'inactive']);
        $payload = ['quote_currency' => 'USD', 'rate_date' => '2026-09-13', 'rate' => '36.123456', 'source' => 'fixture'];
        $this->post(route('exchange-rates.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('exchange-rates.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertDatabaseHas('exchange_rates', ['quote_currency' => 'USD', 'source' => 'fixture']);
        $this->assertSame('2026-09-13', ExchangeRate::firstOrFail()->rate_date->toDateString());
        $this->post(route('exchange-rates.store'), array_merge($payload, ['rate' => '37.123456']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('exchange_rates', 1);
        $this->assertSame('37.123456', ExchangeRate::firstOrFail()->rate);
    }

    public function test_master_dialog_creates_can_retry_without_duplicate_rows_or_secret_receipts(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'finance', 'name' => 'Finance', 'is_system' => true]);
        foreach (['products.manage', 'suppliers.create', 'treasury.accounts.manage'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'finance', 'action' => 'manage']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
        $cases = [
            ['products.store', Product::class, ['name' => 'Retry product', 'type' => 'service', 'sku' => '', 'barcode' => '', 'price' => '12.34', 'cost' => '1.25', 'is_active' => false, 'description' => 'Preserved description', 'reorder_point' => '2.50']],
            ['suppliers.store', Supplier::class, ['name' => 'Retry supplier', 'status' => 'inactive', 'address' => 'Fixture address', 'email' => 'supplier@example.test']],
            ['bank-accounts.store', BankAccount::class, ['bank_name' => 'Fixture bank', 'bank_code' => '', 'branch_name' => '', 'account_name' => 'Fixture account', 'account_number' => 'TEST23140001', 'account_type' => 'savings', 'currency' => 'THB', 'is_cash_account' => false, 'status' => 'active', 'opening_balance' => '123.45', 'opening_balance_date' => '2026-09-13']],
        ];
        foreach ($cases as [$routeName, $model, $payload]) {
            $headers = ['X-Form-Submission' => (string) Str::uuid()];
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
                    ->post(route($routeName), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
            }
            $this->assertSame(1, $model::where('org_id', $user->org_id)->count());
        }
        $this->assertDatabaseCount('form_submissions', 3);
        $this->assertDatabaseHas('products', ['description' => 'Preserved description', 'is_active' => false]);
        $this->assertDatabaseHas('suppliers', ['address' => 'Fixture address', 'status' => 'inactive']);
        $this->assertSame('123.45', BankAccount::firstOrFail()->opening_balance);
        $this->assertStringNotContainsString('TEST23140001', json_encode(DB::table('form_submissions')->get()));
        $role->permissions()->detach();
        $user->unsetRelation('roles');
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-accounts.store'), $cases[2][2], $headers)->assertForbidden();
    }
}
