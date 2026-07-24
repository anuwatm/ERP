# Module: Import / Export

  Meta   Value  
  ---   ---  
  Module code   `import_export`  
  Version   V1 (CSV/PDF), Excel ใน V2  
  Priority   P1  
  Schema กลาง   [`../database/DATABASE.md`](../database/DATABASE.md) §9.12, §9.1  

---

## 1. ชื่อ Module

**Import / Export** — ย้ายข้อมูลเข้าออก

---

## 2. รายละเอียด / หน้าที่

- Import CSV: customers, products, invoices
- Validate ก่อน import + preview ก่อน confirm
- Export CSV ของข้อมูลหลัก
- Export PDF: invoice, quotation, report
- ป้องกัน CSV formula injection
- Excel export ใน V2

---

## 3. Workflow

### 3.1 Import

```text
Upload CSV → files
→ สร้าง import_jobs (status=validating)
→ parse + validate rows
→ status=preview (โชว์ errors)
→ user confirm
→ status=importing → insert/update target tables
→ status=done / failed + error_json
→ audit
```

### 3.2 Export

```text
เลือก entity + filter
→ generate CSV/PDF
→ download
→ audit export action
```

---

## 4. Data Flow

```text
CSV file → files → import_jobs
                      │
                      ├──► customers / products / invoices ...
                      └──► error_json feedback

DB tables → Export service → CSV/PDF stream
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

  Table   Role  
  ---   ---  
  `import_jobs`   สถานะงานนำเข้า  

### ตารางร่วม

  Table   Role  
  ---   ---  
  `files`   ไฟล์ต้นทาง  
  target entities   customers, products, invoices ฯลฯ  

### Business rules

- sanitize ค่าที่ขึ้นต้นด้วย `=`, `+`, `-`, `@` ใน CSV
- preview บังคับก่อน commit ชุดใหญ่
- ทุกแถว inherit `org_id` ของ actor
- partial success ต้องรายงาน success_rows / error_rows
