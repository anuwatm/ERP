import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/Types/auth';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { type FormEvent, useEffect, useRef, useState } from 'react';

type Endpoint = {
    id: string;
    name: string;
    url: string;
    status: string;
    events: string[];
    verified_at: string | null;
};
type Delivery = {
    id: string;
    integration_webhook_event_id: string;
    status: string;
    attempts: number;
    last_http_status: number | null;
    last_error: string | null;
};
type Props = {
    enabled: boolean;
    endpoints: Endpoint[];
    keys: { id: string; name: string; prefix: string; events: string[] }[];
    deliveries: Delivery[];
};
const button = 'rounded border px-3 py-2 text-sm disabled:opacity-50';
const control =
    'mt-1 block w-full rounded border-gray-300 bg-white text-gray-900 dark:bg-gray-900 dark:text-white';

export default function ApiWebhooks(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const manage = auth.permissions.includes('api_keys.manage');
    const [endpoints, setEndpoints] = useState(props.endpoints);
    const [secret, setSecret] = useState<string | null>(null);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const pending = useRef(false);
    const [rotateId, setRotateId] = useState<string | null>(null);
    const [form, setForm] = useState({
        name: '',
        url: '',
        integration_api_key_id: '',
        events: [] as string[],
    });
    useEffect(() => {
        const clear = () => setSecret(null);
        window.addEventListener('pagehide', clear);
        return () => window.removeEventListener('pagehide', clear);
    }, []);
    const save = async (url: string, body?: typeof form) => {
        if (pending.current) return;
        pending.current = true;
        setBusy(true);
        setMessage('');
        try {
            const result = await axios.post<{
                endpoint: Endpoint;
                secret?: string;
            }>(url, body);
            setEndpoints((rows) => [
                result.data.endpoint,
                ...rows.filter((row) => row.id !== result.data.endpoint.id),
            ]);
            if (result.data.secret) setSecret(result.data.secret);
            setMessage('บันทึกแล้ว');
        } catch {
            setMessage(
                'ทำรายการไม่สำเร็จ ตรวจสิทธิ์ ยืนยันรหัสผ่าน และการตั้งค่าปลายทาง หากไม่ได้รับ secret ให้ rotate ใหม่',
            );
        } finally {
            pending.current = false;
            setBusy(false);
        }
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        void save(route('settings.api-webhooks.store'), form);
    };
    const events =
        props.keys.find((key) => key.id === form.integration_api_key_id)
            ?.events ?? [];
    return (
        <AuthenticatedLayout>
            <Head title="API Webhooks" />
            <div className="space-y-6 text-gray-900 dark:text-gray-100">
                <header>
                    <h1 className="text-2xl font-semibold">API Webhooks</h1>
                    <Link
                        className="underline"
                        href={route('settings.api-access.index')}
                    >
                        กลับ API Access
                    </Link>
                </header>
                <p>
                    ส่งเฉพาะรหัสเหตุการณ์ให้แอปดึงข้อมูลตามสิทธิ์
                    ไม่มีการแก้ข้อมูลธุรกิจ รับข้อมูลซ้ำ/ลำดับสลับได้และต้อง
                    dedupe ด้วย event ID
                </p>
                {!props.enabled && (
                    <p role="status">
                        ยังปิดการส่ง webhook จริง รอการติดตั้งและตรวจรับ
                        environment เป้าหมาย
                    </p>
                )}
                <p role="status">{message}</p>
                {props.enabled && manage && (
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded border p-4"
                    >
                        <h2 className="font-semibold">เพิ่มปลายทาง</h2>
                        <label className="block text-sm">
                            ชื่อแอป
                            <input
                                required
                                maxLength={100}
                                className={control}
                                value={form.name}
                                onChange={(e) =>
                                    setForm({ ...form, name: e.target.value })
                                }
                            />
                        </label>
                        <label className="block text-sm">
                            HTTPS URL ของผู้รับ
                            <input
                                required
                                type="url"
                                maxLength={2048}
                                className={control}
                                value={form.url}
                                onChange={(e) =>
                                    setForm({ ...form, url: e.target.value })
                                }
                            />
                        </label>
                        <label className="block text-sm">
                            Key ที่กำหนดสิทธิ์ผู้รับ
                            <select
                                required
                                className={control}
                                value={form.integration_api_key_id}
                                onChange={(e) =>
                                    setForm({
                                        ...form,
                                        integration_api_key_id: e.target.value,
                                        events: [],
                                    })
                                }
                            >
                                <option value="">เลือก key</option>
                                {props.keys
                                    .filter((key) => key.events.length > 0)
                                    .map((key) => (
                                        <option key={key.id} value={key.id}>
                                            {key.name} — {key.prefix}
                                        </option>
                                    ))}
                            </select>
                        </label>
                        <fieldset>
                            <legend>เหตุการณ์ที่อนุญาต</legend>
                            {events.map((event) => (
                                <label
                                    className="mt-2 flex items-center gap-2 text-sm"
                                    key={event}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.events.includes(event)}
                                        onChange={(e) =>
                                            setForm({
                                                ...form,
                                                events: e.target.checked
                                                    ? [...form.events, event]
                                                    : form.events.filter(
                                                          (value) =>
                                                              value !== event,
                                                      ),
                                            })
                                        }
                                    />
                                    {event}
                                </label>
                            ))}
                        </fieldset>
                        <p className="text-sm">
                            ปลายทางใหม่จะพักไว้ก่อน เก็บ secret
                            ที่แสดงครั้งเดียว ตั้งค่าผู้รับ แล้วกด Verify
                            เพื่อพิสูจน์ว่าเป็นเจ้าของ URL
                        </p>
                        <button
                            className={button}
                            disabled={busy || !form.events.length}
                        >
                            สร้างปลายทาง
                        </button>
                    </form>
                )}
                <section aria-label="ปลายทาง webhook" className="space-y-3">
                    {endpoints.map((endpoint) => (
                        <article
                            key={endpoint.id}
                            className="space-y-2 rounded border p-4"
                        >
                            <h2 className="font-semibold">
                                {endpoint.name} — {endpoint.status}
                            </h2>
                            <p className="break-all text-sm">{endpoint.url}</p>
                            <p className="text-sm">
                                {endpoint.events.join(' · ')}
                            </p>
                            {manage && (
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        disabled={busy}
                                        className={button}
                                        onClick={() =>
                                            void save(
                                                route(
                                                    'settings.api-webhooks.verify',
                                                    endpoint.id,
                                                ),
                                            )
                                        }
                                    >
                                        Verify / เริ่มส่ง
                                    </button>
                                    <button
                                        disabled={busy}
                                        className={button}
                                        onClick={() =>
                                            void save(
                                                route(
                                                    'settings.api-webhooks.pause',
                                                    endpoint.id,
                                                ),
                                            )
                                        }
                                    >
                                        พักการส่ง
                                    </button>
                                    <button
                                        disabled={busy}
                                        className={button}
                                        onClick={() => setRotateId(endpoint.id)}
                                    >
                                        Rotate secret
                                    </button>
                                </div>
                            )}
                        </article>
                    ))}
                </section>
                <section
                    aria-label="ประวัติส่ง webhook"
                    className="overflow-x-auto"
                >
                    <h2 className="mb-2 font-semibold">
                        ประวัติส่งล่าสุด 50 รายการ
                    </h2>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Event ID</th>
                                <th>สถานะ</th>
                                <th>Attempts</th>
                                <th>HTTP / Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {props.deliveries.map((delivery) => (
                                <tr key={delivery.id}>
                                    <td className="p-2 font-mono">
                                        {delivery.integration_webhook_event_id}
                                    </td>
                                    <td>{delivery.status}</td>
                                    <td>{delivery.attempts}</td>
                                    <td>
                                        {delivery.last_http_status ??
                                            delivery.last_error ??
                                            '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-2 text-sm">
                        dead_letter หยุด retry แล้ว
                        ต้องตรวจผู้รับก่อนส่งข้อมูลใหม่; retry ใช้ event ID เดิม
                    </p>
                </section>
            </div>
            <Dialog
                open={rotateId !== null}
                onClose={() => setRotateId(null)}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/50" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-lg rounded bg-white p-6 text-gray-900 dark:bg-gray-900 dark:text-white">
                        <DialogTitle className="font-semibold">
                            เปลี่ยน signing secret?
                        </DialogTitle>
                        <p className="my-3 text-sm">
                            ระบบจะพักการส่งจนกว่าผู้รับตั้งค่า secret ใหม่และ
                            Verify สำเร็จอีกครั้ง
                        </p>
                        <div className="flex justify-end gap-2">
                            <button
                                className={button}
                                onClick={() => setRotateId(null)}
                            >
                                ยกเลิก
                            </button>
                            <button
                                disabled={busy}
                                className={button}
                                onClick={() => {
                                    if (rotateId) {
                                        const id = rotateId;
                                        setRotateId(null);
                                        void save(
                                            route(
                                                'settings.api-webhooks.rotate',
                                                id,
                                            ),
                                        );
                                    }
                                }}
                            >
                                ยืนยันเปลี่ยน
                            </button>
                        </div>
                    </DialogPanel>
                </div>
            </Dialog>
            <Dialog
                open={secret !== null}
                onClose={() => setSecret(null)}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/50" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-xl rounded bg-white p-6 text-gray-900 dark:bg-gray-900 dark:text-white">
                        <DialogTitle className="font-semibold">
                            บันทึก signing secret — แสดงครั้งเดียว
                        </DialogTitle>
                        <p className="my-2 text-sm">
                            เก็บฝั่ง server ของผู้รับ ห้ามใส่ URL หรือ frontend
                        </p>
                        <textarea
                            readOnly
                            value={secret ?? ''}
                            className={`${control} font-mono`}
                            rows={3}
                            onFocus={(e) => e.target.select()}
                        />
                        <button
                            className={`${button} mt-4`}
                            onClick={() => setSecret(null)}
                        >
                            บันทึกแล้ว ปิด
                        </button>
                    </DialogPanel>
                </div>
            </Dialog>
        </AuthenticatedLayout>
    );
}
