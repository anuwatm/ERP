# Module: AI Assistant

| Meta | Value |
| --- | --- |
| Module code | `ai` |
| Version | V2 |
| Priority | P2 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.10 |

---

## 1. ชื่อ Module

**AI Assistant** — ผู้ช่วยอัจฉริยะบนข้อมูลบริษัท

---

## 2. รายละเอียด / หน้าที่

- สรุป deal
- draft follow-up message
- สรุป meeting notes
- แนะนำ next action
- generate project brief จาก deal
- classify customer notes
- search knowledge base

### ข้อควรระวัง

- ต้องมี fallback ถ้าไม่มี API key
- ห้ามส่งข้อมูล sensitive โดยไม่แจ้ง
- ต้อง log การใช้งาน AI

---

## 3. Workflow

### 3.1 เรียกใช้ feature

```text
User กด "Summarize deal" บน Deal Detail
→ ตรวจ permission + AI enabled
→ ดึง context จาก DB (deal, activities, customer)
→ redaction policy (optional)
→ เรียก LLM provider
→ แสดงผลให้ user ตรวจก่อนใช้
→ บันทึก ai_usage_logs
```

### 3.2 ไม่มี API key

```text
status=skipped_no_key
→ แสดงข้อความ fallback ใน UI
```

---

## 4. Data Flow

```text
deals / activities / customers / projects (read-only context)
                │
                ▼
         AI service ──► LLM API
                │
                ├──► UI response (draft text)
                └──► ai_usage_logs
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `ai_usage_logs` | ติดตามการใช้และต้นทุน token |

### Business rules

- AI **ไม่เขียน** ข้อมูลธุรกิจตรง ๆ โดยไม่ผ่าน user confirm
- จำกัดข้อมูลที่ส่งออกนอกระบบ
- log feature, user, token, status ทุกครั้ง
- rate limit ต่อ org
