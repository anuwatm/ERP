# คู่มือทำความเข้าใจ ERP ทีละโมดูล

วิเคราะห์จาก source ใน working tree ณ **2026-10-24 (Asia/Bangkok)**; HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3`

เอกสารชุดนี้ใช้อ้างอิงก่อนแก้โค้ด: หน้าที่, routes/permissions, entities, workflows/statuses, dependencies, frontend, schema และ tests ที่เกี่ยวข้อง พร้อมข้อจำกัดที่อ่านพบใน implementation

## เริ่มอ่าน

1. [00 ภาพรวมสถาปัตยกรรม](00_architecture.md)
2. [01 องค์กร/โครงสร้าง/เลขเอกสาร](01_foundation_organization.md) → [02 Auth/Security](02_authentication_security.md) → [03 Admin/Audit](03_administration_audit.md)
3. เลือก domain ในตาราง แล้วตาม dependency ของบทนั้น
4. ใช้ [Source map](SOURCE_MAP.md) ค้น Controller/Service ว่าอยู่ในบทใด
5. อ่าน [UI/editor lifecycle](20_frontend_dialogs.md) ก่อนแก้ฟอร์ม และ [Setup/Operations](21_setup_operations.md) ก่อนเปลี่ยน deployment

## สารบัญโมดูล

| ลำดับ | โมดูล | สิ่งที่ต้องเข้าใจ |
| --- | --- | --- |
| 00 | [Architecture](00_architecture.md) | Entry points, stack, layers, cross-module flow |
| 01 | [Organization & Foundation](01_foundation_organization.md) | org/branch/division/department, settings, numbering |
| 02 | [Authentication & Security](02_authentication_security.md) | login/invite/session, RBAC, TOTP/trusted device |
| 03 | [Administration & Audit](03_administration_audit.md) | users/roles/custom permissions, session revoke, audit |
| 04 | [CRM & Sales](04_crm_sales.md) | customer/contact/deal/activity, ownership scope |
| 05 | [Quotations & Commercial Documents](05_quotations_commercial_documents.md) | quotation, CN/DN, billing note, DO, PR, voucher |
| 06 | [Billing, Payments & Expenses](06_billing_payments_expenses.md) | Invoice/AR, receipt/reversal, Expense/AP, vendor payment |
| 07 | [Procurement & Inventory](07_procurement_inventory.md) | Supplier/Product, PO/GR, stock/location/lot/count/transfer |
| 08 | [Projects & Tasks](08_projects_tasks.md) | deal-to-project, member/assignee, task visibility, project cost |
| 09 | [Accounting, FX & Assets](09_accounting_fx_assets.md) | GL, posting/reversal, periods, FX, fixed assets |
| 10 | [Treasury & Banking](10_treasury_banking.md) | bank/statement/reconciliation, petty cash, cheques |
| 11 | [Tax & e-Tax](11_tax_etax.md) | tax reports, XML, signature implementation, mock/provider |
| 12 | [HR, Attendance & Payroll](12_hr_attendance_payroll.md) | employees/positions/import/roster, leave/OT/payroll |
| 13 | [Manufacturing](13_manufacturing.md) | BOM, work order, material/cost/stock/GL |
| 14 | [Documents & Files](14_documents_files.md) | version, scan, sensitivity, parent ACL, retention/hold |
| 15 | [Workflows & Approvals](15_workflows_approvals.md) | definitions, steps, delegation, subject effects |
| 16 | [Notifications & Automation](16_notifications_automation.md) | in-app/mail/external outbox, dedupe/retry/scheduler |
| 17 | [Portal & Payment Gateway](17_portal_gateway.md) | magic link, party scope, QR intent, settlement |
| 18 | [External API & Webhooks](18_external_api_webhooks.md) | scopes/principals, cursor/watermark, private download/HMAC |
| 19 | [AI OCR](19_ai_ocr.md) | provider/config, upload/extract/review/convert, limits |
| 20 | [Frontend & Dialogs](20_frontend_dialogs.md) | Inertia, draft/minimize/guard, retry key, Profile |
| 21 | [Setup & Operations](21_setup_operations.md) | installer, runtime paths, queue/schedule, test commands |
| 22 | [Dashboards & Reporting](22_dashboards_reporting.md) | source permissions, metric lineage, date/currency |
| 23 | [Cash Flow Forecasting](23_cash_flow_forecasting.md) | scenarios, formulas, snapshots, financial health assumptions |

หมายเลขบทชุดนี้เป็นลำดับการเรียนรู้ ไม่ใช่ Phase ใน tests หรือหมายเลขเอกสาร diagram เดิม

## Business flows ที่ใช้ตามอ่าน

- ขาย: [CRM](04_crm_sales.md) → [Quotation](05_quotations_commercial_documents.md) → [Invoice/Payment](06_billing_payments_expenses.md) → [GL/FX](09_accounting_fx_assets.md) → [Tax](11_tax_etax.md)
- ซื้อ: [PR](05_quotations_commercial_documents.md) → [PO/GR/Stock](07_procurement_inventory.md) → [Expense/VendorPayment](06_billing_payments_expenses.md) → [Treasury](10_treasury_banking.md)
- ส่งมอบ: [Won Deal](04_crm_sales.md) → [Project/Task](08_projects_tasks.md) → ค่าใช้จ่ายตามโครงการ
- บุคลากร: [Employee/Roster/Attendance](12_hr_attendance_payroll.md) → Payroll → [GL](09_accounting_fx_assets.md)
- ผลิต: [Inventory](07_procurement_inventory.md) → [BOM/WorkOrder](13_manufacturing.md) → Stock/GL
- เชื่อมภายนอก: [Integration Key/Read API/Webhook](18_external_api_webhooks.md) ต่างจาก [Portal/Gateway](17_portal_gateway.md)

ลูกศรเป็นลำดับการอ่านและความสัมพันธ์หลัก ไม่ได้ยืนยันว่าระบบ automate ทุกขั้นตอน

## ประเด็น implementation ที่ควรอ่านก่อนต่อยอด

| เรื่อง | จุดอ่านรายละเอียด |
| --- | --- |
| Tenant/ownership/permission ไม่ใช่ gate เดียวกันทุกโมดูล | 01–04, 08, 14, 18, 22 |
| Payment เต็มจำนวนกับ partial ใช้เส้นทาง/guards ต่างกัน | 06 |
| Conversion/stock aggregate locks ต้องพิสูจน์ concurrency เพิ่ม | 05, 07, 08, 13 |
| Signature e-Tax มี HMAC/certificate placeholder แม้ระบุ RSA-SHA256 | 11 |
| HR employee-centric กับ leave/user-centric ต้องอ่าน bridge | 12 |
| Workflow parallel เป็น first approval wins ใน step | 15 |
| Provider toggles/credentials ไม่ใช่หลักฐานพร้อม production | 11, 16–19 |
| เปิดหน้า Cash Flow สร้าง snapshot/log และมีสูตรประมาณ | 23 |

เป็นข้อสังเกตจาก static reading ไม่ใช่ confirmed runtime defect ทุกข้อ แต่ละบทระบุ source/เงื่อนไขไว้ให้ตรวจซ้ำ

## ขอบเขตและความสดของข้อมูล

- ตรวจ source หลักของทุกกลุ่มโมดูล รวม routes, controller/service flows, models, UI, migrations และ representative tests
- ไม่ใช่การอ่านทุกบรรทัด ไม่ใช่ผล UAT/security audit และไม่ยืนยัน provider production หรือกฎหมายภาษีปัจจุบัน
- ชื่อ tests/assertions อธิบาย contract ที่คาดหวัง ไม่ใช่ผลผ่านล่าสุด
- รอบสำรวจก่อนหน้ารัน Node history/API client/webhook verifier รวม 22 tests ผ่าน; รอบจัดทำเอกสารไม่ได้รัน PHP/build/browser tests
- เอกสารรายโมดูลชุดแรกสร้างด้วย direct source analysis; ภายหลังแก้ runtime และทำ full pipeline ของ skill `understand` ครบแล้ว ดู [ผล Full Pipeline และ Knowledge Graph](../UNDERSTAND_PIPELINE.md)
- เมื่อโค้ดเปลี่ยน ให้อัปเดตบทที่เกี่ยวข้องพร้อม routes/permissions/statuses และ dependencies ตาม checklist ด้านล่าง

ผลตรวจชุดเอกสารเดิมก่อน full pipeline: 24 บท + สารบัญ + Source map รวม 26 ไฟล์ ตรวจ relative links 796 จุด ไม่พบปลายทางหาย, trailing whitespace, code fence ที่ปิดไม่ครบ หรือบทที่ตกจากสารบัญ; Source map จัดหมวด Controllers 80 และ Services 55 ไฟล์ครบตาม inventory การตรวจนี้ยืนยันโครงสร้างเอกสาร ไม่ได้ยืนยัน runtime behavior

## Checklist ดูแลเอกสาร

1. ระบุ snapshot/commit และ working-tree context ใหม่
2. ตรวจ route จริงทั้ง method/name/middleware รวม controller guards
3. ตรวจ model constants, migrations ล่าสุด และ service ที่ถูกเรียกจริง
4. ตรวจผลข้างเคียง GL/stock/notification/webhooks/files เมื่อแก้สถานะ
5. ตรวจ source links และอธิบาย tests ที่รันจริงแยกจาก tests ที่อ่าน
6. ถ้าแผนกับ implementation ต่างกัน ให้ระบุทั้งคู่ ไม่คัดลอกแผนมาอ้างว่าโค้ดทำแล้ว

เอกสารเดิม: [Diagram index](../README.md), [Group workflows](../GROUPS_WORKFLOW.md), [Database ERD](../DATABASE_ERD.md), [External API reference](../API_Document.md)
