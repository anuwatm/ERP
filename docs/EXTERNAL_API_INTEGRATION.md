# External Summary API — Integration & Acceptance

เอกสารนี้เริ่มจาก P0; ปัจจุบันมี read implementation P1–P6 แล้ว ดู [คู่มือรวม](EXTERNAL_READ_API.md) และ [OpenAPI รวม](openapi/external-read-v1.json). P7 sensitive/write ตัดออกตามคำสั่งผู้ใช้. การตรวจรับรวม/UAT ยัง pending และ deployment/integration/SLA deferred.

## Client ฝั่ง server

ตัวอย่าง Node.js: [summary-client.mjs](../examples/external-api/summary-client.mjs) ใช้ built-in fetch ไม่ต้องติดตั้ง package เพิ่ม

```js
import { createSummaryClient } from './summary-client.mjs';

const api = createSummaryClient({
    baseUrl: process.env.ERP_SUMMARY_BASE_URL,
    secret: process.env.ERP_API_SECRET,
});
const result = await api.getSummary('sales', {
    start_date: '2026-10-01', end_date: '2026-10-31',
});
// ใช้ result.data พร้อม meta.period/timezone/currency_mode ตาม contract
```

- `ERP_SUMMARY_BASE_URL` เช่น `https://erp.example.com/ERP/api/v1/summaries`; รองรับติดตั้งใต้ subdirectory
- เก็บ secret ใน server secret store/environment ห้ามใส่ source control, URL, browser bundle, mobile binary หรือ logs
- Timeout 10 วินาทีต่อ attempt; retry สูงสุด 2 ครั้ง รวม 3 attempts เฉพาะ transport failures และ HTTP 429/502/503/504; exponential backoff สูงสุด 60 วินาที
- เคารพ `Retry-After` ทั้ง seconds และ HTTP date; ถ้าต้องรอเกินเพดาน คืน error ให้ caller จัดคิวภายหลัง ไม่ retry เร็วกว่าที่ server กำหนด
- 401: หยุด polling ตรวจ expiry/revoke/rotation; 403: หยุด ตรวจ scope; 422: แก้ filter ห้าม retry แบบเดิม
- ปฏิเสธ redirect เพื่อไม่ส่ง credential ไปปลายทางใหม่; production ใช้ HTTPS เท่านั้น ส่วน HTTP loopback ต้อง opt-in `allowLocalHttp: true` สำหรับ fixture
- Client ไม่คืน upstream response body/error ดิบ; ใช้ code/status/requestId สำหรับตรวจปัญหา อย่า log credentials หรือ summary payload โดยไม่กำหนดสิทธิ์
- Rate limit 60 requests/minute/key รวมทั้งสาม endpoints จัด polling budget ร่วมกัน ไม่ retry ทุก client พร้อมกัน; scheduler จริงควรเพิ่ม jitter
- เงินยังเป็น decimal strings ห้าม parseFloat แล้วรวมยอดต่าง currency; snapshot ไม่ใช่ยอดย้อนหลังตาม end_date

ทดสอบ client โดยไม่ใช้ secret จริง: `node --test examples/external-api/summary-client.test.mjs` จาก project root

Smoke test แบบ read-only จาก server ของแอปจริง: ตั้ง `ERP_SUMMARY_BASE_URL` และ `ERP_API_SECRET` ผ่าน secret store/environment แล้วรัน `node examples/external-api/smoke.mjs` (default Sales หนึ่ง request) เลือกโดเมนด้วย `ERP_SUMMARY_DOMAINS=sales,finance,projects` เฉพาะ scopes ที่ key มี รายงานแค่ domain/ok หรือ error code/status ไม่พิมพ์ secret หรือข้อมูลธุรกิจ; loopback fixture HTTP ต้องตั้ง `ERP_ALLOW_LOCAL_HTTP=1` เพิ่ม

## Rotation / revocation / downstream data

1. สร้าง key ใหม่จาก API Access โดยเลือก scope เท่าที่จำเป็น
2. อัปเดต secret store ฝั่ง client แล้วเรียกแต่ละ endpoint ที่ใช้ ตรวจ `meta` และข้อมูลกับ ERP
3. ถอน key เก่า ตรวจ request ถัดไปตอบ 401; เก็บเฉพาะ key prefix/ID ใน incident record
4. เมื่อถูกถอนสิทธิ์ หยุด polling และระงับการแสดงข้อมูลที่เก็บไว้จนยืนยันสิทธิ์ใหม่ ไม่แสดง stale summary เสมือนเป็น live

