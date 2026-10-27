import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import "./pos.css";
import RestaurantPanel, { RestaurantState } from "./RestaurantPanel";
import ReturnInspectionPanel from "./ReturnInspectionPanel";
import TaxDocumentsPanel from "./TaxDocumentsPanel";

type Product = {
  product_id: string;
  name: string;
  barcode?: string;
  unit: string;
  price: string;
  tax_rate: string;
  active: boolean;
  max_manager_discount_pct?: string;
  restaurant?: unknown;
  restaurant_variant?: {
    parent_product_id: string;
    label: string;
    size: string;
    modifiers: string[];
  };
  pharmacy?: {
    requires_pharmacist: boolean;
    base_step: string;
    units: {
      unit: string;
      price: string;
      factor: string;
      conversion_version: string;
    }[];
  };
};
type State = RestaurantState & {
  staff: { name: string; role: string };
  terminals: { id: string; code: string }[];
  catalog_version: string | null;
  items: Product[];
  shifts: { id: string; terminal_id: string }[];
  pending_sync: number;
  public_menu_enabled: boolean;
  refunds_enabled: boolean;
  cash_movements_enabled: boolean;
  discounts_enabled: boolean;
  return_inspections_enabled: boolean;
  tax_documents_enabled: boolean;
  refundable_sales: {
    id: string;
    receipt_no: string;
    lines: { line_id: string; name: string; unit: string; remaining: string }[];
  }[];
  customer_requests: {
    id: string;
    status: string;
    catalog_version: string;
    items: { product_id: string; quantity: string; unit: string }[];
  }[];
};
type Receipt = {
  receipt_no: string;
  total: string;
  cash_tendered: string;
  change: string;
  lines: { name: string; quantity: string; total: string }[];
};

async function api(path: string, data?: unknown) {
  const response = await fetch(path, {
    method: data === undefined ? "GET" : "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN":
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
          ?.content || "",
    },
    ...(data === undefined ? {} : { body: JSON.stringify(data) }),
  });
  const result = await response.json();
  if (!response.ok)
    throw new Error(
      Object.values(result.errors || {})
        .flat()
        .join(" ") ||
        result.message ||
        "ทำรายการไม่สำเร็จ",
    );
  return result;
}

