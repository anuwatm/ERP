import React, { useState } from "react";

type Sale = {
  id: string;
  terminal_id: string;
  receipt_no: string;
  total: string;
  documents: {
    id: string;
    kind: string;
    document_no: string;
    status: string;
    etax_status: string;
  }[];
};
type Props = {
  busy: boolean;
  api: (path: string, data?: unknown) => Promise<any>;
  run: (action: () => Promise<void>) => Promise<void>;
};
export default function TaxDocumentsPanel({ busy, api, run }: Props) {
  const [sales, setSales] = useState<Sale[]>([]);
  const [sale, setSale] = useState("");
  const [kind, setKind] = useState("FULL");
  const [name, setName] = useState("");
  const [tax, setTax] = useState("");
  const [branch, setBranch] = useState("00000");
  const [address, setAddress] = useState("");
  const [key, setKey] = useState(() => crypto.randomUUID());
  const [result, setResult] = useState("");
  const selected = sales.find((s) => s.id === sale);
  return (
    <section className="panel" aria-label="เอกสารภาษี POS">
      <h2>ABB / Full — ทดสอบ local</h2>
      <p>ออก Full แทน ABB โดยอ้างอิงยอดขายเดิม คิว e-Tax ยังรอ provider</p>
      <button
        disabled={busy}
        onClick={() => run(async () => setSales(await api("/tax-documents")))}
      >
        โหลดบิลและเอกสารภาษี
      </button>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (!selected) return;
          run(async () => {
            const document = await api("/tax-documents", {
              sale_id: selected.id,
              terminal_id: selected.terminal_id,
              kind,
              request_key: key,
              ...(kind === "FULL"
                ? { buyer: { name, tax_id: tax, branch_no: branch, address } }
                : {}),
            });
            setResult(`ออก ${document.document_no} แล้ว · e-Tax รอ provider`);
            setKey(crypto.randomUUID());
            setSales(await api("/tax-documents"));
            setName("");
            setTax("");
            setAddress("");
          });
        }}
      >
        <label>
          บิลขายสำหรับภาษี
          <select
            aria-label="บิลขายสำหรับภาษี"
            required
            disabled={busy}
            value={sale}
            onChange={(e) => {
              setSale(e.target.value);
              setKey(crypto.randomUUID());
            }}
          >
            <option value="">เลือกบิล (50 รายการล่าสุด)</option>
            {sales.map((s) => (
              <option key={s.id} value={s.id}>
                {s.receipt_no} · {s.total}
              </option>
            ))}
          </select>
        </label>
        <label>
          ชนิดเอกสาร
          <select
            aria-label="ชนิดเอกสาร"
            disabled={busy}
            value={kind}
            onChange={(e) => setKind(e.target.value)}
          >
            <option value="FULL">Full</option>
            <option value="ABB">ABB</option>
          </select>
        </label>
        {kind === "FULL" && (
          <>
            <label>
              ชื่อผู้ซื้อ
              <input
                required
                maxLength={255}
                disabled={busy}
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </label>
            <label>
              เลขผู้เสียภาษีผู้ซื้อ
              <input
                required
                pattern="[0-9]{13}"
                maxLength={13}
                disabled={busy}
                value={tax}
                onChange={(e) => setTax(e.target.value)}
              />
            </label>
            <label>
              สาขาผู้ซื้อ
              <input
                required
                pattern="[0-9]{5}"
                maxLength={5}
                disabled={busy}
                value={branch}
                onChange={(e) => setBranch(e.target.value)}
              />
            </label>
            <label>
              ที่อยู่ผู้ซื้อ
              <textarea
                required
                maxLength={1000}
                disabled={busy}
                value={address}
                onChange={(e) => setAddress(e.target.value)}
              />
            </label>
          </>
        )}
        <button disabled={busy || !selected}>ออกเอกสารภาษีทดสอบ</button>
      </form>
      {selected?.documents.map((d) => (
        <p key={d.id}>
          <a
            href={`/tax-documents/${d.id}/print`}
            target="_blank"
            rel="noreferrer"
          >
            {d.document_no}
          </a>{" "}
          · {d.status} · e-Tax {d.etax_status}
        </p>
      ))}
      {result && <p role="status">{result}</p>}
    </section>
  );
}
