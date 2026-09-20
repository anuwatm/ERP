# Phase 23.2.3 — Shared UI & Interaction / 23.2.4 Authorization & Query Architecture

สถานะ: **23.2.3–23.2.4 Complete (2026-09-17)**

เอกสารนี้กำหนด foundation ที่ rollout ใน Phase 23.2.5 ต้องใช้. 23.2.3 ปิดแล้วในขอบเขต shared UI/interaction และ 23.2.4 ปิดแล้วในขอบเขต authorization/query architecture. ยังไม่ถือว่า dashboard รายโมดูลถูก redesign หรือ drill-down ทุกตัวถูก wire/test แล้ว; งานนั้นอยู่ใน 23.2.5–23.2.7.

## Shared UI

| Component | หน้าที่ | Contract |
| --- | --- | --- |
| `DashboardLayout` | title, description, freshness | แสดงเวลาปรับปรุงเมื่อ server ส่งมา |
| `DashboardFilterBar` | URL-backed Today/This Week/This Month/Custom filter | ใช้ Inertia `router.get`, `replace`, preserve state/scroll; destination รับ URL params เท่านั้น |
| `DashboardKpiCard` | 1 question/metric พร้อม context | รองรับ ready/loading/unavailable/error และ retry; tone เป็น positive/risk/pending/neutral |
| `DashboardChart` | line/bar SVG | ใช้ SVG native, มี caption และ accessible data-table fallback |
| `DashboardBreakdown` | status share | stacked bar พร้อม text legend; สีไม่ใช่สัญญาณเดียว |
| `DashboardList` | action list | link เฉพาะ URL ที่ rollout ตรวจว่ามี route/filter จริง |
| `DashboardProgress` | 0–100 progress | native `progressbar` ARIA |

**Chart decision:** ใช้ native SVG renderer แทน third-party chart library. โปรเจกต์ไม่มี chart library และไม่เพิ่ม runtime dependency; SVG ครอบคลุม line/bar/status breakdown, responsive viewBox และ table fallback สำหรับ keyboard/screen reader. Donut/stacked-bar ที่ต้องตีความจากสีอย่างเดียวจะใช้ `DashboardBreakdown` พร้อม legend/table เสมอ.

## Interaction contract

- สี: positive=green, risk=red, pending=amber, neutral=blue/gray และต้องมี label/context ประกอบ.
- Empty data เป็น 0/empty message; no permission ไม่ render widget; source unavailable และ error ต้องแยกข้อความออกจาก 0.
- Drill-down ต้องใช้ route ที่มีจริง และส่ง URL filter semantic เดิม เช่น `period`, `from`, `to`, `status`, `owner`, `assignee`, `warehouse`. ปลายทางตรวจ permission/row scope ซ้ำ.
- Dashboard ไม่มี editor หรือ write transaction. ปุ่ม create/edit ใน rollout ใช้ dialog มาตรฐาน Phase 23.1 ของ destination; approve/payment/post คง step-up/idempotency เดิม.

## Backend access contract

`App\Support\DashboardAccess` เป็นจุดกลางสำหรับ effective permissions:

- ไม่มี role-name bypass: Owner/Admin ต้องมี permission จริงจาก role assignment เช่นเดียวกับ custom role.
- scope gate แยก Admin, Executive, Sales, Finance และ Delivery.
- widget gate แยก source permission เช่น Finance revenue/AR=`invoices.view`, Cash In=`payments.view`, Expense=`expenses.view`, Delivery projects/tasks แยกกัน.
- `Admin\DashboardController` ใช้ scope gate และส่ง `dashboardCapabilities` ซึ่งไม่มีข้อมูลธุรกิจ.
- `dashboardWidgets` ใช้ widget gate ก่อน query/serialize ทุก source; Finance/Executive/Delivery legacy composite props จะส่งเมื่อ source permission ครบเท่านั้น.
- Finance route เดิมคง `expenses.view` contract เพื่อรักษา bookmark/access เดิม. สิทธิ์ create/reverse โดยไม่มี `payments.view` ไม่ส่ง payment metric.

## Query and performance contract

- ใช้ aggregate query ต่อ widget/source; reuse finance definition ระหว่าง Executive/Finance แทนคัดลอกสูตร.
- ทุก query filter `org_id`; Sales/Project/Task ใช้ access scope ก่อน aggregate.
- list widget จำกัด Top N และใช้ pagination ที่ destination. ห้าม N+1 relationship load ใน aggregate.
- ปัจจุบันไม่มี dashboard cache. หากเพิ่ม cache ต้อง key ด้วย org, effective scope fingerprint, period, timezone และ base currency พร้อม TTL/invalidation test; ห้าม share cache ข้าม user/org.
- HR/Payroll และ My Work rollout จะ serialize summary ที่ต่ำสุดเท่านั้น: ไม่ส่ง `salary_base`, รายได้รายบุคคล, finance/deal data ที่ไม่มี source permission.

## งานส่งต่อ 23.2.5

- `GET /payments` is a permission-gated, org-scoped read-only destination; it accepts `entry_type`, `from`, and `to`.
- Invoice and Expense lists accept `from`/`to` against `issue_date`/`expense_date` for dashboard drill-down.
- Legacy Admin/Finance/Executive composite props are emitted only when every source permission required by that composite is present. New dashboard rollout consumes individual widget data for permitted partial views without serializing other sources.
- Each new module adds its own metric and drill-down tests, including revoked permission and row scope.
- ต้องประกอบ shared component กับข้อมูลจริง พร้อม scoped acceptance ในทุก dashboard ที่ระบุใน checklist.
- ต้องตรวจ org/row scope, query count/performance และ full cross-module regression ก่อนปิด Phase 23.2.
