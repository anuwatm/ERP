# Phase 23.1: Create/Edit Dialog Rollout

เริ่ม implementation: 2026-09-11. สถานะกลางอยู่ใน `../checklist.md`.
อ้างอิงภาพและ layout: `../create-edit-dialog-template-concept.md`.

สถานะล่าสุด 2026-09-13: **23.1.3 Customer/Contact pilot เสร็จ** ดู [acceptance](PHASE_23_1_CUSTOMER_CONTACT_ACCEPTANCE.md); ข้อความ browser pending ใน verification log เป็นประวัติเดิม. Rollout โมดูลอื่นยังไม่ปิด

## ขอบเขตงานรอบแรก

ปิด Customer/Contact pilot ก่อน rollout: shared container, permission ของแต่ละ action,
error summary, draft guard และ browser regression. ยังไม่ถือว่า pilot หรือ Phase เสร็จ
จากการ compile ผ่านหรือ backend tests เพียงอย่างเดียว.

โค้ดกลาง: `CreateEditDialog`, `DiscardChangesDialog`, `useDiscardGuard`.
เก็บ payload, validation, owner scope และ submit callbacks ไว้ในแต่ละโมดูล.
ใช้ editor ที่ active หนึ่งรายการต่อหน้า รวมรายการที่ minimized; draft อยู่ใน memory
เฉพาะหน้าเดิม. localStorage เก็บเฉพาะขนาด dialog. ไม่ใช่ autosave.

## Inventory — Phase 23.1.1 เสร็จแล้ว

ตรวจ 60 Pages, 92 JS/TS source files และ 252 registered routes (173 write routes).
รายฟอร์มครบ 91 แถว: migrated 4 (browser pending), pending 66, integration gap 1, exceptions 20.
มี backend-only 12 routes แยกจากฟอร์มปัจจุบัน. ตารางด้านล่างเป็นลำดับกลุ่มงาน;
ขอบเขตราย action ให้ยึด [inventory](PHASE_23_1_FORM_INVENTORY.md) และ [source evidence](PHASE_23_1_FORM_INVENTORY.json).
ดู [ต้องเปลี่ยนอะไร / เปลี่ยนแล้วอะไร](PHASE_23_1_CHANGE_MAP.md).
พบ G01 Attendance Privacy ขาด route และ G02 Invoice Edit ไม่ตรง backend draft-only; บันทึกแล้ว ยังไม่ได้แก้โค้ด.

| ลำดับ | หน้า / กลุ่มฟอร์ม | รูปแบบเดิม / เป้าหมาย | จุดที่ต้องรักษา | สถานะ |
| --- | --- | --- | --- | --- |
| 0 | Sales/Customers: Customer, Contact | shared dialog | owner scope, Contact association, primary uniqueness | complete; browser acceptance 2026-09-13 |
| 1 | Sales/Deals: Deal, Activity/Follow-up | shared dialog | customer/contact chain, won/lost rules, activity owner/date | implementation complete; browser regression pending |
| 1 | Sales/Dashboard | summary only | ตรวจ source แล้วไม่พบ form, create/edit action หรือ route link ให้ย้าย | audited; no editor migration needed |
| 2 | Finance/Products, Suppliers, BankAccounts | inline/sidebar → shared dialog | references, inactive values, delete-safe rules | pending |
| 2 | Settings/OrganizationStructure; Admin/Users, Roles | inline/editor → shared dialog | hierarchy, invitations, owner protection, re-authentication | pending |
| 3 | Finance/Quotations, Invoices, PurchaseOrders | sidebar → shared dialog | line items, tax/FX totals, status locks, source links | pending |
| 3 | Finance/CommercialDocuments | DO form + voucher attachment → shared dialog | CN/DN, billing note, PR, voucher create เป็น backend-only; ไม่สร้าง UI ใหม่อัตโนมัติ | pending |
| 3 | Finance/Expenses; invoice payment action | expense inline/sidebar → shared dialog; payment เป็น command exception | draft vs approve/pay/post, WHT, receipt/proof | pending / documented action exception |
| 4 | Finance/GoodsReceipts, InventoryOperations | inline → shared dialog | GRN, adjustment, warehouse/bin/lot, transfer/count, scanner, stock guards | pending |
| 4 | Finance/BankStatements, PettyCash, Cheques | CRUD inline → shared dialog; import/reconcile/pay/reimburse/transition คง specialized action | funds/claims/cheque create; command guards คงเดิม | pending / documented action exceptions |
| 4 | Accounting/ChartOfAccounts, Periods, Journals | inline → shared dialog | balanced journal lines, immutable posted records, closed periods | pending |
| 4 | Finance/FixedAssets, Currencies | inline → shared dialog | policies/rates, depreciation/disposal actions, historical FX | pending |
| 5 | Delivery/Projects, Tasks | sidebar/inline → shared dialog | members, checklist/comments, task scope and progress | pending |
| 5 | Hr/Index, Finance/Payroll | inline → shared dialog | employee, attendance, leave, summaries, payroll policies and locked runs | pending |
| 5 | Documents/Index | inline → shared dialog | metadata/category, upload/version, links, sensitivity, retention/legal hold | pending |
| 5 | Workflows/Index | inline → shared dialog | definitions/steps/delegations, immutable active snapshots | pending |
| 6 | Settings/Organization, NotificationPreferences, PaymentGateway; Finance/ETax | settings forms → shared dialog | uploads, encrypted secrets, re-authentication, opt-in configuration | pending |
| 6 | Profile/Edit + UpdateProfileInformationForm | inline → shared dialog | email verification and profile validation | pending |
| 6 | Portal/Dashboard: vendor bill intake | inline → shared dialog | magic-link grant, upload, supplier/org scope | pending |
| 7 | Dashboard, layout navigation, embedded links and legacy URLs | source inventory audited; browser regression pending | filtered deep links, return context and permissions | inventory complete; runtime acceptance pending |

