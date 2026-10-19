# External Summary API v1

Sales/Finance (Round 1) และ Projects/Tasks (Round 2) summary ระดับองค์กร สำหรับ server-to-server เท่านั้น ไม่มี write API และไม่มีข้อมูลรายบุคคล

## เปิดใช้งาน

1. Deploy source และ frontend build ตามขั้นตอนปกติ สำรองฐานข้อมูลก่อน deployment
2. รัน `php artisan migrate --force` จาก `backend/` เพื่อเพิ่มตาราง key และสิทธิ์ owner/admin เดิม (อย่ารัน demo seeder บนระบบใช้งานจริง)
3. Owner/admin เปิด Settings → API Access หรือ `/settings/api-access` เลือกสร้าง key ระบุชื่อแอป scopes และอายุ key
4. ยืนยัน password; หากองค์กรบังคับ 2FA ต้องผ่าน flow เดิมของระบบก่อน
5. คัดลอก secret ครั้งเดียว เก็บใน secret manager/environment ฝั่ง backend ของแอปผู้เรียก ห้ามฝังใน browser JavaScript/mobile binary

API base path คือ `/api/v1/summaries` ต่อท้าย base URL ของการติดตั้ง เช่น `https://erp.example.com/ERP/api/v1/summaries` สำหรับการติดตั้งใน `/ERP` ทุกตัวอย่างด้านล่างใช้ placeholder ไม่ใช่ key จริง

```bash
curl --get 'https://erp.example.com/ERP/api/v1/summaries/sales' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <SECRET_FROM_ADMIN>' \
  --data-urlencode 'start_date=2026-10-01' \
  --data-urlencode 'end_date=2026-10-31'
```

เปลี่ยนท้าย URL เป็น `/finance` สำหรับ Finance summary โดย key ต้องมี scope ของ endpoint นั้น

## Credential policy

| รายการ | พฤติกรรม |
| --- | --- |
| รูปแบบ | `erp_<public identifier>.<256-bit random secret>` |
| การจัดเก็บ | SHA-256 hash ทั้ง credential; ไม่เก็บ plaintext และกู้คืนไม่ได้ |
| อายุ | เริ่มต้น 90 วัน เลือกได้ 1–365 วัน |
| จำนวน | สูงสุด 20 active/unexpired keys ต่อ organization |
| Scope | เลือกเองอย่างน้อยหนึ่งรายการ ไม่มี wildcard/default all |
| Visibility | ทุกข้อมูลในองค์กรของ key ไม่ใช่ข้อมูลเฉพาะพนักงานผู้สร้าง |
| ถอนสิทธิ์ | มีผลตั้งแต่ request ถัดไป ไม่ยกเลิก response ที่เริ่มประมวลผลไปแล้ว |
| ผู้สร้างถูกปิดบัญชี | key เป็น credential ขององค์กร ไม่ผูกอายุ session/user; admin ต้องถอน key ที่เลิกใช้เอง |
| องค์กร inactive | key ทั้งหมดขององค์กรเรียกไม่ได้ |
| Rotation | สร้างใหม่ → อัปเดตแอป → ทดสอบ → ถอนเก่า; สิทธิ์ key เก่าไม่เปลี่ยน |
| แก้ไข | เปลี่ยนชื่อ/วันหมดอายุได้เมื่อ key ยัง active; เปลี่ยน scopes ต้องออกใหม่ |
| Key สูญหาย | ถอน key เดิมแล้วสร้างใหม่ ไม่มี endpoint เปิดเผยซ้ำ |

ข้อมูลแสดงครั้งเดียวอยู่ใน JSON create response และ React state ชั่วคราว ไม่อยู่ใน session flash, Inertia props/history หรือ localStorage หาก response สูญหาย ให้ตรวจรายการ key แล้วถอนก่อนสร้างใหม่

หน้า admin ใช้ `api_keys.view`/`api_keys.manage` ให้ owner/admin โดย default; custom role ต้องได้รับสิทธิ์โดยเจตนา การให้ `api_keys.manage` คืออนุญาตออก credential ที่อ่านข้อมูลรวมทั้งองค์กร

