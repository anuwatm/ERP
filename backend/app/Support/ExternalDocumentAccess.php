<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\FixedAsset;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ExternalDocumentAccess
{
    public static function query(Request $request, string $scope = 'documents:read'): Builder
    {
        $issuer = ExternalBusinessAccess::issuer($request, $scope);
        $key = $request->attributes->get('integration_key');

        return self::forUser($issuer)->whereIn('documents.id', $key->access_policy['document_ids'] ?? []);
    }

    public static function forUser(User $user): Builder
    {
        $org = $user->org_id;
        $roles = $user->roles()->with('permissions')->get();
        $all = $roles->contains(fn ($role) => in_array($role->code, ['owner', 'admin'], true));
        $permissions = $roles->flatMap(fn ($role) => $role->permissions->pluck('code'))->unique()->all();
        $customers = Customer::where('org_id', $org)->when(! $all, fn ($q) => $q->where('owner_id', $user->id));
        $deals = Deal::where('org_id', $org)->when(! $all, fn ($q) => $q->where('owner_id', $user->id));
        $projects = Project::where('org_id', $org)->when(! $all, fn ($q) => $q->where(fn ($own) => $own->where('owner_id', $user->id)->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id))));
        $tasks = Task::where('org_id', $org)->where(fn ($q) => $q->whereNull('project_id')->orWhereIn('project_id', Project::where('org_id', $org)->select('id')))
            ->when(! $all, fn ($q) => $q->where(fn ($own) => $own->where('assignee_id', $user->id)->orWhereHas('project', fn ($project) => $project->where('org_id', $org)->where('owner_id', $user->id))));
        $parents = [
            'customer' => [$customers, 'customers.view'], 'deal' => [$deals, 'deals.view'],
            'project' => [$projects, 'projects.view'], 'task' => [$tasks, 'tasks.view'],
            'supplier' => [Supplier::where('org_id', $org), 'suppliers.view'],
            'purchase_order' => [PurchaseOrder::where('org_id', $org), 'purchase_orders.view'],
            'fixed_asset' => [FixedAsset::where('org_id', $org), 'fixed_assets.view'],
        ];

        return Document::where('documents.org_id', $org)->where('status', 'active')->whereIn('sensitivity', ['org_internal', 'department_restricted'])
            ->where(fn ($q) => $q->whereNull('category_id')->orWhereIn('category_id', DocumentCategory::where('org_id', $org)->whereIn('default_sensitivity', ['org_internal', 'department_restricted'])->select('id')))
            ->whereHas('currentVersion', fn ($version) => $version->where('org_id', $org)->whereColumn('document_versions.document_id', 'documents.id'))
            ->whereDoesntHave('links', function ($links) use ($org, $parents, $permissions) {
                $links->where(function ($invalid) use ($org, $parents, $permissions) {
                    $invalid->where('org_id', '!=', $org)->orWhereNot(function ($allowed) use ($parents, $permissions) {
                        foreach ($parents as $type => [$query, $permission]) {
                            if (in_array($permission, $permissions, true)) {
                                $allowed->orWhere(fn ($parent) => $parent->where('linkable_type', $type)->whereIn('linkable_id', (clone $query)->select('id')));
                            }
                        }
                        // An empty permission allowlist must still be a false condition.
                        $allowed->orWhereRaw('1 = 0');
                    });
                });
            });
    }
}
