# Phase 28 — Local acceptance handoff — 2026-10-27

สถานะ: Gemini ตรวจรับ Local PASS แล้ว ตาม gemini.md หมวด 17; ไม่ใช่ production sign-off. ใช้เอกสารนี้แทนรายการงานค้างเก่าเฉพาะส่วนที่ระบุว่าทำแล้ว

## ขอบเขตที่ผู้ใช้ยืนยัน

- Local เท่านั้น; ไม่ใช้ prescription/credentials จริงหรือ remote POS. ผู้ใช้อนุมัติอัปเดตฐานตาม config เพิ่มเติมแล้ว ดู DATABASE_UPGRADE_20261027.md
- Retention ออนไลน์อย่างน้อย 90 วัน; archive เฉพาะ ERP accepted + reconciled; เก็บ dedupe/audit/pending/review/hold
- เลข local `ABB-{branch}-{terminal}-{YYYYMMDD}-{8 digits}` และ `FULL-...`; Full แทน ABB อ้าง sale เดิม ไม่เพิ่ม revenue/VAT
- ผู้จัดการที่เป็นเภสัชกรตรวจของคืน เลือก restock/destroy; signing owner เลือก active ERP user ในองค์กรเดียวกัน
- ยังไม่มี e-Tax provider/channel; ไม่สมมติ API/certificate หรือแสดงส่งสำเร็จ

## พัฒนาเพิ่มแล้ว

1. Admin: instance/branch/terminal/warehouse/customer/actor, explicit GL mappings, catalog/BOM/quota publication, staff authority, RSA public-key rotation/revoke, activate/pause พร้อม permission/re-auth/audit. Pause ไม่คืน quota
2. Restaurant: size/modifier combinations เป็น SKU ราคา/BOM ของตนเอง; QR ผูก table session, staff accept ก่อน reserve, dedupe และ kitchen polling ทุก 5 วินาทีไม่ต่อ idle timeout. Prepared sale ชำระหลัง catalog expiry ได้โดยไม่ consume ซ้ำ
3. Recall: ERP hold lot + idempotent publication; POS บล็อก allocation/เตรียม/คืนพร้อมขาย; shared ERP movement guard บล็อก lot recall. Source sale/lot ยังคง trace ไม่มี patient history
4. ภาษี local: trusted issuer profile, ABB ใน checkout transaction, Full buyer fields/replacement, print/reprint, signed tax event และ ERP correlation ไม่มี financial/Stock posting เพิ่ม. Queue `awaiting_provider` ไม่แสดง accepted ปลอม
5. Signed checkpoints ตรวจ envelope hash/sequence/generation/high watermark และ ERP payment/GL/Stock/source correlation; รายงานแสดง cutoff จริง ไม่ ACK เมื่อพบปัญหา
6. Recovery: independent POS anchor นอก DB restore set, fsync/CAS/rollback compensation; DB rollback ทำให้หยุดขาย. ERP fence ห้าม worker posting ปกติ. Admin เก็บ fresh checkpoint chain จาก 0 ถึง high watermark แล้ว replay ผ่าน validation เดิมและตรวจ ledger ก่อนปลด fenceใน transaction. ขาดหน้า/hash conflict/pending ต้อง rollback ไม่ปลดด้วย timeout
7. Archive: accepted outbox เกิน 90 วันย้ายเป็น gzip + authenticated encryption ใน private disk; อ่านกลับ/hash/fsync ก่อนลบ hot row. Metadata/result/dedupe/source documents คงอยู่; restore payload เดิมไม่ post ธุรกิจซ้ำ. Hold ติด event identity ข้าม checkpoint/restore. ไม่ลบ archive อัตโนมัติ
8. Operations: persisted backoff 5–300 วินาที, last success/error/next retry และ lease; `--force` ข้าม backoff ไม่ข้าม fence. `pos:health` ตรวจ backlog/age/disk; defaults เป็นค่าพัฒนา ยังไม่ใช่ production SLO

Cancel ก่อนเตรียมใช้ restaurant cancel; หลัง consume ใช้ waste evidence. Completed sale ใช้ manager-authorized refund/Credit Note อ้างต้นฉบับ ไม่ลบหรือแก้ยอดเดิม. QR payment/patient history deferred ตามขอบเขตเดิม

## ผลตรวจ

