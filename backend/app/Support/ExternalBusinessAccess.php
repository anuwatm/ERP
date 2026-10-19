<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceipt;
use App\Models\IntegrationApiKey;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ExternalBusinessAccess
{
    public static function issuer(Request $request, string $scope): User
    {
        $key = $request->attributes->get('integration_key');
        $issuer = User::where('org_id', $key->org_id)->where('status', 'active')->whereKey($key->created_by)->first();
        abort_unless($issuer && isset(IntegrationApiKey::SCOPE_PERMISSIONS[$scope]) && $issuer->hasPermissionCode(IntegrationApiKey::SCOPE_PERMISSIONS[$scope]), 403);

        return $issuer;
    }

    public static function query(Request $request, string $resource, string $scope): Builder
    {
        $key = $request->attributes->get('integration_key');
        $issuer = self::issuer($request, $scope);
        $principal = $key->access_policy['principal'] ?? 'organization';
        $party = $key->access_policy['party_id'] ?? null;
        $customers = SalesAccess::scopeCustomers(Customer::withTrashed(), $issuer)->select('id');
        if ($principal === 'customer') {
            $customers->whereKey($party)->whereNull('deleted_at')->where('status', 'active');
        }
        $suppliers = Supplier::where('org_id', $key->org_id)->select('id');
        if ($principal === 'supplier') {
            $suppliers->whereKey($party)->where('status', 'active');
        }
        $invoices = Invoice::where('org_id', $key->org_id)->whereIn('customer_id', clone $customers);
        if ($principal === 'customer') {
            $invoices->where('status', '!=', 'draft');
        }
        $orders = PurchaseOrder::where('org_id', $key->org_id)->whereIn('supplier_id', clone $suppliers);
        if ($principal === 'supplier') {
            $orders->where('status', '!=', 'draft');
        }

        return match ($resource) {
            'customers' => SalesAccess::scopeCustomers(Customer::query(), $issuer)->whereIn('id', $customers),
            'suppliers' => Supplier::where('org_id', $key->org_id)->whereIn('id', $suppliers),
            'quotations' => Quotation::where('org_id', $key->org_id)->whereIn('customer_id', $customers)
                ->when($principal === 'customer', fn ($q) => $q->where('status', '!=', 'draft')),
            'invoices' => $invoices,
            'delivery-orders' => DeliveryOrder::where('org_id', $key->org_id)->whereIn('invoice_id', $invoices->select('id'))
                ->when($principal === 'customer', fn ($q) => $q->where('status', '!=', 'draft')),
            'payments' => Payment::where('org_id', $key->org_id)->whereIn('invoice_id', $invoices->select('id'))
                ->with(['reversalOf' => fn ($q) => $q->where('org_id', $key->org_id)->select('id', 'invoice_id')]),
            'purchase-orders' => $orders,
            'goods-receipts' => GoodsReceipt::where('org_id', $key->org_id)->whereIn('purchase_order_id', $orders->select('id')),
            'projects' => ProjectAccess::scopeProjects(Project::query(), $issuer),
            'tasks' => TaskAccess::scopeTasks(Task::query(), $issuer)->where(fn ($q) => $q->whereNull('project_id')
                ->orWhereIn('project_id', ProjectAccess::scopeProjects(Project::query(), $issuer)->select('id'))),
            default => throw new \LogicException('Unsupported resource.'),
        };
    }
}
