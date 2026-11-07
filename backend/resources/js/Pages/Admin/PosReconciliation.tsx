import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/UI/PageHeader';
import InputError from '@/Components/InputError';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Report = {
    instance_id: string;
    name: string;
    from: string;
    to: string;
    source_checkpoint: string;
    checkpoint_through: number | null;
    checkpoint_checked_at: string | null;
    recovery_fenced: boolean;
    currency: string | null;
    sales_count: number;
    refund_count: number;
    cash_movement_count: number;
    gross_sales: string;
    refunds: string;
    cash_in: string;
    cash_out: string;
    source_cash_flow: string;
    inventory_pending: number;
    issues: { source_id: string; code: string }[];
};

export default function PosReconciliation({ report }: { report: Report }) {
    const [from, setFrom] = useState(report.from),
        [to, setTo] = useState(report.to);
    const [filterError, setFilterError] = useState('');
    const [loading, setLoading] = useState(false);
    return (
        <AuthenticatedLayout>
            <Head title="POS Ledger Reconciliation" />
            <PageHeader title="POS Ledger Reconciliation" />
            <div className="mx-auto max-w-7xl space-y-6">
                <Link
                    href="/pos-integration"
                    className="text-emerald-800 underline dark:text-emerald-200"
                >
                    กลับ POS Integration
                </Link>
                <h3 className="text-lg font-semibold">{report.name}</h3>
                <p>
                    สกุลเงิน: {report.currency || 'ไม่มีรายการ'} · จำกัด 100
                    records/type และ 1000 stock allocations ต่อรายงาน
                </p>
                <p className="rounded-lg bg-amber-50 p-4 text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    ตรวจหลักฐานที่ ERP รับไว้ ณ ปัจจุบัน · Checkpoint:{' '}
                    {report.source_checkpoint}
                    {report.checkpoint_through !== null && (
                        <>
                            {' '}
                            ถึง sequence {report.checkpoint_through} · ตรวจเมื่อ{' '}
                            {report.checkpoint_checked_at}
                        </>
                    )}{' '}
                    ·
                    {report.recovery_fenced && (
                        <>Instance ถูก recovery fence · </>
                    )}
                    รับรองเฉพาะช่วงที่ checkpoint ตรวจผ่าน ไม่รับรอง events
                    หลังจุดตรวจ ·
                    เงินสดเคลื่อนไหวไม่ใช่ยอดเงินสดองค์กรหรือยอดปิดกะ
                </p>
                <form
                    className="flex flex-wrap items-end gap-4 erp-surface p-4 sm:p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (loading) return;
                        const days =
                            (Date.parse(to) - Date.parse(from)) / 86_400_000;
                        if (days < 0 || days > 30) {
                            setFilterError(
                                'เลือกช่วงวันตามลำดับและไม่เกิน 31 วัน',
                            );
                            return;
                        }
                        setFilterError('');
                        router.get(
                            `/pos-integration/${report.instance_id}/reconciliation`,
                            { from, to },
                            {
                                preserveState: true,
                                onStart: () => setLoading(true),
                                onFinish: () => setLoading(false),
                                onError: (errors) =>
                                    setFilterError(
                                        errors.from ||
                                            errors.to ||
                                            'ตรวจช่วงวันไม่สำเร็จ',
                                    ),
                            },
                        );
                    }}
                >
                    <label>
                        จากวันที่
                        <input
                            className="erp-input block max-w-full"
                            type="date"
                            required
                            value={from}
                            aria-invalid={Boolean(filterError)}
                            aria-describedby={
                                filterError
                                    ? 'reconciliation-filter-error'
                                    : undefined
                            }
                            onChange={(e) => {
                                setFrom(e.target.value);
                                setFilterError('');
                            }}
                        />
                    </label>
                    <label>
                        ถึงวันที่
                        <input
                            className="erp-input block max-w-full"
                            type="date"
                            required
                            value={to}
                            aria-invalid={Boolean(filterError)}
                            aria-describedby={
                                filterError
                                    ? 'reconciliation-filter-error'
                                    : undefined
                            }
                            onChange={(e) => {
                                setTo(e.target.value);
                                setFilterError('');
                            }}
                        />
                    </label>
                    <button
                        className="erp-button erp-primary"
                        disabled={loading}
                    >
                        {loading ? 'กำลังตรวจ…' : 'ตรวจช่วงวัน (สูงสุด 31 วัน)'}
                    </button>
                    <InputError
                        id="reconciliation-filter-error"
                        className="w-full"
                        message={filterError}
                    />
                </form>
                <dl className="grid gap-4 erp-surface p-4 sm:p-6 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['ขายต้นฉบับ', report.gross_sales],
                        ['คืนเงิน', report.refunds],
                        ['Cash In', report.cash_in],
                        ['Cash Out', report.cash_out],
                        ['เงินสดเคลื่อนไหวสุทธิ', report.source_cash_flow],
                        ['จำนวน sale', report.sales_count],
                        ['จำนวน refund', report.refund_count],
                        ['Stock/COGS ค้าง', report.inventory_pending],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-sm text-erp-muted">{label}</dt>
                            <dd className="text-xl font-semibold">{value}</dd>
                        </div>
                    ))}
                </dl>
                <section className="overflow-x-auto erp-surface p-4 sm:p-6">
                    <h3 className="mb-4 font-semibold">
                        พบ {report.issues.length} ข้อที่ต้องตรวจ
                    </h3>
                    {report.issues.length === 0 ? (
                        <p>
                            หลักฐานใน ERP ที่เลือกสอดคล้องกัน ·
                            การตรวจความครบจาก POS ยังไม่พร้อม
                        </p>
                    ) : (
                        <table className="w-full text-left">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>เหตุผล</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.issues.map((issue, index) => (
                                    <tr key={index} className="border-t">
                                        <td className="break-all py-3">
                                            {issue.source_id}
                                        </td>
                                        <td>{issue.code}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
