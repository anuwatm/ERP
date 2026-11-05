import React, { useEffect, useRef, useState } from "react";

type Option = { id: string; code: string; name_masked: string };
export default function PatientPicker({
  value,
  onChange,
  disabled,
}: {
  value: string;
  onChange: (value: string) => void;
  disabled: boolean;
}) {
  const epoch = useRef(0);
  const [options, setOptions] = useState<Option[]>([]),
    [cursor, setCursor] = useState<string | null>(null),
    [error, setError] = useState("");
  async function load(after?: string) {
    const generation = ++epoch.current;
    try {
      const response = await fetch(
        "/patients/options" +
          (after ? "?after=" + encodeURIComponent(after) : ""),
        {
          credentials: "same-origin",
          cache: "no-store",
          headers: { Accept: "application/json" },
        },
      );
      if (!response.ok)
        throw new Error(
          "โหลดรายชื่อผู้ป่วยไม่ได้ กรุณาตรวจสิทธิ์/เข้าสู่ระบบใหม่",
        );
      const data = await response.json();
      if (generation !== epoch.current) return;
      setOptions((old) => (after ? [...old, ...data.items] : data.items));
      setCursor(data.next);
      setError("");
    } catch (error) {
      if (generation !== epoch.current) return;
      setOptions([]);
      setCursor(null);
      onChange("");
      setError(String(error));
    }
  }
  useEffect(() => {
    void load();
    const clear = () => {
      epoch.current++;
      setOptions([]);
      setCursor(null);
      onChange("");
    };
    const channel = new BroadcastChannel("pos-offline-auth-v1");
    channel.onmessage = clear;
    window.addEventListener("pagehide", clear);
    return () => {
      epoch.current++;
      channel.close();
      window.removeEventListener("pagehide", clear);
    };
  }, []);
  return (
    <fieldset style={{ minWidth: 0, maxWidth: "100%" }}>
      <legend>ผู้ป่วย — เฉพาะข้อมูลทดสอบ</legend>
      <label>
        เชื่อมบิลกับผู้ป่วย
        <select
          aria-label="ผู้ป่วย"
          style={{ width: "100%", minWidth: 0 }}
          value={value}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
        >
          <option value="">ไม่ระบุผู้ป่วย</option>
          {options.map((patient) => (
            <option key={patient.id} value={patient.id}>
              {patient.code} · {patient.name_masked}
            </option>
          ))}
        </select>
      </label>
      <button
        type="button"
        disabled={disabled}
        onClick={() => {
          onChange("");
          void load();
        }}
      >
        โหลดรายชื่อใหม่
      </button>
      {cursor && (
        <button
          type="button"
          disabled={disabled}
          onClick={() => void load(cursor)}
        >
          รายชื่อถัดไป
        </button>
      )}
      <p>
        รายละเอียดสุขภาพอ่านได้เฉพาะเภสัชกร/Manager ที่มีคุณสมบัติ
        ไม่ส่งข้อมูลผู้ป่วยเข้า ERP
      </p>
      {error && <p role="alert">{error}</p>}
    </fieldset>
  );
}
