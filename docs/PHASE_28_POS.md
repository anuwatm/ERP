# Phase 28 — Standalone POS: current implementation

**Local Complete — Gemini PASS (2026-10-27); configured ERP/POS databases current; Production Pending.**

อ้างอิงผลรับรอง [Gemini หมวด 17](../gemini.md#17-ผลการตรวจรับงาน-phase-28-ฉบับสมบูรณ์สำหรับสภาพแวดล้อม-local-phase-28-local-acceptance-sign-off--2026-10-27). สถานะนี้ครอบคลุม local implementation ไม่ใช่ production/e-Tax certification

| พื้นที่ | สถานะปัจจุบัน |
| --- | --- |
| Core / Retail | Cash checkout/change/shift, manager discount, cash in-out, partial/full refund และ void ผ่าน refund pipeline; completed sale immutable |
| Restaurant | Table/takeaway, variant/size/modifier SKU+BOM, public menu/table-session + staff accept, kitchen polling 5 วินาที, split bill, recipe consumption และ waste |
| Pharmacy | Unit conversions/precision, FEFO/lot-expiry, pharmacist authority, synthetic prescription ACL/quarantine, qualified pharmacist manager ตรวจของคืน restock/destroy และ lot recall |
| Tax local | ABB/FULL แยก branch/terminal/date/sequence, trusted issuer/buyer snapshot, replacement/print/reprint; ไม่เพิ่ม sale/VAT ซ้ำ |
| e-Tax | เลือก signing owner ได้; queue `awaiting_provider`. Provider adapter/certificate/sign/submit/accept/reject ยังรอ provider contract |
| ERP connector | ERP outbound เท่านั้น, signature/immutable inbox/dedupe, financial-imported แยก inventory-pending, shared Stock/GL services, bounded polling/lease/backoff |
| Integrity / operations | Signed checkpoint + ledger reconciliation, independent recovery anchor/fence, authorized fresh paged recovery, private encrypted archive/restore และ sticky hold |
| Retention | ออนไลน์ขั้นต่ำ 90 วัน; ย้ายเฉพาะ accepted/reconciled/ACKed events ที่ไม่ hold. เก็บ source/dedupe/audit/pending/review และ archive; ไม่ลบเอกสารอัตโนมัติ |
| Configured databases | ERP MySQL `erp`: 83 applied / 0 pending; POS SQLite: 17 applied / 0 pending. POS ฐานใหม่ยังไม่ provision staff/orders |

## หลักฐานและคู่มือ

- [Local acceptance + provisioning/recovery runbook](../document/PHASE_28_LOCAL_ACCEPTANCE.md)
- [Database upgrade / backup / data verification](../document/DATABASE_UPGRADE_20261027.md)
- [Database inventory / relationships](../document/PHASE_28_DATABASE.md)
- [API / routes](../document/PHASE_28_API.md)
- [Operations](PHASE_28_OPERATIONS.md)
- [Architecture contract](../document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md), [Security](../document/PHASE_28_3_POS_SECURITY.md)
- [Work history](../document/PHASE_28_IMPLEMENTATION_STATUS.md), [Requirements](../document/PHASE_28_1_POS_SCOPE.md), [Checklist](../checklist.md)

## Validation baseline

Gemini: ERP Phase28 **39/348**, POS **41/242**; TypeScript/build/browser ผ่านตามรายงาน. หลังแก้ MySQL identifier lengths และอัปเดตฐาน: Phase28 + ExternalWebhook **46 tests / 422 assertions**, Pint ผ่าน. ชุดทับซ้อน ห้ามบวกจำนวนเข้าด้วยกัน. PHPUnit ใช้ฐานทดสอบแยก; actual configured DB migration/integrity มีหลักฐานใน database-upgrade report

## Production / deferred gates

ยังรอ e-Tax provider/certificate/signing adapter, two-server firewall/mTLS, hardware printer/scanner, MariaDB concurrency/load, UAT/backup recovery drill และ SLO จริง. Production checkout/local feature gates ยังไม่เปิด. QR payment, patient history, card/mixed tender และ browser offline ไม่รวม local cash release นี้
