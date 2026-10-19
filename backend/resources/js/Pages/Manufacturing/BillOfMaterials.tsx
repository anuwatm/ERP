import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/Types';
import { money } from '@/Utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import {
    IconActivity,
    IconBoxes,
    IconClose,
    IconCopy,
    IconEdit,
    IconFolderTree,
    IconLayers,
    IconPlus,
    IconSearch,
    IconSpinner,
    IconTrash,
    IconWrench,
} from './Icons';

export interface ProductSummary {
    id: string;
    sku: string;
    name: string;
    unit: string | null;
    cost: string;
    price: string;
}

export interface SubBomSummary {
    id: string;
    bom_no: string;
    name: string;
    version: string;
    product_id: string;
    total_cost: string;
    quantity: string;
    unit: string;
}

export interface BomItemData {
    id?: string;
    item_type: 'raw_material' | 'sub_assembly';
    product_id?: string | null;
    sub_bom_id?: string | null;
    product?: ProductSummary;
    quantity: number | string;
    unit: string;
    unit_cost: number | string;
    scrap_percentage: number | string;
    total_cost?: number | string;
    notes?: string | null;
}

export interface BomRecord {
    id: string;
    bom_no: string;
    name: string;
    product_id: string;
    product?: ProductSummary;
    version: string;
    quantity: string;
    unit: string;
    material_cost: string;
    labor_cost: string;
    overhead_cost: string;
    total_cost: string;
    unit_cost: string;
    routing_notes?: string | null;
    is_active: boolean;
    created_at: string;
    creator?: { id: string; name: string };
    items_count?: number;
    work_orders_count?: number;
    items?: BomItemData[];
}

export interface ExplosionNode {
    level: number;
    bom_id: string;
    bom_no: string;
    bom_name: string;
    product_id: string;
    product_name: string;
    product_sku: string;
    output_quantity: number;
    unit: string;
    unit_cost: number;
    total_cost: number;
    labor_cost: number;
    overhead_cost: number;
    items: Array<{
        type: 'raw_material' | 'sub_assembly';
        level: number;
        product_id: string;
        product_sku: string;
        product_name: string;
        quantity_per_unit: number;
        required_quantity: number;
        unit: string;
        unit_cost: number;
        scrap_percentage: number;
        total_cost: number;
        sub_bom_id?: string | null;
        sub_explosion?: ExplosionNode | null;
    }>;
}

export interface FlattenedMaterial {
    product_id: string;
    product_sku: string;
    product_name: string;
    total_quantity: number;
    unit: string;
    unit_cost: number;
    total_cost: number;
}

interface Props {
    boms: BomRecord[];
    products: ProductSummary[];
    availableSubBoms: SubBomSummary[];
    filters: {
        search?: string;
        is_active?: string;
        product_id?: string;
    };
}