## Customer/Contact field and action contract

| Form | Route/action | Fields | References / attachments | Permission / business scope | Verification |
| --- | --- | --- | --- | --- | --- |
| Customer create/edit | GET `/customers`; POST `/customers`; PATCH `/customers/{customer}` | company_name, tax_id, customer_type, status, owner_id, phone, email, line_id, website, address, source, note | owner search; no line items/upload | customers.create / customers.update; backend SalesAccess enforces visible org/owner | Phase2SalesTest + browser passed 2026-09-13 |
| Contact create/edit | POST `/customers/{customer}/contacts`; PATCH `/contacts/{contact}` | name, position, phone, email, line_id, is_primary, note | customer_id held by page; update route retains association; no line items/upload | contacts.create / contacts.update; backend parent visibility; one primary per customer | Phase2SalesTest + browser passed 2026-09-13 |
| Customer delete | DELETE `/customers/{customer}` | no editor | referenced deals/invoices forbid deletion | customers.delete plus backend reference checks | existing action, not a CRUD dialog |

ไม่ใช้ชื่อ role ตัดสินสิทธิ์ปุ่ม; ใช้ `auth.permissions` ที่ backend ส่งมา.
การซ่อนปุ่มไม่แทน middleware/authorization. ค่า owner เดิมที่ไม่อยู่ใน active options
ต้องคง id เดิมไว้ ไม่ clear โดยอัตโนมัติ.

## ข้อยกเว้นที่กำหนดก่อน rollout

