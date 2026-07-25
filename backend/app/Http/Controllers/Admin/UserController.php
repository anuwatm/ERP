<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use App\Support\PersonIdMask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $canViewFullPersonId = $user->roles()->whereHas('permissions', fn ($query) => $query->where('code', 'person_id.view_full'))->exists();

        return Inertia::render('Admin/Users', [
            'users' => User::where('org_id', $user->org_id)->with('roles:id,code,name')->orderBy('name')->get()->map(fn (User $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'email' => $item->email,
                'status' => $item->status,
                'position' => $item->position,
                'person_id' => $canViewFullPersonId ? $item->person_id : PersonIdMask::mask($item->person_id),
                'roles' => $item->roles->map->only(['id', 'code', 'name'])->values(),
            ]),
            'roles' => Role::where('org_id', $user->org_id)->orderBy('code')->get(['id', 'code', 'name']),
            'branches' => Branch::where('org_id', $user->org_id)->orderBy('code')->get(['id', 'code', 'name']),
            'divisions' => Division::where('org_id', $user->org_id)->orderBy('code')->get(['id', 'branch_id', 'code', 'name']),
            'departments' => Department::where('org_id', $user->org_id)->orderBy('code')->get(['id', 'branch_id', 'division_id', 'code', 'name']),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'position' => ['nullable', 'string', 'max:100'],
            'person_id' => ['nullable', 'digits:13'],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)],
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('org_id', $actor->org_id)],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('org_id', $actor->org_id)],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('org_id', $actor->org_id)],
        ]);

        $this->validateHierarchy($validated, $actor->org_id);

        $plainToken = Str::random(48);

        $invited = DB::transaction(function () use ($actor, $validated, $plainToken): User {
            $user = User::create([
                'org_id' => $actor->org_id,
                'branch_id' => $validated['branch_id'],
                'division_id' => $validated['division_id'],
                'department_id' => $validated['department_id'],
                'name' => $validated['name'],
                'display_name' => $validated['name'],
                'email' => $validated['email'],
                'position' => $validated['position'] ?? null,
                'person_id' => $validated['person_id'] ?? null,
                'auth_provider' => 'local',
                'status' => 'invited',
                'invited_at' => now(),
                'invite_token_hash' => hash('sha256', $plainToken),
                'invite_expires_at' => now()->addHours(72),
                'created_by' => $actor->id,
            ]);

            $user->roles()->attach($validated['role_id'], [
                'assigned_at' => now(),
                'assigned_by' => $actor->id,
            ]);

            AuditLog::create([
                'org_id' => $actor->org_id,
                'actor_user_id' => $actor->id,
                'action' => 'user.invite',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'after_json' => ['email' => $user->email, 'role_id' => $validated['role_id']],
            ]);

            return $user;
        });

        return back()->with('success', 'Invite created. Accept token: '.$plainToken)->with('invite_url', route('invites.accept', ['user' => $invited->id, 'token' => $plainToken], absolute: false));
    }

    public function updateStructure(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->org_id === $actor->org_id, 404);

        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('org_id', $actor->org_id)],
            'division_id' => ['required', Rule::exists('divisions', 'id')->where('org_id', $actor->org_id)],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('org_id', $actor->org_id)],
        ]);

        $this->validateHierarchy($validated, $actor->org_id);

        $before = $user->only(['branch_id', 'division_id', 'department_id']);
        $user->update($validated + ['updated_by' => $actor->id]);

        AuditLog::create([
            'org_id' => $actor->org_id,
            'actor_user_id' => $actor->id,
            'action' => 'user.hierarchy_change',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'before_json' => $before,
            'after_json' => $user->only(['branch_id', 'division_id', 'department_id']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'User structure updated.');
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->org_id === $actor->org_id, 404);
        abort_if($user->id === $actor->id, 422, 'Cannot disable yourself.');
        abort_if($this->isLastOwner($user), 422, 'Cannot disable last owner.');

        $before = $user->only(['status']);
        $user->update(['status' => 'inactive']);

        AuditLog::create([
            'org_id' => $actor->org_id,
            'actor_user_id' => $actor->id,
            'action' => 'user.disable',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'before_json' => $before,
            'after_json' => ['status' => 'inactive'],
        ]);

        return back()->with('success', 'User disabled.');
    }

    private function validateHierarchy(array $data, string $orgId): void
    {
        $division = Division::where('org_id', $orgId)->where('id', $data['division_id'])->firstOrFail();
        $department = Department::where('org_id', $orgId)->where('id', $data['department_id'])->firstOrFail();

        abort_unless($division->branch_id === $data['branch_id'], 422, 'Division does not belong to branch.');
        abort_unless($department->branch_id === $data['branch_id'] && $department->division_id === $data['division_id'], 422, 'Department does not belong to division.');
    }

    private function isLastOwner(User $user): bool
    {
        if (! $user->roles()->where('code', 'owner')->exists()) {
            return false;
        }

        return User::where('org_id', $user->org_id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('code', 'owner'))
            ->count() <= 1;
    }
}
