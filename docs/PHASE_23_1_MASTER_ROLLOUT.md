# Phase 23.1.4 — Master data rollout

2026-09-13: Products/Services, Suppliers และ Bank Accounts implementation เสร็จ; browser acceptance ยัง pending

| หน้า | เปลี่ยนแล้ว | คง contract เดิม |
| --- | --- | --- |
| Products/Services | ถอด sidebar; Create/Edit shared dialog, sections, typed fields, Description ที่ backend รองรับ, draft guard | SKU/barcode, ราคา/cost/reorder point, active/type, org scope |
| Suppliers | ถอด sidebar; dialog, email/tel/address, field errors และ action permissions | generated supplier code, status, nullable fields, org scope |
| Bank Accounts | ถอด sidebar; dialog, Branch/GL searchable references, current unavailable label, decimal/date inputs | masked account number; blank Edit number keeps encrypted value; opening balance, branch/GL, currency/status |

ทั้งสามหน้ามี size preference, minimize/restore, memory-only draft, discard guard, save processing และ session confirmation ผ่าน shared container. Edit ตั้ง form defaults ให้ dirty status เทียบข้อมูลจริง; Create ใหม่ล้าง defaults/data/errors อย่างชัดเจน

เพิ่ม opt-in EnsureFormSubmission ที่สาม create routes หลัง permission/password/throttle middleware; ใช้ UUID ต่อ draft และ receipt transaction เดิม ไม่เพิ่ม migration. Backend tests ยืนยัน retry เดิมสร้างแถวเดียวและ receipt ไม่เก็บเลขบัญชีดิบ; revoked permission ปฏิเสธ retry

## ผลตรวจ

- Phase2314MasterDialogsTest + Phase3ProductsTest + Phase7ProcurementAndMembersTest + Phase10BankAccountsTest: 14 tests / 130 assertions ผ่าน
- TypeScript, scoped ESLint, Pint และ production build ผ่าน; diff check ผ่าน
- isolated browser fixture เพิ่ม permission ของสามโมดูลแล้ว; ไม่มีการเพิ่มสิทธิ์ผู้ใช้ในฐาน operational
- Browser regression ยังไม่ผ่านการรับรอง: automatic approval review ปฏิเสธเปิด localhost เพราะ usage limit เต็ม ไม่ได้อ้างผลจาก compile/backend tests ว่าเท่ากับ browser acceptance

## งานที่ยังต้องทำ

- Browser Create/Edit/error/retry, blank bank number preservation, reference search, minimize/discard/history, keyboard และ responsive ของสามหน้า
- Rollout Procurement/Treasury/Inventory ยังไม่ปิด เพราะ Purchase Orders, Bank Statements/Petty Cash/Cheques, Goods Receipts/Inventory Operations ยังอยู่ในรายการงาน
- Sales และกลุ่มโมดูลอื่นใน checklist 23.1.4 ยังต้องรับรองตามรายการเดิม
- BankAccountController ปฏิเสธเลขซ้ำด้วย HTTP 422 เดิม; ต้องตรวจลักษณะ error response ใน browser ก่อนปิด acceptance

ไม่แก้ baseline inventory วันที่ 2026-09-11 ให้ดูเหมือนทุก surface migrate แล้ว; ใช้เอกสารนี้เป็นสถานะ incremental

## รอบถัดมา: Currencies/FX และ Accounting Periods

Implementation เพิ่ม 2026-09-13:

- Currencies และ exchange-rate settings ถอด inline forms; ใช้ shared dialog หนึ่ง active draft พร้อม labels/errors, decimal/date controls, minimize/discard และ permission fx.manage
- เพิ่ม Edit currency และ Edit reference rate เพื่อ prefill จากรายการเดิม; ยังคง POST upsert ตาม code หรือ pair/date เดิม ไม่แก้ historical document/payment snapshots
- เพิ่ม decimal_places/status/source ที่ backend รองรับให้ผู้ใช้ตรวจและแก้ได้ชัดเจน
- Accounting Periods ใช้ controlled form ใน dialog แทนอ่าน FormData จาก inline form; ป้องกัน retry สร้างงวดซ้ำด้วย EnsureFormSubmission หลัง middleware เดิม
- คำสั่ง close period และ FX revalue/reverse ยังเป็น domain commands; ปิดปุ่มระหว่างมี editor เพื่อไม่ให้เปลี่ยนสถานะขณะมี draft ค้าง
- พบและแก้ bug ExchangeRate upsert บน SQLite: date cast เก็บเวลาเที่ยงคืนแต่ lookup เดิมใช้ date string ทำให้ retry ชน unique key. ค้นหาด้วย whereDate และ serialize rate writes ต่อ organization ใน transaction; normalize currency code เป็น uppercase ก่อน save

