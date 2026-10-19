import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useEditorCoordinator, {
    type GuardRequestFn,
} from '@/Hooks/useEditorCoordinator';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/Types/auth';
import {
    Dialog,
    DialogDescription,
    DialogPanel,
    DialogTitle,
} from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    type FormEvent,
    type MutableRefObject,
    useEffect,
    useRef,
    useState,
} from 'react';

type ApiKey = {
    id: string;
    name: string;
    prefix: string;
    scopes: string[];
    access_policy?: {
        hr_all_branches?: boolean;
        hr_branch_ids?: string[];
        principal?: string;
        party_id?: string | null;
        document_ids?: string[];
    } | null;
    created_by: string | null;
    created_at: string;
    expires_at: string;
    revoked_at: string | null;
    last_used_at: string | null;
    status: 'active' | 'expired' | 'revoked';
};
type Editor = (
    { mode: 'create' } | { mode: 'edit' | 'revoke'; key: ApiKey }
) & { instance?: string };
type Result = { key: ApiKey; secret?: string };
type Props = {
    keys: ApiKey[];
    availableScopes: string[];
    hrBranches: { id: string; code: string; name: string }[];
    defaultLifetimeDays: number;
    maxLifetimeDays: number;
    maxActiveKeys: number;
    apiBaseUrl: string;
};
const button =
    'min-h-10 rounded-md border border-gray-300 px-3 text-sm font-medium hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50 dark:border-gray-600 dark:hover:bg-gray-800';
const control =
    'mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
const partyScopes: Record<string, string[]> = {
    customer: [
        'customers:read',
        'quotations:read',
        'invoices:read',
        'delivery-orders:read',
        'payments:read',
    ],
    supplier: ['suppliers:read', 'purchase-orders:read', 'goods-receipts:read'],
};
const scopeLabels: Record<string, string> = {
    'approvals:read':
        'รายการอนุมัติที่ผู้สร้าง key มีสิทธิ์อ่าน — อนุมัติผ่าน API ไม่ได้',
    'summary:manufacturing:read': 'สรุปสถานะและปริมาณการผลิต — ไม่รวมต้นทุน',
    'manufacturing:boms:read': 'ข้อมูลพื้นฐาน BOM',
    'manufacturing:formulas:read': 'รายการสูตร BOM — ต้องมีสิทธิ์ BOM ด้วย',
    'manufacturing:work-orders:read': 'ใบสั่งผลิตและปริมาณเบิก',
    'manufacturing:costs:read':
        'ต้นทุนการผลิต — สิทธิ์เพิ่มเติมจากข้อมูลพื้นฐาน',
    'documents:read': 'Metadata เฉพาะเอกสารที่เลือก',
    'documents:download':
        'ดาวน์โหลด current version ที่ผ่าน scan — ต้องมีสิทธิ์ metadata ด้วย',
    'customers:read': 'รายชื่อลูกค้า — ข้อมูลบริษัทพื้นฐาน',
    'suppliers:read': 'รายชื่อคู่ค้า — ข้อมูลบริษัทพื้นฐาน',
    'quotations:read': 'ใบเสนอราคาและรายการสินค้า',
    'invoices:read': 'Invoice ยอดชำระและยอดคงเหลือ',
    'delivery-orders:read': 'ใบส่งสินค้าและสถานะการส่ง',
    'payments:read': 'รายการรับชำระและรายการกลับรายการ — ไม่รวมบัญชีธนาคาร',
    'purchase-orders:read': 'ใบสั่งซื้อและปริมาณค้างรับ',
    'goods-receipts:read': 'รายการรับสินค้า',
    'projects:read': 'รายชื่อและรายละเอียดโครงการพื้นฐาน',
    'tasks:read': 'รายชื่อและสถานะงานพื้นฐาน',
    'summary:procurement:read': 'สรุปจัดซื้อและปริมาณค้างรับ',
    'summary:hr:read': 'สรุปบุคลากรตามขอบเขต HR ที่เลือก',
    'summary:attendance:read':
        'สรุปเวลาเข้างาน — ไม่รวมพิกัดหรือข้อมูลเงินเดือน',
    'summary:leaves:read': 'สรุปวันลา — ไม่รวมประเภทลาและเหตุผล',
    'summary:roster:read': 'สรุปการจัดกะ',
    'employees:read': 'รายชื่อและข้อมูลพื้นฐานพนักงาน',
    'employees:attendance:read': 'เวลาเข้างานรายพนักงาน',
    'employees:roster:read': 'ตารางกะรายพนักงาน',
    'employees:leaves:read': 'วันลาและยอดสิทธิ์รายพนักงาน — ไม่รวมเหตุผล',
    'reference:organization:read': 'โครงสร้างองค์กร — สาขา ฝ่าย แผนก ตำแหน่ง',
    'reference:currencies:read': 'สกุลเงินที่องค์กรกำหนด',
    'products:read': 'รายชื่อและข้อมูลพื้นฐานสินค้า/บริการ',
    'products:prices:read': 'ราคาขายสินค้า — ต้องมีสิทธิ์อ่านสินค้าด้วย',
    'products:costs:read': 'ต้นทุนสินค้า — ต้องมีสิทธิ์อ่านสินค้าด้วย',
    'inventory:read': 'คลังและยอดคงคลังแยกสินค้า/คลัง/lot',
    'summary:inventory:read': 'สรุปสินค้าคงคลังและสินค้าต่ำกว่าจุดสั่งซื้อ',
    'summary:projects:read':
        'สรุปโครงการและงาน — สถานะ ความคืบหน้า และงานเกินกำหนด',
    'summary:sales:read': 'สรุปงานขาย — ลูกค้า Deals และใบเสนอราคา',
    'summary:finance:read': 'สรุปการเงิน — Invoice เงินรับ รายจ่าย และลูกหนี้',
};
const date = (value: string | null) =>
    value ? new Date(value).toLocaleString('th-TH') : 'ยังไม่เคยใช้งาน';

