import React, { useEffect, useRef, useState } from "react";

type Entry = {
  id: string;
  number: number;
  kind: "walk_in" | "reservation";
  status: "waiting" | "called" | "confirmed" | "arrived";
  party_size: number;
  table_id: string | null;
  booked_at: string | null;
  joined_at: string;
  contact: string | null;
};
type Props = {
  tables: {
    id: string;
    label: string;
    capacity: number;
    session_id: string | null;
  }[];
  busy: boolean;
  api: (path: string, data?: unknown) => Promise<any>;
  run: (action: () => Promise<void>) => Promise<void>;
  reload: () => Promise<void>;
};
const statusLabel = {
  waiting: "รอ",
  called: "เรียกแล้ว",
  confirmed: "ยืนยันแล้ว",
  arrived: "มาถึงแล้ว",
};

export default function SeatingPanel({
  tables,
  busy,
  api,
  run,
  reload,
}: Props) {
  const [entries, setEntries] = useState<Entry[]>([]);
  const [kind, setKind] = useState<"walk_in" | "reservation">("walk_in");
  const [party, setParty] = useState("2");
  const [table, setTable] = useState("");
  const [when, setWhen] = useState("");
  const [contact, setContact] = useState("");
  const [choices, setChoices] = useState<Record<string, string>>({});
  const [online, setOnline] = useState(navigator.onLine);
  const pending = useRef<{
    request_key: string;
    [key: string]: unknown;
  } | null>(null);
  const [retry, setRetry] = useState(false);

  useEffect(() => {
    const update = () => {
      setOnline(navigator.onLine);
      if (!navigator.onLine) setEntries([]);
    };
    addEventListener("online", update);
    addEventListener("offline", update);
    return () => {
      removeEventListener("online", update);
      removeEventListener("offline", update);
    };
  }, []);

  const refresh = async () => {
    const result = await api("/restaurant/seating");
    if (navigator.onLine) setEntries(result.entries);
  };
  const change = (entry: Entry, action: string) =>
    run(async () => {
      await api(`/restaurant/seating/${entry.id}/${action}`, {
        table_id:
          action === "seat" ? entry.table_id || choices[entry.id] : undefined,
      });
      await refresh();
      await reload();
    });
  const disabled = busy || !online;

  return (
    <section aria-label="คิวที่นั่งและจองโต๊ะ">
      <h3>คิวที่นั่งและจองโต๊ะ</h3>
      {!online && (
        <p role="status">
          ออฟไลน์ เก็บแบบฟอร์มไว้บนหน้าจอ รอเชื่อมต่อเพื่อส่งให้ POS Server
        </p>
      )}
      <form
        onSubmit={(event) => {
          event.preventDefault();
          run(async () => {
            if (!pending.current) {
              pending.current = {
                request_key: crypto.randomUUID(),
                kind,
                party_size: Number(party),
                ...(kind === "reservation"
                  ? {
                      table_id: table,
                      booked_at: new Date(when).toISOString(),
                      contact,
                    }
                  : {}),
              };
            }
            setRetry(true);
            await api("/restaurant/seating", pending.current);
            pending.current = null;
            setRetry(false);
            setContact("");
            await refresh();
            await reload();
          });
        }}
      >
        <fieldset disabled={busy || retry}>
          <label>
            ประเภทคิว{" "}
            <select
              value={kind}
              onChange={(e) =>
                setKind(
                  e.target.value === "reservation" ? "reservation" : "walk_in",
                )
              }
            >
              <option value="walk_in">Walk-in</option>
              <option value="reservation">จองโต๊ะ</option>
            </select>
          </label>
          <label>
            จำนวนลูกค้า{" "}
            <input
              type="number"
              min={1}
              max={100}
              required
              value={party}
              onChange={(e) => setParty(e.target.value)}
            />
          </label>
          {kind === "reservation" && (
            <>
              <label>
                โต๊ะที่จอง{" "}
                <select
                  required
                  value={table}
                  onChange={(e) => setTable(e.target.value)}
                >
                  <option value="">เลือกโต๊ะ</option>
                  {tables
                    .filter((t) => t.capacity >= Number(party))
                    .map((t) => (
                      <option value={t.id} key={t.id}>
                        {t.label} ({t.capacity} ที่)
                      </option>
                    ))}
                </select>
              </label>
              <label>
                วันเวลานัดตามเวลาเครื่อง{" "}
                <input
                  type="datetime-local"
                  required
                  value={when}
                  onChange={(e) => setWhen(e.target.value)}
                />
              </label>
              <label>
                ข้อมูลติดต่อสำหรับจอง{" "}
                <input
                  required
                  maxLength={160}
                  value={contact}
                  onChange={(e) => setContact(e.target.value)}
                />
              </label>
            </>
          )}
        </fieldset>
        <button disabled={disabled}>
          {retry ? "ส่งคำขอเดิมอีกครั้ง" : "บันทึกคิวที่นั่ง"}
        </button>
        {retry && (
          <button
            type="button"
            disabled={disabled}
            onClick={() =>
              run(async () => {
                await refresh();
                pending.current = null;
                setRetry(false);
              })
            }
          >
            ตรวจรายการล่าสุดและกลับไปแก้
          </button>
        )}
      </form>
      <button type="button" disabled={disabled} onClick={() => run(refresh)}>
        โหลดคิวที่นั่ง
      </button>
      <ul>
        {entries.map((entry) => (
          <li key={entry.id} className="panel">
            <strong>
              คิว #{entry.number} ·{" "}
              {entry.kind === "reservation" ? "จองโต๊ะ" : "Walk-in"} ·{" "}
              {entry.party_size} คน · {statusLabel[entry.status]}
            </strong>
            <p>
              {new Date(entry.booked_at ?? entry.joined_at).toLocaleString(
                "th-TH",
              )}
              {entry.contact && ` · ${entry.contact}`}
              {entry.table_id &&
                ` · ${tables.find((t) => t.id === entry.table_id)?.label ?? ""}`}
            </p>
            {entry.status === "waiting" && (
              <button disabled={disabled} onClick={() => change(entry, "call")}>
                เรียกคิว
              </button>
            )}
            {entry.status === "confirmed" && (
              <button
                disabled={disabled}
                onClick={() => change(entry, "arrive")}
              >
                มาถึงแล้ว
              </button>
            )}
            {entry.kind === "walk_in" && (
              <label>
                โต๊ะสำหรับคิว #{entry.number}{" "}
                <select
                  value={choices[entry.id] ?? ""}
                  onChange={(e) =>
                    setChoices({ ...choices, [entry.id]: e.target.value })
                  }
                >
                  <option value="">เลือกโต๊ะว่าง</option>
                  {tables
                    .filter(
                      (t) => !t.session_id && t.capacity >= entry.party_size,
                    )
                    .map((t) => (
                      <option value={t.id} key={t.id}>
                        {t.label}
                      </option>
                    ))}
                </select>
              </label>
            )}
            {(entry.kind === "walk_in" || entry.status === "arrived") && (
              <button
                disabled={disabled || !(entry.table_id || choices[entry.id])}
                onClick={() => change(entry, "seat")}
              >
                จัดที่นั่ง
              </button>
            )}
            <button disabled={disabled} onClick={() => change(entry, "cancel")}>
              ยกเลิกคิว
            </button>
            {(entry.status === "called" ||
              (entry.status === "confirmed" &&
                entry.booked_at &&
                Date.now() >= Date.parse(entry.booked_at) + 900000)) && (
              <button
                disabled={disabled}
                onClick={() => change(entry, "no_show")}
              >
                ยืนยันไม่มา
              </button>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
