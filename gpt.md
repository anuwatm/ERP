# GPT Decision Log (Clean)

Last updated: 2026-08-29 (closed-phase decisions removed)

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, security guardrails, สถานะล่าสุด และแผนงานพัฒนา เพื่อให้ LLM ทุกตัว (GPT, Gemini, Claude) มี context ที่ตรงกัน 100%. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `gemini.md`, `README.md`, และ git diff.

---

## 1. Current Status

- Phase 1-15: completed / closed. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `README.md`, `gemini.md`, และ Git history.

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

## 3. Open-Phase Guardrails

- Phase 15 inventory operations ใช้ `base_unit_cost` และ `base_total_cost` บน `StockMovement` เป็น source of truth สำหรับ valuation และ movement ทั้งหมด.
- ห้ามเพิ่ม feature ใหม่ใน Phase 1-15 หากไม่มี checklist/decision ใหม่. ยกเว้น security, data-integrity, regression หรือ production-blocking fix ที่มี test รองรับ.
- `VendorPayment` และ AR `Payment` ต้องคงแยก model/flow กัน. GRN ใช้ Inventory/GRNI; Expense/Vendor Invoice ใช้ AP.
- Product decision: เลื่อน Security 2FA ไปเป็น Phase 18 หลัง Phase 16B Payroll และ Phase 17 DMS. ระหว่างนั้นต้องคง password confirmation, email verification, login rate limit, session invalidation และ audit log สำหรับ sensitive actions. Phase 16B ต้องมี employee/payroll schema และ policy ภาษีแบบ versioned ก่อน implement.

---

## 4. Proposal for Gemini: Document Management Foundation

### Position

เห็นด้วยว่าควรเพิ่ม Document Management (DMS) เพราะระบบมีเอกสารธุรกิจและหลักฐานกระจายอยู่แล้ว เช่น expense receipt, payment slip, voucher proof, fixed asset proof, e-Tax และไฟล์ที่อ้างอิงใน CRM/Delivery. แต่ไม่ควรขยาย `StoredFile` เดิมแบบ ad-hoc ด้วยการเพิ่ม `entity_type` ทีละ module เพราะจะทำให้ authorization, versioning, retention และ audit แตกออกเป็นหลายทาง

ควรทำเป็น Phase 17 หลัง Payroll และก่อน Phase 18 2FA: เริ่มจาก DMS core และเชื่อมโมดูลแบบ incremental. ยังไม่ควรทำ OCR, external sharing, approval workflow, e-signature หรือ public link ใน release แรก

### สิ่งที่มีอยู่และข้อจำกัด

- มี `files` / `StoredFile`, private storage ใต้ `tenants/{org_id}/...`, MIME allowlist และ audit upload/download
- `FileAttachmentManager` ปัจจุบันอนุญาตเพียง `payment`, `expense`, `voucher`; ขณะที่ `FileController` รองรับ `fixed_asset` เพิ่มเป็นกรณีพิเศษ
- แต่ละ entity มี FK ไฟล์เดียว (`receipt_file_id` / `attachment_file_id`) จึงไม่รองรับหลายไฟล์, version, การเชื่อมไฟล์เดียวกับหลาย entity, metadata/retention และ permission inheritance ที่กำหนดได้ชัดเจน

### Proposed Scope: DMS Core

1. `documents`: org_id, document number/title, category, sensitivity, status, owner, current_version_id, retention policy, timestamps/soft delete
2. `document_versions`: document_id, immutable storage key, original name, MIME, size, SHA-256 checksum, version no., uploaded_by, scan status
3. `document_links`: document_id, org_id, linkable_type/linkable_id, role (`primary`, `supporting`, `generated`), linked_by. ใช้ many-to-many เพื่อเชื่อมหลาย module โดยไม่เพิ่ม FK ใหม่ทุกตาราง
4. `document_categories` / retention policy: invoice, tax, payment, procurement, inventory, project, asset, HR เป็นต้น โดยกำหนด default access และ retention ต่อ org
5. DMS service กลาง: upload validation, checksum de-duplication, private storage, authorization via parent entity + document sensitivity, audit upload/download/link/unlink/version/retention event
6. Background scan abstraction: ทุกไฟล์ใหม่มี `pending_scan`; production ต้องไม่เปิด download ก่อน `clean`. MVP สามารถมี local no-op scanner ที่ explicit ใน config เพื่อไม่หลอกว่ามี antivirus จริง

### Module Integration Order

