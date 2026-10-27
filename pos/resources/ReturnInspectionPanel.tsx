import React, { useState } from "react";

type Option = {
  refund_id: string;
  source_line_id: string;
  grant_id: string;
  lot_id: string;
  name: string;
  remaining_base_quantity: string;
  original_lot_quantity: string;
};
type Props = {
  busy: boolean;
  api: (path: string, data?: unknown) => Promise<any>;
  run: (action: () => Promise<void>) => Promise<void>;
  reload: () => Promise<void>;
};

export default function ReturnInspectionPanel({
  busy,
  api,
  run,
  reload,
}: Props) {
  const [options, setOptions] = useState<Option[]>([]);
  const [selected, setSelected] = useState("");
  const [quantity, setQuantity] = useState("");
  const [decision, setDecision] = useState("destroy");
  const [packaging, setPackaging] = useState(false);
  const [storage, setStorage] = useState(false);
  const [lot, setLot] = useState(false);
  const [reason, setReason] = useState("");
  const [password, setPassword] = useState("");
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [result, setResult] = useState("");
  const option = options.find(
    (o) => `${o.refund_id}:${o.source_line_id}:${o.grant_id}` === selected,
  );
  return (
    <section className="panel" aria-label="ตรวจของคืนร้านขายยา">
      <h2>ตรวจของคืน — ผู้จัดการที่เป็นเภสัชกร</h2>
      <p>
        ตรวจ lot และสภาพจริงก่อนเลือกผล คืน Stock เมื่อ ERP ตรวจรับแล้ว ไม่เพิ่ม
        quota ขายใน POS อัตโนมัติ
      </p>
      <button
        disabled={busy}
        onClick={() =>
          run(async () => {
            setOptions(await api("/local-return-inspections"));
            setSelected("");
            setKey(crypto.randomUUID());
          })
        }
      >
        โหลดของรอตรวจ
      </button>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (!option) return;
          const secret = password;
          setPassword("");
          run(async () => {
            const response = await api("/local-return-inspections", {
              refund_id: option.refund_id,
              source_line_id: option.source_line_id,
              grant_id: option.grant_id,
              request_key: key,
              base_quantity: quantity,
              decision,
              packaging_intact: packaging,
              storage_verified: storage,
              lot_verified: lot,
              reason,
              manager_password: secret,
            });
            setResult(
              `บันทึกผลตรวจ ${response.inspection_id} · รอ ERP ตรวจรับ`,
            );
            setOptions(await api("/local-return-inspections"));
            setSelected("");
            setQuantity("");
            setKey(crypto.randomUUID());
            await reload();
          });
        }}
      >
        <label>
          รายการของคืน
          <select
            aria-label="รายการของคืน"
            required
            disabled={busy}
            value={selected}
            onChange={(e) => {
              setSelected(e.target.value);
              setPackaging(false);
              setStorage(false);
              setLot(false);
              setKey(crypto.randomUUID());
            }}
          >
            <option value="">เลือกสินค้าและ lot ต้นฉบับ</option>
            {options.map((o) => {
              const value = `${o.refund_id}:${o.source_line_id}:${o.grant_id}`;
              return (
                <option key={value} value={value}>
                  {o.name} · lot {o.lot_id} · คืน {o.refund_id}
                </option>
              );
            })}
          </select>
        </label>
        {option && (
          <p>
            เหลือรอตรวจ {option.remaining_base_quantity} หน่วยฐาน · lot
            นี้ขายเดิม {option.original_lot_quantity} หน่วยฐาน
          </p>
        )}
        <label>
          จำนวนตรวจในหน่วยฐาน
          <input
            required
            disabled={busy}
            inputMode="decimal"
            value={quantity}
            onChange={(e) => setQuantity(e.target.value)}
          />
        </label>
        <label>
          ผลตรวจ
          <select
            aria-label="ผลตรวจ"
            disabled={busy}
            value={decision}
            onChange={(e) => setDecision(e.target.value)}
          >
            <option value="destroy">ทำลาย / ห้ามกลับไปขาย</option>
            <option value="restock">คืน Stock พร้อมขาย</option>
          </select>
        </label>
        <label>
          <input
            type="checkbox"
            checked={packaging}
            disabled={busy}
            onChange={(e) => setPackaging(e.target.checked)}
          />
          บรรจุภัณฑ์สมบูรณ์
        </label>
        <label>
          <input
            type="checkbox"
            checked={storage}
            disabled={busy}
            onChange={(e) => setStorage(e.target.checked)}
          />
          ตรวจยืนยันการเก็บรักษา
        </label>
        <label>
          <input
            type="checkbox"
            checked={lot}
            disabled={busy}
            onChange={(e) => setLot(e.target.checked)}
          />
          ตรวจ lot ตรงต้นฉบับ
        </label>
        <label>
          หลักฐานและเหตุผลตรวจ
          <input
            required
            maxLength={500}
            disabled={busy}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
        </label>
        <label>
          รหัสผ่านผู้ตรวจ
          <input
            required
            type="password"
            autoComplete="current-password"
            disabled={busy}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
        </label>
        <button
          disabled={
            busy ||
            !option ||
            (decision === "restock" && !(packaging && storage && lot))
          }
        >
          ยืนยันผลตรวจของคืน
        </button>
      </form>
      {result && <p role="status">{result}</p>}
    </section>
  );
}
