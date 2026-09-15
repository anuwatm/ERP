# Phase 23.1.1 — ต้องเปลี่ยนอะไร / เปลี่ยนแล้วอะไร

**Scope & Inventory เสร็จแล้ว**. รอบนี้จัดทำรายการและหลักฐาน ไม่ได้เปลี่ยนหน้าจอเพิ่ม.

| สถานะ | จำนวน | ความหมาย |
| --- | ---: | --- |
| เปลี่ยนแล้ว | 4 | Sales shared dialog; ยังรอ browser regression |
| ต้องเปลี่ยน | 66 | ฟอร์มเดิมยังไม่ได้ย้ายเข้า shared dialog |
| ต้องแก้ integration แล้วเปลี่ยน | 1 | Attendance Privacy เรียก route ที่ยังไม่ได้ register |
| ข้อยกเว้น | 20 | Security, filter, import และ command forms คง workflow เฉพาะ |

นับตามฟอร์มที่ render จริง: Create/Edit ที่ใช้ฟอร์มร่วมกันนับหนึ่ง ส่วนที่แยกฟอร์มนับแยก. นอกเหนือจาก 91 แถวนี้ มี 12 write routes ที่ไม่มี UI editor — ไม่สร้างฟอร์มใหม่โดยอัตโนมัติ.

## เปลี่ยนแล้ว

| ฟอร์ม | ไฟล์ที่เปลี่ยน | ผลที่เห็น |
| --- | --- | --- |
| Customer | Sales/Customers.tsx, CustomerDialog.tsx | dialog สร้าง/แก้ไข, owner search, validation, draft guard |
| Contact | Sales/Customers.tsx, ContactDialog.tsx | dialog บนลูกค้าเดิม, primary contact, draft guard |
| Deal | Sales/Deals.tsx, DealDialog.tsx | dialog, customer/contact/owner search, stage rules |
| Activity | Sales/Deals.tsx, ActivityDialog.tsx | dialog สร้าง/แก้ไข, follow-up, UTC dates, owner search |

ทั้ง 4 ใช้ CreateEditDialog.tsx + DiscardChangesDialog.tsx + useDiscardGuard.ts + historyGuard.ts; มี normal/expanded/minimized. Quick-create reference และ browser acceptance ยังไม่เสร็จ. ผลทดสอบรอบก่อน: Sales 12 tests / 196 assertions, history 9 tests, TypeScript/ESLint/build ผ่าน.

## ยังต้องเปลี่ยน — แยกตามหน้า

