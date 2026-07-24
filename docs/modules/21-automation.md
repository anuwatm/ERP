# Module: Automation

  Meta   Value  
  ---   ---  
  Module code   `automation`  
  Version   V1 เบื้องต้น, ขยาย V2  
  Priority   P1  
  Schema กลาง   [`../database/DATABASE.md`](../database/DATABASE.md) §9.4–9.6  

---

## 1. ชื่อ Module

**Automation** — ลดงานซ้ำด้วย rule, cron, webhook

---

## 2. รายละเอียด / หน้าที่

- Reminder ตาม follow-up / invoice due / task due
- Webhook endpoint + outbound event queue
- Cron jobs
- Automation log
- Notification ผ่าน email / LINE (ร่วม Notifications)

### ตัวอย่าง rule

- invoice overdue → สร้าง reminder
- task due วันนี้ → แจ้ง assignee
- deal ไม่มี activity 7 วัน → แจ้ง owner
- payment complete → อัปเดต invoice status (ส่วนนี้ส่วนใหญ่ทำใน domain; automation เสริม)

---

## 3. Workflow

### 3.1 กำหนด rule

```text
Admin สร้าง automation_rules
→ trigger_type + condition_json + action_type + action_json
→ is_active=true
```

### 3.2 รันตามตาราง (cron)

```text
Scheduler ดึง active rules
→ evaluate condition กับข้อมูลล่าสุด
→ ทำ action (notification / webhook)
→ เขียน automation_logs
```

### 3.3 Outbound webhook

```text
Event เกิดขึ้น → enqueue webhook_events
→ worker POST target_url
→ retry ถ้า failed (attempts, next_retry_at)
```

---

## 4. Data Flow

```text
Cron / Domain events
        │
        ▼
automation_rules ──evaluate──► domain tables (deals, invoices, tasks...)
        │
        ├──► notifications
        ├──► webhook_events ──► external systems
        └──► automation_logs
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

  Table   Role  
  ---   ---  
  `automation_rules`   กติกา  
  `automation_logs`   ผลรัน  
  `webhook_events`   คิว webhook  

### Business rules

- action ต้อง idempotent เท่าที่ทำได้
- เก็บ log ทุกครั้งที่รัน
- webhook ต้อง sign ด้วย webhook.secret จาก Settings
- จำกัด rate ต่อ org
