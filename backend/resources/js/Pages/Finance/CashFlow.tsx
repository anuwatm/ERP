import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type ForecastScenario = {
    id: string;
    scenario_key: string;
    name: string;
    description?: string;
    ar_collection_rate: number;
    deal_multiplier: number;
    ar_delay_days: number;
    expense_buffer_percent: number;
    payroll_buffer_percent: number;
    is_default: boolean;
};

type SnapshotBreakdown = {
    inflow_items: Array<{
        type: string;
        title: string;
        original_amount: number;
        adjusted_amount: number;
        date: string;
        weight_percent?: number;
    }>;
    outflow_items: Array<{
        type: string;
        title: string;
        original_amount: number;
        adjusted_amount: number;
        date: string;
        buffer_percent?: number;
    }>;
    daily_series: Array<{
        day: number;
        date: string;
        inflow: number;
        outflow: number;
        net: number;
        balance: number;
    }>;
};

type ForecastSnapshot = {
    id: string;
    scenario_key: string;
    as_of_date: string;
    horizon_days: number;
    starting_cash: number;
    projected_inflow: number;
    projected_outflow: number;
    net_cash_flow: number;
    projected_ending_cash: number;
    lowest_cash_point: number;
    lowest_cash_date: string;
    breakdown_json: SnapshotBreakdown;
    scenario?: ForecastScenario;
};

type ScenarioCurve = {
    name: string;
    key: string;
    ending_cash: number;
    lowest_cash_point: number;
    lowest_cash_date: string | null;
    points: Array<{
        date: string;
        balance: number;
        net: number;
    }>;
};

type FinancialHealthMetrics = {
    id: string;
    as_of_date: string;
    current_ratio: number;
    quick_ratio: number;
    dso_days: number;
    dpo_days: number;
    working_capital: number;
    working_capital_runway_months: number;
    monthly_burn_rate: number;
    current_cash: number;
    current_ar: number;
    current_ap: number;
    inventory_value: number;
};

type ComparisonData = {
    snapshots: Record<string, ForecastSnapshot>;
    curves: Record<string, ScenarioCurve>;
    health_metrics: FinancialHealthMetrics;
    scenarios: ForecastScenario[];
};

type Props = {
    comparison: ComparisonData;
    financialHealth: FinancialHealthMetrics;
    scenarios: ForecastScenario[];
    recentSnapshots: ForecastSnapshot[];
    filters: {
        horizon: number;
        as_of_date: string;
    };
    canManage: boolean;
};

const formatCurrency = (amount: number, currency = 'THB') =>
    new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);

