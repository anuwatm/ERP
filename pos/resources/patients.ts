const root = document.getElementById("patients-root")!;
root.innerHTML = `<style>body{font:16px system-ui;background:#f2f6f7;color:#18353c;margin:0}main{max-width:1050px;margin:auto;padding:24px}section{background:white;padding:20px;margin:16px 0;border-radius:12px}label{display:block;margin:10px 0}input,textarea,button{font:inherit;padding:10px;max-width:100%;box-sizing:border-box}input:not([type=checkbox]),textarea{display:block;width:100%}textarea{min-height:100px}button{background:#154650;color:white;border:0;border-radius:6px;margin:4px;cursor:pointer}article{padding:12px;border:1px solid #cadbdd;margin:8px 0;overflow-wrap:anywhere}pre{white-space:pre-wrap;overflow-wrap:anywhere}#code{overflow-wrap:anywhere}#message{background:#fff0c5;padding:12px}@media(max-width:500px){main{padding:12px}button{width:100%;margin:4px 0}}</style>
<h1>ประวัติผู้ป่วย · Pharmacy local</h1><p><a href="/">กลับหน้าขาย</a></p><p>ใช้ข้อมูลสังเคราะห์เท่านั้น เก็บเฉพาะสาขา ไม่ sync ERP/ข้ามสาขา และไม่เก็บ offline</p><p>ซ่อนแท็บ/ขาดการเชื่อมต่อ/หมด session จะล้างข้อมูลบนจอ โปรดบันทึกก่อนเปลี่ยนหน้า</p><p id="message" role="status">กดโหลดรายชื่อเพื่อยืนยัน session</p>
<section><h2>ทะเบียนผู้ป่วย</h2><div id="clinical-search" hidden><label>ค้นหาตรงข้อมูล<select id="search-field"><option value="name">ชื่อเต็ม</option><option value="phone">โทรศัพท์</option></select></label><label>คำค้น<input id="search-term" autocomplete="off" maxlength="150"></label><button id="search">ค้นหาแบบ exact match</button></div><button id="load">โหลดรายชื่อ</button><button id="more" hidden>รายชื่อถัดไป</button><div id="patients"></div></section>
<section id="clinical" hidden><h2 id="heading">ข้อมูลผู้ป่วย</h2><button id="new">ทะเบียนใหม่ / ล้างข้อมูลบนจอ</button><p id="code"></p><label>ชื่อ<input id="name" maxlength="150" autocomplete="off"></label><label>วันเกิด (ไม่ทราบให้เว้นว่าง)<input id="birth_date" type="date" autocomplete="off"></label><label>เบอร์ติดต่อ<input id="phone" maxlength="30" autocomplete="off"></label><label>ประวัติแพ้ยาที่บันทึกเอง (ว่าง = ยังไม่ได้บันทึก)<textarea id="allergies" maxlength="4000" autocomplete="off"></textarea></label><p>ไม่มีระบบวินิจฉัยหรือเตือนแพ้ยา/ข้อห้ามใช้อัตโนมัติ</p><label><input id="synthetic" type="checkbox"> ยืนยันใช้ข้อมูลสังเคราะห์สำหรับ local</label><button id="save">บันทึกทะเบียน / revision ใหม่</button>
<h2>บันทึกรับบริการ</h2><label>วันที่รับบริการ<input id="occurred_on" type="date"></label><label>บันทึก<textarea id="note" maxlength="5000" autocomplete="off"></textarea></label><label>รหัสบันทึกเดิมที่ต้องการแก้ (ถ้ามี)<input id="supersedes_id" autocomplete="off"></label><label>เหตุผลแก้ไข<textarea id="correction_reason" maxlength="1000" autocomplete="off"></textarea></label><button id="visit">เพิ่มบันทึก — ไม่ทับต้นฉบับ</button><button id="new-visit">ล้างแบบฟอร์มรับบริการ / เริ่มรายการใหม่</button>
<h2>ประวัติ</h2><button id="revisions">อ่าน revisions</button><button id="visits">อ่านบันทึกรับบริการ</button><button id="dispensing">อ่านรายการจากบิล</button><button id="timeline-more" hidden>ประวัติถัดไป</button><p>รายการจากบิลไม่ยืนยันว่าผู้ป่วยใช้ยานั้นแล้ว รายการคืนเงินแสดงแยก ไม่ลบประวัติเดิม</p><div id="timeline"></div></section><button id="clear">ซ่อนข้อมูล / ล้างหน้าจอ</button>`;
const el = <T extends HTMLElement>(id: string) =>
  document.getElementById(id) as T;
const value = (id: string) => el<HTMLInputElement>(id).value;
const set = (id: string, text: string) => {
  el<HTMLInputElement>(id).value = text;
};
const message = (text: string) => {
  const node = document.getElementById("message");
  if (node) node.textContent = text;
};
let selected: { id: string; version: number } | null = null,
  profileKey = crypto.randomUUID(),
  visitKey = crypto.randomUUID();
let cursor: string | null = null,
  timelineCursor: string | null = null,
  timelineKind = "",
  epoch = 0,
  controller = new AbortController();
