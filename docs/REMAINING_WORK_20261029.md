# งานค้างในขอบเขตเดิม — เริ่ม 2026-10-29 / อัปเดต 2026-10-30

ผู้ใช้ให้ปิดงานในขอบเขตเดิม คง Phase 28.8 deferred. ไม่มีการเพิ่มเวชระเบียน, payment methods ใหม่, membership/promotions หรือ PWA offline.

## สถานะ

- [x] Phase18 polish: one-time recovery UI, validation, setup-secret retry/lifetime และ trusted-device policy — 10 tests / 68 assertions
- [x] Production prep เฉพาะ local code/tooling/runbooks: heartbeat health, deployment templates, Thai PDF fonts, log redaction, backup/restore instructions
- [x] Phase23.1 B01–B08: matrix284PASS/60NO_CREATE/0FAIL; contextual36, coordination8, responsive4, readonly14, DMS12; network/session/upload4PASS รวมlost responseหลังcommitและretry1record
- [x] External API local HTTP: auth/scope/expiry/revocation/read-only/filter9กรณี; browserสร้าง key/แสดงsecretครั้งเดียวผ่าน desktop/mobile; lifecycle replacement/revoke desktop/mobile2PASS
- [x] POS Config Gemini static review เฉพาะ6ไฟล์ที่ผู้ใช้อนุญาต ไม่พบข้อบกพร่องสำคัญสำหรับ Local-only; POS regression45 tests/268 assertions
- [x] Diagram18ไฟล์ showcase9/9 และ containment4desktop sizes ทั้งlight/dark ผ่านครบ
- [x] Understand rescan: working-tree scan/publish พร้อมschema/coverage/source-drift validation; รายละเอียด .ua/analysis-report.json และ document/UNDERSTAND_PIPELINE.md
- [x] Reconcile README/checklist/handoff และเก็บผล JSON/logs ใน document/acceptance/20261030

## หลักฐาน

[รายงาน local acceptance](LOCAL_ACCEPTANCE_20261030.md) · [Production runbook](PRODUCTION_RUNBOOK.md) · [Gemini POS Config](../document/POS_CONFIG_GEMINI_REVIEW_20261029.md) · [Diagram receipts](../document/DIAGRAM_VISUAL_CHECK_20261029.json)

## ยังไม่ได้ทดสอบระบบจริง

รอ e-Tax provider/channel/certificate/contract, merchant/settlement onboarding, target two-server/mTLS/firewall, printer/scanner และ target-load/UAT/DR-owner. ต้องตรวจ storage permissions, HTTPS/cookie/domain และ log/queue/backup บนเครื่องปลายทางก่อนgo-live. หลักฐานlocalไม่ใช่production sign-off และไม่ได้แก้ .env หรือฐานข้อมูลจริงในรอบนี้.