| หน้า | ฟอร์มที่ต้องเปลี่ยน |
| --- | --- |
| Accounting/ChartOfAccounts.tsx | Account (F001) |
| Accounting/Journals.tsx | Manual journal (F002) |
| Accounting/Periods.tsx | Accounting period (F003) |
| Admin/Roles.tsx | Role permissions (F004) |
| Admin/Users.tsx | Invite user (F005), Edit user (F006) |
| Delivery/Projects.tsx | Project (F007), Project member (F008) |
| Delivery/Tasks.tsx | Task (F009), Task checklist item (F010), Task comment (F011) |
| Documents/Index.tsx | Document and initial version (F012), Retention policy (F013), Document category (F014), Document version (F015), Document link (F016) |
| Finance/BankAccounts.tsx | Bank account (F017) |
| Finance/Cheques.tsx | Cheque (F019) |
| Finance/CommercialDocuments.tsx | Delivery order (F020), Voucher attachment (F078) |
| Finance/Currencies.tsx | Currency (F021), Exchange rate (F022) |
| Finance/ETax.tsx | ETax configuration (F023) |
| Finance/Expenses.tsx | Expense (F025) |
| Finance/FixedAssets.tsx | Asset category (F027), Capitalize asset (F028), Asset attachment (F029) |
| Finance/GoodsReceipts.tsx | Goods receipt (F030), Stock adjustment (F031) |
| Finance/InventoryOperations.tsx | Warehouse (F032), Warehouse bin (F033), Inventory lot (F034), Stock transfer (F035), Stock count (F036) |
| Finance/Invoices.tsx | Invoice (F038) |
| Finance/Payroll.tsx | Payroll profile (F039), Payroll run (F040), Payroll tax and social security policy (F041) |
| Finance/PettyCash.tsx | Petty cash fund (F042), Petty cash request (F043) |
| Finance/Products.tsx | Product (F045) |
| Finance/PurchaseOrders.tsx | Purchase order (F046) |
| Finance/Quotations.tsx | Quotation (F047) |
| Finance/Suppliers.tsx | Supplier (F048) |
| Hr/Index.tsx | Leave request (F050), Attendance summary (F051), Employee shift (F052), Leave type (F053), Employee work profile (F054), Holiday (F055) |
| Portal/Dashboard.tsx | Vendor bill intake (F056) |
| Profile/Partials/UpdateProfileInformationForm.tsx | Profile information (F057) |
| Settings/NotificationPreferences.tsx | Notification preferences (F058), External notification preferences (F059), Notification channel (F060) |
| Settings/Organization.tsx | Organization information (F061), Document numbering formats (F062), Attendance privacy policy (F064) |
| Settings/OrganizationStructure.tsx | Create branch (F065), Create division (F066), Create department (F067), Edit branch (F068), Edit division (F069), Edit department (F070) |
| Settings/PaymentGateway.tsx | Payment gateway configuration (F071) |
| Workflows/Index.tsx | Workflow delegation (F072), Workflow definition and steps (F073) |

แต่ละฟอร์มย้ายตัวครอบเดิมเป็น shared dialog, เพิ่มปุ่มเปิดตาม action ที่มีอยู่, คง payload/line items/ไฟล์/สิทธิ์/สถานะ, เพิ่ม draft/error/focus handling และตรวจ desktop/mobile. ยังไม่เปลี่ยน backend business rules ตามการออกแบบ UI.

## จุดที่ต้องแก้ให้ตรง backend

| จุด | ปัจจุบัน | ต้องแก้ |
| --- | --- | --- |
| G01 — Settings/Organization.tsx:515 | เรียก settings.organization.attendance-privacy.update แต่ route ไม่มี; controller มี method แล้ว | เชื่อม route พร้อม middleware ที่เหมาะสมและทดสอบ ก่อนย้าย dialog |
| G02 — Finance/Invoices.tsx:468 | Edit แสดงเมื่อไม่ void และ paid_amount <= 0 | จำกัดตาม draft + permission ให้ตรง InvoiceController::update |
| G03 — Quotation/PO/COA และเอกสารการค้า | backend มี action แต่ UI ยังไม่มี editor บางรายการ | บันทึก backend-only; แยกเป็น feature เพิ่ม ไม่ถือว่าย้าย dialog แล้ว |

G01/G02 **ยังไม่ได้แก้โค้ดในรอบ inventory นี้**.

## หลักฐานและรายการครบ

- [91 form contracts + route/page coverage](PHASE_23_1_FORM_INVENTORY.md): fields, references, line items, attachment, permission, business state, เดิม/ใหม่, ผลตรวจ และข้อยกเว้นรายฟอร์ม
- [Machine-readable source evidence](PHASE_23_1_FORM_INVENTORY.json): UI snippets, payload initializers, exact middleware และ guard evidence
- [Rollout order / acceptance](PHASE_23_1_DIALOG_ROLLOUT.md)
- [Checklist กลาง](../checklist.md)

ตรวจ 60 Pages, 92 JS/TS source files และ 252 registered routes (173 write routes), รวม shared components, dynamic route names, dashboard links, Portal และ security flows. Inventory ผ่าน coverage checks; runtime/browser ของโมดูลที่ยังไม่ย้ายยังไม่ได้ทดสอบในรอบนี้.
