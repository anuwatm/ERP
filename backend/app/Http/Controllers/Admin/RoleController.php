<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->org_id;

        return Inertia::render('Admin/Roles', [
            'roles' => Role::where('org_id', $orgId)
                ->with(['permissions:id,code,module,action'])
                ->withCount(['permissions', 'users'])
                ->orderBy('code')
                ->get(),
            'permissions' => Permission::orderBy('module')->orderBy('code')->get(['id', 'code', 'module', 'action', 'description']),
        ]);
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->org_id === $actor->org_id, 404);

        $validated = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')->where('org_id', $actor->org_id)],
        ]);

        $oldRoleIds = $user->roles()->pluck('roles.id')->all();
        $newRole = Role::where('org_id', $actor->org_id)->findOrFail($validated['role_id']);

        abort_if($this->wouldRemoveLastOwner($user, $oldRoleIds, $newRole->id), 422, 'Cannot remove last owner.');

        DB::transaction(function () use ($user, $newRole, $actor, $oldRoleIds): void {
            $user->roles()->sync([$newRole->id => ['assigned_at' => now(), 'assigned_by' => $actor->id]]);

            $roleChanged = count($oldRoleIds) !== 1 || ! in_array($newRole->id, $oldRoleIds);
            if ($roleChanged) {
                $user->forceFill(['remember_token' => Str::random(60)])->save();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            }
        });

        $this->audit($actor, 'user.role_change', 'user', $user->id, ['role_ids' => $oldRoleIds], ['role_ids' => [$newRole->id]]);

        return back()->with('success', 'Role updated.');
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($role->org_id === $actor->org_id, 404);
        abort_if($role->code === 'owner', 422, 'Owner role permissions are immutable.');

        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['uuid', Rule::exists('permissions', 'id')],
        ]);

        $permissionIds = $validated['permission_ids'] ?? [];
        $before = $role->permissions()->pluck('permissions.code')->sort()->values()->all();

        DB::transaction(function () use ($role, $permissionIds): void {
            $role->permissions()->sync($permissionIds);

            $userIds = $role->users()->pluck('users.id')->all();
            if (! empty($userIds)) {
                User::whereIn('id', $userIds)->update(['remember_token' => null]);
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }
            }
        });

        $after = $role->fresh()->permissions()->pluck('permissions.code')->sort()->values()->all();

        $this->audit($actor, 'role.permission_update', 'role', $role->id, ['permission_codes' => $before], ['permission_codes' => $after]);

        return back()->with('success', 'Role permissions updated.');
    }

    private function wouldRemoveLastOwner(User $user, array $oldRoleIds, string $newRoleId): bool
    {
        $wasOwner = Role::whereIn('id', $oldRoleIds)->where('code', 'owner')->exists();
        $willBeOwner = Role::where('id', $newRoleId)->where('code', 'owner')->exists();

        if (! $wasOwner || $willBeOwner) {
            return false;
        }

        return User::where('org_id', $user->org_id)
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->whereHas('roles', fn ($query) => $query->where('code', 'owner'))
            ->count() === 0;
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
