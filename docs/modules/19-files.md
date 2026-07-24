# Module: Files / Documents

| Meta | Value |
| --- | --- |
| Module code | `files` |
| Version | V1 — MVP subset เฉพาะ payment/expense attachment; full module หลัง MVP |
| Priority | P0 limited / P1 full |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.1 |

---

## 1. ชื่อ Module

**Files / Documents** — เอกสารแนบกับลูกค้า งาน และการเงิน

---

## 2. รายละเอียด / หน้าที่

- Upload ไฟล์ไป object storage / disk
- MVP attach เฉพาะ payment/expense; attach กับ customer, deal, project, invoice, task ฯลฯ = Post-MVP
- Preview พื้นฐาน, จัด category
- ควบคุมสิทธิ์การเข้าถึงตาม entity แม่
- เก็บ activity/log ผ่าน audit หรือ file metadata

---

## 3. Workflow

### 3.1 Upload + attach

```text
User เลือกไฟล์บน payment/expense flow (MVP)
→ validate type/size
→ store binary → storage_key
→ insert files (entity_type, entity_id)
→ audit (optional)
```

### 3.2 ดาวน์โหลด / preview

```text
ตรวจ permission บน parent entity
→ gen signed URL หรือ stream
→ แสดง preview ถ้า mime รองรับ
```

### 3.3 Soft delete

```text
deleted_at set
→ ซ่อนจาก UI
→ ลบ physical ทีหลัง (GC job)
```

---

## 4. Data Flow

```text
[Client upload]
      │
      ▼
File service ──► Object storage
      │
      ▼
    files ◄── payments.attachment_file_id
           ◄── expenses.receipt_file_id
           ◄── import_jobs.file_id
           ◄── polymorphic entity_* จากหลาย module
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `files` | metadata ไฟล์ |

### Field สำคัญ

`storage_key`, `file_name`, `mime_type`, `size_bytes`, `category`, `entity_type`, `entity_id`, `uploaded_by`

### Business rules

- validate extension/mime ฝั่ง server
- จำกัดขนาดไฟล์
- ไม่เก็บไฟล์นอก org ของ user
- payment/expense อ้าง `files.id` เป็น FK
- `storage_key` สร้างฝั่ง server ด้วย UUID/random path; ห้ามใช้ filename/path จากผู้ใช้โดยตรง