Summary ไม่ใช่ change feed: response ใหม่แทน snapshot เดิมตาม domain/filter/currency; ห้ามตีความ array ว่างว่าเกิด delete event รายบุคคล หากต้อง sync รายรายการให้รอ contract list/detail และ tombstones

## มาตรฐาน list/detail (นำไปใช้ใน P1–P6 แล้ว)

- Field allowlist แยกแต่ละ resource/scopes; summary scope ไม่ให้สิทธิ์ list/detail/download
- ใช้ UUID ของ resource; unknown/foreign ID ตอบ 404 แบบเดียวกัน ไม่เปิดเผยการมีอยู่ขององค์กรอื่น
- Stable cursor pagination เรียง tuple `(updated_at, id)`; default 50, max 100; cursor opaque และตรวจ resource/org/filter context ทุก request ห้ามใช้ offset เพื่ออ้าง sync ที่ไม่ตกหล่น
- `updated_since` เป็น RFC3339 UTC แบบ inclusive; client upsert/dedupe ด้วย UUID และ timestamp ใช้ overlap window; จำกัด high-water mark ต่อรอบและทดสอบ concurrent updates ก่อนเปิด endpoint
- Soft delete/permission revocation ต้องมี tombstone หรือบังคับ full resync ตาม contract ของ resource ก่อนอ้าง incremental sync สมบูรณ์; ไม่คืน 0/[] เพื่อกลบ unsupported coverage
- Allowlist sort/filter/search length/date window; ไม่รับ arbitrary includes/fields, org_id หรือ dynamic table names
- Credential ของ organization integration ใช้กับ backend ที่องค์กรเชื่อถือ; Customer/Supplier ต้องมี party/resource-scoped principal แยกก่อนเปิดข้อมูล ห้ามนำ org-wide key ไปให้ลูกค้า
- Org-unit restrictions และผู้มีสิทธิ์ออก HR/financial/document credentials ต้องตกลงก่อนเปิดโดเมนนั้น ไม่ถือว่า generic admin ได้อ่านทุกโดเมนจาก design นี้
- v1 เปลี่ยนแบบ additive เท่านั้น; client ต้องยอมรับ field ใหม่; การลบ/เปลี่ยนชนิด/ความหมายต้องออก version ใหม่ พร้อม contract tests

มาตรฐานนี้มี implementation ใน P1–P6 แล้ว; ดู resource contract/ข้อจำกัด/field allowlists ใน EXTERNAL_READ_API.md และ OpenAPI รวม. ไม่ใช่การรับรอง privacy/deployment/UAT แทนผู้ตรวจ.

## Release acceptance — บันทึกแยกจาก implementation

- Automated: API/security/tenant/metric regression, OpenAPI และ client resilience tests
- Browser: create/reveal/copy/edit/revoke, password confirmation/2FA, discard/minimize, keyboard/responsive และ one-time secret หลัง reload
- Performance: ใช้ isolated schema/fixture เท่านั้น วัด service queries/EXPLAIN แยกจาก HTTP/auth/concurrent p50/p95/error rate; ไม่อ้าง service median เป็น HTTP SLA
- SLA ต้องให้ผู้รับผิดชอบตกลง target environment, data size, concurrency, request rate และ latency/error budget ก่อนตัดสิน pass/fail; การตั้งตัวเลขเองไม่ใช่ acceptance
- Target deployment: backup/recovery, ตรวจ migration เฉพาะที่เกี่ยวข้อง, config/shared cache/proxy HTTPS, build และ smoke test จาก backend ของแอปจริง ห้ามใช้ credentials จริงใน artifacts
- Gemini Round 2 และผู้ใช้ตรวจรับตามหลักฐาน; ห้ามติ๊กผ่านแทนผู้ตรวจหรือเปิด live integration โดยอาศัย automated tests อย่างเดียว

สถานะจริงดู [checklist](EXTERNAL_SUMMARY_API_CHECKLIST.md) และ [gpt.md](../gpt.md)
