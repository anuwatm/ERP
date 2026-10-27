# API_Document — ERP External Read-only API

**POS แยกแอป:** [Phase28 API/routes](PHASE_28_API.md) เป็น staff/admin/connector interfaces คนละขอบเขตกับ External Read API 62 GET paths ในเอกสารนี้. [Configured database upgrade](DATABASE_UPGRADE_20261027.md) appliedแล้ว; ไม่เท่ากับ production deployment.

เอกสารอ้างอิงการดึงข้อมูลสำหรับ backend ของแอปภายนอก ครอบคลุม **62 GET endpoints** ภายใต้ `/api/v1` อ้างอิง routes/controllers และ OpenAPI version1.2.0 ณ2026-10-19 ตัวอย่างทั้งหมดเป็นข้อมูลสมมติ ไม่ใช่ผลเรียกฐานใช้งานจริง

ไม่รวม Admin session endpoints, Payment/e-Tax callbacks หรือ POS integration ที่ยังเป็นแผน External API ชุดนี้ไม่มี create/update/delete/approve/reject และไม่เปิด national_id, payroll, bank account, private contact, GPS หรือเหตุผลลา

## 1. เริ่มใช้งาน

1. Admin ไป **Administration & Settings → API Access** (`/settings/api-access`) สร้าง key พร้อม scopes/policy ที่แอปจำเป็นต้องใช้ Secret แสดงเต็มครั้งเดียว
2. เก็บ secret ใน backend/secret store ห้ามใส่ browser/mobile binary, query string, source control หรือ logs
3. ใช้ HTTPS, `Accept: application/json` และ `Authorization: Bearer <INTEGRATION_SECRET>` ทุกครั้ง ไม่ใช้ staff session แทน key
4. Base URL ตัวอย่างคือ `https://erp.example.com/api/v1` หากติดตั้งใต้ `/ERP` ใช้ `https://erp.example.com/ERP/api/v1` ตัวอย่างราย endpoint แสดง URL แบบไม่มี subdirectory ให้ปรับตามจริง
5. ไม่มี request body และส่งเฉพาะ parameters ที่ระบุใน endpoint นั้น ห้าม inject `org_id`, `user_id`, arbitrary include/fields/sort

```sh
curl --get 'https://erp.example.com/api/v1/employees' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>' \
  --data-urlencode 'limit=50'
```

ค่าเริ่มต้น60requests/minute/key และ30invalid requests/minute/IP ปรับได้ใน server config. ทุก response ใช้ `Cache-Control: no-store, private`. สำเร็จอาจมี `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-Request-Id`; 429 มี `Retry-After`. HEAD ใช้ route เดียวกับ GET แต่ไม่มี response body และอาจยังทำงาน query/telemetry จึงไม่ใช้แทน lightweight health check

## 2. รูปแบบข้อมูล สิทธิ์ และ Metadata

- `data` เป็น array สำหรับ list, object สำหรับ detail/summary; list ว่างคืน `[]` ไม่ใช่404. Detail ที่ไม่มีหรือมองไม่เห็นคืน404เพื่อไม่เปิดว่ามีข้อมูลข้ามองค์กร
- UUID ใช้เป็น identifier ส่วน `_no`, `_code`, SKU เป็นรหัสแสดงผล ชื่อสาขา/แผนก/ตำแหน่งอ่านจาก Reference APIs ที่ key มีสิทธิ์ ไม่ใช่ API employees ส่งชื่อ master ทั้งหมดมา
- เงิน/จำนวนวัน/อัตราภาษีส่วนใหญ่เป็น decimal string2ตำแหน่ง ปริมาณเป็น string4ตำแหน่ง และนาทีเป็น integer ห้ามใช้ binary floating-point รวมเงิน. Quantity ต่างหน่วยและเงินต่าง currency ห้ามรวมเข้าด้วยกัน
- วันที่เป็น YYYY-MM-DD ส่วน timestamps เป็น ISO8601 พร้อม timezone หรือ Z ไม่ควรถือว่าทุก timestamp เป็นเวลาไทย. เวลากะ starts_at/ends_at เป็น HH:mm:ss. Null หมายถึงไม่มีค่า/ไม่ครอบคลุม/ถูกซ่อนตามบริบท ไม่เท่ากับ0
- Scope summary ไม่ให้ list/detail. products price/cost และ manufacturing formula/cost เป็น opt-in. Keyเดิมไม่ได้ scope ใหม่อัตโนมัติ
- Party key จำกัด customer/supplier และเอกสารเผยแพร่ของตน HR ต้อง explicit branch/all-branches policy; Document ต้องเลือก document_ids และผ่าน current issuer/classification/link ACL. หลาย resource ตรวจ current issuer permissions ด้วย ไม่ใช่ org scope แล้วเห็นทุกอย่าง
- `meta.visibility=organization` เป็น label ทั่วไปของ paginator ไม่ยกเลิก party/HR/issuer restrictions. อาจเห็นข้อมูลน้อยกว่าทั้งองค์กรแม้ label นี้แสดง organization
- Detail บางแบบไม่มี generated_at หรือ visibility ตาราง Metadata เป็น field dictionary **ไม่ใช่สัญญาว่าทุก endpoint ส่งครบ** ตัวอย่างของแต่ละ endpoint แสดงรูปแบบที่ใช้จริง

### Metadata field dictionary

| Field | ชนิด / ความหมาย |
| --- | --- |
| `meta.api_version` | เวอร์ชัน API ปัจจุบัน v1 |
| `meta.request_id` | UUID ของ request ใช้ติดตามปัญหา ตรงกับ X-Request-Id เมื่อมี header |
| `meta.generated_at` | เวลาสร้าง response ไม่ใช่ checkpoint ที่ใช้แทน sync_watermark |
| `meta.timezone` | timezone ขององค์กรที่ใช้ตัดวันรายงาน |
| `meta.period` | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `meta.snapshot_as_of` | เวลาภาพปัจจุบันที่รายงาน snapshot |
| `meta.currency_mode` | original_currency / stored_base_currency / not_applicable ตามชนิดรายงาน |
| `meta.visibility` | บริบทการมองเห็น; organization หรือ credential_hr_policy ไม่ใช่หลักฐานว่าทุก row ใน org ถูกเปิด |
| `meta.freshness` | live: อ่าน source ปัจจุบัน ไม่ใช่ historical snapshot |
| `meta.as_of` | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `meta.next_cursor` | opaque cursor หน้าถัดไป; null เมื่อจบชุด ห้ามแก้/ถอดรหัสเอง |
| `meta.limit` | จำนวนสูงสุดต่อหน้า ค่าเริ่มต้น50 สูงสุด100 |
| `meta.consistency` | live: ไม่มี snapshot isolation ระหว่างหน้า |
| `meta.reconciliation_required` | true: ผู้รับต้อง full reconcile ข้อมูลลบ/สิทธิ์/derived fields ตามนโยบาย |
| `meta.delta_supported` | true เมื่อรับ updated_since; false เมื่อ endpoint ต้อง period read/full reconciliation |
| `meta.sync_watermark` | checkpoint UTC แบบ Z ที่คงเดิมทั้ง cursor chain ใช้เริ่ม delta รอบถัดไปเมื่อจบครบ |
| `meta.cursor_expires_at` | วันเวลาหมดอายุ cursor chain ประมาณ30นาทีจากหน้าแรก |
| `meta.quantity_mode` | on_hand: inventory balances ส่งยอดคงเหลือจาก movements เท่านั้น |
| `meta.quantity_unit_basis` | current_product_master: manufacturing summary ใช้ Product.unit ปัจจุบัน |

### สถานะของ resources ที่ใช้บ่อย

Status เป็นค่าปัจจุบันของ resource นั้น ไม่ใช้ invoice paid แทน delivered หรือ approval approved แทน payment settled

| Resource | ค่าที่ระบบใช้ |
| --- | --- |
| Deals ใน Sales summary | new, contacted, qualified, proposal, negotiation, won, lost |
| Quotations | draft, sent, approved, rejected, expired, converted |
| Invoices | draft, sent, partially_paid, paid, overdue, void |
| Purchase Orders | draft, sent, approved, partially_received, received, cancelled, closed |
| Projects | planning, active, on_hold, completed, cancelled |
| Tasks | todo, in_progress, blocked, done, cancelled; priority=low/normal/high/urgent |
| Manufacturing Work Orders | draft, planned, in_progress, quality_check, completed, cancelled |
| Approval steps | queued, pending, approved, rejected, revision_requested; subject.status แยกจาก step.status |
| Employee employment_status | probation, active, suspended, resigned, terminated |

Business list status filter ส่วนใหญ่เป็น string ไม่ใช่ enum validation หากส่งค่าไม่ตรงอาจได้200/[]; approvals, employee และ manufacturing filters ที่กำหนด enum จะตอบ422 เมื่อผิดค่า ดูparameter tableแต่ละendpoint

### Cursor / Delta / Reconciliation

List ส่วนใหญ่ default50/max100 เรียง `(updated_at,id)`, encrypted cursor30นาทีผูก key/org/scopes/policy/path/filters/limit. อ่านทุกหน้าด้วย filters เดิมจน next_cursor=null แล้วบันทึก **sync_watermark เดิมของรอบ** เริ่ม delta รอบใหม่ด้วย updated_since แบบ inclusive จึงต้อง upsert/dedupe UUID

ห้ามใช้ generated_at หน้าสุดท้ายแทน watermark. ข้อมูลที่เปลี่ยนหลัง watermark อาจหายจากรอบปัจจุบันแล้วกลับมา delta รอบหน้า Live pagination ไม่ใช่ snapshot isolation. Cursorหมดอายุ/เปลี่ยน policy ให้เริ่ม full reconciliationใหม่

Inventory balances, approvals, PO items และ employee operational lists **ไม่รับ updated_since**; ใช้ period read/full reconciliation. Resource อื่นที่รองรับ delta ก็ยังต้อง reconcile hard delete, parent/ACL changes และ derived fields ที่ไม่เปลี่ยน record.updated_at เช่นรับของเพิ่มแต่ PO header ไม่แก้

Soft-delete tombstone มีเฉพาะ resource ที่ controller รองรับ withTrashed ใน delta เช่น Products, reference models บางชนิด, business resources บางชนิด และ manufacturing header ไม่ใช่ทุก resource โดยเฉพาะ Employee/Document ไม่ใช่ complete delete feed:

```json
{"id":"019a0010-0000-7000-8000-000000000001","deleted_at":"2026-10-19T05:00:00+00:00","updated_at":"2026-10-19T05:00:00+00:00"}
```

Tombstone มีเฉพาะ id/deleted_at/updated_at ไม่มีรายละเอียดเดิม. Full reconciliation ต้องลบ/ระงับสำเนาที่ credential ปัจจุบันอ่านไม่ได้ และหยุด pollingเมื่อ401/403จนแก้สิทธิ์สำเร็จ

## 3. Error Reference

Error body มาตรฐานเป็น JSON object ชั้นบน **ไม่มี data/meta และไม่มี field errors รายฟิลด์**:

```json
{
  "code": "invalid_filters",
  "message": "Use valid start_date and end_date within the allowed date window. No other filters are supported.",
  "request_id": "019a0010-0000-7000-8000-000000000001"
}
```

| Field | ความหมาย |
| --- | --- |
| `code` | รหัสปัญหาคงรูปแบบสำหรับให้โปรแกรมแยกกรณี |
| `message` | ข้อความ sanitized ภาษาอังกฤษ อาจเป็นข้อความร่วมหลายเหตุ ห้ามใช้แยก logic แทน code/status |
| `request_id` | UUID ติดตามปัญหา ส่งให้ผู้ดูแลพร้อม endpoint/status โดยไม่ส่ง secret |

| HTTP | code | ความหมาย / สาเหตุ | วิธีจัดการ |
| --- | --- | --- | --- |
| 401 | `unauthorized` | ไม่ส่ง Bearer/รูปแบบผิด/secretผิด/keyหมดอายุหรือถูกrevoke/องค์กรไม่active | หยุด polling ให้adminตรวจkey ไม่retryด้วยsecretเดิมวน |
| 403 | `forbidden` | keyไม่มีscope, principalใช้resourceไม่ได้, additionalscopeขาด หรือissuerไม่มีcurrent permission | ตรวจ scopes/policy/issuer สร้างkeyใหม่เมื่อเปลี่ยนscope ไม่ส่งuser_idเพื่อสวมสิทธิ์ |
| 404 | `not_found` | ไม่มีroute/UUIDหรือไม่ผ่านorg/row/parent/ACL; download version/scan/fileไม่พร้อมก็ใช้404 | ตรวจpath/UUID/สิทธิ์ ไม่ใช้404ยืนยันว่าข้อมูลต่างองค์กรไม่มีจริง |
| 405 | `method_not_allowed` | ใช้POST/PUT/PATCH/DELETEกับread endpoint | เปลี่ยนเป็นGET; HEADไม่มีbody ไม่ใช่write |
| 409 | `unsupported_employee_coverage` | เฉพาะ employee leave requests/balances: ไม่มีlinkedUser | ถือว่าไม่ครอบคลุม ไม่แทนเป็น[]/สิทธิ์ลา0 ต้องจัดการuser bridgeภายใน |
| 409 | `conflict` | ตัวจัดการกลางรองรับresource state conflict; ปัจจุบันreadcontrollersไม่มีbusiness conflictอื่นที่ประกาศใช้เฉพาะ | ตรวจrequest/stateกับผู้ดูแล ไม่สับสนกับunsupported_employee_coverage |
| 422 | `invalid_filters` | unknownquery/body, วันที่ไม่ครบคู่/ผิดรูป/กลับช่วง/เกิน366วัน, limitผิด, UUIDfilterต่างorg, enum/searchผิด, cursorผิด/หมดอายุ/ผิดบริบท, futurecheckpoint หรือส่งdeltaให้resourceที่ไม่รองรับ | แก้ตามparameter table; cursorหมดอายุเริ่มreconcile ไม่retryqueryเดิม |
| 426 | `https_required` | Productionใช้HTTPแทนHTTPS | ใช้HTTPSตรงปลายทาง ไม่ส่งsecretผ่านHTTPหรือพึ่งredirect |
| 429 | `rate_limited` | เกินเพดานkeyหรือinvalidIPbucket | รอRetry-Afterอย่างน้อยตามserverแจ้ง แล้วretryแบบbounded/backoff |
| 500 | `server_error` | exceptionภายใน/ฐานข้อมูล/บริการ source ไม่พร้อม | เก็บrequest_id retryแบบbounded สำหรับงานอ่าน; แจ้งผู้ดูแลเมื่อซ้ำ ห้ามlogsecret/responsePII |

**ข้อความจริงที่อาจพบ:** `unauthorized`: "A valid integration key is required."; `forbidden`: "The integration key does not allow this summary." หรือ "The integration credential does not allow this resource."; `not_found`: "The summary endpoint was not found."; `method_not_allowed`: "This API only supports GET requests."; `https_required`: "HTTPS is required for this API."; `rate_limited`: "Too many requests. Please retry later."; `server_error`: "Unable to generate the summary." คำว่า summary เป็นข้อความร่วมเดิม **ไม่ได้หมายความว่า endpointอื่นเป็นsummaryหรือว่า422รับได้แค่date filters** ให้ยึดparameter tableของ endpoint

409 ของ non-User leave ใช้ message: "Leave records require a linked ERP account. No zero balance is implied." HTTPS/auth/rate guards อาจตอบก่อนquery validation จึงแก้credentialก่อนพยายามวิเคราะห์filters

Reverse proxy/web server อาจตอบ502/503/504, HTMLหรือTLS/networktimeout **นอก error envelope ของแอปนี้** Clientต้องตรวจstatus/content-typeก่อนparse และไม่ตีความresponsebodyที่ไม่ใช่JSONเป็นdata

## 4. สารบัญ Endpoints

ทุก path ด้านล่างต่อจาก origin ของ deployment หากมี `/ERP` ให้เติมก่อน `/api/v1`

