# 14 — Documents และไฟล์แนบ

สำรวจจาก source วันที่ 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree ขณะอ่าน
เอกสารนี้อธิบาย implementation; ไม่ได้รัน tests หรือยืนยันบริการ production

## ขอบเขต

เก็บเอกสารกลาง มีหมวดหมู่ รุ่นไฟล์ ความลับ วันหมดอายุ และ retention
เชื่อมเอกสารกับรายการธุรกิจหลายรายการผ่าน DocumentLink
ระบบ StoredFile เดิมยังแยกจาก Document/DocumentVersion และใช้โดยหลักฐานรับจ่าย/OCR

## จุดอ่าน source

- [DocumentController](../../backend/app/Http/Controllers/DocumentController.php): `index`, `store`, `addVersion`, `download`, `mayAccess`
- [DocumentService](../../backend/app/Services/DocumentService.php): `create`, `appendVersion`, `link`, `storeVersion`
- [DocumentParentAccessService](../../backend/app/Services/DocumentParentAccessService.php): `find`, `canAccess`, `canAccessLink`
- [DocumentRetentionService](../../backend/app/Services/DocumentRetentionService.php): `applyPolicy`, `archiveDueDocuments`, `purgeArchivedDocuments`
- [FileController](../../backend/app/Http/Controllers/FileController.php): `download`, `preview`, `authorizeParent`
- [Document model](../../backend/app/Models/Document.php): relation และ casts
- [หน้าจอ](../../backend/resources/js/Pages/Documents/Index.tsx)
- [Routes](../../backend/routes/web.php)

## Routes และสิทธิ์

Routes หลัง staff authentication ใช้ permission เพิ่มตามตาราง

| Method / path | Action | Permission |
|---|---|---|
| GET /documents | index | documents.view |
| POST /documents | store | documents.manage |
| POST /documents/{document}/versions | addVersion | documents.manage |
| POST /documents/{document}/links | storeLink | documents.manage |
| POST /documents/categories | storeCategory | documents.retention.manage |
| POST /documents/retention-policies | storeRetentionPolicy | documents.retention.manage |
| GET /document-versions/{version}/download | download | documents.download |
| GET /files/{file}/download | FileController.download | ตรวจ parent ใน controller |
| GET /files/{file}/preview | FileController.preview | ตรวจ parent ใน controller |

POST เอกสารทั้งหมดมี `password.confirm`, `throttle:10,1`, `EnsureFormSubmission`
`index` paginate 30 รายการและ query ด้วย org_id

## Entities และความสัมพันธ์

- Document: org_id, category_id, owner_user_id, retention_policy_id, current_version_id
- Document มี `versions()` และ `links()` แบบ hasMany
- `currentVersion()` ชี้รุ่นใช้งานล่าสุด ไม่ได้ลบรุ่นก่อนหน้า
- DocumentCategory: sensitivity เริ่มต้นและเปิด/ปิด expiry tracking
- RetentionPolicy: minimum_retention_days, effective_from, legal_hold_required
- DocumentVersion: storage_key, version_no, checksum_sha256, scan_status, uploaded_by
- DocumentLink: linkable_type/id และ role `primary`, `supporting`, `generated`
- Document ใช้ SoftDeletes แต่ retention purge ใช้ forceDelete
- StoredFile: ไฟล์แนบ legacy มี entity_type/id และ storage_key ของตนเอง

## ลำดับ upload/version

1. `store` validate ขนาดไม่เกิน 5 MB และค่าหมวดหมู่ต้องอยู่ org เดียวกัน
2. หมวดหมู่ override sensitivity และกำหนด retention policy
3. หากหมวดหมู่ไม่เปิด expiry tracking จะล้าง expires_at/renewal_alert_days
4. `create` สร้าง Document สถานะ `active` ใน DB transaction
5. `applyPolicy` คำนวณ retention_until จาก expires_at หรือวันที่สร้าง
6. `storeVersion` ตรวจ extension และ MIME แบบจับคู่จริง
7. อนุญาต PDF/JPG/JPEG/PNG/WEBP เท่านั้น
8. SHA-256 ใช้หาไฟล์เดิมภายใน org เพื่อ reuse storage_key
9. เก็บ local disk ใต้ `tenants/{org}/documents/{Y/m}/{uuid}.{ext}`
10. เพิ่ม version_no และตั้ง current_version_id

