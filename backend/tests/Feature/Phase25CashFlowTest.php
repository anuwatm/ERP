<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Models\Expense;
use App\Models\FinancialMetricLog;
use App\Models\ForecastScenario;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CashFlowForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase25CashFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function setupOrgUser(array $permissions = ['cash_flow.view', 'cash_flow.manage']): array
    {
        $user = User::factory()->create();
        $org = Organization::findOrFail($user->org_id);
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => 'finance_lead',
            'name' => 'Finance Lead',
        ]);

        $permissionModels = Permission::whereIn('code', $permissions)->get();
        $role->permissions()->attach($permissionModels->pluck('id'));
        $user->roles()->attach($role);

        return [$user, $org];
    }

    private function createBankAccount(Organization $org, float $balance = 500000.00, string $name = 'Kasikorn'): BankAccount
    {
        return BankAccount::create([
            'org_id' => $org->id,
            'bank_name' => $name,
            'account_name' => 'Main Operating',
            'account_number' => '1234567890',
            'account_number_hash' => hash('sha256', '1234567890'),
            'account_type' => 'savings',
            'currency' => 'THB',
            'base_balance' => $balance,
            'status' => 'active',
        ]);
    }

    public function test_default_scenarios_seeded_automatically(): void
    {
        [$user, $org] = $this->setupOrgUser();
        $service = app(CashFlowForecastService::class);

        $scenarios = $service->getOrCreateDefaultScenarios($org);

        $this->assertCount(3, $scenarios);
        $this->assertTrue($scenarios->contains('scenario_key', 'base'));
        $this->assertTrue($scenarios->contains('scenario_key', 'best'));
        $this->assertTrue($scenarios->contains('scenario_key', 'worst'));

        $base = $scenarios->firstWhere('scenario_key', 'base');
        $this->assertTrue((bool) $base->is_default);
        $this->assertEquals(1.0, (float) $base->ar_collection_rate);

        $worst = $scenarios->firstWhere('scenario_key', 'worst');
        $this->assertEquals(0.80, (float) $worst->ar_collection_rate);
        $this->assertEquals(15, (int) $worst->ar_delay_days);
    }

    public function test_inflow_projection_includes_ar_invoices_and_pipeline_deals(): void
    {
        [$user, $org] = $this->setupOrgUser();
        $service = app(CashFlowForecastService::class);
        $asOfDate = Carbon::parse('2026-10-01');

        // Initial cash
        $this->createBankAccount($org, 500000.00);

        // Customer & AR Invoice due in 15 days
        $customer = Customer::create([
            'org_id' => $org->id,
            'customer_code' => 'CUST001',
            'company_name' => 'Acme Corp',
            'customer_type' => 'company',
            'status' => 'active',
        ]);

        Invoice::create([
            'org_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-2026-001',
            'status' => 'sent',
            'tax_mode' => 'exclusive',
            'issue_date' => $asOfDate->toDateString(),
            'due_date' => $asOfDate->copy()->addDays(15)->toDateString(),
            'currency' => 'THB',
            'subtotal' => 100000,
            'tax_amount' => 7000,
            'total' => 107000,
            'base_total' => 107000,
            'balance_due' => 107000,
            'base_balance_due' => 107000,
        ]);

        // Sales Deal with 50% probability closing in 20 days
        Deal::create([
            'org_id' => $org->id,
            'customer_id' => $customer->id,
            'title' => 'Big Software Contract',
            'stage' => 'proposal',
            'currency' => 'THB',
            'value_amount' => 200000.00,
            'probability' => 50,
            'expected_close_date' => $asOfDate->copy()->addDays(20)->toDateString(),
        ]);

        $snapshot = $service->generateForecast($org, 'base', 60, $asOfDate, $user);

        $this->assertEquals(500000.00, (float) $snapshot->starting_cash);
        // Inflow: 107,000 (100% AR) + (200,000 * 50% stage probability * 100% multiplier = 100,000) = 207,000
        $this->assertEquals(207000.00, (float) $snapshot->projected_inflow);
        $this->assertGreaterThan(500000.00, (float) $snapshot->projected_ending_cash);
    }

    public function test_outflow_projection_includes_ap_expenses_pos_and_payroll(): void
    {
        [$user, $org] = $this->setupOrgUser();
        $service = app(CashFlowForecastService::class);
        $asOfDate = Carbon::parse('2026-10-01');

        // Initial cash
        $this->createBankAccount($org, 300000.00, 'SCB');

        $supplier = Supplier::create([
            'org_id' => $org->id,
            'supplier_code' => 'SUPP01',
            'name' => 'Cloud Provider',
            'tax_id' => '0105550000000',
            'status' => 'active',
        ]);

        // 1. AP Expense due in 10 days
        Expense::create([
            'org_id' => $org->id,
            'supplier_id' => $supplier->id,
            'expense_no' => 'EXP-001',
            'category' => 'hosting',
            'title' => 'Cloud Server',
            'status' => 'approved',
            'expense_date' => $asOfDate->toDateString(),
            'currency' => 'THB',
            'amount' => 50000,
            'base_amount' => 50000,
            'balance_due' => 50000,
            'base_balance_due' => 50000,
        ]);

        // 2. Open Purchase Order due in 25 days
        PurchaseOrder::create([
            'org_id' => $org->id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-001',
            'status' => 'approved',
            'order_date' => $asOfDate->toDateString(),
            'expected_date' => $asOfDate->copy()->addDays(25)->toDateString(),
            'currency' => 'THB',
            'subtotal' => 30000,
            'total' => 30000,
            'base_total' => 30000,
        ]);

        // 3. Active Employee with Payroll Profile
        $employee = Employee::create([
            'org_id' => $org->id,
            'employee_code' => 'EMP001',
            'first_name' => 'Somchai',
            'last_name' => 'Jaidee',
            'status' => 'active',
        ]);

        EmployeePayrollProfile::create([
            'org_id' => $org->id,
            'employee_id' => $employee->id,
            'monthly_salary' => 45000.00,
            'payment_method' => 'bank_transfer',
            'social_security_enabled' => true,
            'status' => 'active',
        ]);

        $snapshot = $service->generateForecast($org, 'base', 30, $asOfDate, $user);

        // Outflow should include:
        // Expense 50k + PO 30k + Payroll on Oct 25 (45k salary + employer social security ~47,250)
        $this->assertGreaterThanOrEqual(125000, (float) $snapshot->projected_outflow);
        $this->assertLessThan(300000, (float) $snapshot->projected_ending_cash);
    }

    public function test_multi_scenario_comparison_and_stress_testing(): void
    {
        [$user, $org] = $this->setupOrgUser();
        $service = app(CashFlowForecastService::class);
        $asOfDate = Carbon::parse('2026-10-01');

        $this->createBankAccount($org, 200000.00, 'BBL');

        $customer = Customer::create([
            'org_id' => $org->id,
            'customer_code' => 'CUST002',
            'company_name' => 'Beta Co',
            'customer_type' => 'company',
            'status' => 'active',
        ]);

        Invoice::create([
            'org_id' => $org->id,
            'customer_id' => $customer->id,
            'invoice_no' => 'INV-2026-002',
            'status' => 'sent',
            'tax_mode' => 'exclusive',
            'issue_date' => $asOfDate->toDateString(),
            'due_date' => $asOfDate->copy()->addDays(20)->toDateString(),
            'currency' => 'THB',
            'subtotal' => 100000,
            'tax_amount' => 7000,
            'total' => 107000,
            'base_total' => 107000,
            'balance_due' => 107000,
            'base_balance_due' => 107000,
        ]);

        $comparison = $service->getMultiScenarioComparison($org, 60, $asOfDate, $user);

        $this->assertArrayHasKey('snapshots', $comparison);
        $this->assertArrayHasKey('curves', $comparison);

        $baseEnding = (float) $comparison['snapshots']['base']->projected_ending_cash;
        $bestEnding = (float) $comparison['snapshots']['best']->projected_ending_cash;
        $worstEnding = (float) $comparison['snapshots']['worst']->projected_ending_cash;

        // Worst scenario has haircuts and buffers, so ending cash is lower than base
        $this->assertGreaterThanOrEqual($baseEnding, $bestEnding);
        $this->assertLessThanOrEqual($baseEnding, $worstEnding);
    }

    public function test_financial_health_ratios_and_runway_calculation(): void
    {
        [$user, $org] = $this->setupOrgUser();
        $service = app(CashFlowForecastService::class);
        $asOfDate = Carbon::parse('2026-10-01');

        $this->createBankAccount($org, 400000.00, 'KTB');

        $supplier = Supplier::create([
            'org_id' => $org->id,
            'supplier_code' => 'SUPP02',
            'name' => 'Office Supply',
            'tax_id' => '0105550000001',
            'status' => 'active',
        ]);

        Expense::create([
            'org_id' => $org->id,
            'supplier_id' => $supplier->id,
            'expense_no' => 'EXP-002',
            'category' => 'office',
            'title' => 'Office Supplies',
            'status' => 'approved',
            'expense_date' => $asOfDate->toDateString(),
            'currency' => 'THB',
            'amount' => 100000,
            'base_amount' => 100000,
            'balance_due' => 100000,
            'base_balance_due' => 100000,
        ]);

        $health = $service->calculateFinancialHealth($org, $asOfDate, $user);

        $this->assertInstanceOf(FinancialMetricLog::class, $health);
        $this->assertEquals(400000.00, (float) $health->current_cash);
        $this->assertEquals(100000.00, (float) $health->current_ap);
        $this->assertEquals(4.0, (float) $health->current_ratio);
        $this->assertEquals(4.0, (float) $health->quick_ratio);
        $this->assertGreaterThan(0, (float) $health->working_capital_runway_months);
    }

    public function test_authorization_and_multi_tenant_isolation(): void
    {
        // User without permission gets 403
        $unauthorizedUser = User::factory()->create();
        $this->actingAsOrgUser($unauthorizedUser)
            ->get(route('finance.cash-flow.index'))
            ->assertForbidden();

        // User with permission can view
        [$authorizedUser, $org] = $this->setupOrgUser(['cash_flow.view']);
        $this->actingAsOrgUser($authorizedUser)
            ->get(route('finance.cash-flow.index'))
            ->assertOk();

        // Org B cannot access or update Org A's scenario
        [$userOrgB, $orgB] = $this->setupOrgUser(['cash_flow.view', 'cash_flow.manage']);
        $scenarioOrgA = ForecastScenario::create([
            'org_id' => $org->id,
            'scenario_key' => 'custom',
            'name' => 'Custom Org A',
            'ar_collection_rate' => 100,
            'deal_multiplier' => 100,
            'ar_delay_days' => 0,
            'expense_buffer_percent' => 0,
            'payroll_buffer_percent' => 0,
            'is_default' => false,
        ]);

        $this->actingAsOrgUser($userOrgB)
            ->patch(route('finance.cash-flow.scenarios.update', $scenarioOrgA->id), [
                'name' => 'Hacked Scenario',
                'ar_collection_rate' => 50,
                'deal_multiplier' => 50,
                'ar_delay_days' => 10,
                'expense_buffer_percent' => 10,
                'payroll_buffer_percent' => 10,
            ])
            ->assertForbidden();
    }

    public function test_scenario_update_and_snapshot_generation(): void
    {
        [$user, $org] = $this->setupOrgUser(['cash_flow.view', 'cash_flow.manage']);
        $service = app(CashFlowForecastService::class);
        $scenarios = $service->getOrCreateDefaultScenarios($org);
        $base = $scenarios->firstWhere('scenario_key', 'base');

        // Update scenario parameters
        $this->actingAsOrgUser($user)
            ->patch(route('finance.cash-flow.scenarios.update', $base->id), [
                'name' => 'Updated Base Case',
                'ar_collection_rate' => 95,
                'deal_multiplier' => 90,
                'ar_delay_days' => 5,
                'expense_buffer_percent' => 10,
                'payroll_buffer_percent' => 5,
            ])
            ->assertRedirect();

        $base->refresh();
        $this->assertEquals('Updated Base Case', $base->name);
        $this->assertEquals(95.0, (float) $base->ar_collection_rate);

        // Snapshot generation
        $this->actingAsOrgUser($user)
            ->post(route('finance.cash-flow.snapshot'), [
                'horizon' => 60,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_flow_forecast_snapshots', [
            'org_id' => $org->id,
            'scenario_key' => 'base',
            'horizon_days' => 60,
        ]);
        $this->assertDatabaseHas('financial_metric_logs', [
            'org_id' => $org->id,
        ]);
    }

    public function test_artisan_cashflow_snapshot_command(): void
    {
        [$user, $org] = $this->setupOrgUser();

        $this->artisan('cashflow:snapshot', [
            '--org' => (string) $org->id,
            '--horizon' => 90,
        ])
            ->expectsOutputToContain('cash flow forecast snapshot(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('cash_flow_forecast_snapshots', [
            'org_id' => $org->id,
            'scenario_key' => 'base',
        ]);
    }
}