| ลำดับ | Method / Path | ใช้ดึงข้อมูล |
| --- | --- | --- |
| [01](#api-01) | `GET /api/v1/summaries/sales` | ดึงข้อมูลสรุปการขาย |
| [02](#api-02) | `GET /api/v1/summaries/finance` | ดึงข้อมูลสรุปการเงิน |
| [03](#api-03) | `GET /api/v1/summaries/projects` | ดึงข้อมูลสรุปโครงการและงาน |
| [04](#api-04) | `GET /api/v1/products` | ดึงรายชื่อ/รายการสินค้า/บริการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [05](#api-05) | `GET /api/v1/products/{id}` | ดึงรายละเอียดสินค้า/บริการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [06](#api-06) | `GET /api/v1/reference/branches` | ดึงข้อมูลอ้างอิงสาขาสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [07](#api-07) | `GET /api/v1/reference/divisions` | ดึงข้อมูลอ้างอิงฝ่ายสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [08](#api-08) | `GET /api/v1/reference/departments` | ดึงข้อมูลอ้างอิงแผนกสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [09](#api-09) | `GET /api/v1/reference/positions` | ดึงข้อมูลอ้างอิงตำแหน่งงานสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [10](#api-10) | `GET /api/v1/reference/warehouses` | ดึงข้อมูลอ้างอิงคลังสินค้าสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [11](#api-11) | `GET /api/v1/reference/currencies` | ดึงข้อมูลอ้างอิงสกุลเงินสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน |
| [12](#api-12) | `GET /api/v1/inventory/balances` | ดึงยอดคงเหลือสินค้าตามคลัง ตำแหน่งจัดเก็บ และล็อต |
| [13](#api-13) | `GET /api/v1/summaries/inventory` | ดึงข้อมูลสรุปสินค้าคงคลัง |
| [14](#api-14) | `GET /api/v1/employees` | ดึงรายชื่อ/รายการพนักงานที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [15](#api-15) | `GET /api/v1/employees/{id}` | ดึงรายละเอียดพนักงานหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [16](#api-16) | `GET /api/v1/employees/{id}/attendance` | ดึงรายการลงเวลาของพนักงานตาม work_date ในช่วง |
| [17](#api-17) | `GET /api/v1/employees/{id}/roster` | ดึงตารางกะพนักงานตาม schedule_date ในช่วง |
| [18](#api-18) | `GET /api/v1/employees/{id}/leave-requests` | ดึงคำขอลาพนักงานที่ช่วงวันลาทับช่วง query |
| [19](#api-19) | `GET /api/v1/employees/{id}/leave-balances` | ดึงยอดสิทธิ์วันลาของพนักงานในปีปัจจุบัน ณ ตอนอ่าน |
| [20](#api-20) | `GET /api/v1/summaries/hr` | ดึงข้อมูลสรุปบุคลากร |
| [21](#api-21) | `GET /api/v1/summaries/attendance` | ดึงข้อมูลสรุปเวลาทำงาน |
| [22](#api-22) | `GET /api/v1/summaries/leaves` | ดึงข้อมูลสรุปการลา |
| [23](#api-23) | `GET /api/v1/summaries/roster` | ดึงข้อมูลสรุปการจัดกะ |
| [24](#api-24) | `GET /api/v1/customers` | ดึงรายชื่อ/รายการลูกค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [25](#api-25) | `GET /api/v1/customers/{id}` | ดึงรายละเอียดลูกค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [26](#api-26) | `GET /api/v1/suppliers` | ดึงรายชื่อ/รายการคู่ค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [27](#api-27) | `GET /api/v1/suppliers/{id}` | ดึงรายละเอียดคู่ค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [28](#api-28) | `GET /api/v1/quotations` | ดึงรายชื่อ/รายการใบเสนอราคาที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [29](#api-29) | `GET /api/v1/quotations/{id}` | ดึงรายละเอียดใบเสนอราคาหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [30](#api-30) | `GET /api/v1/quotations/{id}/items` | ดึงรายการย่อยของใบเสนอราคาที่ระบุ UUID แบบแบ่งหน้า |
| [31](#api-31) | `GET /api/v1/invoices` | ดึงรายชื่อ/รายการใบแจ้งหนี้ที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [32](#api-32) | `GET /api/v1/invoices/{id}` | ดึงรายละเอียดใบแจ้งหนี้หนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [33](#api-33) | `GET /api/v1/invoices/{id}/items` | ดึงรายการย่อยของใบแจ้งหนี้ที่ระบุ UUID แบบแบ่งหน้า |
| [34](#api-34) | `GET /api/v1/delivery-orders` | ดึงรายชื่อ/รายการใบส่งของที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [35](#api-35) | `GET /api/v1/delivery-orders/{id}` | ดึงรายละเอียดใบส่งของหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [36](#api-36) | `GET /api/v1/delivery-orders/{id}/items` | ดึงรายการย่อยของใบส่งของที่ระบุ UUID แบบแบ่งหน้า |
| [37](#api-37) | `GET /api/v1/payments` | ดึงรายชื่อ/รายการรายการรับชำระ/กลับรายการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [38](#api-38) | `GET /api/v1/payments/{id}` | ดึงรายละเอียดรายการรับชำระ/กลับรายการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [39](#api-39) | `GET /api/v1/purchase-orders` | ดึงรายชื่อ/รายการใบสั่งซื้อที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [40](#api-40) | `GET /api/v1/purchase-orders/{id}` | ดึงรายละเอียดใบสั่งซื้อหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [41](#api-41) | `GET /api/v1/purchase-orders/{id}/items` | ดึงรายการย่อยของใบสั่งซื้อที่ระบุ UUID แบบแบ่งหน้า |
| [42](#api-42) | `GET /api/v1/goods-receipts` | ดึงรายชื่อ/รายการใบรับสินค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [43](#api-43) | `GET /api/v1/goods-receipts/{id}` | ดึงรายละเอียดใบรับสินค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [44](#api-44) | `GET /api/v1/goods-receipts/{id}/items` | ดึงรายการย่อยของใบรับสินค้าที่ระบุ UUID แบบแบ่งหน้า |
| [45](#api-45) | `GET /api/v1/projects` | ดึงรายชื่อ/รายการโครงการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [46](#api-46) | `GET /api/v1/projects/{id}` | ดึงรายละเอียดโครงการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [47](#api-47) | `GET /api/v1/tasks` | ดึงรายชื่อ/รายการงานที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [48](#api-48) | `GET /api/v1/tasks/{id}` | ดึงรายละเอียดงานหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [49](#api-49) | `GET /api/v1/projects/{id}/tasks` | ดึงรายการงานที่อยู่ในโครงการ UUID ที่ระบุ โดยตรวจสิทธิ์ทั้งโครงการและงาน |
| [50](#api-50) | `GET /api/v1/summaries/procurement` | ดึงข้อมูลสรุปจัดซื้อ |
| [51](#api-51) | `GET /api/v1/approvals` | ดึงรายชื่อ/รายการขั้นอนุมัติที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [52](#api-52) | `GET /api/v1/approvals/{id}` | ดึงรายละเอียดขั้นอนุมัติหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [53](#api-53) | `GET /api/v1/documents` | ดึงรายชื่อ/รายการเอกสาร DMSที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [54](#api-54) | `GET /api/v1/documents/{id}` | ดึงรายละเอียดเอกสาร DMSหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [55](#api-55) | `GET /api/v1/documents/{id}/download` | ดาวน์โหลดไฟล์เวอร์ชันปัจจุบันของเอกสารที่ได้รับอนุญาต แบบ binary private stream |
| [56](#api-56) | `GET /api/v1/manufacturing/boms` | ดึงรายชื่อ/รายการสูตรการผลิตที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [57](#api-57) | `GET /api/v1/manufacturing/boms/{id}` | ดึงรายละเอียดสูตรการผลิตหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [58](#api-58) | `GET /api/v1/manufacturing/boms/{id}/items` | ดึงรายการย่อยของสูตรการผลิตที่ระบุ UUID แบบแบ่งหน้า |
| [59](#api-59) | `GET /api/v1/manufacturing/work-orders` | ดึงรายชื่อ/รายการใบสั่งผลิตที่ key มีสิทธิ์เห็น แบบแบ่งหน้า |
| [60](#api-60) | `GET /api/v1/manufacturing/work-orders/{id}` | ดึงรายละเอียดใบสั่งผลิตหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key |
| [61](#api-61) | `GET /api/v1/manufacturing/work-orders/{id}/items` | ดึงรายการย่อยของใบสั่งผลิตที่ระบุ UUID แบบแบ่งหน้า |
| [62](#api-62) | `GET /api/v1/summaries/manufacturing` | ดึงข้อมูลสรุปการผลิต |

## 5. รายละเอียดแต่ละ Endpoint

ตาราง fields ใช้ `[]` แทนสมาชิกแต่ละรายการและ dot แทน object ซ้อน ไม่ใช่ชื่อ key ที่มีจุดจริง ตัวอย่าง list แสดง1record และ Summary ใช้ยอดสมมติ ไม่ใช่ผลจากฐานจริง ใช้ตาราง fields อธิบายสมาชิกของarrayแม้ตัวอย่างเป็น[] ตัวอย่างที่มี price/cost fields สมมติว่า keyมีadditionalscopesครบ; ถ้าไม่มี fieldsเหล่านั้นจะหายไป ไม่ใช่คืน0

<a id="api-01"></a>

### 01. GET /api/v1/summaries/sales

**ใช้ทำอะไร:** ดึงข้อมูลสรุปการขาย

**Scopes:** `summary:sales:read`

**เงื่อนไขข้อมูล:** นับลูกค้าจาก created_at, Deals จาก created_at/current stage, Quotations จาก issue_date/current status; จำนวนเงินแยก original currency ไม่ใช่รายได้ใน GL

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/sales?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "new_customers": 1,
    "deals_created_by_current_status": [
      {
        "currency": "THB",
        "count": 1,
        "amount": "1000.00",
        "status": "new"
      }
    ],
    "quotations_issued_by_current_status": [
      {
        "currency": "THB",
        "count": 1,
        "amount": "1000.00",
        "status": "sent"
      }
    ]
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "original_currency",
    "visibility": "organization",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.new_customers` | integer | ปกติ | ลูกค้าที่ created_at อยู่ในช่วงวัน โดยไม่นับรายการ soft-deleted |
| `data.deals_created_by_current_status` | array | ปกติ | Deals ที่สร้างในช่วง แบ่ง current stage และ original currency รวม value_amount ไม่ใช่ยอดขายรับรู้ |
| `data.deals_created_by_current_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.deals_created_by_current_status[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.deals_created_by_current_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.deals_created_by_current_status[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.quotations_issued_by_current_status` | array | ปกติ | ใบเสนอราคาตาม issue_date ในช่วง แบ่งสถานะปัจจุบัน/สกุลเงิน รวม total |
| `data.quotations_issued_by_current_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.quotations_issued_by_current_status[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.quotations_issued_by_current_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.quotations_issued_by_current_status[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-02"></a>

### 02. GET /api/v1/summaries/finance

**ใช้ทำอะไร:** ดึงข้อมูลสรุปการเงิน

**Scopes:** `summary:finance:read`

**เงื่อนไขข้อมูล:** ช่วงวันใช้ issue_date/expense_date/payment_date ตาม metric; ยอดเงินแยก stored base currency. AR snapshot ปัจจุบันไม่เปลี่ยนตาม end_date และไม่มี organization cash balance

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/finance?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "invoiced_revenue": [
        {
          "currency": "THB",
          "count": 1,
          "amount": "1000.00"
        }
      ],
      "cash_received": [
        {
          "currency": "THB",
          "gross": "500.00",
          "reversals": "0.00",
          "net": "500.00"
        }
      ],
      "recognized_expense": [
        {
          "currency": "THB",
          "count": 1,
          "amount": "100.00"
        }
      ],
      "ap_cash_paid": [
        {
          "currency": "THB",
          "gross": "50.00",
          "reversals": "0.00",
          "net": "50.00"
        }
      ]
    },
    "snapshot": {
      "as_of": "2026-10-19T05:00:00+00:00",
      "outstanding_ar": [
        {
          "currency": "THB",
          "count": 1,
          "amount": "500.00"
        }
      ],
      "overdue_ar": []
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "stored_base_currency",
    "visibility": "organization",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.invoiced_revenue` | array | ปกติ | ยอด base_total ของ invoices issue_date ในช่วงและสถานะ sent/partially_paid/paid/overdue ไม่ใช่ GL revenue recognition |
| `data.period.invoiced_revenue[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.period.invoiced_revenue[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.invoiced_revenue[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.period.cash_received` | array | ปกติ | ยอดรับชำระในช่วง แยก stored base_currency และ gross/reversals/net |
| `data.period.cash_received[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.period.cash_received[].gross` | decimal string (2 ตำแหน่ง) | ปกติ | ยอด receipt/payment ปกติในช่วงก่อนหัก reversals |
| `data.period.cash_received[].reversals` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดกลับรายการที่ payment_date อยู่ในช่วง |
| `data.period.cash_received[].net` | decimal string (2 ตำแหน่ง) | ปกติ | gross - reversals; อาจติดลบ ไม่ใช่เงินสดคงเหลือองค์กร |
| `data.period.recognized_expense` | array | ปกติ | ยอด base_amount ของ expenses expense_date ในช่วงและสถานะ approved/partially_paid/paid ไม่ใช่ยอดจ่ายเงินสดทั้งหมด |
| `data.period.recognized_expense[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.period.recognized_expense[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.recognized_expense[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.period.ap_cash_paid` | array | ปกติ | Vendor Payments ตาม payment_date ในช่วง แยก stored base_currency และ reversals |
| `data.period.ap_cash_paid[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.period.ap_cash_paid[].gross` | decimal string (2 ตำแหน่ง) | ปกติ | ยอด receipt/payment ปกติในช่วงก่อนหัก reversals |
| `data.period.ap_cash_paid[].reversals` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดกลับรายการที่ payment_date อยู่ในช่วง |
| `data.period.ap_cash_paid[].net` | decimal string (2 ตำแหน่ง) | ปกติ | gross - reversals; อาจติดลบ ไม่ใช่เงินสดคงเหลือองค์กร |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.as_of` | string (date-time) | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `data.snapshot.outstanding_ar` | array | ปกติ | ลูกหนี้คงค้างปัจจุบัน: base_balance_due>0 ของ invoices sent/partially_paid/overdue |
| `data.snapshot.outstanding_ar[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.snapshot.outstanding_ar[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.outstanding_ar[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.snapshot.overdue_ar` | array | ปกติ | ส่วนลูกหนี้ค้างปัจจุบันที่ due_date ก่อนวันนี้ตาม timezone องค์กร |
| `data.snapshot.overdue_ar[].currency` | string | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.snapshot.overdue_ar[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.overdue_ar[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-03"></a>

### 03. GET /api/v1/summaries/projects

**ใช้ทำอะไร:** ดึงข้อมูลสรุปโครงการและงาน

**Scopes:** `summary:projects:read`

**เงื่อนไขข้อมูล:** Period ใช้ created_at และ done.completed_at. Snapshot active=planning/active/on_hold, overdue tasks=todo/in_progress ไม่รวม blocked; ต้องมี live parent project ใน org เดียวกัน ไม่มี names/people/work hours

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/projects?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "projects_created_by_current_status": [
        {
          "status": "active",
          "count": 1
        }
      ],
      "tasks_created_by_current_status": [
        {
          "status": "in_progress",
          "count": 1
        }
      ],
      "tasks_completed": 0
    },
    "snapshot": {
      "as_of": "2026-10-19T05:00:00+00:00",
      "projects_by_status": [
        {
          "status": "active",
          "count": 1
        }
      ],
      "tasks_by_status": [
        {
          "status": "in_progress",
          "count": 1
        }
      ],
      "active_projects": 1,
      "overdue_projects": 0,
      "overdue_tasks": 0,
      "average_active_project_progress_percent": "50.0"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "organization",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.projects_created_by_current_status` | array | ปกติ | จำนวนโครงการสร้างในช่วง แบ่งสถานะปัจจุบัน |
| `data.period.projects_created_by_current_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.period.projects_created_by_current_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.tasks_created_by_current_status` | array | ปกติ | จำนวนงานสร้างในช่วงที่มี parent project ใช้งานได้ใน org เดียวกัน แบ่งสถานะปัจจุบัน |
| `data.period.tasks_created_by_current_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.period.tasks_created_by_current_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.tasks_completed` | integer | ปกติ | งานสถานะ done ปัจจุบันและ completed_at อยู่ในช่วง |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.as_of` | string (date-time) | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `data.snapshot.projects_by_status` | array | ปกติ | จำนวนโครงการปัจจุบันแบ่งสถานะ |
| `data.snapshot.projects_by_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.snapshot.projects_by_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.tasks_by_status` | array | ปกติ | จำนวนงานปัจจุบันแบ่งสถานะ เฉพาะ parent project ที่ใช้ได้ |
| `data.snapshot.tasks_by_status[].status` | string | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.snapshot.tasks_by_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.active_projects` | integer | ปกติ | จำนวนโครงการ planning/active/on_hold ปัจจุบัน |
| `data.snapshot.overdue_projects` | integer | ปกติ | active projects ที่ due_date ก่อนวันนี้ |
| `data.snapshot.overdue_tasks` | integer | ปกติ | งาน todo/in_progress ที่ due_date ก่อนวันนี้ ไม่รวม blocked |
| `data.snapshot.average_active_project_progress_percent` | decimal string (1 ตำแหน่ง) / null | ปกติ | ค่าเฉลี่ย progress_percent แบบไม่ถ่วงน้ำหนักของ active projects decimal1 HALF_UP; null หากไม่มี |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-04"></a>

### 04. GET /api/v1/products

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการสินค้า/บริการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `products:read`

**เงื่อนไขข้อมูล:** price/cost ปรากฏเฉพาะ additional scopes ตามลำดับ products:prices:read / products:costs:read; currency ของ Product ไม่มีใน response จึงไม่สมมติว่าเป็น THB ทุกกรณี

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `search` | query | ไม่ | string maxLength=100 | ข้อความค้นหายาวไม่เกิน100อักขระ SKU/ชื่อสินค้า |
| `type` | query | ไม่ | enum product, service, package enum=product,service,package | ชนิดสินค้า/บริการ เช่น product, service, package; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `is_active` | query | ไม่ | enum 0, 1 enum=0,1 | สถานะเปิดใช้งาน ส่ง0หรือ1 |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/products?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "sku": "SKU-001",
      "name": "รายการตัวอย่าง",
      "type": "product",
      "category": "สินค้า",
      "unit": "pcs",
      "is_active": true,
      "track_inventory": true,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "price": "100.00",
      "cost": "0.00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].sku` | string / null | ปกติ | รหัส SKU ของสินค้าหรือบริการ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].type` | string / null | ปกติ | ชนิดสินค้า/บริการ เช่น product, service, package |
| `data[].category` | string / null | ปกติ | หมวดหมู่สินค้าตามค่าที่จัดเก็บ |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].is_active` | boolean | ปกติ | true เมื่อรายการเปิดใช้งาน |
| `data[].track_inventory` | boolean | ปกติ | true เมื่อสินค้าถูกกำหนดให้ติดตามสต็อก |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].price` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ราคาขายที่จัดเก็บ คืนเมื่อมี products:prices:read เพิ่ม |
| `data[].cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนที่จัดเก็บ คืนเมื่อมี products:costs:read เพิ่ม |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-05"></a>

### 05. GET /api/v1/products/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดสินค้า/บริการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `products:read`

**เงื่อนไขข้อมูล:** price/cost ปรากฏเฉพาะ additional scopes ตามลำดับ products:prices:read / products:costs:read; currency ของ Product ไม่มีใน response จึงไม่สมมติว่าเป็น THB ทุกกรณี

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/products/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "sku": "SKU-001",
    "name": "รายการตัวอย่าง",
    "type": "product",
    "category": "สินค้า",
    "unit": "pcs",
    "is_active": true,
    "track_inventory": true,
    "updated_at": "2026-10-19T05:00:00+00:00",
    "price": "100.00",
    "cost": "0.00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.sku` | string / null | ปกติ | รหัส SKU ของสินค้าหรือบริการ |
| `data.name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data.type` | string / null | ปกติ | ชนิดสินค้า/บริการ เช่น product, service, package |
| `data.category` | string / null | ปกติ | หมวดหมู่สินค้าตามค่าที่จัดเก็บ |
| `data.unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.is_active` | boolean | ปกติ | true เมื่อรายการเปิดใช้งาน |
| `data.track_inventory` | boolean | ปกติ | true เมื่อสินค้าถูกกำหนดให้ติดตามสต็อก |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.price` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ราคาขายที่จัดเก็บ คืนเมื่อมี products:prices:read เพิ่ม |
| `data.cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนที่จัดเก็บ คืนเมื่อมี products:costs:read เพิ่ม |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-06"></a>

### 06. GET /api/v1/reference/branches

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงสาขาสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `reference:organization:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/branches?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-07"></a>

### 07. GET /api/v1/reference/divisions

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงฝ่ายสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `reference:organization:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/divisions?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "branch_id": "019a0010-0000-7000-8000-000000000001",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].branch_id` | string (uuid) / null | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-08"></a>

### 08. GET /api/v1/reference/departments

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงแผนกสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `reference:organization:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/departments?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "branch_id": "019a0010-0000-7000-8000-000000000001",
      "division_id": "019a0010-0000-7000-8000-000000000001",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].branch_id` | string (uuid) / null | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data[].division_id` | string (uuid) / null | ปกติ | UUID ฝ่าย; ใต้ org_units เป็นชุดกลุ่มตามฝ่าย |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-09"></a>

### 09. GET /api/v1/reference/positions

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงตำแหน่งงานสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `reference:organization:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/positions?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "department_id": "019a0010-0000-7000-8000-000000000001",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].department_id` | string (uuid) / null | ปกติ | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-10"></a>

### 10. GET /api/v1/reference/warehouses

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงคลังสินค้าสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `inventory:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/warehouses?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "branch_id": "019a0010-0000-7000-8000-000000000001",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].branch_id` | string (uuid) / null | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-11"></a>

### 11. GET /api/v1/reference/currencies

**ใช้ทำอะไร:** ดึงข้อมูลอ้างอิงสกุลเงินสำหรับแปลง UUID/รหัสเป็นชื่อที่ใช้งาน

**Scopes:** `reference:currencies:read`

ตรวจ parent org และไม่เปิด arbitrary table access. ไม่มี `/reference/units` เพราะไม่มี Unit Master แยก

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/reference/currencies?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "code": "MAIN",
      "name": "รายการตัวอย่าง",
      "decimal_places": 2,
      "status": "active",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].code` | string / null | ปกติ | รหัสอ้างอิงของ master data; ใน error คือรหัสปัญหาสำหรับให้โปรแกรมตรวจ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].decimal_places` | integer | ปกติ | จำนวนตำแหน่งทศนิยมที่กำหนดใน Currency Master |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-12"></a>

### 12. GET /api/v1/inventory/balances

**ใช้ทำอะไร:** ดึงยอดคงเหลือสินค้าตามคลัง ตำแหน่งจัดเก็บ และล็อต

**Scopes:** `inventory:read`

**เงื่อนไขข้อมูล:** คืน product/warehouse/bin/lot group แบบ signed decimal4. warehouse/bin/lot=null ได้; ไม่ใช่ทุกสินค้าต้องมี row เมื่อ stock0 และไม่ส่งต้นทุน/reserved/available. Cursor เรียง tuple ของกลุ่ม ไม่มี delta/watermark snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `product_id` | query | ไม่ | string (uuid)  | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `warehouse_id` | query | ไม่ | string (uuid)  | UUID คลัง; null ใน stock balance หมายถึง movement ที่ไม่ได้ระบุคลัง; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `inventory_lot_id` | query | ไม่ | string (uuid)  | UUID ล็อต; null หากไม่ระบุ; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/inventory/balances?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "warehouse_id": "019a0010-0000-7000-8000-000000000001",
      "warehouse_bin_id": "019a0010-0000-7000-8000-000000000001",
      "inventory_lot_id": "019a0010-0000-7000-8000-000000000001",
      "lot_no": "LOT-001",
      "on_hand": "10.0000",
      "expires_at": "2026-10-19"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "as_of": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "quantity_mode": "on_hand",
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].warehouse_id` | string (uuid) / null | ปกติ | UUID คลัง; null ใน stock balance หมายถึง movement ที่ไม่ได้ระบุคลัง |
| `data[].warehouse_bin_id` | string (uuid) / null | ปกติ | UUID ตำแหน่งจัดเก็บภายในคลัง; null หากไม่ระบุ |
| `data[].inventory_lot_id` | string (uuid) / null | ปกติ | UUID ล็อต; null หากไม่ระบุ |
| `data[].lot_no` | string / null | ปกติ | เลขล็อตสินค้า ไม่ใช่ UUID |
| `data[].on_hand` | decimal string (4 ตำแหน่ง) | ปกติ | ผลรวม stock movement แบบมีเครื่องหมายของ product/warehouse/bin/lot; ไม่ใช่ reserved/available/ATP |
| `data[].expires_at` | string (date) / null | ปกติ | วันหมดอายุของล็อต รูปแบบวันที่ตาม source (YYYY-MM-DD หรือ date-time เมื่อมี cast); null หากไม่มี |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-13"></a>

### 13. GET /api/v1/summaries/inventory

**ใช้ทำอะไร:** ดึงข้อมูลสรุปสินค้าคงคลัง

**Scopes:** `summary:inventory:read`

**เงื่อนไขข้อมูล:** Current snapshot เท่านั้น ไม่รับ date range. Low-stock ต้อง reorder_point>0 และ summed on_hand ต่ำกว่าจุดสั่งซื้อ รวมสินค้าศูนย์ stock ที่ active/tracked

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| — | — | — | ไม่มี | ไม่รับ query filters หรือ body |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/inventory' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "snapshot": {
      "as_of": "2026-10-19T05:00:00+00:00",
      "active_tracked_products": 2,
      "low_stock_products": 1,
      "negative_stock_products": 0
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "currency_mode": "not_applicable",
    "visibility": "organization",
    "freshness": "live"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.as_of` | string / null | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `data.snapshot.active_tracked_products` | integer | ปกติ | จำนวนสินค้าเปิดใช้งานและ track_inventory=true ปัจจุบัน |
| `data.snapshot.low_stock_products` | integer | ปกติ | สินค้า tracked/active ที่ reorder_point>0 และ on_hand<reorder_point รวมสินค้าที่ stock=0 |
| `data.snapshot.negative_stock_products` | integer | ปกติ | สินค้า tracked/active ที่ยอด movement รวมต่ำกว่า0 |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-14"></a>

### 14. GET /api/v1/employees

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการพนักงานที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `employees:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `search` | query | ไม่ | string maxLength=100 | ข้อความค้นหายาวไม่เกิน100อักขระ รหัสพนักงาน/ชื่อ/นามสกุล |
| `employment_status` | query | ไม่ | string maxLength=100 | สถานะจ้างงาน probation/active/suspended/resigned/terminated; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `employment_type` | query | ไม่ | string maxLength=100 | ประเภทการจ้าง monthly/daily/hourly/contractor; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `branch_id` | query | ไม่ | string maxLength=100 | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `division_id` | query | ไม่ | string maxLength=100 | UUID ฝ่าย; ใต้ org_units เป็นชุดกลุ่มตามฝ่าย; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `department_id` | query | ไม่ | string maxLength=100 | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |
| `position_id` | query | ไม่ | string maxLength=100 | UUID ตำแหน่ง; ใต้ org_units เป็นชุดกลุ่มตามตำแหน่ง; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "employee_code": "EMP-001",
      "first_name": "สมชาย",
      "last_name": "ตัวอย่าง",
      "nickname": "ชาย",
      "branch_id": "019a0010-0000-7000-8000-000000000001",
      "division_id": "019a0010-0000-7000-8000-000000000001",
      "department_id": "019a0010-0000-7000-8000-000000000001",
      "position_id": "019a0010-0000-7000-8000-000000000001",
      "manager_id": null,
      "employment_type": "monthly",
      "employment_status": "active",
      "started_on": "2026-10-19",
      "probation_ends_on": "2026-10-19",
      "resigned_on": null,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].employee_code` | string / null | ปกติ | รหัสพนักงาน ใช้ร่วมกับ Employee UUID เพื่อเชื่อมข้อมูลโดยไม่ใช้เลขบัตรประชาชน |
| `data[].first_name` | string / null | ปกติ | ชื่อพนักงาน |
| `data[].last_name` | string / null | ปกติ | นามสกุลพนักงาน |
| `data[].nickname` | string / null | ปกติ | ชื่อเล่นพนักงาน หากมี |
| `data[].branch_id` | string (uuid) / null | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data[].division_id` | string (uuid) / null | ปกติ | UUID ฝ่าย; ใต้ org_units เป็นชุดกลุ่มตามฝ่าย |
| `data[].department_id` | string (uuid) / null | ปกติ | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก |
| `data[].position_id` | string (uuid) / null | ปกติ | UUID ตำแหน่ง; ใต้ org_units เป็นชุดกลุ่มตามตำแหน่ง |
| `data[].manager_id` | string (uuid) / null | ปกติ | UUID ของ Employee ผู้บังคับบัญชา; null หากไม่มีหรืออยู่นอกสิทธิ์ key |
| `data[].employment_type` | string / null | ปกติ | ประเภทการจ้าง monthly/daily/hourly/contractor |
| `data[].employment_status` | string / null | ปกติ | สถานะจ้างงาน probation/active/suspended/resigned/terminated |
| `data[].started_on` | string (date) / null | ปกติ | วันที่เริ่มงานจริง ไม่ใช่ created_at ของรายการ import |
| `data[].probation_ends_on` | string (date) / null | ปกติ | วันสิ้นสุดทดลองงานที่บันทึกไว้ |
| `data[].resigned_on` | string (date) / null | ปกติ | วันพ้นสภาพ/ลาออกที่บันทึกไว้ |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-15"></a>

### 15. GET /api/v1/employees/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดพนักงานหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `employees:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ พนักงาน จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "employee_code": "EMP-001",
    "first_name": "สมชาย",
    "last_name": "ตัวอย่าง",
    "nickname": "ชาย",
    "branch_id": "019a0010-0000-7000-8000-000000000001",
    "division_id": "019a0010-0000-7000-8000-000000000001",
    "department_id": "019a0010-0000-7000-8000-000000000001",
    "position_id": "019a0010-0000-7000-8000-000000000001",
    "manager_id": null,
    "employment_type": "monthly",
    "employment_status": "active",
    "started_on": "2026-10-19",
    "probation_ends_on": "2026-10-19",
    "resigned_on": null,
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "visibility": "credential_hr_policy"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.employee_code` | string / null | ปกติ | รหัสพนักงาน ใช้ร่วมกับ Employee UUID เพื่อเชื่อมข้อมูลโดยไม่ใช้เลขบัตรประชาชน |
| `data.first_name` | string / null | ปกติ | ชื่อพนักงาน |
| `data.last_name` | string / null | ปกติ | นามสกุลพนักงาน |
| `data.nickname` | string / null | ปกติ | ชื่อเล่นพนักงาน หากมี |
| `data.branch_id` | string (uuid) / null | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data.division_id` | string (uuid) / null | ปกติ | UUID ฝ่าย; ใต้ org_units เป็นชุดกลุ่มตามฝ่าย |
| `data.department_id` | string (uuid) / null | ปกติ | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก |
| `data.position_id` | string (uuid) / null | ปกติ | UUID ตำแหน่ง; ใต้ org_units เป็นชุดกลุ่มตามตำแหน่ง |
| `data.manager_id` | string (uuid) / null | ปกติ | UUID ของ Employee ผู้บังคับบัญชา; null หากไม่มีหรืออยู่นอกสิทธิ์ key |
| `data.employment_type` | string / null | ปกติ | ประเภทการจ้าง monthly/daily/hourly/contractor |
| `data.employment_status` | string / null | ปกติ | สถานะจ้างงาน probation/active/suspended/resigned/terminated |
| `data.started_on` | string (date) / null | ปกติ | วันที่เริ่มงานจริง ไม่ใช่ created_at ของรายการ import |
| `data.probation_ends_on` | string (date) / null | ปกติ | วันสิ้นสุดทดลองงานที่บันทึกไว้ |
| `data.resigned_on` | string (date) / null | ปกติ | วันพ้นสภาพ/ลาออกที่บันทึกไว้ |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-16"></a>

### 16. GET /api/v1/employees/{id}/attendance

**ใช้ทำอะไร:** ดึงรายการลงเวลาของพนักงานตาม work_date ในช่วง

**Scopes:** `employees:attendance:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

Operational list ไม่รับupdated_since. Date pairไม่ส่งทั้งคู่ใช้เดือนปัจจุบัน. Attendance/rosterไม่ส่งrates/notes; leave-requestไม่ส่งประเภท/เหตุผล/attachment และtotal_daysยังเป็นทั้งคำขอแม้queryทับเพียงบางวัน

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ พนักงาน จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees/019a0010-0000-7000-8000-000000000001/attendance?limit=50&start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "work_date": "2026-10-19",
      "clock_in_at": "2026-10-19T01:00:00+00:00",
      "clock_out_at": "2026-10-19T10:00:00+00:00",
      "late_minutes": 0,
      "early_leave_minutes": 0,
      "worked_minutes": 480,
      "ot_normal_minutes": 0,
      "ot_holiday_minutes": 0,
      "ot_special_minutes": 0,
      "is_reconciled": true,
      "is_absent": false,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].work_date` | string (date) / null | ปกติ | วันที่ทำงานซึ่งระบบผูก attendance เข้ากะ ไม่จำเป็นต้องเท่ากับวันตามปฏิทินของ clock-out |
| `data[].clock_in_at` | string (date-time) / null | ปกติ | วันเวลาเข้างาน; null หากยังไม่มี |
| `data[].clock_out_at` | string (date-time) / null | ปกติ | วันเวลาออกงาน; null หากยังไม่มี |
| `data[].late_minutes` | integer | ปกติ | จำนวนนาทีสาย; summary รวมเฉพาะ reconciled records |
| `data[].early_leave_minutes` | integer | ปกติ | จำนวนนาทีกลับก่อน; summary รวมเฉพาะ reconciled records |
| `data[].worked_minutes` | integer | ปกติ | จำนวนนาทีทำงาน; summary รวมเฉพาะ reconciled records |
| `data[].ot_normal_minutes` | integer | ปกติ | นาที OT ปกติ ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data[].ot_holiday_minutes` | integer | ปกติ | นาที OT วันหยุด ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data[].ot_special_minutes` | integer | ปกติ | นาที OT วันหยุดพิเศษ ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data[].is_reconciled` | boolean | ปกติ | true เมื่อ attendance ผ่านกระบวนการ reconciliation แล้ว |
| `data[].is_absent` | boolean | ปกติ | สถานะขาดงานที่เก็บใน record; การไม่มี clock-in อย่างเดียวไม่ยืนยัน absent |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-17"></a>

### 17. GET /api/v1/employees/{id}/roster

**ใช้ทำอะไร:** ดึงตารางกะพนักงานตาม schedule_date ในช่วง

**Scopes:** `employees:roster:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

Operational list ไม่รับupdated_since. Date pairไม่ส่งทั้งคู่ใช้เดือนปัจจุบัน. Attendance/rosterไม่ส่งrates/notes; leave-requestไม่ส่งประเภท/เหตุผล/attachment และtotal_daysยังเป็นทั้งคำขอแม้queryทับเพียงบางวัน

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ พนักงาน จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees/019a0010-0000-7000-8000-000000000001/roster?limit=50&start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "schedule_date": "2026-10-19",
      "is_day_off": false,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "shift": "ตัวอย่าง"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].schedule_date` | string (date) / null | ปกติ | วันที่จัดกะ |
| `data[].is_day_off` | boolean | ปกติ | true เมื่อจัดเป็นวันหยุด; shift อาจเป็น null |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].shift` | ค่าตาม schema | ปกติ | รายละเอียดกะที่อนุญาต ไม่รวม OT rates/payroll/notes |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-18"></a>

### 18. GET /api/v1/employees/{id}/leave-requests

**ใช้ทำอะไร:** ดึงคำขอลาพนักงานที่ช่วงวันลาทับช่วง query

**Scopes:** `employees:leaves:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

Operational list ไม่รับupdated_since. Date pairไม่ส่งทั้งคู่ใช้เดือนปัจจุบัน. Attendance/rosterไม่ส่งrates/notes; leave-requestไม่ส่งประเภท/เหตุผล/attachment และtotal_daysยังเป็นทั้งคำขอแม้queryทับเพียงบางวัน

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ พนักงาน จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees/019a0010-0000-7000-8000-000000000001/leave-requests?limit=50&start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "starts_on": "2026-10-19",
      "ends_on": "2026-10-19",
      "total_days": "1.00",
      "status": "approved",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].starts_on` | string (date) / null | ปกติ | วันเริ่มคำขอลา |
| `data[].ends_on` | string (date) / null | ปกติ | วันสิ้นสุดคำขอลา |
| `data[].total_days` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนวันลารวมทั้งคำขอ ไม่ใช่เฉพาะส่วนที่ทับช่วง query |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error; **409 unsupported_employee_coverage** เมื่อไม่มีlinkedUser. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-19"></a>

### 19. GET /api/v1/employees/{id}/leave-balances

**ใช้ทำอะไร:** ดึงยอดสิทธิ์วันลาของพนักงานในปีปัจจุบัน ณ ตอนอ่าน

**Scopes:** `employees:leaves:read`

**เงื่อนไขข้อมูล:** HR key ต้อง explicit ทุกสาขาหรือ branch IDs. รองรับ Employee ไม่มี User รวมพ้นสภาพตาม filter. คืน structure เป็น UUID ไม่ใช่ชื่อ manager นอก visibility เป็น null. ไม่คืน identity/payroll/bank/private contact

คืนเฉพาะcurrentyear ไม่รับstart_date/end_date หรือupdated_since; ไม่มีlinkedUserตอบ409 ไม่อ้างยอดสิทธิ์ย้อนหลัง

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ พนักงาน จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/employees/019a0010-0000-7000-8000-000000000001/leave-balances?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "leave_type_id": "019a0010-0000-7000-8000-000000000001",
      "leave_year": 2026,
      "opening_days": "10.00",
      "accrued_days": "0.00",
      "carry_over_days": "0.00",
      "used_days": "2.00",
      "remaining_days": "8.00",
      "updated_at": "2026-10-19T05:00:00+00:00",
      "as_of": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].leave_type_id` | string (uuid) / null | ปกติ | UUID ประเภทสิทธิ์ลา ใช้อ้างอิง balance; API นี้ไม่ให้ชื่อ/เหตุผลประเภทลา |
| `data[].leave_year` | integer | ปกติ | ปีสิทธิ์ลา ปัจจุบันคืนเฉพาะ current year ของ timezone องค์กร |
| `data[].opening_days` | decimal string (2 ตำแหน่ง) | ปกติ | สิทธิ์วันลาตั้งต้น |
| `data[].accrued_days` | decimal string (2 ตำแหน่ง) | ปกติ | สิทธิ์วันลาที่สะสมเพิ่ม |
| `data[].carry_over_days` | decimal string (2 ตำแหน่ง) | ปกติ | วันลายกมา |
| `data[].used_days` | decimal string (2 ตำแหน่ง) | ปกติ | สิทธิ์วันลาที่ใช้ไปตามยอดที่ระบบจัดเก็บ |
| `data[].remaining_days` | decimal string (2 ตำแหน่ง) | ปกติ | opening_days + accrued_days + carry_over_days - used_days; ไม่อ้าง historical ledger |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].as_of` | string / null | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error; **409 unsupported_employee_coverage** เมื่อไม่มีlinkedUser. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-20"></a>

### 20. GET /api/v1/summaries/hr

**ใช้ทำอะไร:** ดึงข้อมูลสรุปบุคลากร

**Scopes:** `summary:hr:read`

**เงื่อนไขข้อมูล:** ใช้ Employee ไม่ใช่ User; current headcount/status ไม่ใช่ historical end_date. org-unit groups<5 suppress count=null และ cap100groups

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/hr?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "employees_started": 2,
      "employees_departed": 0
    },
    "snapshot": {
      "total_employees": 6,
      "active_employees": 6,
      "by_status_and_type": [
        {
          "employment_status": "active",
          "employment_type": "monthly",
          "count": 6
        }
      ],
      "probation_ends_within_30_days": 1,
      "small_group_threshold": 5,
      "org_units": {
        "branch_id": {
          "truncated": false,
          "groups": [
            {
              "id": "019a0010-0000-7000-8000-000000000001",
              "count": 6,
              "suppressed": false
            }
          ]
        },
        "division_id": {
          "truncated": false,
          "groups": [
            {
              "id": "019a0010-0000-7000-8000-000000000001",
              "count": 6,
              "suppressed": false
            }
          ]
        },
        "department_id": {
          "truncated": false,
          "groups": [
            {
              "id": "019a0010-0000-7000-8000-000000000001",
              "count": 6,
              "suppressed": false
            }
          ]
        },
        "position_id": {
          "truncated": false,
          "groups": [
            {
              "id": "019a0010-0000-7000-8000-000000000001",
              "count": 6,
              "suppressed": false
            }
          ]
        }
      }
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "credential_hr_policy",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.employees_started` | integer | ปกติ | จำนวนพนักงานที่ started_on อยู่ในช่วง ไม่ใช้เวลา import |
| `data.period.employees_departed` | integer | ปกติ | จำนวนพนักงานที่ resigned_on อยู่ในช่วง |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.total_employees` | integer | ปกติ | จำนวน Employee ที่ HR policy เห็นทั้งหมดปัจจุบัน ไม่ใช่จำนวน User |
| `data.snapshot.active_employees` | integer | ปกติ | Employee สถานะ active/probation และ started_on/resigned_on ครอบคลุมวันนี้หรือยังไม่ระบุ |
| `data.snapshot.by_status_and_type` | array | ปกติ | จำนวนพนักงานแบ่งสถานะและประเภทการจ้างปัจจุบัน |
| `data.snapshot.by_status_and_type[].employment_status` | string / null | ปกติ | สถานะจ้างงาน probation/active/suspended/resigned/terminated |
| `data.snapshot.by_status_and_type[].employment_type` | string / null | ปกติ | ประเภทการจ้าง monthly/daily/hourly/contractor |
| `data.snapshot.by_status_and_type[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.probation_ends_within_30_days` | integer | ปกติ | พนักงานที่ยังจ้างอยู่และ probation_ends_on อยู่ระหว่างวันนี้ถึง30วันถัดไป |
| `data.snapshot.small_group_threshold` | integer | ปกติ | เกณฑ์ suppression ของกลุ่ม HR ปัจจุบัน5คน |
| `data.snapshot.org_units` | object | ปกติ | จำนวนพนักงานแบ่งตาม branch/division/department/position ภายใต้ HR policy |
| `data.snapshot.org_units.branch_id` | object | ปกติ | UUID สาขา; ใต้ org_units เป็นชุดกลุ่มตามสาขา ไม่ใช่ UUID ค่าเดียว |
| `data.snapshot.org_units.branch_id.truncated` | boolean | ปกติ | true หากมีมากกว่า100กลุ่มและแสดงเพียง100กลุ่มแรก |
| `data.snapshot.org_units.branch_id.groups` | array | ปกติ | รายการกลุ่มตามมิติ parent แต่ละกลุ่มมี id/count/suppressed |
| `data.snapshot.org_units.branch_id.groups[].id` | string (uuid) / null | ปกติ | UUID ของกลุ่มโครงสร้างนี้; null เมื่อไม่ระบุ org unit |
| `data.snapshot.org_units.branch_id.groups[].count` | integer / null | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.org_units.branch_id.groups[].suppressed` | boolean | ปกติ | true เมื่อกลุ่มเล็กกว่า5คน จำนวนที่เกี่ยวข้องจะเป็น null เพื่อจำกัดการเปิดเผย |
| `data.snapshot.org_units.division_id` | object | ปกติ | UUID ฝ่าย; ใต้ org_units เป็นชุดกลุ่มตามฝ่าย |
| `data.snapshot.org_units.division_id.truncated` | boolean | ปกติ | true หากมีมากกว่า100กลุ่มและแสดงเพียง100กลุ่มแรก |
| `data.snapshot.org_units.division_id.groups` | array | ปกติ | รายการกลุ่มตามมิติ parent แต่ละกลุ่มมี id/count/suppressed |
| `data.snapshot.org_units.division_id.groups[].id` | string (uuid) / null | ปกติ | UUID ของกลุ่มโครงสร้างนี้; null เมื่อไม่ระบุ org unit |
| `data.snapshot.org_units.division_id.groups[].count` | integer / null | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.org_units.division_id.groups[].suppressed` | boolean | ปกติ | true เมื่อกลุ่มเล็กกว่า5คน จำนวนที่เกี่ยวข้องจะเป็น null เพื่อจำกัดการเปิดเผย |
| `data.snapshot.org_units.department_id` | object | ปกติ | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก |
| `data.snapshot.org_units.department_id.truncated` | boolean | ปกติ | true หากมีมากกว่า100กลุ่มและแสดงเพียง100กลุ่มแรก |
| `data.snapshot.org_units.department_id.groups` | array | ปกติ | รายการกลุ่มตามมิติ parent แต่ละกลุ่มมี id/count/suppressed |
| `data.snapshot.org_units.department_id.groups[].id` | string (uuid) / null | ปกติ | UUID ของกลุ่มโครงสร้างนี้; null เมื่อไม่ระบุ org unit |
| `data.snapshot.org_units.department_id.groups[].count` | integer / null | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.org_units.department_id.groups[].suppressed` | boolean | ปกติ | true เมื่อกลุ่มเล็กกว่า5คน จำนวนที่เกี่ยวข้องจะเป็น null เพื่อจำกัดการเปิดเผย |
| `data.snapshot.org_units.position_id` | object | ปกติ | UUID ตำแหน่ง; ใต้ org_units เป็นชุดกลุ่มตามตำแหน่ง |
| `data.snapshot.org_units.position_id.truncated` | boolean | ปกติ | true หากมีมากกว่า100กลุ่มและแสดงเพียง100กลุ่มแรก |
| `data.snapshot.org_units.position_id.groups` | array | ปกติ | รายการกลุ่มตามมิติ parent แต่ละกลุ่มมี id/count/suppressed |
| `data.snapshot.org_units.position_id.groups[].id` | string (uuid) / null | ปกติ | UUID ของกลุ่มโครงสร้างนี้; null เมื่อไม่ระบุ org unit |
| `data.snapshot.org_units.position_id.groups[].count` | integer / null | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.org_units.position_id.groups[].suppressed` | boolean | ปกติ | true เมื่อกลุ่มเล็กกว่า5คน จำนวนที่เกี่ยวข้องจะเป็น null เพื่อจำกัดการเปิดเผย |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-21"></a>

### 21. GET /api/v1/summaries/attendance

**ใช้ทำอะไร:** ดึงข้อมูลสรุปเวลาทำงาน

**Scopes:** `summary:attendance:read`

**เงื่อนไขข้อมูล:** เลือก attendance หลักต่อ Employee+work_date: reconciled ก่อน แล้ว updated_at/id; รวม minutes เฉพาะ reconciled. Pending/incomplete อาจทับกัน ห้ามอนุมาน absent จาก missing punch

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/attendance?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "employee_workdays": 3,
      "distinct_employees": 2,
      "worked_minutes": 960,
      "late_minutes": 10,
      "early_leave_minutes": 0,
      "ot_normal_minutes": 60,
      "ot_holiday_minutes": 0,
      "ot_special_minutes": 0,
      "present_workdays": 2,
      "present_workdays_employees": 2,
      "late_workdays": 1,
      "late_workdays_employees": 1,
      "early_leave_workdays": 0,
      "early_leave_workdays_employees": 0,
      "confirmed_absent_workdays": 0,
      "confirmed_absent_workdays_employees": 0,
      "pending_workdays": 1,
      "pending_workdays_employees": 1,
      "incomplete_workdays": 1,
      "incomplete_workdays_employees": 1
    },
    "coverage": {
      "basis": "employee_workdays",
      "legacy_user_bridge": true,
      "missing_punch_is_absence": false,
      "minutes_basis": "reconciled_only"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "credential_hr_policy",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.employee_workdays` | integer | ปกติ | จำนวนคู่ Employee+work_date หลังเลือก attendance หลักของวันนั้น |
| `data.period.distinct_employees` | integer | ปกติ | จำนวนพนักงานไม่ซ้ำที่มี attendance ในช่วง |
| `data.period.worked_minutes` | integer | ปกติ | จำนวนนาทีทำงาน; summary รวมเฉพาะ reconciled records |
| `data.period.late_minutes` | integer | ปกติ | จำนวนนาทีสาย; summary รวมเฉพาะ reconciled records |
| `data.period.early_leave_minutes` | integer | ปกติ | จำนวนนาทีกลับก่อน; summary รวมเฉพาะ reconciled records |
| `data.period.ot_normal_minutes` | integer | ปกติ | นาที OT ปกติ ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data.period.ot_holiday_minutes` | integer | ปกติ | นาที OT วันหยุด ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data.period.ot_special_minutes` | integer | ปกติ | นาที OT วันหยุดพิเศษ ไม่ส่งอัตราจ่ายหรือยอดเงิน OT |
| `data.period.present_workdays` | integer | ปกติ | จำนวน employee-days ที่ reconciled ไม่ absent และมีทั้ง clock-in/out |
| `data.period.present_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข present_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.period.late_workdays` | integer | ปกติ | จำนวน employee-days ที่ reconciled และ late_minutes>0 |
| `data.period.late_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข late_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.period.early_leave_workdays` | integer | ปกติ | จำนวน employee-days ที่ reconciled และ early_leave_minutes>0 |
| `data.period.early_leave_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข early_leave_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.period.confirmed_absent_workdays` | integer | ปกติ | จำนวน employee-days ที่ reconciled และ is_absent=true |
| `data.period.confirmed_absent_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข confirmed_absent_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.period.pending_workdays` | integer | ปกติ | จำนวน employee-days ที่ยังไม่ reconciled |
| `data.period.pending_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข pending_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.period.incomplete_workdays` | integer | ปกติ | จำนวน employee-days ที่ไม่ absent แต่ขาด clock-in หรือ clock-out อาจทับกับ pending |
| `data.period.incomplete_workdays_employees` | integer | ปกติ | จำนวน Employee ไม่ซ้ำที่เข้าเงื่อนไข incomplete_workdays ในช่วง ไม่ใช่จำนวนวัน |
| `data.coverage` | object | ปกติ | เงื่อนไข/ความครอบคลุมข้อมูล ต้องอ่านก่อนตีความจำนวนว่าเป็นทั้งองค์กร |
| `data.coverage.basis` | string / null | ปกติ | ฐานคำนวณ attendance summary ปัจจุบัน employee_workdays |
| `data.coverage.legacy_user_bridge` | boolean | ปกติ | true เมื่อรองรับ attendance เดิมที่อ้าง user_id ผ่าน Employee ใน org เดียวกัน |
| `data.coverage.missing_punch_is_absence` | boolean | ปกติ | false: ไม่มี punch อย่างเดียวไม่ถือว่าขาดงาน |
| `data.coverage.minutes_basis` | string / null | ปกติ | reconciled_only: รวม minutes เฉพาะรายการที่ผ่าน reconciliation |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-22"></a>

### 22. GET /api/v1/summaries/leaves

**ใช้ทำอะไร:** ดึงข้อมูลสรุปการลา

**Scopes:** `summary:leaves:read`

**เงื่อนไขข้อมูล:** ใช้คำขอ starts_on/ends_on ทับช่วง. Approved employee-workdays ตัดเสาร์–อาทิตย์/วันหยุดองค์กร และ dedupe employee/date; ไม่ใช่ total_days ทั้งคำขอหรือ payroll entitlement. อ่าน coverage non-User ด้วย

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/leaves?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "requests_overlapping_by_status": [
        {
          "status": "approved",
          "count": 2
        }
      ],
      "approved_distinct_employees": 1,
      "approved_leave_employee_workdays": "1.00"
    },
    "coverage": {
      "linked_employees": 6,
      "unsupported_non_user_employees": 1,
      "day_policy": "weekdays_excluding_organization_holidays",
      "type_breakdown": "withheld",
      "overlap_deduplication": "employee_date"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "credential_hr_policy",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.requests_overlapping_by_status` | array | ปกติ | จำนวนคำขอลาที่ starts_on/ends_on ทับช่วง แบ่ง current status |
| `data.period.requests_overlapping_by_status[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.period.requests_overlapping_by_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.approved_distinct_employees` | integer | ปกติ | จำนวนพนักงาน linked User ที่มีคำขอ approved ทับช่วงแบบไม่ซ้ำ |
| `data.period.approved_leave_employee_workdays` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนคู่พนักงาน/วันลาที่ approved เฉพาะส่วนทับช่วง ตัดเสาร์อาทิตย์/วันหยุดองค์กร และ dedupe วันซ้ำ ไม่ใช่ payroll entitlement |
| `data.coverage` | object | ปกติ | เงื่อนไข/ความครอบคลุมข้อมูล ต้องอ่านก่อนตีความจำนวนว่าเป็นทั้งองค์กร |
| `data.coverage.linked_employees` | integer | ปกติ | จำนวน Employee ใน HR policy ที่มี user bridge ซึ่งอ่าน leave ได้ |
| `data.coverage.unsupported_non_user_employees` | integer | ปกติ | จำนวน Employee ที่ไม่มี linked User จึงไม่อยู่ใน leave coverage ไม่ใช่คนที่มีสิทธิ์ลา0 |
| `data.coverage.day_policy` | string / null | ปกติ | weekdays_excluding_organization_holidays: นิยามวันของ leave summary |
| `data.coverage.type_breakdown` | string / null | ปกติ | withheld: ไม่เปิด breakdown ประเภทลา |
| `data.coverage.overlap_deduplication` | string / null | ปกติ | employee_date: รวมคำขอที่ทับกันโดยนับพนักงาน/วันครั้งเดียว |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-23"></a>

### 23. GET /api/v1/summaries/roster

**ใช้ทำอะไร:** ดึงข้อมูลสรุปการจัดกะ

**Scopes:** `summary:roster:read`

**เงื่อนไขข้อมูล:** Eligible ใช้ current status+recorded employment dates. missing_schedule_workdays=null เพราะไม่มี required-workday calendar; department group<5 suppress และ cap100. ไม่มี schedule ไม่ใช่ absent

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/roster?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "allocated_days": 6,
      "day_off_allocations": 0,
      "scheduled_employees": 6,
      "eligible_employees": 7,
      "employees_without_any_schedule": 1,
      "missing_schedule_workdays": null,
      "department_allocations": [
        {
          "department_id": "019a0010-0000-7000-8000-000000000001",
          "allocated_days": 6,
          "scheduled_employees": 6,
          "suppressed": false
        }
      ],
      "departments_truncated": false
    },
    "coverage": {
      "eligibility_basis": "current_status_and_recorded_employment_dates",
      "missing_workdays_reason": "No authoritative per-employee required-workday calendar; missing schedule is not absence."
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "credential_hr_policy",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.allocated_days` | integer | ปกติ | จำนวน schedule records ในช่วง รวม day-off allocations |
| `data.period.day_off_allocations` | integer | ปกติ | จำนวน schedule records ที่ is_day_off=true |
| `data.period.scheduled_employees` | integer | ปกติ | จำนวนพนักงานไม่ซ้ำที่มี schedule ในช่วง |
| `data.period.eligible_employees` | integer | ปกติ | Employee ที่ HR policy เห็นและ employment dates/current status เข้าเกณฑ์ช่วงวัน |
| `data.period.employees_without_any_schedule` | integer | ปกติ | max(eligible_employees - scheduled_employees, 0) ไม่ใช่จำนวนคนขาดงาน |
| `data.period.missing_schedule_workdays` | null | ปกติ | null เพราะไม่มี authoritative required-workday calendar รายคน ไม่สมมติเป็น0 |
| `data.period.department_allocations` | array | ปกติ | กลุ่ม roster ตาม department พร้อมจำนวนและ suppression สำหรับกลุ่มเล็ก |
| `data.period.department_allocations[].department_id` | string (uuid) / null | ปกติ | UUID แผนก; ใต้ org_units เป็นชุดกลุ่มตามแผนก |
| `data.period.department_allocations[].allocated_days` | integer / null | ปกติ | จำนวน schedule records ในช่วง รวม day-off allocations |
| `data.period.department_allocations[].scheduled_employees` | integer / null | ปกติ | จำนวนพนักงานไม่ซ้ำที่มี schedule ในช่วง |
| `data.period.department_allocations[].suppressed` | boolean | ปกติ | true เมื่อกลุ่มเล็กกว่า5คน จำนวนที่เกี่ยวข้องจะเป็น null เพื่อจำกัดการเปิดเผย |
| `data.period.departments_truncated` | boolean | ปกติ | true หาก roster มีเกิน100แผนกและแสดงเพียง100กลุ่ม |
| `data.coverage` | object | ปกติ | เงื่อนไข/ความครอบคลุมข้อมูล ต้องอ่านก่อนตีความจำนวนว่าเป็นทั้งองค์กร |
| `data.coverage.eligibility_basis` | string / null | ปกติ | current_status_and_recorded_employment_dates: ข้อจำกัดฐาน eligible roster |
| `data.coverage.missing_workdays_reason` | string / null | ปกติ | เหตุผลที่ missing_schedule_workdays ยังให้ค่าไม่ได้ |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-24"></a>

### 24. GET /api/v1/customers

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการลูกค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `customers:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/customers?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "customer_code": "CUS-001",
      "company_name": "บริษัท ตัวอย่าง จำกัด",
      "customer_type": "company",
      "status": "active",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].customer_code` | string / null | ปกติ | รหัสลูกค้า |
| `data[].company_name` | string / null | ปกติ | ชื่อบริษัท/กิจการลูกค้า |
| `data[].customer_type` | string / null | ปกติ | ชนิดลูกค้าที่จัดเก็บ เช่น company หรือ individual ไม่ใช่สิทธิ์การเข้าถึง |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-25"></a>

### 25. GET /api/v1/customers/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดลูกค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `customers:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/customers/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "customer_code": "CUS-001",
    "company_name": "บริษัท ตัวอย่าง จำกัด",
    "customer_type": "company",
    "status": "active",
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.customer_code` | string / null | ปกติ | รหัสลูกค้า |
| `data.company_name` | string / null | ปกติ | ชื่อบริษัท/กิจการลูกค้า |
| `data.customer_type` | string / null | ปกติ | ชนิดลูกค้าที่จัดเก็บ เช่น company หรือ individual ไม่ใช่สิทธิ์การเข้าถึง |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-26"></a>

### 26. GET /api/v1/suppliers

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการคู่ค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `suppliers:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/suppliers?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "supplier_code": "SUP-001",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].supplier_code` | string / null | ปกติ | รหัสคู่ค้า |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-27"></a>

### 27. GET /api/v1/suppliers/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดคู่ค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `suppliers:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/suppliers/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "supplier_code": "SUP-001",
    "name": "รายการตัวอย่าง",
    "status": "active",
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.supplier_code` | string / null | ปกติ | รหัสคู่ค้า |
| `data.name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-28"></a>

### 28. GET /api/v1/quotations

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบเสนอราคาที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `quotations:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/quotations?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "quotation_no": "QT-2026-0001",
      "customer_id": "019a0010-0000-7000-8000-000000000001",
      "status": "sent",
      "issue_date": "2026-10-19",
      "valid_until": "2026-10-19",
      "subtotal": "1000.00",
      "discount_amount": "0.00",
      "tax_amount": "0.00",
      "total": "1000.00",
      "currency": "THB",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].quotation_no` | string / null | ปกติ | เลขที่ใบเสนอราคา |
| `data[].customer_id` | string (uuid) / null | ปกติ | UUID ลูกค้าที่สัมพันธ์กับเอกสาร |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].issue_date` | string (date) / null | ปกติ | วันที่ออกเอกสาร |
| `data[].valid_until` | string (date) / null | ปกติ | วันสิ้นสุดอายุใบเสนอราคา |
| `data[].subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data[].total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-29"></a>

### 29. GET /api/v1/quotations/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบเสนอราคาหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `quotations:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

Detailฝังitemsสูงสุด100รายการ ดูitems_truncated; ถ้าtrueใช้/{id}/itemsแบบแบ่งหน้า ไม่ถือว่าdetailมีitemsครบ

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/quotations/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "quotation_no": "QT-2026-0001",
    "customer_id": "019a0010-0000-7000-8000-000000000001",
    "status": "sent",
    "issue_date": "2026-10-19",
    "valid_until": "2026-10-19",
    "subtotal": "1000.00",
    "discount_amount": "0.00",
    "tax_amount": "0.00",
    "total": "1000.00",
    "currency": "THB",
    "updated_at": "2026-10-19T05:00:00+00:00",
    "items": [
      {
        "id": "019a0010-0000-7000-8000-000000000001",
        "product_id": "019a0010-0000-7000-8000-000000000001",
        "quantity": "10.0000",
        "unit": "pcs",
        "unit_price": "100.00",
        "discount_amount": "0.00",
        "tax_rate": "0.00",
        "line_total": "1000.00",
        "updated_at": "2026-10-19T05:00:00+00:00"
      }
    ],
    "items_truncated": false
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.quotation_no` | string / null | ปกติ | เลขที่ใบเสนอราคา |
| `data.customer_id` | string (uuid) / null | ปกติ | UUID ลูกค้าที่สัมพันธ์กับเอกสาร |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.issue_date` | string (date) / null | ปกติ | วันที่ออกเอกสาร |
| `data.valid_until` | string (date) / null | ปกติ | วันสิ้นสุดอายุใบเสนอราคา |
| `data.subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data.discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data.total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data.currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items` | array | ปกติ | รายการย่อยใน detail; สูงสุด100รายการ ต้องอ่าน endpoint /items ต่อหากถูกตัด |
| `data.items[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.items[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.items[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.items[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.items[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data.items[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.items[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data.items[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data.items[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items_truncated` | boolean | ปกติ | true เมื่อ detail มีรายการย่อยเกิน100และคืนเฉพาะ100รายการแรก |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-30"></a>

### 30. GET /api/v1/quotations/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบเสนอราคาที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `quotations:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/quotations/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "quantity": "10.0000",
      "unit": "pcs",
      "unit_price": "100.00",
      "discount_amount": "0.00",
      "tax_rate": "0.00",
      "line_total": "1000.00",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-31"></a>

### 31. GET /api/v1/invoices

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบแจ้งหนี้ที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `invoices:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/invoices?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "invoice_no": "INV-2026-0001",
      "customer_id": "019a0010-0000-7000-8000-000000000001",
      "status": "partially_paid",
      "issue_date": "2026-10-19",
      "due_date": "2026-10-19",
      "subtotal": "1000.00",
      "discount_amount": "0.00",
      "tax_amount": "0.00",
      "total": "1000.00",
      "paid_amount": "500.00",
      "balance_due": "500.00",
      "currency": "THB",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].invoice_no` | string / null | ปกติ | เลขที่ใบแจ้งหนี้ |
| `data[].customer_id` | string (uuid) / null | ปกติ | UUID ลูกค้าที่สัมพันธ์กับเอกสาร |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].issue_date` | string (date) / null | ปกติ | วันที่ออกเอกสาร |
| `data[].due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data[].subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data[].total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data[].paid_amount` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดชำระสะสมของ invoice ตาม source |
| `data[].balance_due` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดค้างชำระ invoice ตาม source ไม่ใช่ยอดเงินสดขององค์กร |
| `data[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-32"></a>

### 32. GET /api/v1/invoices/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบแจ้งหนี้หนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `invoices:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

Detailฝังitemsสูงสุด100รายการ ดูitems_truncated; ถ้าtrueใช้/{id}/itemsแบบแบ่งหน้า ไม่ถือว่าdetailมีitemsครบ

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/invoices/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "invoice_no": "INV-2026-0001",
    "customer_id": "019a0010-0000-7000-8000-000000000001",
    "status": "partially_paid",
    "issue_date": "2026-10-19",
    "due_date": "2026-10-19",
    "subtotal": "1000.00",
    "discount_amount": "0.00",
    "tax_amount": "0.00",
    "total": "1000.00",
    "paid_amount": "500.00",
    "balance_due": "500.00",
    "currency": "THB",
    "updated_at": "2026-10-19T05:00:00+00:00",
    "items": [
      {
        "id": "019a0010-0000-7000-8000-000000000001",
        "product_id": "019a0010-0000-7000-8000-000000000001",
        "quantity": "10.0000",
        "unit": "pcs",
        "unit_price": "100.00",
        "discount_amount": "0.00",
        "tax_rate": "0.00",
        "line_total": "1000.00",
        "updated_at": "2026-10-19T05:00:00+00:00"
      }
    ],
    "items_truncated": false
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.invoice_no` | string / null | ปกติ | เลขที่ใบแจ้งหนี้ |
| `data.customer_id` | string (uuid) / null | ปกติ | UUID ลูกค้าที่สัมพันธ์กับเอกสาร |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.issue_date` | string (date) / null | ปกติ | วันที่ออกเอกสาร |
| `data.due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data.subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data.discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data.total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data.paid_amount` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดชำระสะสมของ invoice ตาม source |
| `data.balance_due` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดค้างชำระ invoice ตาม source ไม่ใช่ยอดเงินสดขององค์กร |
| `data.currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items` | array | ปกติ | รายการย่อยใน detail; สูงสุด100รายการ ต้องอ่าน endpoint /items ต่อหากถูกตัด |
| `data.items[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.items[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.items[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.items[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.items[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data.items[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.items[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data.items[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data.items[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items_truncated` | boolean | ปกติ | true เมื่อ detail มีรายการย่อยเกิน100และคืนเฉพาะ100รายการแรก |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-33"></a>

### 33. GET /api/v1/invoices/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบแจ้งหนี้ที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `invoices:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/invoices/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "quantity": "10.0000",
      "unit": "pcs",
      "unit_price": "100.00",
      "discount_amount": "0.00",
      "tax_rate": "0.00",
      "line_total": "1000.00",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-34"></a>

### 34. GET /api/v1/delivery-orders

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบส่งของที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `delivery-orders:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/delivery-orders?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "do_no": "DO-2026-0001",
      "invoice_id": "019a0010-0000-7000-8000-000000000001",
      "status": "draft",
      "delivery_date": "2026-10-19",
      "delivered_at": null,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].do_no` | string / null | ปกติ | เลขที่ใบส่งของ |
| `data[].invoice_id` | string (uuid) / null | ปกติ | UUID ใบแจ้งหนี้ต้นทาง |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].delivery_date` | string (date) / null | ปกติ | วันที่ส่งของที่ระบุในเอกสาร |
| `data[].delivered_at` | string (date-time) / null | ปกติ | เวลาที่บันทึกว่าส่งของแล้ว; ไม่เท่ากับสถานะ invoice paid |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-35"></a>

### 35. GET /api/v1/delivery-orders/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบส่งของหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `delivery-orders:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

Detailฝังitemsสูงสุด100รายการ ดูitems_truncated; ถ้าtrueใช้/{id}/itemsแบบแบ่งหน้า ไม่ถือว่าdetailมีitemsครบ

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/delivery-orders/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "do_no": "DO-2026-0001",
    "invoice_id": "019a0010-0000-7000-8000-000000000001",
    "status": "draft",
    "delivery_date": "2026-10-19",
    "delivered_at": null,
    "updated_at": "2026-10-19T05:00:00+00:00",
    "items": [
      {
        "id": "019a0010-0000-7000-8000-000000000001",
        "product_id": "019a0010-0000-7000-8000-000000000001",
        "quantity": "10.0000",
        "unit": "pcs",
        "updated_at": "2026-10-19T05:00:00+00:00"
      }
    ],
    "items_truncated": false
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.do_no` | string / null | ปกติ | เลขที่ใบส่งของ |
| `data.invoice_id` | string (uuid) / null | ปกติ | UUID ใบแจ้งหนี้ต้นทาง |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.delivery_date` | string (date) / null | ปกติ | วันที่ส่งของที่ระบุในเอกสาร |
| `data.delivered_at` | string (date-time) / null | ปกติ | เวลาที่บันทึกว่าส่งของแล้ว; ไม่เท่ากับสถานะ invoice paid |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items` | array | ปกติ | รายการย่อยใน detail; สูงสุด100รายการ ต้องอ่าน endpoint /items ต่อหากถูกตัด |
| `data.items[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.items[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.items[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.items[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.items[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items_truncated` | boolean | ปกติ | true เมื่อ detail มีรายการย่อยเกิน100และคืนเฉพาะ100รายการแรก |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-36"></a>

### 36. GET /api/v1/delivery-orders/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบส่งของที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `delivery-orders:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/delivery-orders/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "quantity": "10.0000",
      "unit": "pcs",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-37"></a>

### 37. GET /api/v1/payments

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการรายการรับชำระ/กลับรายการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `payments:read`

**เงื่อนไขข้อมูล:** entry_type แยก receipt/reversal และ amount ของรายการ ไม่รวมเป็น net/cash balance เอง. reversal pointer นอก invoice/visibility คืน null ไม่ส่งบัญชีธนาคาร/ช่องทางรับเงิน/secret

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/payments?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "invoice_id": "019a0010-0000-7000-8000-000000000001",
      "entry_type": "receipt",
      "reversal_of_payment_id": null,
      "payment_date": "2026-10-19",
      "amount": "1000.00",
      "currency": "THB",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].invoice_id` | string (uuid) / null | ปกติ | UUID ใบแจ้งหนี้ต้นทาง |
| `data[].entry_type` | string / null | ปกติ | ชนิดรายการรับชำระ receipt หรือ reversal |
| `data[].reversal_of_payment_id` | string (uuid) / null | ปกติ | UUID รายการรับชำระที่ถูกกลับรายการ; null หากไม่มีหรืออยู่นอก invoice/visibility |
| `data[].payment_date` | string (date) / null | ปกติ | วันที่บันทึกการชำระเงิน |
| `data[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-38"></a>

### 38. GET /api/v1/payments/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดรายการรับชำระ/กลับรายการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `payments:read`

**เงื่อนไขข้อมูล:** entry_type แยก receipt/reversal และ amount ของรายการ ไม่รวมเป็น net/cash balance เอง. reversal pointer นอก invoice/visibility คืน null ไม่ส่งบัญชีธนาคาร/ช่องทางรับเงิน/secret

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/payments/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "invoice_id": "019a0010-0000-7000-8000-000000000001",
    "entry_type": "receipt",
    "reversal_of_payment_id": null,
    "payment_date": "2026-10-19",
    "amount": "1000.00",
    "currency": "THB",
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.invoice_id` | string (uuid) / null | ปกติ | UUID ใบแจ้งหนี้ต้นทาง |
| `data.entry_type` | string / null | ปกติ | ชนิดรายการรับชำระ receipt หรือ reversal |
| `data.reversal_of_payment_id` | string (uuid) / null | ปกติ | UUID รายการรับชำระที่ถูกกลับรายการ; null หากไม่มีหรืออยู่นอก invoice/visibility |
| `data.payment_date` | string (date) / null | ปกติ | วันที่บันทึกการชำระเงิน |
| `data.amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-39"></a>

### 39. GET /api/v1/purchase-orders

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบสั่งซื้อที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `purchase-orders:read`

**เงื่อนไขข้อมูล:** received/outstanding อยู่ใน detail.items หรือ /items จาก posted GRN ที่ parent/org/product/unit ตรงกัน; PO header delta ไม่ครอบคลุม receipt-derived changes ต้อง reconcile

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/purchase-orders?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "po_no": "PO-2026-0001",
      "supplier_id": "019a0010-0000-7000-8000-000000000001",
      "status": "approved",
      "order_date": "2026-10-19",
      "expected_date": "2026-10-19",
      "subtotal": "1000.00",
      "discount_amount": "0.00",
      "tax_amount": "0.00",
      "total": "1000.00",
      "currency": "THB",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].po_no` | string / null | ปกติ | เลขที่ใบสั่งซื้อ |
| `data[].supplier_id` | string (uuid) / null | ปกติ | UUID คู่ค้าที่สัมพันธ์กับเอกสาร |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].order_date` | string (date) / null | ปกติ | วันที่ใบสั่งซื้อ |
| `data[].expected_date` | string (date) / null | ปกติ | วันที่คาดว่าจะรับสินค้า |
| `data[].subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data[].total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-40"></a>

### 40. GET /api/v1/purchase-orders/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบสั่งซื้อหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `purchase-orders:read`

**เงื่อนไขข้อมูล:** received/outstanding อยู่ใน detail.items หรือ /items จาก posted GRN ที่ parent/org/product/unit ตรงกัน; PO header delta ไม่ครอบคลุม receipt-derived changes ต้อง reconcile

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

Detailฝังitemsสูงสุด100รายการ ดูitems_truncated; ถ้าtrueใช้/{id}/itemsแบบแบ่งหน้า ไม่ถือว่าdetailมีitemsครบ

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/purchase-orders/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "po_no": "PO-2026-0001",
    "supplier_id": "019a0010-0000-7000-8000-000000000001",
    "status": "approved",
    "order_date": "2026-10-19",
    "expected_date": "2026-10-19",
    "subtotal": "1000.00",
    "discount_amount": "0.00",
    "tax_amount": "0.00",
    "total": "1000.00",
    "currency": "THB",
    "updated_at": "2026-10-19T05:00:00+00:00",
    "items": [
      {
        "id": "019a0010-0000-7000-8000-000000000001",
        "product_id": "019a0010-0000-7000-8000-000000000001",
        "quantity": "10.0000",
        "unit": "pcs",
        "unit_price": "100.00",
        "discount_amount": "0.00",
        "tax_rate": "0.00",
        "line_total": "1000.00",
        "updated_at": "2026-10-19T05:00:00+00:00",
        "received_quantity": "4.0000",
        "outstanding_quantity": "6.0000"
      }
    ],
    "items_truncated": false
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.po_no` | string / null | ปกติ | เลขที่ใบสั่งซื้อ |
| `data.supplier_id` | string (uuid) / null | ปกติ | UUID คู่ค้าที่สัมพันธ์กับเอกสาร |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.order_date` | string (date) / null | ปกติ | วันที่ใบสั่งซื้อ |
| `data.expected_date` | string (date) / null | ปกติ | วันที่คาดว่าจะรับสินค้า |
| `data.subtotal` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมก่อนส่วนลด/ภาษีตามนิยามยอดของเอกสารต้นทาง |
| `data.discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.tax_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินภาษีรวมของเอกสาร |
| `data.total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมสุทธิของเอกสารตามการคำนวณในระบบ |
| `data.currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items` | array | ปกติ | รายการย่อยใน detail; สูงสุด100รายการ ต้องอ่าน endpoint /items ต่อหากถูกตัด |
| `data.items[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.items[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.items[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.items[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.items[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data.items[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data.items[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data.items[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data.items[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items[].received_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณรับจริงจาก posted GRN ที่ org/PO/product/unit ตรงกัน |
| `data.items[].outstanding_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | max(ordered - received, 0) ตามบรรทัด; summary รวมโดยหน่วยเดียวกัน |
| `data.items_truncated` | boolean | ปกติ | true เมื่อ detail มีรายการย่อยเกิน100และคืนเฉพาะ100รายการแรก |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-41"></a>

### 41. GET /api/v1/purchase-orders/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบสั่งซื้อที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `purchase-orders:read`

**เงื่อนไขข้อมูล:** received/outstanding อยู่ใน detail.items หรือ /items จาก posted GRN ที่ parent/org/product/unit ตรงกัน; PO header delta ไม่ครอบคลุม receipt-derived changes ต้อง reconcile

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/purchase-orders/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "quantity": "10.0000",
      "unit": "pcs",
      "unit_price": "100.00",
      "discount_amount": "0.00",
      "tax_rate": "0.00",
      "line_total": "1000.00",
      "updated_at": "2026-10-19T05:00:00+00:00",
      "received_quantity": "4.0000",
      "outstanding_quantity": "6.0000"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].unit_price` | decimal string (2 ตำแหน่ง) | ปกติ | ราคาต่อหน่วยของบรรทัดเอกสาร |
| `data[].discount_amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินส่วนลด ไม่ใช่อัตราร้อยละ |
| `data[].tax_rate` | decimal string (2 ตำแหน่ง) | ปกติ | อัตราภาษีของบรรทัด หน่วยร้อยละ เช่น 7.00 |
| `data[].line_total` | decimal string (2 ตำแหน่ง) | ปกติ | ยอดรวมบรรทัดที่ source จัดเก็บ ไม่ใช่ราคาต่อหน่วย |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].received_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณรับจริงจาก posted GRN ที่ org/PO/product/unit ตรงกัน |
| `data[].outstanding_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | max(ordered - received, 0) ตามบรรทัด; summary รวมโดยหน่วยเดียวกัน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-42"></a>

### 42. GET /api/v1/goods-receipts

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบรับสินค้าที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `goods-receipts:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/goods-receipts?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "grn_no": "GRN-2026-0001",
      "purchase_order_id": "019a0010-0000-7000-8000-000000000001",
      "received_date": "2026-10-19",
      "status": "posted",
      "currency": "THB",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].grn_no` | string / null | ปกติ | เลขที่ใบรับสินค้า |
| `data[].purchase_order_id` | string (uuid) / null | ปกติ | UUID ใบสั่งซื้อ |
| `data[].received_date` | string (date) / null | ปกติ | วันที่รับสินค้า |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-43"></a>

### 43. GET /api/v1/goods-receipts/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบรับสินค้าหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `goods-receipts:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

Detailฝังitemsสูงสุด100รายการ ดูitems_truncated; ถ้าtrueใช้/{id}/itemsแบบแบ่งหน้า ไม่ถือว่าdetailมีitemsครบ

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/goods-receipts/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "grn_no": "GRN-2026-0001",
    "purchase_order_id": "019a0010-0000-7000-8000-000000000001",
    "received_date": "2026-10-19",
    "status": "posted",
    "currency": "THB",
    "updated_at": "2026-10-19T05:00:00+00:00",
    "items": [
      {
        "id": "019a0010-0000-7000-8000-000000000001",
        "purchase_order_item_id": "019a0010-0000-7000-8000-000000000001",
        "product_id": "019a0010-0000-7000-8000-000000000001",
        "quantity": "10.0000",
        "unit": "pcs",
        "updated_at": "2026-10-19T05:00:00+00:00"
      }
    ],
    "items_truncated": false
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.grn_no` | string / null | ปกติ | เลขที่ใบรับสินค้า |
| `data.purchase_order_id` | string (uuid) / null | ปกติ | UUID ใบสั่งซื้อ |
| `data.received_date` | string (date) / null | ปกติ | วันที่รับสินค้า |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items` | array | ปกติ | รายการย่อยใน detail; สูงสุด100รายการ ต้องอ่าน endpoint /items ต่อหากถูกตัด |
| `data.items[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.items[].purchase_order_item_id` | string (uuid) / null | ปกติ | UUID บรรทัด PO ที่บรรทัดรับสินค้าอ้างถึง |
| `data.items[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.items[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.items[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.items[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.items_truncated` | boolean | ปกติ | true เมื่อ detail มีรายการย่อยเกิน100และคืนเฉพาะ100รายการแรก |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-44"></a>

### 44. GET /api/v1/goods-receipts/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบรับสินค้าที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `goods-receipts:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/goods-receipts/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "purchase_order_item_id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "quantity": "10.0000",
      "unit": "pcs",
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].purchase_order_item_id` | string (uuid) / null | ปกติ | UUID บรรทัด PO ที่บรรทัดรับสินค้าอ้างถึง |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-45"></a>

### 45. GET /api/v1/projects

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการโครงการที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `projects:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/projects?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "project_code": "PRJ-001",
      "name": "รายการตัวอย่าง",
      "status": "active",
      "start_date": "2026-10-19",
      "due_date": "2026-10-19",
      "progress_percent": 50,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].project_code` | string / null | ปกติ | รหัสโครงการ |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].start_date` | string (date) / null | ปกติ | วันที่เริ่มโครงการ/ผลิต หรือขอบต้นของช่วงรายงานใน period |
| `data[].due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data[].progress_percent` | integer | ปกติ | เปอร์เซ็นต์ความคืบหน้าโครงการที่จัดเก็บ ไม่ใช่ชั่วโมงทำงาน |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-46"></a>

### 46. GET /api/v1/projects/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดโครงการหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `projects:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/projects/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "project_code": "PRJ-001",
    "name": "รายการตัวอย่าง",
    "status": "active",
    "start_date": "2026-10-19",
    "due_date": "2026-10-19",
    "progress_percent": 50,
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.project_code` | string / null | ปกติ | รหัสโครงการ |
| `data.name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.start_date` | string (date) / null | ปกติ | วันที่เริ่มโครงการ/ผลิต หรือขอบต้นของช่วงรายงานใน period |
| `data.due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data.progress_percent` | integer | ปกติ | เปอร์เซ็นต์ความคืบหน้าโครงการที่จัดเก็บ ไม่ใช่ชั่วโมงทำงาน |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-47"></a>

### 47. GET /api/v1/tasks

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการงานที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `tasks:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | string maxLength=30 | กรอง current status ของ resource string ยาวไม่เกิน30; ค่าไม่ตรงข้อมูลอาจได้[] ไม่ใช่ error |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/tasks?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "project_id": "019a0010-0000-7000-8000-000000000001",
      "title": "เอกสารตัวอย่าง",
      "status": "in_progress",
      "priority": "normal",
      "due_date": "2026-10-19",
      "completed_at": null,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].project_id` | string (uuid) / null | ปกติ | UUID โครงการของงาน; null ได้ตาม source/สิทธิ์ |
| `data[].title` | string / null | ปกติ | หัวข้อของงานหรือชื่อเอกสารตาม resource |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].priority` | string / null | ปกติ | ระดับความสำคัญงาน low/normal/high/urgent |
| `data[].due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data[].completed_at` | string (date-time) / null | ปกติ | วันเวลาที่งานเสร็จ |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-48"></a>

### 48. GET /api/v1/tasks/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดงานหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `tasks:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/tasks/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "project_id": "019a0010-0000-7000-8000-000000000001",
    "title": "เอกสารตัวอย่าง",
    "status": "in_progress",
    "priority": "normal",
    "due_date": "2026-10-19",
    "completed_at": null,
    "updated_at": "2026-10-19T05:00:00+00:00"
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.project_id` | string (uuid) / null | ปกติ | UUID โครงการของงาน; null ได้ตาม source/สิทธิ์ |
| `data.title` | string / null | ปกติ | หัวข้อของงานหรือชื่อเอกสารตาม resource |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.priority` | string / null | ปกติ | ระดับความสำคัญงาน low/normal/high/urgent |
| `data.due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data.completed_at` | string (date-time) / null | ปกติ | วันเวลาที่งานเสร็จ |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-49"></a>

### 49. GET /api/v1/projects/{id}/tasks

**ใช้ทำอะไร:** ดึงรายการงานที่อยู่ในโครงการ UUID ที่ระบุ โดยตรวจสิทธิ์ทั้งโครงการและงาน

**Scopes:** `projects:read + tasks:read`

ใช้ current issuer permission/row ceiling และ party principal. Customer/Supplier keys ไม่ได้อ่านทั้งองค์กร; foreign/missing/hidden detail ไม่แยกเหตุออกจาก404

ต้องมีทั้ง projects:read และ tasks:read; parent visible ยังไม่ให้สิทธิ์ทุก task

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ โครงการ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/projects/019a0010-0000-7000-8000-000000000001/tasks?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "project_id": "019a0010-0000-7000-8000-000000000001",
      "title": "เอกสารตัวอย่าง",
      "status": "active",
      "priority": "normal",
      "due_date": "2026-10-19",
      "completed_at": null,
      "updated_at": "2026-10-19T05:00:00+00:00"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].project_id` | string (uuid) / null | ปกติ | UUID โครงการของงาน; null ได้ตาม source/สิทธิ์ |
| `data[].title` | string / null | ปกติ | หัวข้อของงานหรือชื่อเอกสารตาม resource |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].priority` | string / null | ปกติ | ระดับความสำคัญงาน low/normal/high/urgent |
| `data[].due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data[].completed_at` | string (date-time) / null | ปกติ | วันเวลาที่งานเสร็จ |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-50"></a>

### 50. GET /api/v1/summaries/procurement

**ใช้ทำอะไร:** ดึงข้อมูลสรุปจัดซื้อ

**Scopes:** `summary:procurement:read`

**เงื่อนไขข้อมูล:** Period ตาม PO.order_date/current status/original currency. Current outstanding จาก posted GRN ที่ PO/org/product/unit ตรงกัน; หน่วยเทียบ case-sensitive และ cap100groups ไม่รวมข้ามหน่วย

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/procurement?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "orders_by_status_and_currency": [
        {
          "currency": "THB",
          "count": 1,
          "amount": "1000.00",
          "status": "approved"
        }
      ]
    },
    "snapshot": {
      "as_of": "2026-10-19T05:00:00+00:00",
      "open_order_quantities_by_unit": [
        {
          "unit": "pcs",
          "lines_count": 1,
          "ordered_quantity": "10.0000",
          "received_quantity": "4.0000",
          "outstanding_quantity": "6.0000"
        }
      ],
      "units_truncated": false
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "original_currency",
    "visibility": "organization",
    "freshness": "live"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.orders_by_status_and_currency` | array | ปกติ | PO order_date ในช่วง แบ่ง current status/original currency และรวม total |
| `data.period.orders_by_status_and_currency[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.period.orders_by_status_and_currency[].currency` | string / null | ปกติ | สกุลเงินตามบริบท: เอกสาร=list/detail, original currency=Sales/Procurement, stored base currency=Finance summary; UNSPECIFIED หาก summary ไม่มีรหัส |
| `data.period.orders_by_status_and_currency[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.period.orders_by_status_and_currency[].amount` | decimal string (2 ตำแหน่ง) | ปกติ | จำนวนเงินของ payment หรือยอดรวมกลุ่ม summary ตาม parent field ไม่ใช่ cash balance |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.as_of` | string / null | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `data.snapshot.open_order_quantities_by_unit` | array | ปกติ | ปริมาณ PO สถานะ sent/approved/partially_received ปัจจุบัน แยก exact unit ไม่รวมข้ามหน่วย |
| `data.snapshot.open_order_quantities_by_unit[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.snapshot.open_order_quantities_by_unit[].lines_count` | integer | ปกติ | จำนวนบรรทัด PO ในกลุ่มหน่วย |
| `data.snapshot.open_order_quantities_by_unit[].ordered_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณสั่งซื้อรวมของกลุ่มหน่วย |
| `data.snapshot.open_order_quantities_by_unit[].received_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณรับจริงจาก posted GRN ที่ org/PO/product/unit ตรงกัน |
| `data.snapshot.open_order_quantities_by_unit[].outstanding_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | max(ordered - received, 0) ตามบรรทัด; summary รวมโดยหน่วยเดียวกัน |
| `data.snapshot.units_truncated` | boolean | ปกติ | true เมื่อมีมากกว่า100หน่วยและส่งเพียง100กลุ่ม |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-51"></a>

### 51. GET /api/v1/approvals

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการขั้นอนุมัติที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `approvals:read`

**เงื่อนไขข้อมูล:** เห็นเฉพาะที่ issuer ถูก assign/เคยลงมือ หรือ delegation ที่ยัง active/ตรงชนิดคำขอ; ไม่รับ user_id เปลี่ยน actor ไม่มี approve/reject API. ตรวจ issuer workflows.approve ปัจจุบัน

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `status` | query | ไม่ | enum queued, pending, approved, rejected, revision_requested enum=queued,pending,approved,rejected,revision_requested | กรอง current status ของ resource ตาม enum ที่ระบุ |
| `workflow_instance_id` | query | ไม่ | string (uuid)  | UUID workflow instance ของ approval step; filters ของ relation ต้องเป็น UUID ใน org/visibility ที่อนุญาตตาม endpoint |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/approvals?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "workflow_instance_id": "019a0010-0000-7000-8000-000000000001",
      "step_no": 1,
      "status": "pending",
      "acted_at": null,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "subject": {
        "subject_type": "purchase_order",
        "subject_id": "019a0010-0000-7000-8000-000000000001",
        "status": "pending"
      }
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": false,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].workflow_instance_id` | string (uuid) / null | ปกติ | UUID workflow instance ของ approval step |
| `data[].step_no` | integer | ปกติ | ลำดับขั้นอนุมัติ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].acted_at` | string (date-time) / null | ปกติ | วันเวลาที่มีการลงมือในขั้นอนุมัติ; null หากยังไม่ทำ |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].subject` | object | ปกติ | ข้อมูลอ้างอิงคำขอต้นทางแบบจำกัด fields ไม่ใช่ snapshot/รายละเอียดคำขอ |
| `data[].subject.subject_type` | string / null | ปกติ | ชนิดต้นทาง leave_request/purchase_request/purchase_order/expense |
| `data[].subject.subject_id` | string (uuid) / null | ปกติ | UUID ของคำขอต้นทางตาม subject_type |
| `data[].subject.status` | string / null | ปกติ | สถานะ workflow instance ของ subject ไม่ใช่สถานะขั้น approval แถวนี้ |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-52"></a>

### 52. GET /api/v1/approvals/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดขั้นอนุมัติหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `approvals:read`

**เงื่อนไขข้อมูล:** เห็นเฉพาะที่ issuer ถูก assign/เคยลงมือ หรือ delegation ที่ยัง active/ตรงชนิดคำขอ; ไม่รับ user_id เปลี่ยน actor ไม่มี approve/reject API. ตรวจ issuer workflows.approve ปัจจุบัน

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/approvals/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "workflow_instance_id": "019a0010-0000-7000-8000-000000000001",
    "step_no": 1,
    "status": "pending",
    "acted_at": null,
    "updated_at": "2026-10-19T05:00:00+00:00",
    "subject": {
      "subject_type": "purchase_order",
      "subject_id": "019a0010-0000-7000-8000-000000000001",
      "status": "pending"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.workflow_instance_id` | string (uuid) / null | ปกติ | UUID workflow instance ของ approval step |
| `data.step_no` | integer | ปกติ | ลำดับขั้นอนุมัติ |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.acted_at` | string (date-time) / null | ปกติ | วันเวลาที่มีการลงมือในขั้นอนุมัติ; null หากยังไม่ทำ |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.subject` | object | ปกติ | ข้อมูลอ้างอิงคำขอต้นทางแบบจำกัด fields ไม่ใช่ snapshot/รายละเอียดคำขอ |
| `data.subject.subject_type` | string / null | ปกติ | ชนิดต้นทาง leave_request/purchase_request/purchase_order/expense |
| `data.subject.subject_id` | string (uuid) / null | ปกติ | UUID ของคำขอต้นทางตาม subject_type |
| `data.subject.status` | string / null | ปกติ | สถานะ workflow instance ของ subject ไม่ใช่สถานะขั้น approval แถวนี้ |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-53"></a>

### 53. GET /api/v1/documents

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการเอกสาร DMSที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `documents:read`

**เงื่อนไขข้อมูล:** ต้องมี document_ids allowlist, current issuer permissions และ parent ACL/classification/category guards; ไม่เปิด confidential/payroll/bank documents. Metadata ไม่เท่ากับสิทธิ์ download และไม่มี storage paths

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/documents?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "document_no": "DOC-2026-0001",
      "title": "เอกสารตัวอย่าง",
      "category_id": "019a0010-0000-7000-8000-000000000001",
      "status": "active",
      "updated_at": "2026-10-19T05:00:00+00:00",
      "expires_at": "2026-10-19",
      "current_version": {
        "version_no": 1,
        "mime_type": "application/pdf",
        "size_bytes": 2048,
        "scan_status": "clean"
      }
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].document_no` | string / null | ปกติ | เลขที่เอกสารใน DMS |
| `data[].title` | string / null | ปกติ | หัวข้อของงานหรือชื่อเอกสารตาม resource |
| `data[].category_id` | string (uuid) / null | ปกติ | UUID หมวดหมู่เอกสาร |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].expires_at` | string (date) / null | ปกติ | วันหมดอายุล็อต/เอกสารตาม resource; null หากไม่มี |
| `data[].current_version` | object | ปกติ | metadata เวอร์ชันปัจจุบันของเอกสาร ไม่คืน storage key/current_version_id |
| `data[].current_version.version_no` | integer | ปกติ | เลขเวอร์ชันเอกสาร |
| `data[].current_version.mime_type` | string / null | ปกติ | MIME type ที่เก็บไว้ เช่น application/pdf |
| `data[].current_version.size_bytes` | integer | ปกติ | ขนาดไฟล์เป็น bytes |
| `data[].current_version.scan_status` | string / null | ปกติ | สถานะสแกนไฟล์ เช่น pending_scan/clean/failed; download อนุญาตเฉพาะ clean |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-54"></a>

### 54. GET /api/v1/documents/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดเอกสาร DMSหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `documents:read`

**เงื่อนไขข้อมูล:** ต้องมี document_ids allowlist, current issuer permissions และ parent ACL/classification/category guards; ไม่เปิด confidential/payroll/bank documents. Metadata ไม่เท่ากับสิทธิ์ download และไม่มี storage paths

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ เอกสาร จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/documents/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "document_no": "DOC-2026-0001",
    "title": "เอกสารตัวอย่าง",
    "category_id": "019a0010-0000-7000-8000-000000000001",
    "status": "active",
    "updated_at": "2026-10-19T05:00:00+00:00",
    "expires_at": "2026-10-19",
    "current_version": {
      "version_no": 1,
      "mime_type": "application/pdf",
      "size_bytes": 2048,
      "scan_status": "clean"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.document_no` | string / null | ปกติ | เลขที่เอกสารใน DMS |
| `data.title` | string / null | ปกติ | หัวข้อของงานหรือชื่อเอกสารตาม resource |
| `data.category_id` | string (uuid) / null | ปกติ | UUID หมวดหมู่เอกสาร |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.expires_at` | string (date) / null | ปกติ | วันหมดอายุล็อต/เอกสารตาม resource; null หากไม่มี |
| `data.current_version` | object | ปกติ | metadata เวอร์ชันปัจจุบันของเอกสาร ไม่คืน storage key/current_version_id |
| `data.current_version.version_no` | integer | ปกติ | เลขเวอร์ชันเอกสาร |
| `data.current_version.mime_type` | string / null | ปกติ | MIME type ที่เก็บไว้ เช่น application/pdf |
| `data.current_version.size_bytes` | integer | ปกติ | ขนาดไฟล์เป็น bytes |
| `data.current_version.scan_status` | string / null | ปกติ | สถานะสแกนไฟล์ เช่น pending_scan/clean/failed; download อนุญาตเฉพาะ clean |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-55"></a>

### 55. GET /api/v1/documents/{id}/download

**ใช้ทำอะไร:** ดาวน์โหลดไฟล์เวอร์ชันปัจจุบันของเอกสารที่ได้รับอนุญาต แบบ binary private stream

**Scopes:** `documents:read + documents:download`

**เงื่อนไขข้อมูล:** ต้องมี document_ids allowlist, current issuer permissions และ parent ACL/classification/category guards; ไม่เปิด confidential/payroll/bank documents. Metadata ไม่เท่ากับสิทธิ์ download และไม่มี storage paths

ต้องมีread+download, documentallowlist/issuerACL/currentversionparentตรงและscan_status=clean พร้อมไฟล์อยู่จริง; ถ้าไม่ผ่านใช้403หรือ404ตามจุดตรวจ ไม่คืนsigned/publicURL

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ เอกสาร จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/documents/019a0010-0000-7000-8000-000000000001/download' \
  --header 'Accept: application/octet-stream, application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>' \
  --output downloaded-document.bin
```

**Output สำเร็จ:** HTTP200 เป็น binary file ไม่ใช่ JSON

```http
HTTP/1.1 200 OK
Content-Disposition: attachment; filename="document.pdf"
Cache-Control: no-store, private
X-Content-Type-Options: nosniff
X-Request-Id: 019a0010-0000-7000-8000-000000000001

<binary bytes ของ current version>
```

ชื่อไฟล์/MIMEจริงขึ้นกับ source/storage HTTP layer ไม่รับประกันว่าContent-Typeเป็นapplication/pdfเสมอ ไม่มีdata/metaหรือfileURL ให้ตรวจstatusก่อนเก็บไฟล์ เพราะcurl --output อาจบันทึกJSONerrorเป็นไฟล์ได้ ใช้ `/documents/{id}` อ่านversion_no/mime_type/size_bytes/scan_statusก่อน; ไฟล์อาจเปลี่ยนระหว่างสองrequestจึงตรวจmetadataใหม่ตามนโยบายผู้รับ

**Fields:** ไม่มี JSON fields เมื่อสำเร็จ ข้อมูลอ้างอิงไฟล์อยู่ที่Metadata endpoint Errorยังเป็น JSON code/message/request_id ตาม Error Reference

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-56"></a>

### 56. GET /api/v1/manufacturing/boms

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการสูตรการผลิตที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `manufacturing:boms:read`

**เงื่อนไขข้อมูล:** BOM header ไม่ส่งสูตรโดยอัตโนมัติ /items ต้อง boms:read + formulas:read. costs ต้อง manufacturing:costs:read และ issuer permission ปัจจุบัน; currency=null เพราะไม่มี source currency snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `is_active` | query | ไม่ | enum 0, 1 enum=0,1 | สถานะเปิดใช้งาน ส่ง0หรือ1 |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/boms?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "bom_no": "BOM-001",
      "name": "รายการตัวอย่าง",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "version": "1.0",
      "quantity": "10.0000",
      "unit": "pcs",
      "is_active": true,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "costs": {
        "labor_cost": "0.00",
        "overhead_cost": "0.00",
        "total_cost": "0.00",
        "currency": null,
        "currency_status": "not_recorded_on_source"
      }
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].bom_no` | string / null | ปกติ | เลขที่สูตรการผลิต |
| `data[].name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].version` | string / null | ปกติ | รหัสเวอร์ชัน BOM ตาม source เช่น 1.0 |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].is_active` | boolean | ปกติ | true เมื่อรายการเปิดใช้งาน |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].costs` | object | ตาม additional scope | กลุ่มต้นทุน คืนเฉพาะเมื่อมี manufacturing:costs:read เพิ่ม |
| `data[].costs.labor_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนค่าแรงของสูตร/ใบสั่งผลิต |
| `data[].costs.overhead_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนโสหุ้ยของสูตร/ใบสั่งผลิต |
| `data[].costs.total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data[].costs.currency` | null | ตาม additional scope | null เพราะ source ไม่บันทึก currency snapshot ห้ามสมมติเป็น THB |
| `data[].costs.currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source หมายถึง source ไม่มี currency snapshot จึงไม่สมมติสกุลเงิน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-57"></a>

### 57. GET /api/v1/manufacturing/boms/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดสูตรการผลิตหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `manufacturing:boms:read`

**เงื่อนไขข้อมูล:** BOM header ไม่ส่งสูตรโดยอัตโนมัติ /items ต้อง boms:read + formulas:read. costs ต้อง manufacturing:costs:read และ issuer permission ปัจจุบัน; currency=null เพราะไม่มี source currency snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/boms/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "bom_no": "BOM-001",
    "name": "รายการตัวอย่าง",
    "product_id": "019a0010-0000-7000-8000-000000000001",
    "version": "1.0",
    "quantity": "10.0000",
    "unit": "pcs",
    "is_active": true,
    "updated_at": "2026-10-19T05:00:00+00:00",
    "costs": {
      "labor_cost": "0.00",
      "overhead_cost": "0.00",
      "total_cost": "0.00",
      "currency": null,
      "currency_status": "not_recorded_on_source"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.bom_no` | string / null | ปกติ | เลขที่สูตรการผลิต |
| `data.name` | string / null | ปกติ | ชื่อรายการหรือชื่อ master data นี้ |
| `data.product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.version` | string / null | ปกติ | รหัสเวอร์ชัน BOM ตาม source เช่น 1.0 |
| `data.quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data.unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.is_active` | boolean | ปกติ | true เมื่อรายการเปิดใช้งาน |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.costs` | object | ตาม additional scope | กลุ่มต้นทุน คืนเฉพาะเมื่อมี manufacturing:costs:read เพิ่ม |
| `data.costs.labor_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนค่าแรงของสูตร/ใบสั่งผลิต |
| `data.costs.overhead_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนโสหุ้ยของสูตร/ใบสั่งผลิต |
| `data.costs.total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data.costs.currency` | null | ตาม additional scope | null เพราะ source ไม่บันทึก currency snapshot ห้ามสมมติเป็น THB |
| `data.costs.currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source หมายถึง source ไม่มี currency snapshot จึงไม่สมมติสกุลเงิน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-58"></a>

### 58. GET /api/v1/manufacturing/boms/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของสูตรการผลิตที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `manufacturing:boms:read + manufacturing:formulas:read`

**เงื่อนไขข้อมูล:** BOM header ไม่ส่งสูตรโดยอัตโนมัติ /items ต้อง boms:read + formulas:read. costs ต้อง manufacturing:costs:read และ issuer permission ปัจจุบัน; currency=null เพราะไม่มี source currency snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/boms/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "sub_bom_id": null,
      "quantity": "10.0000",
      "unit": "pcs",
      "waste_percent": "0.00",
      "sequence": 1,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "unit_cost": "0.00",
      "total_cost": "0.00",
      "cost_currency_status": "not_recorded_on_source"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].sub_bom_id` | string (uuid) / null | ปกติ | UUID สูตรย่อยถ้ามี ไม่ส่งสูตรย่อยทั้งก้อนอัตโนมัติ |
| `data[].quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณบรรทัดเอกสาร; สำหรับ BOM header คือ batch quantity ของสูตร |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].waste_percent` | decimal string (2 ตำแหน่ง) | ปกติ | เปอร์เซ็นต์เผื่อสูญเสียของวัตถุดิบ |
| `data[].sequence` | integer | ปกติ | ลำดับแสดงรายการในสูตร |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].unit_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนต่อหน่วยในรายการต้นทาง |
| `data[].total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data[].cost_currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source เช่นเดียวกัน สำหรับ cost fields ของ manufacturing items |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-59"></a>

### 59. GET /api/v1/manufacturing/work-orders

**ใช้ทำอะไร:** ดึงรายชื่อ/รายการใบสั่งผลิตที่ key มีสิทธิ์เห็น แบบแบ่งหน้า

**Scopes:** `manufacturing:work-orders:read`

**เงื่อนไขข้อมูล:** อ่าน stored state ของใบสั่งผลิต ไม่มี plan/release/complete/write actions ผ่าน API. costs และต้นทุน items ต้อง additional manufacturing:costs:read; currency ของต้นทุนไม่มี snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |
| `status` | query | ไม่ | enum draft, planned, in_progress, quality_check, completed, cancelled enum=draft,planned,in_progress,quality_check,completed,cancelled | กรอง current status ของ resource ตาม enum ที่ระบุ |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/work-orders?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "wo_no": "WO-001",
      "bill_of_materials_id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "status": "planned",
      "planned_quantity": "10.0000",
      "actual_quantity": "0.0000",
      "scrap_quantity": "0.0000",
      "start_date": "2026-10-19",
      "due_date": "2026-10-19",
      "completed_date": null,
      "qc_passed": false,
      "updated_at": "2026-10-19T05:00:00+00:00",
      "costs": {
        "material_cost": "0.00",
        "labor_cost": "0.00",
        "overhead_cost": "0.00",
        "total_cost": "0.00",
        "unit_cost": "0.00",
        "currency": null,
        "currency_status": "not_recorded_on_source"
      }
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].wo_no` | string / null | ปกติ | เลขที่ใบสั่งผลิต |
| `data[].bill_of_materials_id` | string (uuid) / null | ปกติ | UUID สูตรการผลิตที่ใบสั่งผลิตอ้างอิง |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data[].planned_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณตามแผนผลิต/แผนวัตถุดิบตาม resource |
| `data[].actual_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณผลิตจริงที่ source จัดเก็บ |
| `data[].scrap_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณสูญเสีย/ของเสียในการผลิต |
| `data[].start_date` | string (date) / null | ปกติ | วันที่เริ่มโครงการ/ผลิต หรือขอบต้นของช่วงรายงานใน period |
| `data[].due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data[].completed_date` | string (date) / null | ปกติ | วันที่ผลิตเสร็จที่จัดเก็บ |
| `data[].qc_passed` | boolean / null | ปกติ | ผลตรวจคุณภาพ true/false; null เมื่อยังไม่มีผล |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].costs` | object | ตาม additional scope | กลุ่มต้นทุน คืนเฉพาะเมื่อมี manufacturing:costs:read เพิ่ม |
| `data[].costs.material_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนวัตถุดิบของใบสั่งผลิต |
| `data[].costs.labor_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนค่าแรงของสูตร/ใบสั่งผลิต |
| `data[].costs.overhead_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนโสหุ้ยของสูตร/ใบสั่งผลิต |
| `data[].costs.total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data[].costs.unit_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนต่อหน่วยในรายการต้นทาง |
| `data[].costs.currency` | null | ตาม additional scope | null เพราะ source ไม่บันทึก currency snapshot ห้ามสมมติเป็น THB |
| `data[].costs.currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source หมายถึง source ไม่มี currency snapshot จึงไม่สมมติสกุลเงิน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

<a id="api-60"></a>

### 60. GET /api/v1/manufacturing/work-orders/{id}

**ใช้ทำอะไร:** ดึงรายละเอียดใบสั่งผลิตหนึ่งรายการตาม UUID ภายใต้สิทธิ์ key

**Scopes:** `manufacturing:work-orders:read`

**เงื่อนไขข้อมูล:** อ่าน stored state ของใบสั่งผลิต ไม่มี plan/release/complete/write actions ผ่าน API. costs และต้นทุน items ต้อง additional manufacturing:costs:read; currency ของต้นทุนไม่มี snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ resource นี้ จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/work-orders/019a0010-0000-7000-8000-000000000001' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "id": "019a0010-0000-7000-8000-000000000001",
    "wo_no": "WO-001",
    "bill_of_materials_id": "019a0010-0000-7000-8000-000000000001",
    "product_id": "019a0010-0000-7000-8000-000000000001",
    "status": "planned",
    "planned_quantity": "10.0000",
    "actual_quantity": "0.0000",
    "scrap_quantity": "0.0000",
    "start_date": "2026-10-19",
    "due_date": "2026-10-19",
    "completed_date": null,
    "qc_passed": false,
    "updated_at": "2026-10-19T05:00:00+00:00",
    "costs": {
      "material_cost": "0.00",
      "labor_cost": "0.00",
      "overhead_cost": "0.00",
      "total_cost": "0.00",
      "unit_cost": "0.00",
      "currency": null,
      "currency_status": "not_recorded_on_source"
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data.wo_no` | string / null | ปกติ | เลขที่ใบสั่งผลิต |
| `data.bill_of_materials_id` | string (uuid) / null | ปกติ | UUID สูตรการผลิตที่ใบสั่งผลิตอ้างอิง |
| `data.product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data.status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.planned_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณตามแผนผลิต/แผนวัตถุดิบตาม resource |
| `data.actual_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณผลิตจริงที่ source จัดเก็บ |
| `data.scrap_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณสูญเสีย/ของเสียในการผลิต |
| `data.start_date` | string (date) / null | ปกติ | วันที่เริ่มโครงการ/ผลิต หรือขอบต้นของช่วงรายงานใน period |
| `data.due_date` | string (date) / null | ปกติ | วันครบกำหนดเอกสาร/โครงการ/งานตาม resource |
| `data.completed_date` | string (date) / null | ปกติ | วันที่ผลิตเสร็จที่จัดเก็บ |
| `data.qc_passed` | boolean / null | ปกติ | ผลตรวจคุณภาพ true/false; null เมื่อยังไม่มีผล |
| `data.updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data.costs` | object | ตาม additional scope | กลุ่มต้นทุน คืนเฉพาะเมื่อมี manufacturing:costs:read เพิ่ม |
| `data.costs.material_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนวัตถุดิบของใบสั่งผลิต |
| `data.costs.labor_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนค่าแรงของสูตร/ใบสั่งผลิต |
| `data.costs.overhead_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนโสหุ้ยของสูตร/ใบสั่งผลิต |
| `data.costs.total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data.costs.unit_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนต่อหน่วยในรายการต้นทาง |
| `data.costs.currency` | null | ตาม additional scope | null เพราะ source ไม่บันทึก currency snapshot ห้ามสมมติเป็น THB |
| `data.costs.currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source หมายถึง source ไม่มี currency snapshot จึงไม่สมมติสกุลเงิน |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-61"></a>

### 61. GET /api/v1/manufacturing/work-orders/{id}/items

**ใช้ทำอะไร:** ดึงรายการย่อยของใบสั่งผลิตที่ระบุ UUID แบบแบ่งหน้า

**Scopes:** `manufacturing:work-orders:read`

**เงื่อนไขข้อมูล:** อ่าน stored state ของใบสั่งผลิต ไม่มี plan/release/complete/write actions ผ่าน API. costs และต้นทุน items ต้อง additional manufacturing:costs:read; currency ของต้นทุนไม่มี snapshot

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `id` | path | ใช่ | string (uuid)  | UUID ของ parent document จากรายการที่ key อ่านได้ ไม่ใช่เลขเอกสาร |
| `limit` | query | ไม่ | integer minimum=1; maximum=100; default=50 | 1–100; ค่าเริ่มต้น50 ต้องคงค่าเดิมเมื่อใช้ cursor ตัวอย่างแสดงหน้าเดียวที่ limit50 |
| `cursor` | query | ไม่ | string maxLength=4096 | opaque next_cursor จากหน้าก่อน อายุ30นาที; ส่งพร้อม filters/limit เดิม ใช้ --data-urlencode ห้ามสร้างเอง |
| `updated_since` | query | ไม่ | string (ตามรูปแบบ)  | UTC YYYY-MM-DDTHH:mm:ssZ แบบ inclusive ไม่ใช่อนาคต; อ่านจาก sync_watermark รอบสำเร็จ และยังต้อง full reconcile |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/manufacturing/work-orders/019a0010-0000-7000-8000-000000000001/items?limit=50' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": [
    {
      "id": "019a0010-0000-7000-8000-000000000001",
      "product_id": "019a0010-0000-7000-8000-000000000001",
      "planned_quantity": "10.0000",
      "issued_quantity": "0.0000",
      "unit": "pcs",
      "updated_at": "2026-10-19T05:00:00+00:00",
      "unit_cost": "0.00",
      "total_cost": "0.00",
      "cost_currency_status": "not_recorded_on_source"
    }
  ],
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "next_cursor": null,
    "limit": 50,
    "consistency": "live",
    "reconciliation_required": true,
    "delta_supported": true,
    "sync_watermark": "2026-10-19T05:00:00Z",
    "cursor_expires_at": "2026-10-19T05:30:00+00:00",
    "visibility": "organization"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data[].id` | string (uuid) | ปกติ | UUID ของรายการนี้ ไม่ใช่เลขเอกสารที่แสดงบนหน้าจอ |
| `data[].product_id` | string (uuid) / null | ปกติ | UUID ของสินค้า/บริการที่สัมพันธ์กับรายการ |
| `data[].planned_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณตามแผนผลิต/แผนวัตถุดิบตาม resource |
| `data[].issued_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณวัตถุดิบที่เบิกจริงตามรายการใบสั่งผลิต |
| `data[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data[].updated_at` | string (date-time) / null | ปกติ | เวลาที่ record นี้แก้ไขล่าสุด ไม่ใช่เวลาที่ parent/ACL/ข้อมูลคำนวณทั้งหมดเปลี่ยน |
| `data[].unit_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนต่อหน่วยในรายการต้นทาง |
| `data[].total_cost` | decimal string (2 ตำแหน่ง) | ตาม additional scope | ต้นทุนรวมของสูตร/ใบสั่งผลิต/บรรทัดตามบริบท |
| `data[].cost_currency_status` | ค่าคงที่ "not_recorded_on_source" | ตาม additional scope | not_recorded_on_source เช่นเดียวกัน สำหรับ cost fields ของ manufacturing items |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference).

<a id="api-62"></a>

### 62. GET /api/v1/summaries/manufacturing

**ใช้ทำอะไร:** ดึงข้อมูลสรุปการผลิต

**Scopes:** `summary:manufacturing:read`

**เงื่อนไขข้อมูล:** Period ตาม WO.created_at แปลง organization timezone; snapshot counts/quantities ปัจจุบัน หน่วยตาม Product.unit ปัจจุบัน cap100. ไม่รวมสูตร/ต้นทุนใน summary และไม่รับรอง historical valuation

**Parameters ที่รับได้เท่านั้น**

| ชื่อ | ตำแหน่ง | จำเป็น | ชนิด / เงื่อนไข | ความหมาย |
| --- | --- | --- | --- | --- |
| `start_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |
| `end_date` | query | ไม่ | string (date)  | YYYY-MM-DD ต้องส่งคู่กัน รวมต้น/ท้าย ไม่เกิน366วัน; ไม่ส่งทั้งคู่=เดือนปัจจุบัน timezone องค์กร |

**ตัวอย่างเรียก**

```sh
curl --get 'https://erp.example.com/api/v1/summaries/manufacturing?start_date=2026-10-01&end_date=2026-10-31' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <INTEGRATION_SECRET>'
```

**ตัวอย่าง Output (HTTP200, ข้อมูลสมมติ)**

```json
{
  "data": {
    "period": {
      "work_orders_created_by_current_status": [
        {
          "status": "planned",
          "count": 1
        }
      ]
    },
    "snapshot": {
      "as_of": "2026-10-19T05:00:00+00:00",
      "active_boms": 1,
      "work_orders_by_status": [
        {
          "status": "planned",
          "count": 1
        }
      ],
      "production_quantities_by_unit": [
        {
          "unit": "pcs",
          "planned_quantity": "10.0000",
          "actual_quantity": "0.0000",
          "scrap_quantity": "0.0000"
        }
      ],
      "units_truncated": false
    }
  },
  "meta": {
    "api_version": "v1",
    "request_id": "019a0010-0000-7000-8000-000000000001",
    "generated_at": "2026-10-19T05:00:00+00:00",
    "period": {
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "timezone": "Asia/Bangkok",
    "currency_mode": "not_applicable",
    "visibility": "organization",
    "freshness": "live",
    "snapshot_as_of": "2026-10-19T05:00:00+00:00",
    "quantity_unit_basis": "current_product_master"
  }
}
```

**Fields ของ data**

| Field path | ชนิด | การปรากฏ | ความหมาย |
| --- | --- | --- | --- |
| `data.period` | object | ปกติ | ข้อมูลที่นับตามช่วงวัน query; ภายใน meta คือขอบช่วง start_date/end_date |
| `data.period.work_orders_created_by_current_status` | array | ปกติ | จำนวนใบสั่งผลิต created_at ในช่วง แบ่ง current status |
| `data.period.work_orders_created_by_current_status[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.period.work_orders_created_by_current_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot` | object | ปกติ | ภาพปัจจุบันขณะอ่าน ไม่ใช่ยอดย้อนหลัง ณ end_date |
| `data.snapshot.as_of` | string / null | ปกติ | เวลาที่อ่าน current snapshot หรือคำนวณ balance นี้ |
| `data.snapshot.active_boms` | integer | ปกติ | จำนวนสูตรผลิต is_active=true ปัจจุบันที่ผ่าน parent/org guards |
| `data.snapshot.work_orders_by_status` | array | ปกติ | จำนวนใบสั่งผลิตปัจจุบันแบ่งสถานะ |
| `data.snapshot.work_orders_by_status[].status` | string / null | ปกติ | สถานะปัจจุบันของรายการ ดูเงื่อนไขเฉพาะ endpoint ไม่ใช้แทนสถานะเอกสารชนิดอื่น |
| `data.snapshot.work_orders_by_status[].count` | integer | ปกติ | จำนวน record ในกลุ่มนั้น; HR กลุ่มเล็กอาจเป็น null เพราะ suppression |
| `data.snapshot.production_quantities_by_unit` | array | ปกติ | planned/actual/scrap รวมของใบสั่งผลิตปัจจุบัน แยก Product.unit ปัจจุบัน ไม่ใช่ historical unit snapshot |
| `data.snapshot.production_quantities_by_unit[].unit` | string / null | ปกติ | หน่วยปริมาณตาม source เช่น pcs; ห้ามรวมปริมาณคนละหน่วย ตัวพิมพ์ต่างกันแยกกลุ่ม |
| `data.snapshot.production_quantities_by_unit[].planned_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณตามแผนผลิต/แผนวัตถุดิบตาม resource |
| `data.snapshot.production_quantities_by_unit[].actual_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณผลิตจริงที่ source จัดเก็บ |
| `data.snapshot.production_quantities_by_unit[].scrap_quantity` | decimal string (4 ตำแหน่ง) | ปกติ | ปริมาณสูญเสีย/ของเสียในการผลิต |
| `data.snapshot.units_truncated` | boolean | ปกติ | true เมื่อมีมากกว่า100หน่วยและส่งเพียง100กลุ่ม |

Metadata ของตัวอย่างนี้ใช้ความหมายตาม [Metadata field dictionary](#2-รูปแบบข้อมูล-สิทธิ์-และ-metadata) และ Errorใช้fieldsกลางสามตัวเท่านั้น

**Errors ที่ต้องจัดการ:** 401 unauthorized; 403 forbidden; 404 not_found; 405 method_not_allowed; 422 invalid_filters; 426 https_required; 429 rate_limited; 500 server_error. ความหมาย/วิธีแก้อยู่ใน [Error Reference](#3-error-reference). List/summaryไม่มีข้อมูลตามสิทธิ์คืน200กับ[]/ค่ารวมว่าง ไม่ใช่404; 404ของURLที่ไม่มีจริงเป็นคนละกรณี

## 6. ตัวอย่างปัญหาและแนวทางตรวจ

| เหตุการณ์ | ผลที่คาด | สิ่งที่ตรวจ |
| --- | --- | --- |
| เรียกemployeesด้วยsummary:hr:readอย่างเดียว | 403 forbidden | ต้องemployees:readและHRbranchpolicyด้วย |
| productsได้ข้อมูลแต่ไม่มีprice/cost | 200 แต่ไม่มีfieldsนั้น | เป็นadditionalscope ไม่ใช่ราคา0 |
| ส่งstart_dateอย่างเดียว | 422 invalid_filters | ต้องส่งend_dateคู่กัน |
| ส่งorg_idหรือinclude=user | 422 invalid_filters | orgมาจากkey ไม่เปิดarbitraryinclude |
| ใช้cursorกับlimit/filter/keyใหม่ | 422 invalid_filters | คงcontextเดิมหรือเริ่มsyncใหม่ |
| ใช้updated_sinceกับapprovals/stockbalances/POitems/employeeoperational | 422 invalid_filters | resourceไม่รองรับdelta ใช้period/fullreconcile |
| ดูemployeeได้แต่leave409 | 409 unsupported_employee_coverage | Employeeไม่มีlinkedUser; ไม่ใช่สิทธิ์ลา0 |
| documentmetadataเห็นแต่download404 | 404 not_found | currentversion/scan/file/ACLอาจไม่พร้อม ยังไม่ถือว่าไฟล์ดาวน์โหลดได้ |
| summaryกับlistนับไม่เท่ากัน | อาจถูกต้องตามcontract | summaryscope/issuerrows/HRpolicy/parent/status/periodbasisต่างกัน ไม่ถือว่าsummaryให้สิทธิ์อ่านทุกrecord |
| POSต้องการส่งรายการขายกลับ | ไม่มีwriteendpointในเอกสารนี้ | POSintegrationเป็นงานออกแบบแยก ไม่ส่งPOSTเข้าreadAPIs |

## 7. เอกสารประกอบและขอบเขตอ้างอิง

- [OpenAPI contract รวม](../docs/openapi/external-read-v1.json)
- [Read API / visibility / sync guide](../docs/EXTERNAL_READ_API.md)
- [Summary metric dictionary](../docs/EXTERNAL_SUMMARY_API.md)
- [Server-side client / receiver examples](../examples/external-api)
- [API architecture / sequence diagrams](22_external_api_architecture.md)

เอกสารนี้เป็นreferenceของreadendpoints ไม่เปิดเผยsecretsและไม่ใช่หลักฐานว่าtargetdeployment/migrations/integration/SLAผ่านแล้ว Webhooksเป็นoutboundsignalสำหรับreceiverและไม่ได้เพิ่มERPwriteendpoint ดูลายเซ็น/verification/retryในReadAPIguide เมื่อต้องแก้fields/scopes/filtersให้ตรวจroutes/controllers/OpenAPIและอัปเดตเอกสารนี้พร้อมกัน
