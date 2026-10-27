import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold">
                    POS Ledger Reconciliation
                </h2>
            }
        >
            <Head title="POS Ledger Reconciliation" />
            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <Link
                    href="/pos-integration"
                    className="text-sky-700 underline"
                >
                    กลับ POS Integration
                </Link>
                <h3 className="text-lg font-semibold">{report.name}</h3>
                <p>
                    สกุลเงิน: {report.currency || 'ไม่มีรายการ'} · จำกัด 100
                    records/type และ 1000 stock allocations ต่อรายงาน
                </p>
                <p className="rounded bg-amber-50 p-4 text-amber-900">
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
                    className="flex flex-wrap items-end gap-4 rounded bg-white p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            `/pos-integration/${report.instance_id}/reconciliation`,
                            { from, to },
                        );
                    }}
                >
                    <label>
                        จากวันที่
                        <input
                            className="block rounded border p-2"
                            type="date"
                            required
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </label>
                    <label>
                        ถึงวันที่
                        <input
                            className="block rounded border p-2"
                            type="date"
                            required
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </label>
                    <button className="rounded border px-4 py-2">
                        ตรวจช่วงวัน (สูงสุด 31 วัน)
                    </button>
                </form>
                <dl className="grid gap-4 rounded bg-white p-6 sm:grid-cols-2 lg:grid-cols-4">
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
                            <dt className="text-sm text-gray-600">{label}</dt>
                            <dd className="text-xl font-semibold">{value}</dd>
                        </div>
                    ))}
                </dl>
                <section className="overflow-x-auto rounded bg-white p-6">
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
