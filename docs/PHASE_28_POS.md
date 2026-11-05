# Phase 28 — Standalone POS ถึง Phase 28.8.5

**Local Complete ถึง Phase 28.8.5 (2026-11-05)** · [ติดตั้งและตั้งค่า](../document/README_POS.md) · [Config](../document/POS_CONFIGURATION.md) · [Diagrams รายร้าน](../document/24_pos_workflows.html) · [หลักฐานล่าสุด](../document/acceptance/phase28_8_5/README.md)

> **ยังไม่ได้ทดสอบกับระบบและอุปกรณ์จริง** ผล Gemini 2026-10-27 เป็น baseline ของ Phase 28 เดิม

| พื้นที่ | สถานะปัจจุบัน |
| --- | --- |
| Core / Retail | Cash checkout/change/shift, manager discount, cash in-out, partial/full refund และ void ผ่าน refund pipeline; completed sale immutable |
| Restaurant | Table/takeaway, variant/size/modifier SKU+BOM, QR/table session และ staff acceptance, คิวครัว FIFO, Walk-in/จองโต๊ะ 90 นาทีและเผื่อมาสาย 15 นาที, kitchen polling 5 วินาที, split bill, recipe consumption และ waste |
| Pharmacy | Unit conversions/precision, FEFO/lot-expiry, pharmacist authority, synthetic prescription ACL/quarantine, qualified pharmacist manager ตรวจของคืน restock/destroy และ lot recall พร้อมประวัติผู้ป่วยสังเคราะห์เฉพาะ POS สาขา |
| Tax local | ABB/FULL แยก branch/terminal/date/sequence, trusted issuer/buyer snapshot, replacement/print/reprint; ไม่เพิ่ม sale/VAT ซ้ำ |
| e-Tax | เลือก signing owner ได้; queue `awaiting_provider`. Provider adapter/certificate/sign/submit/accept/reject ยังรอ provider contract |
| ERP connector | ERP outbound เท่านั้น, signature/immutable inbox/dedupe, financial-imported แยก inventory-pending, shared Stock/GL services, bounded polling/lease/backoff |
| Integrity / operations | Signed checkpoint + ledger reconciliation, independent recovery anchor/fence, authorized fresh paged recovery, private encrypted archive/restore และ sticky hold |
| Retention | ออนไลน์ขั้นต่ำ 90 วัน; ย้ายเฉพาะ accepted/reconciled/ACKed events ที่ไม่ hold. เก็บ source/dedupe/audit/pending/review และ archive; ไม่ลบเอกสารอัตโนมัติ |
| ส่วนขยาย local | Delivery สำหรับ Retail/Restaurant, สมาชิก/โปรโมชัน, browser offline ตาม permit และคิวตรวจรับ |
| Configured databases | Snapshot ล่าสุด ERP MySQL 88/88 และ POS SQLite 25/25 migrations, pending 0 |

## หลักฐานและคู่มือ

- [Local acceptance + provisioning/recovery runbook](../document/PHASE_28_LOCAL_ACCEPTANCE.md)
- [ฐาน POS 25/25 และ backup](../document/DATABASE_UPDATE_20261105_PHASE28_8_5.md) · [ฐาน ERP 88/88](../document/DATABASE_UPDATE_20261104.md)
- [Database inventory / relationships](../document/PHASE_28_DATABASE.md)
- [API / routes](../document/PHASE_28_API.md)
- [Operations](PHASE_28_OPERATIONS.md)
- [Architecture contract](../document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md), [Security](../document/PHASE_28_3_POS_SECURITY.md)
- [Work history](../document/PHASE_28_IMPLEMENTATION_STATUS.md), [Requirements](../document/PHASE_28_1_POS_SCOPE.md), [Checklist](../checklist.md)

## Validation baseline

Gemini: ERP Phase28 **39/348**, POS **41/242**; TypeScript/build/browser ผ่านตามรายงาน. หลังแก้ MySQL identifier lengths และอัปเดตฐาน: Phase28 + ExternalWebhook **46 tests / 422 assertions**, Pint ผ่าน. ชุดทับซ้อน ห้ามบวกจำนวนเข้าด้วยกัน. PHPUnit ใช้ฐานทดสอบแยก; actual configured DB migration/integrity มีหลักฐานใน database-upgrade report

## Production / deferred gates

ยังรอ e-Tax provider/certificate/signing adapter, two-server firewall/mTLS, hardware printer/scanner, MariaDB concurrency/load, UAT/backup recovery drill และ SLO จริง. Production checkout/local feature gates ยังไม่เปิด. QR payment, card/EDC และ mixed tender ยังเลื่อนไว้ ส่วนประวัติผู้ป่วยและ browser offline ใช้ได้เฉพาะ local ตาม feature gates และ policy ที่กำหนด
