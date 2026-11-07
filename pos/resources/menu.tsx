import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import "./pos.css";
type Product = {
  product_id: string;
  name: string;
  price: string;
  unit: string;
  tax_rate: string;
  available?: boolean;
  restaurant_variant?: { size: string; modifiers: string[] };
};
function Menu() {
  const [token] = useState(() => location.hash.slice(1));
  const validLink = /^[a-f\d]{64}$/i.test(token);
  const [loadingCatalog, setLoadingCatalog] = useState(validLink);
  const [products, setProducts] = useState<Product[]>([]),
    [cart, setCart] = useState<Record<string, string>>({});
  const [version, setVersion] = useState("");
  const [restaurant, setRestaurant] = useState(false);
  const [online, setOnline] = useState(navigator.onLine);
  const [uncertain, setUncertain] = useState(false);
  const [known, setKnown] = useState<string[]>([]);
  const [review, setReview] = useState("");
  const [progress, setProgress] = useState<Record<string, number>>({});
  const [queueNumber, setQueueNumber] = useState<number | null>(null);
  const [key, setKey] = useState(() => crypto.randomUUID()),
    [id, setId] = useState<string | null>(null);
  const [status, setStatus] = useState(""),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  async function call(path: string, body: object) {
    const response = await fetch(path, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
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
      throw Object.assign(
        new Error(result.message || "เมนูนี้ไม่พร้อมใช้งาน"),
        { status: response.status },
      );
    return result;
  }
  useEffect(() => {
    if (!validLink) return;
    let active = true;
    call("/menu/catalog", {})
      .then((data) => {
        if (active) applyCatalog(data);
      })
      .catch((e) => {
        if (active) {
          setError(
            [403, 404, 410, 422].includes(Number(e?.status))
              ? "เปิดเมนูจากลิงก์นี้ไม่ได้ กรุณาสแกน QR ใหม่หรือติดต่อพนักงาน"
              : "โหลดเมนูไม่สำเร็จ ตรวจการเชื่อมต่อแล้วลองอีกครั้ง",
          );
        }
      })
      .finally(() => {
        if (active) setLoadingCatalog(false);
      });
    return () => {
      active = false;
    };
  }, [token, validLink]);
  function applyCatalog(data: {
    items: Product[];
    version: string;
    restaurant?: boolean;
  }) {
    setProducts(data.items);
    setVersion(data.version);
    setRestaurant(data.restaurant === true);
  }
  useEffect(() => {
    const update = () => setOnline(navigator.onLine);
    addEventListener("online", update);
    addEventListener("offline", update);
    return () => {
      removeEventListener("online", update);
      removeEventListener("offline", update);
    };
  }, []);
  async function checkStatus(requestId: string) {
    const result = await call("/menu/status", { id: requestId });
    setId(requestId);
    setStatus(result.status);
    setReview(result.review ?? "");
    setProgress(result.progress ?? {});
    setQueueNumber(result.queue_number ?? null);
  }
  function newOrder() {
    setId(null);
    setStatus("");
    setReview("");
    setQueueNumber(null);
    setProgress({});
    setCart({});
    setKey(crypto.randomUUID());
  }
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
        <h1>{restaurant ? "เมนูอาหาร" : "เมนูสินค้า"}</h1>
      </header>
      <p className="notice">
        เลือกสินค้าแล้วส่งให้พนักงานตรวจรับ
        ราคาและสินค้าพร้อมขายยืนยันอีกครั้งตอนชำระเงิน ไม่มีการชำระเงินออนไลน์
      </p>
      {!online && (
        <p role="status">ออฟไลน์ รายการยังรอส่งหรือยืนยันผลจาก POS Server</p>
      )}
      <button
        disabled={!validLink || loadingCatalog || busy || !online || uncertain}
        onClick={() =>
          run(async () => {
            applyCatalog(await call("/menu/catalog", {}));
            if (!id) newOrder();
          })
        }
      >
        โหลดเมนูล่าสุด
      </button>
      {known.length > 0 && (
        <label>
          คำขอจากเครื่องนี้{" "}
          <select
            disabled={busy || !online || uncertain}
            value={id ?? ""}
            onChange={(e) => {
              const requestId = e.target.value;
              if (requestId) run(() => checkStatus(requestId));
            }}
          >
            <option value="">เลือกคำขอ</option>
            {known.map((requestId) => (
              <option key={requestId} value={requestId}>
                {requestId}
              </option>
            ))}
          </select>
        </label>
      )}
      {!validLink && (
        <p role="alert" className="error">
          กรุณาสแกน QR ที่ร้านเพื่อเปิดเมนู
        </p>
      )}
      {loadingCatalog && <p role="status">กำลังโหลดเมนู…</p>}
      {version && products.length === 0 && (
        <p role="status">ยังไม่มีรายการพร้อมสั่ง</p>
      )}
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      <div className="products">
        {products.map((p) => (
          <label className="panel" key={p.product_id}>
            <strong>{p.name}</strong>
            {p.restaurant_variant && (
              <span>
                {" "}
                · {p.restaurant_variant.size}{" "}
                {p.restaurant_variant.modifiers.join(", ")}
              </span>
            )}
            {p.available === false && <span> · หมดชั่วคราว</span>}
            <p>
              {p.price} / {p.unit} · VAT {p.tax_rate}% ยังไม่รวม
            </p>
            <input
              aria-label={`จำนวน ${p.name}`}
              inputMode={restaurant ? "numeric" : "decimal"}
              value={cart[p.product_id] || "0"}
              disabled={
                busy || uncertain || id !== null || p.available === false
              }
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
          disabled={
            busy ||
            !online ||
            !version ||
            !Object.values(cart).some((q) => Number(q) > 0)
          }
          onClick={() =>
            run(async () => {
              setUncertain(true);
              try {
                const result = await call("/menu/requests", {
                  request_key: key,
                  catalog_version: version,
                  items: Object.entries(cart)
                    .filter(([, q]) => Number(q) > 0)
                    .map(([product_id, quantity]) => ({
                      product_id,
                      quantity,
                    })),
                });
                setKnown((old) =>
                  old.includes(result.id) ? old : [...old, result.id],
                );
                setId(result.id);
                setStatus(result.status);
                setUncertain(false);
              } catch (error) {
                if (
                  error &&
                  typeof error === "object" &&
                  "status" in error &&
                  [404, 409, 422].includes(Number(error.status))
                ) {
                  setUncertain(false);
                }
                throw error;
              }
            })
          }
        >
          {uncertain ? "ส่งคำขอเดิมอีกครั้ง" : "ส่งคำขอให้พนักงาน"}
        </button>
      ) : (
        <section className="panel">
          <h2>{labels[status] || status}</h2>
          {review && (
            <p role="status">
              {review === "menu_changed"
                ? "เมนูหรือราคาเปลี่ยน กรุณาโหลดเมนูล่าสุดก่อนส่งคำขอใหม่"
                : "วัตถุดิบไม่พอสำหรับรายการนี้ กรุณาตรวจสถานะอีกครั้งหรือติดต่อพนักงาน"}
            </p>
          )}
          <p>
            คำขอ {id}
            {queueNumber !== null && ` · คิว #${queueNumber}`}
          </p>
          {Object.entries(progress).length > 0 && (
            <p>
              {Object.entries(progress)
                .map(([phase, count]) => {
                  const phases: Record<string, string> = {
                    reserved: "รอส่งครัว",
                    sent: "รอครัวรับ",
                    acknowledged: "รอทำ",
                    preparing: "กำลังทำ",
                    ready: "พร้อมเสิร์ฟ",
                    served: "เสิร์ฟแล้ว",
                    cancelled: "ยกเลิก",
                    wasted: "ยกเลิก",
                  };
                  return `${phases[phase] ?? phase} ${count}`;
                })
                .join(" · ")}
            </p>
          )}
          <button
            disabled={busy || !online}
            onClick={() => run(() => checkStatus(id))}
          >
            ตรวจสถานะ
          </button>
          {(restaurant || status === "completed" || status === "rejected") && (
            <button onClick={newOrder}>
              {restaurant ? "สั่งเพิ่ม / เริ่มคำขอใหม่" : "เริ่มคำขอใหม่"}
            </button>
          )}
        </section>
      )}
    </main>
  );
}
createRoot(document.getElementById("root")!).render(<Menu />);
