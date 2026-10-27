# Phase 28 — Local setup / operations — 2026-10-27

Local accepted; configured schemas current. ยังไม่มี POS staff/orders. เอกสารนี้ไม่อนุมัติ production/network/hardware/e-Tax เพิ่ม

## First setup

1. POS อยู่ `pos/`, ERP อยู่ `backend/`; แยก `.env`, APP_KEY, DB, session/staff และ secrets. ไม่คัดลอก ERP credentials ไป POS. Defaults ของ local feature flags ปิด; `POS_RELEASE_APPROVED=false`
2. ใช้ POS Composer dependencies ของแอป. สำหรับ local เท่านั้น สามารถตั้ง `$env:POS_SHARED_VENDOR='1'` เพื่อใช้ backend vendor ตาม bootstrap ปัจจุบัน. Database ตาม config สร้าง/migrate แล้ว ไม่ต้อง migrate:fresh หรือ seed ตัวอย่างลงฐานนี้
3. APP_KEY ต้อง provision เฉพาะติดตั้งใหม่และเก็บปลอดภัย; ห้าม key:generate ทับเมื่อต้องอ่าน encrypted archives เดิม. POS event RSA private key แยกนอก DB; ERP เก็บ public key
4. สร้าง `pos:branch`, `pos:terminal`, `pos:staff`; ผูก staff UUID กับ ERP authority. Pharmacy inspector ต้อง manager + pharmacist qualification
5. Provision instance/warehouse/customer/actor/GL mappings ผ่าน ERP POS Integration แล้ว publish catalog/BOM/quota/issuer profile. Activate เฉพาะหลัง identity/key/mappings ครบ. Pause/revoke ไม่คืน quota
6. Provision loopback HTTPS CA/client cert/scopes แยกตาม instance ก่อนเปิด local transport; ไม่ปิด TLS verification เพื่อให้ทดสอบผ่าน
7. ตั้ง generation/independent recovery anchor ตาม [runbook](../document/PHASE_28_LOCAL_ACCEPTANCE.md#local-provisioning--recovery-runbook). Anchor อยู่นอก DB restore set และ initialize เฉพาะฐานใหม่ไม่มี event

## Commands

| App | Command | Purpose |
| --- | --- | --- |
| ERP | `php artisan pos:sync {instance}` | Outbound publish/poll/import/results/checkpoint; lease + persisted backoff |
| ERP | `php artisan pos:sync {instance} --force` | ข้ามเวลารอ retry; ไม่ข้าม signature/scope/fence |
| POS | `php artisan pos:health` | JSON backlog/age/disk; exit1 ต้องตรวจ |
| POS | `php artisan pos:recovery-initialize` | เฉพาะ new event-empty DB/anchor ยังไม่เคยสร้าง |
| POS | `php artisan pos:hold-checkpoint {checkpoint} {reason}` | Sticky hold ตาม source event identity |
| POS | `php artisan pos:archive-checkpoint {checkpoint}` | Archive accepted/reconciled/ACKed เกิน90วัน ไม่ hold |
| POS | `php artisan pos:restore-event {event}` | คืน original envelope/result จาก private archive ไม่ repost ธุรกิจ |

Backoff เริ่ม5 วินาที สูงสุด300 วินาที; UI แสดง last sync/error/next retry และ checkpoint cutoff. Health defaults 1,000 pending events / 60 นาที / 256 MiB เป็นค่าพัฒนา ต้องกำหนด scheduler/supervisor/alert owner และปรับ threshold จาก load จริงก่อน production

## Backup / restore / recovery

- สำรอง ERP ก่อน schema changes; [backup ล่าสุดและผลตรวจ](../document/DATABASE_UPGRADE_20261027.md). ห้ามเผยแพร่ SQL dump หรือ secrets ใน repository/public docs
- POS backup ต้องมี DB + private archives + APP_KEY/RSA material ตาม ACL. Anchor/generation เป็น independent evidence ไม่ restore ย้อนตาม DB
- หยุดขายเมื่อ source watermark/hash ไม่ตรง anchor; ไม่ล้าง anchor/fence หรือแก้ source identity เพื่อเริ่มขายต่อ
- กู้ต้นฉบับ signed events/archives จนตรง anchor แล้ว ERP admin เก็บ fresh checkpoint chain จาก0ต่อ through ทีละหน้า จนครบ high watermark. ใช้ checkpoint UUID หน้าสุดท้าย+reason เพื่อ replay/ตรวจ ledger/clear fence ตาม original pipeline
- ไม่ครบ evidence หรือยังมี integrity/pending conflicts ต้องคง fence; ไม่มี timeout reclaim/auto-adjust Stock
- Archive ไม่ใช่การลบเอกสาร/dedupe/audit. Hold ติดตาม event แม้ checkpoint ซ้อน/restore. ไม่เปลี่ยน APP_KEY โดยไม่มีแผน decrypt archive เก่า

## Release gates

ก่อน production ต้องมี e-Tax provider/certificate/adapter contract และ acceptance, two-server firewall/mTLS, actual printer/scanner, MariaDB concurrency/load, operational UAT/recovery drill/SLO. QR payment/EDC/mixed tender/offline browser/patient history เป็น deferred scope. อ่าน [ภาพรวมล่าสุด](PHASE_28_POS.md)