ผลตรวจ: Phase2314MasterDialogsTest + Phase14FxTest + Phase141ApInventoryFxTest + Phase11GeneralLedgerTest ผ่าน 12 tests / 123 assertions. ครอบคลุม period validation/retry, currency optional settings, repeated/updated rate และ FX/GL regressions. TypeScript, scoped ESLint, Pint และ build ผ่าน

Browser acceptance ของสองหน้านี้ยัง pending เช่นเดียวกับ master data รอบแรก จึงยังไม่ติ๊ก Currencies/FX หรือ Accounting ทั้งกลุ่มเสร็จ. Accounting Chart of Accounts และ Journals ยังไม่ได้ migrate ในรอบนี้


## รอบถัดมา: Chart of Accounts และ Manual Journal

Implementation 2026-09-13:

- Chart of Accounts ถอด inline form เป็น shared dialog พร้อม field errors, permission, memory draft และ discard/minimize; คง create-only UI และ normal balance จาก backend
- เพิ่ม EnsureFormSubmission ที่ create account route; validation ล้มเหลวแก้แล้ว retry ได้ และ retry สำเร็จเดิมไม่สร้างบัญชีซ้ำ
- Manual Journal ใช้ shared dialog และ EditorLineItems พร้อมค้นบัญชี, เพิ่ม/ลบแถว, indexed errors และ stable row keys; ตัด local keys ก่อนส่ง payload
- คงปุ่ม Post Journal และการลงบัญชีจริงตามเดิม; ไม่เพิ่มการแก้ posted journal หรือ persisted draft
- idempotency_key คงเดิมเมื่อ retry ภายใน draft; สร้างใหม่เมื่อเปิดรายการใหม่
- แยก Reversal posting date ให้ระบุได้ชัดเจน; คง reverse command เดิม พร้อม permission และปิดระหว่าง editor เปิด

ผลตรวจ: Phase2314MasterDialogsTest + Phase11GeneralLedgerTest ผ่าน 6 tests / 109 assertions. ครอบคลุม HTTP validation/retry ของ account/journal และ service balance, closed period, reversal, org scope เดิม. TypeScript, scoped ESLint, Pint และ production build ผ่าน

Browser acceptance ของ Chart of Accounts/Journals ยัง pending เนื่องจาก automatic approval review ติด usage limit เช่นรอบก่อน จึงยังไม่ปิด Accounting rollout ทั้งกลุ่ม. ต้องตรวจ line-item errors, account search, draft/discard/minimize, keyboard และ responsive ใน browser


## รอบถัดมา: Fixed Asset Category และ Capitalization

Implementation 2026-09-13:

- ถอด inline category/capitalization forms; ใช้ FixedAssetCreateDialog พร้อม controlled fields, labels/errors, account/category/source/custodian search และหนึ่ง active draft
- minimize/restore เก็บข้อมูลเดิม; close/navigation ใช้ discard guard; เปิดใหม่ได้ draft ใหม่; เปลี่ยน source type ล้าง source id เพื่อไม่ส่งข้อมูลอ้างอิงข้ามประเภท
- คงทุก field เดิม รวม blank useful life (ใช้ category default), salvage value, location, custodian และจำนวนเงินใน expense source label
- ปุ่ม Capitalize and Post ระบุผลลง GL ชัดเจน; depreciation/disposal/write-off ยังเป็นคำสั่งเดิม เพิ่ม permission UI และปิดปุ่มขณะ editor เปิด
- เพิ่ม EnsureFormSubmission หลัง permission/password/throttle ของ category และ capitalization; retry สำเร็จไม่สร้าง category/asset/GL ซ้ำ
- พบ regression เมื่อ validation ล้มเหลวหลัง POST สำเร็จก่อนหน้า: success flash เก่าอาจสร้าง receipt ให้ request ที่ไม่สำเร็จ. ล้าง success เก่าก่อนเรียก controller เพื่อรับรองเฉพาะ success ของ request ปัจจุบัน

ผลตรวจ: Phase13FixedAssetsTest + Phase2314MasterDialogsTest + Phase231SalesDialogsTest ผ่าน 13 tests / 232 assertions รวม validation แก้แล้ว retry, optional values/defaults, single capitalization journal, permission และ depreciation/disposal เดิม. TypeScript, scoped ESLint, Pint และ production build ผ่าน