ค่าขีดจำกัดอยู่ใน `backend/config/external_api.php` การแก้ config ต้องผ่าน deployment/config cache ปกติ

## Endpoint และ filters

| Method/path | Scope |
| --- | --- |
| `GET /api/v1/summaries/sales` | `summary:sales:read` |
| `GET /api/v1/summaries/finance` | `summary:finance:read` |
| `GET /api/v1/summaries/projects` | `summary:projects:read` |

ส่ง `start_date` และ `end_date` คู่กันแบบ YYYY-MM-DD หรือไม่ส่งทั้งคู่ (default เดือนปัจจุบันตาม organization timezone) รวมวันต้น/ท้าย จำกัด 366 วัน อนุญาตวันที่อนาคตเพื่ออ่านเอกสารที่ลงวันที่ล่วงหน้า

ปฏิเสธ unknown filter รวม `org_id`, `branch_id`, `user_id`, pagination และ query secret ด้วย 422 เมื่อ authenticate สำเร็จ ไม่รองรับ branch filter ในรอบแรก เพราะแต่ละโดเมนยังไม่มี branch semantics ร่วมกันที่ถูกต้อง API ไม่เปิด CORS wildcard และไม่ใช้ session cookie เป็น credential; ไม่ควรเรียกจาก browser โดยตรง

## Metric dictionary

### Sales

| Field | ความหมาย/วันที่ |
| --- | --- |
| `new_customers` | จำนวนลูกค้าที่ `created_at` อยู่ในช่วง รวมทุกสถานะที่ยังไม่ soft delete |
| `deals_created_by_current_status` | cohort deals ตาม `created_at` จัดกลุ่มด้วย `stage` ปัจจุบันและ `currency`; `count` จำนวน, `amount` รวม `value_amount` |
| `quotations_issued_by_current_status` | quotations ตาม `issue_date` จัดกลุ่มด้วย `status` ปัจจุบันและ `currency`; `amount` รวม `total` รวมภาษีตามเอกสาร |

Sales แสดงกลุ่ม draft/rejected/expired/lost ด้วยชื่อสถานะชัดเจน ไม่ถือเป็นรายได้ ไม่ใช่ historical pipeline snapshot และไม่ใช่จำนวน won/lost events ตาม won_at/lost_at; จึงเปรียบเทียบกับ dashboard ได้เฉพาะ metric ที่ใช้ cohort/date เดียวกัน

### Finance

| Field | ความหมาย/วันที่ |
| --- | --- |
| `period.invoiced_revenue` | SUM `invoices.base_total` ตาม `issue_date`; เฉพาะ sent/partially_paid/paid/overdue (ยอดรวมตามเอกสาร ไม่ใช่ revenue ตามมาตรฐานบัญชี) |
| `period.cash_received` | SUM `payments.base_amount` ตาม `payment_date`; `gross` receipt, `reversals` reversal, `net` = gross − reversals |
| `period.recognized_expense` | SUM `expenses.base_amount` ตาม `expense_date`; approved/partially_paid/paid ไม่ใช่เงินสดที่จ่าย |
| `period.ap_cash_paid` | SUM `vendor_payments.base_amount` ตาม `payment_date`; gross payment − reversals ไม่ใช่ sum ของ expense สถานะ paid |
| `snapshot.outstanding_ar` | SUM `base_balance_due > 0` ของ invoices sent/partially_paid/overdue ทั้งหมด ณ เวลาที่เรียก โดยไม่กรอง issue_date ตาม period |
| `snapshot.overdue_ar` | ส่วนของ outstanding_ar ที่ due_date < วันนี้ตาม timezone องค์กร; due วันนี้/ไม่มี due_date ไม่เป็น overdue |

