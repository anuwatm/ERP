<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase5EndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_mvp_flow_invite_customer_deal_invoice_payment_project_task_dashboard(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', [
            'dashboard.view',
            'executive.dashboard.view',
            'users.create',
            'customers.create',
            'deals.create',
            'invoices.create',
            'payments.create',
            'projects.create',
            'projects.view',
            'tasks.create',
            'tasks.view',
        ]);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                'name' => 'E2E Member',
                'email' => 'e2e-member@example.com',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $memberRole->id,
            ])->assertRedirect();
        $assignee = User::where('email', 'e2e-member@example.com')->firstOrFail();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('customers.store'), [
                'company_name' => 'E2E Customer',
                'customer_type' => 'lead',
                'status' => 'active',
                'owner_id' => $owner->id,
            ])->assertRedirect();
        $customer = Customer::where('company_name', 'E2E Customer')->firstOrFail();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.store'), [
                'title' => 'E2E Deal',
                'customer_id' => $customer->id,
                'stage' => 'won',
                'value_amount' => '2000.00',
                'currency' => 'THB',
                'probability' => 100,
                'owner_id' => $owner->id,
            ])->assertRedirect();
        $deal = Deal::where('title', 'E2E Deal')->firstOrFail();

        $product = Product::create(['org_id' => $owner->org_id, 'sku' => 'E2E-SVC', 'name' => 'E2E Service', 'type' => 'service', 'price' => '1000.00']);
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'deal_id' => $deal->id,
                'status' => 'sent',
                'tax_mode' => 'no_tax',
                'issue_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'discount_amount' => '0.00',
                'currency' => 'THB',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'E2E service delivery',
                    'quantity' => '2',
                    'unit' => 'job',
                    'unit_price' => '1000.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '0.00',
                ]],
            ])->assertRedirect();
        $invoice = Invoice::where('deal_id', $deal->id)->firstOrFail();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload('600.00', 'e2e-flow-payment'))
            ->assertRedirect();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.projects.store', $deal))
            ->assertRedirect(route('projects.index'));
        $project = Project::where('deal_id', $deal->id)->firstOrFail();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.store'), $this->taskPayload($project, $assignee, ['due_date' => now()->subDay()->toDateString()]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame('600.00', $invoice->paid_amount);
        $this->assertSame('1400.00', $invoice->balance_due);

        $this->actingAsOrgUser($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('executiveSummary.sales.customers', 1)
                ->where('executiveSummary.sales.won_deals', 1)
                ->where('executiveSummary.sales.won_value', 2000)
                ->where('executiveSummary.finance.invoiced_revenue', 2000)
                ->where('executiveSummary.finance.cash_in', 600)
                ->where('executiveSummary.finance.outstanding_ar', 1400)
                ->where('executiveSummary.delivery.active_projects', 1)
                ->where('executiveSummary.delivery.overdue_tasks', 1)
                ->where('executiveSummary.delivery.project_profit', 2000)
                ->where('executiveSummary.delivery.delivery_risk_count', 1)
                ->missing('executiveSummary.cash_balance')
            );
    }

    public function test_e2e_role_isolation_and_multi_role_permission_union(): void
    {
        $sales = User::factory()->create();
        $other = User::factory()->create(['org_id' => $sales->org_id]);
        $this->attachRole($sales, 'sales_customers', ['dashboard.view', 'customers.view']);
        $this->attachRole($sales, 'sales_deals', ['deals.view']);

        Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Owned Customer', 'owner_id' => $sales->id]);
        $hiddenCustomer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000002', 'company_name' => 'Hidden Customer', 'owner_id' => $other->id]);
        Deal::create(['org_id' => $sales->org_id, 'title' => 'Hidden Deal', 'customer_id' => $hiddenCustomer->id, 'stage' => 'proposal', 'owner_id' => $other->id]);

        $this->actingAsOrgUser($sales)->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers', 1)
                ->where('customers.0.company_name', 'Owned Customer')
            );

        $this->actingAsOrgUser($sales)->get(route('deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('deals', 0));

        $this->actingAsOrgUser($sales)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('executiveSummary', null)
                ->where('financeSummary', null)
                ->where('deliverySummary', null)
            );
    }

    public function test_e2e_payment_reversal_updates_invoice_and_dashboard_metrics(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['dashboard.view', 'executive.dashboard.view', 'expenses.view', 'payments.create', 'payments.reverse']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Payment Customer', 'owner_id' => $finance->id]);
        $invoice = Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => now()->toDateString(), 'total' => '1000.00', 'balance_due' => '1000.00']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload('400.00', 'e2e-receipt'))
            ->assertRedirect();
        $receipt = Payment::where('invoice_id', $invoice->id)->where('entry_type', 'receipt')->firstOrFail();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), ['idempotency_key' => 'e2e-reversal'])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1000.00', $invoice->balance_due);
        $this->assertSame('sent', $invoice->status);

        $this->actingAsOrgUser($finance)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('executiveSummary.finance.cash_in', 0)
                ->where('financeSummary.cash_in', 0)
                ->where('financeSummary.cash_in_receipts', 400)
                ->where('financeSummary.cash_in_reversals', 400)
            );
    }

    public function test_e2e_invoice_totals_and_needs_sales_review_after_void(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['customers.view', 'deals.view', 'invoices.create', 'invoices.view', 'invoices.void']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Review Customer', 'owner_id' => $owner->id]);
        $deal = Deal::create(['org_id' => $owner->org_id, 'title' => 'Review Deal', 'customer_id' => $customer->id, 'stage' => 'won', 'value_amount' => '2140.00', 'owner_id' => $owner->id]);
        $product = Product::create(['org_id' => $owner->org_id, 'sku' => 'REV-SVC', 'name' => 'Review Service', 'type' => 'service', 'price' => '1000.00']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'deal_id' => $deal->id,
                'status' => 'draft',
                'tax_mode' => 'exclusive',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'discount_amount' => '100.00',
                'currency' => 'THB',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Review service',
                    'quantity' => '2',
                    'unit' => 'job',
                    'unit_price' => '1000.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ]],
            ])->assertRedirect();
        $invoice = Invoice::where('deal_id', $deal->id)->firstOrFail();

        $this->assertSame('2000.00', $invoice->subtotal);
        $this->assertSame('100.00', $invoice->discount_amount);
        $this->assertSame('140.00', $invoice->tax_amount);
        $this->assertSame('2040.00', $invoice->total);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('invoices.void', $invoice))
            ->assertRedirect();

        $this->actingAsOrgUser($owner)->get(route('deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deals.0.id', $deal->id)
                ->where('deals.0.needs_sales_review', true)
            );
    }

    public function test_security_review_has_no_sensitive_audit_payload_and_no_export_notification_or_public_api_routes(): void
    {
        $owner = User::factory()->create(['person_id' => '1234567890123']);
        $this->attachRole($owner, 'owner', ['users.create']);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                'name' => 'Security Invite',
                'email' => 'security-invite@example.com',
                'person_id' => '9876543210123',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $memberRole->id,
            ])->assertRedirect();

        $auditPayload = AuditLog::query()
            ->get(['before_json', 'after_json'])
            ->map(fn (AuditLog $log) => json_encode([$log->before_json, $log->after_json]))
            ->implode(' ');

        $this->assertStringNotContainsString('password', $auditPayload);
        $this->assertStringNotContainsString('invite_token_hash', $auditPayload);
        $this->assertStringNotContainsString('secret', $auditPayload);
        $this->assertStringNotContainsString('9876543210123', $auditPayload);

        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->values();
        $this->assertFalse($uris->contains(fn (string $uri) => Str::startsWith($uri, 'api/')));
        $this->assertFalse($uris->contains(fn (string $uri) => str_contains($uri, 'export')));
        $this->assertFalse($uris->contains(fn (string $uri) => $uri === 'notifications' || Str::startsWith($uri, 'notifications/')));
        $this->assertFalse($uris->contains(fn (string $uri) => $uri === 'notification-settings' || Str::startsWith($uri, 'notification-settings/')));
    }

    public function test_production_invite_does_not_flash_plain_token(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $csrfToken = Str::random(40);

        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['users.create']);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member_prod', 'name' => 'Member Prod', 'is_system' => true]);

        $this->actingAsOrgUser($owner)->withSession(['_token' => $csrfToken, 'auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                '_token' => $csrfToken,
                'name' => 'Production Invite',
                'email' => 'production-invite@example.com',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $memberRole->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Invite created.')
            ->assertSessionMissing('invite_url');

        $success = session('success');
        $this->assertIsString($success);
        $this->assertStringNotContainsString('Accept token:', $success);
        $this->assertStringNotContainsString('/accept-invite/', $success);
    }

    private function paymentPayload(string $amount, string $key): array
    {
        return [
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference_no' => $key,
            'idempotency_key' => $key,
        ];
    }

    private function taskPayload(Project $project, User $assignee, array $overrides = []): array
    {
        return array_merge([
            'project_id' => $project->id,
            'title' => 'E2E Task',
            'description' => 'E2E task description',
            'status' => 'todo',
            'priority' => 'urgent',
            'assignee_id' => $assignee->id,
            'due_date' => now()->addWeek()->toDateString(),
        ], $overrides);
    }

    public function test_audit_log_redaction_and_session_invalidation(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['users.update', 'users.disable']);

        $user = User::factory()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'person_id' => '1234567890123',
            'status' => 'active',
        ]);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $user->roles()->attach($memberRole->id);

        // Test AuditLog Redaction
        $log = AuditLog::create([
            'org_id' => $owner->org_id,
            'actor_user_id' => $owner->id,
            'action' => 'test.redaction',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'before_json' => ['password' => 'secret123', 'person_id' => '1234567890123', 'profile' => ['api_token' => 'tok_live']],
            'after_json' => ['password' => 'newsecret456', 'remember_token' => 'tokenabc', 'oauth' => ['access_token' => 'nested-token']],
        ]);

        $this->assertSame('[REDACTED]', $log->before_json['password']);
        $this->assertSame('1-2345-xxxxx-xx-3', $log->before_json['person_id']);
        $this->assertSame('[REDACTED]', $log->before_json['profile']['api_token']);
        $this->assertSame('[REDACTED]', $log->after_json['password']);
        $this->assertSame('[REDACTED]', $log->after_json['remember_token']);
        $this->assertSame('[REDACTED]', $log->after_json['oauth']['access_token']);

        // Set up active session in sessions table
        DB::table('sessions')->insert([
            'id' => 'sess_123',
            'user_id' => $user->id,
            'payload' => 'payload_data',
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);

        // Disable user and assert session is deleted and remember_token regenerated
        $originalToken = $user->remember_token;
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.disable', $user))
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('inactive', $user->status);
        $this->assertNotEquals($originalToken, $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        // Re-enable user
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.enable', $user))
            ->assertRedirect();
        $user->refresh();
        $this->assertSame('active', $user->status);

        // Put another session in sessions table
        DB::table('sessions')->insert([
            'id' => 'sess_456',
            'user_id' => $user->id,
            'payload' => 'payload_data',
            'last_activity' => time(),
        ]);
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);

        // Change user role and assert session is deleted
        $newRole = Role::create(['org_id' => $owner->org_id, 'code' => 'admin_role', 'name' => 'Admin Role', 'is_system' => true]);
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position,
                'branch_id' => $user->branch_id,
                'division_id' => $user->division_id,
                'department_id' => $user->department_id,
                'role_id' => $newRole->id,
            ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
