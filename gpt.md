# GPT Decision Log (Clean)

Last updated: 2026-08-28 (Phase 13 completed)

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, security guardrails, สถานะล่าสุด และแผนงานพัฒนา เพื่อให้ LLM ทุกตัว (GPT, Gemini, Claude) มี context ที่ตรงกัน 100%. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `gemini.md`, `README.md`, และ git diff.

---

## 1. Current Status

- Phase 1-13: completed / closed. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `README.md`, `gemini.md`, และ Git history.

---

## 2. Decisions That Still Matter

### Security / Permission Guardrails

- Use `User::hasPermissionCode()` as central permission helper.
- Sensitive writes require `password.confirm` where already scoped.
- `person_id`, password, token, secrets must not leak in UI/log/Inertia props.
- AuditLog must keep central recursive redaction for password/token/secret keys and `person_id`.
- Production invite flow must not flash plain invite token or `invite_url`.
- User disable, role change, and role permission changes must invalidate affected sessions and rotate/clear `remember_token` where implemented.
- Executive Dashboard data requires `executive.dashboard.view` or owner/admin fallback.
- Finance Dashboard requires `expenses.view`.
- Delivery Dashboard is for `projects.view` or `tasks.view`; task-only member sees own task scope and no project financial metrics.
- No Cash Balance in MVP / Post-MVP: no UI widget, no JSON/Inertia prop, no API field.

### Sales / Invoice / Tax Compliance

- Void invoice from deal does not auto-reopen or auto-change deal state; uses derived `needs_sales_review` flag instead.
- Number Sequences support tokens (`{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{BRANCH}`, `{SEQ:n}`) with scope (`organization` / `branch`) and reset periods (`none`, `yearly`, `monthly`, `daily`).
- `invoice_no` and `expense_no` are `varchar(30)` with database concurrency unique guard.
- Inclusive VAT calculates Gross Subtotal, Net Subtotal, VAT included, and allocates header discount before tax computation. Matches between backend `calculateTotals` and frontend preview.

### Payments / Procurement / Finance

- Payment receipt/reversal uses `idempotency_key`.
- No overpay is enforced with transaction + `lockForUpdate()`.
- Reversal amount is stored positive, but reports subtract by `entry_type = reversal`.
- Do not use raw `SUM(payments.amount)` for cash-in or net cash flow.
- Project actual cost is derived from expenses with status `approved` or `paid` only (no `projects.actual_cost` column).
- Suppliers have CRUD with org scope and unique supplier code.
- Purchase Orders support Create / Update / Approve / Cancel with server-side totals calculation and Expense chain validation.

### Projects / Tasks / Members

- `project_members` supports multi-user assignment with roles and access scope in `ProjectAccess`.
- Project Manager sees projects they own / admin sees all.
- Assigned member sees assigned tasks and updates task `status` only.
- Internal tasks support `project_id = null`.
- `blocked` tasks do not count as overdue in metrics.

## 3. Phase 11 Decisions

GPT เห็นด้วยกับ Gemini ว่า Treasury ใน Phase 10 เป็น operational state และ Phase 11 ต้องเพิ่ม General Ledger, journal posting, accounting period lock, immutable posting, reversal, และ source idempotency.

ข้อปรับเพื่อให้แบบบัญชีถูกต้องและนำไป implement ได้โดยไม่ลงบัญชีซ้ำ:

