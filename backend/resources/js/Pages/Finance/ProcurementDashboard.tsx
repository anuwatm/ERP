import {
    DashboardKpiCard,
    DashboardLayout,
    DashboardList,
} from '@/Components/Dashboard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Po = {
    id: string;
    po_no: string;
    status: string;
    supplier: string | null;
    total: string;
    currency: string;
};
type Approval = {
    id: string;
    subject_type: string | null;
    subject_id: string | null;
};
type SupplierValue = {
    supplier_id: string | null;
    supplier: string;
    currency: string;
    total: number;
};
type Receipt = {
    id: string;
    grn_no: string;
    po_no: string | null;
    received_date: string | null;
    status: string;
};
type LowStockLocation = {
    product_id: string;
    sku: string | null;
    name: string;
    unit: string | null;
    reorder_point: string;
    warehouse_id: string;
    warehouse_name: string;
    on_hand: string;
};

export default function ProcurementDashboard({
    can,
    openPoCount,
    poStatus,
    pendingApprovals,
    pendingApprovalCount,
    openPos,
    supplierValues,
    receipts,
    lowStockCount,
    lowStock,
}: {
    can: {
        po: boolean;
        inventory: boolean;
        workflow: boolean;
        workflowLink: boolean;
    };
    openPoCount: number | null;
    poStatus: Record<string, number> | null;
    pendingApprovals: Approval[] | null;
    pendingApprovalCount: number | null;
    openPos: Po[] | null;
    supplierValues: SupplierValue[] | null;
    receipts: Receipt[] | null;
    lowStockCount: number | null;
    lowStock: LowStockLocation[] | null;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Procurement / Inventory Dashboard" />
            <DashboardLayout
                title="Procurement / Inventory"
                description="Current purchase orders, your approval inbox, and recent receipts."
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    {can.po && (
                        <DashboardKpiCard
                            question="Open POs"
                            value={openPoCount ?? 0}
                            context="Sent, approved, or partially received"
                            tone="pending"
                        />
                    )}
                    {can.workflow && (
                        <DashboardKpiCard
                            question="My Pending Approvals"
                            value={pendingApprovalCount ?? 0}
                            context="Pending workflow steps assigned to you"
                            tone="pending"
                        />
                    )}
                    {can.inventory && (
                        <DashboardKpiCard
                            question="Low Stock"
                            value={lowStockCount ?? 0}
                            context="Tracked stock locations at or below their positive reorder point"
                            tone="risk"
                        />
                    )}
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    {can.po && (
                        <>
                            <DashboardList
                                title="PO Status"
                                description="Current orders in this organization"
                                emptyMessage="No purchase orders."
                                items={Object.entries(poStatus ?? {}).map(
                                    ([status, count]) => ({
                                        id: status,
                                        title: status,
                                        detail: `${count} orders`,
                                        routeName: 'purchase-orders.index',
                                        routeParameters: { status },
                                    }),
                                )}
                            />
                            <DashboardList
                                title="Open Purchase Orders"
                                description="Most recent 10 open orders"
                                emptyMessage="No open purchase orders."
                                items={(openPos ?? []).map((po) => ({
                                    id: po.id,
                                    title: po.po_no,
                                    detail: `${po.supplier ?? 'Unknown supplier'} · ${po.status} · ${po.currency} ${po.total}`,
                                    routeName: 'purchase-orders.index',
                                    routeParameters: { po_id: po.id },
                                }))}
                            />
                            <DashboardList
                                title="PO Order Value by Supplier"
                                description="Non-draft, non-cancelled PO totals; currencies remain separate. This is committed order value, not paid spend."
                                emptyMessage="No committed orders."
                                items={(supplierValues ?? []).map((value) => ({
                                    id: `${value.supplier_id}-${value.currency}`,
                                    title: value.supplier,
                                    detail: `${value.currency} ${value.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                                    routeName: 'purchase-orders.index',
                                    routeParameters: {
                                        supplier_id:
                                            value.supplier_id ?? undefined,
                                        currency: value.currency,
                                        committed: 1,
                                    },
                                }))}
                            />
                        </>
                    )}
                    {can.workflow && (
                        <DashboardList
                            title="My Pending Approvals"
                            description="Directly assigned workflow steps"
                            emptyMessage="No pending approvals."
                            items={(pendingApprovals ?? [])
                                .slice(0, 10)
                                .map((approval) => ({
                                    id: approval.id,
                                    title: `${approval.subject_type ?? 'Document'} approval`,
                                    detail: approval.subject_id ?? approval.id,
                                    routeName: can.workflowLink
                                        ? 'workflows.index'
                                        : undefined,
                                    routeParameters: {
                                        approval_id: approval.id,
                                    },
                                }))}
                        />
                    )}
                    {can.inventory && (
                        <>
                            <DashboardList
                                title="Low Stock"
                                description="Recorded stock locations by product and warehouse; first 10"
                                items={(lowStock ?? []).map((location) => ({
                                    id: `${location.product_id}-${location.warehouse_id}`,
                                    title: `${location.name} · ${location.warehouse_name}`,
                                    detail: `${location.sku ?? 'No SKU'} · on hand ${location.on_hand} ${location.unit ?? ''} · reorder ${location.reorder_point}`,
                                    routeName: 'inventory-operations.index',
                                    routeParameters: {
                                        product_id: location.product_id,
                                        warehouse_id: location.warehouse_id,
                                    },
                                }))}
                                emptyMessage="No low stock items."
                            />
                            <DashboardList
                                title="Recent Goods Receipts"
                                description="Most recent 10 receipts"
                                emptyMessage="No goods receipts."
                                items={(receipts ?? []).map((receipt) => ({
                                    id: receipt.id,
                                    title: receipt.grn_no,
                                    detail: `${receipt.po_no ?? 'No PO'} · ${receipt.status} · ${receipt.received_date ?? 'No date'}`,
                                    routeName: 'goods-receipts.index',
                                    routeParameters: { receipt_id: receipt.id },
                                }))}
                            />
                        </>
                    )}
                </div>
            </DashboardLayout>
        </AuthenticatedLayout>
    );
}
