import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import "./pos.css";
type Product = {
  product_id: string;
  name: string;
  price: string;
  unit: string;
  tax_rate: string;
};
function Menu() {
  const [token] = useState(() => location.hash.slice(1));
  const [products, setProducts] = useState<Product[]>([]),
    [cart, setCart] = useState<Record<string, string>>({});
  const [key, setKey] = useState(() => crypto.randomUUID()),
    [id, setId] = useState<string | null>(null);
  const [status, setStatus] = useState(""),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  async function call(path: string, body: object) {
    const response = await fetch(path, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-CSRF-TOKEN":
          document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content || "",
      },
      body: JSON.stringify({ token, ...body }),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.message || "เมนูนี้ไม่พร้อมใช้งาน");
    return result;
  }
  useEffect(() => {
    let active = true;
    call("/menu/catalog", {})
      .then((data) => {
        if (active) setProducts(data.items);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [token]);
  async function run(fn: () => Promise<void>) {
    setBusy(true);
    setError("");
    try {
      await fn();
    } catch (e) {
      setError(e instanceof Error ? e.message : "ไม่ทราบผลคำขอ กรุณาตรวจสถานะ");
    } finally {
      setBusy(false);
    }
  }
  const labels: Record<string, string> = {
    pending: "รอพนักงานตรวจรับ",
    accepted: "พนักงานรับคำขอแล้ว กรุณาชำระเงินที่จุดขาย",
    ordered: "พนักงานสร้างออเดอร์อาหารแล้ว รอครัวเตรียม",
    cancelled: "ออเดอร์ถูกยกเลิก กรุณาติดต่อพนักงาน",
    completed: "ชำระเงินและรับสินค้าแล้ว",
    rejected: "พนักงานไม่สามารถรับรายการนี้ กรุณาติดต่อจุดขาย",
  };
  return (
    <main>
      <header>
        <h1>เมนูสินค้า</h1>
      </header>
      <p className="notice">
        เลือกสินค้าแล้วส่งให้พนักงานตรวจรับ
        ราคาและสินค้าพร้อมขายยืนยันอีกครั้งตอนชำระเงิน ไม่มีการชำระเงินออนไลน์
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      <div className="products">
        {products.map((p) => (
          <label className="panel" key={p.product_id}>
            <strong>{p.name}</strong>
            <p>
              {p.price} / {p.unit} · VAT {p.tax_rate}% ยังไม่รวม
            </p>
            <input
              aria-label={`จำนวน ${p.name}`}
              inputMode="decimal"
              value={cart[p.product_id] || "0"}
              disabled={busy || id !== null}
              onChange={(e) =>
                setCart((old) => ({ ...old, [p.product_id]: e.target.value }))
              }
            />
          </label>
        ))}
      </div>
      {!id ? (
        <button
          className="primary"
          disabled={busy || !Object.values(cart).some((q) => Number(q) > 0)}
          onClick={() =>
            run(async () => {
              const result = await call("/menu/requests", {
                request_key: key,
                items: Object.entries(cart)
                  .filter(([, q]) => Number(q) > 0)
                  .map(([product_id, quantity]) => ({ product_id, quantity })),
              });
              setId(result.id);
              setStatus(result.status);
            })
          }
        >
          ส่งคำขอให้พนักงาน
        </button>
      ) : (
        <section className="panel">
          <h2>{labels[status] || status}</h2>
          <p>คำขอ {id}</p>
          <button
            disabled={busy}
            onClick={() =>
              run(async () => {
                const result = await call("/menu/status", { id });
                setStatus(result.status);
              })
            }
          >
            ตรวจสถานะ
          </button>
          {(status === "completed" || status === "rejected") && (
            <button
              onClick={() => {
                setId(null);
                setStatus("");
                setCart({});
                setKey(crypto.randomUUID());
              }}
            >
              เริ่มคำขอใหม่
            </button>
          )}
        </section>
      )}
    </main>
  );
}
createRoot(document.getElementById("root")!).render(<Menu />);
