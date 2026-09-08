# Module: Files / Documents

| Meta | Value |
| --- | --- |
| Module code | `files` |
| Version | Current — Phase 17 DMS |
| Priority | P0 |
| Schema กลาง | [`../../document/DATABASE_ERD.md`](../../document/DATABASE_ERD.md) §11 |

---

## 1. ชื่อ Module

**Files / Documents** — คลังเอกสารองค์กรแบบ versioned และการผูกเข้ากับข้อมูลธุรกิจ

---

## 2. รายละเอียด / หน้าที่

- เก็บไฟล์ private storage แยกองค์กร, SHA256 checksum และ version history
- รองรับ PDF/JPEG/PNG/WebP; production ใช้ `pending_scan` จน external scanner เปลี่ยนเป็น `clean`
- จัด category, sensitivity 5 ระดับ, expiry/renewal alert, retention/legal-hold metadata
- ผูกกับ allowlisted business models ด้วย polymorphic document link
- เอกสาร legacy `files` ยังมีอยู่เพื่อรองรับ attachment เดิม

---

## 3. Workflow

### 3.1 Upload + attach

```text
User เลือก category และไฟล์
→ validate type/size → private storage
→ create document version พร้อม SHA256 และ `pending_scan`/`clean`
→ link กับ business entity ที่ allowlist
→ audit log
```

### 3.2 ดาวน์โหลด / preview

```text
ตรวจ `documents.view`, org, sensitivity และ `scan_status = clean`
→ stream ผ่าน DocumentController
→ preview ฝั่ง UI ถ้า mime รองรับ

Document link และ download ตรวจ parent authorization ทุก link เพิ่มจาก `documents.download`, org, sensitivity และ scan status; ผู้ใช้ต้องมองเห็น parent ทุกตัวที่ผูกอยู่
```

### 3.3 Version และ retention metadata

```text
เพิ่ม version ใหม่แบบ append-only
→ `current_version_id` ชี้ version ล่าสุด
→ บันทึก `retention_until` / `legal_hold`
→ scheduler archive เมื่อครบ retention และไม่มี legal hold; purge ใช้ `documents:enforce-retention --purge` เท่านั้น
```

---

## 4. Data Flow

```text
[Client upload]
      │
      ▼
Document service ──► Private storage
      │
      ▼
 documents ──► document_versions
      │
      └──► document_links (polymorphic business entities)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `documents` | metadata เอกสาร, sensitivity, expiry และ retention metadata |
| `document_versions` | immutable version metadata และ checksum |
| `document_links` | polymorphic link ไปข้อมูลธุรกิจ |
| `document_categories` | category และ default policy |
| `retention_policies` | minimum retention และ legal-hold requirement |

### Field สำคัญ

`storage_key`, `checksum_sha256`, `scan_status`, `sensitivity`, `expires_at`, `retention_until`, `legal_hold`, `linkable_type`, `linkable_id`

### Business rules

- validate MIME/ขนาดฝั่ง server และสร้าง `storage_key` ฝั่ง server
- download อนุญาตเฉพาะ org เดียวกัน, `documents.download`, sensitivity ที่ role เข้าถึงได้, parent ทุกตัวที่ผูกอยู่ และ version ที่ `clean`
- version เดิมไม่ถูกแก้ไข; เพิ่มได้เฉพาะ version ใหม่
- category policy คำนวณ `retention_until`, default renewal และ legal hold; failed scan ถูก quarantine และ legal hold กัน archive/purge