Finance อ้างอิง dashboard widgets ใน `Admin/DashboardController` ไม่ใช้ legacy composite ที่นิยามต่างกัน ทุกกลุ่มแยกตาม **stored `base_currency`** เพื่อไม่รวมคนละฐานสกุลเงิน เงินต่างประเทศใช้ base snapshot ที่บันทึกไว้ ไม่คำนวณด้วยอัตราวันนี้ ยอดรับ/จ่ายรวม ledger entries ขององค์กรโดยไม่ join invoice/expense ที่อาจถูกลบ ไม่มี cash balance และไม่มีการหัก credit/debit notes เพิ่มจาก dashboard semantics

Soft-deleted customers/deals/quotations/invoices/expenses ถูกตัดออกโดย Eloquent ตามระบบเดิม วันที่ timestamp ใช้ local midnight แปลงกลับเป็น app timezone; DATE ไม่แปลง timezone รูปแบบตัวเงินเป็น decimal string ทศนิยมสองตำแหน่ง ไม่มี float arithmetic ใน PHP; SQL SUM อาศัย DECIMAL ของ MySQL/MariaDB

## Response

### Projects & Tasks — Round 2

ใช้ date filters และ security policy เดียวกัน ส่ง `meta.currency_mode = not_applicable` เพราะไม่มีข้อมูลเงิน

| Field | ความหมาย |
| --- | --- |
| `period.projects_created_by_current_status` | โครงการตาม created_at ในช่วงวัน จัดกลุ่มสถานะปัจจุบันเป็น `{status,count}` |
| `period.tasks_created_by_current_status` | งานตาม created_at ในช่วงวัน จัดกลุ่มสถานะปัจจุบันเป็น `{status,count}` |
| `period.tasks_completed` | งานที่สถานะปัจจุบันเป็น done และ completed_at อยู่ในช่วงวัน ไม่ใช่จำนวนเหตุการณ์ complete/reopen ทั้งหมด |
| `snapshot.projects_by_status` / `tasks_by_status` | จำนวนตามสถานะปัจจุบันทั้งหมด ไม่กรอง created_at ตาม period |
| `snapshot.active_projects` | โครงการ planning/active/on_hold |
| `snapshot.overdue_projects` | Active projects ที่ due_date < วันนี้ตาม timezone องค์กร; due วันนี้หรือ null ไม่ overdue |
| `snapshot.overdue_tasks` | งาน todo/in_progress ที่ due_date < วันนี้; blocked/done/cancelled ไม่ overdue ตาม Dashboard |
| `snapshot.average_active_project_progress_percent` | ค่าเฉลี่ยแบบไม่ถ่วงน้ำหนักของ progress_percent ใน active projects; decimal string 1 ตำแหน่ง HALF_UP เช่น `50.5` หรือ null เมื่อไม่มี active project |
| `snapshot.as_of` | เวลาเริ่มสร้าง summary; ไม่ใช่ historical snapshot ตาม end_date |

ไม่รวม soft-deleted project/task; ทุก task ต้องมี parent project ที่ไม่ soft-delete และอยู่ org เดียวกับ key แม้ข้อมูล foreign key ในฐานข้อมูลผิดพลาด งานในโครงการ completed/cancelled ยังอยู่ในกลุ่มตามสถานะ task ของตน ไม่มีชื่อ รายละเอียด ผู้รับผิดชอบ งบประมาณ ต้นทุน หรือข้อมูลลูกค้าใน response

ยังไม่มี timesheet/time-entry schema ของ Project/Task จึงไม่มี metric ชั่วโมงทำงาน ไม่แทนด้วย attendance HR ค่า progress เป็นข้อมูลโครงการที่เก็บไว้ ไม่คำนวณใหม่จากจำนวน tasks

Key ที่สร้างก่อน Round 2 ไม่ได้สิทธิ์เพิ่มอัตโนมัติ ต้องออก key ใหม่และเลือก `summary:projects:read` ตามขั้นตอน rotation เดิม

### ตัวอย่าง Sales

