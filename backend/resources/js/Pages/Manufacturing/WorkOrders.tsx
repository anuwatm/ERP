import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/Types';
import { money } from '@/Utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import {
    IconCheck,
    IconClose,
    IconDollar,
    IconEye,
    IconFactory,
    IconLayers,
    IconPlay,
    IconPlus,
    IconSearch,
    IconShieldCheck,
    IconWarehouse,
    IconXCircle,
} from './Icons';

export interface WarehouseSummary {
    id: string;
    code: string;
    name: string;
}

export interface BomSummary {
    id: string;
    bom_no: string;
    name: string;
    version: string;
    product_id: string;
    quantity: string;
    unit: string;
    total_cost: string;
    product?: {
        id: string;
        sku: string;
        name: string;
        unit: string | null;
    };
}

export interface WorkOrderItemRecord {
    id: string;
    product_id: string;
    product?: {
        id: string;
        sku: string;
        name: string;
        unit: string | null;
    };
    required_quantity: string;
    issued_quantity: string;
    unit: string;
    unit_cost: string;
    total_cost: string;
}

export interface ProductionCostRecord {
    id: string;
    cost_type: 'labor' | 'overhead';
    description: string;
    amount: string;
    cost_date: string;
}

export interface StockMovementRecord {
    id: string;
    movement_type: string;
    quantity: string;
    unit_cost: string;
    total_cost: string;
    created_at: string;
}

export interface WorkOrderRecord {
    id: string;
    wo_no: string;
    bill_of_materials_id: string;
    bill_of_materials?: BomSummary;
    product_id: string;
    product?: {
        id: string;
        sku: string;
        name: string;
        unit: string | null;
    };
    warehouse_id?: string | null;
    warehouse?: WarehouseSummary | null;
    source_warehouse_id?: string | null;
    source_warehouse?: WarehouseSummary | null;
    status:
        | 'draft'
        | 'planned'
        | 'in_progress'
        | 'quality_check'
        | 'completed'
        | 'cancelled';
    planned_quantity: string;
    actual_quantity: string;
    scrap_quantity: string;
    unit: string;
    material_cost: string;
    labor_cost: string;
    overhead_cost: string;
    total_cost: string;
    unit_cost: string;
    start_date?: string | null;
    due_date?: string | null;
    completed_date?: string | null;
    notes?: string | null;
    qc_notes?: string | null;
    qc_passed: boolean;
    created_at: string;
    creator?: { id: string; name: string };
    items?: WorkOrderItemRecord[];
    production_costs?: ProductionCostRecord[];
    stock_movements?: StockMovementRecord[];
}

interface Props {
    workOrders: WorkOrderRecord[];
    boms: BomSummary[];
    warehouses: WarehouseSummary[];
    filters: {
        status?: string;
        search?: string;
        start_date?: string;
        due_date?: string;
    };
}

const STATUS_CONFIG: Record<
    WorkOrderRecord['status'],
    {
        label: string;
        variant: 'neutral' | 'info' | 'warning' | 'success' | 'danger';
        step: number;
    }
> = {
    draft: { label: 'Draft', variant: 'neutral', step: 1 },
    planned: { label: 'Planned', variant: 'info', step: 2 },
    in_progress: { label: 'In Progress', variant: 'warning', step: 3 },
    quality_check: { label: 'Quality Check', variant: 'info', step: 4 },
    completed: { label: 'Completed', variant: 'success', step: 5 },
    cancelled: { label: 'Cancelled', variant: 'danger', step: 0 },
};

const getTodayDateString = () => new Date().toISOString().split('T')[0];
const getDefaultDueDateString = () => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().split('T')[0];
};

