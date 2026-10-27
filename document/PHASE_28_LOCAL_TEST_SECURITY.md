# Phase 28 — Local-only Connector / Prescription / Pharmacy validation

**ล่าสุด 2026-10-27:** Geminiหมวด17รับรอง local; [acceptance](PHASE_28_LOCAL_ACCEPTANCE.md), [API](PHASE_28_API.md), [schema](PHASE_28_DATABASE.md). ผู้ใช้อนุมัติอัปเดตฐานตามconfigแล้วตาม [upgrade report](DATABASE_UPGRADE_20261027.md); ไม่ขยาย remote/production/real-prescription authorization. หลักฐานทดสอบเดิมด้านล่างเป็นประวัติ.

2026-10-25: ผู้ใช้อนุมัติ “อนุญาตพัฒนาและทดสอบ local เท่านั้น”. เอกสารนี้บันทึกขอบเขตที่ implement และทดสอบได้ ไม่ใช่อนุมัติ remote/production หรือรับรอง e-Tax

## Connector outbound

- `PosTransport` ต้อง APP_ENV=`local`/`testing` และ `pos.local_transport_enabled === true`; default ปิด และ production ปฏิเสธแม้ตั้ง flag
- connection config แยกต่อ instance; รับเฉพาะ numeric loopback HTTPS (`127.0.0.1` / `[::1]`), origin ไม่มี userinfo/query/fragment/path แฝง. ไม่มี DNS resolution หรือ remote origin ที่ payload เลือกได้
- operation/path เป็น allowlist, resource identity ตรวจรูปแบบ, TLS ต้องมี CA bundle/client certificate/private key ที่ provision ใน config. ไม่มี insecure TLS fallback
- ปิด proxy และ redirect; timeout/connect/read/body limits; ขอ identity encoding และปิด decompression เพื่อจำกัด compressed-body abuse
- ทดสอบด้วย Laravel Http fake / preventStrayRequests: ตรวจส่ง TLS options, instance identity, ปฏิเสธ remote/userinfo/traversal/redirect/oversize/production. **ยังไม่ได้เชื่อม HTTPS/mTLS Server จริง**

## ใบสั่งยาจำลอง

- API อยู่ `/local-fixtures/prescriptions`; ต้อง local feature flag + local/testing environment + loopback request + active pharmacist ของสาขา Pharmacy
- upload รับเฉพาะ SHA-256 ใน `pos.synthetic_prescription_hashes`. default ว่าง จึงไม่รับไฟล์ใด ๆ; การทดสอบสร้าง PDF ที่มีข้อความ SYNTHETIC และไม่มีข้อมูลผู้ป่วย
- ตรวจ MIME จากเนื้อหา, ขนาดสูงสุด 5 MiB, private disk ที่ไม่มี public serving, ชื่อ UUID ไม่เก็บ original filename
- เก็บ quarantined ก่อน; scanner default ปฏิเสธเพราะยังไม่ provisioned. เฉพาะ tests ใช้ scanner double เพื่อพิสูจน์ lifecycle/ACL ไม่ใช่หลักฐานว่า malware scan จริงผ่านแล้ว
- ตรวจ branch/role/retention/status/path identity/content hash ก่อนดาวน์โหลด. forced attachment, no-store/nosniff/sandbox พร้อม audit upload/scan/attach/download
- การแนบกับ sale อยู่ใน checkout transaction: quarantined/expired/foreign-branch/tampered file ทำให้ rollback sale/payment/quota/outbox ทั้งกลุ่ม. ใบสั่งยายัง optional
- `prescription_retention_days` default null; 30 วันใน tests เป็น fixture parameter ไม่ใช่นโยบายข้อมูลจริง ไม่มี automatic deletion หรือ patient/visit/history schema

## Pharmacy และ quota recovery