function App() {
  const [state, setState] = useState<State | null>(null),
    [email, setEmail] = useState(""),
    [password, setPassword] = useState("");
  const [terminal, setTerminal] = useState(""),
    [search, setSearch] = useState(""),
    [cart, setCart] = useState<Record<string, string>>({});
  const [cash, setCash] = useState(""),
    [opening, setOpening] = useState("0.00"),
    [counted, setCounted] = useState("");
  const [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [receipt, setReceipt] = useState<Receipt | null>(null);
  const [requestKey, setRequestKey] = useState(() => crypto.randomUUID());
  const [kitchenSync, setKitchenSync] = useState("");
  const [unitChoices, setUnitChoices] = useState<Record<string, string>>({});
  const [customerRequest, setCustomerRequest] = useState<string | null>(null);
  const [refundOrder, setRefundOrder] = useState("");
  const [refundQuantities, setRefundQuantities] = useState<
    Record<string, string>
  >({});
  const [refundPassword, setRefundPassword] = useState("");
  const [refundReason, setRefundReason] = useState("customer_return");
  const [goodsReturned, setGoodsReturned] = useState(false);
  const [refundKey, setRefundKey] = useState(() => crypto.randomUUID());
  const [refundResult, setRefundResult] = useState("");
  const [cashMoveDirection, setCashMoveDirection] = useState("in");
  const [cashMoveAmount, setCashMoveAmount] = useState("");
  const [cashMoveReference, setCashMoveReference] = useState("");
  const [cashMovePassword, setCashMovePassword] = useState("");
  const [cashMoveKey, setCashMoveKey] = useState(() => crypto.randomUUID());
  const [cashMoveResult, setCashMoveResult] = useState("");
  const [discountAmounts, setDiscountAmounts] = useState<
    Record<string, string>
  >({});
  const [discountManagerEmail, setDiscountManagerEmail] = useState("");
  const [discountPassword, setDiscountPassword] = useState("");
  const [discountKey, setDiscountKey] = useState(() => crypto.randomUUID());
  const [discountApproval, setDiscountApproval] = useState<{
    id: string;
    discounted_total: string;
  } | null>(null);
  useEffect(() => {
    setDiscountApproval(null);
    setDiscountKey(crypto.randomUUID());
  }, [cart, unitChoices, terminal, state?.catalog_version, discountAmounts]);
  const [menuLink, setMenuLink] = useState<{
    id: string;
    menu_path: string;
    qr_data_uri: string;
  } | null>(null);
  const [menuSession, setMenuSession] = useState("");
  const reload = async () => {
    const data = await api("/state");
    setState(data);
    setTerminal((old) => old || data.terminals[0]?.id || "");
  };
  useEffect(() => {
    reload().catch(() => setState(null));
  }, []);
  useEffect(() => {
    if (!state?.restaurant_enabled || busy) return;
    const controller = new AbortController();
    let timer: ReturnType<typeof setTimeout>;
    const poll = async () => {
      try {
        if (document.visibilityState === "visible") {
          const response = await fetch("/state", {
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-POS-Passive": "1" },
            signal: controller.signal,
          });
          if (!response.ok)
            throw new Error(
              "จอครัวเชื่อมต่อไม่ได้ กรุณาตรวจเครือข่ายหรือเข้าสู่ระบบใหม่",
            );
          const data = await response.json();
          if (!controller.signal.aborted) {
            setState(data);
            setKitchenSync("จอครัวอัปเดตอัตโนมัติทุก 5 วินาที");
          }
        }
      } catch (error) {
        if (!controller.signal.aborted)
          setKitchenSync(
            error instanceof Error ? error.message : "จอครัวยังไม่อัปเดต",
          );
      } finally {
        if (!controller.signal.aborted) timer = setTimeout(poll, 5000);
      }
    };
    timer = setTimeout(poll, 5000);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [state?.restaurant_enabled, busy]);
  const run = async (fn: () => Promise<void>) => {
    setBusy(true);
    setError("");
    try {
      await fn();
    } catch (e) {
      setError(
        e instanceof Error
          ? e.message
          : "ไม่ทราบผลรายการ กรุณาตรวจสถานะก่อนรับเงินใหม่",
      );
    } finally {
      setBusy(false);
    }
  };
  const shift = state?.shifts.find((s) => s.terminal_id === terminal);
  const products =
    state?.items.filter(
      (p) =>
        p.active &&
        `${p.name} ${p.barcode || ""}`
          .toLowerCase()
          .includes(search.toLowerCase()),
    ) || [];
  const items = Object.entries(cart).filter(([, q]) => Number(q) > 0);
  const discountRequested = items.some(
    ([id]) => discountAmounts[id]?.trim() && Number(discountAmounts[id]) !== 0,
  );
  return (
    <main>
      <header>
        <div>
          <span className="eyebrow">COMPANY OS</span>
          <h1>จุดขายเงินสด</h1>
        </div>
        {state && (
          <div>
            {state.staff.name}
            <button
              disabled={busy}
              onClick={() =>
                run(async () => {
                  await api("/logout", {});
                  location.reload();
                })
              }
            >
              ออกจากระบบ
            </button>
          </div>
        )}
      </header>
      <aside className="notice">
        ระบบอยู่ระหว่างพัฒนา · e-Tax และใบกำกับภาษีรอตั้งค่า ·
        เอกสารตัวอย่างไม่ใช่ใบกำกับภาษี
      </aside>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {!state ? (
        <form
          className="panel login"
          onSubmit={(e) => {
            e.preventDefault();
            run(async () => {
              await api("/login", { email, password });
              setPassword("");
              location.reload();
            });
          }}
        >
          <h2>เข้าสู่ระบบพนักงาน POS</h2>
          <label>
            อีเมล
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="username"
            />
          </label>
          <label>
            รหัสผ่าน
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
            />
          </label>
          <button className="primary" disabled={busy}>
            เข้าสู่ระบบ
          </button>
        </form>
      ) : (
        <>
          <section className="toolbar panel">
            <label>
              Terminal
              <select
                value={terminal}
                onChange={(e) => setTerminal(e.target.value)}
              >
                {state.terminals.map((t) => (
                  <option value={t.id} key={t.id}>
                    {t.code}
                  </option>
                ))}
              </select>
            </label>
            <span>{shift ? "กะเปิดอยู่" : "ยังไม่เปิดกะ"}</span>
            <span>รอ ERP: {state.pending_sync} รายการ</span>
            <button disabled={busy} onClick={() => run(reload)}>
              ตรวจสถานะ
            </button>
          </section>
          {state.restaurant_enabled && (
            <RestaurantPanel
              state={state}
              terminal={terminal}
              shiftOpen={!!shift}
              busy={busy}
              api={api}
              run={run}
              reload={reload}
              onReceipt={setReceipt}
            />
          )}
          {state.restaurant_enabled && (
            <p role="status">
              {kitchenSync || "กำลังเชื่อมต่อจอครัวอัตโนมัติ…"}
            </p>
          )}
          {state.return_inspections_enabled && (
            <ReturnInspectionPanel
              busy={busy}
              api={api}
              run={run}
              reload={reload}
            />
          )}
          {state.tax_documents_enabled && (
            <TaxDocumentsPanel busy={busy} api={api} run={run} />
          )}
          {state.cash_movements_enabled && (
            <form
              className="panel"
              onSubmit={(e) => {
                e.preventDefault();
                run(async () => {
                  const result = await api("/local-cash-movements", {
                    terminal_id: terminal,
                    request_key: cashMoveKey,
                    direction: cashMoveDirection,
                    amount: cashMoveAmount,
                    reference: cashMoveReference,
                    reason:
                      cashMoveDirection === "in" ? "float_added" : "cash_drop",
                    manager_password: cashMovePassword,
                  });
                  setCashMovePassword("");
                  setCashMoveAmount("");
                  setCashMoveReference("");
                  setCashMoveKey(crypto.randomUUID());
                  setCashMoveResult(
                    `${result.direction === "in" ? "นำเงินเข้า" : "นำเงินออก"} ${result.amount} · อ้างอิง ${result.cash_movement_id}`,
                  );
                  await reload();
                });
              }}
            >
              <h2>Cash In-Out จำลอง — Manager</h2>
              <p>บันทึกเงินเข้า/ออกลิ้นชัก แยกจากยอดขายและ VAT</p>
              <label>
                ทิศทางเงิน
                <select
                  value={cashMoveDirection}
                  onChange={(e) => setCashMoveDirection(e.target.value)}
                >
                  <option value="in">นำเงินเข้าลิ้นชัก</option>
                  <option value="out">นำเงินออกลิ้นชัก</option>
                </select>
              </label>
              <label>
                จำนวนเงิน Cash In-Out
                <input
                  required
                  inputMode="decimal"
                  value={cashMoveAmount}
                  onChange={(e) => setCashMoveAmount(e.target.value)}
                />
              </label>
              <label>
                เอกสารอ้างอิง Cash In-Out
                <input
                  required
                  maxLength={100}
                  value={cashMoveReference}
                  onChange={(e) => setCashMoveReference(e.target.value)}
                />
              </label>
              <label>
                ยืนยันรหัสผ่าน Cash In-Out
                <input
                  required
                  type="password"
                  autoComplete="current-password"
                  value={cashMovePassword}
                  onChange={(e) => setCashMovePassword(e.target.value)}
                />
              </label>
              <button disabled={busy || !shift}>บันทึก Cash In-Out</button>
              {cashMoveResult && <p role="status">{cashMoveResult}</p>}
            </form>
          )}
          {state.refunds_enabled && (
            <form
              className="panel"
              onSubmit={(e) => {
                e.preventDefault();
                run(async () => {
                  const result = await api("/local-refunds", {
                    order_id: refundOrder,
                    terminal_id: terminal,
                    request_key: refundKey,
                    reason: refundReason,
                    manager_password: refundPassword,
                    items: Object.entries(refundQuantities)
                      .filter(([, q]) => Number(q) > 0)
                      .map(([line_id, quantity]) => ({
                        line_id,
                        quantity,
                        returned_quantity: goodsReturned ? quantity : "0",
                      })),
                  });
                  setRefundPassword("");
                  setRefundQuantities({});
                  setRefundKey(crypto.randomUUID());
                  setRefundResult(
                    `คืนเงิน ${result.total} · อ้างอิง ${result.refund_id}`,
                  );
                  await reload();
                });
              }}
            >
              <h2>คืนเงินจำลอง — Manager</h2>
              <p>
                อ้างอิงราคาขายเดิม ของที่รับคืนรอตรวจ ไม่เพิ่ม Stock พร้อมขาย
              </p>
              <label>
                บิลต้นฉบับ
                <select
                  required
                  value={refundOrder}
                  onChange={(e) => {
                    setRefundOrder(e.target.value);
                    setRefundQuantities({});
                  }}
                >
                  <option value="">เลือกบิล</option>
                  {state.refundable_sales.map((sale) => (
                    <option key={sale.id} value={sale.id}>
                      {sale.receipt_no}
                    </option>
                  ))}
                </select>
              </label>
              {state.refundable_sales
                .find((sale) => sale.id === refundOrder)
                ?.lines.map((line) => (
                  <label key={line.line_id}>
                    {line.name} · คืนได้ {line.remaining} {line.unit}
                    <input
                      aria-label={`จำนวนคืน ${line.name}`}
                      inputMode="decimal"
                      value={refundQuantities[line.line_id] || ""}
                      onChange={(e) =>
                        setRefundQuantities({
                          ...refundQuantities,
                          [line.line_id]: e.target.value,
                        })
                      }
                    />
                  </label>
                ))}
              <label>
                เหตุผล
                <select
                  value={refundReason}
                  onChange={(e) => setRefundReason(e.target.value)}
                >
                  <option value="customer_return">ลูกค้าคืนสินค้า</option>
                  <option value="sale_error">บันทึกการขายผิด</option>
                  <option value="damaged">สินค้าเสียหาย</option>
                </select>
              </label>
              <label>
                <input
                  type="checkbox"
                  checked={goodsReturned}
                  onChange={(e) => setGoodsReturned(e.target.checked)}
                />
                รับสินค้ากลับเข้าพื้นที่รอตรวจ
              </label>
              <label>
                ยืนยันรหัสผ่าน Manager
                <input
                  required
                  type="password"
                  autoComplete="current-password"
                  value={refundPassword}
                  onChange={(e) => setRefundPassword(e.target.value)}
                />
              </label>
              <button disabled={busy || !shift || !refundOrder}>
                ยืนยันจ่ายคืนเงินสด
              </button>
              {refundResult && <p role="status">{refundResult}</p>}
              <p>บิลบันทึกผิดทั้งใบ: คืนเงินเต็มจำนวน เก็บต้นฉบับและไม่คืน stock อัตโนมัติ บิลคืนบางส่วนแล้วให้ใช้คืนเงินตามรายการ</p>
              <button type="button" disabled={busy || !shift || !refundOrder} onClick={() => run(async () => {
                const result = await api('/local-voids', { order_id: refundOrder, terminal_id: terminal, request_key: refundKey, manager_password: refundPassword });
                setRefundPassword(''); setRefundQuantities({}); setRefundKey(crypto.randomUUID()); setRefundResult(`ยกเลิกบิลและคืนเงินเต็มจำนวน ${result.total} · ${result.refund_id}`); await reload();
              })}>ยกเลิกบิลและคืนเงินเต็มจำนวน</button>
            </form>
          )}
          {state.public_menu_enabled && (
            <section className="panel">
              <h2>คำขอจากเมนู</h2>
              <p>
                การตรวจรับยังไม่รับชำระและไม่กันสินค้า ยืนยันสินค้าพร้อมขายตอน
                checkout
              </p>
              {state.restaurant_enabled && state.staff.role === "manager" && (
                <label>
                  QR สำหรับโต๊ะ
                  <select
                    aria-label="QR สำหรับโต๊ะ"
                    value={menuSession}
                    onChange={(e) => setMenuSession(e.target.value)}
                  >
                    <option value="">เมนูกลับบ้าน</option>
                    {state.restaurant_tables
                      .filter((t) => t.session_id)
                      .map((t) => (
                        <option key={t.id} value={t.session_id!}>
                          {t.label}
                        </option>
                      ))}
                  </select>
                </label>
              )}
              {state.staff.role === "manager" && (
                <button
                  disabled={busy}
                  onClick={() =>
                    run(async () =>
                      setMenuLink(
                        await api("/menu-tokens", {
                          minutes: 60,
                          ...(state.restaurant_enabled && menuSession
                            ? { table_session_id: menuSession }
                            : {}),
                        }),
                      ),
                    )
                  }
                >
                  สร้าง QR เมนูจำลอง
                </button>
              )}
              {menuLink && (
                <div>
                  <img
                    alt="QR เข้าเมนูสินค้าทดสอบ"
                    width="240"
                    height="240"
                    src={menuLink.qr_data_uri}
                  />
                  <a href={menuLink.menu_path} target="_blank" rel="noreferrer">
                    เปิดเมนูจำลอง
                  </a>
                  <button
                    onClick={() =>
                      run(async () => {
                        await api(`/menu-tokens/${menuLink.id}/revoke`, {});
                        setMenuLink(null);
                      })
                    }
                  >
                    ยกเลิก QR นี้
                  </button>
                </div>
              )}
              {state.customer_requests?.map((r) => (
                <div className="panel" key={r.id}>
                  <span>
                    {r.id} · {r.status}
                  </span>
                  <button
                    disabled={busy}
                    onClick={() =>
                      run(async () => {
                        await api(`/customer-requests/${r.id}/accept`, {});
                        await reload();
                      })
                    }
                  >
                    ตรวจรับ / ตรวจใหม่
                  </button>
                  <button
                    disabled={busy}
                    onClick={() =>
                      run(async () => {
                        await api(`/customer-requests/${r.id}/reject`, {});
                        await reload();
                      })
                    }
                  >
                    ไม่รับรายการ
                  </button>
                  {r.status === "accepted" && state.restaurant_enabled && (
                    <button
                      disabled={busy || !terminal}
                      onClick={() =>
                        run(async () => {
                          await api(`/restaurant/customer-requests/${r.id}`, {
                            terminal_id: terminal,
                          });
                          await reload();
                        })
                      }
                    >
                      สร้างออเดอร์ครัวจากคำขอ
                    </button>
                  )}
                  {r.status === "accepted" && !state.restaurant_enabled && (
                    <button
                      disabled={
                        busy ||
                        items.length > 0 ||
                        !shift ||
                        r.catalog_version !== state.catalog_version
                      }
                      onClick={() => {
                        setCart(
                          Object.fromEntries(
                            r.items.map((i) => [i.product_id, i.quantity]),
                          ),
                        );
                        setUnitChoices(
                          Object.fromEntries(
                            r.items.map((i) => [i.product_id, i.unit]),
                          ),
                        );
                        setCustomerRequest(r.id);
                        setRequestKey(crypto.randomUUID());
                      }}
                    >
                      นำเข้าตะกร้า
                    </button>
                  )}
                </div>
              ))}
            </section>
          )}
          {!shift ? (
            <form
              className="panel shift"
              onSubmit={(e) => {
                e.preventDefault();
                run(async () => {
                  await api("/shifts", {
                    terminal_id: terminal,
                    opening_cash: opening,
                  });
                  await reload();
                });
              }}
            >
              <h2>เปิดกะ</h2>
              <label>
                เงินตั้งต้น
                <input
                  inputMode="decimal"
                  value={opening}
                  onChange={(e) => setOpening(e.target.value)}
                />
              </label>
              <button disabled={busy || !terminal}>เปิดกะขาย</button>
            </form>
          ) : (
            <div className="workspace">
              <section className="panel">
                <h2>สินค้า</h2>
                <input
                  aria-label="ค้นหาสินค้าหรือบาร์โค้ด"
                  placeholder="ค้นหาชื่อ / สแกนบาร์โค้ด"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
                <div className="products">
                  {products.map((p) => (
                    <button
                      className="product"
                      key={p.product_id}
                      disabled={busy}
                      onClick={() =>
                        setCart((old) => ({
                          ...old,
                          [p.product_id]: String(
                            Number(old[p.product_id] || 0) + 1,
                          ),
                        }))
                      }
                    >
                      <strong>{p.name}</strong>
                      <span>
                        {p.price} / {p.unit}
                      </span>
                      <small>VAT {p.tax_rate}% (ยังไม่รวม)</small>
                    </button>
                  ))}
                </div>
                {!state.catalog_version && <p>ยังไม่มี catalog ที่พร้อมขาย</p>}
              </section>
              <section className="panel cart">
                <h2>ตะกร้า</h2>
                {items.map(([id, q]) => (
                  <div className="line" key={id}>
                    <span>
                      {state.items.find((p) => p.product_id === id)?.name}
                    </span>
                    <select
                      aria-label={`หน่วย ${state.items.find((p) => p.product_id === id)?.name}`}
                      value={
                        unitChoices[id] ||
                        state.items.find((p) => p.product_id === id)?.unit
                      }
                      disabled={busy}
                      onChange={(e) =>
                        setUnitChoices((old) => ({
                          ...old,
                          [id]: e.target.value,
                        }))
                      }
                    >
                      <option
                        value={
                          state.items.find((p) => p.product_id === id)?.unit
                        }
                      >
                        {state.items.find((p) => p.product_id === id)?.unit}
                      </option>
                      {state.items
                        .find((p) => p.product_id === id)
                        ?.pharmacy?.units.map((unit) => (
                          <option key={unit.unit} value={unit.unit}>
                            {unit.unit} · {unit.price}
                          </option>
                        ))}
                    </select>
                    <input
                      aria-label="จำนวน"
                      inputMode="decimal"
                      value={q}
                      disabled={busy}
                      onChange={(e) =>
                        setCart((old) => ({ ...old, [id]: e.target.value }))
                      }
                    />
                    <button
                      disabled={busy}
                      onClick={() =>
                        setCart((old) => {
                          const next = { ...old };
                          delete next[id];
                          return next;
                        })
                      }
                    >
                      ลบ
                    </button>
                  </div>
                ))}
                {state.discounts_enabled && items.length > 0 && (
                  <form
                    className="discount"
                    onSubmit={(e) => {
                      e.preventDefault();
                      run(async () => {
                        const result = await api("/local-discount-approvals", {
                          terminal_id: terminal,
                          catalog_version: state.catalog_version,
                          request_key: discountKey,
                          manager_email: discountManagerEmail,
                          manager_password: discountPassword,
                          items: items.map(([product_id, quantity]) => ({
                            product_id,
                            quantity,
                            unit:
                              unitChoices[product_id] ||
                              state.items.find(
                                (p) => p.product_id === product_id,
                              )?.unit,
                            discount_amount:
                              discountAmounts[product_id] || "0.00",
                          })),
                        });
                        setDiscountPassword("");
                        setDiscountApproval(result);
                      });
                    }}
                  >
                    <h3>ส่วนลดที่ Manager อนุมัติ</h3>
                    {items.map(([id]) => (
                      <label key={id}>
                        ส่วนลด{" "}
                        {state.items.find((p) => p.product_id === id)?.name} ·
                        เพดาน{" "}
                        {state.items.find((p) => p.product_id === id)
                          ?.max_manager_discount_pct || "0.00"}
                        %
                        <input
                          inputMode="decimal"
                          value={discountAmounts[id] || ""}
                          disabled={busy}
                          onChange={(e) =>
                            setDiscountAmounts({
                              ...discountAmounts,
                              [id]: e.target.value,
                            })
                          }
                        />
                      </label>
                    ))}
                    <label>
                      อีเมล Manager อนุมัติส่วนลด
                      <input
                        required
                        type="email"
                        value={discountManagerEmail}
                        disabled={busy}
                        onChange={(e) =>
                          setDiscountManagerEmail(e.target.value)
                        }
                      />
                    </label>
                    <label>
                      รหัสผ่าน Manager อนุมัติส่วนลด
                      <input
                        required
                        type="password"
                        autoComplete="current-password"
                        value={discountPassword}
                        disabled={busy}
                        onChange={(e) => setDiscountPassword(e.target.value)}
                      />
                    </label>
                    <button
                      disabled={
                        busy || !discountRequested || !!discountApproval
                      }
                    >
                      ตรวจและอนุมัติส่วนลด
                    </button>
                    {discountApproval && (
                      <p role="status">
                        ยอดหลังส่วนลดและ VAT:{" "}
                        {discountApproval.discounted_total}
                      </p>
                    )}
                  </form>
                )}
                <label>
                  รับเงินสด
                  <input
                    inputMode="decimal"
                    value={cash}
                    disabled={busy}
                    onChange={(e) => setCash(e.target.value)}
                  />
                </label>
                <p>ตรวจราคา ภาษี และสินค้าพร้อมขายก่อนยืนยัน</p>
                <button
                  className="primary"
                  disabled={
                    busy ||
                    !items.length ||
                    !state.catalog_version ||
                    (!!discountRequested && !discountApproval)
                  }
                  onClick={() =>
                    run(async () => {
                      const result = await api("/checkout", {
                        terminal_id: terminal,
                        request_key: requestKey,
                        catalog_version: state.catalog_version,
                        items: items.map(([product_id, quantity]) => ({
                          product_id,
                          quantity,
                          unit:
                            unitChoices[product_id] ||
                            state.items.find((p) => p.product_id === product_id)
                              ?.unit,
                        })),
                        cash_tendered: cash,
                        ...(customerRequest
                          ? { customer_request_id: customerRequest }
                          : {}),
                        ...(discountApproval
                          ? { discount_approval_id: discountApproval.id }
                          : {}),
                      });
                      setReceipt(result);
                      setCart({});
                      setUnitChoices({});
                      setCustomerRequest(null);
                      setDiscountApproval(null);
                      setDiscountAmounts({});
                      setCash("");
                      setRequestKey(crypto.randomUUID());
                      await reload();
                    })
                  }
                >
                  {busy ? "กำลังตรวจรายการ…" : "ยืนยันรับเงินสด"}
                </button>
                <form
                  className="close"
                  onSubmit={(e) => {
                    e.preventDefault();
                    run(async () => {
                      const result = await api(`/shifts/${shift.id}/close`, {
                        counted_cash: counted,
                      });
                      setError(`ผลต่างเงินสด: ${result.over_short}`);
                      await reload();
                    });
                  }}
                >
                  <h3>ปิดกะ</h3>
                  <label>
                    ยอดนับจริง
                    <input
                      inputMode="decimal"
                      value={counted}
                      onChange={(e) => setCounted(e.target.value)}
                    />
                  </label>
                  <button disabled={busy || items.length > 0}>
                    ปิดกะและบันทึกผลต่าง
                  </button>
                </form>
              </section>
            </div>
          )}
        </>
      )}
      {receipt && (
        <section className="panel receipt">
          <h2>รายการขาย {receipt.receipt_no}</h2>
          <p>ตัวอย่างใบรับเงิน — ไม่ใช่ใบกำกับภาษี</p>
          {receipt.lines.map((l, i) => (
            <p key={i}>
              {l.name} × {l.quantity} — {l.total}
            </p>
          ))}
          <strong>
            รวม {receipt.total} · รับ {receipt.cash_tendered} · ทอน{" "}
            {receipt.change}
          </strong>
          <div>
            <button onClick={() => window.print()}>พิมพ์ตัวอย่าง</button>
            <button onClick={() => setReceipt(null)}>ปิด</button>
          </div>
        </section>
      )}
    </main>
  );
}
createRoot(document.getElementById("root")!).render(<App />);