function DocumentGrantPicker({
    ids,
    onChange,
}: {
    ids: string[];
    onChange: (ids: string[]) => void;
}) {
    type Choice = { id: string; document_no: string; title: string };
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState<Choice[]>([]);
    const [selected, setSelected] = useState<Choice[]>([]);
    useEffect(() => {
        if (search.trim().length < 2) return;
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void axios
                .get<{ data: Choice[] }>(
                    route('settings.api-access.documents'),
                    { params: { search }, signal: controller.signal },
                )
                .then((response) => setOptions(response.data.data))
                .catch(() => {
                    if (!controller.signal.aborted) setOptions([]);
                });
        }, 300);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [search]);
    return (
        <fieldset className="space-y-2 rounded border border-gray-300 p-4 dark:border-gray-600">
            <legend className="text-sm font-medium">
                เอกสารที่อนุญาต (สูงสุด 100)
            </legend>
            <p className="text-xs">
                เลือกเฉพาะเอกสารทั่วไปที่เหมาะกับแอปนี้ ไม่เลือกเงินเดือน
                บัญชีธนาคาร หรือข้อมูลส่วนบุคคล เอกสาร confidential ไม่เปิดผ่าน
                API
            </p>
            <label className="block text-sm">
                ค้นหาเลขหรือชื่อเอกสาร
                <input
                    className={control}
                    maxLength={100}
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setOptions([]);
                    }}
                />
            </label>
            <p role="status" className="text-sm">
                เลือกแล้ว {ids.length} รายการ
            </p>
            {selected
                .filter((item) => ids.includes(item.id))
                .map((item) => (
                    <div
                        key={item.id}
                        className="flex items-center justify-between gap-2 text-sm"
                    >
                        <span>
                            {item.document_no} — {item.title}
                        </span>
                        <button
                            type="button"
                            className={button}
                            onClick={() =>
                                onChange(ids.filter((id) => id !== item.id))
                            }
                        >
                            เอาออก
                        </button>
                    </div>
                ))}
            <div className="max-h-48 overflow-y-auto">
                {options
                    .filter((item) => !ids.includes(item.id))
                    .map((item) => (
                        <button
                            type="button"
                            key={item.id}
                            disabled={ids.length >= 100}
                            className="block w-full rounded p-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                            onClick={() => {
                                onChange([...ids, item.id]);
                                setSelected((rows) => [
                                    ...rows.filter((row) => row.id !== item.id),
                                    item,
                                ]);
                            }}
                        >
                            {item.document_no} — {item.title}
                        </button>
                    ))}
            </div>
        </fieldset>
    );
}

