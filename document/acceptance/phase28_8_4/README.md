# Phase 28.8.4 — Local acceptance

สถานะ2026-11-05: **Local Complete / synthetic-only** ตามขอบเขตผู้ใช้ยืนยัน. ยังไม่ได้ทดสอบกับระบบจริง และยังไม่ได้ส่ง Gemini ตรวจโค้ดใหม่.

[ขอบเขต/การใช้งาน/สิทธิ์/diagram](../../PHASE_28_8_4_PATIENT_HISTORY.md) · [อัปเดต configured POS23/23](../../DATABASE_UPDATE_20261105.md)

- POS full regression **108 tests /2,059 assertions**, ไม่ skip: [JUnit](pos.junit.xml). ชุด core targeted ทับซ้อน ไม่บวกจำนวน.
- Chrome **7กรณี**: clinical create/lost-response replay/stale409/append correction/checkout/history/no browser persistence; cashier, unqualified Manager, qualified Manager, other branch, Retail, Restaurant. [ผล](browser-report.json)
- ตรวจ server fixture: patient1/revisions2/visits2/dispensing1/order1/payment1/outbox1/offline inbox0. Clinical fields และ bill referenceเข้ารหัส; auditไม่มีhealth plaintext; signed eventไม่มีpatient identityและsignatureผ่าน. [ผล](server-report.json)
- Chrome IndexedDB/WebCrypto regression **7กลุ่ม** รวมปฏิเสธ structured patient dataก่อนเขียนคิว. [ผล](storage-final.json). เป็นผลรันใหม่ ไม่ใช้ผลเก่าPhase28.8.3แทน.
- TypeScript, Vite build, Pint และ Prettierผ่าน. ไม่มีการรัน ERP acceptance ใหม่ในphaseนี้.
- Fixturesเป็นSQLiteแยกและข้อมูลสังเคราะห์เท่านั้น. Server8147ปิด, staffปิด, sessions/keyสังเคราะห์ถอน; เก็บencrypted clinical/revision/auditไว้ ไม่ลบข้อมูลอัตโนมัติ. [cleanup](cleanup.json)

[ภาพ clinical desktop](clinical-desktop.png) · [ภาพ cashier mobile](cashier-mobile.png) · [source/artifact SHA256 snapshot](manifest.json)

Configured POS เพิ่ม4ตารางว่าง, ไม่ใส่ข้อมูลทดสอบ/ไม่เปิดflags/ไม่แก้environment. นโยบายข้อมูลจริง/retention/key custody/backup restore/clinical privacy approval และอุปกรณ์จริงยังต้องตรวจรับ. ไม่มี automatic diagnosis/allergy/contraindication หรือpatient sync ERP/offline. Manifestเป็นsnapshot ไม่ใช่external Gemini approval.
