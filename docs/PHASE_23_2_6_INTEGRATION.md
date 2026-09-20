# Phase 23.2.6 — Dashboard / Phase 23.1 integration contract

สถานะ: implementation contract in place; acceptance ยังขึ้นกับ Phase 23.2.5/23.2.7 regression และ browser checks.

## Read and navigation contract

- Dashboard รับข้อมูลแบบ read-only และใช้ `DashboardList` พร้อม named route + `routeParameters` เพื่อเปิดรายการที่กรองตรงกับ widget. ปลายทางรับ filter จาก URL แล้วตรวจ permission, org และ row scope ใหม่เสมอ.
- Filter ที่มีใน rollout: `task_id`, `project_id`, `po_id`, `status`, `supplier_id`, `currency`, `committed`, `receipt_id`, `approval_id`, `leave_request_id`, `run_id` และ date/owner filters ของ Finance/Sales/Delivery. Dashboard ไม่ส่ง form state หรือข้อมูลธุรกรรมลับผ่าน URL.
- Filtered link คือ GET navigation เท่านั้น. เมื่อ back/refresh จะคง URL filter; destination ไม่เปิด Create/Edit dialog อัตโนมัติ. ผู้ใช้เปิด dialog ของโมดูลปลายทางเองตามสิทธิ์เดิม.
- ถ้า user มีสิทธิ์ดู widget แต่ไม่มีสิทธิ์หน้า drill-down จะไม่ render link (workflow approval กรณีมี `workflows.approve` แต่ไม่มี `workflows.view`).

## Create/Edit and transaction contract

- ไม่มี Create/Edit editor ซ้ำใน dashboard. Dialog ของปลายทางยังใช้ `CreateEditDialog`/`useDiscardGuard`/`useSubmissionKey` ตาม Phase 23.1; permission สำหรับ create/update ตรวจทั้ง UI และ route. Unsaved-change guard ทำงานเฉพาะเมื่อ dialog ปลายทางเปิด.
- Dashboard ไม่เรียก POST/PUT/PATCH/DELETE สำหรับ approve, post, record payment, reverse หรือ security action. การคลิก KPI/chart/list เป็น GET; mutation อยู่ในหน้าปลายทางพร้อม authorization, step-up, confirmation และ idempotency เดิม.
- Portal แยกจาก internal dashboard: quotation acceptance และ vendor bill submission ใช้ portal grant/session และ flow เดิม; summary นับเฉพาะเอกสารที่ party grant เห็น.
- ไม่ขยายขอบเขตไป settlement bridge, live e-Tax, forecasting Phase 25 หรือโมดูลใหม่เพื่อเติม widget.

## Acceptance evidence

- `DashboardAccessTest`, `Phase4DeliveryDashboardTest`, `Phase22PortalTest` ตรวจ permission/tenant/task/portal payload และ filtered destination.
- `Phase1AdminTest` ตรวจไม่ปลอม operational state เป็น Security Alert.
- TypeScript, ESLint, Pint และ production build ต้องผ่านหลัง rollout; browser/mobile/accessibility/performance acceptance ยังอยู่ใน Phase 23.2.7.
