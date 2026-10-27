import { router, useForm } from '@inertiajs/react';

type Choice = { id: string; name: string; code?: string };
export type SetupOptions = {
    branches: Choice[];
    warehouses: Choice[];
    customers: Choice[];
    actors: Choice[];
    accounts: { code: string; name: string; account_type: string }[];
    products: Choice[];
    lots: (Choice & { product_id: string })[];
    boms: (Choice & { product_id: string })[];
};
export default function PosSetup({
    options,
    instances,
}: {
    options: SetupOptions;
    instances: Choice[];
}) {
    const setup = useForm({
        id: crypto.randomUUID() as string,
        name: '',
        branch_id: '',
        branch_code: '',
        warehouse_id: '',
        customer_id: '',
        actor_id: '',
        business_type: 'retail',
        terminals_text: '',
        key_id: '',
        public_key: '',
        recovery_generation: crypto.randomUUID() as string,
        account_codes: {
            drawer: '',
            receivable: '',
            revenue: '',
            vat: '',
            inventory: '',
            cogs: '',
            cash_clearing: '',
            waste: '',
        } as Record<string, string>,
    });
    const staff = useForm({
        instance: '',
        source_staff_id: '',
        role: 'cashier',
        active: true,
        pharmacist_qualified: false,
    });
    const recovery = useForm({ instance: '', checkpoint_id: '', reason: '' });
    const capture = useForm({ instance: '', after_sequence: 0 });
    const key = useForm({
        instance: '',
        key_id: '',
        operation: 'add',
        public_key: '',
        reason: '',
    });
    const catalog = useForm({
        instance: '',
        version: '',
        tax_rate: '',
        valid_until: '',
        selected: [] as string[],
        online: [] as string[],
        recipes: {} as Record<string, string>,
    });
    const grant = useForm({
        instance: '',
        product_id: '',
        lot_id: '',
        quantity: '',
    });
    const select = (
        label: string,
        choices: Choice[],
        value: string,
        change: (value: string) => void,
    ) => (
        <label className="block">
            {label}
            <select
                aria-label={label}
                required
                className="block w-full rounded border p-2"
                value={value}
                onChange={(e) => change(e.target.value)}
            >
                <option value="">เลือก</option>
                {choices.map((c) => (
                    <option key={c.id} value={c.id}>
                        {c.name}
                    </option>
                ))}
            </select>
        </label>
    );
    return (
        <section className="space-y-6 rounded bg-white p-6 shadow">
            <h3 className="font-semibold">ตั้งค่า POS instance — local</h3>
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    setup.transform((d) => ({
                        ...d,
                        terminals: d.terminals_text
                            .split('\n')
                            .filter((s) => s.trim())
                            .map((row) => {
                                const [id, code] = row.trim().split(/\s+/);
                                return { id, code };
                            }),
                    }));
                    setup.post('/pos-integration/instances', {
                        preserveScroll: true,
                    });
                }}
            >
                <p>
                    ลงทะเบียนแบบ paused ก่อน ตรวจให้
                    UUID/รหัสสาขา/Terminal/generation ตรงกับ POS แยก DB
                    และกุญแจส่วนตัวไว้ที่ POS เท่านั้น
                </p>
                <label className="block">
                    Instance UUID
                    <input
                        required
                        className="block w-full rounded border p-2"
                        value={setup.data.id}
                        onChange={(e) => setup.setData('id', e.target.value)}
                    />
                </label>
                <label className="block">
                    ชื่อ instance
                    <input
                        required
                        maxLength={255}
                        className="block w-full rounded border p-2"
                        value={setup.data.name}
                        onChange={(e) => setup.setData('name', e.target.value)}
                    />
                </label>
                {select(
                    'สาขา ERP',
                    options.branches,
                    setup.data.branch_id,
                    (id) => {
                        setup.setData('branch_id', id);
                        setup.setData(
                            'branch_code',
                            options.branches.find((b) => b.id === id)?.code ||
                                '',
                        );
                    },
                )}
                <label className="block">
                    รหัสสาขา POS
                    <input
                        required
                        maxLength={30}
                        className="block w-full rounded border p-2"
                        value={setup.data.branch_code}
                        onChange={(e) =>
                            setup.setData('branch_code', e.target.value)
                        }
                    />
                </label>
                {select(
                    'คลังสาขา',
                    options.warehouses,
                    setup.data.warehouse_id,
                    (v) => setup.setData('warehouse_id', v),
                )}
                {select(
                    'ลูกค้าสำหรับยอดหน้าร้าน',
                    options.customers,
                    setup.data.customer_id,
                    (v) => setup.setData('customer_id', v),
                )}
                {select(
                    'ผู้บันทึก ERP Connector',
                    options.actors,
                    setup.data.actor_id,
                    (v) => setup.setData('actor_id', v),
                )}
                <label className="block">
                    กิจการ
                    <select
                        className="block w-full rounded border p-2"
                        value={setup.data.business_type}
                        onChange={(e) =>
                            setup.setData('business_type', e.target.value)
                        }
                    >
                        <option value="retail">Retail</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="pharmacy">Pharmacy</option>
                    </select>
                </label>
                <label className="block">
                    Terminal แต่ละบรรทัด: UUID รหัส
                    <textarea
                        required
                        className="block w-full rounded border p-2"
                        value={setup.data.terminals_text}
                        onChange={(e) =>
                            setup.setData('terminals_text', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    Signing key ID
                    <input
                        required
                        maxLength={100}
                        className="block w-full rounded border p-2"
                        value={setup.data.key_id}
                        onChange={(e) =>
                            setup.setData('key_id', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    RSA public key PEM
                    <textarea
                        required
                        className="block w-full rounded border p-2"
                        value={setup.data.public_key}
                        onChange={(e) =>
                            setup.setData('public_key', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    Recovery generation
                    <input
                        required
                        className="block w-full rounded border p-2"
                        value={setup.data.recovery_generation}
                        onChange={(e) =>
                            setup.setData('recovery_generation', e.target.value)
                        }
                    />
                </label>
                {Object.entries({
                    drawer: 'asset',
                    receivable: 'asset',
                    revenue: 'revenue',
                    vat: 'liability',
                    inventory: 'asset',
                    cogs: 'expense',
                    cash_clearing: 'asset',
                    waste: 'expense',
                }).map(([key, type]) => (
                    <label className="block" key={key}>
                        บัญชี {key}
                        <select
                            required={!['cash_clearing', 'waste'].includes(key)}
                            aria-label={`บัญชี ${key}`}
                            className="block w-full rounded border p-2"
                            value={setup.data.account_codes[key]}
                            onChange={(e) =>
                                setup.setData('account_codes', {
                                    ...setup.data.account_codes,
                                    [key]: e.target.value,
                                })
                            }
                        >
                            <option value="">เลือกบัญชี {type}</option>
                            {options.accounts
                                .filter((a) => a.account_type === type)
                                .map((a) => (
                                    <option key={a.code} value={a.code}>
                                        {a.code} · {a.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                ))}
                {Object.values(setup.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={setup.processing}
                    className="rounded border px-4 py-2"
                >
                    ลงทะเบียน instance
                </button>
            </form>
            <div className="space-y-2">
                {instances.map((i) => (
                    <button
                        key={i.id}
                        className="mr-2 rounded border px-4 py-2"
                        onClick={() =>
                            router.post(`/pos-integration/${i.id}/activate`)
                        }
                    >
                        Activate {i.name}
                    </button>
                ))}
            </div>
            <form
                className="space-y-3 border-t pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    catalog.transform((d) => ({
                        ...d,
                        product_ids: d.selected,
                        online_product_ids: d.online.filter((id) =>
                            d.selected.includes(id),
                        ),
                        valid_until: new Date(d.valid_until).toISOString(),
                        recipes: d.selected.map((product_id) => ({
                            product_id,
                            bom_id: d.recipes[product_id] || null,
                        })),
                    }));
                    catalog.post(
                        `/pos-integration/${catalog.data.instance}/catalog`,
                        { preserveScroll: true },
                    );
                }}
            >
                <h4 className="font-semibold">เผยแพร่ catalog และสูตร</h4>
                {select(
                    'Instance สำหรับ catalog',
                    instances,
                    catalog.data.instance,
                    (v) => catalog.setData('instance', v),
                )}
                <label className="block">
                    Catalog version
                    <input
                        required
                        maxLength={80}
                        className="block w-full rounded border p-2"
                        value={catalog.data.version}
                        onChange={(e) =>
                            catalog.setData('version', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    อัตรา VAT ใน catalog
                    <input
                        required
                        inputMode="decimal"
                        className="block w-full rounded border p-2"
                        value={catalog.data.tax_rate}
                        onChange={(e) =>
                            catalog.setData('tax_rate', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    Catalog ใช้ได้ถึง
                    <input
                        required
                        type="datetime-local"
                        className="block w-full rounded border p-2"
                        value={catalog.data.valid_until}
                        onChange={(e) =>
                            catalog.setData('valid_until', e.target.value)
                        }
                    />
                </label>
                <div className="max-h-96 space-y-3 overflow-y-auto">
                    {options.products.map((p) => (
                        <div key={p.id} className="rounded border p-3">
                            <label>
                                <input
                                    type="checkbox"
                                    checked={catalog.data.selected.includes(
                                        p.id,
                                    )}
                                    onChange={(e) =>
                                        catalog.setData(
                                            'selected',
                                            e.target.checked
                                                ? [
                                                      ...catalog.data.selected,
                                                      p.id,
                                                  ]
                                                : catalog.data.selected.filter(
                                                      (id) => id !== p.id,
                                                  ),
                                        )
                                    }
                                />{' '}
                                {p.name}
                            </label>
                            {catalog.data.selected.includes(p.id) && (
                                <>
                                    <label className="ml-4">
                                        <input
                                            type="checkbox"
                                            checked={catalog.data.online.includes(
                                                p.id,
                                            )}
                                            onChange={(e) =>
                                                catalog.setData(
                                                    'online',
                                                    e.target.checked
                                                        ? [
                                                              ...catalog.data
                                                                  .online,
                                                              p.id,
                                                          ]
                                                        : catalog.data.online.filter(
                                                              (id) =>
                                                                  id !== p.id,
                                                          ),
                                                )
                                            }
                                        />{' '}
                                        แสดงในเมนูออนไลน์ (Pharmacy ยังไม่เปิด)
                                    </label>
                                    <label className="block">
                                        สูตรอาหาร {p.name}
                                        <select
                                            className="block w-full rounded border p-2"
                                            value={
                                                catalog.data.recipes[p.id] || ''
                                            }
                                            onChange={(e) =>
                                                catalog.setData('recipes', {
                                                    ...catalog.data.recipes,
                                                    [p.id]: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">
                                                สินค้าสำเร็จรูป ไม่ใช้ BOM
                                            </option>
                                            {options.boms
                                                .filter(
                                                    (b) =>
                                                        b.product_id === p.id,
                                                )
                                                .map((b) => (
                                                    <option
                                                        key={b.id}
                                                        value={b.id}
                                                    >
                                                        {b.name}
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                </>
                            )}
                        </div>
                    ))}
                </div>
                {Object.values(catalog.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={
                        catalog.processing || !catalog.data.selected.length
                    }
                    className="rounded border px-4 py-2"
                >
                    เตรียม catalog เพื่อ sync
                </button>
            </form>
            <form
                className="space-y-3 border-t pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    grant.transform((d) => ({
                        ...d,
                        lot_id: d.lot_id || null,
                    }));
                    grant.post(
                        `/pos-integration/${grant.data.instance}/grants`,
                        { preserveScroll: true },
                    );
                }}
            >
                <h4 className="font-semibold">แบ่ง stock quota ให้ POS</h4>
                {select(
                    'Instance สำหรับ quota',
                    instances,
                    grant.data.instance,
                    (v) => grant.setData('instance', v),
                )}
                {select(
                    'สินค้า quota',
                    options.products,
                    grant.data.product_id,
                    (v) => {
                        grant.setData('product_id', v);
                        grant.setData('lot_id', '');
                    },
                )}
                <label className="block">
                    Lot quota
                    <select
                        className="block w-full rounded border p-2"
                        value={grant.data.lot_id}
                        onChange={(e) =>
                            grant.setData('lot_id', e.target.value)
                        }
                    >
                        <option value="">ไม่มี lot</option>
                        {options.lots
                            .filter(
                                (l) => l.product_id === grant.data.product_id,
                            )
                            .map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.name}
                                </option>
                            ))}
                    </select>
                </label>
                <label className="block">
                    ปริมาณ quota ในหน่วยฐาน
                    <input
                        required
                        inputMode="decimal"
                        className="block w-full rounded border p-2"
                        value={grant.data.quantity}
                        onChange={(e) =>
                            grant.setData('quantity', e.target.value)
                        }
                    />
                </label>
                {Object.values(grant.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={grant.processing}
                    className="rounded border px-4 py-2"
                >
                    กัน stock และเตรียม quota
                </button>
            </form>
            <form
                className="space-y-3 border-t pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    staff.post(
                        `/pos-integration/${staff.data.instance}/authorities`,
                        { preserveScroll: true },
                    );
                }}
            >
                <h4 className="font-semibold">สิทธิ์ source staff</h4>
                {select(
                    'Instance สำหรับสิทธิ์',
                    instances,
                    staff.data.instance,
                    (v) => staff.setData('instance', v),
                )}
                <label className="block">
                    Staff UUID จาก POS
                    <input
                        required
                        className="block w-full rounded border p-2"
                        value={staff.data.source_staff_id}
                        onChange={(e) =>
                            staff.setData('source_staff_id', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    บทบาท
                    <select
                        className="block w-full rounded border p-2"
                        value={staff.data.role}
                        onChange={(e) => staff.setData('role', e.target.value)}
                    >
                        {['cashier', 'manager', 'kitchen', 'pharmacist'].map(
                            (r) => (
                                <option key={r}>{r}</option>
                            ),
                        )}
                    </select>
                </label>
                <label className="block">
                    <input
                        type="checkbox"
                        checked={staff.data.active}
                        onChange={(e) =>
                            staff.setData('active', e.target.checked)
                        }
                    />{' '}
                    Active
                </label>
                <label className="block">
                    <input
                        type="checkbox"
                        checked={staff.data.pharmacist_qualified}
                        onChange={(e) =>
                            staff.setData(
                                'pharmacist_qualified',
                                e.target.checked,
                            )
                        }
                    />{' '}
                    ยืนยันตรวจคุณสมบัติเภสัชกรแล้ว
                </label>
                {Object.values(staff.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={staff.processing}
                    className="rounded border px-4 py-2"
                >
                    บันทึกสิทธิ์พนักงาน
                </button>
            </form>
            <form
                className="space-y-3 border-t pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    recovery.post(
                        `/pos-integration/${recovery.data.instance}/recover`,
                        { preserveScroll: true },
                    );
                }}
            >
                <h4 className="font-semibold">
                    ปลด recovery fence หลังตรวจหลักฐาน
                </h4>
                <p>
                    เก็บ checkpoint ใหม่จากลำดับ 0 แล้วใช้ through
                    ของหน้าก่อนเป็นลำดับเริ่มต้นหน้าถัดไป จน through เท่ากับ
                    high watermark ก่อนปลด fence
                </p>
                <label className="block">
                    ลำดับเริ่มต้น checkpoint
                    <input
                        className="block w-full rounded border p-2"
                        type="number"
                        min="0"
                        value={capture.data.after_sequence}
                        onChange={(e) =>
                            capture.setData(
                                'after_sequence',
                                Number(e.target.value),
                            )
                        }
                    />
                </label>
                <button
                    type="button"
                    disabled={!recovery.data.instance || capture.processing}
                    className="rounded border px-4 py-2"
                    onClick={() =>
                        capture.post(
                            `/pos-integration/${recovery.data.instance}/checkpoint`,
                            { preserveScroll: true },
                        )
                    }
                >
                    เก็บ source checkpoint
                </button>
                {Object.values(capture.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                {select(
                    'Instance สำหรับ recovery',
                    instances,
                    recovery.data.instance,
                    (v) => recovery.setData('instance', v),
                )}
                <label className="block">
                    Source checkpoint UUID หน้าสุดท้ายที่สร้างใหม่หลัง fence
                    <input
                        required
                        className="block w-full rounded border p-2"
                        value={recovery.data.checkpoint_id}
                        onChange={(e) =>
                            recovery.setData('checkpoint_id', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    เหตุผล recovery
                    <input
                        required
                        maxLength={500}
                        className="block w-full rounded border p-2"
                        value={recovery.data.reason}
                        onChange={(e) =>
                            recovery.setData('reason', e.target.value)
                        }
                    />
                </label>
                {Object.values(recovery.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={recovery.processing}
                    className="rounded border px-4 py-2"
                >
                    ตรวจหลักฐานและปลด fence
                </button>
            </form>
            <form
                className="space-y-3 border-t pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    key.post(
                        `/pos-integration/${key.data.instance}/signing-keys`,
                        { preserveScroll: true },
                    );
                }}
            >
                <h4 className="font-semibold">
                    เพิ่ม / เพิกถอน verification key
                </h4>
                {select(
                    'Instance สำหรับกุญแจ',
                    instances,
                    key.data.instance,
                    (v) => key.setData('instance', v),
                )}
                <p>
                    เพิ่ม key ใหม่ก่อนสลับ POS signer เก็บ key เดิมไว้ตรวจ
                    backlog จนรับครบ การเพิกถอนจะพัก events
                    ที่ยังยืนยันไม่ได้และคง quota ไว้
                </p>
                <label>
                    Key ID
                    <input
                        required
                        maxLength={100}
                        className="block w-full rounded border p-2"
                        value={key.data.key_id}
                        onChange={(e) => key.setData('key_id', e.target.value)}
                    />
                </label>
                <label>
                    การจัดการ key
                    <select
                        className="block w-full rounded border p-2"
                        value={key.data.operation}
                        onChange={(e) =>
                            key.setData('operation', e.target.value)
                        }
                    >
                        <option value="add">เพิ่ม public key</option>
                        <option value="revoke">เพิกถอน</option>
                    </select>
                </label>
                {key.data.operation === 'add' && (
                    <label>
                        Public key PEM ใหม่
                        <textarea
                            required
                            className="block w-full rounded border p-2"
                            value={key.data.public_key}
                            onChange={(e) =>
                                key.setData('public_key', e.target.value)
                            }
                        />
                    </label>
                )}
                <label>
                    เหตุผล key change
                    <input
                        required
                        maxLength={500}
                        className="block w-full rounded border p-2"
                        value={key.data.reason}
                        onChange={(e) => key.setData('reason', e.target.value)}
                    />
                </label>
                {Object.values(key.errors).map((error, i) => (
                    <p role="alert" className="text-red-700" key={i}>
                        {error}
                    </p>
                ))}
                <button
                    disabled={key.processing}
                    className="rounded border px-4 py-2"
                >
                    บันทึกสิทธิ์กุญแจ
                </button>
            </form>
        </section>
    );
}