export default function BillOfMaterialsIndex({
    boms,
    products,
    availableSubBoms,
    filters,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('manufacturing.bom.manage');

    // Filter states
    const [search, setSearch] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.is_active ?? '');

    // Builder modal state
    const [builderOpen, setBuilderOpen] = useState(false);
    const [editingBom, setEditingBom] = useState<BomRecord | null>(null);

    // Explosion modal state
    const [explosionOpen, setExplosionOpen] = useState(false);
    const [explosionLoading, setExplosionLoading] = useState(false);
    const [explosionData, setExplosionData] = useState<{
        bom: BomRecord;
        tree: ExplosionNode;
        flattened_materials: FlattenedMaterial[];
        total_calculated_cost: number;
    } | null>(null);

    // Form setup
    const form = useForm<{
        name: string;
        product_id: string;
        version: string;
        quantity: number;
        unit: string;
        labor_cost: number;
        overhead_cost: number;
        routing_notes: string;
        is_active: boolean;
        items: Array<{
            item_type: 'raw_material' | 'sub_assembly';
            product_id: string;
            sub_bom_id: string;
            quantity: number;
            unit: string;
            unit_cost: number;
            scrap_percentage: number;
            notes: string;
        }>;
    }>({
        name: '',
        product_id: '',
        version: '1.0',
        quantity: 1,
        unit: 'pcs',
        labor_cost: 0,
        overhead_cost: 0,
        routing_notes: '',
        is_active: true,
        items: [
            {
                item_type: 'raw_material',
                product_id: '',
                sub_bom_id: '',
                quantity: 1,
                unit: 'pcs',
                unit_cost: 0,
                scrap_percentage: 0,
                notes: '',
            },
        ],
    });

    const openCreateModal = () => {
        setEditingBom(null);
        form.reset();
        form.setData({
            name: '',
            product_id: products[0]?.id ?? '',
            version: '1.0',
            quantity: 1,
            unit: products[0]?.unit ?? 'pcs',
            labor_cost: 0,
            overhead_cost: 0,
            routing_notes: '',
            is_active: true,
            items: [
                {
                    item_type: 'raw_material',
                    product_id: '',
                    sub_bom_id: '',
                    quantity: 1,
                    unit: 'pcs',
                    unit_cost: 0,
                    scrap_percentage: 0,
                    notes: '',
                },
            ],
        });
        setBuilderOpen(true);
    };

    const openEditModal = (bom: BomRecord) => {
        setEditingBom(bom);
        form.reset();
        form.setData({
            name: bom.name,
            product_id: bom.product_id,
            version: bom.version,
            quantity: Number(bom.quantity) || 1,
            unit: bom.unit || 'pcs',
            labor_cost: Number(bom.labor_cost) || 0,
            overhead_cost: Number(bom.overhead_cost) || 0,
            routing_notes: bom.routing_notes ?? '',
            is_active: bom.is_active,
            items: (bom.items || []).map((it) => ({
                item_type: it.item_type,
                product_id: it.product_id ?? '',
                sub_bom_id: it.sub_bom_id ?? '',
                quantity: Number(it.quantity) || 1,
                unit: it.unit || 'pcs',
                unit_cost: Number(it.unit_cost) || 0,
                scrap_percentage: Number(it.scrap_percentage) || 0,
                notes: it.notes ?? '',
            })),
        });
        setBuilderOpen(true);
    };

    const addItemRow = () => {
        form.setData('items', [
            ...form.data.items,
            {
                item_type: 'raw_material',
                product_id: '',
                sub_bom_id: '',
                quantity: 1,
                unit: 'pcs',
                unit_cost: 0,
                scrap_percentage: 0,
                notes: '',
            },
        ]);
    };

    const removeItemRow = (index: number) => {
        if (form.data.items.length <= 1) return;
        const next = [...form.data.items];
        next.splice(index, 1);
        form.setData('items', next);
    };

    // Calculate dynamic cost estimates inside builder
    const estimatedCosts = useMemo(() => {
        let matCost = 0;
        form.data.items.forEach((item) => {
            const qty = Number(item.quantity) || 0;
            const unitCost = Number(item.unit_cost) || 0;
            const scrap = Number(item.scrap_percentage) || 0;
            const effectiveQty = qty * (1 + scrap / 100);
            matCost += effectiveQty * unitCost;
        });

        const labor = Number(form.data.labor_cost) || 0;
        const overhead = Number(form.data.overhead_cost) || 0;
        const total = matCost + labor + overhead;
        const batchQty = Number(form.data.quantity) || 1;
        const unitCost = batchQty > 0 ? total / batchQty : 0;

        return {
            materialCost: matCost,
            laborCost: labor,
            overheadCost: overhead,
            totalCost: total,
            unitCost,
        };
    }, [
        form.data.items,
        form.data.labor_cost,
        form.data.overhead_cost,
        form.data.quantity,
    ]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (editingBom) {
            form.put(route('manufacturing.boms.update', editingBom.id), {
                onSuccess: () => setBuilderOpen(false),
            });
        } else {
            form.post(route('manufacturing.boms.store'), {
                onSuccess: () => setBuilderOpen(false),
            });
        }
    };

    const handleDuplicate = (bom: BomRecord) => {
        if (!confirm(`Duplicate BOM "${bom.name}" (${bom.bom_no})?`)) return;
        router.post(route('manufacturing.boms.duplicate', bom.id));
    };

    const handleDelete = (bom: BomRecord) => {
        if (!confirm(`Are you sure you want to delete BOM "${bom.bom_no}"?`))
            return;
        router.delete(route('manufacturing.boms.destroy', bom.id));
    };

    const viewExplosion = async (bom: BomRecord) => {
        setExplosionOpen(true);
        setExplosionLoading(true);
        try {
            const res = await fetch(
                route('manufacturing.boms.explosion', bom.id),
            );
            if (!res.ok) throw new Error('Failed to load explosion tree');
            const data = await res.json();
            setExplosionData(data);
        } catch (err: unknown) {
            const message =
                err instanceof Error
                    ? err.message
                    : 'Error loading BOM explosion tree';
            alert(message);
            setExplosionOpen(false);
        } finally {
            setExplosionLoading(false);
        }
    };

    const handleFilterSubmit = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            route('manufacturing.boms.index'),
            {
                search: search || undefined,
                is_active: statusFilter !== '' ? statusFilter : undefined,
            },
            { preserveState: true },
        );
    };

    // Aggregate statistics
    const stats = useMemo(() => {
        const total = boms.length;
        const active = boms.filter((b) => b.is_active).length;
        const totalItems = boms.reduce(
            (acc, b) => acc + (b.items_count || 0),
            0,
        );
        const avgUnitCost =
            total > 0
                ? boms.reduce((acc, b) => acc + Number(b.unit_cost || 0), 0) /
                  total
                : 0;
        return { total, active, totalItems, avgUnitCost };
    }, [boms]);

    return (
        <AuthenticatedLayout>
            <Head title="Manufacturing - Bills of Materials" />

            <div className="space-y-6">
                <PageHeader
                    title="Bills of Materials (BOM)"
                    description="Multi-level production recipes, cost rollups, sub-assemblies, and explosion trees"
                    actions={
                        canManage && (
                            <button
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <IconPlus className="h-4 w-4" />
                                Create BOM
                            </button>
                        )
                    }
                />

                {/* Top Stat Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Recipes"
                        value={stats.total}
                        icon={<IconLayers className="h-5 w-5" />}
                        subtitle="Registered BOM definitions"
                    />
                    <StatCard
                        title="Active BOMs"
                        value={stats.active}
                        icon={<IconActivity className="h-5 w-5" />}
                        iconBgColor="bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-300"
                        subtitle="Available for work orders"
                    />
                    <StatCard
                        title="Total Ingredients"
                        value={stats.totalItems}
                        icon={<IconBoxes className="h-5 w-5" />}
                        iconBgColor="bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-300"
                        subtitle="Materials & sub-assemblies"
                    />
                    <StatCard
                        title="Average Unit Cost"
                        value={money(stats.avgUnitCost)}
                        icon={<IconWrench className="h-5 w-5" />}
                        iconBgColor="bg-blue-50 dark:bg-blue-950/70 text-blue-600 dark:text-blue-300"
                        subtitle="Standard batch unit cost"
                    />
                </div>

                {/* Filter and Search Bar */}
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
                                placeholder="Search by BOM #, recipe name, or finished product..."
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
                                <option value="">All Status</option>
                                <option value="1">Active Only</option>
                                <option value="0">Inactive Only</option>
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

                {/* BOMs Table */}
                <Card bodyClassName="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                            <thead className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                                <tr>
                                    <th className="py-3.5 pl-6 pr-3">
                                        BOM Code & Name
                                    </th>
                                    <th className="px-3 py-3.5">
                                        Finished Product
                                    </th>
                                    <th className="px-3 py-3.5">
                                        Batch Output
                                    </th>
                                    <th className="px-3 py-3.5">
                                        Material Cost
                                    </th>
                                    <th className="px-3 py-3.5">
                                        Direct Labor
                                    </th>
                                    <th className="px-3 py-3.5">Factory O/H</th>
                                    <th className="px-3 py-3.5">
                                        Total & Unit Cost
                                    </th>
                                    <th className="px-3 py-3.5">Status</th>
                                    <th className="py-3.5 pl-3 pr-6 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {boms.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="py-12 text-center text-slate-400"
                                        >
                                            <IconLayers className="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600 mb-2" />
                                            No Bills of Materials found. Click
                                            "Create BOM" to define a new
                                            production recipe.
                                        </td>
                                    </tr>
                                ) : (
                                    boms.map((bom) => (
                                        <tr
                                            key={bom.id}
                                            className="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                        >
                                            <td className="py-4 pl-6 pr-3 font-medium text-slate-900 dark:text-white">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded">
                                                        {bom.bom_no}
                                                    </span>
                                                    <span className="text-xs text-slate-400">
                                                        v{bom.version}
                                                    </span>
                                                </div>
                                                <div className="font-semibold mt-1 text-slate-800 dark:text-slate-200">
                                                    {bom.name}
                                                </div>
                                                <div className="text-xs text-slate-400 mt-0.5">
                                                    {bom.items_count || 0}{' '}
                                                    component
                                                    {(bom.items_count || 0) ===
                                                    1
                                                        ? ''
                                                        : 's'}
                                                </div>
                                            </td>

                                            <td className="px-3 py-4">
                                                <div className="font-medium text-slate-900 dark:text-slate-100">
                                                    {bom.product?.name ?? '-'}
                                                </div>
                                                <div className="font-mono text-xs text-slate-400">
                                                    {bom.product?.sku}
                                                </div>
                                            </td>

                                            <td className="px-3 py-4">
                                                <span className="font-semibold text-slate-900 dark:text-white">
                                                    {Number(bom.quantity)}
                                                </span>{' '}
                                                <span className="text-xs text-slate-400">
                                                    {bom.unit}
                                                </span>
                                            </td>

                                            <td className="px-3 py-4 font-mono">
                                                {money(bom.material_cost)}
                                            </td>

                                            <td className="px-3 py-4 font-mono">
                                                {money(bom.labor_cost)}
                                            </td>

                                            <td className="px-3 py-4 font-mono">
                                                {money(bom.overhead_cost)}
                                            </td>

                                            <td className="px-3 py-4">
                                                <div className="font-semibold font-mono text-slate-900 dark:text-white">
                                                    {money(bom.total_cost)}
                                                </div>
                                                <div className="text-xs text-indigo-600 dark:text-indigo-400 font-mono">
                                                    {money(bom.unit_cost)} /{' '}
                                                    {bom.unit}
                                                </div>
                                            </td>

                                            <td className="px-3 py-4">
                                                <Badge
                                                    variant={
                                                        bom.is_active
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {bom.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>

                                            <td className="py-4 pl-3 pr-6 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        onClick={() =>
                                                            viewExplosion(bom)
                                                        }
                                                        title="Multi-Level Tree Explosion"
                                                        className="rounded-lg p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-indigo-400"
                                                    >
                                                        <IconFolderTree className="h-4 w-4" />
                                                    </button>

                                                    {canManage && (
                                                        <>
                                                            <button
                                                                onClick={() =>
                                                                    handleDuplicate(
                                                                        bom,
                                                                    )
                                                                }
                                                                title="Duplicate Recipe"
                                                                className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                                            >
                                                                <IconCopy className="h-4 w-4" />
                                                            </button>

                                                            <button
                                                                onClick={() =>
                                                                    openEditModal(
                                                                        bom,
                                                                    )
                                                                }
                                                                title="Edit Recipe"
                                                                className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                                            >
                                                                <IconEdit className="h-4 w-4" />
                                                            </button>

                                                            <button
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        bom,
                                                                    )
                                                                }
                                                                title="Delete BOM"
                                                                className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:text-rose-400 dark:hover:bg-slate-800"
                                                            >
                                                                <IconTrash className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* BOM BUILDER MODAL */}
                {builderOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
                        <div className="relative w-full max-w-5xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 max-h-[92vh] flex flex-col overflow-hidden border border-slate-200 dark:border-slate-800">
                            {/* Modal Header */}
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <IconLayers className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                                        {editingBom
                                            ? `Edit BOM: ${editingBom.bom_no}`
                                            : 'New Bill of Materials (BOM Builder)'}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Define manufacturing recipes,
                                        sub-assemblies, direct labor, and
                                        overhead costing
                                    </p>
                                </div>
                                <button
                                    onClick={() => setBuilderOpen(false)}
                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-500 dark:hover:bg-slate-800"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Modal Body */}
                            <form
                                onSubmit={handleSubmit}
                                className="flex-1 overflow-y-auto p-6 space-y-6"
                            >
                                {/* Basic Info Section */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="sm:col-span-2">
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Recipe / BOM Name *
                                        </label>
                                        <input
                                            type="text"
                                            required
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. Ergonomic Office Chair Assembly"
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                        {form.errors.name && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {form.errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Finished Output Product *
                                        </label>
                                        <select
                                            required
                                            value={form.data.product_id}
                                            onChange={(e) => {
                                                const pid = e.target.value;
                                                const p = products.find(
                                                    (prod) => prod.id === pid,
                                                );
                                                form.setData({
                                                    ...form.data,
                                                    product_id: pid,
                                                    unit:
                                                        p?.unit ||
                                                        form.data.unit ||
                                                        'pcs',
                                                });
                                            }}
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        >
                                            <option value="">
                                                Select Target Product
                                            </option>
                                            {products.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.sku} - {p.name}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors.product_id && (
                                            <p className="mt-1 text-xs text-rose-500">
                                                {form.errors.product_id}
                                            </p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Version
                                        </label>
                                        <input
                                            type="text"
                                            value={form.data.version}
                                            onChange={(e) =>
                                                form.setData(
                                                    'version',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Batch Output Quantity *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            value={form.data.quantity}
                                            onChange={(e) =>
                                                form.setData(
                                                    'quantity',
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
                                            Batch Unit
                                        </label>
                                        <input
                                            type="text"
                                            value={form.data.unit}
                                            onChange={(e) =>
                                                form.setData(
                                                    'unit',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Direct Labor Cost (THB)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.labor_cost}
                                            onChange={(e) =>
                                                form.setData(
                                                    'labor_cost',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Factory Overhead Cost (THB)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.overhead_cost}
                                            onChange={(e) =>
                                                form.setData(
                                                    'overhead_cost',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>
                                </div>

                                {/* Dynamic Costing Summary Banner */}
                                <div className="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/20">
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-5 text-center">
                                        <div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Material Cost
                                            </div>
                                            <div className="font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {money(
                                                    estimatedCosts.materialCost,
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Labor Cost
                                            </div>
                                            <div className="font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {money(
                                                    estimatedCosts.laborCost,
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Overhead Cost
                                            </div>
                                            <div className="font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {money(
                                                    estimatedCosts.overheadCost,
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Total Batch Cost
                                            </div>
                                            <div className="font-mono text-base font-bold text-slate-900 dark:text-white">
                                                {money(
                                                    estimatedCosts.totalCost,
                                                )}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                                Unit Cost
                                            </div>
                                            <div className="font-mono text-base font-bold text-indigo-700 dark:text-indigo-300">
                                                {money(estimatedCosts.unitCost)}{' '}
                                                / {form.data.unit || 'unit'}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Bill of Materials Items / Ingredients */}
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <IconBoxes className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                            Bill of Materials Components
                                        </h4>
                                        <button
                                            type="button"
                                            onClick={addItemRow}
                                            className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        >
                                            <IconPlus className="h-3.5 w-3.5" />
                                            Add Ingredient
                                        </button>
                                    </div>

                                    <div className="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                                        <table className="w-full text-left text-xs">
                                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                                                <tr>
                                                    <th className="py-2.5 pl-4 pr-2">
                                                        Type
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Component / Sub-BOM
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Required Qty
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Unit
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Unit Cost (THB)
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Scrap %
                                                    </th>
                                                    <th className="px-2 py-2.5">
                                                        Est. Line Cost
                                                    </th>
                                                    <th className="py-2.5 pl-2 pr-4 text-right">
                                                        Action
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {form.data.items.map(
                                                    (item, idx) => {
                                                        const qty =
                                                            Number(
                                                                item.quantity,
                                                            ) || 0;
                                                        const unitCost =
                                                            Number(
                                                                item.unit_cost,
                                                            ) || 0;
                                                        const scrap =
                                                            Number(
                                                                item.scrap_percentage,
                                                            ) || 0;
                                                        const lineCost =
                                                            qty *
                                                            (1 + scrap / 100) *
                                                            unitCost;

                                                        return (
                                                            <tr
                                                                key={idx}
                                                                className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                                            >
                                                                <td className="py-2 pl-4 pr-2">
                                                                    <select
                                                                        value={
                                                                            item.item_type
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...form
                                                                                        .data
                                                                                        .items,
                                                                                ];
                                                                            next[
                                                                                idx
                                                                            ].item_type =
                                                                                e
                                                                                    .target
                                                                                    .value as
                                                                                    | 'raw_material'
                                                                                    | 'sub_assembly';
                                                                            if (
                                                                                next[
                                                                                    idx
                                                                                ]
                                                                                    .item_type ===
                                                                                'sub_assembly'
                                                                            ) {
                                                                                next[
                                                                                    idx
                                                                                ].product_id =
                                                                                    '';
                                                                            } else {
                                                                                next[
                                                                                    idx
                                                                                ].sub_bom_id =
                                                                                    '';
                                                                            }
                                                                            form.setData(
                                                                                'items',
                                                                                next,
                                                                            );
                                                                        }}
                                                                        className="rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                                                                    >
                                                                        <option value="raw_material">
                                                                            Raw
                                                                            Material
                                                                        </option>
                                                                        <option value="sub_assembly">
                                                                            Sub-Assembly
                                                                        </option>
                                                                    </select>
                                                                </td>

                                                                <td className="px-2 py-2">
                                                                    {item.item_type ===
                                                                    'raw_material' ? (
                                                                        <select
                                                                            required
                                                                            value={
                                                                                item.product_id
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) => {
                                                                                const pid =
                                                                                    e
                                                                                        .target
                                                                                        .value;
                                                                                const p =
                                                                                    products.find(
                                                                                        (
                                                                                            prod,
                                                                                        ) =>
                                                                                            prod.id ===
                                                                                            pid,
                                                                                    );
                                                                                const next =
                                                                                    [
                                                                                        ...form
                                                                                            .data
                                                                                            .items,
                                                                                    ];
                                                                                next[
                                                                                    idx
                                                                                ].product_id =
                                                                                    pid;
                                                                                if (
                                                                                    p
                                                                                ) {
                                                                                    next[
                                                                                        idx
                                                                                    ].unit =
                                                                                        p.unit ||
                                                                                        'pcs';
                                                                                    next[
                                                                                        idx
                                                                                    ].unit_cost =
                                                                                        Number(
                                                                                            p.cost,
                                                                                        ) ||
                                                                                        0;
                                                                                }
                                                                                form.setData(
                                                                                    'items',
                                                                                    next,
                                                                                );
                                                                            }}
                                                                            className="w-full rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                                                                        >
                                                                            <option value="">
                                                                                Select
                                                                                Raw
                                                                                Material
                                                                            </option>
                                                                            {products.map(
                                                                                (
                                                                                    p,
                                                                                ) => (
                                                                                    <option
                                                                                        key={
                                                                                            p.id
                                                                                        }
                                                                                        value={
                                                                                            p.id
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            p.sku
                                                                                        }{' '}
                                                                                        -{' '}
                                                                                        {
                                                                                            p.name
                                                                                        }
                                                                                    </option>
                                                                                ),
                                                                            )}
                                                                        </select>
                                                                    ) : (
                                                                        <select
                                                                            required
                                                                            value={
                                                                                item.sub_bom_id
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) => {
                                                                                const sId =
                                                                                    e
                                                                                        .target
                                                                                        .value;
                                                                                const sBom =
                                                                                    availableSubBoms.find(
                                                                                        (
                                                                                            sb,
                                                                                        ) =>
                                                                                            sb.id ===
                                                                                            sId,
                                                                                    );
                                                                                const next =
                                                                                    [
                                                                                        ...form
                                                                                            .data
                                                                                            .items,
                                                                                    ];
                                                                                next[
                                                                                    idx
                                                                                ].sub_bom_id =
                                                                                    sId;
                                                                                if (
                                                                                    sBom
                                                                                ) {
                                                                                    next[
                                                                                        idx
                                                                                    ].product_id =
                                                                                        sBom.product_id;
                                                                                    next[
                                                                                        idx
                                                                                    ].unit =
                                                                                        sBom.unit ||
                                                                                        'pcs';
                                                                                    const bQty =
                                                                                        Number(
                                                                                            sBom.quantity,
                                                                                        ) ||
                                                                                        1;
                                                                                    next[
                                                                                        idx
                                                                                    ].unit_cost =
                                                                                        Number(
                                                                                            sBom.total_cost,
                                                                                        ) /
                                                                                        bQty;
                                                                                }
                                                                                form.setData(
                                                                                    'items',
                                                                                    next,
                                                                                );
                                                                            }}
                                                                            className="w-full rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                                                                        >
                                                                            <option value="">
                                                                                Select
                                                                                Sub-Assembly
                                                                                BOM
                                                                            </option>
                                                                            {availableSubBoms
                                                                                .filter(
                                                                                    (
                                                                                        sb,
                                                                                    ) =>
                                                                                        !editingBom ||
                                                                                        sb.id !==
                                                                                            editingBom.id,
                                                                                )
                                                                                .map(
                                                                                    (
                                                                                        sb,
                                                                                    ) => (
                                                                                        <option
                                                                                            key={
                                                                                                sb.id
                                                                                            }
                                                                                            value={
                                                                                                sb.id
                                                                                            }
                                                                                        >
                                                                                            {
                                                                                                sb.bom_no
                                                                                            }{' '}
                                                                                            -{' '}
                                                                                            {
                                                                                                sb.name
                                                                                            }{' '}
                                                                                            (v
                                                                                            {
                                                                                                sb.version
                                                                                            }
                                                                                            )
                                                                                        </option>
                                                                                    ),
                                                                                )}
                                                                        </select>
                                                                    )}
                                                                </td>

                                                                <td className="px-2 py-2">
                                                                    <input
                                                                        type="number"
                                                                        step="0.0001"
                                                                        min="0.0001"
                                                                        required
                                                                        value={
                                                                            item.quantity
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...form
                                                                                        .data
                                                                                        .items,
                                                                                ];
                                                                            next[
                                                                                idx
                                                                            ].quantity =
                                                                                parseFloat(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                0;
                                                                            form.setData(
                                                                                'items',
                                                                                next,
                                                                            );
                                                                        }}
                                                                        className="w-20 rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                                                                    />
                                                                </td>

                                                                <td className="px-2 py-2">
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            item.unit
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...form
                                                                                        .data
                                                                                        .items,
                                                                                ];
                                                                            next[
                                                                                idx
                                                                            ].unit =
                                                                                e.target.value;
                                                                            form.setData(
                                                                                'items',
                                                                                next,
                                                                            );
                                                                        }}
                                                                        className="w-16 rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                                                                    />
                                                                </td>

                                                                <td className="px-2 py-2">
                                                                    <input
                                                                        type="number"
                                                                        step="0.01"
                                                                        min="0"
                                                                        value={
                                                                            item.unit_cost
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...form
                                                                                        .data
                                                                                        .items,
                                                                                ];
                                                                            next[
                                                                                idx
                                                                            ].unit_cost =
                                                                                parseFloat(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                0;
                                                                            form.setData(
                                                                                'items',
                                                                                next,
                                                                            );
                                                                        }}
                                                                        className="w-24 rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                                                                    />
                                                                </td>

                                                                <td className="px-2 py-2">
                                                                    <input
                                                                        type="number"
                                                                        step="0.1"
                                                                        min="0"
                                                                        max="100"
                                                                        value={
                                                                            item.scrap_percentage
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...form
                                                                                        .data
                                                                                        .items,
                                                                                ];
                                                                            next[
                                                                                idx
                                                                            ].scrap_percentage =
                                                                                parseFloat(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                0;
                                                                            form.setData(
                                                                                'items',
                                                                                next,
                                                                            );
                                                                        }}
                                                                        className="w-16 rounded border border-slate-300 bg-white py-1 px-2 text-xs dark:border-slate-700 dark:bg-slate-900"
                                                                    />
                                                                </td>

                                                                <td className="px-2 py-2 font-mono font-medium text-slate-800 dark:text-slate-200">
                                                                    {money(
                                                                        lineCost,
                                                                    )}
                                                                </td>

                                                                <td className="py-2 pl-2 pr-4 text-right">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            removeItemRow(
                                                                                idx,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            form
                                                                                .data
                                                                                .items
                                                                                .length <=
                                                                            1
                                                                        }
                                                                        className="text-rose-500 hover:text-rose-700 disabled:opacity-30"
                                                                    >
                                                                        <IconTrash className="h-4 w-4" />
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Routing Notes and Active Status */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 items-center">
                                    <div>
                                        <label className="block text-xs font-semibold uppercase text-slate-600 dark:text-slate-300 mb-1">
                                            Routing & Work Center Instructions
                                        </label>
                                        <textarea
                                            rows={2}
                                            value={form.data.routing_notes}
                                            onChange={(e) =>
                                                form.setData(
                                                    'routing_notes',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Assembly steps, workstation requirements, calibration notes..."
                                            className="w-full rounded-lg border border-slate-300 bg-white py-2 px-3 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                        />
                                    </div>

                                    <div className="flex items-center gap-3 pt-4">
                                        <label className="relative inline-flex cursor-pointer items-center">
                                            <input
                                                type="checkbox"
                                                checked={form.data.is_active}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'is_active',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="peer sr-only"
                                            />
                                            <div className="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:bg-slate-700 dark:after:border-slate-600"></div>
                                        </label>
                                        <div>
                                            <div className="text-sm font-semibold text-slate-900 dark:text-white">
                                                Active Recipe
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                Available for creating new work
                                                orders
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Modal Footer Buttons */}
                                <div className="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setBuilderOpen(false)}
                                        className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none disabled:opacity-50"
                                    >
                                        {form.processing
                                            ? 'Saving...'
                                            : editingBom
                                              ? 'Update BOM'
                                              : 'Create BOM Recipe'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* EXPLOSION & FLATTENING TREE MODAL */}
                {explosionOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
                        <div className="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 max-h-[90vh] flex flex-col overflow-hidden border border-slate-200 dark:border-slate-800">
                            <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <IconFolderTree className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                                        Multi-Level BOM Explosion & Flattened
                                        Materials
                                    </h3>
                                    {explosionData && (
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            {explosionData.bom.bom_no} -{' '}
                                            {explosionData.bom.name} (Output:{' '}
                                            {Number(explosionData.bom.quantity)}{' '}
                                            {explosionData.bom.unit})
                                        </p>
                                    )}
                                </div>
                                <button
                                    onClick={() => setExplosionOpen(false)}
                                    className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-500 dark:hover:bg-slate-800"
                                >
                                    <IconClose className="h-5 w-5" />
                                </button>
                            </div>

                            <div className="flex-1 overflow-y-auto p-6 space-y-6">
                                {explosionLoading ? (
                                    <div className="py-16 text-center text-slate-400">
                                        <IconSpinner className="mx-auto h-8 w-8 animate-spin text-indigo-500 mb-3" />
                                        Analyzing multi-level BOM explosion and
                                        checking for circular dependencies...
                                    </div>
                                ) : explosionData ? (
                                    <>
                                        {/* Total Calculated Cost Banner */}
                                        <div className="flex items-center justify-between rounded-xl bg-indigo-50 p-4 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900">
                                            <div>
                                                <div className="text-xs text-indigo-700 dark:text-indigo-300 font-semibold uppercase">
                                                    Fully Absorbed Production
                                                    Cost
                                                </div>
                                                <div className="text-xs text-slate-500 dark:text-slate-400">
                                                    Calculated across all nested
                                                    sub-assemblies and labor
                                                    lines
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="text-xl font-bold font-mono text-indigo-700 dark:text-indigo-300">
                                                    {money(
                                                        explosionData.total_calculated_cost,
                                                    )}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {money(
                                                        explosionData.total_calculated_cost /
                                                            (Number(
                                                                explosionData
                                                                    .bom
                                                                    .quantity,
                                                            ) || 1),
                                                    )}{' '}
                                                    / {explosionData.bom.unit}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Hierarchical Tree View */}
                                        <div className="space-y-3">
                                            <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <IconLayers className="h-4 w-4 text-indigo-600" />
                                                Multi-Level Assembly Tree
                                            </h4>
                                            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50 space-y-2">
                                                <ExplosionTreeBranch
                                                    node={explosionData.tree}
                                                />
                                            </div>
                                        </div>

                                        {/* Flattened Raw Materials Requirements */}
                                        <div className="space-y-3">
                                            <h4 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <IconBoxes className="h-4 w-4 text-indigo-600" />
                                                Consolidated Material Demand
                                                (Flattened MRP)
                                            </h4>
                                            <div className="rounded-xl border border-slate-200 overflow-hidden dark:border-slate-800">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                                                        <tr>
                                                            <th className="py-2.5 pl-4 pr-2">
                                                                Material SKU
                                                            </th>
                                                            <th className="px-2 py-2.5">
                                                                Name
                                                            </th>
                                                            <th className="px-2 py-2.5">
                                                                Total Required
                                                                Qty
                                                            </th>
                                                            <th className="px-2 py-2.5">
                                                                Unit
                                                            </th>
                                                            <th className="px-2 py-2.5">
                                                                Unit Cost
                                                            </th>
                                                            <th className="py-2.5 pl-2 pr-4 text-right">
                                                                Total Cost
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                        {explosionData.flattened_materials.map(
                                                            (m) => (
                                                                <tr
                                                                    key={
                                                                        m.product_id
                                                                    }
                                                                    className="hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                                                >
                                                                    <td className="py-2 pl-4 pr-2 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                                                        {
                                                                            m.product_sku
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-2 text-slate-800 dark:text-slate-200">
                                                                        {
                                                                            m.product_name
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-2 font-semibold text-slate-900 dark:text-white">
                                                                        {
                                                                            m.total_quantity
                                                                        }
                                                                    </td>
                                                                    <td className="px-2 py-2 text-slate-500">
                                                                        {m.unit}
                                                                    </td>
                                                                    <td className="px-2 py-2 font-mono">
                                                                        {money(
                                                                            m.unit_cost,
                                                                        )}
                                                                    </td>
                                                                    <td className="py-2 pl-2 pr-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                                                        {money(
                                                                            m.total_cost,
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </>
                                ) : null}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ExplosionTreeBranch({ node }: { node: ExplosionNode }) {
    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between rounded-lg bg-white p-2.5 shadow-sm border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center justify-center h-6 w-6 rounded bg-indigo-100 dark:bg-indigo-900/60 text-xs font-bold text-indigo-700 dark:text-indigo-300">
                        L{node.level}
                    </span>
                    <span className="font-semibold text-slate-900 dark:text-white text-xs">
                        {node.bom_name} ({node.bom_no})
                    </span>
                    <span className="text-xs text-slate-400 font-mono">
                        [{node.product_sku}]
                    </span>
                </div>
                <div className="flex items-center gap-4 text-xs font-mono">
                    <span className="text-slate-500">
                        Qty: {node.output_quantity} {node.unit}
                    </span>
                    <span className="font-bold text-slate-900 dark:text-white">
                        {money(node.total_cost)}
                    </span>
                </div>
            </div>

            {node.items && node.items.length > 0 && (
                <div className="pl-6 border-l-2 border-slate-200 dark:border-slate-700 ml-3 space-y-1.5 mt-1.5">
                    {node.items.map((item, idx) => (
                        <div key={idx} className="space-y-1.5">
                            <div className="flex items-center justify-between rounded bg-slate-50/80 p-2 text-xs dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                                <div className="flex items-center gap-2">
                                    <span
                                        className={`px-1.5 py-0.5 rounded text-[10px] font-semibold ${
                                            item.type === 'sub_assembly'
                                                ? 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300'
                                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                        }`}
                                    >
                                        {item.type === 'sub_assembly'
                                            ? 'Sub-Assembly'
                                            : 'Material'}
                                    </span>
                                    <span className="font-medium text-slate-800 dark:text-slate-200">
                                        {item.product_name}
                                    </span>
                                    <span className="font-mono text-slate-400">
                                        [{item.product_sku}]
                                    </span>
                                </div>
                                <div className="flex items-center gap-3 font-mono">
                                    <span className="text-slate-500">
                                        {item.required_quantity} {item.unit}
                                        {item.scrap_percentage > 0 &&
                                            ` (+${item.scrap_percentage}% scrap)`}
                                    </span>
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {money(item.total_cost)}
                                    </span>
                                </div>
                            </div>

                            {item.sub_explosion && (
                                <ExplosionTreeBranch
                                    node={item.sub_explosion}
                                />
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
