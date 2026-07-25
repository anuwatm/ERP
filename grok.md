# Grok: Checklist + README Review (ไม่ซ้ำ `gemini.md`)

**วันที่:** 2026-07-25  
**ขอบเขต:** `checklist.md`, root `README.md`, เทียบ `docs/PHASE_ACCEPTANCE_CRITERIA.md` / Phase 1 plan / routes  
**อ้างอิงคู่:** [`gemini.md`](./gemini.md) — อย่าทำรายการซ้ำ; เอกสารนี้เติมช่องว่าง + ยืนยัน/แก้สถานะจริงบน disk  

---

## 0. สรุปสั้น

| สถานะ | ความหมาย |
| --- | --- |
| Phase 0 checklist | ติ๊ก Done เกือบครบ แต่ **ไฟล์ decision บางตัวหายจาก disk** |
| Phase 1–5 checklist | โครงดี พอเริ่ม code ได้ แต่ **ยังขาด DoD/security/route/seed หลายข้อ** |
| README | ใช้เป็น hub ได้ แล้ว; ลิงก์ docs หลักครบขึ้น — **ยังขาด tracking/seed/decision notes บางอัน** |
| เทียบ Gemini | รับ gaps ด้าน security ที่ Gemini ชี้ — **ส่วนใหญ่ยังไม่ถูกใส่ใน checklist** |

**บล็อกก่อน coding จริง (P0):** แก้สถานะไฟล์ `gpt.md` / `grok.md` ให้ตรง checklist + README

---

## 1. สถานะไฟล์จริงบน disk (Critical — ยืนยัน Gemini บางส่วน)

ตรวจ 2026-07-25:

| ไฟล์ | อยู่จริง? | checklist / README ว่าอย่างไร |
| --- | --- | --- |
| `checklist.md` | **มี** | ใช้เป็น tracking หลัก |
| `README.md` | **มี** | hub โปรเจกต์ |
| `gemini.md` | **มี** | checklist ติ๊กสร้างแล้ว; README ลิงก์แล้ว |
| `gpt.md` | **ไม่มี** | checklist `[x] สร้าง gpt.md/gemini.md` — **ไม่ตรงความจริง** |
| `grok.md` | **ไม่มี** (ก่อนรอบนี้) | ไม่ถูกอ้างใน README; checklist ไม่แยก grok |
| `backend/` | **ไม่มี** | ถูกต้องกับ “ยังไม่เริ่ม Phase 1” |

### ข้อเสนอ (P0)

1. **สร้าง/กู้ `gpt.md` กลับมา** ถ้ายังต้องการเป็น decision log ของ GPT — หรือแก้ checklist เป็น  
   `- [x] สร้าง gemini.md` / `- [ ] gpt.md (optional / หาย — ต้อง recreate)`  
2. **README** ควรลิงก์ decision notes ที่มีจริง: `gemini.md`, `grok.md` (ไฟล์นี้), และ `gpt.md` เมื่อมี  
3. อย่าติ๊ก `[x]` งานเอกสารที่ไฟล์ไม่อยู่บน disk  

> **ไม่ซ้ำ Gemini:** Gemini บอกว่าไม่มีทั้ง gpt และ gemini — **อัปเดต:** ตอนนี้ `gemini.md` มีแล้ว; **ยังขาด `gpt.md`**

---

## 2. สิ่งที่ Gemini ชี้แล้ว — สถานะกับ checklist (ไม่เขียนซ้ำรายละเอียด)

Gemini ขอเพิ่มใน checklist:

| รายการ Gemini | อยู่ใน `checklist.md` ตอนนี้? | หมายเหตุ Grok |
| --- | --- | --- |
| Phase 1 re-auth (password confirm) | **บางส่วน** — มี “Sensitive action re-auth สำหรับ role/user change” | ควรแยก test ชัด + ครอบ disable/invite |
| Phase 1 `person_id` mask | **ไม่มี** | เห็นด้วย Gemini — ต้องเพิ่ม |
| Phase 1 session rotation + security headers | **บางส่วน** — มี test regenerate session | ยังไม่มี headers / CSRF / SameSite checklist |
| Phase 1 `last_login_at` + audit login | audit login **มี**; `last_login_at` **ไม่มีเป็นข้อ** | เพิ่ม 1 บรรทัด |
| Phase 3 idempotency key | **ไม่มี** | เห็นด้วย — เพิ่ม |
| Phase 3 reversal `payment_date = CURRENT_DATE` | **ไม่มี** (มี reversal ทั่วไป) | เห็นด้วย — เพิ่ม test |
| Phase 3 attachment download permission | **ไม่มี** (มี attachment upload) | เห็นด้วย — เพิ่ม |
| Phase 4 no `actual_cost` | **บางส่วน** — “derived … expenses” | เพิ่ม test “schema ไม่มี actual_cost” |
| Phase 4 reassign owner | **มีแล้ว** | OK |
| Phase 5 log redaction / no secrets | **ไม่มี** (มี Security review กว้าง) | เห็นด้วย — แตกข้อ |
| Phase 5 no Cash Balance API leak | **บางส่วน** — “No Cash Balance shown” | เพิ่ม test API/props |
| README ลิงก์ security/validation/routes | **มีแล้วใน README** | Gemini §3.1 เรื่องลิงก์ — **ปิดแล้วบางส่วน** |