- Auth/*, Profile/Partials/UpdatePasswordForm และ DeleteUserForm: คง security flow และ confirmation เดิม.
- Auth/AcceptInvite: เป็น token-based enrollment; ไม่ใช่ internal CRUD editor.
- Portal/SignIn: คง access flow. Portal quotation acceptance และ logout เป็นคำสั่งแยกจาก editor.
- Welcome, Admin/AuditLogs, Accounting/Reports, Finance/TaxReports และ TreasuryReports:
  report/search/filter/export ไม่แปลงเป็น Create/Edit dialog.
- Dashboard filters และ links: คง controls; Create/Edit ที่พบต้องเชื่อมไป editor ของโมดูลเดิม.
- Approve/reject/post/pay/complete/cancel/delete, QR checkout, e-Tax submit/retry,
  depreciation/disposal และ stock posting: คง domain confirmation และ authorization.
  หากคำสั่งมีแบบกรอกข้อมูลประกอบ ให้สำรวจ editor แยก ไม่เปลี่ยน semantics ของคำสั่ง.
- Posted/locked/approved records: ใช้ guards จริงของแต่ละ controller/service;
  ห้ามเพิ่ม Edit เพียงเพราะมี shared component.

## Acceptance ต่อกลุ่มก่อนติ๊ก rollout

1. บันทึกรายฟอร์ม: route/method, fields, references, line items, attachments,
   permissions และ allowed business states จาก frontend/backend จริง.
2. ย้าย create/edit entry points และถอด editor เดิม; ใช้ shared dialog เดียวทั้ง create/edit.
3. ตรวจ prefill/nullable/clear ทุก field, inactive references, totals และ indexed errors.
4. ตรวจ X/Cancel/Escape/overlay/switch draft/minimize/restore/resize;
   ระหว่าง saving ห้าม submit ซ้ำหรือเปลี่ยน editor; failure เก็บ draft ไว้.
5. ตรวจ in-app navigation, Back/Forward และ reload. `useDiscardGuard` ครอบ Inertia GET,
   beforeunload และเชื่อม `Support/historyGuard.ts` สำหรับ history entries ที่แอปสร้าง.
   History guard ผ่าน automated tests แต่ยังต้องยืนยัน browser จริงก่อนปิด pilot.
6. Browser save/error round-trip ใช้ฐานทดสอบแยก; ตรวจ desktop/mobile, short landscape,
   keyboard/focus, light/dark และชื่อไทยยาว. ห้ามใช้ฐาน operational สร้างข้อมูลทดลอง.
7. Scoped feature tests + TypeScript/ESLint/build ผ่าน. ก่อนปิด Phase รัน cross-module
   regression และอัปเดต README, routes/screens, checklist และ gpt.md.

## Verification log

- ก่อนเริ่มรอบนี้: Phase2SalesTest + Phase12ETaxTest + Phase24ETaxFoundationTest
  ผ่าน 18 tests / 152 assertions และ TypeScript ผ่าน (2026-09-11).
- ผล implementation รอบแรก (2026-09-11): `Phase2SalesTest` ผ่าน 8 tests / 108 assertions
  รวม view-only permission ปฏิเสธ Customer/Contact writes; `tsc --noEmit`, scoped ESLint
  ของ Customers/CreateEditDialog, Pint ของ Phase2SalesTest และ Vite production build ผ่าน.
  `git diff --check` ผ่าน. ไม่มีการเปลี่ยน schema หรือ backend authorization.
- Browser automation timeout สองครั้ง; ยังไม่ได้ทดสอบ browser regression ในรอบนี้.
  บันทึกข้างต้นเป็นรอบแรก; ความคืบหน้า history guard อยู่ด้านล่าง.

## Continuation: History Guard (2026-09-11)

ติดตั้ง history tracking ใน `app.tsx` ก่อน Inertia initialization. เพิ่มเฉพาะเลขตำแหน่ง
`__erpHistoryIndex` ใน history state โดยคง page/scroll/encrypted payload เดิมไว้.
ไม่มีการเก็บ draft เพิ่มใน history/localStorage/sessionStorage.
เมื่อมี dirty/processing editor จะกั้น popstate ไม่ให้ Inertia unmount ฟอร์ม,
คืนตำแหน่งเดิมก่อนเปิด discard confirmation. Keep อยู่หน้าเดิม; Discard replay
ไปปลายทางเดิมครั้งเดียว. Processing คืนหน้าเดิมโดยไม่เปิด confirmation.

หลักฐาน source: Inertia ที่ติดตั้ง `@inertiajs/core@2.3.27` รับ popstate แล้วเรียก
`page.setQuietly(..., { preserveState: false })` โดยไม่ผ่าน before event.
สอดคล้องกับ [Inertia event documentation](https://inertiajs.com/docs/v3/advanced/events).
เราไม่ได้ cancel native popstate แต่กั้น router handler ระหว่างคืน history.

| การตรวจ | ผล |
| --- | --- |
| Baseline test harness ไม่มี history blocker | ล้มเหลว 5 จาก 6 กรณี; router ได้รับ Back และแทนหน้าเดิม |
| หลังเพิ่ม history guard | 6/6 ผ่าน |
| เพิ่ม rapid Back, stale effect cleanup, save ระหว่าง restoration | 9/9 ผ่าน |
| Phase2SalesTest | 8 tests / 108 assertions ผ่าน |
| TypeScript / scoped ESLint / Vite build | ผ่าน |
| Isolated fixture prepare และ GET `/login` | ผ่าน; HTTP 200 |
| Browser interaction | ยังไม่ผ่านการตรวจ: connector เปิดแท็บ timeout และ inventory หาย |

รันทดสอบซ้ำจาก `backend/`: `npm run test:history`.
ชุดนี้จำลอง asynchronous History API/events และทดสอบ production TS จริง
แต่ไม่ได้แทน browser render/focus/scroll tests.

ข้อจำกัดที่ต้องตรวจต่อ: native fragment entries ที่ไม่ได้ผ่าน History API wrappers,
history จาก document เก่า, reload/external navigation, focus/scroll หลัง Keep,
session/re-authentication redirects และ browser save/error round-trip.
ก่อนปิด pilot ต้องยืนยันทุกกรณีบน browser จริง. งาน implementation ของโมดูลถัดไปเดินหน้าระหว่างรอ connector ได้ แต่ยังไม่ปิด rollout จน browser regression ครบ

## Deals/Activities implementation scope

| Form | Route/action | Fields | References | Guards | Status |
| --- | --- | --- | --- | --- | --- |
| Deal create/edit | POST `/deals`; PATCH `/deals/{deal}` | title, customer_id, contact_id, stage, value_amount, currency, probability, expected_close_date, owner_id, source, lost_reason, note | scoped customer, contact belonging to customer, owner | deals.create/update; lost reason, probability 0–100, org/owner scope | implemented; backend round-trip passed; browser pending |
| Activity create/edit | POST `/activities`; PATCH `/activities/{activity}` | entity_type=deal, entity_id, activity_type, subject, body, activity_at, follow_up_at, completed_at, owner_id | visible deal, org owner | activities.create/update; original and target entity visibility | implemented; backend round-trip passed; browser pending |

ไม่มี line items/upload ในสองฟอร์มนี้. คง Create Invoice, Create Project และ Complete Activity เป็นคำสั่งเดิมแยกจาก editor.
Sales Dashboard ปัจจุบันมีเฉพาะ summary/charts; ไม่พบ form หรือ route link ใน source และไม่ได้สร้าง editor ใหม่ใน Dashboard.

Deals ใช้ editor active ได้หนึ่งรายการรวม minimized draft. ขณะเปลี่ยน Customer จะคง contact_id เดิมไว้และแจ้งให้เลือกใหม่/ล้างหากไม่ตรงลูกค้า แทนการลบทิ้งเงียบ ๆ. Reference ที่ไม่อยู่ใน options ยังคง id เดิม.
Activity date inputs ระบุ UTC ตาม application timezone; prefill แสดงถึงวินาที แต่ส่ง timestamp เดิมหากไม่ได้แก้. completed_at คงค่าเดิมแบบ read-only; Done ยังคงเป็นคำสั่งแยก.
Quick-create reference และ browser checks ยังไม่ถือว่าครบตามมาตรฐานทั้งระบบ.

ผลตรวจ 2026-09-11: Phase2SalesTest + Phase231SalesDialogsTest ผ่าน 12 tests / 196 assertions; TypeScript, scoped ESLint และ Vite build ผ่าน. รายการทดสอบครอบ currency/source/note, optional field clearing, Activity body/owner/completion timestamps, validation ไม่แก้ข้อมูลเดิม, view-only writes และ owner/org boundaries.

### Isolated browser fixture

จาก `backend/`:

```powershell
php tests/Browser/prepare-phase231.php
php -S 127.0.0.1:8011 -t public tests/Browser/phase231-router.php
```

เปิด `http://127.0.0.1:8011/customers` แล้ว login ด้วยบัญชีทดสอบ
`phase231@example.test` / `password` (UserFactory default; ใช้เฉพาะ fixture).
ฐานอยู่ที่ `temp/phase231-browser/database.sqlite` ใน root โปรเจกต์.
Environment/config cache และ session cookie แยก; ไม่โหลด operational `.env`.
Prepare ตรวจชื่อฐานก่อน migrate และไม่ใช้ migrate:fresh/reset หรือล้างข้อมูลเดิม.
ข้อมูลนี้เป็น synthetic fixture สำหรับทดสอบเท่านั้น. ใช้ Ctrl+C หยุด test server.
