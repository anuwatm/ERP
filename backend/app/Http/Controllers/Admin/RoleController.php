<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $orgId = auth()->user()->org_id;

        return Inertia::render('Admin/Roles', [
            'roles' => Role::where('org_id', $orgId)->withCount(['permissions', 'users'])->orderBy('code')->get(),
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

        $user->roles()->sync([$newRole->id => ['assigned_at' => now(), 'assigned_by' => $actor->id]]);

        AuditLog::create([
            'org_id' => $actor->org_id,
            'actor_user_id' => $actor->id,
            'action' => 'user.role_change',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'before_json' => ['role_ids' => $oldRoleIds],
            'after_json' => ['role_ids' => [$newRole->id]],
        ]);

        return back()->with('success', 'Role updated.');
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
}