- Pharmacy checkout เปิดเฉพาะ local/testing + `pos.local_pharmacy_enabled`; หน่วยขายเลือกจาก published factor/price/conversion version, เก็บ quantity หน่วยขายและ base quantity แยกกัน
- base step ป้องกันเศษเม็ด และรองรับปริมาตรแบบ decimal; FEFO/expiry/quota ใช้ Core pipeline เดียวกับ Retail
- SKU ที่กำหนดต้องเภสัชกรใช้ authenticated pharmacist session; ERP ตรวจ source pharmacist กับ `pos_staff_authorities` ที่ลงทะเบียนไว้ ไม่รับ browser flag แทน authority. สินค้าทั่วไปไม่ถูกบังคับ gate นี้เพียงเพราะอยู่ร้านขายยา
- ERP product policy เปลี่ยนผ่าน local administration service ที่ตรวจ `pos.manage`/org และ audit; catalogue เก็บ snapshot ไม่เปลี่ยนอัตราแปลงย้อนหลัง
- freeze grant คุมด้วย branch/grant locks และหยุด consume ใหม่; duplicate freeze คืน evidence เดิม
- ERP reclaim service ต้อง active actor ที่มี `pos.manage`+`inventory.adjust`; ขอ proof ผ่าน Connector เอง ไม่รับ counter ที่ caller อ้าง. คืนเฉพาะ unused หลัง counters/watermark/import status ตรงกันและไม่มี integrity conflict. ทั้งสองฝั่งมี local-only recovery flag ปิดเป็นค่าเริ่มต้น
- restore fencing, reservations ของ Restaurant และ MariaDB concurrency ยังต้องทำ/ทดสอบก่อน production; ไม่ถือว่า freeze/reclaim tests ทดแทนได้

## ผลตรวจ

- ERP broad regression: 97 tests / 581 assertions ในรอบก่อนเพิ่ม assertions ของ TLS options
- ERP targeted import/transport: 16 tests / 89 assertions หลังตรวจ TLS options (ชุดนี้ทับซ้อน broad regression ไม่บวกจำนวน)
- POS ล่าสุด: 26 tests / 112 assertions รวม public menu/refunds/precision/production gate/signer rollback; ERP selected regression 82/624 และ focused import/transport หลัง account snapshot + worker recovery 18/140 (ชุดทับซ้อน ไม่บวกจำนวน)
- POS TypeScript/Vite build ผ่าน; browser Retail desktop/mobile รวม cart unit selector ผ่าน ไม่มี JS error
- PHP tests ใช้ SQLite `:memory:`; browser ใช้ SQLite file เฉพาะ temp fixture. ไม่มี migration บนฐานใช้งานจริง ไม่มี real prescription หรือ external provider traffic

## Public menu / Refund local extension — 2026-10-26

- Public menu เปิดเฉพาะ loopback + local/testing + explicit flag. Manager ออก/revoke QR token, DB เก็บ hash, token expire, catalog ใช้ explicit online visibility + public field allowlist. API ไม่มี patient/customer PII/online payment และไม่กัน Stock ก่อน shared checkout
- Staff acceptance/re-review ใช้ current catalog; source request ถูก claim พร้อม sale transaction และ dedupe แม้ checkout request key เปลี่ยน. พนักงานต่างสาขา/role ไม่ผ่าน
- Refund API เปิดเฉพาะ loopback + local/testing + explicit flag. รหัส manager ตรวจใหม่และ approval ผูก exact normalized command/branch/actor/อายุ 5 นาที; re-check active role ใต้ transaction lock
- Partial refund คุม cumulative source quantity และปัดเศษจากยอดต้นฉบับ; conversion/precision ใช้ snapshot เดิมหลัง catalog เปลี่ยน. สินค้าคืนรอตรวจ ไม่เพิ่ม available quota/Stock/COGS กลับเอง
- ERP ตรวจ signed refund + manager registry + original sale, dedupe refund identity, แยก Credit Note/refund Payment และ original payment correlation ใน subledger; retained receipt reversal unique constraint ไม่ถูกผ่อน. ปิด generic ERP reverse/void/CN-DN ที่จะข้าม source workflow
- Ledger copy และ tax report แสดง source gross พร้อม credit adjustment ไม่หักซ้ำ. งวดบัญชีปิดทำ rollback Credit Note/Payment/GL และพัก needs_review; missing original sale เป็น dependency_pending
- Local browser ทดสอบ QR/staff acceptance และ refund workflow ผ่าน viewport 1440/390 ไม่มี JS error. ไม่ใช่หลักฐานว่า public Internet, hardware printer หรือ real fiscal documents ผ่าน acceptance

เพิ่มเติม 2026-10-26: Restaurant โต๊ะ/ครัว/แยกบิล/recipe/waste และ Pharmacy inspection-to-stock ทำ local แล้ว. Inspection ต้อง manager ที่มี pharmacist_qualified ทั้ง POS และ ERP, fresh password, lot/expiry/condition/quantity bounds และ signed event; ERP ตรวจรับก่อนคืน Stock/COGS โดยไม่คืน quota. Browser 1440/390 ผ่าน ไม่มี JS error. ตัวเลือก signing owner ฝั่ง ERP ยังไม่เปิด digital signing/submission

Phase 28 ยัง In Progress: modifiers/restaurant online/table scope, recall/full tax/e-Tax/full reconciliation/restore และ operations ที่เหลือยังไม่ปิด ดู [Work Register](PHASE_28_IMPLEMENTATION_STATUS.md)