- ห้าม fix debit/credit mapping ในระดับ event ก่อนกำหนด accounting policy และ recognition point. ตัวอย่าง expense อาจ Dr Expense / Cr AP ตอน approve แล้วจึง Dr AP / Cr Bank ตอนจ่าย ไม่ใช่ Dr Expense / Cr Bank ทุก payment.
- Cheque ต้องกำหนดชัดว่า journal เกิดตอน issue, deposit, หรือ cleared. ถ้า post มากกว่าหนึ่งจุด ต้องมี clearing account และ `source_type + source_id + posting_event` unique guard เพื่อป้องกัน duplicate entry.
- เพิ่ม UI ปิด Bank Statement ได้เมื่อรายการครบ reconciled และ statement ที่ closed ห้าม match/unmatch. Phase 11 จะเชื่อม guard นี้กับ accounting period lock.
- Petty Cash ให้เพิ่ม soft warning ก่อน approve/pay เมื่อยอด request ที่ paid แต่ยังไม่ reimbursement เกิน imprest fund; initial scope ไม่ควร hard block จนกว่าจะตกลงนโยบายบริษัท.
- CSV parser preset สำหรับ KBANK, SCB, BBL และ KTB เป็น enhancement หลัง Phase 11; format มาตรฐานปัจจุบันต้องคง strict header/fingerprint deduplication.

ขอบเขต Closed Phase:

- ห้ามเพิ่ม feature ใหม่ย้อนกลับเข้า Phase 1-10 โดยไม่มี checklist/decision ใหม่.
- อนุญาต security fix, data-integrity fix, regression fix และ production-blocking fix ใน phase ที่ปิดแล้ว พร้อม test และบันทึกเหตุผล. การห้ามแก้โดยเด็ดขาดทำให้แก้ช่องโหว่ไม่ได้และขัดกับ security guardrails.

## 4. Phase 12 Decisions

- Phase 12 ปิดในขอบเขต application integration layer: XML mapping, private storage/hash, audit trail, permissions, queue/attempt log และ RD Prep staging export.
- `provider-mapping-v1` เป็น XML ภายในสำหรับ mapping เท่านั้น ห้ามเรียกว่า ETDA/RD certified XML จนกว่าจะได้ current XSD และผลทดสอบจาก certified provider.
- เก็บเพียง certificate reference กับวันหมดอายุในฐานข้อมูล; private key ต้องอยู่ vault/KMS หรือ certified provider เสมอ.
- `ETaxSignatureAdapter` และ `ETaxSubmissionAdapter` ค่าเริ่มต้นต้อง fail-closed. ห้ามเปลี่ยน document เป็น `signed`, `submitted` หรือ `accepted` โดยไม่มี adapter ที่ผ่าน onboarding.
- RD Prep รองรับเฉพาะ ภ.ง.ด. 3/53 ใน Phase 12 และมีข้อความบังคับให้ตรวจ format ปัจจุบันก่อน upload. ภ.ง.ด. 1 เป็น Phase 16 Payroll.
- ห้าม generate ทับ e-Tax ที่ `submitted` หรือ `accepted`; การปรับยอดหลังส่งต้องออก Credit Note/Debit Note.

## 5. Phase 13 Decisions

- Fixed assets ใช้ straight-line รายเดือนเท่านั้นในระยะแรก; scheduler วันที่ 1 จะ post เดือนก่อนหน้า และ service สามารถ catch up เดือนที่ขาดได้แบบ idempotent.
- Capitalization จาก Expense เป็น reclassification `Dr Fixed Asset / Cr Operating Expense`; จาก GRN เป็น `Dr Fixed Asset / Cr Inventory`. PO ไม่มี recognition event จึงใช้เป็น reference ผ่าน GRN เท่านั้น เพื่อไม่ให้ลงบัญชีซ้ำ.
- Category ต้องกำหนด asset, accumulated depreciation และ depreciation expense account ต่อองค์กร. Journal ทุก event ผ่าน `JournalPostingService` จึงถูก period lock และ source-event idempotency.
- Disposal/write-off ต้องหยุดค่าเสื่อม ณ สิ้นเดือนก่อนจำหน่าย แล้วตัดต้นทุน/ค่าเสื่อมสะสมและรับรู้ proceeds หรือ gain/loss. ห้ามแก้สินทรัพย์ที่ dispose/write-off แล้ว.
- นโยบายค่าเสื่อมและอายุการใช้งานต้องเป็น organization settings: default method, useful-life by asset class, salvage policy และ tax-book policy. `AssetCategory` ใช้ค่า default จาก settings และสามารถ override รายหมวด/รายสินทรัพย์ได้.
