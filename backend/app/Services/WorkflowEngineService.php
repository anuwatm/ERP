<?php

namespace App\Services;

use App\Models\EmployeeWorkProfile;
use App\Models\Expense;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDelegation;
use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowEngineService
{
    public const SUBJECTS = ['leave_request', 'purchase_request', 'purchase_order', 'expense'];

    public function __construct(private readonly FinancialJournalService $journals) {}

    public function submit(User $requester, string $subjectType, string $subjectId): WorkflowInstance
    {
        $subject = $this->subject($subjectType, $subjectId, $requester->org_id);
        if ($this->creator($subject) !== $requester->id) {
            throw ValidationException::withMessages(['subject' => 'Only the document creator can submit this workflow.']);
        }

        return DB::transaction(function () use ($requester, $subjectType, $subject): WorkflowInstance {
            $existing = WorkflowInstance::where(['org_id' => $requester->org_id, 'subject_type' => $subjectType, 'subject_id' => $subject->id])->where('status', 'pending')->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            $definition = $this->definitionFor($requester, $subjectType, $subject);
            if (! $definition) {
                throw ValidationException::withMessages(['workflow' => 'No active approval workflow matches this document.']);
            }
            $steps = $definition->steps;
            if ($steps->isEmpty()) {
                throw ValidationException::withMessages(['workflow' => 'The matched workflow has no steps.']);
            }
            $snapshot = $steps->map(fn ($step) => ['step_no' => $step->step_no, 'assignment_type' => $step->assignment_type, 'approver_user_id' => $step->approver_user_id, 'approver_role_code' => $step->approver_role_code, 'execution_mode' => $step->execution_mode, 'assignee_ids' => $this->assignees($requester, $step, $subject)->reject(fn (string $id) => $id === $requester->id)->unique()->values()->all()])->values()->all();
            if (collect($snapshot)->contains(fn ($step) => empty($step['assignee_ids']))) {
                throw ValidationException::withMessages(['workflow' => 'A workflow step has no eligible approver.']);
            }
            $instance = WorkflowInstance::create(['org_id' => $requester->org_id, 'workflow_definition_id' => $definition->id, 'subject_type' => $subjectType, 'subject_id' => $subject->id, 'requester_user_id' => $requester->id, 'status' => 'pending', 'current_step_no' => 1, 'definition_snapshot' => ['code' => $definition->code, 'version' => $definition->version, 'steps' => $snapshot], 'subject_snapshot' => $this->snapshot($subject), 'submitted_at' => now()]);
            foreach ($snapshot as $step) {
                foreach ($step['assignee_ids'] as $id) {
                    WorkflowApproval::create(['workflow_instance_id' => $instance->id, 'step_no' => $step['step_no'], 'assigned_user_id' => $id, 'status' => $step['step_no'] === 1 ? 'pending' : 'queued']);
                }
            }

            return $instance;
        });
    }

    public function act(WorkflowApproval $approval, User $actor, string $action, ?string $comment = null): WorkflowInstance
    {
        return DB::transaction(function () use ($approval, $actor, $action, $comment): WorkflowInstance {
            $approval = WorkflowApproval::with('instance')->lockForUpdate()->findOrFail($approval->id);
            $instance = WorkflowInstance::lockForUpdate()->findOrFail($approval->workflow_instance_id);
            if ($instance->org_id !== $actor->org_id) {
                abort(404);
            }
            if ($approval->status !== 'pending') {
                return $instance;
            }
            if (! in_array($action, ['approved', 'rejected', 'revision_requested'], true)) {
                throw ValidationException::withMessages(['action' => 'Unsupported workflow action.']);
            }
            if ($instance->requester_user_id === $actor->id) {
                throw ValidationException::withMessages(['action' => 'Segregation of duties blocks self-approval.']);
            }
            $delegated = $this->isDelegate($instance, $approval->assigned_user_id, $actor->id);
            if ($approval->assigned_user_id !== $actor->id && ! $delegated) {
                abort(403);
            }
            $approval->update(['status' => $action, 'acted_by_user_id' => $actor->id, 'delegated_from_user_id' => $delegated ? $approval->assigned_user_id : null, 'comment' => $comment, 'acted_at' => now()]);
            if ($action !== 'approved') {
                $this->markSubjectNotApproved($instance, $action, $actor);
                $instance->update(['status' => $action, 'completed_at' => now()]);

                return $instance->fresh();
            }
            $stepApprovals = WorkflowApproval::where('workflow_instance_id', $instance->id)->where('step_no', $instance->current_step_no)->lockForUpdate()->get();
            $step = collect($instance->definition_snapshot['steps'])->firstWhere('step_no', $instance->current_step_no);
            if (($step['execution_mode'] ?? 'sequential') === 'parallel') {
                $stepApprovals->where('id', '!=', $approval->id)->where('status', 'pending')->each->update(['status' => 'superseded']);
            } elseif ($stepApprovals->contains('status', 'pending')) {
                return $instance->fresh();
            }
            $next = $instance->current_step_no + 1;
            $nextApprovals = WorkflowApproval::where('workflow_instance_id', $instance->id)->where('step_no', $next)->lockForUpdate()->get();
            if ($nextApprovals->isNotEmpty()) {
                $nextApprovals->each->update(['status' => 'pending']);
                $instance->update(['current_step_no' => $next]);

                return $instance->fresh();
            }
            $this->markSubjectApproved($instance, $actor);
            $instance->update(['status' => 'approved', 'completed_at' => now()]);

            return $instance->fresh();
        });
    }

    private function definitionFor(User $user, string $type, Model $subject): ?WorkflowDefinition
    {
        $amount = (float) ($subject->total ?? $subject->base_payable_total ?? $subject->amount ?? 0);

        return WorkflowDefinition::where('org_id', $user->org_id)->where('subject_type', $type)->where('is_active', true)->where(fn ($q) => $q->whereNull('amount_min')->orWhere('amount_min', '<=', $amount))->where(fn ($q) => $q->whereNull('amount_max')->orWhere('amount_max', '>=', $amount))->with('steps')->orderByDesc('version')->first();
    }

    private function assignees(User $requester, $step, Model $subject): Collection
    {
        return match ($step->assignment_type) {
            'user' => collect([$step->approver_user_id])->filter(),
            'manager' => collect([EmployeeWorkProfile::where('org_id', $requester->org_id)->where('user_id', $requester->id)->value('manager_user_id')])->filter(),
            'role' => User::where('org_id', $requester->org_id)->whereHas('roles', fn ($q) => $q->where('code', $step->approver_role_code))->pluck('id'),
            default => collect(),
        };
    }

    private function subject(string $type, string $id, string $orgId): Model
    {
        $class = ['leave_request' => LeaveRequest::class, 'purchase_request' => PurchaseRequest::class, 'purchase_order' => PurchaseOrder::class, 'expense' => Expense::class][$type] ?? null;
        if (! $class) {
            throw ValidationException::withMessages(['subject' => 'Unsupported workflow subject.']);
        }

        return $class::where('org_id', $orgId)->findOrFail($id);
    }

    private function creator(Model $subject): ?string
    {
        return $subject->created_by ?? $subject->user_id ?? null;
    }

    private function snapshot(Model $subject): array
    {
        return collect($subject->getAttributes())->only(['id', 'status', 'total', 'amount', 'base_payable_total', 'title', 'pr_no', 'po_no', 'expense_no', 'starts_on', 'ends_on', 'total_days'])->all();
    }

    private function isDelegate(WorkflowInstance $instance, string $delegator, string $actor): bool
    {
        return WorkflowDelegation::where('org_id', $instance->org_id)->where('delegator_user_id', $delegator)->where('delegate_user_id', $actor)->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->where(fn ($q) => $q->whereNull('subject_type')->orWhere('subject_type', $instance->subject_type))->exists();
    }

    private function markSubjectApproved(WorkflowInstance $instance, User $actor): void
    {
        $subject = $this->subject($instance->subject_type, $instance->subject_id, $instance->org_id);
        if ($instance->subject_type === 'expense') {
            /** @var Expense $subject */
            $gross = $subject->tax_mode === 'exclusive'
                ? round((float) $subject->base_amount + (float) $subject->base_tax_amount, 2)
                : round((float) $subject->base_amount ?: (float) $subject->amount, 2);
            $subject->update(['status' => 'approved', 'payable_total' => $this->grossExpense($subject), 'base_payable_total' => $gross, 'balance_due' => $this->grossExpense($subject), 'base_balance_due' => $gross, 'approved_by' => $actor->id, 'approved_at' => now(), 'updated_by' => $actor->id]);
            $this->journals->postExpenseApproval($subject->fresh(), $actor->id);

            return;
        }
        $data = ['status' => 'approved'];
        if (in_array($instance->subject_type, ['purchase_request', 'purchase_order'], true)) {
            $data += ['approved_by' => $actor->id, 'approved_at' => now()];
        }
        $subject->update($data);
    }

    private function markSubjectNotApproved(WorkflowInstance $instance, string $action, User $actor): void
    {
        $subject = $this->subject($instance->subject_type, $instance->subject_id, $instance->org_id);
        $status = $action === 'revision_requested' ? 'draft' : 'rejected';
        if ($instance->subject_type === 'leave_request') {
            /** @var LeaveRequest $subject */
            if ($subject->status === 'submitted') {
                $type = LeaveType::where('org_id', $subject->org_id)->findOrFail($subject->leave_type_id);
                if ($type->is_paid) {
                    LeaveBalance::where(['org_id' => $subject->org_id, 'user_id' => $subject->user_id, 'leave_type_id' => $type->id, 'leave_year' => $subject->starts_on->year])->lockForUpdate()->firstOrFail()->decrement('used_days', $subject->total_days);
                }
            }
            $subject->update(['status' => $status]);

            return;
        }
        $subject->update(array_filter(['status' => $status, 'updated_by' => $actor->id]));
    }

    private function grossExpense(Expense $expense): float
    {
        return $expense->tax_mode === 'exclusive'
            ? round((float) $expense->amount + (float) $expense->tax_amount, 2)
            : round((float) $expense->amount, 2);
    }
}