const formatNumber = (val: number, decimals = 1) =>
    new Intl.NumberFormat('th-TH', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(val);

export default function CashFlow({
    comparison,
    financialHealth,
    scenarios,
    recentSnapshots,
    filters,
    canManage,
}: Props) {
    const [horizon, setHorizon] = useState<number>(filters.horizon || 90);
    const [activeTab, setActiveTab] = useState<
        'chart' | 'inflow' | 'outflow' | 'snapshots'
    >('chart');
    const [selectedScenarioForEdit, setSelectedScenarioForEdit] =
        useState<ForecastScenario | null>(null);
    const [editForm, setEditForm] = useState<{
        name: string;
        ar_collection_rate: number;
        deal_multiplier: number;
        ar_delay_days: number;
        expense_buffer_percent: number;
        payroll_buffer_percent: number;
    }>({
        name: '',
        ar_collection_rate: 100,
        deal_multiplier: 100,
        ar_delay_days: 0,
        expense_buffer_percent: 0,
        payroll_buffer_percent: 0,
    });
    const [isSavingSnapshot, setIsSavingSnapshot] = useState(false);
    const [isSavingScenario, setIsSavingScenario] = useState(false);

    const changeHorizon = (days: number) => {
        setHorizon(days);
        router.get(
            route('finance.cash-flow.index'),
            { horizon: days, as_of_date: filters.as_of_date },
            { preserveState: true, replace: true },
        );
    };

    const handleCreateSnapshot = () => {
        if (isSavingSnapshot) return;
        setIsSavingSnapshot(true);
        router.post(
            route('finance.cash-flow.snapshot'),
            { horizon, as_of_date: filters.as_of_date },
            {
                onFinish: () => setIsSavingSnapshot(false),
            },
        );
    };

    const handleOpenEditScenario = (sc: ForecastScenario) => {
        setSelectedScenarioForEdit(sc);
        setEditForm({
            name: sc.name,
            ar_collection_rate: Number(sc.ar_collection_rate),
            deal_multiplier: Number(sc.deal_multiplier),
            ar_delay_days: Number(sc.ar_delay_days),
            expense_buffer_percent: Number(sc.expense_buffer_percent),
            payroll_buffer_percent: Number(sc.payroll_buffer_percent),
        });
    };

    const handleSaveScenario = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedScenarioForEdit || isSavingScenario) return;
        setIsSavingScenario(true);
        router.patch(
            route(
                'finance.cash-flow.scenarios.update',
                selectedScenarioForEdit.id,
            ),
            editForm,
            {
                onSuccess: () => setSelectedScenarioForEdit(null),
                onFinish: () => setIsSavingScenario(false),
            },
        );
    };

    // Calculate chart dimensions and coordinates
    const chartData = useMemo(() => {
        const curves = comparison.curves || {};
        const basePoints = curves.base?.points || [];
        const bestPoints = curves.best?.points || [];
        const worstPoints = curves.worst?.points || [];

        const allValues = [
            ...basePoints.map((p) => p.balance),
            ...bestPoints.map((p) => p.balance),
            ...worstPoints.map((p) => p.balance),
            0,
        ];

        const minVal = Math.min(...allValues);
        const maxVal = Math.max(...allValues, 10000);
        const range = maxVal - minVal || 1;
        const totalPoints = Math.max(basePoints.length, 1);

        const svgWidth = 800;
        const svgHeight = 280;
        const padding = { top: 20, right: 30, bottom: 35, left: 70 };
        const innerWidth = svgWidth - padding.left - padding.right;
        const innerHeight = svgHeight - padding.top - padding.bottom;

        const getY = (val: number) =>
            padding.top + innerHeight - ((val - minVal) / range) * innerHeight;
        const getX = (idx: number) =>
            padding.left + (idx / (totalPoints - 1 || 1)) * innerWidth;

        const buildPath = (points: Array<{ balance: number }>) => {
            if (!points.length) return '';
            return points
                .map(
                    (p, i) =>
                        `${i === 0 ? 'M' : 'L'} ${getX(i).toFixed(1)} ${getY(p.balance).toFixed(1)}`,
                )
                .join(' ');
        };

        const zeroY = getY(0);

        return {
            svgWidth,
            svgHeight,
            padding,
            innerWidth,
            innerHeight,
            minVal,
            maxVal,
            zeroY,
            totalPoints,
            basePath: buildPath(basePoints),
            bestPath: buildPath(bestPoints),
            worstPath: buildPath(worstPoints),
            basePoints,
            bestPoints,
            worstPoints,
            getX,
            getY,
        };
    }, [comparison.curves]);

    const runwayMonths = financialHealth?.working_capital_runway_months ?? 0;
    const runwayBadgeColor =
        runwayMonths >= 6
            ? 'success'
            : runwayMonths >= 3
              ? 'warning'
              : 'danger';

    const currentRatio = financialHealth?.current_ratio ?? 0;
    const quickRatio = financialHealth?.quick_ratio ?? 0;

    const baseSnapshot = comparison.snapshots?.base;
    const bestSnapshot = comparison.snapshots?.best;
    const worstSnapshot = comparison.snapshots?.worst;

    return (
        <AuthenticatedLayout>
            <Head title="Cash Flow Forecasting - การพยากรณ์กระแสเงินสด" />

            <div className="space-y-6 pb-12">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <PageHeader
                        title="Cash Flow Forecasting & Financial Health"
                        description="พยากรณ์กระแสเงินสดไปข้างหน้า 30-180 วัน ด้วย 3 สถานการณ์ (Base, Best, Worst) และวิเคราะห์อัตราส่วนสภาพคล่อง"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Horizon Selector */}
                        <div className="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm font-medium shadow-sm dark:border-slate-700 dark:bg-slate-800">
                            {[30, 60, 90, 180].map((d) => (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() => changeHorizon(d)}
                                    className={`rounded-md px-3 py-1.5 transition-colors ${
                                        horizon === d
                                            ? 'bg-indigo-600 text-white shadow-xs'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
                                    }`}
                                >
                                    {d} วัน
                                </button>
                            ))}
                        </div>

                        {canManage && (
                            <button
                                type="button"
                                onClick={handleCreateSnapshot}
                                disabled={isSavingSnapshot}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                            >
                                <svg
                                    className={`h-4 w-4 ${isSavingSnapshot ? 'animate-spin' : ''}`}
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>
                                {isSavingSnapshot
                                    ? 'กำลังบันทึก...'
                                    : 'บันทึก Snapshot'}
                            </button>
                        )}
                    </div>
                </div>

                {/* KPI Row: Financial Health & Runway */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <StatCard
                        title="เงินสดตั้งต้น (Cash)"
                        value={formatCurrency(
                            financialHealth?.current_cash ?? 0,
                        )}
                        subtitle="ยอดรวมบัญชีธนาคารทั้งหมด"
                    />
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Cash Runway (เดือน)
                        </div>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                {formatNumber(runwayMonths, 1)}
                            </span>
                            <Badge variant={runwayBadgeColor}>
                                {runwayMonths >= 6
                                    ? 'ปลอดภัย'
                                    : runwayMonths >= 3
                                      ? 'เฝ้าระวัง'
                                      : 'วิกฤต'}
                            </Badge>
                        </div>
                        <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Burn rate:{' '}
                            {formatCurrency(
                                financialHealth?.monthly_burn_rate ?? 0,
                            )}
                            /ด.
                        </div>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Current Ratio
                        </div>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                {formatNumber(currentRatio, 2)}x
                            </span>
                            <Badge
                                variant={
                                    currentRatio >= 1.5
                                        ? 'success'
                                        : currentRatio >= 1.0
                                          ? 'warning'
                                          : 'danger'
                                }
                            >
                                {currentRatio >= 1.5
                                    ? 'แข็งแกร่ง'
                                    : 'ต่ำกว่าเกณฑ์'}
                            </Badge>
                        </div>
                        <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            เกณฑ์สุขภาพ &ge; 1.5x
                        </div>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                            Quick Ratio
                        </div>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                {formatNumber(quickRatio, 2)}x
                            </span>
                            <Badge
                                variant={
                                    quickRatio >= 1.0 ? 'success' : 'warning'
                                }
                            >
                                {quickRatio >= 1.0 ? 'คล่องตัว' : 'ตึงตัว'}
                            </Badge>
                        </div>
                        <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            (Cash + AR) / AP &ge; 1.0x
                        </div>
                    </div>
                    <StatCard
                        title="DSO (ระยะเวลาเก็บหนี้)"
                        value={`${formatNumber(financialHealth?.dso_days ?? 0, 0)} วัน`}
                        subtitle="เป้าหมาย &le; 45 วัน"
                    />
                    <StatCard
                        title="DPO (ระยะเวลาจ่ายหนี้)"
                        value={`${formatNumber(financialHealth?.dpo_days ?? 0, 0)} วัน`}
                        subtitle="ระยะเครดิตการค้าเฉลี่ย"
                    />
                </div>

                {/* Multi-Scenario Summary Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    {/* Worst Scenario */}
                    <div className="relative overflow-hidden rounded-xl border border-rose-200 bg-gradient-to-b from-rose-50/50 to-white p-5 shadow-xs dark:border-rose-900/50 dark:from-rose-950/20 dark:to-slate-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-rose-500" />
                                <h3 className="font-semibold text-slate-900 dark:text-white">
                                    {worstSnapshot?.scenario?.name ??
                                        'Worst Case (แย่สุด)'}
                                </h3>
                            </div>
                            <span className="text-xs font-medium text-rose-600 dark:text-rose-400">
                                Haircut & Buffers
                            </span>
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    เงินสดสิ้นสุดคาดการณ์:
                                </span>
                                <span
                                    className={`font-semibold ${(worstSnapshot?.projected_ending_cash ?? 0) < 0 ? 'text-rose-600' : 'text-slate-900 dark:text-white'}`}
                                >
                                    {formatCurrency(
                                        worstSnapshot?.projected_ending_cash ??
                                            0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    จุดต่ำสุดของเงินสด:
                                </span>
                                <span className="font-medium text-rose-600">
                                    {formatCurrency(
                                        worstSnapshot?.lowest_cash_point ?? 0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between text-xs text-slate-400">
                                <span>วันที่เงินสดต่ำสุด:</span>
                                <span>
                                    {worstSnapshot?.lowest_cash_date || '-'}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-500 dark:border-slate-700">
                                <span>
                                    In:{' '}
                                    {formatCurrency(
                                        worstSnapshot?.projected_inflow ?? 0,
                                    )}
                                </span>
                                <span>
                                    Out:{' '}
                                    {formatCurrency(
                                        worstSnapshot?.projected_outflow ?? 0,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Base Scenario */}
                    <div className="relative overflow-hidden rounded-xl border-2 border-indigo-500/80 bg-gradient-to-b from-indigo-50/50 to-white p-5 shadow-md dark:from-indigo-950/20 dark:to-slate-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-indigo-600" />
                                <h3 className="font-semibold text-slate-900 dark:text-white">
                                    {baseSnapshot?.scenario?.name ??
                                        'Base Case (ฐานคาดการณ์)'}
                                </h3>
                            </div>
                            <Badge variant="info">หลัก</Badge>
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    เงินสดสิ้นสุดคาดการณ์:
                                </span>
                                <span className="text-base font-bold text-indigo-600 dark:text-indigo-400">
                                    {formatCurrency(
                                        baseSnapshot?.projected_ending_cash ??
                                            0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    จุดต่ำสุดของเงินสด:
                                </span>
                                <span
                                    className={`font-semibold ${(baseSnapshot?.lowest_cash_point ?? 0) < 0 ? 'text-rose-600' : 'text-slate-700 dark:text-slate-300'}`}
                                >
                                    {formatCurrency(
                                        baseSnapshot?.lowest_cash_point ?? 0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between text-xs text-slate-400">
                                <span>วันที่เงินสดต่ำสุด:</span>
                                <span>
                                    {baseSnapshot?.lowest_cash_date || '-'}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-500 dark:border-slate-700">
                                <span>
                                    In:{' '}
                                    {formatCurrency(
                                        baseSnapshot?.projected_inflow ?? 0,
                                    )}
                                </span>
                                <span>
                                    Out:{' '}
                                    {formatCurrency(
                                        baseSnapshot?.projected_outflow ?? 0,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Best Scenario */}
                    <div className="relative overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-b from-emerald-50/50 to-white p-5 shadow-xs dark:border-emerald-900/50 dark:from-emerald-950/20 dark:to-slate-800">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full bg-emerald-500" />
                                <h3 className="font-semibold text-slate-900 dark:text-white">
                                    {bestSnapshot?.scenario?.name ??
                                        'Best Case (กรณีดีเยี่ยม)'}
                                </h3>
                            </div>
                            <span className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                Upside & Deals
                            </span>
                        </div>
                        <div className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    เงินสดสิ้นสุดคาดการณ์:
                                </span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {formatCurrency(
                                        bestSnapshot?.projected_ending_cash ??
                                            0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    จุดต่ำสุดของเงินสด:
                                </span>
                                <span className="font-medium text-slate-700 dark:text-slate-300">
                                    {formatCurrency(
                                        bestSnapshot?.lowest_cash_point ?? 0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between text-xs text-slate-400">
                                <span>วันที่เงินสดต่ำสุด:</span>
                                <span>
                                    {bestSnapshot?.lowest_cash_date || '-'}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-500 dark:border-slate-700">
                                <span>
                                    In:{' '}
                                    {formatCurrency(
                                        bestSnapshot?.projected_inflow ?? 0,
                                    )}
                                </span>
                                <span>
                                    Out:{' '}
                                    {formatCurrency(
                                        bestSnapshot?.projected_outflow ?? 0,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tab Navigation & Scenario Tuning Trigger */}
                <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-700">
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setActiveTab('chart')}
                            className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'chart'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                            }`}
                        >
                            กราฟเส้นทางการเงิน (Projection Curve)
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('inflow')}
                            className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'inflow'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                            }`}
                        >
                            รายการเงินเข้า (Inflows:{' '}
                            {baseSnapshot?.breakdown_json?.inflow_items
                                ?.length ?? 0}
                            )
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('outflow')}
                            className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'outflow'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                            }`}
                        >
                            รายการเงินออก (Outflows:{' '}
                            {baseSnapshot?.breakdown_json?.outflow_items
                                ?.length ?? 0}
                            )
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('snapshots')}
                            className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'snapshots'
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                            }`}
                        >
                            ประวัติ Snapshot ({recentSnapshots?.length ?? 0})
                        </button>
                    </div>

                    {canManage && (
                        <div className="flex items-center gap-2">
                            <span className="text-xs text-slate-500">
                                ปรับพารามิเตอร์แบบจำลอง:
                            </span>
                            {scenarios.map((sc) => (
                                <button
                                    key={sc.id}
                                    type="button"
                                    onClick={() => handleOpenEditScenario(sc)}
                                    className="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    <svg
                                        className="h-3.5 w-3.5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                                        />
                                    </svg>
                                    {sc.name}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Tab Content: Interactive Trajectory SVG Chart */}
                {activeTab === 'chart' && (
                    <Card
                        title={`เส้นทางการเปลี่ยนแปลงเงินสดล่วงหน้า ${horizon} วัน (Cash Trajectory)`}
                        description="เปรียบเทียบวิถีเงินสดของ 3 แบบจำลอง: ฟ้า = Base Case, เขียว = Best Case, แดง = Worst Case"
                    >
                        <div className="mt-4">
                            {/* Legend */}
                            <div className="mb-4 flex flex-wrap items-center justify-end gap-6 text-xs">
                                <div className="flex items-center gap-2">
                                    <div className="h-3 w-6 rounded-sm bg-emerald-500" />
                                    <span className="text-slate-700 dark:text-slate-300 font-medium">
                                        Best Case (กรณีดี)
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-3 w-6 rounded-sm bg-indigo-600" />
                                    <span className="text-slate-700 dark:text-slate-300 font-medium">
                                        Base Case (กรณีฐาน)
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-3 w-6 rounded-sm bg-rose-500" />
                                    <span className="text-slate-700 dark:text-slate-300 font-medium">
                                        Worst Case (กรณีแย่)
                                    </span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="h-0.5 w-6 border-b-2 border-dashed border-red-400" />
                                    <span className="text-rose-500 font-medium">
                                        จุดเงินสดหมด (0 บาท)
                                    </span>
                                </div>
                            </div>

                            {/* SVG Container */}
                            <div className="w-full overflow-x-auto rounded-lg bg-slate-50/50 p-2 dark:bg-slate-900/30">
                                <svg
                                    viewBox={`0 0 ${chartData.svgWidth} ${chartData.svgHeight}`}
                                    className="h-auto w-full min-w-[700px] overflow-visible"
                                >
                                    {/* Grid Lines */}
                                    <line
                                        x1={chartData.padding.left}
                                        y1={chartData.padding.top}
                                        x2={
                                            chartData.svgWidth -
                                            chartData.padding.right
                                        }
                                        y2={chartData.padding.top}
                                        stroke="#cbd5e1"
                                        strokeDasharray="3 3"
                                        strokeOpacity="0.6"
                                    />
                                    <line
                                        x1={chartData.padding.left}
                                        y1={
                                            chartData.padding.top +
                                            chartData.innerHeight / 2
                                        }
                                        x2={
                                            chartData.svgWidth -
                                            chartData.padding.right
                                        }
                                        y2={
                                            chartData.padding.top +
                                            chartData.innerHeight / 2
                                        }
                                        stroke="#cbd5e1"
                                        strokeDasharray="3 3"
                                        strokeOpacity="0.6"
                                    />
                                    <line
                                        x1={chartData.padding.left}
                                        y1={
                                            chartData.padding.top +
                                            chartData.innerHeight
                                        }
                                        x2={
                                            chartData.svgWidth -
                                            chartData.padding.right
                                        }
                                        y2={
                                            chartData.padding.top +
                                            chartData.innerHeight
                                        }
                                        stroke="#cbd5e1"
                                        strokeOpacity="0.8"
                                    />

                                    {/* Zero balance line */}
                                    {chartData.minVal < 0 && (
                                        <line
                                            x1={chartData.padding.left}
                                            y1={chartData.zeroY}
                                            x2={
                                                chartData.svgWidth -
                                                chartData.padding.right
                                            }
                                            y2={chartData.zeroY}
                                            stroke="#f43f5e"
                                            strokeWidth="1.5"
                                            strokeDasharray="4 4"
                                        />
                                    )}

                                    {/* Worst Curve */}
                                    {chartData.worstPath && (
                                        <path
                                            d={chartData.worstPath}
                                            fill="none"
                                            stroke="#f43f5e"
                                            strokeWidth="2.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    )}

                                    {/* Best Curve */}
                                    {chartData.bestPath && (
                                        <path
                                            d={chartData.bestPath}
                                            fill="none"
                                            stroke="#10b981"
                                            strokeWidth="2.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    )}

                                    {/* Base Curve (Prominent) */}
                                    {chartData.basePath && (
                                        <path
                                            d={chartData.basePath}
                                            fill="none"
                                            stroke="#4f46e5"
                                            strokeWidth="3.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    )}

                                    {/* Y-Axis Labels */}
                                    <text
                                        x={chartData.padding.left - 10}
                                        y={chartData.padding.top + 5}
                                        textAnchor="end"
                                        className="fill-slate-400 text-[11px]"
                                    >
                                        {formatCurrency(chartData.maxVal)}
                                    </text>
                                    <text
                                        x={chartData.padding.left - 10}
                                        y={
                                            chartData.padding.top +
                                            chartData.innerHeight / 2 +
                                            4
                                        }
                                        textAnchor="end"
                                        className="fill-slate-400 text-[11px]"
                                    >
                                        {formatCurrency(
                                            (chartData.maxVal +
                                                chartData.minVal) /
                                                2,
                                        )}
                                    </text>
                                    <text
                                        x={chartData.padding.left - 10}
                                        y={
                                            chartData.padding.top +
                                            chartData.innerHeight +
                                            4
                                        }
                                        textAnchor="end"
                                        className="fill-slate-400 text-[11px]"
                                    >
                                        {formatCurrency(chartData.minVal)}
                                    </text>

                                    {/* X-Axis Date Marks (Start, Mid, End) */}
                                    {chartData.basePoints.length > 0 && (
                                        <>
                                            <text
                                                x={chartData.padding.left}
                                                y={chartData.svgHeight - 10}
                                                textAnchor="start"
                                                className="fill-slate-400 text-[11px]"
                                            >
                                                {chartData.basePoints[0]?.date}
                                            </text>
                                            <text
                                                x={
                                                    chartData.padding.left +
                                                    chartData.innerWidth / 2
                                                }
                                                y={chartData.svgHeight - 10}
                                                textAnchor="middle"
                                                className="fill-slate-400 text-[11px]"
                                            >
                                                {
                                                    chartData.basePoints[
                                                        Math.floor(
                                                            chartData.basePoints
                                                                .length / 2,
                                                        )
                                                    ]?.date
                                                }
                                            </text>
                                            <text
                                                x={
                                                    chartData.svgWidth -
                                                    chartData.padding.right
                                                }
                                                y={chartData.svgHeight - 10}
                                                textAnchor="end"
                                                className="fill-slate-400 text-[11px]"
                                            >
                                                {
                                                    chartData.basePoints[
                                                        chartData.basePoints
                                                            .length - 1
                                                    ]?.date
                                                }
                                            </text>
                                        </>
                                    )}
                                </svg>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Tab Content: Inflows Breakdown */}
                {activeTab === 'inflow' && (
                    <Card
                        title="รายการเงินเข้าคาดการณ์ (Projected Inflow Items - Base Case)"
                        description="ประกอบด้วยลูกหนี้การค้า (Invoices Due) และดีลฝ่ายขายที่มีโอกาสปิดการขาย (Sales Pipeline Deals)"
                    >
                        <DataTable
                            data={
                                baseSnapshot?.breakdown_json?.inflow_items ?? []
                            }
                            keyExtractor={(item, i) =>
                                `${item.type}-${item.date}-${i}`
                            }
                            columns={[
                                {
                                    header: 'ประเภท',
                                    accessor: (item) => (
                                        <Badge
                                            variant={
                                                item.type === 'ar_invoice'
                                                    ? 'info'
                                                    : 'success'
                                            }
                                        >
                                            {item.type === 'ar_invoice'
                                                ? 'AR Invoice'
                                                : 'Deal Pipeline'}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'รายการ / อ้างอิง',
                                    accessor: (item) => item.title,
                                },
                                {
                                    header: 'วันครบกำหนด/คาดว่าจะปิด',
                                    accessor: (item) => item.date,
                                },
                                {
                                    header: 'น้ำหนัก / ความน่าจะเป็น',
                                    accessor: (item) =>
                                        item.weight_percent
                                            ? `${item.weight_percent}%`
                                            : '100%',
                                },
                                {
                                    header: 'ยอดเดิม',
                                    accessor: (item) =>
                                        formatCurrency(item.original_amount),
                                },
                                {
                                    header: 'ยอดปรับปรุงตามโมเดล',
                                    accessor: (item) => (
                                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {formatCurrency(
                                                item.adjusted_amount,
                                            )}
                                        </span>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                )}

                {/* Tab Content: Outflows Breakdown */}
                {activeTab === 'outflow' && (
                    <Card
                        title="รายการเงินออกคาดการณ์ (Projected Outflow Items - Base Case)"
                        description="ประกอบด้วยเจ้าหนี้ค่าใช้จ่าย (AP Expenses), ใบสั่งซื้อที่รอจ่าย (Open POs) และเงินเดือนประจำงวด (Recurring Payroll วันที่ 25)"
                    >
                        <DataTable
                            data={
                                baseSnapshot?.breakdown_json?.outflow_items ??
                                []
                            }
                            keyExtractor={(item, i) =>
                                `${item.type}-${item.date}-${i}`
                            }
                            columns={[
                                {
                                    header: 'ประเภท',
                                    accessor: (item) => (
                                        <Badge
                                            variant={
                                                item.type === 'payroll'
                                                    ? 'warning'
                                                    : item.type ===
                                                        'purchase_order'
                                                      ? 'info'
                                                      : 'danger'
                                            }
                                        >
                                            {item.type === 'payroll'
                                                ? 'เงินเดือนประจำ (Payroll)'
                                                : item.type === 'purchase_order'
                                                  ? 'ใบสั่งซื้อ (PO)'
                                                  : 'ค่าใช้จ่ายค้างจ่าย (AP Expense)'}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'รายการ / อ้างอิง',
                                    accessor: (item) => item.title,
                                },
                                {
                                    header: 'วันที่จ่ายคาดการณ์',
                                    accessor: (item) => item.date,
                                },
                                {
                                    header: 'ยอดตั้งต้น',
                                    accessor: (item) =>
                                        formatCurrency(item.original_amount),
                                },
                                {
                                    header: 'ยอดรวม Buffer',
                                    accessor: (item) => (
                                        <span className="font-semibold text-rose-600 dark:text-rose-400">
                                            {formatCurrency(
                                                item.adjusted_amount,
                                            )}
                                        </span>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                )}

                {/* Tab Content: Snapshot History */}
                {activeTab === 'snapshots' && (
                    <Card
                        title="ประวัติการบันทึก Snapshot ทางการเงิน"
                        description="บันทึกรูปตัดสถานะสภาพคล่องและผลการพยากรณ์ที่สร้างไว้ก่อนหน้า"
                    >
                        <DataTable
                            data={recentSnapshots ?? []}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'วันที่บันทึก (As of Date)',
                                    accessor: (item) => item.as_of_date,
                                },
                                {
                                    header: 'แบบจำลอง (Scenario)',
                                    accessor: (item) => (
                                        <Badge
                                            variant={
                                                item.scenario_key === 'base'
                                                    ? 'info'
                                                    : item.scenario_key ===
                                                        'best'
                                                      ? 'success'
                                                      : 'danger'
                                            }
                                        >
                                            {item.scenario?.name ??
                                                item.scenario_key}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'กรอบเวลา',
                                    accessor: (item) =>
                                        `${item.horizon_days} วัน`,
                                },
                                {
                                    header: 'เงินสดตั้งต้น',
                                    accessor: (item) =>
                                        formatCurrency(item.starting_cash),
                                },
                                {
                                    header: 'เงินเข้าคาดการณ์',
                                    accessor: (item) =>
                                        formatCurrency(item.projected_inflow),
                                },
                                {
                                    header: 'เงินออกคาดการณ์',
                                    accessor: (item) =>
                                        formatCurrency(item.projected_outflow),
                                },
                                {
                                    header: 'เงินสดสิ้นสุดคาดการณ์',
                                    accessor: (item) => (
                                        <span
                                            className={`font-semibold ${item.projected_ending_cash < 0 ? 'text-rose-600' : 'text-slate-900 dark:text-white'}`}
                                        >
                                            {formatCurrency(
                                                item.projected_ending_cash,
                                            )}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'จุดต่ำสุด (Lowest)',
                                    accessor: (item) => (
                                        <span className="text-xs text-rose-500">
                                            {formatCurrency(
                                                item.lowest_cash_point,
                                            )}{' '}
                                            ({item.lowest_cash_date || '-'})
                                        </span>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                )}
            </div>

            {/* Scenario Tuning Modal */}
            {selectedScenarioForEdit && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                        <div className="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-700">
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                                ปรับแต่งพารามิเตอร์:{' '}
                                {selectedScenarioForEdit.name}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setSelectedScenarioForEdit(null)}
                                className="text-slate-400 hover:text-slate-500"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        <form
                            onSubmit={handleSaveScenario}
                            className="mt-4 space-y-4"
                        >
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    ชื่อแบบจำลอง
                                </label>
                                <input
                                    type="text"
                                    value={editForm.name}
                                    onChange={(e) =>
                                        setEditForm({
                                            ...editForm,
                                            name: e.target.value,
                                        })
                                    }
                                    required
                                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs focus:border-indigo-500 focus:outline-hidden dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        AR Collection Rate (%)
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="200"
                                        value={editForm.ar_collection_rate}
                                        onChange={(e) =>
                                            setEditForm({
                                                ...editForm,
                                                ar_collection_rate: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    />
                                    <span className="text-[11px] text-slate-400">
                                        อัตราการเก็บหนี้ได้จริง
                                    </span>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Deal Multiplier (%)
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="200"
                                        value={editForm.deal_multiplier}
                                        onChange={(e) =>
                                            setEditForm({
                                                ...editForm,
                                                deal_multiplier: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    />
                                    <span className="text-[11px] text-slate-400">
                                        สัดส่วนดีลขายที่จะปิดสำเร็จ
                                    </span>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        AR Delay (วัน)
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="180"
                                        value={editForm.ar_delay_days}
                                        onChange={(e) =>
                                            setEditForm({
                                                ...editForm,
                                                ar_delay_days: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    />
                                    <span className="text-[11px] text-slate-400">
                                        เลื่อนรับเงินช้าลง
                                    </span>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Expense Buffer (%)
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="200"
                                        value={editForm.expense_buffer_percent}
                                        onChange={(e) =>
                                            setEditForm({
                                                ...editForm,
                                                expense_buffer_percent: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    />
                                    <span className="text-[11px] text-slate-400">
                                        เผื่อค่าใช้จ่ายฉุกเฉิน
                                    </span>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Payroll Buffer (%)
                                    </label>
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="200"
                                        value={editForm.payroll_buffer_percent}
                                        onChange={(e) =>
                                            setEditForm({
                                                ...editForm,
                                                payroll_buffer_percent: Number(
                                                    e.target.value,
                                                ),
                                            })
                                        }
                                        className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    />
                                    <span className="text-[11px] text-slate-400">
                                        เผื่อ OT/โบนัสเงินเดือน
                                    </span>
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setSelectedScenarioForEdit(null)
                                    }
                                    className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    ยกเลิก
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSavingScenario}
                                    className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:opacity-50"
                                >
                                    {isSavingScenario
                                        ? 'กำลังบันทึก...'
                                        : 'บันทึกการตั้งค่า'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