export default function ApiAccess(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('api_keys.manage');
    const [keys, setKeys] = useState(props.keys);
    const [secret, setSecret] = useState<string | null>(null);
    const [copyStatus, setCopyStatus] = useState('');
    const { activeEditor, activeGuardRef, switchEditor, closeActiveEditor } =
        useEditorCoordinator<Editor>();
    const openEditor = (editor: Editor) =>
        switchEditor({ ...editor, instance: crypto.randomUUID() });
    useEffect(() => {
        const clear = () => setSecret(null);
        window.addEventListener('pagehide', clear);
        return () => window.removeEventListener('pagehide', clear);
    }, []);
    const saved = (result: Result) => {
        setKeys((current) => [
            result.key,
            ...current.filter((key) => key.id !== result.key.id),
        ]);
        closeActiveEditor();
        setCopyStatus('');
        if (result.secret) setSecret(result.secret);
    };
    return (
        <AuthenticatedLayout>
            <Head title="API Access" />
            <div className="space-y-6 text-gray-900 dark:text-gray-100">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">API Access</h1>
                        <Link
                            className="text-sm underline"
                            href={route('settings.api-webhooks.index')}
                        >
                            Webhook และประวัติการส่ง
                        </Link>
                        <p className="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                            ให้แอปภายนอกอ่านข้อมูลที่องค์กรอนุญาต
                            เลือกสิทธิ์แยกแต่ละแอป และถอน key ได้ทันที
                        </p>
                    </div>
                    {canManage && (
                        <button
                            className={`${button} border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700`}
                            onClick={() => openEditor({ mode: 'create' })}
                        >
                            สร้าง secret key
                        </button>
                    )}
                </header>
                <section
                    aria-label="วิธีเรียก API"
                    className="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <p className="text-sm">
                        เก็บ key ฝั่ง server ของแอปผู้เรียก ใช้ HTTPS และส่งผ่าน{' '}
                        <code>Authorization: Bearer &lt;secret&gt;</code>
                    </p>
                    <p className="mt-2 break-all font-mono text-sm">
                        GET {props.apiBaseUrl}/sales · /finance · /projects
                    </p>
                    <p className="mt-2 break-all font-mono text-sm">
                        GET {props.apiBaseUrl.replace(/\/summaries$/, '')}
                        /products · /employees · /invoices · /documents
                    </p>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        อ่านได้อย่างเดียว · 60 ครั้ง/นาที/key · สูงสุด{' '}
                        {props.maxActiveKeys} active keys ·
                        เวลาที่แสดงอิงอุปกรณ์นี้
                    </p>
                </section>
                <section
                    aria-label="รายการ secret key"
                    className="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                >
                    <table className="w-full text-left text-sm">
                        <thead className="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                {[
                                    'แอป / Key prefix',
                                    'สิทธิ์',
                                    'สถานะ',
                                    'หมดอายุ / ใช้ล่าสุด',
                                    'จัดการ',
                                ].map((label) => (
                                    <th
                                        key={label}
                                        className="whitespace-nowrap px-4 py-3 font-semibold"
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {keys.map((key) => (
                                <tr key={key.id}>
                                    <td className="px-4 py-4">
                                        <p className="font-medium">
                                            {key.name}
                                        </p>
                                        <p className="mt-1 font-mono text-xs">
                                            {key.prefix}…
                                        </p>
                                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                            สร้าง {date(key.created_at)} ·
                                            ผู้สร้าง{' '}
                                            {key.created_by ?? 'ไม่ระบุ'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-4">
                                        {key.scopes.map((scope) => (
                                            <p
                                                key={scope}
                                                className="whitespace-nowrap py-1 font-mono text-xs"
                                            >
                                                {scope}
                                            </p>
                                        ))}
                                    </td>
                                    <td className="px-4 py-4">
                                        <span className="rounded bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-gray-700">
                                            {key.status}
                                        </span>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-4">
                                        <p>{date(key.expires_at)}</p>
                                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                            ใช้ล่าสุด: {date(key.last_used_at)}
                                        </p>
                                    </td>
                                    <td className="px-4 py-4">
                                        {canManage && (
                                            <div className="flex gap-2">
                                                {key.status === 'active' && (
                                                    <button
                                                        className={button}
                                                        onClick={() =>
                                                            openEditor({
                                                                mode: 'edit',
                                                                key,
                                                            })
                                                        }
                                                    >
                                                        แก้ไข
                                                    </button>
                                                )}
                                                {key.status !== 'revoked' && (
                                                    <button
                                                        className={`${button} text-red-700 dark:text-red-300`}
                                                        onClick={() =>
                                                            openEditor({
                                                                mode: 'revoke',
                                                                key,
                                                            })
                                                        }
                                                    >
                                                        ถอน key
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!keys.length && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-8 text-center text-gray-600 dark:text-gray-300"
                                    >
                                        ยังไม่มี key สร้างแยกสำหรับแต่ละแอป
                                        และเลือกเฉพาะสิทธิ์ที่ต้องใช้
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
                <p className="text-sm text-gray-600 dark:text-gray-300">
                    เปลี่ยน key: สร้างใหม่ → อัปเดตแอปผู้เรียก → ตรวจว่าเรียกได้
                    → ถอน key เก่า ระบบไม่สามารถแสดง secret เดิมซ้ำได้
                </p>
            </div>
            {activeEditor && (
                <KeyEditor
                    key={activeEditor.instance}
                    editor={activeEditor}
                    scopes={props.availableScopes}
                    hrBranches={props.hrBranches}
                    defaultDays={props.defaultLifetimeDays}
                    maxDays={props.maxLifetimeDays}
                    guardRef={activeGuardRef}
                    onClose={closeActiveEditor}
                    onSaved={saved}
                />
            )}
            <Dialog
                open={secret !== null}
                onClose={() => setSecret(null)}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/50" aria-hidden="true" />
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <DialogPanel className="w-full max-w-xl rounded-lg bg-white p-6 text-gray-900 shadow-xl dark:bg-gray-900 dark:text-gray-100">
                        <DialogTitle className="text-lg font-semibold">
                            บันทึก secret key ก่อนปิด
                        </DialogTitle>
                        <DialogDescription className="mt-2 text-sm">
                            แสดงครั้งเดียวเท่านั้น เก็บใน secret manager ฝั่ง
                            server ของแอปคุณ
                        </DialogDescription>
                        <label
                            htmlFor="new-secret"
                            className="mt-4 block text-sm font-medium"
                        >
                            Secret key
                        </label>
                        <textarea
                            id="new-secret"
                            readOnly
                            value={secret ?? ''}
                            className={`${control} font-mono`}
                            rows={3}
                            onFocus={(event) => event.target.select()}
                        />
                        <p role="status" className="mt-2 text-sm">
                            {copyStatus}
                        </p>
                        <div className="mt-5 flex flex-wrap justify-end gap-2">
                            <button
                                className={button}
                                onClick={async () => {
                                    try {
                                        await navigator.clipboard.writeText(
                                            secret ?? '',
                                        );
                                        setCopyStatus('คัดลอกแล้ว');
                                    } catch {
                                        setCopyStatus(
                                            'เลือกข้อความแล้วคัดลอกด้วยตนเอง',
                                        );
                                    }
                                }}
                            >
                                คัดลอก
                            </button>
                            <button
                                className={button}
                                onClick={() => setSecret(null)}
                            >
                                บันทึกแล้ว ปิดหน้าต่าง
                            </button>
                        </div>
                    </DialogPanel>
                </div>
            </Dialog>
        </AuthenticatedLayout>
    );
}

function KeyEditor({
    editor,
    scopes,
    hrBranches,
    defaultDays,
    maxDays,
    guardRef,
    onClose,
    onSaved,
}: {
    editor: Editor;
    scopes: string[];
    hrBranches: Props['hrBranches'];
    defaultDays: number;
    maxDays: number;
    guardRef: MutableRefObject<GuardRequestFn | null>;
    onClose: () => void;
    onSaved: (result: Result) => void;
}) {
    const key = editor.mode === 'create' ? null : editor.key;
    const expiry = key ? new Date(key.expires_at) : null;
    const localExpiry = expiry
        ? new Date(expiry.getTime() - expiry.getTimezoneOffset() * 60000)
              .toISOString()
              .slice(0, 16)
        : '';
    const form = useForm({
        name: key?.name ?? '',
        scopes: key?.scopes ?? ([] as string[]),
        lifetime_days: defaultDays,
        expires_at: localExpiry,
        access_policy: {
            document_ids: key?.access_policy?.document_ids ?? ([] as string[]),
            principal: key?.access_policy?.principal ?? 'organization',
            party_id: key?.access_policy?.party_id ?? '',
            hr_all_branches: key?.access_policy?.hr_all_branches ?? false,
            hr_branch_ids:
                key?.access_policy?.hr_branch_ids ?? ([] as string[]),
        },
    });
    const [processing, setProcessing] = useState(false);
    const [partySearch, setPartySearch] = useState('');
    const [partyOptions, setPartyOptions] = useState<
        { id: string; code: string; name: string }[]
    >([]);
    const [selectedParty, setSelectedParty] = useState('');
    useEffect(() => {
        if (
            editor.mode !== 'create' ||
            form.data.access_policy.principal === 'organization' ||
            partySearch.trim().length < 2
        ) {
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            void axios
                .get<{ data: { id: string; code: string; name: string }[] }>(
                    route('settings.api-access.parties'),
                    {
                        params: {
                            type: form.data.access_policy.principal,
                            search: partySearch,
                        },
                        signal: controller.signal,
                    },
                )
                .then((response) => setPartyOptions(response.data.data))
                .catch(() => {
                    if (!controller.signal.aborted) setPartyOptions([]);
                });
        }, 300);
        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [editor.mode, form.data.access_policy.principal, partySearch]);
    const pending = useRef(false);
    const [minimized, setMinimized] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const guard = useDiscardGuard(form.isDirty, processing);
    useEffect(() => {
        guardRef.current = guard.request;
        return () => {
            guardRef.current = null;
        };
    }, [guardRef, guard.request]);
    const title =
        editor.mode === 'create'
            ? 'สร้าง secret key'
            : editor.mode === 'edit'
              ? 'แก้ไข key'
              : 'ถอน key';
    const submit = async (event: FormEvent) => {
        event.preventDefault();
        if (pending.current) return;
        pending.current = true;
        setProcessing(true);
        setErrors({});
        try {
            let response;
            if (editor.mode === 'create')
                response = await axios.post<Result>(
                    route('settings.api-access.store'),
                    {
                        name: form.data.name,
                        scopes: form.data.scopes,
                        lifetime_days: form.data.lifetime_days,
                        access_policy: form.data.access_policy,
                    },
                );
            else if (editor.mode === 'revoke')
                response = await axios.post<Result>(
                    route('settings.api-access.revoke', editor.key.id),
                );
            else
                response = await axios.patch<Result>(
                    route('settings.api-access.update', editor.key.id),
                    {
                        name: form.data.name,
                        expires_at: new Date(form.data.expires_at)
                            .toISOString()
                            .replace('.000Z', '+00:00'),
                    },
                );
            onSaved(response.data);
        } catch (error) {
            if (
                axios.isAxiosError<{ errors?: Record<string, string[]> }>(error)
            ) {
                const validation = error.response?.data.errors;
                setErrors(
                    validation
                        ? Object.fromEntries(
                              Object.entries(validation).map(
                                  ([field, messages]) => [field, messages[0]],
                              ),
                          )
                        : {
                              request:
                                  error.response?.status === 423
                                      ? 'กรุณายืนยันรหัสผ่าน แล้วลองอีกครั้ง'
                                      : 'ทำรายการไม่ได้ ตรวจการเชื่อมต่อและสิทธิ์ หากสร้าง key แล้วไม่ได้รับ secret ให้ถอน key นั้นแล้วสร้างใหม่',
                          },
                );
            } else
                setErrors({ request: 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง' });
        } finally {
            pending.current = false;
            setProcessing(false);
        }
    };
    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={title}
                breadcrumb="Settings / API Access"
                entityLabel="API key"
                draftLabel={form.data.name || 'New app'}
                storageKey="api-key-dialog-size"
                formId="api-key-editor"
                processing={processing}
                saveLabel={title}
                errors={errors}
                status={
                    form.isDirty ? 'มีการเปลี่ยนแปลง' : 'กำหนดสิทธิ์เฉพาะแอป'
                }
                onSubmit={submit}
            >
                {() =>
                    editor.mode === 'revoke' ? (
                        <p className="text-sm">
                            ถอน key ของ <strong>{key?.name}</strong> (
                            {key?.prefix})? แอปที่ใช้ key นี้จะเรียก API
                            ไม่ได้ตั้งแต่คำขอถัดไป
                        </p>
                    ) : (
                        <div className="space-y-4">
                            <label className="block text-sm font-medium">
                                ชื่อแอป
                                <input
                                    required
                                    maxLength={100}
                                    className={control}
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={!!errors.name}
                                />
                            </label>
                            {editor.mode === 'create' ? (
                                <>
                                    <label className="block text-sm font-medium">
                                        แอปสำหรับ
                                        <select
                                            className={control}
                                            value={
                                                form.data.access_policy
                                                    .principal
                                            }
                                            onChange={(event) => {
                                                form.setData('scopes', []);
                                                form.setData('access_policy', {
                                                    principal:
                                                        event.target.value,
                                                    party_id: '',
                                                    hr_all_branches: false,
                                                    hr_branch_ids: [],
                                                    document_ids: [],
                                                });
                                                setPartySearch('');
                                                setSelectedParty('');
                                            }}
                                        >
                                            <option value="organization">
                                                ระบบภายในองค์กร —
                                                ตามสิทธิ์ที่เลือก
                                            </option>
                                            <option value="customer">
                                                ลูกค้ารายเดียว
                                            </option>
                                            <option value="supplier">
                                                คู่ค้ารายเดียว
                                            </option>
                                        </select>
                                    </label>
                                    {form.data.access_policy.principal !==
                                        'organization' && (
                                        <div className="space-y-2 text-sm">
                                            <label className="block">
                                                ค้นหาลูกค้า/คู่ค้า (อย่างน้อย 2
                                                ตัวอักษร)
                                                <input
                                                    className={control}
                                                    maxLength={100}
                                                    value={partySearch}
                                                    onChange={(event) => {
                                                        setPartySearch(
                                                            event.target.value,
                                                        );
                                                        setPartyOptions([]);
                                                    }}
                                                />
                                            </label>
                                            <p role="status">
                                                {selectedParty
                                                    ? `เลือก: ${selectedParty}`
                                                    : 'กรุณาเลือกรายการก่อนสร้าง key'}
                                            </p>
                                            <div className="max-h-48 overflow-y-auto">
                                                {partyOptions.map((party) => (
                                                    <button
                                                        type="button"
                                                        className="block w-full rounded p-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
                                                        key={party.id}
                                                        onClick={() => {
                                                            form.setData(
                                                                'access_policy',
                                                                {
                                                                    ...form.data
                                                                        .access_policy,
                                                                    party_id:
                                                                        party.id,
                                                                },
                                                            );
                                                            setSelectedParty(
                                                                `${party.code} — ${party.name}`,
                                                            );
                                                            setPartyOptions([]);
                                                        }}
                                                    >
                                                        {party.code} —{' '}
                                                        {party.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <fieldset>
                                        <legend className="text-sm font-medium">
                                            สิทธิ์อ่านข้อมูล
                                        </legend>
                                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                            เลือกเฉพาะโดเมนที่แอปต้องใช้ สิทธิ์
                                            HR จำกัดสาขาเพิ่มเติมด้านล่าง
                                        </p>
                                        {scopes
                                            .filter(
                                                (scope) =>
                                                    form.data.access_policy
                                                        .principal ===
                                                        'organization' ||
                                                    partyScopes[
                                                        form.data.access_policy
                                                            .principal
                                                    ]?.includes(scope),
                                            )
                                            .map((scope) => (
                                                <label
                                                    key={scope}
                                                    className="mt-3 flex items-start gap-3 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        checked={form.data.scopes.includes(
                                                            scope,
                                                        )}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'scopes',
                                                                event.target
                                                                    .checked
                                                                    ? [
                                                                          ...form
                                                                              .data
                                                                              .scopes,
                                                                          scope,
                                                                      ]
                                                                    : form.data.scopes.filter(
                                                                          (
                                                                              value,
                                                                          ) =>
                                                                              value !==
                                                                              scope,
                                                                      ),
                                                            )
                                                        }
                                                    />
                                                    <span>
                                                        {scopeLabels[scope] ??
                                                            scope}
                                                        <code className="mt-1 block text-xs">
                                                            {scope}
                                                        </code>
                                                    </span>
                                                </label>
                                            ))}
                                    </fieldset>
                                    {form.data.scopes.some((scope) =>
                                        scope.startsWith('documents:'),
                                    ) && (
                                        <DocumentGrantPicker
                                            ids={
                                                form.data.access_policy
                                                    .document_ids
                                            }
                                            onChange={(document_ids) =>
                                                form.setData('access_policy', {
                                                    ...form.data.access_policy,
                                                    document_ids,
                                                })
                                            }
                                        />
                                    )}
                                    {form.data.scopes.some(
                                        (scope) =>
                                            scope.startsWith('employees:') ||
                                            [
                                                'summary:hr:read',
                                                'summary:attendance:read',
                                                'summary:leaves:read',
                                                'summary:roster:read',
                                            ].includes(scope),
                                    ) && (
                                        <fieldset className="space-y-3 rounded border border-gray-300 p-4 dark:border-gray-600">
                                            <legend className="text-sm font-medium">
                                                ขอบเขตข้อมูล HR
                                            </legend>
                                            <p className="text-xs">
                                                ไม่ส่งเงินเดือน บัญชีธนาคาร
                                                ข้อมูลระบุตัวตน
                                                และข้อมูลสุขภาพผ่าน API
                                            </p>
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        form.data.access_policy
                                                            .hr_all_branches
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'access_policy',
                                                            {
                                                                ...form.data
                                                                    .access_policy,
                                                                hr_all_branches:
                                                                    event.target
                                                                        .checked,
                                                                hr_branch_ids:
                                                                    [],
                                                            },
                                                        )
                                                    }
                                                />
                                                อนุญาต HR ทุกสาขา
                                                รวมพนักงานที่ยังไม่ระบุสาขา
                                            </label>
                                            {!form.data.access_policy
                                                .hr_all_branches &&
                                                (hrBranches ?? []).map(
                                                    (branch) => (
                                                        <label
                                                            key={branch.id}
                                                            className="flex items-center gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={form.data.access_policy.hr_branch_ids.includes(
                                                                    branch.id,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    form.setData(
                                                                        'access_policy',
                                                                        {
                                                                            ...form
                                                                                .data
                                                                                .access_policy,
                                                                            hr_all_branches: false,
                                                                            hr_branch_ids:
                                                                                event
                                                                                    .target
                                                                                    .checked
                                                                                    ? [
                                                                                          ...form
                                                                                              .data
                                                                                              .access_policy
                                                                                              .hr_branch_ids,
                                                                                          branch.id,
                                                                                      ]
                                                                                    : form.data.access_policy.hr_branch_ids.filter(
                                                                                          (
                                                                                              value,
                                                                                          ) =>
                                                                                              value !==
                                                                                              branch.id,
                                                                                      ),
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            {branch.code} —{' '}
                                                            {branch.name}
                                                        </label>
                                                    ),
                                                )}
                                            {!form.data.access_policy
                                                .hr_all_branches &&
                                                !hrBranches?.length && (
                                                    <p className="text-sm">
                                                        ไม่มีสาขาที่เลือกได้
                                                        กรุณาตรวจโครงสร้างองค์กรและสิทธิ์
                                                    </p>
                                                )}
                                        </fieldset>
                                    )}
                                    <label className="block text-sm font-medium">
                                        อายุ key (วัน)
                                        <input
                                            type="number"
                                            required
                                            min={1}
                                            max={maxDays}
                                            className={control}
                                            value={form.data.lifetime_days}
                                            onChange={(event) =>
                                                form.setData(
                                                    'lifetime_days',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                    </label>
                                </>
                            ) : (
                                <>
                                    <label className="block text-sm font-medium">
                                        วันหมดอายุ (เวลาบนอุปกรณ์นี้)
                                        <input
                                            required
                                            type="datetime-local"
                                            className={control}
                                            value={form.data.expires_at}
                                            onChange={(event) =>
                                                form.setData(
                                                    'expires_at',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <p className="text-sm">
                                        สิทธิ์: {key?.scopes.join(', ')}{' '}
                                        หากต้องการเปลี่ยนสิทธิ์ ให้สร้าง key
                                        ใหม่
                                    </p>
                                </>
                            )}
                        </div>
                    )
                }
            </CreateEditDialog>
        </>
    );
}
