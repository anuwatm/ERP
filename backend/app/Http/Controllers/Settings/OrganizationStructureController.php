<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use App\Services\NumberSequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationStructureController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->org_id;

        return Inertia::render('Settings/OrganizationStructure', [
            'branches' => Branch::where('org_id', $orgId)->orderBy('code')->get(['id', 'code', 'name', 'address', 'phone', 'is_head_office', 'status']),
            'divisions' => Division::where('org_id', $orgId)->orderBy('code')->get(['id', 'branch_id', 'code', 'name', 'status']),
            'departments' => Department::where('org_id', $orgId)->orderBy('code')->get(['id', 'branch_id', 'division_id', 'code', 'name', 'status']),
        ]);
    }

    public function storeBranch(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_head_office' => ['boolean'],
        ]);

        DB::transaction(function () use ($actor, $validated, $numbers): void {
            $branch = Branch::create([
                'org_id' => $actor->org_id,
                'code' => $numbers->next($actor->org_id, 'branch'),
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_head_office' => false,
                'status' => 'active',
                'created_by' => $actor->id,
            ]);

            if ($validated['is_head_office'] ?? false) {
                $beforeHeadOffice = Branch::where('org_id', $actor->org_id)->where('is_head_office', true)->pluck('id')->all();
                $this->setHeadOfficeInTransaction($branch, $actor->id);
                $afterHeadOffice = Branch::where('org_id', $actor->org_id)->where('is_head_office', true)->pluck('id')->all();
                $this->audit($actor, 'branch.set_head_office', 'branch', $branch->id, ['head_office_branch_ids' => $beforeHeadOffice], ['head_office_branch_ids' => $afterHeadOffice]);
            }

            $this->audit($actor, 'branch.create', 'branch', $branch->id, null, $branch->fresh()->only(['code', 'name', 'address', 'phone', 'is_head_office', 'status']));
        });

        return back()->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($branch->org_id, $actor->org_id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_head_office' => ['boolean'],
        ]);

        DB::transaction(function () use ($actor, $branch, $validated): void {
            $before = $branch->only(['name', 'address', 'phone', 'is_head_office', 'status']);
            $branch->update([
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'updated_by' => $actor->id,
            ]);

            if ($validated['is_head_office'] ?? false) {
                $this->setHeadOfficeInTransaction($branch->refresh(), $actor->id);
            }

            $this->audit($actor, 'branch.update', 'branch', $branch->id, $before, $branch->fresh()->only(['name', 'address', 'phone', 'is_head_office', 'status']));
        });

        return back()->with('success', 'Branch updated.');
    }

    public function setHeadOffice(Request $request, Branch $branch): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($branch->org_id, $actor->org_id);

        DB::transaction(function () use ($actor, $branch): void {
            $before = Branch::where('org_id', $actor->org_id)->where('is_head_office', true)->pluck('id')->all();
            $this->setHeadOfficeInTransaction($branch, $actor->id);
            $after = Branch::where('org_id', $actor->org_id)->where('is_head_office', true)->pluck('id')->all();
            $this->audit($actor, 'branch.set_head_office', 'branch', $branch->id, ['head_office_branch_ids' => $before], ['head_office_branch_ids' => $after]);
        });

        return back()->with('success', 'Head office updated.');
    }

    public function disableBranch(Request $request, Branch $branch): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($branch->org_id, $actor->org_id);
        $this->assertBranchCanBeDisabled($branch);
        abort_if($branch->is_head_office, 422, 'Cannot disable the head office branch. Set another head office first.');

        $before = $branch->only(['status']);
        $branch->update(['status' => 'inactive', 'updated_by' => $actor->id]);
        $this->audit($actor, 'branch.disable', 'branch', $branch->id, $before, ['status' => 'inactive']);

        return back()->with('success', 'Branch disabled.');
    }

    public function destroyBranch(Request $request, Branch $branch): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($branch->org_id, $actor->org_id);
        $this->assertBranchCanBeDeleted($branch);
        abort_if($branch->is_head_office, 422, 'Cannot delete the head office branch. Set another head office first.');

        $before = $branch->only(['code', 'name', 'status']);
        $branch->delete();
        $this->audit($actor, 'branch.delete', 'branch', $branch->id, $before, null);

        return back()->with('success', 'Branch deleted.');
    }

    public function storeDivision(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $division = Division::create([
            'org_id' => $actor->org_id,
            'branch_id' => $validated['branch_id'],
            'code' => $numbers->next($actor->org_id, 'division', $validated['branch_id']),
            'name' => $validated['name'],
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        $this->audit($actor, 'division.create', 'division', $division->id, null, $division->only(['branch_id', 'code', 'name', 'status']));

        return back()->with('success', 'Division created.');
    }

    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($division->org_id, $actor->org_id);
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $before = $division->only(['branch_id', 'name', 'status']);
        $division->update($validated + ['updated_by' => $actor->id]);
        $this->audit($actor, 'division.update', 'division', $division->id, $before, $division->fresh()->only(['branch_id', 'name', 'status']));

        return back()->with('success', 'Division updated.');
    }

    public function disableDivision(Request $request, Division $division): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($division->org_id, $actor->org_id);
        $this->assertDivisionCanBeDisabled($division);

        $before = $division->only(['status']);
        $division->update(['status' => 'inactive', 'updated_by' => $actor->id]);
        $this->audit($actor, 'division.disable', 'division', $division->id, $before, ['status' => 'inactive']);

        return back()->with('success', 'Division disabled.');
    }

    public function destroyDivision(Request $request, Division $division): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($division->org_id, $actor->org_id);
        $this->assertDivisionCanBeDeleted($division);

        $before = $division->only(['code', 'name', 'status']);
        $division->delete();
        $this->audit($actor, 'division.delete', 'division', $division->id, $before, null);

        return back()->with('success', 'Division deleted.');
    }

    public function storeDepartment(Request $request, NumberSequenceService $numbers): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $this->validateDivisionBranch($validated['branch_id'], $validated['division_id'], $actor->org_id);

        $department = Department::create([
            'org_id' => $actor->org_id,
            'branch_id' => $validated['branch_id'],
            'division_id' => $validated['division_id'],
            'code' => $numbers->next($actor->org_id, 'department', $validated['branch_id']),
            'name' => $validated['name'],
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        $this->audit($actor, 'department.create', 'department', $department->id, null, $department->only(['branch_id', 'division_id', 'code', 'name', 'status']));

        return back()->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($department->org_id, $actor->org_id);
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('org_id', $actor->org_id)->where('status', 'active')],
            'name' => ['required', 'string', 'max:255'],
        ]);
        $this->validateDivisionBranch($validated['branch_id'], $validated['division_id'], $actor->org_id);

        $before = $department->only(['branch_id', 'division_id', 'name', 'status']);
        $department->update($validated + ['updated_by' => $actor->id]);
        $this->audit($actor, 'department.update', 'department', $department->id, $before, $department->fresh()->only(['branch_id', 'division_id', 'name', 'status']));

        return back()->with('success', 'Department updated.');
    }

    public function disableDepartment(Request $request, Department $department): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($department->org_id, $actor->org_id);
        $this->assertDepartmentCanBeDisabled($department);

        $before = $department->only(['status']);
        $department->update(['status' => 'inactive', 'updated_by' => $actor->id]);
        $this->audit($actor, 'department.disable', 'department', $department->id, $before, ['status' => 'inactive']);

        return back()->with('success', 'Department disabled.');
    }

    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        $actor = $request->user();
        $this->assertSameOrg($department->org_id, $actor->org_id);
        $this->assertDepartmentCanBeDeleted($department);

        $before = $department->only(['code', 'name', 'status']);
        $department->delete();
        $this->audit($actor, 'department.delete', 'department', $department->id, $before, null);

        return back()->with('success', 'Department deleted.');
    }

    private function setHeadOfficeInTransaction(Branch $branch, string $actorId): void
    {
        Branch::where('org_id', $branch->org_id)->where('is_head_office', true)->update(['is_head_office' => false, 'updated_by' => $actorId]);
        $branch->update(['is_head_office' => true, 'status' => 'active', 'updated_by' => $actorId]);
    }

    private function validateDivisionBranch(string $branchId, string $divisionId, string $orgId): void
    {
        $division = Division::where('org_id', $orgId)->findOrFail($divisionId);
        abort_unless($division->branch_id === $branchId, 422, 'Division does not belong to branch.');
    }

    private function assertBranchCanBeDisabled(Branch $branch): void
    {
        abort_if(Division::where('branch_id', $branch->id)->where('status', 'active')->exists(), 422, 'Cannot disable branch while active divisions exist.');
        abort_if(Department::where('branch_id', $branch->id)->where('status', 'active')->exists(), 422, 'Cannot disable branch while active departments exist.');
        abort_if(User::where('branch_id', $branch->id)->whereIn('status', ['active', 'invited'])->exists(), 422, 'Cannot disable branch while active or invited users are assigned.');
    }

    private function assertBranchCanBeDeleted(Branch $branch): void
    {
        abort_if(Division::where('branch_id', $branch->id)->exists(), 422, 'Cannot delete branch while divisions exist.');
        abort_if(Department::where('branch_id', $branch->id)->exists(), 422, 'Cannot delete branch while departments exist.');
        abort_if(User::where('branch_id', $branch->id)->exists(), 422, 'Cannot delete branch while users are assigned.');
    }

    private function assertDivisionCanBeDisabled(Division $division): void
    {
        abort_if(Department::where('division_id', $division->id)->where('status', 'active')->exists(), 422, 'Cannot disable division while active departments exist.');
        abort_if(User::where('division_id', $division->id)->whereIn('status', ['active', 'invited'])->exists(), 422, 'Cannot disable division while active or invited users are assigned.');
    }

    private function assertDivisionCanBeDeleted(Division $division): void
    {
        abort_if(Department::where('division_id', $division->id)->exists(), 422, 'Cannot delete division while departments exist.');
        abort_if(User::where('division_id', $division->id)->exists(), 422, 'Cannot delete division while users are assigned.');
    }

    private function assertDepartmentCanBeDisabled(Department $department): void
    {
        abort_if(User::where('department_id', $department->id)->whereIn('status', ['active', 'invited'])->exists(), 422, 'Cannot disable department while active or invited users are assigned.');
    }

    private function assertDepartmentCanBeDeleted(Department $department): void
    {
        abort_if(User::where('department_id', $department->id)->exists(), 422, 'Cannot delete department while users are assigned.');
    }

    private function assertSameOrg(string $entityOrgId, string $actorOrgId): void
    {
        abort_unless($entityOrgId === $actorOrgId, 404);
    }

    private function audit($actor, string $action, string $entityType, ?string $entityId, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'org_id' => $actor->org_id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
