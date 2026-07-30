<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Services\NumberSequenceService;
use App\Support\ProjectAccess;
use App\Support\SalesAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'owner_id', 'customer_id']);
        $canSeeAll = ProjectAccess::canSeeAll($user);

        $projects = ProjectAccess::scopeProjects(Project::query(), $user)
            ->with(['customer:id,company_name,customer_code', 'deal:id,title', 'owner:id,name,email'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('company_name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['owner_id'] ?? null) && $canSeeAll, fn ($query) => $query->where('owner_id', $filters['owner_id']))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->latest()
            ->get();

        $this->attachProjectCosts($projects, $user->org_id);

        return Inertia::render('Delivery/Projects', [
            'projects' => $projects,
            'customers' => Customer::where('org_id', $user->org_id)->orderBy('company_name')->get(['id', 'customer_code', 'company_name']),
            'deals' => SalesAccess::scopeDeals(Deal::query(), $user)->where('stage', 'won')->doesntHave('project')->orderBy('title')->get(['id', 'title', 'customer_id', 'owner_id', 'value_amount']),
            'owners' => User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => Project::STATUSES,
            'filters' => $filters,
            'canSeeAllProjects' => $canSeeAll,
            'canReassignProjects' => $user->hasPermissionCode('projects.reassign'),
        ]);
    }

    private function attachProjectCosts($projects, string $orgId): void
    {
        $projectIds = $projects->pluck('id')->filter()->values();

        if ($projectIds->isEmpty()) {
            return;
        }

        $costs = Expense::where('org_id', $orgId)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid'])
            ->selectRaw('project_id, SUM(amount) as actual_cost')
            ->groupBy('project_id')
            ->pluck('actual_cost', 'project_id');

        $projects->each(function ($project) use ($costs): void {
            $actualCost = round((float) ($costs[$project->id] ?? 0), 2);
            $budget = (float) ($project->budget_amount ?? 0);
            $project->setAttribute('actual_cost', number_format($actualCost, 2, '.', ''));
            $project->setAttribute('gross_margin', number_format($budget - $actualCost, 2, '.', ''));
        });
    }

    public function store(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateProject($request);
        $this->assertDealMatchesCustomer($validated, $user);
        $validated['org_id'] = $user->org_id;
        $validated['project_code'] = $numbers->next($user->org_id, 'project');
        $validated['owner_id'] = ProjectAccess::canSeeAll($user) ? ($validated['owner_id'] ?? $user->id) : $user->id;
        $validated['created_by'] = $user->id;

        $project = Project::create($validated);
        $this->audit($request, 'project.create', $project, null, $this->snapshot($project));

        return back()->with('success', "Project {$project->project_code} created.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        ProjectAccess::assertProjectVisible($project, $user);
        $before = $this->snapshot($project);
        $validated = $this->validateProject($request, $project);
        $this->assertDealMatchesCustomer($validated, $user);
        $validated['owner_id'] = $project->owner_id;

        if ($user->hasPermissionCode('projects.reassign')) {
            $validated['owner_id'] = $request->input('owner_id') ?: $project->owner_id ?: $user->id;
        }

        $validated['updated_by'] = $user->id;
        $project->update($validated);
        $this->audit($request, 'project.update', $project, $before, $this->snapshot($project));

        return back()->with('success', "Project {$project->project_code} updated.");
    }

    public function storeFromDeal(Request $request, Deal $deal, NumberSequenceService $numbers): RedirectResponse
    {
        $user = $request->user();
        SalesAccess::assertDealVisible($deal, $user);
        abort_unless($deal->stage === 'won', 422, 'Only won deals can create projects.');
        abort_if(Project::where('org_id', $user->org_id)->where('deal_id', $deal->id)->exists(), 422, 'Project already exists for this deal.');

        $project = Project::create([
            'org_id' => $user->org_id,
            'project_code' => $numbers->next($user->org_id, 'project'),
            'name' => $deal->title,
            'customer_id' => $deal->customer_id,
            'deal_id' => $deal->id,
            'owner_id' => ProjectAccess::canSeeAll($user) ? ($deal->owner_id ?? $user->id) : $user->id,
            'status' => 'planning',
            'budget_amount' => $deal->value_amount,
            'currency' => $deal->currency ?: 'THB',
            'note' => 'Created from deal: '.$deal->title,
            'created_by' => $user->id,
        ]);
        $this->audit($request, 'project.create_from_deal', $project, null, $this->snapshot($project));

        return redirect()->route('projects.index')->with('success', "Project {$project->project_code} created from deal.");
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        $orgId = $request->user()->org_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'uuid', Rule::exists('customers', 'id')->where('org_id', $orgId)],
            'deal_id' => ['nullable', 'uuid', Rule::exists('deals', 'id')->where('org_id', $orgId), Rule::unique('projects', 'deal_id')->where('org_id', $orgId)->ignore($project?->id)],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function assertDealMatchesCustomer(array $validated, User $user): void
    {
        if (! filled($validated['deal_id'] ?? null)) {
            return;
        }

        $deal = Deal::where('org_id', $user->org_id)->whereKey($validated['deal_id'])->firstOrFail();
        SalesAccess::assertDealVisible($deal, $user);
        abort_unless($deal->stage === 'won', 422, 'Only won deals can be linked to projects.');

        if (filled($validated['customer_id'] ?? null)) {
            abort_unless($deal->customer_id === $validated['customer_id'], 422, 'Deal must belong to selected customer.');
        }
    }

    private function snapshot(Project $project): array
    {
        return $project->fresh()->only(['project_code', 'name', 'customer_id', 'deal_id', 'owner_id', 'status', 'start_date', 'due_date', 'progress_percent', 'budget_amount', 'currency', 'note']);
    }

    private function audit(Request $request, string $action, Project $project, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $project->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'project',
            'entity_id' => $project->id,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