- ERP Phase28 **39 tests / 348 assertions**; POS ทั้งชุดหลังเพิ่ม sticky hold **41 / 242**; focused CheckpointTest **1 / 18** (ทับซ้อน ไม่บวกจำนวนชุด)
- ERP selected Finance/GL/Inventory/Manufacturing + recovery regression **80 / 674** (ทับซ้อน Phase28 บางส่วน)
- TypeScript ERP/POS, Vite build ERP/POS ผ่าน; Pint เฉพาะไฟล์ที่แก้
- Browser restaurant online/table/variant/kitchen polling/checkout และ ABB→Full/print/reprint ผ่าน 1440/390 ไม่มี JS errors. หลักฐาน `temp/phase28-pos-browser/restaurant-online-result.json`, `tax-documents-result.json` และ screenshots
- PHP ใช้ SQLite `:memory:`; browser ใช้ synthetic temp SQLite. ไม่พิสูจน์ MariaDB row locks, firewall/TLS สอง Server หรือ printer จริง
- Gemini verdict: **PASS สำหรับ Local Development** ตาม [gemini.md หมวด 17](../gemini.md#17-ผลการตรวจรับงาน-phase-28-ฉบับสมบูรณ์สำหรับสภาพแวดล้อม-local-phase-28-local-acceptance-sign-off--2026-10-27). บันทึก CLI ขัดข้องเดิมเป็นประวัติก่อนผลตรวจรับใหม่นี้

## Local provisioning / recovery runbook

1. POS แยก app/DB/secrets จาก ERP. สร้าง `pos:branch {uuid} {code} {name} --type=...`, `pos:terminal {uuid} {branch} {code}`, `pos:staff` และนำ staff UUID ตั้ง ERP authority ให้ตรง. Inspector ใช้ manager + `--pharmacist-qualified`
2. POS RSA private key อยู่นอก DB; ERP เก็บ public key. ตั้ง instance/branch allowlist/generation ให้ตรง; local feature flags defaults ปิดและ production hard-block
3. ตั้ง `POS_RECOVERY_ANCHOR_PATH` นอกชุด DB restore และ `POS_LOCAL_RECOVERY_FENCING_ENABLED=true`; `pos:recovery-initialize` เฉพาะฐานใหม่ไม่มี event. ห้ามลบ/สร้าง anchor ใหม่เพื่อปลด fence
4. ERP admin ลงทะเบียน/public key/mappings, publish catalog/recipe/quota/tax profile, staff authority แล้ว activate. `pos:sync {instance}` ใช้ loopback transport ที่ provision local-only
5. Backup DB + private archive directory + APP_KEY/key material ด้วย ACL แยก. Anchor/generation เป็น independent evidence ห้าม restore ย้อนพร้อม DB. ไม่เปลี่ยน APP_KEY โดยไม่มีแผนอ่าน archive เก่า
6. Restore: หยุดขายและคง anchor; กู้ signed event/archive/DB จน source hash/sequence ตรง anchor. ขาดหลักฐานคง fence ห้ามสร้าง event/เลขเอกสารใหม่แทน. ERP admin เก็บ checkpoint ใหม่เริ่ม 0 แล้วต่อจาก through หน้าก่อนจน through=high watermark; ใช้ UUID หน้าสุดท้ายพร้อมเหตุผลเพื่อ reconstruct/ตรวจ ledger/ปลด fence. จำกัด 1,000 entries/page และ 1,000 pages/recovery; เกินขอบเขตหยุดเพื่อวางแผนแยก
7. `pos:hold-checkpoint {id} {reason}` sticky hold; `pos:archive-checkpoint {id}` ผ่าน gate เท่านั้น; `pos:restore-event {uuid}` คืน payload/source identity/result เดิมโดยไม่ replay financial posting
8. เรียก `pos:health` ผ่าน supervisor/scheduler ที่มีผู้ดูแลรับ alert; exit 1 ต้องตรวจ. Defaults 1,000 events / 60 นาที / 256 MiB ต้องปรับจาก load/พื้นที่จริง

## External gates

- เลือก e-Tax provider/channel/certificate และ authority ของ signing owner ก่อน implement provider adapter + sign/submit/accept/reject/retry/replacement/cancellation ตาม contract. e-Tax end-to-end ยังไม่เสร็จ
- สอง Server/firewall/mTLS/rotation, MariaDB contention/crash/restart/restore, hardware printer/driver, disaster recovery numbering
- Operational UAT, backup drill, polling/load/SLO และ production tax/legal acceptance ต้องมีผลจริงแยกจาก local

อ้างอิงข้อมูลภาษี: [กรมสรรพากร](https://www.rd.go.th/5208.html), [คู่มือใบกำกับภาษี](https://www.rd.go.th/fileadmin/user_upload/ebook/taxinvoice.pdf). ใช้ออกแบบ local ไม่ใช่รับรองเอกสารหรือ provider

## Configured database update — 2026-10-27

ERP และ POS ไม่มี migration ค้างแล้ว. ERP backup/compatibility fixes/data verification และ POS schema integrity ดู [บันทึกอัปเดตฐานข้อมูล](DATABASE_UPGRADE_20261027.md). ฐาน POS ใหม่ไม่มี staff/orders ต้อง provision ก่อนใช้ขาย.

## Current reference set

[Implementation summary](../docs/PHASE_28_POS.md) · [API/routes](PHASE_28_API.md) · [Database inventory](PHASE_28_DATABASE.md) · [Operations](../docs/PHASE_28_OPERATIONS.md) · [Active checklist](../checklist.md). Historical checklist/work logs และ CLI errors ไม่ใช่สถานะรับรองล่าสุด
