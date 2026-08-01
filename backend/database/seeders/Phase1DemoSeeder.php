<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase1DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissionCatalog();

        $owner = User::where('email', 'owner@example.com')->first();

        if (! $owner) {
            $owner = $this->seedPhase1Demo();
        }

        $this->seedPhase2Demo($owner);
        $this->seedPhase5UatDemo($owner);
    }

    private function seedPhase1Demo(): User
    {
        $request = Request::create('/register', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'ERP Phase 1 Seeder',
        ]);

        $owner = app(OrganizationProvisioner::class)->registerOwner([
            'organization_name' => 'Demo ERP Co., Ltd.',
            'name' => 'Demo Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ], $request);

        $owner->forceFill(['email_verified_at' => now()])->save();

        $roles = Role::where('org_id', $owner->org_id)->get()->keyBy('code');

        $admin = $this->createDemoUser($owner, 'Demo Admin', 'admin@example.com', 'Admin', 'active');
        $admin->roles()->syncWithoutDetaching([$roles['admin']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        $inviteToken = Str::random(48);
        $member = $this->createDemoUser($owner, 'Demo Invited Member', 'member@example.com', 'Member', 'invited');
        $member->forceFill([
            'invite_token_hash' => hash('sha256', $inviteToken),
            'invite_expires_at' => now()->addHours(72),
            'invited_at' => now(),
        ])->save();
        $member->roles()->syncWithoutDetaching([$roles['member']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        $viewer = $this->createDemoUser($owner, 'Demo Inactive Viewer', 'viewer@example.com', 'Viewer', 'inactive');
        $viewer->roles()->syncWithoutDetaching([$roles['viewer']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        AuditLog::create([
            'org_id' => $owner->org_id,
            'actor_user_id' => $owner->id,
            'action' => 'seed.phase1_demo',
            'entity_type' => 'organization',
            'entity_id' => $owner->org_id,
            'after_json' => [
                'owner' => $owner->email,
                'admin' => $admin->email,
                'invited_member' => $member->email,
                'inactive_viewer' => $viewer->email,
                'dev_invite_url' => route('invites.accept', ['user' => $member->id, 'token' => $inviteToken], absolute: false),
            ],
        ]);

        return $owner;
    }

    private function seedPhase2Demo(User $owner): void
    {
        if (! Customer::where('org_id', $owner->org_id)->where('customer_code', '000001')->exists()) {
            $roles = Role::where('org_id', $owner->org_id)->get()->keyBy('code');
            $sales = $this->createDemoUser($owner, 'Demo Sales', 'sales@example.com', 'Sales Representative', 'active');
            $sales->roles()->syncWithoutDetaching([$roles['sales']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

            $first = Customer::create([
                'org_id' => $owner->org_id,
                'customer_code' => '000001',
                'company_name' => 'Siam Retail Co., Ltd.',
                'customer_type' => 'prospect',
                'status' => 'active',
                'owner_id' => $sales->id,
                'phone' => '021111111',
                'email' => 'hello@siam-retail.example',
                'source' => 'Referral',
                'created_by' => $owner->id,
            ]);

            $second = Customer::create([
                'org_id' => $owner->org_id,
                'customer_code' => '000002',
                'company_name' => 'Bangkok Services Group',
                'customer_type' => 'lead',
                'status' => 'active',
                'owner_id' => $owner->id,
                'phone' => '022222222',
                'email' => 'contact@bsg.example',
                'source' => 'Website',
                'created_by' => $owner->id,
            ]);

            $contact = Contact::create([
                'org_id' => $owner->org_id,
                'customer_id' => $first->id,
                'name' => 'Kanda Sales Lead',
                'position' => 'Procurement Manager',
                'phone' => '0811111111',
                'email' => 'kanda@siam-retail.example',
                'is_primary' => true,
                'created_by' => $owner->id,
            ]);

            $deal = Deal::create([
                'org_id' => $owner->org_id,
                'title' => 'Retail ERP Starter Package',
                'customer_id' => $first->id,
                'contact_id' => $contact->id,
                'stage' => 'proposal',
                'value_amount' => 180000,
                'currency' => 'THB',
                'probability' => 60,
                'expected_close_date' => now()->addDays(21)->toDateString(),
                'owner_id' => $sales->id,
                'source' => 'Referral',
                'created_by' => $owner->id,
            ]);

            Deal::create([
                'org_id' => $owner->org_id,
                'title' => 'Service Workflow Setup',
                'customer_id' => $second->id,
                'stage' => 'won',
                'value_amount' => 95000,
                'currency' => 'THB',
                'probability' => 100,
                'expected_close_date' => now()->subDays(2)->toDateString(),
                'owner_id' => $owner->id,
                'won_at' => now()->subDays(1),
                'source' => 'Website',
                'created_by' => $owner->id,
            ]);

            Activity::create([
                'org_id' => $owner->org_id,
                'entity_type' => 'deal',
                'entity_id' => $deal->id,
                'activity_type' => 'meeting',
                'subject' => 'Proposal walkthrough',
                'activity_at' => now()->subDays(3),
                'follow_up_at' => now(),
                'owner_id' => $sales->id,
                'created_by' => $owner->id,
            ]);

            AuditLog::create([
                'org_id' => $owner->org_id,
                'actor_user_id' => $owner->id,
                'action' => 'seed.phase2_demo',
                'entity_type' => 'customer',
                'entity_id' => $first->id,
                'after_json' => ['customers' => 2, 'deals' => 2, 'activities' => 1],
            ]);
        }

        NumberSequence::updateOrCreate(
            ['org_id' => $owner->org_id, 'branch_key' => '00000000-0000-0000-0000-000000000000', 'doc_type' => 'customer', 'year_key' => 0],
            ['branch_id' => null, 'year' => null, 'last_number' => max(2, Customer::where('org_id', $owner->org_id)->count())]
        );
    }

    private function seedPhase5UatDemo(User $owner): void
    {
        if (Product::where('org_id', $owner->org_id)->where('sku', 'UAT-SVC-001')->exists()) {
            return;
        }

        $roles = Role::where('org_id', $owner->org_id)->get()->keyBy('code');
        $sales = $this->createDemoUser($owner, 'Demo Sales', 'sales@example.com', 'Sales Representative', 'active');
        $sales->roles()->syncWithoutDetaching([$roles['sales']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);
        $finance = $this->createDemoUser($owner, 'Demo Finance', 'finance@example.com', 'Finance Officer', 'active');
        $finance->roles()->syncWithoutDetaching([$roles['finance']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);
        $pm = $this->createDemoUser($owner, 'Demo Project Manager', 'pm@example.com', 'Project Manager', 'active');
        $pm->roles()->syncWithoutDetaching([$roles['project_manager']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        $customer = Customer::create([
            'org_id' => $owner->org_id,
            'customer_code' => '000003',
            'company_name' => 'UAT Executive Co., Ltd.',
            'customer_type' => 'customer',
            'status' => 'active',
            'owner_id' => $sales->id,
            'email' => 'hello@uat-executive.example',
            'source' => 'UAT',
            'created_by' => $owner->id,
        ]);

        Deal::create([
            'org_id' => $owner->org_id,
            'title' => 'UAT Pipeline Deal',
            'customer_id' => $customer->id,
            'stage' => 'proposal',
            'value_amount' => 120000,
            'currency' => 'THB',
            'probability' => 60,
            'owner_id' => $sales->id,
            'source' => 'UAT',
            'created_by' => $owner->id,
        ]);

        $wonDeal = Deal::create([
            'org_id' => $owner->org_id,
            'title' => 'UAT Won Delivery Deal',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => 100000,
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $sales->id,
            'won_at' => now()->subDays(2),
            'source' => 'UAT',
            'created_by' => $owner->id,
        ]);

        $product = Product::create([
            'org_id' => $owner->org_id,
            'sku' => 'UAT-SVC-001',
            'name' => 'UAT Implementation Service',
            'type' => 'service',
            'category' => 'implementation',
            'unit' => 'job',
            'price' => 50000,
            'cost' => 0,
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        Product::create(['org_id' => $owner->org_id, 'sku' => 'UAT-SVC-002', 'name' => 'UAT Support Package', 'type' => 'service', 'category' => 'support', 'unit' => 'month', 'price' => 10000, 'is_active' => true, 'created_by' => $owner->id]);
        Product::create(['org_id' => $owner->org_id, 'sku' => 'UAT-PRD-001', 'name' => 'UAT License', 'type' => 'product', 'category' => 'software', 'unit' => 'seat', 'price' => 5000, 'is_active' => true, 'created_by' => $owner->id]);
        Product::create(['org_id' => $owner->org_id, 'sku' => 'UAT-PRD-002', 'name' => 'UAT Hardware Setup', 'type' => 'product', 'category' => 'hardware', 'unit' => 'set', 'price' => 15000, 'is_active' => true, 'created_by' => $owner->id]);
        Product::create(['org_id' => $owner->org_id, 'sku' => 'UAT-SVC-003', 'name' => 'UAT Training', 'type' => 'service', 'category' => 'training', 'unit' => 'day', 'price' => 8000, 'is_active' => true, 'created_by' => $owner->id]);

        $invoice = Invoice::create([
            'org_id' => $owner->org_id,
            'invoice_no' => '000001',
            'customer_id' => $customer->id,
            'deal_id' => $wonDeal->id,
            'status' => 'partially_paid',
            'tax_mode' => 'no_tax',
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'paid_amount' => 25000,
            'balance_due' => 75000,
            'currency' => 'THB',
            'created_by' => $owner->id,
        ]);
        $invoice->items()->create([
            'org_id' => $owner->org_id,
            'product_id' => $product->id,
            'description' => 'UAT implementation service',
            'quantity' => 2,
            'unit' => 'job',
            'unit_price' => 50000,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'line_total' => 100000,
            'sort_order' => 0,
        ]);
        Payment::create(['org_id' => $owner->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'receipt', 'amount' => 30000, 'payment_date' => now()->subDays(3)->toDateString(), 'payment_method' => 'bank_transfer', 'reference_no' => 'UAT-RCPT-001', 'idempotency_key' => 'uat-receipt-001', 'created_by' => $owner->id]);
        Payment::create(['org_id' => $owner->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'reversal', 'amount' => 5000, 'payment_date' => now()->subDays(2)->toDateString(), 'payment_method' => 'bank_transfer', 'reference_no' => 'UAT-REV-001', 'idempotency_key' => 'uat-reversal-001', 'created_by' => $owner->id]);

        $project = Project::create([
            'org_id' => $owner->org_id,
            'project_code' => '000001',
            'name' => 'UAT Delivery Project',
            'customer_id' => $customer->id,
            'deal_id' => $wonDeal->id,
            'owner_id' => $pm->id,
            'status' => 'active',
            'start_date' => now()->subDays(4)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'progress_percent' => 25,
            'budget_amount' => 100000,
            'currency' => 'THB',
            'created_by' => $owner->id,
        ]);

        Expense::create(['org_id' => $owner->org_id, 'expense_no' => 'UAT-EXP-001', 'category' => 'delivery', 'title' => 'UAT approved vendor', 'amount' => 15000, 'expense_date' => now()->subDays(2)->toDateString(), 'project_id' => $project->id, 'status' => 'approved', 'created_by' => $owner->id]);
        Expense::create(['org_id' => $owner->org_id, 'expense_no' => 'UAT-EXP-002', 'category' => 'delivery', 'title' => 'UAT paid vendor', 'amount' => 10000, 'expense_date' => now()->subDay()->toDateString(), 'project_id' => $project->id, 'status' => 'paid', 'paid_at' => now()->subDay(), 'created_by' => $owner->id]);
        Expense::create(['org_id' => $owner->org_id, 'expense_no' => 'UAT-EXP-003', 'category' => 'delivery', 'title' => 'UAT draft cost', 'amount' => 999, 'expense_date' => now()->toDateString(), 'project_id' => $project->id, 'status' => 'draft', 'created_by' => $owner->id]);
        Expense::create(['org_id' => $owner->org_id, 'expense_no' => 'UAT-EXP-004', 'category' => 'delivery', 'title' => 'UAT rejected cost', 'amount' => 888, 'expense_date' => now()->toDateString(), 'project_id' => $project->id, 'status' => 'rejected', 'created_by' => $owner->id]);

        Task::create(['org_id' => $owner->org_id, 'project_id' => $project->id, 'title' => 'UAT overdue task', 'description' => 'Expected overdue task for dashboard', 'status' => 'todo', 'priority' => 'urgent', 'assignee_id' => $pm->id, 'due_date' => now()->subDay()->toDateString(), 'created_by' => $owner->id]);
        Task::create(['org_id' => $owner->org_id, 'project_id' => $project->id, 'title' => 'UAT blocked task', 'description' => 'Blocked task excluded from overdue', 'status' => 'blocked', 'priority' => 'normal', 'assignee_id' => $pm->id, 'due_date' => now()->subDays(2)->toDateString(), 'created_by' => $owner->id]);
        Task::create(['org_id' => $owner->org_id, 'project_id' => $project->id, 'title' => 'UAT done task', 'description' => 'Done task for UAT', 'status' => 'done', 'priority' => 'normal', 'assignee_id' => $pm->id, 'due_date' => now()->subDay()->toDateString(), 'completed_at' => now(), 'created_by' => $owner->id]);

        NumberSequence::updateOrCreate(['org_id' => $owner->org_id, 'branch_key' => '00000000-0000-0000-0000-000000000000', 'doc_type' => 'customer', 'year_key' => 0], ['branch_id' => null, 'year' => null, 'last_number' => max(3, Customer::where('org_id', $owner->org_id)->count())]);
        NumberSequence::updateOrCreate(['org_id' => $owner->org_id, 'branch_key' => '00000000-0000-0000-0000-000000000000', 'doc_type' => 'invoice', 'year_key' => 0], ['branch_id' => null, 'year' => null, 'last_number' => 1]);
        NumberSequence::updateOrCreate(['org_id' => $owner->org_id, 'branch_key' => '00000000-0000-0000-0000-000000000000', 'doc_type' => 'project', 'year_key' => 0], ['branch_id' => null, 'year' => null, 'last_number' => 1]);

        AuditLog::create([
            'org_id' => $owner->org_id,
            'actor_user_id' => $owner->id,
            'action' => 'seed.phase5_uat',
            'entity_type' => 'dashboard',
            'entity_id' => $project->id,
            'after_json' => [
                'customers' => 3,
                'open_deals' => 2,
                'pipeline_value' => 300000,
                'won_deals' => 2,
                'won_value' => 195000,
                'invoiced_revenue' => 100000,
                'cash_in' => 25000,
                'outstanding_ar' => 75000,
                'recognized_expense' => 25000,
                'active_projects' => 1,
                'overdue_tasks' => 1,
                'project_profit' => 75000,
            ],
        ]);
    }

    private function syncPermissionCatalog(): void
    {
        foreach (PermissionCatalog::permissions() as $permission) {
            Permission::firstOrCreate(['code' => $permission['code']], $permission);
        }

        $permissions = Permission::whereIn('code', array_column(PermissionCatalog::permissions(), 'code'))->get()->keyBy('code');

        foreach (Role::all() as $role) {
            $defaultCodes = PermissionCatalog::defaults()[$role->code] ?? [];
            $ids = collect($defaultCodes)
                ->filter(fn (string $code) => $permissions->has($code))
                ->map(fn (string $code) => $permissions[$code]->id)
                ->all();

            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    private function createDemoUser(User $owner, string $name, string $email, string $position, string $status): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'org_id' => $owner->org_id,
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'name' => $name,
                'display_name' => $name,
                'password' => Hash::make('password'),
                'auth_provider' => 'local',
                'status' => $status,
                'position' => $position,
                'email_verified_at' => $status === 'active' ? now() : null,
                'created_by' => $owner->id,
            ]
        );
    }
}
