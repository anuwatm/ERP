# HR Employee, Attendance & Payroll Integration — Phase 23.3

> สถานะ: **In progress / ไม่ผ่านการตรวจรับปิด Phase ณ 2026-09-20**

```mermaid
flowchart LR
    Employee["Employee Master\noptional User account"] --> Roster["Shift roster"]
    Shift["Shift Master\nrates / grace / threshold"] --> Roster
    Import["CSV/TXT import"] --> Raw["attendance_raw_logs"]
    Raw --> Recon["Reconciliation"]
    Roster --> Recon
    Recon --> Attendance["attendances\nlate / worked / OT"]
    Attendance --> Payroll["payroll_items\ndaily/hourly/monthly"]
    Payroll --> Payslip["PDF / email payslip"]

    Excel["Excel import"] -. pending .-> Raw
    Shift -. "rate source not applied" .-> Payroll
    Overnight["overnight shift"] -. pending .-> Recon
```

ระบบรองรับ Employee ที่ไม่มี ERP login, schedule รายบุคคล, import CSV/TXT, reconciliation และ payroll แบบรายวัน/รายชั่วโมงแล้ว. ก่อนปิด Phase ต้องเพิ่ม Excel import, ใช้ OT rate จาก Shift Master, นิยาม/คำนวณ 3x special holiday และรองรับกะข้ามวัน.

ดูรายละเอียด implementation, route, permission และ verification ที่ [`../docs/PHASE_23_3_HR_EMPLOYEE_ATTENDANCE.md`](../docs/PHASE_23_3_HR_EMPLOYEE_ATTENDANCE.md).