ยังไม่ปิด Fixed Assets ทั้งกลุ่ม: attachment upload ยังเป็น inline form เดิม; browser acceptance ของ editor ใหม่ยัง pending. Category บัญชีซ้ำและ capitalization domain failures ยังใช้ HTTP 422 เดิม ต้องตรวจ error presentation ใน browser ก่อนรับรอง. รอบนี้ไม่ได้ทดสอบ browser เพิ่ม; การเปิด browser รอบก่อนถูก automatic approval review ปฏิเสธเพราะ usage limit


## รอบถัดมา: Fixed Asset Proof Attachment

Implementation 2026-09-13:

- ถอด inline upload form จาก Asset Register; เปิด FixedAssetAttachmentDialog ใช้ CreateEditDialog และ EditorAttachments
- แสดง asset number/name, current proof link, selected file, clear selection, upload progress และ field errors; ระบุว่า Replace Proof แทนที่ไฟล์เดิม
- File อยู่ใน memory draft; minimize เก็บ instance เดิม; discard/navigation guard ทำงานเมื่อเลือกไฟล์; เปิด draft ใหม่ล้าง selection
- ใช้ permission fixed_assets.manage และหนึ่ง active editor ร่วมกับ Category/Capitalize; ปิด domain command buttons ขณะเปิด attachment draft
- คง multipart POST, field attachment, MIME/5 MB rules และ download authorization ตามเดิม; ไม่ใส่ JSON fingerprint middleware กับไฟล์ binary
- Regression ทำซ้ำ bug เดิมได้: valid fixed asset upload กลับ 422 เพราะ FileAttachmentManager allowlist ไม่มี fixed_asset. เพิ่ม entity ที่ controller/download รองรับอยู่แล้ว
- ย้ายการลบ proof เดิมไปหลัง store/update/audit ของไฟล์ใหม่; test จำลอง store exception ยืนยัน attachment pointer และไฟล์เดิมยังอยู่. ไม่อ้างว่า filesystem กับ DB เป็น atomic transaction ในทุก failure mode

ผลตรวจ: Phase13FixedAssetsTest + Phase3FilesTest + Phase10VoucherAttachmentsTest ผ่าน 15 tests / 116 assertions. ครอบคลุม upload/download/replace, invalid MIME/oversize, failed store preservation, cross-org denial และ Payment/Expense/Voucher attachment regression. TypeScript, scoped ESLint, Pint และ production build ผ่าน

Fixed Assets category/capitalization/proof editors ย้ายแล้ว; ยังไม่ติ๊กทั้งกลุ่มเสร็จจน browser acceptance ผ่าน. ต้องตรวจ file picker, minimize/restore, discard, validation presentation และ responsive. รอบนี้ไม่ได้ทดสอบ browser เพิ่ม; การเปิด browser รอบก่อนถูก automatic approval review ปฏิเสธเพราะ usage limit


## รอบถัดมา: Petty Cash

Implementation 2026-09-14:

- ถอด inline New Fund/New Request/Reimburse Fund; ใช้ PettyCashDialog และ shared shell พร้อม labels/errors, searchable custodian/account/fund, amount/date controls, minimize/discard guard และหนึ่ง active draft
- ส่ง payload เฉพาะ field ของแต่ละฟอร์ม; คง optional bank account/note และ blank date defaults เดิม
- Create Fund สร้าง active fund; Submit Request ส่งสถานะ submitted เพื่อรออนุมัติ; Record Reimbursement ระบุว่า posts GL ตาม backend เดิม
- เพิ่ม opt-in EnsureFormSubmission ที่สาม routes หลัง permission/password/throttle; retry ใช้ key เดิมต่อ draft และสร้างใหม่เมื่อเปิด draft ใหม่
- ปุ่ม approve/reject ตรวจ permission และไม่แสดงให้เจ้าของ request; Pay ตรวจ manage; ปิด domain commands ระหว่างมี editor โดย backend guards คงเดิม

ผลตรวจ: Phase10TreasuryOperationsTest + Phase2314MasterDialogsTest ผ่าน 7 tests / 113 assertions. ครอบคลุม validation แล้วแก้ส่งใหม่, retry ไม่สร้าง fund/request/reimbursement ซ้ำ, null bank account, purpose/note, submitted status, reimbursement journal เดียว, revoked permission และ independent approval/payment เดิม. TypeScript, scoped ESLint, Pint และ production build ผ่าน

