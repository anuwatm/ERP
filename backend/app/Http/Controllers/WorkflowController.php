<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDelegation;
use App\Services\WorkflowEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeDelegations = WorkflowDelegation::where('org_id', $user->org_id)
            ->where('delegate_user_id', $user->id)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get(['delegator_user_id', 'subject_type']);
        $delegatorIds = $activeDelegations->pluck('delegator_user_id');
        $inbox = WorkflowApproval::where('status', 'pending')
            ->where(function ($query) use ($user, $activeDelegations) {
                $query->where('assigned_user_id', $user->id);
                foreach ($activeDelegations as $delegation) {
                    $query->orWhere(function ($delegatedQuery) use ($delegation) {
                        $delegatedQuery->where('assigned_user_id', $delegation->delegator_user_id)
                            ->whereHas('instance', function ($instanceQuery) use ($delegation) {
                                $instanceQuery->when($delegation->subject_type, fn ($subjectQuery, $subjectType) => $subjectQuery->where('subject_type', $subjectType));
                            });
                    });
                }
            })
            ->with(['instance', 'assignedUser:id,name'])
            ->latest()
            ->get();
        $inbox->each(function (WorkflowApproval $approval) use ($delegatorIds): void {
            if ($delegatorIds->contains($approval->assigned_user_id)) {
                $approval->setRelation('delegatedFromUser', $approval->assignedUser);
            }
        });

        return Inertia::render('Workflows/Index', ['definitions' => WorkflowDefinition::where('org_id', $user->org_id)->with('steps')->latest()->get(), 'inbox' => $inbox, 'history' => WorkflowApproval::where(fn ($query) => $query->where('assigned_user_id', $user->id)->orWhere('acted_by_user_id', $user->id))->with(['instance', 'assignedUser:id,name', 'delegatedFromUser:id,name'])->latest()->limit(100)->get(), 'users' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']), 'delegations' => WorkflowDelegation::where('org_id', $user->org_id)->where('delegator_user_id', $user->id)->with('delegateUser:id,name')->latest()->get(), 'can' => ['manage' => $user->hasPermissionCode('workflows.manage'), 'approve' => $user->hasPermissionCode('workflows.approve')]]);
    }

    public function storeDefinition(Request $request): RedirectResponse
    {
        $org = $request->user()->org_id;
        $data = $request->validate(['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:150'], 'subject_type' => ['required', Rule::in(WorkflowEngineService::SUBJECTS)], 'amount_min' => ['nullable', 'numeric', 'min:0'], 'amount_max' => ['nullable', 'numeric', 'gte:amount_min'], 'steps' => ['required', 'array', 'min:1', 'max:10'], 'steps.*.assignment_type' => ['required', Rule::in(['user', 'manager', 'role'])], 'steps.*.approver_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $org)], 'steps.*.approver_role_code' => ['nullable', 'string', 'max:50'], 'steps.*.execution_mode' => ['required', Rule::in(['sequential', 'parallel'])]]);
        foreach ($data['steps'] as $step) {
            if (($step['assignment_type'] === 'user' && ! $step['approver_user_id']) || ($step['assignment_type'] === 'role' && ! $step['approver_role_code'])) {
                return back()->withErrors(['steps' => 'Each user/role step requires an assignee.']);
            }
        }
        $version = (int) WorkflowDefinition::where('org_id', $org)->where('code', $data['code'])->max('version') + 1;
        $definition = WorkflowDefinition::create(['org_id' => $org, 'code' => $data['code'], 'name' => $data['name'], 'subject_type' => $data['subject_type'], 'version' => $version, 'amount_min' => $data['amount_min'] ?? null, 'amount_max' => $data['amount_max'] ?? null, 'is_active' => true, 'created_by' => $request->user()->id]);
        foreach ($data['steps'] as $index => $step) {
            $definition->steps()->create(['step_no' => $index + 1] + $step);
        }
        $this->audit($request, 'workflow.definition.create', 'workflow_definition', $definition->id, ['code' => $definition->code, 'version' => $version]);

        return back()->with('success', 'Workflow definition version saved.');
    }

    public function submit(Request $request, string $subjectType, string $subjectId, WorkflowEngineService $engine): RedirectResponse
    {
        $instance = $engine->submit($request->user(), $subjectType, $subjectId);
        $this->audit($request, 'workflow.submit', 'workflow_instance', $instance->id, ['subject_type' => $subjectType, 'subject_id' => $subjectId]);

        return back()->with('success', 'Workflow submitted.');
    }

    public function act(Request $request, WorkflowApproval $workflowApproval, WorkflowEngineService $engine): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approved', 'rejected', 'revision_requested'])], 'comment' => ['nullable', 'string', 'max:2000', 'required_if:action,rejected,revision_requested']]);
        $instance = $engine->act($workflowApproval, $request->user(), $data['action'], $data['comment'] ?? null);
        $this->audit($request, 'workflow.'.$data['action'], 'workflow_approval', $workflowApproval->id, ['instance_id' => $instance->id]);

        return back()->with('success', 'Workflow action recorded.');
    }

    public function storeDelegation(Request $request): RedirectResponse
    {
        $org = $request->user()->org_id;
        $data = $request->validate(['delegate_user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $org)], 'subject_type' => ['nullable', Rule::in(WorkflowEngineService::SUBJECTS)], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']]);
        if ($data['delegate_user_id'] === $request->user()->id) {
            return back()->withErrors(['delegate_user_id' => 'Cannot delegate to yourself.']);
        } $delegation = WorkflowDelegation::create($data + ['org_id' => $org, 'delegator_user_id' => $request->user()->id, 'is_active' => true]);
        $this->audit($request, 'workflow.delegation.create', 'workflow_delegation', $delegation->id, ['delegate_user_id' => $delegation->delegate_user_id]);

        return back()->with('success', 'Delegation saved.');
    }

    private function audit(Request $request, string $action, string $type, string $id, array $after): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'after_json' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'request_id' => (string) Str::uuid()]);
    }
}
