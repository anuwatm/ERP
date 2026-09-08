<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLink;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ProjectAccess;
use App\Support\SalesAccess;
use App\Support\TaskAccess;
use Illuminate\Database\Eloquent\Model;

class DocumentParentAccessService
{
    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'customer' => Customer::class,
        'deal' => Deal::class,
        'supplier' => Supplier::class,
        'purchase_order' => PurchaseOrder::class,
        'payment' => Payment::class,
        'expense' => Expense::class,
        'voucher' => Voucher::class,
        'project' => Project::class,
        'task' => Task::class,
        'fixed_asset' => FixedAsset::class,
        'bank_account' => BankAccount::class,
        'accounting_period' => AccountingPeriod::class,
        'user' => User::class,
    ];

    public function isSupported(string $type): bool
    {
        return isset(self::MODELS[$type]);
    }

    public function find(string $type, string $id, string $orgId): ?Model
    {
        $model = self::MODELS[$type] ?? null;

        return $model ? $model::query()->whereKey($id)->where('org_id', $orgId)->first() : null;
    }

    public function canAccess(User $user, string $type, Model $parent): bool
    {
        if ($parent->getAttribute('org_id') !== $user->org_id) {
            return false;
        }

        return match ($type) {
            'customer' => $user->hasPermissionCode('customers.view') && SalesAccess::scopeCustomers(Customer::query()->whereKey($parent->getKey()), $user)->exists(),
            'deal' => $user->hasPermissionCode('deals.view') && SalesAccess::scopeDeals(Deal::query()->whereKey($parent->getKey()), $user)->exists(),
            'supplier' => $user->hasPermissionCode('suppliers.view'),
            'purchase_order' => $user->hasPermissionCode('purchase_orders.view'),
            'payment' => $user->hasPermissionCode('payments.view'),
            'expense' => $user->hasPermissionCode('expenses.view'),
            'voucher' => $user->hasPermissionCode('vouchers.view'),
            'project' => $user->hasPermissionCode('projects.view') && ProjectAccess::scopeProjects(Project::query()->whereKey($parent->getKey()), $user)->exists(),
            'task' => $user->hasPermissionCode('tasks.view') && TaskAccess::scopeTasks(Task::query()->whereKey($parent->getKey()), $user)->exists(),
            'fixed_asset' => $user->hasPermissionCode('fixed_assets.view'),
            'bank_account' => $user->hasPermissionCode('treasury.accounts.view'),
            'accounting_period' => $user->hasPermissionCode('accounting.periods.view'),
            'user' => $parent->is($user) || $user->hasPermissionCode('users.view'),
            default => false,
        };
    }

    public function canAccessLink(User $user, DocumentLink $link): bool
    {
        $parent = $this->find($link->linkable_type, $link->linkable_id, $user->org_id);

        return $parent !== null && $this->canAccess($user, $link->linkable_type, $parent);
    }
}