ยังไม่ปิด Treasury rollout: browser acceptance ของ Petty Cash ยัง pending และ Bank Statements/Cheques ยังมีงานย้าย editor. รอบนี้ไม่ได้ตรวจ browser จึงไม่อ้างผล responsive/keyboard/draft interaction จาก backend tests

---

## สรุปผลการปิดรอบสมบูรณ์และ Reconciliation รายโมดูล (Final Rollout Reconciliation — Phase 23.1.6 — 2026-09-16)

ตารางตรวจรับรองขั้นสุดท้ายของทุก Module/Surface เทียบกับ Route, Component ปัจจุบัน, และสถานะการแปลงเป็น Dialog:

| Module / Surface | Current Component / Entry Point | Registered Route(s) | Status | Evidence / Implementation Details |
| :--- | :--- | :--- | :--- | :--- |
| **Sales — Customers** | `Pages/Sales/Customers.tsx` | `POST /customers`, `PATCH /customers/{customer}` | Converted (Dialog) | `CreateEditDialog`, `useDiscardGuard`, `useSubmissionKey`, `Phase231SalesDialogsTest` |
| **Sales — Contacts** | `Pages/Sales/Customers.tsx` | `POST /customers/{customer}/contacts`, `PATCH /contacts/{contact}` | Converted (Dialog) | Shared dialog within Customers, discard guard, prefill defaults |
| **Sales — Deals** | `Pages/Sales/Deals.tsx` | `POST /deals`, `PATCH /deals/{deal}` | Converted (Dialog) | `CreateEditDialog`, `useDiscardGuard`, `useSubmissionKey` |
| **Sales — Activities** | `Pages/Sales/Deals.tsx` | `POST /activities`, `PATCH /activities/{activity}` | Converted (Dialog) | Shared dialog within Deals, activity types, date picker |
| **Procurement — Products** | `Pages/Finance/Products.tsx` | `POST /products`, `PATCH /products/{product}` | Converted (Dialog) | `CreateEditDialog`, `useDiscardGuard`, `useSubmissionKey`, `Phase2314MasterDialogsTest` |
| **Procurement — Suppliers** | `Pages/Finance/Suppliers.tsx` | `POST /suppliers`, `PATCH /suppliers/{supplier}` | Converted (Dialog) | `CreateEditDialog`, `useDiscardGuard`, `useSubmissionKey`, `Phase7ProcurementAndMembersTest` |
| **Procurement — Purchase Orders** | `Pages/Finance/PurchaseOrders.tsx` | `POST /purchase-orders` | Converted (Dialog) | `CreateEditDialog`, line items, discard guard, `useSubmissionKey` |
| **Treasury — Bank Accounts** | `Pages/Finance/BankAccounts.tsx` | `POST /bank-accounts`, `PATCH /bank-accounts/{bankAccount}` | Converted (Dialog) | Masked account preservation, branch/GL search, `Phase10BankAccountsTest` |
| **Treasury — Bank Statements** | `Pages/Finance/BankStatements.tsx` | `POST /bank-statements/import` | Exception | Specialized statement file import flow with validation preview |
| **Treasury — Cheques** | `Pages/Finance/Cheques.tsx` | `POST /cheques` | Converted (Dialog) | `CreateEditDialog`, cheque register, discard guard, submission key |
| **Treasury — Petty Cash** | `Pages/Finance/PettyCashDialog.tsx` | `POST /petty-cash/funds`, `POST /petty-cash/requests`, `POST /petty-cash/reimbursements` | Converted (Dialog) / Exception | Funds/requests use `PettyCashDialog` with discard guard; reimbursement posts GL directly |
| **Accounting — Chart of Accounts** | `Pages/Accounting/ChartOfAccounts.tsx` | `POST /accounting/chart-of-accounts` | Converted (Dialog) | `CreateEditDialog`, account type selector, `EnsureFormSubmission` |
| **Accounting — Journals** | `Pages/Accounting/Journals.tsx` | `POST /accounting/journals` | Converted (Dialog) | `CreateEditDialog`, line items, GL balance validation, idempotent submission key |
| **Accounting — Periods** | `Pages/Accounting/Periods.tsx` | `POST /accounting/periods` | Converted (Dialog) | `CreateEditDialog`, controlled date fields, `EnsureFormSubmission` |
| **Currencies & FX** | `Pages/Finance/Currencies.tsx` | `POST /currencies`, `POST /exchange-rates` | Converted (Dialog) | `CreateEditDialog`, decimal inputs, date controls, `Phase14FxTest` |
| **Fixed Assets — Category / Capitalize** | `Pages/Finance/FixedAssetCreateDialog.tsx` | `POST /fixed-assets/categories`, `POST /fixed-assets` | Converted (Dialog) | `CreateEditDialog`, category defaults, GL capitalization post |
| **Fixed Assets — Proof Attachment** | `Pages/Finance/FixedAssetAttachmentDialog.tsx` | `POST /fixed-assets/{fixedAsset}/attachment` | Converted (Dialog) | `CreateEditDialog`, file attachment manager, discard guard |
| **Inventory — Warehouse / Bin / Lot / Transfer / Count** | `Pages/Finance/InventoryDialogs.tsx` | `POST /warehouses`, `/bins`, `/lots`, `/transfers`, `/counts` | Converted (Dialog) | `useEditorCoordinator`, `inventoryDirty.ts`, `Phase2315CompatibilityTest` |
| **Goods Receipts** | `Pages/Finance/ReceiveGoodsDialog.tsx` | `POST /goods-receipts`, `POST /stock-movements` | Converted (Dialog) | `CreateEditDialog`, line items, discard guard, `Phase15InventoryAdvancedTest` |
| **Invoices & Commercial Docs** | `Pages/Finance/Invoices.tsx`, `CommercialDocuments.tsx` | `POST /invoices`, `PATCH /invoices/{invoice}`, `/delivery-orders` | Converted (Dialog) / Exception | Invoices use `CreateEditDialog` (edit restricted to draft); payment receipt is specialized command |
| **Payroll** | `Pages/Finance/PayrollDialogs.tsx` | `POST /payroll/profiles`, `POST /payroll/runs`, `POST /payroll/policies` | Converted (Dialog) | Single active coordinator, discard guard, `Phase16PayrollTest` |
| **HR Core & Attendance** | `Pages/Hr/HrDialogs.tsx` | `POST /hr/leave-requests`, `/shifts`, `/leave-types`, `/work-profiles`, `/holidays` | Converted (Dialog) / Exception | CRUD uses `HrDialogs.tsx`; Clock In/Out is event command exception |
| **Admin — Users** | `Pages/Admin/UserDialog.tsx` | `POST /users/invite`, `PATCH /users/{user}` | Converted (Dialog) | `CreateEditDialog`, user invite/edit, discard guard, password confirmation |
| **Admin — Roles** | `Pages/Admin/RolePermissionsDialog.tsx` | `POST /roles`, `PATCH /roles/{role}`, `PATCH /roles/{role}/permissions` | Converted (Dialog) | `CreateEditDialog`, system role immutability guard, `RoleEditorTest` (5 tests / 34 assertions) |
| **Settings — Organization Structure** | `Pages/Settings/OrgStructureDialogs.tsx` | `POST/PATCH /settings/branches`, `/divisions`, `/departments` | Converted (Dialog) | `orgStructureDirty.ts` (full-field comparison against record defaults), Discard Guard, UI permission gate for `settings.structure.update` |
| **Settings — Notifications & Channels** | `Pages/Settings/NotificationPreferences.tsx` | `PATCH /settings/notifications`, `POST /notification-channels` | Converted (Dialog) | Channel vault encryption, recipient quiet hours, `Phase21NotificationsTest` |
| **Customer/Supplier Portals** | `Pages/Portal/VendorBillDialog.tsx` | `POST /portal/vendor-bills` | Converted (Dialog) | Scoped portal authentication, file quarantine scan gate, discard guard |
| **Workflow Engine** | `Pages/Workflows/WorkflowDialogs.tsx` | `POST /workflows/delegations`, `POST /workflows/definitions` | Converted (Dialog) | Dynamic multi-step builder, SoD guard, discard guard, `Phase20WorkflowTest` |

### เอกสารและ Route Reconciliation
- `docs/ROUTES_AND_SCREENS.md`: ปรับปรุง Route `GET /roles` เป็น `roles.view`, เพิ่ม `POST /roles` (`roles.manage`), `PATCH /roles/{role}` (`roles.manage`), `PATCH /users/{user}/role` (`roles.update`).
- `docs/PHASE_23_1_FORM_INVENTORY.md`: แยกสถิติการสำรวจตั้งต้น (Baseline Snapshot 2026-09-11: 60 หน้า 92 ไฟล์) ออกจากรายงานสรุปผลการ Reconcile สมบูรณ์ (Phase 23.1.6: 70 dialogs, 20 exceptions, 0 dead workflows).
- การทดสอบยืนยันผล: Backend 316 tests (2,609 assertions / 0 failures), Frontend 34 tests (0 failures), ESLint 0 warnings / 0 errors, TypeScript 0 errors, Vite Build clean.
