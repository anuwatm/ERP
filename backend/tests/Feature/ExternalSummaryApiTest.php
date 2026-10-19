<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\IntegrationApiKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\ExternalSummaryService;
use App\Services\TwoFactorPolicyService;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExternalSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(5, 0));
    }

    private function manager(array $permissions = ['api_keys.view', 'api_keys.manage']): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'admin', 'name' => 'Admin']);
        $role->permissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function credential(User $user, array $scopes = IntegrationApiKey::SCOPES): array
    {
        $prefix = 'erp_'.bin2hex(random_bytes(12));
        $secret = $prefix.'.'.bin2hex(random_bytes(32));
        $key = IntegrationApiKey::create([
            'org_id' => $user->org_id, 'name' => 'Reporting app', 'prefix' => $prefix,
            'secret_hash' => hash('sha256', $secret), 'scopes' => $scopes,
            'created_by' => $user->id, 'expires_at' => now()->addDays(90),
        ]);

        return [$key, $secret];
    }

    private function customer(User $user): Customer
    {
        return Customer::create(['org_id' => $user->org_id, 'customer_code' => '000001', 'company_name' => 'Private customer', 'status' => 'active']);
    }

    private function invoice(User $user, Customer $customer, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'org_id' => $user->org_id, 'customer_id' => $customer->id, 'invoice_no' => Str::random(12),
            'status' => 'partially_paid', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-17',
            'total' => '1000.10', 'base_total' => '1000.10', 'balance_due' => '600.10', 'base_balance_due' => '600.10',
            'currency' => 'THB', 'base_currency' => 'THB',
        ], $overrides));
    }

    public function test_creation_reveals_secret_once_and_never_serializes_hash_in_list_audit_or_session(): void
    {
        $user = $this->manager();
        $response = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'Analytics', 'scopes' => ['summary:sales:read']])
            ->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $secret = $response->json('secret');
        $key = IntegrationApiKey::firstOrFail();
        $this->assertSame(hash('sha256', $secret), $key->secret_hash);
        $this->assertSame(90, (int) now()->diffInDays($key->expires_at));
        $this->assertStringNotContainsString($secret, json_encode($this->app['session']->all()));
        $this->assertStringNotContainsString($key->secret_hash, $response->getContent());
        $this->assertStringNotContainsString($secret, AuditLog::where('action', 'api_key.create')->firstOrFail()->toJson());
        $this->get(route('settings.api-access.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/ApiAccess')->has('keys', 1)->missing('secret')->missing('keys.0.secret_hash')->where('keys.0.name', 'Analytics'));
    }

    public function test_key_management_requires_permissions_password_and_active_user(): void
    {
        $viewer = $this->manager(['api_keys.view']);
        $payload = ['name' => 'App', 'scopes' => ['summary:sales:read']];
        $this->actingAs($viewer)->postJson(route('settings.api-access.store'), $payload)->assertForbidden();
        $admin = $this->manager();
        $this->actingAs($admin)->postJson(route('settings.api-access.store'), $payload)->assertStatus(423);
        $admin->update(['status' => 'inactive']);
        $this->withSession(['auth.password_confirmed_at' => time()])->postJson(route('settings.api-access.store'), $payload)->assertForbidden();
    }

    public function test_two_factor_policy_requires_enrollment_for_admin_management(): void
    {
        $admin = $this->manager();
        Setting::create(['org_id' => $admin->org_id, 'key' => TwoFactorPolicyService::SETTING_KEY, 'value_json' => ['enabled' => true, 'required_for_privileged_roles' => true]]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'App', 'scopes' => ['summary:sales:read']])->assertRedirect(route('two-factor.setup'));
        $this->assertDatabaseCount('integration_api_keys', 0);
    }

    public function test_key_validation_limits_expiry_scopes_and_active_key_count(): void
    {
        $admin = $this->manager();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->postJson(route('settings.api-access.store'), ['name' => 'App', 'scopes' => ['*'], 'lifetime_days' => 366])->assertUnprocessable()->assertJsonValidationErrors(['scopes.0', 'lifetime_days']);
        $this->postJson(route('settings.api-access.store'), ['name' => 'App', 'scopes' => []])->assertUnprocessable();
        config(['external_api.max_active_keys' => 1]);
        [$key] = $this->credential($admin);
        $this->postJson(route('settings.api-access.store'), ['name' => 'App', 'scopes' => ['summary:sales:read']])->assertUnprocessable();
        $key->update(['revoked_at' => now()]);
        $this->postJson(route('settings.api-access.store'), ['name' => 'App', 'scopes' => ['summary:sales:read']])->assertCreated();
    }

    public function test_no_key_wrong_key_query_key_expired_key_and_revoked_key_are_unauthorized(): void
    {
        [$key, $secret] = $this->credential($this->manager());
        $this->getJson('/api/v1/summaries/sales')->assertUnauthorized()->assertJsonStructure(['code', 'message', 'request_id']);
        $this->withToken($secret.'x')->getJson('/api/v1/summaries/sales')->assertUnauthorized();
        $this->withHeader('Authorization', '')->getJson('/api/v1/summaries/sales?key='.$secret)->assertUnauthorized();
        $key->update(['expires_at' => now()->subSecond()]);
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertUnauthorized();
        $key->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->getJson('/api/v1/summaries/sales')->assertUnauthorized();
    }

    public function test_key_scopes_and_web_session_cannot_substitute_for_one_another(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin, ['summary:sales:read']);
        $this->withToken($secret)->getJson('/api/v1/summaries/finance')->assertForbidden();
        $this->getJson('/api/v1/summaries/sales')->assertOk();
        $this->getJson(route('settings.api-access.index'))->assertUnauthorized();
        $this->actingAs($admin)->withHeader('Authorization', '')->getJson('/api/v1/summaries/sales')->assertUnauthorized();
    }

    public function test_update_and_revoke_are_tenant_scoped_and_rotation_does_not_grant_new_scopes(): void
    {
        $admin = $this->manager();
        [$key, $secret] = $this->credential($admin, ['summary:sales:read']);
        $foreign = $this->manager();
        [$foreignKey] = $this->credential($foreign);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
        $this->postJson(route('settings.api-access.revoke', $foreignKey))->assertForbidden();
        $this->patchJson(route('settings.api-access.update', $foreignKey), ['name' => 'Stolen', 'expires_at' => now()->addDay()->toIso8601String()])->assertForbidden();
        $this->get(route('settings.api-access.index'))->assertInertia(fn (Assert $page) => $page->has('keys', 1)->where('keys.0.id', $key->id));
        $this->patchJson(route('settings.api-access.update', $key), ['name' => 'Renamed', 'expires_at' => now()->addDays(10)->toIso8601String(), 'scopes' => IntegrationApiKey::SCOPES])->assertUnprocessable();
        $this->patchJson(route('settings.api-access.update', $key), ['name' => 'Renamed', 'expires_at' => now()->addDays(10)->setTimezone('Asia/Bangkok')->toIso8601String()])->assertOk()->assertJsonMissingPath('secret');
        $this->assertSame(now()->addDays(10)->timestamp, $key->fresh()->expires_at->timestamp);
        $new = $this->postJson(route('settings.api-access.store'), ['name' => 'Rotation', 'scopes' => ['summary:finance:read']])->assertCreated()->json('secret');
        $this->withToken($secret)->getJson('/api/v1/summaries/finance')->assertForbidden();
        $this->withToken($new)->getJson('/api/v1/summaries/finance')->assertOk();
        $this->postJson(route('settings.api-access.revoke', $key))->assertOk();
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertUnauthorized();
        $this->postJson(route('settings.api-access.revoke', $key))->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'api_key.revoke')->where('entity_id', $key->id)->count());
        $this->patchJson(route('settings.api-access.update', $key), ['name' => 'Revive', 'expires_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
    }

    public function test_rate_limits_and_telemetry_do_not_log_credentials(): void
    {
        config(['external_api.requests_per_minute' => 2]);
        [$key, $secret] = $this->credential($this->manager());
        Log::spy();
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertOk()->assertHeader('X-RateLimit-Remaining', '1')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/summaries/finance')->assertOk();
        $this->getJson('/api/v1/summaries/sales')->assertStatus(429)->assertHeader('Retry-After');
        $this->assertNotNull($key->fresh()->last_used_at);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'external_summary_api' && $context['key_id'] === $key->id && ! str_contains(json_encode($context), $secret))->times(3);
    }

    public function test_invalid_authentication_has_separate_ip_limit_and_disabled_org_is_rejected(): void
    {
        config(['external_api.invalid_requests_per_minute' => 2]);
        $this->getJson('/api/v1/summaries/sales')->assertUnauthorized();
        $this->getJson('/api/v1/summaries/sales')->assertUnauthorized();
        $this->getJson('/api/v1/summaries/sales')->assertStatus(429)->assertHeader('Retry-After');
        Cache::flush();
        $admin = $this->manager();
        [, $secret] = $this->credential($admin);
        $admin->organization->update(['status' => 'inactive']);
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertUnauthorized();
    }

    public function test_filters_reject_tenant_branch_unknown_invalid_and_oversized_windows(): void
    {
        [, $secret] = $this->credential($this->manager());
        foreach (['org_id=anything', 'branch_id=anything', 'unknown=1', 'start_date=2026-10-01', 'start_date=wrong&end_date=2026-10-01', 'start_date=2026-10-31&end_date=2026-10-01', 'start_date=2025-01-01&end_date=2026-10-01', 'start_date[]=2026-10-01&end_date=2026-10-01'] as $query) {
            $this->withToken($secret)->getJson('/api/v1/summaries/sales?'.$query)->assertUnprocessable()->assertJsonPath('code', 'invalid_filters');
        }
        $this->json('GET', '/api/v1/summaries/sales', ['org_id' => 'body-tenant'])->assertUnprocessable();
    }

    public function test_empty_org_contract_method_rejection_and_sanitized_server_errors(): void
    {
        [, $secret] = $this->credential($this->manager());
        $this->withToken($secret)->get('/api/v1/summaries/sales')->assertOk()->assertJsonPath('data.new_customers', 0)->assertJsonPath('data.deals_created_by_current_status', [])->assertJsonPath('meta.timezone', 'Asia/Bangkok');
        $this->getJson('/api/v1/summaries/finance')->assertOk()->assertJsonPath('data.period.invoiced_revenue', [])->assertJsonPath('meta.period.start_date', '2026-10-01');
        $this->postJson('/api/v1/summaries/sales')->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
        $this->getJson('/api/v1/summaries/unknown')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->mock(ExternalSummaryService::class)->shouldReceive('sales')->andThrow(new \RuntimeException('SQL secret='.$secret));
        Log::spy();
        $response = $this->getJson('/api/v1/summaries/sales')->assertStatus(500)->assertJsonPath('code', 'server_error');
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString('SQL', $response->getContent());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'external_summary_api_error'
            && ! str_contains(json_encode($context), $secret) && ! isset($context['exception']))->once();
    }

    public function test_sales_summary_uses_local_day_boundaries_currency_groups_and_current_status_cohort(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin);
        $customer = $this->customer($admin);
        $customer->forceFill(['created_at' => '2026-09-30 17:00:00'])->save(); // Oct 1 midnight Bangkok.
        foreach ([['2026-09-30 16:59:59', 'THB', '900.00'], ['2026-09-30 17:00:00', 'THB', '10.10'], ['2026-10-01 16:59:59', 'USD', '2.25'], ['2026-10-01 17:00:00', 'THB', '800.00']] as [$at, $currency, $amount]) {
            $deal = Deal::create(['org_id' => $admin->org_id, 'customer_id' => $customer->id, 'title' => 'Private title', 'stage' => 'won', 'value_amount' => $amount, 'currency' => $currency]);
            $deal->forceFill(['created_at' => $at])->save();
        }
        Quotation::create(['org_id' => $admin->org_id, 'customer_id' => $customer->id, 'quotation_no' => 'Q-1', 'status' => 'draft', 'issue_date' => '2026-10-01', 'total' => '20.20', 'currency' => 'THB']);
        $foreign = $this->manager();
        $this->customer($foreign);
        $response = $this->withToken($secret)->getJson('/api/v1/summaries/sales?start_date=2026-10-01&end_date=2026-10-01')->assertOk()
            ->assertJsonPath('data.new_customers', 1)
            ->assertJsonPath('data.deals_created_by_current_status.0.amount', '10.10')
            ->assertJsonPath('data.deals_created_by_current_status.1.currency', 'USD')
            ->assertJsonPath('data.deals_created_by_current_status.1.amount', '2.25')
            ->assertJsonPath('data.quotations_issued_by_current_status.0.status', 'draft');
        $this->assertStringNotContainsString('Private', $response->getContent());
        $customer->delete();
        Deal::where('org_id', $admin->org_id)->delete();
        Quotation::where('org_id', $admin->org_id)->delete();
        $this->getJson('/api/v1/summaries/sales?start_date=2026-10-01&end_date=2026-10-01')->assertJsonPath('data.new_customers', 0)->assertJsonPath('data.deals_created_by_current_status', [])->assertJsonPath('data.quotations_issued_by_current_status', []);
    }

    public function test_finance_summary_matches_recognized_amounts_reversals_fx_and_current_ar(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin);
        $customer = $this->customer($admin);
        $invoice = $this->invoice($admin, $customer);
        foreach (['draft', 'void'] as $status) {
            $this->invoice($admin, $customer, ['status' => $status, 'base_total' => '9000.00']);
        }
        $this->invoice($admin, $customer, ['issue_date' => '2026-09-01', 'due_date' => '2026-10-19', 'base_balance_due' => '50.00']);
        $this->invoice($admin, $customer, ['currency' => 'USD', 'total' => '1.00', 'base_total' => '35.00', 'base_balance_due' => '35.00']);
        $this->invoice($admin, $customer, ['base_total' => '15.50', 'base_balance_due' => '15.50', 'base_currency' => 'USD', 'currency' => 'USD']);
        $this->invoice($admin, $customer, ['base_total' => '777.00'])->delete();
        $receipt = Payment::create(['org_id' => $admin->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'receipt', 'payment_date' => '2026-10-01', 'amount' => '400.10', 'base_amount' => '400.10', 'currency' => 'THB', 'base_currency' => 'THB', 'payment_method' => 'cash']);
        Payment::create(['org_id' => $admin->org_id, 'invoice_id' => $invoice->id, 'reversal_of_payment_id' => $receipt->id, 'entry_type' => 'reversal', 'payment_date' => '2026-10-02', 'amount' => '100.05', 'base_amount' => '100.05', 'currency' => 'THB', 'base_currency' => 'THB', 'payment_method' => 'cash']);
        $expense = null;
        foreach (['approved', 'partially_paid', 'paid', 'draft', 'rejected'] as $status) {
            $expense = Expense::create(['org_id' => $admin->org_id, 'expense_no' => $status, 'category' => 'office', 'title' => 'Private expense', 'amount' => '10.10', 'base_amount' => '10.10', 'status' => $status, 'expense_date' => '2026-10-01', 'currency' => 'THB', 'base_currency' => 'THB']);
        }
        foreach (['payment' => '8.10', 'reversal' => '2.05'] as $type => $amount) {
            VendorPayment::create(['org_id' => $admin->org_id, 'expense_id' => $expense->id, 'entry_type' => $type, 'amount' => $amount, 'base_amount' => $amount, 'exchange_rate' => '1.00', 'expense_base_amount' => $amount, 'payment_date' => '2026-10-01', 'currency' => 'THB', 'base_currency' => 'THB', 'idempotency_key' => (string) Str::uuid()]);
        }
        $foreign = $this->manager();
        $this->invoice($foreign, $this->customer($foreign), ['base_total' => '999999.00']);
        $before = Invoice::all()->toJson();
        $this->withToken($secret)->getJson('/api/v1/summaries/finance?start_date=2026-10-01&end_date=2026-10-02')->assertOk()
            ->assertJsonPath('data.period.invoiced_revenue.0.amount', '1035.10')
            ->assertJsonPath('data.period.invoiced_revenue.1.currency', 'USD')
            ->assertJsonPath('data.period.invoiced_revenue.1.amount', '15.50')
            ->assertJsonPath('data.period.cash_received.0.net', '300.05')
            ->assertJsonPath('data.period.recognized_expense.0.amount', '30.30')
            ->assertJsonPath('data.period.ap_cash_paid.0.net', '6.05')
            ->assertJsonPath('data.snapshot.outstanding_ar.0.amount', '685.10')
            ->assertJsonPath('data.snapshot.overdue_ar.0.amount', '635.10')
            ->assertJsonMissingPath('data.cash_balance');
        $this->assertSame($before, Invoice::all()->toJson());
    }

    public function test_projects_scope_is_explicit_and_old_keys_do_not_gain_access(): void
    {
        $admin = $this->manager();
        [$old, $oldSecret] = $this->credential($admin, ['summary:sales:read', 'summary:finance:read']);
        $this->withToken($oldSecret)->getJson('/api/v1/summaries/projects')->assertForbidden();
        $secret = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'Projects app', 'scopes' => ['summary:projects:read']])
            ->assertCreated()->json('secret');
        $this->withToken($secret)->getJson('/api/v1/summaries/projects')->assertOk()
            ->assertJsonPath('data.period.tasks_completed', 0)
            ->assertJsonPath('data.snapshot.projects_by_status', [])
            ->assertJsonPath('data.snapshot.average_active_project_progress_percent', null)
            ->assertJsonPath('meta.currency_mode', 'not_applicable');
        $this->getJson('/api/v1/summaries/finance')->assertForbidden();
        $this->assertSame(['summary:sales:read', 'summary:finance:read'], $old->fresh()->scopes);
    }

    public function test_projects_summary_separates_period_and_snapshot_and_excludes_blocked_overdue(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin, ['summary:projects:read']);
        $customer = $this->customer($admin);
        $makeProject = fn ($status, $progress, $due) => Project::create([
            'org_id' => $admin->org_id, 'customer_id' => $customer->id, 'project_code' => Str::random(6),
            'name' => 'Private project', 'status' => $status, 'progress_percent' => $progress, 'due_date' => $due,
        ]);
        $active = $makeProject('active', 20, '2026-10-17');
        $active->forceFill(['created_at' => '2026-09-01 00:00:00'])->save();
        $hold = $makeProject('on_hold', 81, '2026-10-18');
        $hold->forceFill(['created_at' => '2026-09-30 17:00:00'])->save();
        $completed = $makeProject('completed', 100, '2026-10-17');
        $completed->forceFill(['created_at' => '2026-10-01 17:00:00'])->save();
        foreach ([['todo', '2026-10-17', '2026-09-30 17:00:00', null], ['blocked', '2026-10-17', '2026-09-30 17:00:00', null], ['in_progress', '2026-10-18', '2026-10-01 17:00:00', null], ['done', '2026-10-17', '2026-09-01 00:00:00', '2026-09-30 17:00:00'], ['done', '2026-10-17', '2026-09-01 00:00:00', '2026-10-01 17:00:00'], ['cancelled', '2026-10-17', '2026-09-30 17:00:00', null]] as [$status, $due, $created, $done]) {
            $task = Task::create(['org_id' => $admin->org_id, 'project_id' => $active->id, 'title' => 'Private task', 'status' => $status, 'due_date' => $due, 'completed_at' => $done]);
            $task->forceFill(['created_at' => $created])->save();
        }
        Task::create(['org_id' => $admin->org_id, 'project_id' => $active->id, 'title' => 'Deleted', 'status' => 'todo', 'due_date' => '2026-10-17'])->delete();
        $deleted = $makeProject('active', 100, '2026-10-17');
        Task::create(['org_id' => $admin->org_id, 'project_id' => $deleted->id, 'title' => 'Deleted parent', 'status' => 'todo', 'due_date' => '2026-10-17']);
        $deleted->delete();
        $foreign = $this->manager();
        $foreignProject = Project::create(['org_id' => $foreign->org_id, 'customer_id' => $this->customer($foreign)->id, 'project_code' => '000001', 'name' => 'Foreign', 'status' => 'active', 'progress_percent' => 100]);
        Task::create(['org_id' => $admin->org_id, 'project_id' => $foreignProject->id, 'title' => 'Corrupt cross-org parent', 'status' => 'todo', 'due_date' => '2026-10-17']);
        Task::create(['org_id' => $foreign->org_id, 'project_id' => $active->id, 'title' => 'Foreign task', 'status' => 'todo', 'due_date' => '2026-10-17']);
        $before = [Project::withTrashed()->get()->toJson(), Task::withTrashed()->get()->toJson()];
        DB::enableQueryLog();
        $response = $this->withToken($secret)->getJson('/api/v1/summaries/projects?start_date=2026-10-01&end_date=2026-10-01')->assertOk()
            ->assertJsonPath('data.period.projects_created_by_current_status', [['status' => 'on_hold', 'count' => 1]])
            ->assertJsonCount(3, 'data.period.tasks_created_by_current_status')
            ->assertJsonPath('data.period.tasks_completed', 1)
            ->assertJsonPath('data.snapshot.active_projects', 2)
            ->assertJsonPath('data.snapshot.overdue_projects', 1)
            ->assertJsonPath('data.snapshot.overdue_tasks', 1)
            ->assertJsonPath('data.snapshot.average_active_project_progress_percent', '50.5')
            ->assertJsonCount(3, 'data.snapshot.projects_by_status')
            ->assertJsonCount(5, 'data.snapshot.tasks_by_status')
            ->assertJsonMissingPath('data.work_hours')->assertJsonMissingPath('data.budget_amount');
        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()));
        $summaryQueries = array_filter(DB::getQueryLog(), fn ($query) => str_contains(strtolower($query['query']), 'count(*) as records_count'));
        $this->assertCount(2, $summaryQueries);
        DB::disableQueryLog();
        $this->assertStringNotContainsString('Private', $response->getContent());
        $this->assertSame($before, [Project::withTrashed()->get()->toJson(), Task::withTrashed()->get()->toJson()]);
    }

    public function test_default_permissions_are_limited_to_owner_admin_and_custom_grants_are_explicit(): void
    {
        foreach (PermissionCatalog::defaults() as $role => $permissions) {
            $this->assertSame(in_array($role, ['owner', 'admin'], true), in_array('api_keys.manage', $permissions, true));
        }
        $this->assertDatabaseHas('permissions', ['code' => 'api_keys.manage']);
    }

    public function test_migration_backfills_existing_admin_roles_and_can_be_rolled_back(): void
    {
        $admin = $this->manager([]);
        $migration = require database_path('migrations/2026_10_18_000001_create_integration_api_keys.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($admin->hasPermissionCode('api_keys.view'));
        $this->assertTrue($admin->hasPermissionCode('api_keys.manage'));
    }

    public function test_summary_responses_do_not_enable_cross_origin_browser_access(): void
    {
        [, $secret] = $this->credential($this->manager());
        $this->withToken($secret)->withHeader('Origin', 'https://external.example')
            ->getJson('/api/v1/summaries/sales')->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->withHeaders(['Access-Control-Request-Method' => 'GET', 'Access-Control-Request-Headers' => 'authorization'])
            ->options('/api/v1/summaries/sales')->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_production_requires_https_and_does_not_accept_client_tenant_on_key_creation(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'Tenant injection', 'scopes' => ['summary:sales:read'], 'org_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('org_id');
        $this->app['env'] = 'production';
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertStatus(426)->assertJsonPath('code', 'https_required');
        $this->getJson('https://localhost/api/v1/summaries/sales')->assertOk();
    }

    public function test_sales_summary_uses_indexed_aggregate_queries_instead_of_loading_each_record(): void
    {
        $admin = $this->manager();
        [, $secret] = $this->credential($admin);
        $customer = $this->customer($admin);
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['id' => (string) Str::uuid(), 'org_id' => $admin->org_id, 'customer_id' => $customer->id,
                'title' => 'Deal '.$i, 'stage' => 'new', 'value_amount' => '1.25', 'currency' => 'THB', 'probability' => 0,
                'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00'];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('deals')->insert($chunk);
        }
        DB::enableQueryLog();
        $this->withToken($secret)->getJson('/api/v1/summaries/sales')->assertOk()
            ->assertJsonPath('data.deals_created_by_current_status.0.count', 1000)
            ->assertJsonPath('data.deals_created_by_current_status.0.amount', '1250.00');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(10, count($queries));
        $plan = DB::select('EXPLAIN QUERY PLAN SELECT stage, currency, SUM(value_amount) FROM deals WHERE org_id = ? AND created_at >= ? AND created_at < ? AND deleted_at IS NULL GROUP BY stage, currency', [$admin->org_id, '2026-10-01', '2026-11-01']);
        $this->assertStringContainsString('deals_org_id_created_at_index', json_encode($plan));
    }
}