local/testing ได้ scan_status=`clean`; environment อื่นเป็น `pending_scan`
การมี pending_scan ไม่ได้แปลว่ามี antivirus scanner ทำงานแล้ว ต้องติดตั้งเส้นทางสแกนจริงแยก
DB transaction ไม่ครอบคลุม filesystem แบบ atomic

## Download และ parent visibility

`download` ต้อง org ตรง, scan_status=`clean`, storage มีอยู่ และ `mayAccess` ผ่าน
เจ้าของเอกสารผ่าน sensitivity gate แต่ยังต้องมี documents.download
`org_internal` และ `department_restricted` ผ่าน sensitivity gate โดยไม่มี department comparison ใน method นี้
finance_confidential ต้อง owner หรือ role owner/admin/finance
hr_confidential และ executive_confidential ต้อง owner หรือ role owner/admin
ทุก DocumentLink ต้องเข้าถึง parent ได้ (`every`) ไม่ใช่เพียง link ใด link หนึ่ง
parent ที่รองรับ: customer, deal, supplier, purchase_order, payment, expense, voucher,
project, task, fixed_asset, bank_account, accounting_period, user
customer/deal/project/task มี ownership หรือ membership scope เพิ่ม
`index` เป็น org-wide metadata query; ไม่ได้ใช้ mayAccess กรองรายการเหมือน download

StoredFile ตรวจ org และ parent permission พร้อมตรวจว่า parent อ้าง file id นั้นจริง
รองรับ ocr_extraction/payment/expense/voucher/fixed_asset; type อื่น deny

## Retention และ automation

- failed/infected current scan ทำให้ active → quarantined
- active ที่ retention_until ถึงกำหนดและไม่มี legal_hold → archived
- archived ที่ถึงกำหนดและไม่มี legal_hold จึง purge ได้เมื่อสั่ง --purge ชัดเจน
- purge ลบ links/versions และไฟล์ที่ไม่ถูก document อื่น share
- การ purge มี AuditLog เช่นเดียวกับ archive/quarantine
- [คำสั่ง retention](../../backend/app/Console/Commands/EnforceDocumentRetention.php) เป็นจุดควบคุม dry-run/purge
- schedule ปกติไม่ส่ง --purge จึง quarantine/archive โดยไม่ลบถาวรอัตโนมัติ
- [console schedule](../../backend/routes/console.php): expiry 08:15, retention 08:30
- expiry แจ้ง owner เฉพาะ category ที่เปิด expiry tracking
- [BackfillLegacyDocuments](../../backend/app/Console/Commands/BackfillLegacyDocuments.php) เชื่อมข้อมูลไฟล์เดิมเข้าระบบเอกสาร

## Dependencies และข้อจำกัด

พึ่ง local storage, scheduler, NotificationService, AuditLog และสิทธิ์ของ parent module
Portal และ External API ใช้ document access gate คนละชุด ต้องอ่านเอกสารของแต่ละช่องทางด้วย
ไม่พบ route แก้ไข metadata/ลบเอกสารทั่วไปในชุด routes นี้
legal_hold ป้องกัน archive/purge ตาม service; ไม่ใช่กลไก encryption ของไฟล์

## Tests ที่อ่าน/ตรวจรายการ — ไม่ได้รัน

- [Phase17DocumentManagementTest](../../backend/tests/Feature/Phase17DocumentManagementTest.php): upload/version, pending scan, cross-org, sensitivity, expiry, backfill
- อ่าน assertions upload ได้ clean ใน testing และเพิ่มรุ่นเป็น version_no=2
- อ่าน assertions pending_scan และ cross-org ตอบ 404
- [Phase181DocumentComplianceTest](../../backend/tests/Feature/Phase181DocumentComplianceTest.php): parent visibility, retention/legal hold, quarantine
- การมี test ไม่ใช่หลักฐานว่าผ่านบน environment ปัจจุบัน

