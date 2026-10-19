<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationApiKey extends Model
{
    use UsesOrderedUuid;

    public const SCOPES = ['summary:sales:read', 'summary:finance:read', 'summary:projects:read',
        'reference:organization:read', 'reference:currencies:read', 'products:read',
        'products:prices:read', 'products:costs:read', 'inventory:read', 'summary:inventory:read',
        'summary:hr:read', 'summary:attendance:read', 'summary:leaves:read', 'summary:roster:read',
        'employees:read', 'employees:attendance:read', 'employees:roster:read', 'employees:leaves:read',
        'customers:read', 'suppliers:read', 'quotations:read', 'invoices:read', 'delivery-orders:read', 'payments:read',
        'purchase-orders:read', 'goods-receipts:read', 'projects:read', 'tasks:read', 'summary:procurement:read', 'approvals:read',
        'summary:manufacturing:read', 'manufacturing:boms:read', 'manufacturing:formulas:read', 'manufacturing:work-orders:read', 'manufacturing:costs:read', 'documents:read', 'documents:download'];

    public const SCOPE_PERMISSIONS = [
        'reference:organization:read' => 'settings.structure.view',
        'reference:currencies:read' => 'fx.view',
        'products:read' => 'products.view',
        'products:prices:read' => 'products.view',
        'products:costs:read' => 'products.manage',
        'inventory:read' => 'inventory.view',
        'summary:inventory:read' => 'inventory.view',
        'summary:hr:read' => 'hr.employees.manage',
        'summary:attendance:read' => 'hr.employees.manage',
        'summary:leaves:read' => 'hr.employees.manage',
        'summary:roster:read' => 'hr.employees.manage',
        'employees:read' => 'hr.employees.manage',
        'employees:attendance:read' => 'hr.employees.manage',
        'employees:roster:read' => 'hr.employees.manage',
        'employees:leaves:read' => 'hr.employees.manage',
        'customers:read' => 'customers.view',
        'suppliers:read' => 'suppliers.view',
        'quotations:read' => 'quotations.view',
        'invoices:read' => 'invoices.view',
        'delivery-orders:read' => 'delivery_orders.view',
        'payments:read' => 'payments.view',
        'purchase-orders:read' => 'purchase_orders.view',
        'goods-receipts:read' => 'inventory.view',
        'projects:read' => 'projects.view',
        'tasks:read' => 'tasks.view',
        'summary:procurement:read' => 'purchase_orders.view',
        'approvals:read' => 'workflows.approve',
        'summary:manufacturing:read' => 'manufacturing.work_orders.view',
        'manufacturing:boms:read' => 'manufacturing.bom.view',
        'manufacturing:formulas:read' => 'manufacturing.bom.manage',
        'manufacturing:work-orders:read' => 'manufacturing.work_orders.view',
        'manufacturing:costs:read' => 'manufacturing.work_orders.manage',
        'documents:read' => 'documents.view',
        'documents:download' => 'documents.download',
    ];

    public const PARTY_SCOPES = [
        'customer' => ['customers:read', 'quotations:read', 'invoices:read', 'delivery-orders:read', 'payments:read'],
        'supplier' => ['suppliers:read', 'purchase-orders:read', 'goods-receipts:read'],
    ];

    public function allowsPrincipalScope(string $scope): bool
    {
        $principal = $this->access_policy['principal'] ?? 'organization';

        return $principal === 'organization' || (isset(self::PARTY_SCOPES[$principal]) && in_array($scope, self::PARTY_SCOPES[$principal], true) && ! empty($this->access_policy['party_id']));
    }

    public static function grantableScopes(User $user): array
    {
        $permissions = $user->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('code'))->unique()->all();

        return array_values(array_filter(self::SCOPES, fn ($scope) => ! isset(self::SCOPE_PERMISSIONS[$scope]) || in_array(self::SCOPE_PERMISSIONS[$scope], $permissions, true)));
    }

    protected $fillable = ['org_id', 'name', 'prefix', 'secret_hash', 'scopes', 'access_policy', 'created_by', 'expires_at', 'revoked_at', 'last_used_at'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'access_policy' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
