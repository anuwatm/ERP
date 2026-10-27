import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import PosSetup, { SetupOptions } from './PosSetup';

type Instance = {
    id: string;
    name: string;
    active: boolean;
    durable_cursor: number;
    last_sync_at: string | null;
    last_sync_error: string | null;
    next_sync_at: string | null;
    recovery_fenced_at: string | null;
    checkpoint_cursor: number;
};
type Event = {
    id: string;
    event_id: string;
    status: string;
    reason_code: string | null;
    attempts: number;
    source_sequence: number;
};

export default function Pos({
    instances,
    events,
    inventoryPending,
    integrityConflicts,
    canReconcile,
    canManage,
    taxSigningOwnerId,
    taxSigningOwners,
    recallsEnabled,
    lotOptions,
    lotRecalls,
    taxDocumentsEnabled,
    productOptions,
    canProvision,
    setupOptions,
}: {
    instances: Instance[];
    events: Event[];
    inventoryPending: number;
    integrityConflicts: number;
    canReconcile: boolean;
    canManage: boolean;
    taxSigningOwnerId: string | null;
    taxSigningOwners: { id: string; name: string }[];
    recallsEnabled: boolean;
    lotOptions: { id: string; lot_no: string; name: string }[];
    lotRecalls: {
        id: string;
        inventory_lot_id: string;
        reason: string;
        created_at: string;
    }[];
    taxDocumentsEnabled: boolean;
    productOptions: { id: string; name: string; sku: string }[];
    canProvision: boolean;
    setupOptions: SetupOptions | null;
}) {
    const option = useForm({
        product_id: '',
        parent_product_id: '',
        label: '',
        size: '',
        modifiers: '',
    });
    const issuer = useForm({
        instance_id: '',
        version: '',
        branch_no: '00000',
    });
    const recall = useForm({ lot_id: '', reason: '' });
    const tax = useForm({
        signing_owner_id: taxSigningOwnerId || '',
        reason: '',
    });
    const action = (url: string) => {
        const reason = window.prompt(
            'ระบุเหตุผล (ต้องยืนยันรหัสผ่านตาม policy)',
        );
        if (reason?.trim()) router.post(url, { reason });
    };
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold">POS Integration</h2>}
        >
            <Head title="POS Integration" />
            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <p className="rounded bg-amber-50 p-4 text-amber-900">
                    Transport จริงยังไม่เปิด · รอ provision ปลายทาง · e-Tax
                    ยังไม่ผ่าน acceptance
                </p>
                {canManage && recallsEnabled && (
                    <form
                        className="space-y-4 rounded bg-white p-6 shadow"
                        onSubmit={(e) => {
                            e.preventDefault();
                            recall.post('/pos-integration/recalls', {
                                preserveScroll: true,
                                onSuccess: () => recall.reset(),
                            });
                        }}
                    >
                        <h3 className="font-semibold">
                            หยุดขาย / เรียกคืน lot
                        </h3>
                        <p>
                            หยุดขายและห้ามคืน Stock ของ lot นี้ คำสั่งไป POS
                            เมื่อ Connector sync สำเร็จ ไม่คืน quota
                            หรือยกเลิกประวัติการขายเดิม
                        </p>
                        <label className="block">
                            สินค้าและ lot
                            <select
                                aria-label="สินค้าและ lot"
                                required
                                className="block w-full rounded border p-2"
                                disabled={recall.processing}
                                value={recall.data.lot_id}
                                onChange={(e) =>
                                    recall.setData('lot_id', e.target.value)
                                }
                            >
                                <option value="">
                                    เลือก lot (สูงสุด 500 รายการ)
                                </option>
                                {lotOptions.map((l) => (
                                    <option key={l.id} value={l.id}>
                                        {l.name} · {l.lot_no}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            เหตุผลเรียกคืน
                            <input
                                required
                                maxLength={500}
                                className="block w-full rounded border p-2"
                                disabled={recall.processing}
                                value={recall.data.reason}
                                onChange={(e) =>
                                    recall.setData('reason', e.target.value)
                                }
                            />
                        </label>
                        {Object.values(recall.errors).map((error, i) => (
                            <p role="alert" className="text-red-700" key={i}>
                                {error}
                            </p>
                        ))}
                        <button
                            className="rounded border px-4 py-2"
                            disabled={recall.processing}
                        >
                            ยืนยันเรียกคืน lot
                        </button>
                        {lotRecalls.map((r) => (
                            <p key={r.id} className="break-words">
                                {r.inventory_lot_id} · {r.reason} ·{' '}
                                {r.created_at}
                            </p>
                        ))}
                    </form>
                )}
                {canManage && taxDocumentsEnabled && (
                    <form
                        className="space-y-4 rounded bg-white p-6 shadow"
                        onSubmit={(e) => {
                            e.preventDefault();
                            issuer.post('/pos-integration/tax-profile', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <h3 className="font-semibold">
                            ข้อมูลผู้ออก ABB / Full สำหรับ local
                        </h3>
                        <p>
                            ใช้ชื่อ เลขภาษี และที่อยู่จาก Organization/Branch ใน
                            ERP ต้องตั้งค่าข้อมูลให้ครบก่อนเผยแพร่
                        </p>
                        <label>
                            POS instance
                            <select
                                aria-label="POS instance สำหรับภาษี"
                                required
                                className="block w-full rounded border p-2"
                                value={issuer.data.instance_id}
                                onChange={(e) =>
                                    issuer.setData(
                                        'instance_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">เลือก instance</option>
                                {instances.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Issuer profile version
                            <input
                                required
                                maxLength={80}
                                className="block w-full rounded border p-2"
                                value={issuer.data.version}
                                onChange={(e) =>
                                    issuer.setData('version', e.target.value)
                                }
                            />
                        </label>
                        <label>
                            เลขสาขาภาษี
                            <input
                                required
                                pattern="[0-9]{5}"
                                maxLength={5}
                                className="block w-full rounded border p-2"
                                value={issuer.data.branch_no}
                                onChange={(e) =>
                                    issuer.setData('branch_no', e.target.value)
                                }
                            />
                        </label>
                        {Object.values(issuer.errors).map((error, i) => (
                            <p role="alert" className="text-red-700" key={i}>
                                {error}
                            </p>
                        ))}
                        <button
                            className="rounded border px-4 py-2"
                            disabled={issuer.processing}
                        >
                            เตรียมข้อมูลผู้ออกเอกสาร
                        </button>
                    </form>
                )}
                {canManage && (
                    <form
                        className="space-y-4 rounded bg-white p-6 shadow"
                        onSubmit={(e) => {
                            e.preventDefault();
                            option.transform((d) => ({
                                ...d,
                                modifiers: d.modifiers
                                    .split(',')
                                    .map((s) => s.trim())
                                    .filter(Boolean),
                            }));
                            option.post('/pos-integration/restaurant-options', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <h3 className="font-semibold">ขนาดและตัวเลือกอาหาร</h3>
                        <p>
                            แต่ละชุดตัวเลือกใช้ SKU ราคาและ BOM ของตัวเอง เช่น
                            จานใหญ่ + เพิ่มไข่ ระบบเก็บสูตรและราคาตาม catalog
                            ที่ใช้สั่ง ไม่เปลี่ยนย้อนหลัง
                        </p>
                        <label>
                            เมนูหลัก
                            <select
                                aria-label="เมนูหลัก"
                                className="block w-full rounded border p-2"
                                required
                                value={option.data.parent_product_id}
                                onChange={(e) =>
                                    option.setData(
                                        'parent_product_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">เลือกเมนูหลัก</option>
                                {productOptions.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.sku} · {p.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            SKU ชุดตัวเลือก
                            <select
                                aria-label="SKU ชุดตัวเลือก"
                                className="block w-full rounded border p-2"
                                required
                                value={option.data.product_id}
                                onChange={(e) =>
                                    option.setData('product_id', e.target.value)
                                }
                            >
                                <option value="">
                                    เลือก SKU ที่ตั้งราคา/สูตรแล้ว
                                </option>
                                {productOptions.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.sku} · {p.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            ชื่อชุดตัวเลือก
                            <input
                                className="block w-full rounded border p-2"
                                required
                                maxLength={100}
                                value={option.data.label}
                                onChange={(e) =>
                                    option.setData('label', e.target.value)
                                }
                            />
                        </label>
                        <label>
                            ขนาด
                            <input
                                className="block w-full rounded border p-2"
                                required
                                maxLength={60}
                                value={option.data.size}
                                onChange={(e) =>
                                    option.setData('size', e.target.value)
                                }
                            />
                        </label>
                        <label>
                            ตัวเลือกเพิ่ม คั่นด้วยจุลภาค
                            <input
                                className="block w-full rounded border p-2"
                                value={option.data.modifiers}
                                onChange={(e) =>
                                    option.setData('modifiers', e.target.value)
                                }
                            />
                        </label>
                        {Object.values(option.errors).map((error, i) => (
                            <p role="alert" className="text-red-700" key={i}>
                                {error}
                            </p>
                        ))}
                        <button
                            className="rounded border px-4 py-2"
                            disabled={option.processing}
                        >
                            บันทึกชุดตัวเลือก
                        </button>
                    </form>
                )}
                {canProvision && setupOptions && (
                    <PosSetup options={setupOptions} instances={instances} />
                )}
                <div className="flex gap-6">
                    <p>Stock/COGS ค้าง: {inventoryPending}</p>
                    <p>Integrity conflicts: {integrityConflicts}</p>
                </div>
                {canManage && (
                    <form
                        className="space-y-4 rounded bg-white p-6 shadow"
                        onSubmit={(e) => {
                            e.preventDefault();
                            tax.transform((data) => ({
                                ...data,
                                signing_owner_id: data.signing_owner_id || null,
                            }));
                            tax.post('/pos-integration/tax-settings', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <h3 className="font-semibold">
                            ผู้รับผิดชอบการลงนาม e-Tax
                        </h3>
                        <p>
                            ยังไม่มี provider
                            การเลือกบุคคลนี้ยังไม่เปิดลงนามดิจิทัลหรือส่งเอกสาร
                            ต้องผูก certificate
                            และสิทธิ์ตามช่องทางที่เลือกก่อนใช้งานจริง
                        </p>
                        <label className="block">
                            ผู้รับผิดชอบ
                            <select
                                className="block w-full rounded border p-2"
                                value={tax.data.signing_owner_id}
                                disabled={tax.processing}
                                onChange={(e) =>
                                    tax.setData(
                                        'signing_owner_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">ยังไม่เลือก</option>
                                {taxSigningOwnerId &&
                                    !taxSigningOwners.some(
                                        (u) => u.id === taxSigningOwnerId,
                                    ) && (
                                        <option
                                            value={taxSigningOwnerId}
                                            disabled
                                        >
                                            ผู้รับผิดชอบเดิมใช้งานไม่ได้
                                            กรุณาเลือกใหม่
                                        </option>
                                    )}
                                {taxSigningOwners.map((u) => (
                                    <option value={u.id} key={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block">
                            เหตุผลเปลี่ยนผู้รับผิดชอบ
                            <input
                                className="block w-full rounded border p-2"
                                required
                                maxLength={500}
                                disabled={tax.processing}
                                value={tax.data.reason}
                                onChange={(e) =>
                                    tax.setData('reason', e.target.value)
                                }
                            />
                        </label>
                        {Object.values(tax.errors).map((error, i) => (
                            <p role="alert" key={i} className="text-red-700">
                                {error}
                            </p>
                        ))}
                        <button
                            disabled={tax.processing}
                            className="rounded border px-4 py-2"
                        >
                            บันทึกผู้รับผิดชอบ e-Tax
                        </button>
                    </form>
                )}
                <section className="rounded bg-white p-6 shadow">
                    <h3 className="mb-4 font-semibold">POS instances</h3>
                    {instances.length === 0 && (
                        <p>ยังไม่มี instance ที่ลงทะเบียน</p>
                    )}
                    {instances.map((i) => (
                        <div
                            key={i.id}
                            className="flex items-center justify-between border-b py-4"
                        >
                            <div>
                                {i.name} · {i.active ? 'Active' : 'Paused'} ·
                                Cursor {i.durable_cursor}
                                <p>Sync ล่าสุด (UTC): {i.last_sync_at || 'ยังไม่สำเร็จ'} · Checkpoint {i.checkpoint_cursor}</p>
                                {i.last_sync_error && <p className="text-red-700">Sync error: {i.last_sync_error} · retry หลัง {i.next_sync_at} UTC</p>}
                                {i.recovery_fenced_at && <p className="text-red-700">Recovery fenced — หยุดโพสต์จนตรวจหลักฐานครบ</p>}
                                {canReconcile && (
                                    <Link
                                        href={`/pos-integration/${i.id}/reconciliation`}
                                        className="ml-4 text-sky-700 underline"
                                    >
                                        ตรวจหลักฐาน ERP
                                    </Link>
                                )}
                            </div>
                            {i.active && (
                                <button
                                    className="rounded border px-4 py-2"
                                    onClick={() =>
                                        action(`/pos-integration/${i.id}/pause`)
                                    }
                                >
                                    พัก instance
                                </button>
                            )}
                        </div>
                    ))}
                </section>
                <section className="overflow-x-auto rounded bg-white p-6 shadow">
                    <h3 className="mb-4 font-semibold">100 events ล่าสุด</h3>
                    <table className="w-full text-left">
                        <thead>
                            <tr>
                                <th>Sequence</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Reason / Attempts</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.map((e) => (
                                <tr className="border-t" key={e.id}>
                                    <td className="py-3">
                                        {e.source_sequence}
                                    </td>
                                    <td>{e.event_id}</td>
                                    <td>{e.status}</td>
                                    <td>
                                        {e.reason_code || '—'} / {e.attempts}
                                    </td>
                                    <td>
                                        {e.status !== 'accepted' && (
                                            <button
                                                className="rounded border px-3 py-2"
                                                onClick={() =>
                                                    action(
                                                        `/pos-events/${e.id}/replay`,
                                                    )
                                                }
                                            >
                                                Replay ผ่าน validation
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