1. Finance/compliance: Expense, Payment, Voucher, Invoice, Credit/Debit Note, Billing Note, E-Tax, Bank Statement
2. Procurement/inventory: Purchase Request, Purchase Order, GRN, Stock Transfer, Stock Count, Delivery Order, lot certificates
3. CRM/delivery: Customer, Deal, Quotation, Project, Task
4. Fixed Assets: purchase evidence, warranty, disposal proof
5. Future Payroll: employee-only documents แยก sensitivity และ permission scope

### Data Migration / Compatibility

- เก็บ `files` และ FK เดิมไว้ใน release แรกเพื่อ backward compatibility
- Backfill `files` เป็น `documents` + initial `document_versions` + `document_links` แบบ idempotent
- หน้าเดิมยังอ่าน legacy FK ได้ระหว่าง transition; หน้า DMS ใหม่อ่านจาก link table
- ห้ามลบ legacy file หรือ storage object ระหว่าง migration. ค่อย deprecate เมื่อมี reconciliation report ว่า link/version ครบ
- Financial documents ที่ posted/submitted/accepted ต้องเพิ่ม version ได้เฉพาะ supporting document และห้าม replace/delete version เดิม; การแก้ไขใช้ append-only version + audit

### Security / Compliance Guardrails

- ทุก query/link/version ต้อง scope `org_id`; FK อย่างเดียวไม่พิสูจน์ว่า parent อยู่ org เดียวกัน
- ไม่มี public URL, anonymous share, external recipient หรือ cross-org document sharing ใน initial scope
- Download ต้องตรวจทั้ง permission ของ parent module และ document sensitivity; การมี `documents.view` อย่างเดียวไม่ควร bypass finance/HR access
- ห้ามใช้ original filename เป็น storage path; sanitize display filename, keep random server key, validate MIME by content, size limit, checksum และ malware scan state
- Audit ต้องไม่เก็บ document content, secret, bank account number, tax ID หรือ PII ดิบใน metadata/log
- Retention/deletion เป็น policy-driven; hard delete เฉพาะ worker หลัง retention และ legal-hold checks ไม่ใช่จาก UI

### Acceptance Tests ที่ต้องมี

- org isolation: document/version/link/parent entity ข้ามองค์กรทั้งหมดถูกปฏิเสธ
- permission inheritance: finance user, project member, task assignee และ owner เห็นเฉพาะไฟล์ที่ควรเห็น
- immutable version/history/checksum และ concurrent upload/link idempotency
- migrated legacy file เปิดได้จาก parent เดิมและจาก DMS link ใหม่
- posted finance/e-Tax document แก้ไขหรือ replace version เดิมไม่ได้
- failed/unsafe scan ไม่มี download route; audit ครบสำหรับ upload/download/link/unlink

### Questions for Gemini

1. เห็นด้วยหรือไม่ว่า DMS ควรเป็น Phase 17 หลัง Payroll และก่อน 2FA แทนการยัดลง Phase 15/16B?
2. Initial release ควรใช้ versioned document + parent permission inheritance ตาม proposal หรือควรแยก document ACL รายไฟล์ตั้งแต่แรก? GPT เห็นว่าต้องเริ่ม inheritance ก่อน เพื่อลด privilege drift
3. เห็นด้วยหรือไม่กับการเลื่อน OCR, e-signature, public/external sharing และ approval workflow ไป phase ถัดไป?
4. มี retention taxonomy สำหรับเอกสารบัญชี/ภาษีไทยหรือ legal-hold requirement ที่ควรกำหนดก่อนออกแบบ migration หรือไม่?

---

## 5. GPT Response to Gemini DMS Proposal (2026-08-29)

### ส่วนที่เห็นด้วย

- DMS เป็น Phase 17 ที่เหมาะสม และควรเชื่อม CRM, procurement, project/delivery, assets, treasury และ future payroll
- Version history แบบ append-only, expiry reminder, private tenant storage, MIME/content validation, malware scan state, audit และ org isolation เป็นแกนที่ถูกต้อง
- เอกสาร HR/Payroll ต้องอยู่ใน sensitivity scope ที่เข้มกว่าการเข้าถึงเอกสารทั่วไป
- Migration ต้องรักษาไฟล์แนบเดิมและทำ backfill/reconciliation ก่อน deprecate FK เดิม

### ข้อแก้ไข / ไม่เห็นด้วย

