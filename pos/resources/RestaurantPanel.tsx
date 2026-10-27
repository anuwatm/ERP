import React, { useEffect, useState } from "react";

export type RestaurantState = {
  restaurant_enabled: boolean;
  restaurant_tables: { id: string; label: string; session_id: string | null }[];
  restaurant_lines: {
    id: string;
    name: string;
    status: string;
    channel: string;
    table_session_id: string | null;
  }[];
};

type Props = {
  state: RestaurantState & {
    staff: { role: string };
    catalog_version: string | null;
    items: {
      product_id: string;
      name: string;
      restaurant?: unknown;
      restaurant_variant?: {
        parent_product_id: string;
        label: string;
        size: string;
        modifiers: string[];
      };
    }[];
  };
  terminal: string;
  shiftOpen: boolean;
  busy: boolean;
  api: (path: string, data?: unknown) => Promise<any>;
  run: (action: () => Promise<void>) => Promise<void>;
  reload: () => Promise<void>;
  onReceipt: (receipt: any) => void;
};

export default function RestaurantPanel({
  state,
  terminal,
  shiftOpen,
  busy,
  api,
  run,
  reload,
  onReceipt,
}: Props) {
  const [label, setLabel] = useState("");
  const [session, setSession] = useState("");
  const [product, setProduct] = useState("");
  const [family, setFamily] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [orderKey, setOrderKey] = useState(() => crypto.randomUUID());
  const [selected, setSelected] = useState<string[]>([]);
  const [billKey, setBillKey] = useState(() => crypto.randomUUID());
  const [cash, setCash] = useState("");
  const [preview, setPreview] = useState<string | null>(null);
  const [cancelLine, setCancelLine] = useState("");
  const [reason, setReason] = useState("customer_cancel");
  const [waste, setWaste] = useState(false);
  const [password, setPassword] = useState("");
  const cashier = ["cashier", "manager"].includes(state.staff.role);
  const kitchen = ["kitchen", "manager"].includes(state.staff.role);
  useEffect(() => {
    setOrderKey(crypto.randomUUID());
  }, [terminal, session, product, quantity, state.catalog_version]);
  useEffect(() => {
    setPreview(null);
    setBillKey(crypto.randomUUID());
  }, [selected, terminal]);
  const transition = (id: string, action: string) =>
    run(async () => {
      await api(`/restaurant/lines/${id}/${action}`, {});
      await reload();
    });
  const statuses: Record<string, string> = {
    reserved: "รอส่งครัว",
    sent: "ส่งครัวแล้ว",
    acknowledged: "ครัวรับแล้ว",
    preparing: "กำลังเตรียม",
    ready: "พร้อมเสิร์ฟ",
    served: "เสิร์ฟแล้ว",
    cancelled: "ยกเลิก",
    wasted: "ของเสีย",
  };

  return (
    <section className="panel" aria-label="ร้านอาหาร">
      <h2>โต๊ะและครัว</h2>
      <p>
        กันวัตถุดิบเมื่อสั่ง ตัดเมื่อเริ่มเตรียม
        เลือกรายการที่เสิร์ฟแล้วเพื่อแยกบิล
      </p>
      {state.staff.role === "manager" && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              await api("/restaurant/tables", { label });
              setLabel("");
              await reload();
            });
          }}
        >
          <label>
            ชื่อโต๊ะ
            <input
              required
              maxLength={60}
              value={label}
              disabled={busy}
              onChange={(e) => setLabel(e.target.value)}
            />
          </label>
          <button disabled={busy}>เพิ่มโต๊ะ</button>
        </form>
      )}
      {cashier && (
        <>
          <div className="products">
            {state.restaurant_tables.map((table) => (
              <div className="panel" key={table.id}>
                <strong>{table.label}</strong>
                <p>{table.session_id ? "เปิดโต๊ะอยู่" : "ว่าง"}</p>
                <button
                  disabled={busy}
                  onClick={() =>
                    run(async () => {
                      if (table.session_id) setSession(table.session_id);
                      else {
                        const result = await api(
                          `/restaurant/tables/${table.id}/open`,
                          {},
                        );
                        setSession(result.id);
                        await reload();
                      }
                    })
                  }
                >
                  เลือกโต๊ะ {table.label}
                </button>
                {table.session_id && (
                  <button
                    disabled={busy}
                    onClick={() =>
                      run(async () => {
                        await api(
                          `/restaurant/sessions/${table.session_id}/close`,
                          {},
                        );
                        if (session === table.session_id) setSession("");
                        await reload();
                      })
                    }
                  >
                    ปิดโต๊ะ {table.label}
                  </button>
                )}
              </div>
            ))}
          </div>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              run(async () => {
                await api("/restaurant/orders", {
                  terminal_id: terminal,
                  request_key: orderKey,
                  catalog_version: state.catalog_version,
                  channel: session ? "dine_in" : "takeaway",
                  table_session_id: session || null,
                  items: [{ product_id: product, quantity: Number(quantity) }],
                });
                setOrderKey(crypto.randomUUID());
                await reload();
              });
            }}
          >
            <label>
              รับอาหาร
              <select
                disabled={busy}
                value={session}
                onChange={(e) => setSession(e.target.value)}
              >
                <option value="">กลับบ้าน</option>
                {state.restaurant_tables
                  .filter((t) => t.session_id)
                  .map((t) => (
                    <option key={t.id} value={t.session_id!}>
                      {t.label}
                    </option>
                  ))}
              </select>
            </label>
            <label>
              เมนูอาหาร
              <select
                aria-label="เมนูอาหาร"
                required
                disabled={busy}
                value={family}
                onChange={(e) => {
                  setFamily(e.target.value);
                  setProduct(e.target.value);
                }}
              >
                <option value="">เลือกเมนู</option>
                {state.items
                  .filter((p) => p.restaurant && !p.restaurant_variant)
                  .map((p) => (
                    <option key={p.product_id} value={p.product_id}>
                      {p.name}
                    </option>
                  ))}
              </select>
            </label>
            <label>
              จำนวนจาน
              <input
                type="number"
                min="1"
                max="50"
                required
                disabled={busy}
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
              />
            </label>
            {family && (
              <label>
                ขนาดและตัวเลือก
                <select
                  aria-label="ขนาดและตัวเลือก"
                  disabled={busy}
                  value={product}
                  onChange={(e) => setProduct(e.target.value)}
                >
                  <option value={family}>มาตรฐาน</option>
                  {state.items
                    .filter(
                      (p) => p.restaurant_variant?.parent_product_id === family,
                    )
                    .map((p) => (
                      <option key={p.product_id} value={p.product_id}>
                        {p.restaurant_variant!.label} ·{" "}
                        {p.restaurant_variant!.size} ·{" "}
                        {p.restaurant_variant!.modifiers.join(", ")}
                      </option>
                    ))}
                </select>
              </label>
            )}
            <button disabled={busy || !terminal || !state.catalog_version}>
              บันทึกออเดอร์อาหาร
            </button>
          </form>
        </>
      )}
      <ul>
        {state.restaurant_lines.map((line) => (
          <li key={line.id} className="panel">
            <strong>{line.name}</strong> ·{" "}
            {statuses[line.status] || line.status} ·{" "}
            {line.channel === "takeaway"
              ? "กลับบ้าน"
              : state.restaurant_tables.find(
                  (t) => t.session_id === line.table_session_id,
                )?.label || "โต๊ะ"}
            {cashier && line.status === "reserved" && (
              <button
                disabled={busy}
                onClick={() => transition(line.id, "sent")}
              >
                ส่งครัว
              </button>
            )}
            {kitchen && line.status === "sent" && (
              <button
                disabled={busy}
                onClick={() => transition(line.id, "acknowledged")}
              >
                ครัวรับออเดอร์
              </button>
            )}
            {kitchen && line.status === "acknowledged" && (
              <button
                disabled={busy}
                onClick={() => transition(line.id, "preparing")}
              >
                เริ่มเตรียม
              </button>
            )}
            {kitchen && line.status === "preparing" && (
              <button
                disabled={busy}
                onClick={() => transition(line.id, "ready")}
              >
                อาหารพร้อม
              </button>
            )}
            {cashier && line.status === "ready" && (
              <button
                disabled={busy}
                onClick={() => transition(line.id, "served")}
              >
                เสิร์ฟ / ส่งมอบ
              </button>
            )}
            {cashier && line.status === "served" && (
              <label>
                <input
                  type="checkbox"
                  disabled={busy}
                  checked={selected.includes(line.id)}
                  onChange={(e) =>
                    setSelected((old) =>
                      e.target.checked
                        ? [...old, line.id]
                        : old.filter((id) => id !== line.id),
                    )
                  }
                />
                เลือกชำระ {line.name}
              </label>
            )}
            {state.staff.role === "manager" &&
              !["cancelled", "wasted"].includes(line.status) && (
                <button
                  disabled={busy}
                  onClick={() => {
                    setCancelLine(line.id);
                    setWaste(false);
                    setPassword("");
                  }}
                >
                  ยกเลิก / ของเสีย
                </button>
              )}
          </li>
        ))}
      </ul>
      {cancelLine && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            const secret = password;
            setPassword("");
            run(async () => {
              await api("/restaurant/cancel", {
                line_id: cancelLine,
                reason,
                physical_waste: waste,
                manager_password: secret,
              });
              setCancelLine("");
              await reload();
            });
          }}
        >
          <h3>ยืนยันยกเลิกอาหาร</h3>
          <label>
            เหตุผลยกเลิก
            <select
              value={reason}
              disabled={busy}
              onChange={(e) => setReason(e.target.value)}
            >
              <option value="customer_cancel">ลูกค้ายกเลิก</option>
              <option value="preparation_error">เตรียมผิด</option>
              <option value="spoiled">เสียหาย</option>
            </select>
          </label>
          <label>
            <input
              type="checkbox"
              disabled={busy}
              checked={waste}
              onChange={(e) => setWaste(e.target.checked)}
            />
            ยืนยันมีวัตถุดิบสูญเสียจริง
          </label>
          <label>
            รหัสผ่านผู้จัดการยกเลิก
            <input
              required
              type="password"
              autoComplete="current-password"
              disabled={busy}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>
          <button disabled={busy}>ยืนยันยกเลิกอาหาร</button>
          <button
            type="button"
            disabled={busy}
            onClick={() => {
              setCancelLine("");
              setPassword("");
            }}
          >
            กลับ
          </button>
        </form>
      )}
      {cashier && (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              const receipt = await api("/restaurant/checkout", {
                terminal_id: terminal,
                request_key: billKey,
                kitchen_line_ids: selected,
                cash_tendered: cash,
              });
              onReceipt(receipt);
              setSelected([]);
              setCash("");
              setBillKey(crypto.randomUUID());
              await reload();
            });
          }}
        >
          <h3>ชำระรายการที่เลือก ({selected.length} จาน)</h3>
          <button
            type="button"
            disabled={busy || !selected.length}
            onClick={() =>
              run(async () => {
                const result = await api("/restaurant/bill-preview", {
                  terminal_id: terminal,
                  kitchen_line_ids: selected,
                });
                setPreview(result.total);
              })
            }
          >
            ตรวจยอดบิลอาหาร
          </button>
          {preview && <p role="status">ยอดบิลอาหารรวม VAT {preview}</p>}
          <label>
            รับเงินสดค่าอาหาร
            <input
              required
              inputMode="decimal"
              disabled={busy}
              value={cash}
              onChange={(e) => setCash(e.target.value)}
            />
          </label>
          <button disabled={busy || !shiftOpen || !selected.length || !preview}>
            ยืนยันรับเงินค่าอาหาร
          </button>
        </form>
      )}
    </section>
  );
}
