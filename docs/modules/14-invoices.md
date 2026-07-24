# Module: Invoices

| Meta | Value |
| --- | --- |
| Module code | `invoices` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §6.3–6.4 |

---

## 1. ชื่อ Module

**Finance: Invoices** — ออกใบแจ้งหนี้และติดตามการเก็บเงิน

---

## 2. รายละเอียด / หน้าที่

- Phase 3: สร้าง invoice จาก deal หรือสร้างเองแบบ manual ได้โดยไม่ต้องมี project
- Phase 4: เพิ่มการผูก invoice กับ project (`project_id`)
- เลข invoice อัตโนมัติ
- หลายบรรทัดสินค้า/บริการ + discount + tax
- สถานะ: draft, sent, partially_paid, paid, overdue, void
- export PDF/CSV (Post-MVP)
- แจ้งเตือนใกล้ครบกำหนด (Post-MVP)
- aging view ใน invoice list (reports เต็ม = Post-MVP)
- ป้องกันแก้ invoice ที่ paid แล้วโดยไม่มี audit

**หมายเหตุ:** MVP เป็น invoice tracking + VAT calculation mode เท่านั้น. UI ห้ามสื่อว่าเป็น “ใบกำกับภาษี” หรือ e-Tax invoice ตามกฎหมายเต็มรูปแบบ.

---

## 3. Workflow

### 3.1 สร้างและส่ง

```text
เลือกแหล่ง Phase 3: deal/manual (project = Phase 4; quotation = Post-MVP)
→ เพิ่ม invoice_items
→ คำนวณ subtotal/tax/total
→ gen invoice_no
→ status draft → sent (set sent_at)
→ mark sent (PDF/email = Post-MVP)
```

### 3.2 รับชำระ (ร่วม Payments)

```text
Record Payment
→ อัปเดต paid_amount, balance_due
→ status: partially_paid | paid
→ ถ้า paid ครบ set paid_at
```

### 3.3 Overdue

```text
Job รายวัน: due_date < today AND balance_due > 0 AND status in (sent, partially_paid)
→ status=overdue
→ dashboard แสดง overdue (notification = Post-MVP)
```

### 3.4 Void

```text
Admin/Finance void invoice (ต้องไม่มี payment net; ถ้ามีต้อง reverse payment ก่อน)
→ status=void, voided_at
→ audit_logs
→ ถ้ามาจาก deal และไม่มี invoice อื่นที่ใช้งานอยู่ใต้ deal นั้น ให้แสดง derived flag `needs_sales_review` บน deal/invoice detail เพื่อให้ Sales Owner ตรวจ stage/value; MVP ไม่ auto-reopen deal และไม่ใช้ notifications module
→ ไม่นับใน revenue
```

---

## 4. Data Flow

```text
customers / products / deals / manual
Phase 4: projects
                │
                ▼
         invoices 1──* invoice_items
                │
                ├──► payments (เงินเข้า)
                ├──► milestones.invoice_id (Post-MVP)
                ├──► files (payment attachment = limited MVP; invoice PDF/snapshot = Post-MVP)
                └──► accounting_sync_logs (V2)
                        │
                        ▼
              Dashboard mini / Aging view (Reports full = Post-MVP)
```

### Status transitions

```text
draft → sent → partially_paid → paid
  │       │
  │       └──► overdue ←→ partially_paid/sent
  └──► void
sent/partially_paid/overdue → void (policy-based; net payment ต้องเป็น 0)
paid → ห้าม void โดยตรง; ต้อง reverse payment จน net payment = 0 ก่อน
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `invoices` | header |
| `invoice_items` | lines |

### Field เงิน

`subtotal`, `discount_amount`, `tax_amount`, `total`, `paid_amount`, `balance_due`

### Business rules

- Phase 3 invoice source = `deal_id` หรือ manual; `project_id` nullable
- Phase 4 ค่อยผูก `project_id` เพื่อ project revenue/profit
- `paid_amount = sum(receipt.amount) - sum(reversal.amount)` จาก payments
- `balance_due = total - paid_amount`
- invoice ที่มี payment ห้าม void จนกว่า reverse payment ครบ; paid invoice ห้ามแก้ยอดโดยตรง
- `tax_mode` รองรับ `exclusive`, `inclusive`, `no_tax`; label UI ควรใช้ “VAT calculation mode” / “ภาษีมูลค่าเพิ่ม (คำนวณ)” ไม่ใช้คำว่าใบกำกับภาษี
- `invoice_no` เป็น text 6 หลัก และ unique ต่อ org
- numbering มาจาก `number_sequences` / Settings