let idleTimer: ReturnType<typeof setTimeout> | undefined;
let boundScope: string | null = null;
function clear() {
  epoch++;
  set("search-term", "");
  el("clinical-search").hidden = true;
  boundScope = null;
  selectionEpoch++;
  controller.abort();
  controller = new AbortController();
  selected = null;
  profileKey = crypto.randomUUID();
  visitKey = crypto.randomUUID();
  for (const id of [
    "name",
    "birth_date",
    "phone",
    "allergies",
    "occurred_on",
    "note",
    "supersedes_id",
    "correction_reason",
  ])
    set(id, "");
  el<HTMLInputElement>("synthetic").checked = false;
  el("patients").replaceChildren();
  el("timeline").replaceChildren();
  el("code").textContent = "";
  el("clinical").hidden = true;
  el("more").hidden = true;
  el("timeline-more").hidden = true;
  cursor = null;
  timelineCursor = null;
  if (idleTimer) clearTimeout(idleTimer);
  message("ซ่อนข้อมูลแล้ว โหลดรายชื่อใหม่เพื่อยืนยันสิทธิ์");
}
async function api(path: string, body?: unknown) {
  const context = epoch;
  const response = await fetch("/patients" + path, {
    method: body === undefined ? "GET" : "POST",
    credentials: "same-origin",
    cache: "no-store",
    signal: controller.signal,
    headers: {
      Accept: "application/json",
      ...(body === undefined
        ? {}
        : {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN":
              document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content || "",
          }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  if ([401, 403, 404, 419, 503].includes(response.status)) {
    clear();
    throw new Error("ไม่สามารถอ่านข้อมูลได้ กรุณาตรวจสิทธิ์/เข้าสู่ระบบใหม่");
  }
  const data = await response.json();
  if (epoch !== context) throw new Error("Session context changed");
  if (!response.ok) throw new Error(data.message || "ทำรายการไม่สำเร็จ");
  const scope = response.headers.get("X-POS-Patient-Scope");
  if (!scope || (boundScope && boundScope !== scope)) {
    clear();
    throw new Error("สาขาหรือสิทธิ์เปลี่ยน ต้องโหลดรายชื่อใหม่");
  }
  boundScope = scope;
  const remaining = Number(response.headers.get("X-POS-Session-Remaining"));
  if (!Number.isFinite(remaining) || remaining <= 0) {
    clear();
    throw new Error("Session หมดอายุ");
  }
  if (idleTimer) clearTimeout(idleTimer);
  idleTimer = setTimeout(clear, Math.min(remaining, 900) * 1000);
  return data;
}
let selectionEpoch = 0;
function newProfile() {
  selectionEpoch++;
  selected = null;
  profileKey = crypto.randomUUID();
  for (const id of ["name", "birth_date", "phone", "allergies"]) set(id, "");
  el("code").textContent = "ทะเบียนใหม่";
  el("timeline").replaceChildren();
  newVisit();
}
function newVisit() {
  visitKey = crypto.randomUUID();
  for (const id of [
    "occurred_on",
    "note",
    "supersedes_id",
    "correction_reason",
  ])
    set(id, "");
}
async function detail(id: string) {
  const selection = ++selectionEpoch;
  const data = await api("/" + id);
  if (selection !== selectionEpoch) return;
  selected = { id: data.id, version: data.version };
  profileKey = crypto.randomUUID();
  el("code").textContent = data.code + " · revision " + data.version;
  for (const field of ["name", "birth_date", "phone", "allergies"])
    set(field, data.profile[field] || "");
  el("timeline").replaceChildren();
  newVisit();
  message("อ่านข้อมูลแล้ว — มี access audit");
}
async function load(next = false) {
  const data = await api(
    "/options" + (next && cursor ? "?after=" + encodeURIComponent(cursor) : ""),
  );
  showPatients(data, next);
}
function showPatients(data: any, next = false) {
  el("clinical-search").hidden = !data.clinical_access;
  cursor = data.next;
  el("more").hidden = !cursor;
  el("clinical").hidden = !data.clinical_access;
  if (!data.clinical_access) newProfile();
  const list = el("patients");
  if (!next) list.replaceChildren();
  if (!next && data.items.length === 0) {
    const empty = document.createElement("p");
    empty.textContent = "ไม่พบผู้ป่วยในรายการนี้";
    list.append(empty);
  }
  for (const patient of data.items) {
    const article = document.createElement("article");
    const label = document.createElement("span");
    label.textContent = patient.code + " · " + patient.name_masked;
    article.append(label);
    if (data.clinical_access) {
      const button = document.createElement("button");
      button.textContent = "อ่านรายละเอียด";
      button.onclick = () => run(() => detail(patient.id));
      article.append(button);
    }
    list.append(article);
  }
  message(
    data.clinical_access
      ? "สิทธิ์อ่าน/บันทึกประวัติพร้อม"
      : "เห็นเฉพาะข้อมูลที่ mask เลือกผู้ป่วยได้ที่หน้าขาย",
  );
}
async function run(work: () => Promise<void> | void) {
  try {
    await work();
  } catch (error) {
    message(error instanceof Error ? error.message : String(error));
  }
}
function action(id: string, work: () => Promise<void> | void) {
  el(id).onclick = async () => {
    const button = el<HTMLButtonElement>(id);
    button.disabled = true;
    await run(work);
    button.disabled = false;
  };
}
action("load", () => load());
action("search", async () => {
  const data = await api("/search", {
    field: value("search-field"),
    term: value("search-term"),
  });
  showPatients(data);
  set("search-term", "");
  message("ค้นหาแบบ exact match แล้ว ไม่จับคู่หรือรวมประวัติอัตโนมัติ");
});
action("more", () => load(true));
action("clear", clear);
action("new", newProfile);
action("new-visit", newVisit);
action("save", async () => {
  if (!el<HTMLInputElement>("synthetic").checked)
    throw new Error("ต้องยืนยันข้อมูลสังเคราะห์");
  const result = await api(selected ? "/" + selected.id : "", {
    request_key: profileKey,
    expected_version: selected?.version || 0,
    synthetic_confirmed: true,
    profile: {
      name: value("name"),
      birth_date: value("birth_date") || null,
      phone: value("phone") || null,
      allergies: value("allergies"),
    },
  });
  await detail(result.id);
  message("บันทึก revision " + result.version + " แล้ว");
});
action("visit", async () => {
  if (!selected) throw new Error("เลือกผู้ป่วยก่อน");
  if (!el<HTMLInputElement>("synthetic").checked)
    throw new Error("ต้องยืนยันข้อมูลสังเคราะห์");
  await api("/" + selected.id + "/visits", {
    request_key: visitKey,
    synthetic_confirmed: true,
    occurred_on: value("occurred_on"),
    note: value("note"),
    supersedes_id: value("supersedes_id") || null,
    correction_reason: value("correction_reason") || null,
  });
  newVisit();
  message("เพิ่มบันทึกแล้ว ต้นฉบับคงอยู่");
});
async function timeline(kind: string, next = false) {
  if (!selected) throw new Error("เลือกผู้ป่วยก่อน");
  const result = await api(
    "/" +
      selected.id +
      "/" +
      kind +
      (next && timelineCursor
        ? "?after=" + encodeURIComponent(timelineCursor)
        : ""),
  );
  timelineKind = kind;
  timelineCursor = result.next;
  el("timeline-more").hidden = !timelineCursor;
  const list = el("timeline");
  if (!next) list.replaceChildren();
  for (const item of result.items) {
    const card = document.createElement("article");
    const pre = document.createElement("pre");
    if (kind === "revisions") {
      pre.textContent = `Revision ${item.version} · ${item.created_at}\nชื่อ: ${item.data.name}\nวันเกิด: ${item.data.birth_date || "ไม่ได้บันทึก"}\nเบอร์: ${item.data.phone || "ไม่ได้บันทึก"}\nประวัติแพ้ยา: ${item.data.allergies || "ยังไม่ได้บันทึก"}`;
    } else if (kind === "visits") {
      pre.textContent = `${item.data.occurred_on} · ${item.created_at}\n${item.data.note}\n${item.supersedes_id ? "แก้ไขบันทึก " + item.supersedes_id + "\nเหตุผล: " + item.data.correction_reason : "บันทึกต้นฉบับ"}`;
      const correction = document.createElement("button");
      correction.textContent = "แก้ไขโดยเพิ่มบันทึก";
      correction.onclick = () => {
        newVisit();
        set("supersedes_id", item.id);
        set("occurred_on", item.data.occurred_on);
        set("note", item.data.note);
        message("กรอกเหตุผลและบันทึกใหม่ ต้นฉบับจะยังอยู่");
      };
      card.append(correction);
    } else {
      pre.textContent = `${item.receipt_no} · ${item.created_at}\nยอดบิล ${item.total} / คืนเงิน ${item.refunded_total}\n${item.lines.map((line: { name: string; quantity: string; unit: string }) => `${line.name} · ${line.quantity} ${line.unit}`).join("\n")}`;
    }
    card.append(pre);
    list.append(card);
  }
  message("อ่านประวัติแล้ว — มี access audit");
}
for (const kind of ["revisions", "visits", "dispensing"])
  action(kind, () => timeline(kind));
action("timeline-more", () => timeline(timelineKind, true));
window.addEventListener("pagehide", clear);
document.addEventListener("visibilitychange", () => {
  if (document.hidden) clear();
});
window.addEventListener("offline", clear);
window.addEventListener("pageshow", (event) => {
  if (event.persisted) location.reload();
});
const channel = new BroadcastChannel("pos-offline-auth-v1");
channel.onmessage = clear;

let lastPrivacyTick = performance.now(),
  lastPrivacyWall = Date.now();
setInterval(() => {
  const mono = performance.now(),
    wall = Date.now();
  if (
    mono - lastPrivacyTick > 5000 ||
    mono < lastPrivacyTick ||
    Math.abs(wall - lastPrivacyWall - (mono - lastPrivacyTick)) > 2000
  )
    clear();
  lastPrivacyTick = mono;
  lastPrivacyWall = wall;
}, 1000);
