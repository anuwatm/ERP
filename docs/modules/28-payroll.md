# Module: Payroll

| Meta | Value |
| --- | --- |
| Module code | `payroll` |
| Version | Phase 16B implemented |
| Status | Done |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §8.5–8.9 |

---

## 1. ชื่อ Module

**Payroll** — เงินเดือนและสลิป

---

## 2. รายละเอียด / หน้าที่

- คำนวณเงินเดือนจาก employee payroll profile ที่ active
- ล็อก version ของ policy ภาษีและประกันสังคมตามวันสิ้นงวด
- สร้าง payslip PDF และ workpaper CSV
- อนุมัติรอบจ่ายและ post GL; บันทึกการจ่ายแยกจาก `VendorPayment`

**ข้อควรระวัง:** เกี่ยวข้องกฎหมาย/ภาษี — ไม่ทำเต็มในระยะแรก  
ระบบนี้เป็น operational payroll เบื้องต้น ไม่ทดแทนระบบบัญชีภาษี

---

## 3. Workflow

### 3.1 เปิดรอบจ่าย

```text
สร้าง `payroll_runs` (period_start, period_end, payment_date) status=draft
→ lock `payroll_tax_policies` + `social_security_policies`
→ calculate สร้าง `payroll_items` พร้อม snapshot
→ approve + GL posting → status=approved
→ mark paid + GL settlement → status=paid
```

### 3.2 ออก payslip

```text
Employee ดูได้เฉพาะ `payroll_item.user_id` ของตนเอง
→ export PDF
→ Finance ที่มี `payroll.view` ดูได้ตาม org
```

---

## 4. Data Flow

```text
users ──1:1── employee_payroll_profiles
                    │
                    ▼
policy versions ──► payroll_runs 1──* payroll_items
                    │                 │
                    ├──► journal_entries
                    └──► payslip PDF / CSV workpapers
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `employee_payroll_profiles` | salary, fixed adjustments, payment and social-security setting per user |
| `payroll_tax_policies` | effective-dated PIT brackets and deductions |
| `social_security_policies` | effective-dated contribution rates and ceiling |
| `payroll_runs` | payroll period, locked policy IDs, totals and lifecycle |
| `payroll_items` | per-user calculation snapshot; source for payslip |

### Business rules

- 1 profile ต่อ run ไม่ซ้ำ (`UNIQUE(payroll_run_id, employee_payroll_profile_id)`)
- calculated/approved/paid run แก้ profile หรือ policy ย้อนหลังไม่ได้
- payslip ใช้ owner-or-`payroll.view` guard
- ภ.ง.ด.1/SSO CSV เป็น workpaper ไม่ใช่ certified filing format
