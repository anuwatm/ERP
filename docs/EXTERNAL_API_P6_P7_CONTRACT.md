# P6 / P7 — Manufacturing, documents และขอบเขต read-only

## P6 manufacturing

GET /api/v1/summaries/manufacturing scope summary:manufacturing:read; นับ BOM active และ Work Orders ตาม status และสรุป planned/actual/scrap ตามหน่วยของ Product ไม่รวมต้นทุน/สูตรโดยปริยาย Period ใช้ created_at แปลง timezone; snapshot สถานะปัจจุบัน

GET /api/v1/manufacturing/boms, /boms/{id}, /work-orders, /work-orders/{id}: scopes manufacturing:boms:read / manufacturing:work-orders:read; pagination/scoping/error/no-store เดิม

BOM header = UUID/bom_no/name/product_id/version/quantity/unit/is_active; WO header = UUID/wo_no/BOM/product/status/planned/actual/scrap/start/due/completed/QC boolean ไม่มี notes/people/financial fields

สูตร BOM อ่าน /boms/{id}/items ด้วย manufacturing:formulas:read เพิ่มจาก boms read; ปริมาณเบิก WO /work-orders/{id}/items ใช้ work-orders read; costs ต้อง manufacturing:costs:read explicit เพิ่มจาก resource scope ไม่ใช้ view เป็นสิทธิ์อ่านต้นทุนทั้งหมด ตรวจ product/sub-BOM/warehouse/parent org และไม่ soft-delete ตาม visibility

API อ่าน stored state ไม่รับรองการปิด Phase27 manufacturing ที่ยังมีประเด็นตรวจรับใน gpt.md และไม่เปิด transition/release/complete ผ่าน API

## P6 documents

GET /api/v1/documents, /documents/{id}: documents:read; GET /documents/{id}/download: documents:download เพิ่มจาก documents:read ดาวน์โหลด current version แบบ private stream ตรวจสิทธิ์ใหม่ทุกครั้ง ไม่เผย storage_key หรือ public URLs

Key ต้องเลือก document_ids allowlist ของ org อย่าง explicit; เปลี่ยน allowlist ต้อง rotate; ใช้ issuer active/current permissions และ parent ACL เดิมเป็นเพดาน จำกัดเฉพาะ sensitivity org_internal/department_restricted และ category ที่มี classification ตรงกัน ไม่เปิด hr/finance/executive confidential หรือ links ไป user/bank_account/payment/accounting period ผ่าน API นี้

Metadata: UUID/document_no/title/category_id/status/expires_at/updated_at และ current version number/mime/size/scan_status ไม่ส่งชื่อคน/paths/checksum/internal audit/legal-case details Download เฉพาะ scan_status=clean และ current_version.document_id/org ตรงกัน retention/legal hold คงกฎเดิม ไม่ลบ/แก้เอกสารหรือ version ผ่าน API

ผู้จัดการต้องเลือกเอกสารที่ไม่ใช่ payroll/บัญชีธนาคาร/ข้อมูลส่วนบุคคล ตาม classification และ purpose ของแอป; ไม่ใช้ Document API เป็นทางอ้อมเปิดข้อมูลที่ตัดออกจาก P7

## P7 — ตัดออกตามคำสั่งผู้ใช้ 2026-10-19

- ไม่สร้าง payroll/salary/bank/identity/personal-data API หรือ scope
- ไม่สร้าง Write API ทุกประเภท: ไม่มี create/update/delete/approval/stock/payment ผ่าน integration key
- Admin session จัดการ key/webhook และระบบบันทึก telemetry/outbox ยังทำงานตามหน้าที่เดิม ไม่ใช่ business Write API สำหรับแอปภายนอก
- ทดสอบทุก route ภายใต้ api/v1 ให้มีเฉพาะ GET/HEAD; model field allowlists/OpenAPI ต้องไม่มี salary/bank/identity/private contact fields; webhook payload มีเฉพาะรหัสเหตุการณ์/resource pointer

ข้อสรุปนี้แทนรายการ P7 เดิมใน roadmap ไม่ตีความว่า sensitive/write implementation ทำเสร็จ แต่เป็น scope exclusion ที่ผู้ใช้ตัดสินใจแล้ว