**สรุป:** อย่า copy รายการ Gemini ทั้งก้อนมา `grok.md` — **checklist ยังไม่ได้ absorb ช่องว่างส่วนใหญ่** ควรอัปเดต `checklist.md` ครั้งเดียวรวมของ Gemini + ของ Grok ด้านล่าง

---

## 3. ช่องว่างเพิ่มเติมที่ Gemini ยังไม่ครอบ (Grok — non-overlapping)

### 3.1 Phase 1 — ติดขัด/หลงลืม

| ช่องว่าง | ทำไมสำคัญ | เสนอเพิ่มใน checklist |
| --- | --- | --- |
| **Screens/routes ตาม `ROUTES_AND_SCREENS`** | มี migration + flow แต่ไม่มีติ๊ก “หน้า UI” | `[ ] UI: /settings/organization`, `organization-structure`, `/users`, invite, `/roles`, `/audit-logs` |
| **Middleware `permission:*`** | Phase 1 plan ระบุ; checklist พูด RBAC ทั่วไป | `[ ] Middleware permission:{code}` + policy |
| **Seed `number_sequences` (branch/division/department)** | มี migration sequences แต่ไม่มี seed/algorithm task | `[ ] Seed sequences + gen code 000001→000002` |
| **Settings ใช้งานได้** | มี migration settings; AC ต้องจัดการ org/settings | `[ ] Organization settings read/update` |
| **Invite one-time + TTL 72h** | อยู่ใน VALIDATION/AC; checklist ไม่มี test | `[ ] Test invite expire / reuse reject` |
| **Reset/verify token expire + no replay** | อยู่ใน Phase 1 tests doc | `[ ] Test password reset + email verify token rules` |
| **Inertia props ไม่รั่ว `password` / `remember_token`** | Security/AD | `[ ] Test props redaction` |
| **Seed demo ตาม `SEED_DATA.md` Phase 1** | UAT ภายหลังพึ่ง seed | `[ ] Seed demo org + owner + roles + sequences` |
| **Permission catalog ครบ module.action ที่ Phase 1 ใช้** | กัน 403 หลอก | `[ ] permissions seed ครอบ users/roles/settings/audit/dashboard` |
| **`docs/PHASE_1_LOGIN_IMPLEMENTATION` table ยัง Not started ทั้งแถว** | สอง source of truth กับ checklist | กฎ: ติ๊ก checklist แล้วอัปเดตตาราง Phase 1 doc ด้วย (หรือชี้ไป checklist อย่างเดียว) |

### 3.2 Phase 2 — หลงลืม

| ช่องว่าง | เสนอ |
| --- | --- |
| Gen `customer_code` จาก number_sequences | task ชัด |
| Primary contact: หนึ่ง primary ต่อ customer | test/app rule |
| **ห้าม Sales Dashboard โชว์ Cash In** | test negative (AC Phase 2) |
| Stale deal definition (เช่น ไม่มี activity 7 วัน) | นิยาม + widget test |
| Activity polymorphic allowlist | validation test |

### 3.3 Phase 3 — หลงลืม (นอกเหนือ Gemini)

| ช่องว่าง | เสนอ |
| --- | --- |
| **Net Cash Flow** metric บน Finance Dashboard | checklist ตอนนี้ “Finance Dashboard metrics” กว้างเกินไป — แตกตาม AC |
| Gross Profit (ถ้าโชว์) | ระบุหรือ “ไม่โชว์ใน Phase 3” |
| Overdue invoice job/cron | `[ ] Mark overdue job` |
| Concurrent double-pay test | `[ ] Concurrent payment no overpay` |
| Finance re-auth: void / reverse / approve expense | แยกจาก Phase 1 re-auth |
| `storage_key` server-generated only | test/upload path |
| Expense `supplier_id` ไม่ FK / ไม่บังคับ | negative test |
| Invoice numbering sequence | seed + gen |

### 3.4 Phase 4 — หลงลืม

| ช่องว่าง | เสนอ |
| --- | --- |
| `progress_percent` manual only | task/test |
| Task `blocked` ไม่ mark overdue | test |
| task_checklists มี `org_id` (tenant) | migration check |
| **ไม่มี** `project_members` จน UAT gate | ทำแล้วใน checklist — คงไว้; อย่า implement ก่อน gate |
| Internal task (`project_id` null) | task ชัด |