```json
{
  "data": {
    "new_customers": 2,
    "deals_created_by_current_status": [
      {"status": "won", "currency": "THB", "count": 1, "amount": "1000.00"}
    ],
    "quotations_issued_by_current_status": []
  },
  "meta": {
    "api_version": "v1",
    "request_id": "<server-generated-uuid>",
    "generated_at": "2026-10-18T12:00:00+07:00",
    "timezone": "Asia/Bangkok",
    "period": {"start_date": "2026-10-01", "end_date": "2026-10-31"},
    "snapshot_as_of": "2026-10-18T12:00:00+07:00",
    "currency_mode": "original_currency",
    "visibility": "organization",
    "freshness": "live"
  }
}
```

Finance `data` มี `period` และ `snapshot` ตาม dictionary; amount groups เป็น `[{currency,count,amount}]` และ cash flow groups เป็น `[{currency,gross,reversals,net}]` ค่า `currency_mode` เป็น `stored_base_currency` องค์กรไม่มีข้อมูลได้ count 0 และ arrays ว่าง ไม่สร้างแถว THB สมมติ หากข้อมูลเก่าขาด currency จะแสดง `UNSPECIFIED`

`snapshot_as_of`/`generated_at` คือเวลาเริ่มอ่าน request ไม่ใช่ database transaction snapshot ทุก query; อาจมีรายการเปลี่ยนระหว่าง aggregate queries ไม่มี application cache และตอบ `Cache-Control: no-store, private`

## Errors / rate limit

```json
{"code":"unauthorized","message":"A valid integration key is required.","request_id":"<uuid>"}
```

| Status | Code | ความหมาย |
| --- | --- | --- |
| 401 | unauthorized | ไม่มี key, key ผิด/หมดอายุ/ถอนแล้ว หรือองค์กร inactive |
| 403 | forbidden | key ไม่มี scope ของ endpoint |
| 404 | not_found | ไม่มี summary endpoint |
| 405 | method_not_allowed | method ที่ไม่รองรับ |
| 422 | invalid_filters | filter/date window ไม่ถูกต้อง |
| 426 | https_required | request HTTP ใน production; ใช้ HTTPS และตั้ง trusted proxy ให้ถูกหาก terminate TLS ที่ reverse proxy |
| 429 | rate_limited | เกินเพดาน ให้รอตาม Retry-After |
| 500 | server_error | สร้าง summary ไม่สำเร็จ ใช้ request_id ตรวจ logs |

60 requests/minute/key รวมทั้ง Sales/Finance/Projects; invalid credentials มี limiter 30/minute/IP แยกต่างหาก Request สำเร็จมี X-Request-Id, X-RateLimit-Limit, X-RateLimit-Remaining; 429 มี Retry-After การเรียก read-only แก้ได้เฉพาะ last_used_at/rate-limit/telemetry ไม่เปลี่ยนข้อมูลธุรกิจ

Laravel รองรับ HEAD และ automatic OPTIONS ตาม routing framework; OPTIONS ไม่คืนข้อมูลธุรกิจและไม่มี Access-Control-Allow-Origin สำหรับ summary

เครื่องหลาย instance ต้องใช้ shared cache สำหรับ rate limiter และปรับ log retention ตาม policy ของระบบ ไม่มีการบันทึก Authorization header/secret/query ใน operational API logs

## ขอบเขตตรวจรับ

[Integration guide / server-side client / release acceptance](EXTERNAL_API_INTEGRATION.md) รวม timeout/retry, rotation, downstream revocation และมาตรฐาน list/detail สำหรับรอบถัดไป

Contract machine-readable: `docs/openapi/external-summary-v1.json` — ยังไม่ขยายถึง admin session endpoints
Tests: `backend/tests/Feature/ExternalSummaryApiTest.php`; ผลคำสั่งจริงและข้อจำกัดตรวจใน `gpt.md`
การเพิ่ม module/detail/export/write ต้องเพิ่ม contract, scope, checklist และ tests แยกก่อนเปิดให้แอปเดิมใช้งาน
