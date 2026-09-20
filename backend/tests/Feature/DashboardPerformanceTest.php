<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PayrollTaxPolicy;
use App\Models\Permission;
use App\Models\PortalSession;
use App\Models\PortalUser;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SocialSecurityPolicy;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\Warehouse;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_dashboards_stay_within_agreed_large_fixture_budget(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'owner', 'name' => 'Performance owner']);
        foreach (['dashboard.view', 'executive.dashboard.view', 'sales.dashboard.view', 'customers.view', 'deals.view', 'quotations.view', 'invoices.view', 'payments.view', 'expenses.view', 'projects.view', 'tasks.view', 'users.view', 'roles.view', 'audit.view', 'purchase_orders.view', 'inventory.view', 'workflows.approve', 'workflows.view', 'hr.manage', 'hr.self.view', 'payroll.view'] as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'test', 'action' => $code]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'PERF', 'company_name' => 'Performance', 'owner_id' => $user->id]);
        $project = Project::create(['org_id' => $user->org_id, 'project_code' => 'PERF', 'name' => 'Performance', 'customer_id' => $customer->id, 'owner_id' => $user->id, 'status' => 'active', 'budget_amount' => 1000, 'currency' => 'THB']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'PERF', 'customer_id' => $customer->id, 'status' => 'sent', 'issue_date' => today(), 'due_date' => today()->subDay(), 'total' => 100, 'balance_due' => 100, 'base_total' => 100, 'base_balance_due' => 100, 'currency' => 'THB']);
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'PERF', 'title' => 'Performance', 'category' => 'other', 'project_id' => $project->id, 'status' => 'approved', 'expense_date' => today(), 'amount' => 10, 'base_amount' => 10, 'created_by' => $user->id]);
        $deal = Deal::create(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'owner_id' => $user->id, 'title' => 'Performance', 'stage' => 'proposal', 'value_amount' => 100, 'currency' => 'THB']);
        $task = Task::create(['org_id' => $user->org_id, 'project_id' => $project->id, 'assignee_id' => $user->id, 'title' => 'Performance', 'status' => 'todo', 'priority' => 'urgent', 'due_date' => today()->subDay()]);
        $payment = Payment::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'receipt', 'amount' => 10, 'base_amount' => 10, 'payment_date' => today(), 'payment_method' => 'cash', 'created_by' => $user->id]);
        $vendorPayment = VendorPayment::create(['org_id' => $user->org_id, 'expense_id' => $expense->id, 'entry_type' => 'payment', 'amount' => 1, 'base_amount' => 1, 'currency' => 'THB', 'base_currency' => 'THB', 'exchange_rate' => 1, 'expense_base_amount' => 1, 'idempotency_key' => (string) Str::uuid(), 'payment_date' => today(), 'created_by' => $user->id]);
        foreach ([[$customer, 'customer_code'], [$project, 'project_code'], [$invoice, 'invoice_no'], [$expense, 'expense_no'], [$deal, null], [$task, null], [$payment, null], [$vendorPayment, null]] as [$model, $number]) {
            $this->multiply($model, $number);
        }
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'PERF', 'name' => 'Performance', 'status' => 'active']);
        $po = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PERF', 'status' => 'sent', 'order_date' => today(), 'total' => 100, 'currency' => 'THB']);
        $receipt = GoodsReceipt::create(['org_id' => $user->org_id, 'purchase_order_id' => $po->id, 'grn_no' => 'PERF', 'received_date' => today(), 'status' => 'received']);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'PERF', 'name' => 'Performance']);
        $product = Product::create(['org_id' => $user->org_id, 'name' => 'Performance', 'type' => 'product', 'track_inventory' => true, 'reorder_point' => 2000]);
        $movement = StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment', 'movement_date' => today(), 'quantity' => 1]);
        $leaveType = LeaveType::create(['org_id' => $user->org_id, 'code' => 'PERF', 'name' => 'Performance']);
        $leave = LeaveRequest::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'leave_type_id' => $leaveType->id, 'starts_on' => today(), 'ends_on' => today(), 'total_days' => 1, 'status' => 'submitted', 'submitted_at' => now()]);
        $onLeave = LeaveRequest::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'leave_type_id' => $leaveType->id, 'starts_on' => today(), 'ends_on' => today(), 'total_days' => 1, 'status' => 'approved']);
        $taxPolicy = PayrollTaxPolicy::create(['org_id' => $user->org_id, 'name' => 'Performance', 'effective_from' => today(), 'brackets_json' => []]);
        $socialPolicy = SocialSecurityPolicy::create(['org_id' => $user->org_id, 'name' => 'Performance', 'effective_from' => today(), 'employee_rate' => 0, 'employer_rate' => 0, 'wage_ceiling' => 0]);
        $payroll = PayrollRun::create(['org_id' => $user->org_id, 'run_no' => 'PERF', 'payroll_tax_policy_id' => $taxPolicy->id, 'social_security_policy_id' => $socialPolicy->id, 'period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(), 'payment_date' => today(), 'currency' => 'THB', 'status' => 'draft']);
        $definition = WorkflowDefinition::create(['org_id' => $user->org_id, 'code' => 'PERF', 'name' => 'Performance', 'subject_type' => 'expense']);
        $instance = WorkflowInstance::create(['org_id' => $user->org_id, 'workflow_definition_id' => $definition->id, 'subject_type' => 'expense', 'subject_id' => $expense->id, 'requester_user_id' => $user->id, 'status' => 'pending', 'definition_snapshot' => [], 'subject_snapshot' => [], 'submitted_at' => now()]);
        $approval = WorkflowApproval::create(['workflow_instance_id' => $instance->id, 'step_no' => 1, 'assigned_user_id' => $user->id, 'status' => 'pending']);
        $quote = Quotation::create(['org_id' => $user->org_id, 'quotation_no' => 'PERF', 'customer_id' => $customer->id, 'deal_id' => $deal->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'valid_until' => today()->addDay(), 'subtotal' => 100, 'total' => 100, 'currency' => 'THB']);
        $activity = Activity::create(['org_id' => $user->org_id, 'entity_type' => 'deal', 'entity_id' => $deal->id, 'owner_id' => $user->id, 'subject' => 'Performance', 'activity_type' => 'call', 'activity_at' => now(), 'follow_up_at' => now()]);
        $audit = AuditLog::create(['org_id' => $user->org_id, 'actor_user_id' => $user->id, 'action' => 'user.disable', 'entity_type' => 'user', 'entity_id' => $user->id]);
        foreach ([[$po, 'po_no'], [$receipt, 'grn_no'], [$movement, null], [$leave, null], [$onLeave, null], [$payroll, 'run_no'], [$approval, null], [$quote, 'quotation_no'], [$activity, null], [$audit, null], [$role, 'code'], [$user, 'email']] as [$model, $number]) {
            $this->multiply($model, $number);
        }
        $this->actingAs($user);
        $measurements = [];
        foreach (['dashboard', 'executive.dashboard', 'finance.dashboard', 'delivery.dashboard', 'sales.dashboard', 'my-work.dashboard', 'procurement.dashboard', 'hr.dashboard'] as $route) {
            // Measure the route's steady-state response. Laravel's one-time
            // metadata/bootstrap work is not dashboard query work and otherwise
            // makes this sub-second contract nondeterministic in SQLite CI.
            $this->get(route($route))->assertOk();
            DB::enableQueryLog();
            DB::flushQueryLog();
            $started = hrtime(true);
            $response = $this->get(route($route));
            $elapsed = (hrtime(true) - $started) / 1e6;
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            $response->assertOk();
            $measurements[$route] = ['queries' => $count, 'milliseconds' => round($elapsed, 2)];
        }
        foreach (['customer' => $customer->id, 'supplier' => $supplier->id] as $type => $partyId) {
            $portal = PortalUser::create(['org_id' => $user->org_id, 'party_type' => $type, 'party_id' => $partyId, 'email' => $type.'@performance.test', 'is_active' => true]);
            $token = Str::random(80);
            PortalSession::create(['portal_user_id' => $portal->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $started = hrtime(true);
            $response = $this->withCookie('erp_portal_session', $token)->get(route('portal.dashboard'));
            $elapsed = (hrtime(true) - $started) / 1e6;
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
            $response->assertOk();
            $measurements['portal.'.$type] = ['queries' => $count, 'milliseconds' => round($elapsed, 2)];
        }
        fwrite(STDERR, "\nDashboard performance: ".json_encode($measurements)."\n");
        foreach ($measurements as $route => $measurement) {
            $this->assertLessThanOrEqual(50, $measurement['queries'], $route.' query budget');
            $this->assertLessThanOrEqual(1000, $measurement['milliseconds'], $route.' response budget');
        }
    }

    private function multiply($model, ?string $number): void
    {
        $rows = [];
        $instances = [];
        $instance = $model instanceof WorkflowApproval
            ? (array) DB::table('workflow_instances')->where('id', $model->workflow_instance_id)->first() : null;
        for ($i = 1; $i < 1000; $i++) {
            $row = $model->getAttributes();
            $row['id'] = (string) Str::uuid();
            if (isset($row['idempotency_key'])) {
                $row['idempotency_key'] = (string) Str::uuid();
            }
            if ($instance) {
                $copy = $instance;
                $copy['id'] = (string) Str::uuid();
                $instances[] = $copy;
                $row['workflow_instance_id'] = $copy['id'];
            }
            if ($number) {
                $row[$number] = 'PERF-'.$i;
            }
            $rows[] = $row;
        }
        foreach (array_chunk($instances, 50) as $chunk) {
            DB::table('workflow_instances')->insert($chunk);
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table($model->getTable())->insert($chunk);
        }
    }
}
