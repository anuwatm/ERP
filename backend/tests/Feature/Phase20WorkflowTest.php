<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDelegation;
use App\Services\ChartOfAccountProvisioner;
use App\Services\WorkflowEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase20WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_threshold_snapshot_sod_and_idempotent_transition(): void
    {
        [$requester,$approver] = $this->users();
        $low = $this->definition($requester, 'purchase_request', 0, 999, $approver->id);
        $high = $this->definition($requester, 'purchase_request', 1000, null, $approver->id);
        $pr = PurchaseRequest::create(['org_id' => $requester->org_id, 'pr_no' => 'PR-1', 'status' => 'draft', 'request_date' => '2026-09-07', 'total' => 1500, 'created_by' => $requester->id]);
        $engine = app(WorkflowEngineService::class);
        $instance = $engine->submit($requester, 'purchase_request', $pr->id);
        $this->assertSame($high->id, $instance->workflow_definition_id);
        $this->assertCount(1, $instance->approvals);
        $this->expectException(ValidationException::class);
        $engine->act($instance->approvals->first(), $requester, 'approved');
    }

    public function test_delegation_expiry_and_final_approval_are_enforced(): void
    {
        [$requester,$approver,$delegate] = $this->users(3);
        $this->definition($requester, 'leave_request', null, null, $approver->id);
        $type = LeaveType::create(['org_id' => $requester->org_id, 'code' => 'A', 'name' => 'Annual']);
        $leave = LeaveRequest::create(['org_id' => $requester->org_id, 'user_id' => $requester->id, 'leave_type_id' => $type->id, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'total_days' => 1, 'status' => 'submitted']);
        $engine = app(WorkflowEngineService::class);
        $instance = $engine->submit($requester, 'leave_request', $leave->id);
        $approval = $instance->approvals->first();
        WorkflowDelegation::create(['org_id' => $requester->org_id, 'delegator_user_id' => $approver->id, 'delegate_user_id' => $delegate->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute(), 'is_active' => true]);
        $this->actingAs($delegate)->post(route('workflows.approvals.act', $approval), ['action' => 'approved'])->assertForbidden();
        WorkflowDelegation::create(['org_id' => $requester->org_id, 'delegator_user_id' => $approver->id, 'delegate_user_id' => $delegate->id, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(), 'is_active' => true]);
        $result = $engine->act($approval, $delegate, 'approved');
        $this->assertSame('approved', $result->status);
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(1, $result->approvals()->count());
        $this->assertSame('approved', $engine->act($approval, $delegate, 'approved')->status);
    }

    public function test_rejection_refunds_paid_leave_and_revision_returns_to_draft(): void
    {
        [$requester, $approver] = $this->users();
        $this->definition($requester, 'leave_request', null, null, $approver->id);
        $type = LeaveType::create(['org_id' => $requester->org_id, 'code' => 'P', 'name' => 'Paid', 'is_paid' => true]);
        $balance = LeaveBalance::create(['org_id' => $requester->org_id, 'user_id' => $requester->id, 'leave_type_id' => $type->id, 'leave_year' => 2026, 'accrued_days' => 5, 'used_days' => 1]);
        $leave = LeaveRequest::create(['org_id' => $requester->org_id, 'user_id' => $requester->id, 'leave_type_id' => $type->id, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'total_days' => 1, 'status' => 'submitted']);
        $instance = app(WorkflowEngineService::class)->submit($requester, 'leave_request', $leave->id);
        app(WorkflowEngineService::class)->act($instance->approvals->first(), $approver, 'rejected', 'Insufficient detail');
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertEquals(0, $balance->fresh()->used_days);
    }

    public function test_expense_final_approval_sets_payable_and_posts_gl(): void
    {
        [$requester, $approver] = $this->users();
        app(ChartOfAccountProvisioner::class)->ensure($requester->org_id);
        $this->definition($requester, 'expense', null, null, $approver->id);
        $expense = Expense::create(['org_id' => $requester->org_id, 'expense_no' => 'EXP-WF-001', 'category' => 'software', 'title' => 'Workflow expense', 'amount' => 1000, 'currency' => 'THB', 'base_currency' => 'THB', 'exchange_rate' => 1, 'base_amount' => 1000, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'base_tax_amount' => 0, 'expense_date' => '2026-09-07', 'status' => 'draft', 'created_by' => $requester->id]);
        $instance = app(WorkflowEngineService::class)->submit($requester, 'expense', $expense->id);
        app(WorkflowEngineService::class)->act($instance->approvals->first(), $approver, 'approved');
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertEquals(1000, $expense->fresh()->balance_due);
        $this->assertDatabaseHas('journal_entries', ['org_id' => $requester->org_id, 'source_type' => 'expense', 'source_id' => $expense->id]);
    }

    private function users(int $count = 2): array
    {
        $first = User::factory()->create();
        $users = [$first];
        for ($i = 1; $i < $count; $i++) {
            $u = User::factory()->create();
            $u->forceFill(['org_id' => $first->org_id, 'branch_id' => $first->branch_id, 'division_id' => $first->division_id, 'department_id' => $first->department_id])->save();
            $users[] = $u;
        }

        return $users;
    }

    private function definition(User $user, string $type, ?float $min, ?float $max, string $approverId): WorkflowDefinition
    {
        $d = WorkflowDefinition::create(['org_id' => $user->org_id, 'code' => 'WF-'.$type.'-'.($min ?? 'all'), 'name' => 'Workflow', 'subject_type' => $type, 'version' => 1, 'amount_min' => $min, 'amount_max' => $max, 'is_active' => true, 'created_by' => $user->id]);
        $d->steps()->create(['step_no' => 1, 'assignment_type' => 'user', 'approver_user_id' => $approverId, 'execution_mode' => 'sequential']);

        return $d;
    }
}