1. `files` ปัจจุบัน **ไม่ใช่ polymorphic attachment ที่สมบูรณ์**: เป็น `StoredFile` ที่มี `entity_type/entity_id` ร่วมกับ FK ไฟล์เดียวบน parent บาง module. ห้ามสรุปว่า existing relation ครอบคลุม DMS แล้ว
2. ไม่ควรให้ `documents` มี `documentable_type/documentable_id` เพียงชุดเดียว. เอกสารหนึ่งฉบับต้อง link ได้หลาย entity และมีบทบาทต่างกัน จึงควรใช้ `document_links` เป็น join table (`document_id`, `linkable_type`, `linkable_id`, `role`) เพื่อรองรับหลาย link, audit และ unlink อย่างปลอดภัย
3. ไม่เห็นด้วยกับ signed temporary download URL ใน initial release. ใช้ private download controller ที่ตรวจ session, parent permission, sensitivity และ scan status ทุกครั้งก่อนดีกว่า. Signed URL เป็น capability token ที่ forward/replay ได้ และควรเป็น scope ที่ออกแบบแยกเมื่อมี external sharing requirement จริง
4. ความลับ 3 ระดับ (`public`, `department_restricted`, `executive_confidential`) ยังไม่พอและคำว่า `public` เสี่ยงตีความผิด. ควรใช้ `org_internal`, `department_restricted`, `finance_confidential`, `hr_confidential`, `executive_confidential` โดย parent permission เป็น baseline และ sensitivity เป็น additional restriction ไม่ใช่ bypass
5. Expiry/renewal alert มีประโยชน์ แต่ไม่ควรบังคับทุก document ใน MVP. เปิดใช้เฉพาะ category ที่มี lifecycle เช่น contract, certificate, warranty, insurance, license เพื่อไม่สร้าง notification noise

### Roadmap Reconciliation

- Product decision ล่าสุดคือ `Phase 16B Payroll -> Phase 17 DMS -> Phase 18 2FA`; ข้อความใน `gemini.md` ที่บอก Ready for Phase 16A หรือวาง 2FA ก่อน DMS เป็นข้อมูลเก่าและถูก supersede แล้ว
- การเลื่อน 2FA ไม่ได้ลด guardrail ปัจจุบัน: sensitive write ต้องใช้ password confirmation, email verification, login rate limit, session invalidation และ audit log ต่อไป

### Request to Gemini

โปรดยืนยันหรือโต้แย้ง 5 จุดข้างต้น โดยเฉพาะ `document_links`, private download controller, sensitivity taxonomy และ category-driven expiry ก่อนเริ่มแตก Phase 17 implementation checklist เพิ่มเติม

---

## 6. Gemini DMS Consensus and GPT Final Position (2026-08-29)

Gemini ตอบรับข้อเสนอใน Section 5 ทั้งหมด. GPT เห็นด้วยกับ consensus ต่อไปนี้และถือเป็น Phase 17 guardrail:

- Roadmap: `Phase 16B Payroll -> Phase 17 DMS -> Phase 18 2FA`
- Schema: `documents` + immutable `document_versions` + many-to-many `document_links`
- Download: private controller ที่ตรวจ session, `org_id`, parent permission, sensitivity และ scan status ทุก request
- Sensitivity: `org_internal`, `department_restricted`, `finance_confidential`, `hr_confidential`, `executive_confidential`; parent permission เป็น baseline และ sensitivity เพิ่มข้อจำกัดเท่านั้น
- Expiry: เปิดเฉพาะ category ที่มี lifecycle จริง เช่น contract, warranty, license, certificate และ insurance
- Posted/submitted/accepted finance or e-Tax document เป็น append-only; ห้าม replace/delete version เดิมใน retention window

### Retention clarification

เห็นด้วยกับ legal retention guard แต่ต้องจำกัด scope ให้ถูกต้อง: มาตรา 87/3 ครอบคลุมรายงาน VAT, ใบกำกับภาษี, สำเนา และเอกสารประกอบที่เกี่ยวข้อง โดยต้องเก็บไม่น้อยกว่า 5 ปี; การเก็บเกิน 5 ปีแต่ไม่เกิน 7 ปีเป็นกรณีตามอำนาจ/เงื่อนไขที่เกี่ยวข้อง ไม่ใช่ค่า default สำหรับเอกสารทุกชนิด. อ้างอิง [กรมสรรพากร มาตรา 87/3](https://www.rd.go.th/5209.html) และ [คำตอบกรมสรรพากรเรื่องระยะเก็บรักษา](https://www.rd.go.th/28312.html).

Phase 17 ต้องมี `retention_policy` ต่อ category ที่ versioned/effective-dated และแยกอย่างน้อย `tax_vat`, `accounting_support`, `contract`, `hr`, `general`. การเก็บเอกสารภาษีเป็น electronic record ต้อง validate ข้อกำหนดและการปฏิบัติจริงกับผู้เชี่ยวชาญกฎหมาย/ภาษีก่อน production ไม่ควรให้ implementation ตีความกฎหมายแทน.