export default function WorkOrdersIndex({
    workOrders,
    boms,
    warehouses,
    filters,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes(
        'manufacturing.work_orders.manage',
    );
    const canExecute = auth.permissions.includes(
        'manufacturing.work_orders.execute',
    );

    const createSubmission = useSubmissionKey();
    const costSubmission = useSubmissionKey();
    const qcSubmission = useSubmissionKey();
    const completeSubmission = useSubmissionKey();
    const actionSubmission = useSubmissionKey();

    // Filter states
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');

    // Modals
    const [createOpen, setCreateOpen] = useState(false);
    const [detailWo, setDetailWo] = useState<WorkOrderRecord | null>(null);
    const [costModalWo, setCostModalWo] = useState<WorkOrderRecord | null>(
        null,
    );
    const [qcModalWo, setQcModalWo] = useState<WorkOrderRecord | null>(null);
    const [completeModalWo, setCompleteModalWo] =
        useState<WorkOrderRecord | null>(null);

    // Create Work Order Form
    const createForm = useForm({
        bill_of_materials_id: '',
        planned_quantity: 1,
        source_warehouse_id: warehouses[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        start_date: getTodayDateString(),
        due_date: getDefaultDueDateString(),
        notes: '',
        flatten_materials: false,
    });

    // Add Direct Cost Form
    const costForm = useForm({
        cost_type: 'labor' as 'labor' | 'overhead',
        description: '',
        amount: 0,
        cost_date: getTodayDateString(),
    });

    // Quality Check Form
    const qcForm = useForm({
        actual_quantity: 1,
        scrap_quantity: 0,
        qc_notes: '',
        qc_passed: true,
    });

    // Complete Form
    const completeForm = useForm({
        completed_date: getTodayDateString(),
    });

    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('manufacturing.work-orders.index'),
            {
                search: search || undefined,
                status: statusFilter || undefined,
            },
            { preserveState: true },
        );
    };

    const handleCreateSubmit = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route('manufacturing.work-orders.store'), {
            headers: createSubmission.headers(),
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
                createSubmission.reset();
            },
        });
    };

    const handlePlan = (wo: WorkOrderRecord) => {
        router.post(
            route('manufacturing.work-orders.plan', wo.id),
            {},
            {
                headers: actionSubmission.headers(),
                onSuccess: () => actionSubmission.reset(),
            },
        );
    };

    const handleRelease = (wo: WorkOrderRecord) => {
        if (
            !confirm(
                `Release Work Order ${wo.wo_no}? This will issue raw materials from stock and post to Work-in-Progress (GL 1160).`,
            )
        )
            return;
        router.post(
            route('manufacturing.work-orders.release', wo.id),
            {},
            {
                headers: actionSubmission.headers(),
                onSuccess: () => actionSubmission.reset(),
            },
        );
    };

    const openQcModal = (wo: WorkOrderRecord) => {
        setQcModalWo(wo);
        qcForm.setData({
            actual_quantity: Number(wo.planned_quantity),
            scrap_quantity: 0,
            qc_notes: '',
            qc_passed: true,
        });
    };

    const handleQcSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!qcModalWo) return;
        qcForm.post(
            route('manufacturing.work-orders.quality-check', qcModalWo.id),
            {
                headers: qcSubmission.headers(),
                onSuccess: () => {
                    setQcModalWo(null);
                    qcSubmission.reset();
                },
            },
        );
    };

    const openCompleteModal = (wo: WorkOrderRecord) => {
        setCompleteModalWo(wo);
        completeForm.setData({
            completed_date: getTodayDateString(),
        });
    };

    const handleCompleteSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!completeModalWo) return;
        completeForm.post(
            route('manufacturing.work-orders.complete', completeModalWo.id),
            {
                headers: completeSubmission.headers(),
                onSuccess: () => {
                    setCompleteModalWo(null);
                    completeSubmission.reset();
                },
            },
        );
    };

    const handleCancel = (wo: WorkOrderRecord) => {
        if (
            !confirm(
                `Cancel Work Order ${wo.wo_no}? Any issued raw materials will be returned to stock and GL reversed.`,
            )
        )
            return;
        router.post(
            route('manufacturing.work-orders.cancel', wo.id),
            {},
            {
                headers: actionSubmission.headers(),
                onSuccess: () => actionSubmission.reset(),
            },
        );
    };

    const openCostModal = (wo: WorkOrderRecord) => {
        setCostModalWo(wo);
        costForm.setData({
            cost_type: 'labor',
            description: '',
            amount: 0,
            cost_date: getTodayDateString(),
        });
    };

    const handleCostSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!costModalWo) return;
        costForm.post(
            route('manufacturing.work-orders.costs', costModalWo.id),
            {
                headers: costSubmission.headers(),
                onSuccess: () => {
                    setCostModalWo(null);
                    costSubmission.reset();
                },
            },
        );
    };

    // Statistics
    const stats = useMemo(() => {
        const total = workOrders.length;
        const inProgress = workOrders.filter(
            (w) => w.status === 'in_progress',
        ).length;
        const qualityCheck = workOrders.filter(
            (w) => w.status === 'quality_check',
        ).length;
        const completed = workOrders.filter(
            (w) => w.status === 'completed',
        ).length;
        const totalValue = workOrders.reduce(
            (sum, w) => sum + Number(w.total_cost || 0),
            0,
        );
        return { total, inProgress, qualityCheck, completed, totalValue };
    }, [workOrders]);

    return (
        <AuthenticatedLayout>
            <Head title="Manufacturing - Work Orders" />

            <div className="space-y-6">
                <PageHeader
                    title="Work Orders & Production Run"
                    description="Track manufacturing floor execution, material issues, labor absorption, and finished goods intake"
                    actions={
                        canManage && (
                            <button
                                onClick={() => {
                                    createForm.reset();
                                    setCreateOpen(true);
                                }}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <IconPlus className="h-4 w-4" />
                                Create Work Order
                            </button>
                        )
                    }
                />

                {/* Top Stat Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Active Work Orders"
                        value={stats.inProgress}
                        icon={<IconFactory className="h-5 w-5" />}
                        iconBgColor="bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-300"
                        subtitle="Currently on shop floor"
                    />
                    <StatCard
                        title="Quality Inspection"
                        value={stats.qualityCheck}
                        icon={<IconShieldCheck className="h-5 w-5" />}
                        iconBgColor="bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-300"
                        subtitle="Awaiting QC verification"
                    />
                    <StatCard
                        title="Completed Runs"
                        value={stats.completed}
                        icon={<IconCheck className="h-5 w-5" />}
                        iconBgColor="bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-300"
                        subtitle="Received into inventory"
                    />
                    <StatCard
                        title="Total Manufacturing Cost"
                        value={money(stats.totalValue)}
                        icon={<IconDollar className="h-5 w-5" />}
                        iconBgColor="bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-300"
                        subtitle="Cumulative production value"
                    />
                </div>

                {/* Filters */}
                <Card>
                    <form
                        onSubmit={handleFilterSubmit}
                        className="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <div className="relative flex-1">
                            <IconSearch className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by WO #, product, or BOM recipe..."
                                className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div className="flex gap-2">
                            <select
                                value={statusFilter}
                                onChange={(e) =>
                                    setStatusFilter(e.target.value)
                                }
                                className="rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            >
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="planned">Planned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="quality_check">
                                    Quality Check
                                </option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <button
                                type="submit"
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                Filter
                            </button>
                        </div>
                    </form>
                </Card>

                {/* Work Orders Table */}
                <Card bodyClassName="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                                <tr>
                                    <th className="py-3.5 pl-6 pr-3">
                                        Order Number
                                    </th>
                                    <th className="px-3 py-3.5">
                                        Product & Recipe
                                    </th>
                                    <th className="px-3 py-3.5">Quantity</th>
                                    <th className="px-3 py-3.5">
                                        Target Warehouse
                                    </th>
                                    <th className="px-3 py-3.5">Cost Rollup</th>
                                    <th className="px-3 py-3.5">Timeline</th>
                                    <th className="px-3 py-3.5">Status</th>
                                    <th className="py-3.5 pl-3 pr-6 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {workOrders.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="py-12 text-center text-slate-400"
                                        >
                                            <IconFactory className="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600 mb-2" />
                                            No Work Orders found. Click "Create
                                            Work Order" to launch a production
                                            run.
                                        </td>
                                    </tr>
                                ) : (
                                    workOrders.map((wo) => {
                                        const cfg = STATUS_CONFIG[wo.status];
                                        return (
                                            <tr
                                                key={wo.id}
                                                className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                            >
                                                <td className="py-4 pl-6 pr-3 font-medium text-slate-900 dark:text-white">
                                                    <span className="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded">
                                                        {wo.wo_no}
                                                    </span>
                                                    <div className="text-xs text-slate-400 mt-1">
                                                        By{' '}
                                                        {wo.creator?.name ??
                                                            'System'}
                                                    </div>
                                                </td>

                                                <td className="px-3 py-4">
                                                    <div className="font-semibold text-slate-900 dark:text-white">
                                                        {wo.product?.name ??
                                                            '-'}
                                                    </div>
                                                    <div className="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                                        <IconLayers className="h-3 w-3" />
                                                        {
                                                            wo.bill_of_materials
                                                                ?.bom_no
                                                        }{' '}
                                                        (v
                                                        {
                                                            wo.bill_of_materials
                                                                ?.version
                                                        }
                                                        )
                                                    </div>
                                                </td>

                                                <td className="px-3 py-4">
                                                    <div className="font-semibold text-slate-900 dark:text-white">
                                                        {Number(
                                                            wo.planned_quantity,
                                                        )}{' '}
                                                        {wo.unit}
                                                    </div>
                                                    {wo.status ===
                                                        'completed' && (
                                                        <div className="text-xs text-emerald-600 dark:text-emerald-400">
                                                            Actual:{' '}
                                                            {Number(
                                                                wo.actual_quantity,
                                                            )}{' '}
                                                            {wo.unit}
                                                        </div>
                                                    )}
                                                    {Number(wo.scrap_quantity) >
                                                        0 && (
                                                        <div className="text-xs text-rose-500">
                                                            Scrap:{' '}
                                                            {Number(
                                                                wo.scrap_quantity,
                                                            )}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-3 py-4">
                                                    <div className="text-slate-800 dark:text-slate-200 flex items-center gap-1 text-xs">
                                                        <IconWarehouse className="h-3.5 w-3.5 text-slate-400" />
                                                        {wo.warehouse?.name ??
                                                            'Default'}
                                                    </div>
                                                    {wo.source_warehouse && (
                                                        <div className="text-[11px] text-slate-400">
                                                            From:{' '}
                                                            {
                                                                wo
                                                                    .source_warehouse
                                                                    .code
                                                            }
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-3 py-4">
                                                    <div className="font-semibold font-mono text-slate-900 dark:text-white">
                                                        {money(wo.total_cost)}
                                                    </div>
                                                    <div className="text-[11px] text-slate-400 font-mono">
                                                        {money(wo.unit_cost)} /{' '}
                                                        {wo.unit}
                                                    </div>
                                                </td>

                                                <td className="px-3 py-4 text-xs">
                                                    <div className="text-slate-600 dark:text-slate-400">
                                                        Start:{' '}
                                                        {wo.start_date ?? '-'}
                                                    </div>
                                                    <div className="text-slate-600 dark:text-slate-400">
                                                        Due:{' '}
                                                        {wo.due_date ?? '-'}
                                                    </div>
                                                </td>

                                                <td className="px-3 py-4">
                                                    <Badge
                                                        variant={cfg.variant}
                                                    >
                                                        {cfg.label}
                                                    </Badge>
                                                </td>

                                                <td className="py-4 pl-3 pr-6 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {/* Details Drawer */}
                                                        <button
                                                            onClick={() =>
                                                                setDetailWo(wo)
                                                            }
                                                            title="View Details & Floor Log"
                                                            className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 dark:text-slate-400"
                                                        >
                                                            <IconEye className="h-4 w-4" />
                                                        </button>

                                                        {/* Lifecycle Transitions */}
                                                        {canExecute &&
                                                            wo.status ===
                                                                'draft' && (
                                                                <button
                                                                    onClick={() =>
                                                                        handlePlan(
                                                                            wo,
                                                                        )
                                                                    }
                                                                    title="Confirm & Plan Run"
                                                                    className="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:bg-blue-950/60 dark:text-blue-300"
                                                                >
                                                                    Plan
                                                                </button>
                                                            )}

                                                        {canExecute &&
                                                            wo.status ===
                                                                'planned' && (
                                                                <button
                                                                    onClick={() =>
                                                                        handleRelease(
                                                                            wo,
                                                                        )
                                                                    }
                                                                    title="Release to Shop Floor (Issue Materials)"
                                                                    className="inline-flex items-center gap-1 rounded bg-amber-500 px-2 py-1 text-xs font-semibold text-white hover:bg-amber-600 shadow-sm"
                                                                >
                                                                    <IconPlay className="h-3 w-3 fill-current" />
                                                                    Release
                                                                </button>
                                                            )}

                                                        {canExecute &&
                                                            wo.status ===
                                                                'in_progress' && (
                                                                <>
                                                                    <button
                                                                        onClick={() =>
                                                                            openCostModal(
                                                                                wo,
                                                                            )
                                                                        }
                                                                        title="Add Direct Cost (Labor / O/H)"
                                                                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                                                                    >
                                                                        <IconDollar className="h-4 w-4" />
                                                                    </button>
                                                                    <button
                                                                        onClick={() =>
                                                                            openQcModal(
                                                                                wo,
                                                                            )
                                                                        }
                                                                        title="Submit to Quality Check"
                                                                        className="inline-flex items-center gap-1 rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white hover:bg-indigo-700 shadow-sm"
                                                                    >
                                                                        <IconShieldCheck className="h-3 w-3" />
                                                                        QC
                                                                    </button>
                                                                </>
                                                            )}

                                                        {canExecute &&
                                                            wo.status ===
                                                                'quality_check' && (
                                                                <button
                                                                    onClick={() =>
                                                                        openCompleteModal(
                                                                            wo,
                                                                        )
                                                                    }
                                                                    title="Complete Run & Receive Finished Goods"
                                                                    className="inline-flex items-center gap-1 rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700 shadow-sm"
                                                                >
                                                                    <IconCheck className="h-3 w-3" />
                                                                    Complete
                                                                </button>
                                                            )}

                                                        {canManage &&
                                                            (wo.status ===
                                                                'draft' ||
                                                                wo.status ===
                                                                    'planned' ||
                                                                wo.status ===
                                                                    'in_progress') && (
                                                                <button
                                                                    onClick={() =>
                                                                        handleCancel(
                                                                            wo,
                                                                        )
                                                                    }
                                                                    title="Cancel Work Order"
                                                                    className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 dark:hover:bg-slate-800"
                                                                >
                                                                    <IconXCircle className="h-4 w-4" />
                                                                </button>
                                                            )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* CREATE WORK ORDER MODAL */}
                {createOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
                        <div className="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <IconFactory className="h-5 w-5 text-indigo-600" />
                                    Launch New Work Order
                                </h3>
                                <button
                                    onClick={() => setCreateOpen(false)}
                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-500 dark:hover:bg-slate-800"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleCreateSubmit}
                                className="p-6 space-y-4"
                            >
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Bill of Materials Recipe *
                                    </label>
                                    <select
                                        required
                                        value={
                                            createForm.data.bill_of_materials_id
                                        }
                                        onChange={(e) =>
                                            createForm.setData(
                                                'bill_of_materials_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="">
                                            Select BOM Recipe
                                        </option>
                                        {boms.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.bom_no} - {b.name} (Output:{' '}
                                                {b.product?.name ?? '-'})
                                            </option>
                                        ))}
                                    </select>
                                    {createForm.errors.bill_of_materials_id && (
                                        <p className="mt-1 text-xs text-rose-500">
                                            {
                                                createForm.errors
                                                    .bill_of_materials_id
                                            }
                                        </p>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Planned Quantity *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            value={
                                                createForm.data.planned_quantity
                                            }
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'planned_quantity',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 1,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Output Destination Warehouse *
                                        </label>
                                        <select
                                            value={createForm.data.warehouse_id}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'warehouse_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            {warehouses.map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.code} - {w.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Raw Materials Source Warehouse
                                        </label>
                                        <select
                                            value={
                                                createForm.data
                                                    .source_warehouse_id
                                            }
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'source_warehouse_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            {warehouses.map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.code} - {w.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Planned Start Date
                                        </label>
                                        <input
                                            type="date"
                                            value={createForm.data.start_date}
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'start_date',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Planned Due Date
                                    </label>
                                    <input
                                        type="date"
                                        value={createForm.data.due_date}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'due_date',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Production Notes / Work Order
                                        Instructions
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={createForm.data.notes}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Priority instructions, technician assignment..."
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                                    <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        <input
                                            type="checkbox"
                                            checked={
                                                Boolean(createForm.data.flatten_materials)
                                            }
                                            onChange={(e) =>
                                                createForm.setData(
                                                    'flatten_materials',
                                                    e.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800"
                                        />
                                        <span>Flatten Multi-Level BOM into Raw Materials</span>
                                    </label>
                                    <p className="text-[11px] text-slate-400 mt-1 ml-5">
                                        Recursively explodes sub-assemblies and issues pure raw materials to this work order directly.
                                    </p>
                                </div>

                                <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setCreateOpen(false)}
                                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={createForm.processing}
                                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none disabled:opacity-50"
                                    >
                                        {createForm.processing
                                            ? 'Creating...'
                                            : 'Initialize Work Order'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* RECORD DIRECT COST MODAL */}
                {costModalWo && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                        <div className="relative w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <IconDollar className="h-5 w-5 text-indigo-600" />
                                    Add Production Cost
                                </h3>
                                <button
                                    onClick={() => setCostModalWo(null)}
                                    className="rounded-lg p-1.5 text-slate-400"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleCostSubmit}
                                className="p-6 space-y-4"
                            >
                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Cost Type *
                                    </label>
                                    <select
                                        value={costForm.data.cost_type}
                                        onChange={(e) =>
                                            costForm.setData(
                                                'cost_type',
                                                e.target.value as
                                                    'labor' | 'overhead',
                                            )
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    >
                                        <option value="labor">
                                            Direct Labor (Dr. WIP / Cr. 5500)
                                        </option>
                                        <option value="overhead">
                                            Factory Overhead (Dr. WIP / Cr.
                                            5200)
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Description *
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={costForm.data.description}
                                        onChange={(e) =>
                                            costForm.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Overtime assembly shifts or machine calibration"
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Amount (THB) *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        value={costForm.data.amount}
                                        onChange={(e) =>
                                            costForm.setData(
                                                'amount',
                                                parseFloat(e.target.value) || 0,
                                            )
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setCostModalWo(null)}
                                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={costForm.processing}
                                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    >
                                        Record Cost
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* QUALITY CHECK MODAL */}
                {qcModalWo && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                        <div className="relative w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <IconShieldCheck className="h-5 w-5 text-indigo-600" />
                                    Quality Assurance Inspection
                                </h3>
                                <button
                                    onClick={() => setQcModalWo(null)}
                                    className="rounded-lg p-1.5 text-slate-400"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleQcSubmit}
                                className="p-6 space-y-4"
                            >
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Actual Produced Qty *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            value={qcForm.data.actual_quantity}
                                            onChange={(e) =>
                                                qcForm.setData(
                                                    'actual_quantity',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Scrap / Defective Qty
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={qcForm.data.scrap_quantity}
                                            onChange={(e) =>
                                                qcForm.setData(
                                                    'scrap_quantity',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        QC Inspection Notes
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={qcForm.data.qc_notes}
                                        onChange={(e) =>
                                            qcForm.setData(
                                                'qc_notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Tolerances, visual inspection, stress tests..."
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div className="flex items-center gap-3 pt-2">
                                    <input
                                        type="checkbox"
                                        id="qc_passed"
                                        checked={qcForm.data.qc_passed}
                                        onChange={(e) =>
                                            qcForm.setData(
                                                'qc_passed',
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <label
                                        htmlFor="qc_passed"
                                        className="text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        Passed QC Inspection
                                    </label>
                                </div>

                                <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setQcModalWo(null)}
                                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={qcForm.processing}
                                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                                    >
                                        Confirm Inspection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* COMPLETE WORK ORDER MODAL */}
                {completeModalWo && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                        <div className="relative w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <IconCheck className="h-5 w-5 text-emerald-600" />
                                    Complete Work Order & Receive FG
                                </h3>
                                <button
                                    onClick={() => setCompleteModalWo(null)}
                                    className="rounded-lg p-1.5 text-slate-400"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <form
                                onSubmit={handleCompleteSubmit}
                                className="p-6 space-y-4"
                            >
                                <div className="rounded-lg bg-emerald-50 dark:bg-emerald-950/40 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                                    Receiving{' '}
                                    <strong>
                                        {Number(
                                            completeModalWo.actual_quantity ||
                                                completeModalWo.planned_quantity,
                                        )}{' '}
                                        {completeModalWo.unit}
                                    </strong>{' '}
                                    into warehouse. This will post inventory
                                    stock receipt and journal entry (Dr. 1140
                                    Finished Goods / Cr. 1160 WIP + Cr.
                                    Labor/OH).
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                        Completion Date *
                                    </label>
                                    <input
                                        type="date"
                                        required
                                        value={completeForm.data.completed_date}
                                        onChange={(e) =>
                                            completeForm.setData(
                                                'completed_date',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    />
                                </div>

                                <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setCompleteModalWo(null)}
                                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={completeForm.processing}
                                        className="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                                    >
                                        Receive Finished Goods
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* WORK ORDER DETAIL DRAWER / MODAL */}
                {detailWo && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
                        <div className="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800 max-h-[92vh] flex flex-col overflow-hidden">
                            {/* Header */}
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                            {detailWo.wo_no}
                                        </span>
                                        <Badge
                                            variant={
                                                STATUS_CONFIG[detailWo.status]
                                                    .variant
                                            }
                                        >
                                            {
                                                STATUS_CONFIG[detailWo.status]
                                                    .label
                                            }
                                        </Badge>
                                    </div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white mt-1">
                                        {detailWo.product?.name} (
                                        {Number(detailWo.planned_quantity)}{' '}
                                        {detailWo.unit})
                                    </h3>
                                </div>
                                <button
                                    onClick={() => setDetailWo(null)}
                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-500 dark:hover:bg-slate-800"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto p-6 space-y-6">
                                {/* Lifecycle Stepper */}
                                <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                                    <div className="flex items-center justify-between text-xs font-semibold text-slate-500">
                                        {[
                                            'Draft',
                                            'Planned',
                                            'In Progress',
                                            'Quality Check',
                                            'Completed',
                                        ].map((st, i) => {
                                            const currentStep =
                                                STATUS_CONFIG[detailWo.status]
                                                    .step;
                                            const isDone = currentStep > i + 1;
                                            const isCurrent =
                                                currentStep === i + 1;
                                            return (
                                                <div
                                                    key={st}
                                                    className="flex flex-col items-center flex-1"
                                                >
                                                    <div
                                                        className={`h-7 w-7 rounded-full flex items-center justify-center font-bold text-xs mb-1 ${
                                                            isDone
                                                                ? 'bg-emerald-600 text-white'
                                                                : isCurrent
                                                                  ? 'bg-indigo-600 text-white'
                                                                  : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                                        }`}
                                                    >
                                                        {isDone ? '✓' : i + 1}
                                                    </div>
                                                    <span
                                                        className={
                                                            isCurrent
                                                                ? 'text-indigo-600 font-bold'
                                                                : ''
                                                        }
                                                    >
                                                        {st}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Financial Cost Breakdown */}
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div className="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                        <div className="text-xs text-slate-500">
                                            Material Cost
                                        </div>
                                        <div className="text-base font-bold font-mono text-slate-900 dark:text-white">
                                            {money(detailWo.material_cost)}
                                        </div>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                        <div className="text-xs text-slate-500">
                                            Direct Labor
                                        </div>
                                        <div className="text-base font-bold font-mono text-slate-900 dark:text-white">
                                            {money(detailWo.labor_cost)}
                                        </div>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                        <div className="text-xs text-slate-500">
                                            Factory Overhead
                                        </div>
                                        <div className="text-base font-bold font-mono text-slate-900 dark:text-white">
                                            {money(detailWo.overhead_cost)}
                                        </div>
                                    </div>
                                    <div className="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-900 dark:bg-indigo-950/30">
                                        <div className="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">
                                            Total / Unit Cost
                                        </div>
                                        <div className="text-base font-bold font-mono text-indigo-700 dark:text-indigo-300">
                                            {money(detailWo.total_cost)}
                                        </div>
                                        <div className="text-[11px] text-slate-500 font-mono">
                                            {money(detailWo.unit_cost)} /{' '}
                                            {detailWo.unit}
                                        </div>
                                    </div>
                                </div>

                                {/* Bill of Materials Requirements Table */}
                                <div className="space-y-2">
                                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                        Materials Allocated & Issued
                                    </h4>
                                    <div className="rounded-xl border border-slate-200 overflow-hidden dark:border-slate-800">
                                        <table className="w-full text-left text-xs">
                                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                                                <tr>
                                                    <th className="py-2.5 pl-4 pr-2">
                                                        Material / Part
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Required Qty
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Issued Qty
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Unit
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Unit Cost
                                                    </th>
                                                    <th className="py-2.5 pl-2 pr-4 text-right">
                                                        Line Cost
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {(detailWo.items || []).map(
                                                    (it) => (
                                                        <tr key={it.id}>
                                                            <td className="py-2 pl-4 pr-2">
                                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                                    {it.product
                                                                        ?.name ??
                                                                        '-'}
                                                                </div>
                                                                <div className="font-mono text-slate-400">
                                                                    {
                                                                        it
                                                                            .product
                                                                            ?.sku
                                                                    }
                                                                </div>
                                                            </td>
                                                            <td className="px-2 py-2 font-medium">
                                                                {Number(
                                                                    it.required_quantity,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-2 font-medium text-indigo-600">
                                                                {Number(
                                                                    it.issued_quantity,
                                                                )}
                                                            </td>
                                                            <td className="px-2 py-2 text-slate-500">
                                                                {it.unit}
                                                            </td>
                                                            <td className="px-2 py-2 font-mono">
                                                                {money(
                                                                    it.unit_cost,
                                                                )}
                                                            </td>
                                                            <td className="py-2 pl-2 pr-4 text-right font-mono font-bold">
                                                                {money(
                                                                    it.total_cost,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Additional Production Costs Log */}
                                {detailWo.production_costs &&
                                    detailWo.production_costs.length > 0 && (
                                        <div className="space-y-2">
                                            <h4 className="text-sm font-bold text-slate-900 dark:text-white">
                                                Absorbed Labor & Overhead Log
                                            </h4>
                                            <div className="rounded-xl border border-slate-200 overflow-hidden dark:border-slate-800">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/60">
                                                        <tr>
                                                            <th className="py-2 pl-4 pr-2">
                                                                Type
                                                            </th>
                                                            <th className="px-2 py-2">
                                                                Description
                                                            </th>
                                                            <th className="px-2 py-2">
                                                                Date
                                                            </th>
                                                            <th className="py-2 pl-2 pr-4 text-right">
                                                                Amount
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                        {detailWo.production_costs.map(
                                                            (pc) => (
                                                                <tr key={pc.id}>
                                                                    <td className="py-2 pl-4 pr-2 uppercase font-semibold text-slate-700 dark:text-slate-300">
                                                                        {
                                                                            pc.cost_type
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-2 text-slate-600 dark:text-slate-400">
                                                                        {
                                                                            pc.description
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-2 text-slate-500">
                                                                        {
                                                                            pc.cost_date
                                                                        }
                                                                    </td>
                                                                    <td className="py-2 pl-2 pr-4 text-right font-mono font-semibold">
                                                                        {money(
                                                                            pc.amount,
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