### 3.5 Phase 5 — หลงลืม

| ช่องว่าง | เสนอ |
| --- | --- |
| UAT expected numbers จาก `SEED_DATA` | แตก “fixture expected values ทุก dashboard” |
| Multi-role UNION permission test | 1 user 2 roles |
| E2E `needs_sales_review` หลัง void | คู่ decision gate ที่มีอยู่ |
| ไม่มี export/notifications/public API | negative scope checklist (กัน creep) |

### 3.6 Process / tracking

| ช่องว่าง | เสนอ |
| --- | --- |
| Checklist ไม่ลิงก์ AC ราย phase | หัวแต่ละ Phase: “DoD: `docs/PHASE_ACCEPTANCE_CRITERIA.md` §Phase N” |
| ไม่มี “Exit criteria” ราย phase แบบ Phase 0 | คัดลอก AC สั้น ๆ เป็น Exit criteria ก่อนขึ้น phase ถัดไป |
| สอง checklist: root vs `PHASE_1_LOGIN` table | ล็อก: **root `checklist.md` = SSOT งาน**; Phase 1 doc = รายละเอียด design เท่านั้น หรือ sync สถานะอัตโนมัติ |
| ไม่มี definition “เสร็จ” ต่อ task | กฎ: ติ๊กได้เมื่อมี test ผ่าน (feature test อย่างน้อย) |

---

## 4. README — ติดขัด / หลงลืม (นอกเหนือหรืออัปเดต Gemini)

### 4.1 สิ่งที่ README ทำได้ดีแล้ว

- ระบุ Phase 0 done / Phase 1 not started  
- ชี้ `checklist.md` เป็น tracking  
- ลิงก์ docs สำคัญครบขึ้น (security, validation, routes, AC, Phase 1)  
- สรุป decision lock (no project_members, needs_sales_review, ฯลฯ)  

### 4.2 สิ่งที่ยังขาด / หลอกลวงเล็กน้อย

| ปัญหา | รายละเอียด | เสนอ |
| --- | --- | --- |
| ลิงก์ `gemini.md` อย่างเดียว | ไม่มี `grok.md` / `gpt.md` | เพิ่ม “Decision notes” section |
| ไม่ลิงก์ `docs/SEED_DATA.md` | seed สำคัญ UAT | เพิ่มในเอกสารหลัก |
| ไม่ลิงก์ `docs/modules/README.md` | 31 modules | เพิ่ม |
| ไม่ลิงก์ `docs/README.md` | index docs | เพิ่ม |
| ไม่มี **Quick start Phase 1** | คนใหม่ไม่รู้คำสั่งแรก | บล็อกสั้น: PHP/Composer, MariaDB, `backend/` scaffold, ชี้ `PHASE_1_LOGIN` + checklist Phase 1 |
| ไม่มี **Out of scope สั้น ๆ** | กัน agent เติม notifications/export | bullet 3–5 บรรทัด |
| “อัปเดต README เมื่อจบ Phase” | ยังไม่มี template | ตาราง “Implemented features” ว่างรอ Phase 1 |
| `backend/` ยังไม่มี | ถูกต้อง | ใส่ path เป้า + “ยังไม่สร้าง” |

### 4.3 ข้อความที่ควรแก้ความเข้าใจ

- Phase 0 “Done” **หมายถึง docs lock** — ไม่รวม decision files ครบทุกชื่อถ้า `gpt.md` หาย  
- อย่าเขียนว่า review ครบถ้าไฟล์ decision ไม่ครบบน disk  

---

## 5. ข้อขัดแย้งระหว่างเอกสาร tracking

| แหล่ง A | แหล่ง B | ปัญหา | แก้ |
| --- | --- | --- | --- |
| `checklist` Phase 0 `[x] gpt.md` | disk ไม่มี `gpt.md` | false complete | recreate หรือ uncheck |
| `PROJECT.md` Phase 1 “Not started (docs only)” | `README` Phase 0 done | สอดคล้อง | OK |
| `PHASE_1` implementation table ทุกแถว Not started | checklist Phase 1 ว่าง `[ ]` | OK ตอนนี้ | หลัง code ต้อง sync |
| `docs/README` ยังชี้ `backend/` | folder ไม่มี | คาดหวัง | ใส่ “(ยังไม่ scaffold)” — มีใน docs README แล้วบางส่วน |
| Gemini: README ขาด security links | README ปัจจุบันมีแล้ว | Gemini ล้าบางข้อ | ใช้ README ปัจจุบันเป็นจริง |

---

## 6. รายการที่ **ไม่** ควรยัดเข้า checklist (กันซ้ำ Gemini/scope creep)

อย่าเพิ่มเพราะ review เก่าหรือ agent ดัน:

- `project_members` implement (มีแค่ **UAT decision gate** — ถูกแล้ว)  
- auto-reopen deal (มี gate + `needs_sales_review` — ถูกแล้ว)  
- notifications module / export PDF / suppliers master  
- Cash Balance widget  

---

## 7. Actionable patch list (ลำดับทำ)

### P0 — ก่อนเริ่ม code Phase 1 (เอกสาร/สถานะ)

1. แก้สถานะ `gpt.md` (สร้างใหม่หรือเอาออกจาก checklist `[x]`)  
2. README: section Decision notes → `gemini.md`, `grok.md`, (`gpt.md` ถ้ามี)  
3. ดูด **Gemini §2 Phase 1 gaps** เข้า `checklist.md` (person_id, headers, last_login_at, แยก re-auth tests)  
4. ดูด **Grok §3.1** เข้า checklist (routes UI, permission middleware, sequences seed, invite TTL tests, props redaction)  

### P1 — ระหว่าง Phase 1 coding

5. ติ๊ก checklist ทีละข้อเมื่อ test ผ่าน  
6. อัปเดต `PHASE_1_LOGIN` status table **หรือ** ลบตารางนั้นแล้วชี้มา checklist อย่างเดียว  
7. จบ Phase 1 → README “Implemented features”  

### P2 — ก่อน Phase 2–5

8. แตก Finance Dashboard metrics ตาม AC  
9. ใส่ Gemini Phase 3–5 security items ที่ยังขาด  
10. Exit criteria ต่อ phase = copy จาก `PHASE_ACCEPTANCE_CRITERIA`  

---

## 8. ชุด checkbox แนะนำให้ copy เข้า `checklist.md` (เฉพาะที่ยังขาด)

### Phase 1 — เพิ่ม

```text
### Security / PII (Phase 1)
- [ ] last_login_at อัปเดตเมื่อ login สำเร็จ
- [ ] person_id mask ใน UI / log / Inertia props (ห้ามโชว์เต็มโดยไม่ permission)
- [ ] Session regenerate หลัง login + password reset
- [ ] Security headers / CSRF / session cookie flags (dev+prod notes)
- [ ] Inertia props ห้ามส่ง password, remember_token
- [ ] Re-auth tests: invite, disable user, role change

### Screens (Phase 1)
- [ ] UI Organization settings
- [ ] UI Organization structure (branch/division/department)
- [ ] UI Users + Invite
- [ ] UI Roles
- [ ] UI Audit logs

### Seed / middleware
- [ ] permission middleware ใช้งานจริง
- [ ] Seed number_sequences branch/division/department + algorithm
- [ ] Seed demo ตาม SEED_DATA Phase 1
- [ ] Test invite one-time + TTL
- [ ] Test reset/verify token expire + no replay
```

### Phase 3 — เพิ่ม (คู่ Gemini)

```text
- [ ] Idempotency-Key บน payment receipt/reversal
- [ ] Reversal payment_date = วันที่ reverse จริง
- [ ] Download attachment ตรวจ permission parent ทุกครั้ง
- [ ] Finance Dashboard แตก metrics ตาม AC (รวม Net Cash Flow)
- [ ] Concurrent payment ไม่ overpay
- [ ] Re-auth: void invoice / reverse payment / approve-pay expense
```

### Phase 5 — เพิ่ม (คู่ Gemini)

```text
- [ ] Log/audit ไม่มี password, token, secret, person_id เต็ม
- [ ] ไม่มี Cash Balance ใน UI และ JSON props/API
```

---

## 9. คำตัดสิน: checklist/README พร้อมแค่ไหน

| คำถาม | คำตอบ |
| --- | --- |
| เริ่ม scaffold Phase 1 ได้ไหม? | **ได้** — scope ชัด |
| checklist ครบ DoD ไหม? | **ยังไม่** — ขาด security/PII/routes/seed/tests หลายข้อ (Gemini + Grok) |
| README พอเป็น onboarding ไหม? | **ปานกลาง** — ขาด quick start + decision notes ครบ + seed/modules links |
| Gemini ซ้ำไหม? | gaps security ซ้ำได้ — **Grok ไม่ขยายซ้ำ**; เน้น disk truth, routes, seed, dual SSOT, phase exit criteria |

---

## 10. สิ่งที่ควรทำทันที (3 บรรทัด)

1. **Fix false complete:** `gpt.md` หาย แต่ checklist ติ๊กแล้ว  
2. **Merge Gemini Phase 1 security gaps + Grok routes/seed/middleware เข้า `checklist.md`**  
3. **README:** Decision notes + Quick start Phase 1 + ลิงก์ SEED/modules  

---

*Grok checklist/README audit — เติมช่องว่างที่ไม่ซ้ำ gemini.md; ยืนยันว่า gemini ถูกเรื่อง security gaps และสถานะ gpt.md ยังต้องแก้*
)
