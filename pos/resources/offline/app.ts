import { EncryptedQueue, QueueScope, CashPermit } from "./EncryptedQueue";
import { QueueSync } from "./QueueSync";
import { TrustedClock } from "./TrustedClock";
const root = document.getElementById("offline-root")!;
root.innerHTML = `<style>body{font:16px system-ui;background:#f3f6f7;color:#18343b;margin:0}main{max-width:950px;margin:auto;padding:24px}section{background:white;padding:20px;border-radius:12px;margin:16px 0}input,select,textarea,button{font:inherit;padding:10px;box-sizing:border-box;max-width:100%;margin:5px}button{background:#123b45;color:white;border:0;border-radius:6px;cursor:pointer}label{display:block}textarea{width:100%;min-height:100px}pre{white-space:pre-wrap;overflow-wrap:anywhere}#message{padding:14px;background:#fff0c0}#queue{display:grid;gap:8px}article{padding:12px;border:1px solid #ccd9db;border-radius:8px}@media(max-width:500px){main{padding:12px}input,select,button{width:100%;margin:5px 0}}</style>
<h1>POS Offline · Local</h1><p><a href="/">กลับหน้าขาย / เข้าสู่ระบบ</a></p>
<p id="message" role="status">เปิดออนไลน์เพื่อยืนยันผู้ใช้และเวลา ก่อนรับสิทธิ์ขายเงินสด</p>
<section><h2>เครื่องและคิว</h2><button id="connect">ตรวจ session ออนไลน์</button><label>Terminal<select id="terminal"></select></label><label>Device ID<input id="device" autocomplete="off"></label><label>รหัสปลดล็อกเครื่อง (12–128 ตัวอักษร)<input id="passphrase" type="password" autocomplete="off"></label><button id="initialize">สร้างคิวใหม่</button><button id="unlock">ปลดล็อกคิวเดิม</button><button id="lock">ล็อก</button><button id="logout">ออกจากระบบ</button><p id="storage"></p></section>
<section><h2>สิทธิ์เงินสด offline</h2><p>เฉพาะสินค้าที่ Manager อนุญาตและมี quota ล่วงหน้า เลข OFF เป็นหลักฐานชั่วคราว ยังไม่ใช่ ABB/FULL ทุกบิลรอตรวจรับเงินโดย Manager</p><label>รายการจอง quota (JSON)<textarea id="quota" placeholder='[{"product_id":"UUID","quantity":"2.0000"}]'></textarea></label><button id="permit">รับ permit ใหม่</button><button id="time">รับรองเวลา permit เดิม</button><pre id="permit-info"></pre><label>สินค้า<select id="product"></select></label><label>จำนวน<input id="quantity" value="1.0000" inputmode="decimal"></label><button id="cash">บันทึกบิลเงินสดลงเครื่อง</button><p>รอข้อความบันทึกสำเร็จก่อนรับเงิน หากเขียนไม่สำเร็จ หยุดรับรายการใหม่</p></section>
<section><h2>คำขอรอตรวจรับ</h2><p>งานร้านอาหาร/Delivery/สมาชิก/โปรโมชัน/ยาที่ต้องตรวจเภสัชกร เก็บเป็นคำขอเท่านั้น ห้ามแสดงว่าส่งครัว จัดส่ง จ่ายยา หรือใช้สิทธิ์สำเร็จ</p><label>ประเภท<select id="draft-kind"><option value="sale.draft">คำขอขาย / สมาชิก / เภสัชกร</option><option value="restaurant.order.draft">คำขอร้านอาหาร</option><option value="delivery.request">คำขอ Delivery</option></select></label><label>หมายเหตุคำขอ (ไม่ใส่ชื่อ เบอร์โทร หรือเวชระเบียน)<textarea id="draft-note"></textarea></label><button id="draft">เก็บคำขอ</button></section>
<section><h2>คิวบนเครื่อง</h2><button id="refresh">แสดงคิว</button><button id="sync">Sync หลังยืนยัน session</button><div id="queue"></div></section>
<section><h2>Manager</h2><p>ต้องมีกะของตนเปิดอยู่ รับเงินเข้ากะปัจจุบันและตรวจหลักฐานก่อนยืนยัน ไม่คิดเงินเพิ่มอัตโนมัติ</p><label>รหัสผ่าน Manager<input id="manager-password" type="password" autocomplete="off"></label><label>Policy (JSON)<textarea id="policy"></textarea></label><button id="save-policy">บันทึก policy</button><button id="review">โหลดรายการ review</button><button id="review-next">รายการ review ถัดไป</button><button id="leases">ตรวจ permit / คืน quota ที่ไม่ใช้</button><div id="leases-list"></div><div id="reviews"></div></section>`;
const element = <T extends HTMLElement>(id: string) =>
  document.getElementById(id) as T;
const value = (id: string) => element<HTMLInputElement>(id).value;
const message = (text: string) => {
  element("message").textContent = text;
};
let session:
  | {
      csrf_token: string;
      instance_id: string;
      branch_id: string;
      staff_id: string;
      role: string;
      server_now: string;
      policy: Record<string, unknown>;
      terminals: {
        id: string;
        code: string;
      }[];
    }
  | undefined;
let queue: EncryptedQueue | undefined;
let bound: QueueScope | undefined;
const clock = new TrustedClock();
let permitRequest: string | undefined;
let permitRequestBody: string | undefined;
const scopeKey = "pos-offline-scope-v1";
try {
  const stored = JSON.parse(
    localStorage.getItem(scopeKey) || "null",
  ) as QueueScope | null;
  if (stored) {
    bound = stored;
    element<HTMLInputElement>("device").value = stored.deviceId;
    const o = new Option(stored.terminalId, stored.terminalId);
    element<HTMLSelectElement>("terminal").add(o);
  }
} catch {
  message("ข้อมูลเครื่องเสียหาย ห้ามล้างคิว ให้ Manager ตรวจหลักฐาน");
}
if (!value("device"))
  element<HTMLInputElement>("device").value = crypto.randomUUID();
async function api(path: string, body?: unknown): Promise<any> {
  const response = await fetch("/offline/" + path, {
    method: body === undefined ? "GET" : "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
      ...(body === undefined
        ? {}
        : {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": session?.csrf_token || "",
          }),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  if ([401, 419].includes(response.status)) {
    queue?.lock();
    clock.invalidate("authentication_required");
    session = undefined;
    throw new Error("Session หมดอายุ: เข้าสู่ระบบใหม่ คิวเดิมยังอยู่");
  }
  if (!response.ok)
    throw new Error(
      (
        await response
          .json()
          .catch(() => ({ message: "HTTP " + response.status }))
      ).message || "HTTP " + response.status,
    );
  return response.json();
}
async function connect() {
  const live = await api("session");
  if (
    bound &&
    (live.instance_id !== bound.instanceId ||
      live.branch_id !== bound.branchId ||
      live.staff_id !== bound.staffId)
  ) {
    queue?.lock();
    throw new Error("ผู้ใช้/สาขาไม่ตรงคิวเดิม ต้องเข้าสู่ระบบเจ้าของคิว");
  }
  session = live;
  const select = element<HTMLSelectElement>("terminal");
  const previous = bound?.terminalId || select.value;
  select.replaceChildren();
  for (const t of session!.terminals) select.add(new Option(t.code, t.id));
  select.value = previous || session!.terminals[0]?.id || "";
  element<HTMLTextAreaElement>("policy").value = JSON.stringify(
    { expected_version: session!.policy.version, ...session!.policy },
    null,
    2,
  );
  message("ยืนยัน session แล้ว คิวต้องปลดล็อกก่อนใช้งาน");
}
async function open(initialize = false) {
  if (initialize) {
    if (!session) throw new Error("ต้องยืนยัน session ออนไลน์ก่อนสร้างคิว");
    bound = {
      instanceId: session.instance_id,
      branchId: session.branch_id,
      staffId: session.staff_id,
      terminalId: value("terminal"),
      deviceId: value("device"),
    };
    await api("devices", {
      device_id: bound.deviceId,
      terminal_id: bound.terminalId,
      label: "Local browser",
    });
  }
  if (!bound)
    throw new Error(
      "ไม่พบข้อมูลเครื่องเดิม ห้ามล้าง storage ให้ Manager ตรวจสอบ",
    );
  queue?.close();
  queue = await EncryptedQueue.open(bound);
  const password = value("passphrase");
  element<HTMLInputElement>("passphrase").value = "";
  if (initialize) {
    await queue.initialize(password, {
      maxTransactions: Number(session!.policy.max_offline_transactions),
      maxAmountCents: String(
        Math.round(Number(session!.policy.max_offline_amount) * 100),
      ),
    });
    localStorage.setItem(scopeKey, JSON.stringify(bound));
  } else await queue.unlock(password);
  const storage = await EncryptedQueue.storage();
  element("storage").textContent =
    `พื้นที่เหลือ ${Math.floor(storage.freeBytes / 1048576)} MB · Persistent Storage: ${storage.persistent ? "อนุญาต" : "ไม่อนุญาต — browser อาจล้างข้อมูลได้"} · หากลืมรหัส ไม่มี recovery`;
  await render();
  message(
    "ปลดล็อกคิวแล้ว เปิดโปรแกรมใหม่ต้องรับรองเวลาออนไลน์ใหม่ก่อนขายเงินสด",
  );
}
async function render() {
  if (!queue) throw new Error("ปลดล็อกคิวก่อน");
  const rows = await queue.entries();
  const list = element("queue");
  list.replaceChildren();
  for (const row of rows) {
    const item = document.createElement("article");
    item.textContent = `${row.data.provisional_receipt || row.id} · ${row.kind} · ${row.status} · ${(Number(row.amountCents) / 100).toFixed(2)} บาท${row.acknowledgement?.sale_id ? " · sale " + row.acknowledgement.sale_id : ""}`;
    list.append(item);
  }
  const p = await queue.permit();
  const select = element<HTMLSelectElement>("product");
  select.replaceChildren();
  if (p) {
    element("permit-info").textContent =
      `Permit ${p.id}\nหมดอายุ ${p.expires_at}\n${p.policy.max_offline_transactions} บิล / ${p.policy.max_offline_amount} บาท`;
    for (const item of p.items)
      select.add(
        new Option(
          `${item.name} · ${item.unit_price} + VAT ${item.tax_rate}%`,
          item.product_id,
        ),
      );
  }
}
const action = (id: string, run: () => Promise<void> | void) =>
  element(id).addEventListener("click", async () => {
    const button = element<HTMLButtonElement>(id);
    button.disabled = true;
    try {
      await run();
    } catch (error) {
      message(error instanceof Error ? error.message : String(error));
    } finally {
      button.disabled = false;
    }
  });
action("connect", connect);
action("initialize", () => open(true));
action("unlock", () => open());
action("refresh", render);
action("lock", () => {
  queue?.lock();
  clock.invalidate("locked");
  element("queue").replaceChildren();
  message("ล็อกแล้ว คิวเดิมยังอยู่");
});
action("logout", async () => {
  queue?.lock();
  clock.invalidate("logged_out");
  element("queue").replaceChildren();
  const token = session?.csrf_token;
  session = undefined;
  await fetch("/logout", {
    method: "POST",
    credentials: "same-origin",
    headers: { "X-CSRF-TOKEN": token || "", Accept: "application/json" },
  });
  message("ออกจากระบบแล้ว คิวเดิมยังอยู่");
});
action("permit", async () => {
  if (!queue || !bound) throw new Error("ปลดล็อกคิวก่อน");
  await connect();
  const requestBody = JSON.stringify(JSON.parse(value("quota")));
  if (permitRequestBody !== requestBody) {
    permitRequest = crypto.randomUUID();
    permitRequestBody = requestBody;
  }
  const start = performance.now();
  const permit = (await api("permits", {
    device_id: bound.deviceId,
    request_key: permitRequest,
    items: JSON.parse(requestBody),
  })) as CashPermit;
  await queue.installPermit(permit);
  // Fetch a fresh authenticated time sample; a replayed permit response is not a new clock sample.
  const freshStart = performance.now();
  const live = await api("session");
  clock.begin(
    {
      id: permit.id,
      version: permit.policy.version,
      serverNow: live.server_now,
      expiresAt: permit.expires_at,
    },
    freshStart,
  );
  await render();
  message("รับ permit และเวลาใหม่แล้ว");
});
action("cash", async () => {
  if (!queue) throw new Error("ปลดล็อกคิวก่อน");
  const evidence = clock.evidence();
  if (!evidence.eligible)
    throw new Error("ต้องรับรองเวลาออนไลน์ใหม่: " + evidence.reason);
  const entry = await queue.enqueue(
    {
      kind: "sale.draft",
      amountCents: "0",
      data: {
        offline_cash: true,
        clock: evidence,
        items: [{ product_id: value("product"), quantity: value("quantity") }],
      },
    },
    () => clock.evidence().eligible,
  );
  await render();
  message(
    `บันทึกหลักฐานสำเร็จ ${entry.data.provisional_receipt} · ${(Number(entry.amountCents) / 100).toFixed(2)} บาท · รอ Manager ตรวจรับ ยังไม่ใช่ ABB/FULL`,
  );
});
action("draft", async () => {
  if (!queue) throw new Error("ปลดล็อกคิวก่อน");
  await queue.enqueue({
    kind: value("draft-kind") as "sale.draft",
    amountCents: "0",
    data: { note: value("draft-note") },
  });
  element<HTMLTextAreaElement>("draft-note").value = "";
  await render();
  message("เก็บคำขอแล้ว ยังไม่ส่งครัว/จัดส่ง/จ่ายยา/ใช้สิทธิ์");
});
action("sync", async () => {
  if (!queue || !bound) throw new Error("ปลดล็อกคิวก่อน");
  await new QueueSync(queue, bound.deviceId, connect, (payload) =>
    api("inbox", payload),
  ).run();
  await render();
  message("Sync แล้ว ดูสถานะ review/synced รายรายการ");
});
action("save-policy", async () => {
  const input = JSON.parse(value("policy"));
  await connect();
  delete input.version;
  const password = value("manager-password");
  element<HTMLInputElement>("manager-password").value = "";
  const result = await api("policies", {
    ...input,
    manager_password: password,
  });
  message("บันทึก policy version " + result.version);
});
let reviewCursor: { at: string; id: string } | undefined;
async function loadReviews(next = false) {
  await connect();
  const query =
    next && reviewCursor
      ? "?" +
        new URLSearchParams({
          cursor_at: reviewCursor.at,
          cursor_id: reviewCursor.id,
        }).toString()
      : "";
  const rows = await api("review" + query);
  const last = rows[rows.length - 1];
  reviewCursor = last
    ? { at: last.server_received_at, id: last.id }
    : undefined;
  const list = element("reviews");
  list.replaceChildren();
  for (const row of rows) {
    const article = document.createElement("article");
    const pre = document.createElement("pre");
    pre.textContent = JSON.stringify(row, null, 2);
    article.append(pre);
    if (row.kind === "sale.draft" && row.evidence.data.permit_id) {
      const note = document.createElement("input");
      note.placeholder = "หลักฐานที่ตรวจและการรับเงินเข้ากะ";
      const button = document.createElement("button");
      button.textContent = "ยืนยันรับเงินเดิมเข้ากะปัจจุบัน";
      button.onclick = async () => {
        button.disabled = true;
        try {
          const password = value("manager-password");
          element<HTMLInputElement>("manager-password").value = "";
          const sale = await api("accept", {
            inbox_id: row.id,
            payload_hash: row.payload_hash,
            terminal_id: value("terminal"),
            cash_received: row.evidence.data.total,
            evidence_note: note.value,
            manager_password: password,
          });
          message("ตรวจรับแล้ว " + sale.receipt_no);
          article.remove();
        } catch (error) {
          message(String(error));
        } finally {
          button.disabled = false;
        }
      };
      article.append(note, button);
    }
    list.append(article);
  }
}
action("review", () => loadReviews());
action("review-next", () => loadReviews(true));
window.addEventListener("offline", () =>
  message(
    "ขาดการเชื่อมต่อ ใช้ได้เฉพาะ permit ที่ยังมีสิทธิ์ คำขออื่นรอตรวจรับ",
  ),
);
window.addEventListener("online", () =>
  message("เชื่อมต่อแล้ว กด Sync เพื่อยืนยัน session และส่งคิว"),
);
if ("serviceWorker" in navigator)
  navigator.serviceWorker
    .register("/offline-sw.js", { scope: "/offline/" })
    .catch((error) =>
      message("ยังติดตั้ง offline shell ไม่สำเร็จ: " + String(error)),
    );
action("leases", async () => {
  await connect();
  const permits = await api("permits");
  const list = element("leases-list");
  list.replaceChildren();
  for (const permit of permits) {
    const article = document.createElement("article");
    const text = document.createElement("pre");
    text.textContent = JSON.stringify(permit, null, 2);
    const note = document.createElement("input");
    note.placeholder = "หลักฐานยืนยันตรวจคิวครบและ quota ส่วนที่เหลือไม่ได้ใช้";
    const label = document.createElement("label");
    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    label.append(
      checkbox,
      document.createTextNode(
        "ตรวจทุกเครื่อง/คิวแล้ว ยืนยันส่วนที่เหลือไม่ได้รับเงินจริง หากคิวหายหรือยังตรวจไม่ได้ ให้คง hold",
      ),
    );
    const button = document.createElement("button");
    button.textContent = "ปิด permit และคืนเฉพาะ quota ไม่ใช้";
    button.onclick = async () => {
      button.disabled = true;
      try {
        if (!checkbox.checked)
          throw new Error("ต้องตรวจหลักฐานและยืนยัน unused ก่อน");
        const password = value("manager-password");
        element<HTMLInputElement>("manager-password").value = "";
        await api("close-permit", {
          permit_id: permit.id,
          accepted_transactions: permit.accepted_transactions,
          unused_confirmed: true,
          evidence_note: note.value,
          manager_password: password,
        });
        article.remove();
        message("ปิด permit แล้ว ส่วนที่ใช้ไม่ถูกคืนซ้ำ");
      } catch (error) {
        message(String(error));
      } finally {
        button.disabled = false;
      }
    };
    article.append(text, note, label, button);
    list.append(article);
  }
});
const channel = new BroadcastChannel("pos-offline-auth-v1");
channel.onmessage = () => {
  queue?.lock();
  session = undefined;
  clock.invalidate("authentication_changed");
  element("queue").replaceChildren();
  element("reviews").replaceChildren();
  message("Session เปลี่ยนจากอีกแท็บ ต้องยืนยันตัวตนและปลดล็อกใหม่");
};
element("logout").addEventListener("click", () =>
  channel.postMessage("logout"),
);
window.addEventListener("pagehide", () => {
  queue?.lock();
  element("queue").replaceChildren();
  element("reviews").replaceChildren();
});
action("time", async () => {
  if (!queue) throw new Error("ปลดล็อกคิวก่อน");
  const p = await queue.permit();
  if (!p) throw new Error("ยังไม่มี permit");
  const start = performance.now();
  await connect();
  clock.begin(
    {
      id: p.id,
      version: p.policy.version,
      serverNow: session!.server_now,
      expiresAt: p.expires_at,
    },
    start,
  );
  message("รับรองเวลา permit เดิมแล้ว สิทธิ์ไม่ถูกต่ออายุ");
});
let lastActivity = performance.now(),
  unlockedAt = performance.now();
for (const name of ["pointerdown", "keydown"])
  window.addEventListener(name, () => {
    lastActivity = performance.now();
  });
for (const id of ["initialize", "unlock"])
  element(id).addEventListener("click", () => {
    unlockedAt = performance.now();
    lastActivity = unlockedAt;
  });
setInterval(() => {
  if (
    queue?.isUnlocked() &&
    (performance.now() - lastActivity > 900000 ||
      performance.now() - unlockedAt > 28800000)
  ) {
    queue.lock();
    clock.invalidate("session_timeout");
    element("queue").replaceChildren();
    element("reviews").replaceChildren();
    message(
      "หมดเวลาใช้งาน ต้องปลดล็อกใหม่ คิวเดิมยังอยู่ และยืนยัน session ก่อน sync",
    );
  }
}, 1000);
