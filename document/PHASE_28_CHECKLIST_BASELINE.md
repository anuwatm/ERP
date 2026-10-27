# Phase 28 — Checklist baseline / history

เก็บรายการเดิมก่อนปรับสถานะ 2026-10-27 เพื่อไม่ให้ requirement/decisions สูญหาย. Checkbox และข้อความงานค้างในเอกสารนี้เป็นประวัติ ไม่ใช่สถานะปัจจุบัน; ให้ยึด [active checklist](../checklist.md), [current implementation](../docs/PHASE_28_POS.md), [Gemini หมวด 17](../gemini.md)

## Phase 28: Standalone POS & ERP Connector — Shared Core + Business Modules [In Progress]

**ล่าสุด 2026-10-27:** ผลพัฒนาและทดสอบ local ให้ยึด [Local acceptance handoff](../document/PHASE_28_LOCAL_ACCEPTANCE.md); Gemini ตรวจรับ local PASS ตาม gemini.md หมวด 17 แล้ว; ฐาน ERP/POS อัปเดตตาม config แล้ว ดู document/DATABASE_UPGRADE_20261027.md. ข้อความ/checkbox เดิมด้านล่างเป็น baseline รวม production acceptance จึงยังไม่ติ๊กทั้ง Phase. Provider e-Tax, hardware, สอง Server, MariaDB concurrency และ UAT ยังเป็น external gates. Retention 90 วันและเลข ABB/Full แบบ branch/terminal/date ได้รับอนุมัติใช้พัฒนา local แล้ว.

เป้าหมาย: POS codebase เดียว ใช้ Core ร่วม + Module เฉพาะกิจการ Retail / Restaurant / Pharmacy พร้อมหน้าจอและ workflow ที่เหมาะกับแต่ละกิจการ. POS ขายหน้าร้านติดตั้งคนละ Server กับ ERP ได้ แยกแอป ฐานข้อมูล บัญชีผู้ใช้ และ secrets โดย ERP Connector เป็นฝ่ายเริ่มเชื่อมออกไปส่ง catalog/ดึงรายการขาย/ส่งผลรับเข้า POS ไม่มีเส้นทางเริ่ม connection เข้าระบบหลักหรือเข้าฐาน ERP โดยตรง

**สถานะ:** อัปเดต requirement ตามคำยืนยันผู้ใช้ 2026-10-24: ร้านค้าทั่วไป + ร้านอาหาร + ร้านขายยา, หลายสาขา/หลายเครื่องต่อสาขา, เงินสด/QR Code, ออกใบกำกับภาษี, เชื่อม Stock ERP และเมนูออนไลน์ผ่าน QR; รุ่นแรกไม่เก็บประวัติผู้ป่วย. คงแนวทางแยก Server และ guardrails จากแผน 2026-10-19 / [Gemini review sections 13.1–13.2](../gemini.md). ผู้ใช้ขอประเมินและอัปเดต checklist เท่านั้น ยังไม่เริ่ม coding/implementation; รายการ `[x]` ในส่วน requirement หมายถึงบันทึกขอบเขตแล้ว ไม่ใช่พัฒนาเสร็จ

**Review เพิ่มเติม:** พิจารณา [Gemini section 14.3](../gemini.md) ทั้ง 5 หัวข้อเป็น design tasks ด้านล่างแล้ว. ข้อเสนอไม่ใช่การยืนยัน requirement เพิ่มโดยผู้ใช้: เลขบิล/จังหวะครัว/precision/retention ยังต้องตกลงรายละเอียด; การยกเลิก QR ไม่ถือว่าป้องกันเงินเข้าหรืออนุญาตให้ทิ้ง valid late webhook และ ผู้ใช้ยืนยัน retention ออนไลน์อย่างน้อย 90 วันแล้ว: archive เฉพาะ accepted/reconciled โดยเก็บ dedupe/audit/pending/review/hold ไม่ใช่ลบเอกสารอัตโนมัติ

### 28.1 ขอบเขตและหลักการที่บันทึกแล้ว

**Requirement ล่าสุด 2026-10-25:** ยึด [คำตอบ D01–D10](../document/PHASE_28_1_POS_SCOPE.md) แทนข้อความเดิมที่ขัดกัน: pilot 1 สาขา 1 PC Terminal, หนึ่งสาขาหนึ่งกิจการ, Stock สาขา/HQ overview, cash-only รุ่นแรก/QR payment ภายหลัง, ABB+Full ออกที่ POS และ e-Tax รุ่นแรก, online ส่งออเดอร์ให้พนักงาน, ร้านอาหารโต๊ะ/กลับบ้าน/จ่ายหลังกิน/จอครัว, Pharmacy กล่อง/แผง/เม็ด/ยาน้ำ เภสัชกรบันทึก สั่งยาหน้าร้าน แนบใบสั่งยา optional ไม่มี patient history. ยืนยันเพิ่มเติม: POS Server ส่วนกลาง, printer, ERP ขาย/เบิก Stock สาขาได้, แยกบิล, takeaway จ่ายตอนรับ; e-Tax provider ยังไม่มีและต้องเลือกใน 28.2; QR-specific design/tests เป็นงานภายหลัง ไม่ใช่ release gate ของรุ่นเงินสด

**สถานะ: Complete — Requirements Baseline 2026-10-25** ดู [Scope & Decision Register](../document/PHASE_28_1_POS_SCOPE.md). ปิดเฉพาะขอบเขต requirement; e-Tax provider/stock concurrency/service extraction ต้องผ่าน design gates 28.2 ก่อนล็อก schema/code/migrations และกิจการ/รุ่นอุปกรณ์นำร่องต้องระบุก่อน UAT

- [x] ยืนยันรองรับกิจการ 3 ประเภท: ร้านค้าขายสินค้าทั่วไป (Retail), ร้านอาหาร (Restaurant) และร้านขายยา (Pharmacy)
- [x] ยืนยันรองรับหลายสาขา และในแต่ละสาขามีเครื่องขาย/Terminal ได้หลายเครื่อง
- [x] ยืนยัน 2026-10-25: รุ่นแรกเงินสดเท่านั้น QR payment เลื่อนภายหลัง; QR เมนูยังอยู่ และยังไม่มี Payment Provider
- [x] ยืนยัน ABB และ Full ออกที่ POS และ e-Tax รุ่นแรก; provider/signing/submission/format/เลขเอกสารต้องออกแบบและทดสอบจริง
- [x] ยืนยันเชื่อมการขายกับ module Stock ของ ERP เพื่อตัดสต็อกสินค้า โดยยังต้องกำหนด warehouse/stock ownership/reservation และจังหวะตัดของแต่ละกิจการ
- [x] ยืนยันมีเมนู/แคตตาล็อกออนไลน์ให้ลูกค้าสแกน QR Code เพื่อเข้าเลือกสินค้า; ยืนยันส่งออเดอร์ให้พนักงานตรวจรับ ไม่มี online payment รุ่นแรก และยาใช้การสั่งหน้าร้าน
- [x] ยืนยันไม่มีประวัติผู้ป่วย; แนบใบสั่งยา optional ได้ในรุ่นแรก เภสัชกรบันทึกและสั่งยาหน้าร้าน
- [x] ผู้ใช้ยืนยันแนวทาง POS codebase เดียว ใช้ Core ร่วม + Module เฉพาะกิจการ Retail / Restaurant / Pharmacy; แยกหน้าจอและ workflow ตาม capability ไม่รวมทุกฟีเจอร์ไว้ในหน้าจอขายเดียว และไม่แยกทำระบบขายซ้ำ 3 ชุด; รายละเอียดโต๊ะ/ครัว/สูตรอาหาร/การจ่ายยายังต้องยืนยัน
- [x] บันทึกแนวทางแยก POS Server + POS Database ออกจาก ERP Server + ERP Database; ไม่แชร์ database credentials, APP_KEY, staff sessions หรือรหัสผ่าน ERP
- [x] บันทึกทิศทาง connection: ERP Connector → POS Sync API เท่านั้น; POS/เครื่องแคชเชียร์ไม่มีสิทธิ์เริ่ม connection เข้า ERP หรือ ERP Database
- [x] คง External API `/api/v1` เดิมเป็น GET/HEAD และไม่มี generic Write API; POS integration เป็น internal import เฉพาะงานที่ ERP Worker ดึงมาตรวจเอง การ POST catalog/ack ไป POS ไม่ใช่เปิด Write API เข้า ERP
- [x] ยืนยัน online POS Server ส่วนกลาง, Stock แยกสาขา/HQ ดูภาพรวม และ ERP ขาย/เบิกได้; ERP polling/ขายต่อระหว่าง ERP outage ภายใต้ allocation contract ที่ต้องออกแบบ; browser offline ภายหลัง สาขาขาด connection ส่วนกลางขายต่อไม่ได้
- [x] Guardrail: เงินเปิดกะ/รับ–จ่าย/กระทบยอด/over-short จำกัดเฉพาะกะ POS ไม่เผยหรืออนุมานเป็น organization-wide Cash Balance ใน Dashboard, Props หรือ API
- [x] Guardrail: การแยก Server ลดช่องทางเข้าถึง แต่รายการจาก POS ยังเป็น untrusted input ต้องตรวจ authority, values และ payment evidence ก่อนบันทึก ERP
- [x] ปิด business-fit gate ตามคำตอบ: pilot 1 สาขา/1 PC/printer, หนึ่งสาขาหนึ่งกิจการ, Stock สาขา ERP ขาย/เบิกได้, cash-only, ABB/Full ที่ POS และ e-Tax รุ่นแรก; e-Tax channel/provider ยังไม่มี ย้ายเป็น gate 28.2 ก่อนล็อก contract/schema; อุปกรณ์จริง/กิจการนำร่องเป็น setup/UAT gate
- [x] ยืนยัน online เลือกสินค้าและส่งออเดอร์ให้พนักงานตรวจรับ; ไม่เพิ่ม self-payment/delivery/สมาชิก และ Pharmacy สั่งยาหน้าร้าน
- [x] ยืนยันร้านอาหาร: โต๊ะและกลับบ้าน, โต๊ะจ่ายหลังกิน, แยกบิล, takeaway จ่ายตอนรับอาหาร, จอ monitor ครัว; ไม่เพิ่มรวมบิลจากคำตอบนี้
- [x] ยืนยัน Pharmacy: กล่อง/แผง/เม็ด/ยาน้ำ เภสัชกรบันทึก สั่งยาหน้าร้าน แนบใบสั่งยา optional; ไม่มี patient history. รายละเอียด ACL/approval/หน่วย/attachment safety ทำใน design

### 28.2 Architecture / Contract ก่อนลงโค้ด

**อัปเดตการอนุมัติ 2026-10-25:** ผู้ใช้อนุมัติ draft 28.2–28.3 เป็นฐานพัฒนา พร้อม quota partition/หยุดขายเมื่อหมดสิทธิ์ และยืนยันทำเฉพาะรุ่นแรก 28.2–28.7; 28.8 ยัง deferred. เริ่ม implementation แล้ว ดู [Work Register / ผลทดสอบ / งานค้าง](../document/PHASE_28_IMPLEMENTATION_STATUS.md). ไม่ปิดทั้ง phase เพราะ e-Tax และ decision/acceptance gates ยังไม่ครบ

**สถานะ 2026-10-25: In Progress — Approved as Development Baseline.** ผู้ใช้อนุมัติ [Architecture & Contract + Service Extraction Plan](../document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md) เป็นฐานพัฒนาแล้ว เริ่ม code/local tests ตาม work register. e-Tax provider/signing owner และ production/operational decision gates ยังไม่ครบ; ไม่ล็อก integration เฉพาะ provider หรือรัน migrations บนฐานใช้งานจริง

- [ ] Gate จาก 28.1 (2026-10-25): เลือก e-Tax channel/provider/signing owner ก่อนล็อก integration/schema; POS Server อยู่ส่วนกลาง และ ERP ขาย/เบิก Stock สาขาร่วมกับ POS ได้ ต้องออกแบบ reservation/quota/locking ที่ทุก writer เคารพ รวม outage/reconnect; ห้ามอ้าง cached stock + polling ป้องกัน oversell ได้เอง และห้ามเปลี่ยนเป็น inbound ERP API โดยพลการ

- [ ] Gemini review gate (2026-10-24): บันทึกผล [Gemini section 15](../gemini.md) เป็น Approved with Conditions เฉพาะแนวทาง; จัดทำและ review Architecture & Contract Document รวม Service Extraction Plan ก่อนลง Application/Connector code หรือ migrations; ยังไม่ถือว่าออกแบบ/ทดสอบ/ผ่าน production แล้ว
- [ ] Service Extraction Plan (Gemini 15.2 ข้อ1): ออกแบบย้าย stock deduction จาก CommercialDocumentController ไปบริการกลาง เช่น `InventoryMovementService` ให้ ERP UI และ POS import ใช้ business rules เดียวกัน; ตรวจ org/warehouse/product/lot, หน่วย/precision, negative-stock guard, moving-average costing, audit และ low-stock notification โดยไม่จำลอง HTTP Request หรือเขียน movement ข้ามบริการ
- [ ] หลังผ่าน design gate จึง extract service และทดสอบ regression เส้นทาง ERP เดิมก่อนสร้าง Connector ที่พึ่งบริการนี้; กำหนด lock บนแถว/คีย์ stock pool ที่มีอยู่จริงและ unique source identity เพื่อ serialize แม้ ledger ยังว่าง ไม่ถือว่า `lockForUpdate()` บน aggregate SUM เพียงอย่างเดียวป้องกัน oversell ได้; ชื่อ service/method สรุปใน design ไม่ใช่ของที่มีแล้ว
- [ ] จัดทำ architecture/sequence/threat model และ data ownership: ERP เป็น master ของสินค้า/ราคา/ภาษี/คลัง/บัญชี; POS เป็นเจ้าของกะ ตะกร้า และหลักฐานรายการขายหน้าร้าน; ระบุกรณีถูกเจาะหรือ credential รั่ว
- [ ] กำหนด deployment แยก repository/release/config หรือ workspace ที่เหมาะสม พร้อม backup/restore และ upgrade compatibility ของสองระบบ โดยไม่บังคับ deploy พร้อมกัน
- [ ] กำหนด org/branch/warehouse/terminal mapping จาก provisioning ฝั่ง ERP ไม่เชื่อ org_id/GL account/สิทธิ์จาก payload
- [ ] ออกแบบ POS core ร่วมสำหรับ 3 ประเภทกิจการ พร้อมแยก capability/workflow และ branch configuration ตามขอบเขตที่ยืนยัน; Terminal และเมนูออนไลน์ในสาขาต้องอ้าง catalog/ราคา/ยอดพร้อมขายชุดเดียวกัน
- [ ] ระบุขอบเขต Shared Core: org/branch/terminal, พนักงาน/สิทธิ์/กะ, catalog/ตะกร้า/ราคา/ส่วนลด/ภาษี, sale/payment เงินสดและ QR, ใบกำกับภาษี/คืนสินค้า, stock reservation และ ERP Connector; ให้ Business Modules เรียกบริการส่วนกลาง ไม่สร้าง payment/tax/stock posting/sync อีกชุดของตนเอง
- [ ] ระบุขอบเขต Business Modules: Retail ดูแลการขายสินค้าทั่วไป, Restaurant เพิ่มเมนู/ตัวเลือก/ครัว/สูตรตาม workflow ที่ยืนยัน, Pharmacy เพิ่มข้อมูลยา/lot/expiry/หน่วยขาย/เภสัชกรตาม policy ที่ยืนยัน; lot/หน่วย/expiry ที่กิจการอื่นต้องใช้ให้ reuse ความสามารถร่วม ไม่ผูกกลไก Stock ไว้เฉพาะ Pharmacy
- [ ] ออกแบบ capability configuration ระดับสาขาร่วมกับคุณสมบัติสินค้า/รายการขาย พร้อมตรวจสิทธิ์และเงื่อนไขฝั่ง server; เช่น สินค้าทั่วไปในร้านขายยาไม่ต้องผ่าน workflow จ่ายยาเพียงเพราะอยู่สาขา Pharmacy. การเปิดหลาย Business Modules ในสาขาเดียวและชุดค่าเริ่มต้นรอยืนยันตาม 28.1
- [ ] กำหนด contract ระหว่าง Core กับ Modules สำหรับ validation, lifecycle, stock demand/consumption และข้อมูลเฉพาะกิจการ; Core คุม transaction/idempotency และผลทางการเงินร่วมกัน ส่วน Module ให้กฎ/ข้อมูลประกอบตาม contract พร้อมเก็บ snapshot ที่ replay ได้
- [ ] จัดหน้าจอขาย/เมนู/สิทธิ์ตาม Modules ที่เปิดใช้ โดยใช้ component/service ร่วมเมื่อเหมาะสม; ปิด Module แล้ว server ต้องไม่รับ operation เฉพาะ Module นั้น และยังอ่านประวัติ/คืนเงิน/กระทบยอดรายการเดิมผ่าน workflow ที่มีสิทธิ์ได้ตาม policy
- [ ] วางลำดับพัฒนา Shared Core + Retail ก่อน แล้วต่อ Restaurant และ Pharmacy ผ่าน contract เดิม; Online Menu/Catalog เป็นช่องทางร่วมที่เลือกแสดงความสามารถตามสินค้า/สาขา ไม่สร้าง checkout/payment/stock pool ซ้ำ และไม่ขยายขอบเขตสั่งซื้อออนไลน์ที่ยังรอยืนยัน
- [ ] กำหนด data ownership ของ online catalog/ตัวเลือกสินค้า/สูตรอาหาร/หน่วยยา/lot และสถานะออเดอร์ให้ชัด; ใช้ BOM/Inventory เดิมเป็นฐานได้เฉพาะส่วนที่ผ่านการตรวจ ห้ามสมมติว่า Work Order เดิมรองรับออเดอร์อาหารแต่ละจานแล้ว
- [ ] ออก versioned POS Sync contract: catalog publish, event polling, pagination/cursor, acknowledgement/result และ reconciliation; ระบุ schema_version/backward compatibility และเพดาน batch/body/timeouts
- [ ] กำหนด UUID/event identity, canonical payload bytes/schema, SHA-256 payload hash ณ transaction บันทึก, source sequence, occurred_at/timezone, immutable sale snapshots, currency/decimal precision และ ERP document correlation; hash ใช้ตรวจ integrity/ID เดิม payload ต่าง ไม่เรียก tamper-proof เพราะผู้โจมตีอาจแก้ทั้ง payload/hash
- [ ] ออกแบบเลขอ้างอิงบิลแยก branch/terminal/ช่วงเวลา เช่น `{BranchCode}-{TerminalID}-{YYYYMMDD}-{Sequence}` ตาม Gemini 14.3 ข้อ1; ออกเลขจาก POS Server ด้วย durable sequence และ atomic lock/unique constraint ภายใน scope ที่จำเป็น ไม่เชื่อ browser counter และไม่อ้างว่ารูปแบบ prefix ทำให้ไม่ต้องใช้ lock ทุกกรณี
- [ ] แยก UUID/source identity ออกจากเลขบิลที่แสดง; กำหนด timezone/การเปลี่ยนวัน, retry, หลาย tab ใน Terminal เดียว, terminal reprovision และ backup restore ให้ไม่ใช้เลขซ้ำ พร้อมแยก policy เลขใบกำกับภาษีที่ต้องยืนยันจากเลขบิล POS
- [ ] กำหนด sync interval/ความสดของ catalog/price/stock และ recovery objectives จากความต้องการจริงก่อนรับรอง real-time/SLA

### 28.3 Network / Authentication / Security

**สถานะ 2026-10-25: In Progress — Local Security Implementation.** ดู [Network/Auth/Security Plan](../document/PHASE_28_3_POS_SECURITY.md) และ [Local-only validation](../document/PHASE_28_LOCAL_TEST_SECURITY.md). ทำ staff/branch/scope/transport/fixture prescription guards และ synthetic accounts/tests แล้ว ยังไม่ติดตั้ง firewall/ออก credentials จริงหรือพิสูจน์ mTLS สอง Server; ไม่ติ๊ก production acceptance จาก local tests

- [ ] วาง ERP private network และ firewall แบบ default deny: public/POS zone เข้า ERP/DB ไม่ได้ อนุญาต Connector outbound เฉพาะ POS host/port ที่กำหนด; แยกสิทธิ์ deployment/admin ของแต่ละระบบ
- [ ] ออกแบบ HTTPS และ service authentication ของ Connector กับ POS เช่น mTLS พร้อม credential แยกต่อ instance/org และ scope เฉพาะ catalog/events/results; กำหนด rotation/revoke/expiry โดยไม่ใช้ ERP admin key
- [ ] เก็บ POS credentials ที่ ERP Connector และเก็บเพียง identity ของ Connector ฝั่ง POS; ไม่มี credential ที่ POS ใช้ login/query ERP ได้ ไม่ฝัง secrets ในแคชเชียร์ browser
- [ ] สร้างบัญชีแคชเชียร์/manager แยกจาก ERP พร้อมสิทธิ์สาขา/terminal, session policy และสิทธิ์ส่วนลด/void/refund/เปิด–ปิดกะ
- [ ] กำหนดสิทธิ์พนักงานร้านอาหาร/ครัว/เภสัชกรตาม workflow ที่อนุมัติ และแยก public online menu ออกจาก staff session; ไม่เปิดข้อมูลต้นทุน/ข้อมูลภายในผ่าน QR ลูกค้า
- [ ] QR เมนูใช้ public token ที่ผูกสาขา และผูกโต๊ะ/session เฉพาะเมื่อยืนยันใช้โต๊ะ; รองรับ revoke/rotation ตรวจ scope ฝั่ง server และป้องกันแก้ branch/table/ราคาใน browser เพื่อเข้าถึงหรือส่งรายการข้ามสาขา
- [ ] กำหนด POS destination จาก admin allowlist ป้องกัน SSRF/redirect/private-network pivot ด้วย fixed paths, DNS/IP validation, TLS verification และ time/response limits
- [ ] จำกัด fields ที่ส่ง POS เฉพาะ catalog/ราคา/ภาษี/stock allocation และข้อมูลอ้างอิงจำเป็น; ไม่ส่ง HR/payroll/bank/national_id/ข้อมูล ERP ทั้งองค์กรหรือต้นทุนโดยปริยาย
- [ ] ออกแบบ authenticated event integrity: signature/HMAC ผูก instance/event_id/schema_version/sequence/payload hash พร้อม key rotation/verification และ immutable ERP-received evidence; จำกัดสิทธิ์กุญแจแยกจาก POS DB และระบุว่าถ้า POS host/key ถูกยึดยังปลอม events ได้ ต้องตรวจธุรกิจ/payment evidence อิสระ (Gemini 13.2 ข้อ5)
- [ ] Audit provisioning, configuration, key lifecycle, cashier actions และ import decisions; redact secrets/payment credentials/PII พร้อม rate limits และ replay/deduplication policy

### 28.4 Schema / POS และพื้นที่พักรับเข้า ERP

**Implementation บางส่วน 2026-10-25:** เพิ่ม workspace `pos/` แยก DB/config/session/staff, ERP entitlement/instance/catalog/inbox/conflict/source links และ atomic financial/inventory stages พร้อม local tests. ยังไม่รัน migrations บนฐานจริง; ไม่ถือว่า schema ของ Restaurant/Pharmacy หรือ retention ครบแล้ว ดู work register

- [ ] POS schema: `pos_terminals`, `pos_shifts`, `pos_orders`, `pos_order_items`, `pos_payments`, catalog/price snapshots และ transactional outbox (ชื่อสุดท้ายยืนยันใน schema design)
- [ ] ออกแบบข้อมูล branch capabilities, terminal identity, ช่องทางรายการขาย (หน้าร้าน/ออนไลน์), ตัวเลือกสินค้า/หน่วยขาย และ source correlation ให้รองรับทั้ง 3 กิจการ; schema ออเดอร์ออนไลน์/โต๊ะ/ครัว/สูตร/lot allocation ต้องตาม workflow ที่ยืนยัน ไม่สร้าง patient/visit/medical-history tables ในรุ่นแรก
- [ ] แยกตัวตน Terminal, กะขาย และ cashier; stock reservation/ยอดพร้อมขายระดับสาขาต้องใช้ร่วมกันข้ามเครื่องและ online channel พร้อม concurrency control
- [ ] ERP schema: registered POS instances/mappings, integration inbox/checkpoints, import results และ source-to-ERP document links แยกจากตารางขายที่ผ่าน validation แล้ว
- [ ] บังคับ unique `(pos_instance_id, event_id)` และ source-order identity ป้องกันขาย/รับเงิน/ตัดสต็อกซ้ำ แม้ retry หรือใช้ event_id ใหม่กับ order เดิม
- [ ] บันทึก sale + payment references + outbox ภายใน POS transaction เดียวกัน; completed sale immutable เปลี่ยนด้วย void/refund events อ้างต้นฉบับ
- [ ] เก็บ inbox ก่อนเลื่อน durable checkpoint; transaction ของแต่ละ import stage ต้องผูกเอกสาร/GL/payment/stock ที่ stage นั้นรับรองกับ import status/unique source links แบบ atomic; แยก financial_imported กับ inventory_pending เมื่อสต็อกขัดแย้ง ห้ามใช้ imported=true กลบงานค้างหรือ retry แล้วลงบัญชีซ้ำ
- [ ] Dead-Letter Queue (DLQ) ฝั่ง ERP รองรับ parse/schema/master-data/terminal failures, retry limits และ Admin UI ดู reason/attempts; เก็บ original event/hash แบบจำกัดสิทธิ์+retention และไม่ log raw PII; malformed event ไม่ค้างทั้ง batch, replay ต้องผ่าน validation/idempotency เดิม ไม่แก้ event ต้นฉบับ (Gemini 13.2 ข้อ5)
- [ ] กำหนด POS retention/archive/pruning ตาม Gemini 14.3 ข้อ5 โดยแยก logs/payload/ไฟล์แนบ/transaction evidence และ dedupe keys; ผู้ใช้ยืนยันขั้นต่ำ 90 วันแล้ว; local archive/restore/hold ทำและทดสอบแล้วตาม handoff ล่าสุด. พื้นที่และ restore drill จริงยังรอ acceptance; ไม่ลบข้อมูลจริง
- [ ] ก่อน prune ต้องมี durable ERP receipt, sync/reconciliation สำเร็จ, archive/restore ที่ตรวจแล้ว และไม่มี inventory_pending/payment review/refund/dispute/hold ค้าง; เก็บ source identity/hash/payment references หรือ tombstone ที่จำเป็นต่อ dedupe ตาม window ที่ตกลง และไม่ลบเอกสารต้นฉบับที่ยังต้องเก็บเพียงเพราะพ้นอายุ payload

### 28.5 Catalog / Sales Sync และ Recovery

- [ ] จัดทำ Event-to-ERP Mapping ต่อ sale/payment/stock/recipe/refund/void/waste: ระบุเอกสารปลายทาง, service owner, transaction boundary, source line/lot, dedupe key, ordering และ recovery; Connector ใช้บริการที่ผ่าน Service Extraction Gate ใน 28.2 ไม่เรียก Controller หรือสร้าง StockMovement ข้ามกฎ
- [ ] Single Stock Posting Path (Gemini 15.2 ข้อ2): เสนอ `pos_sale` สำหรับขายสินค้าทั่วไปและ `recipe_consumption` สำหรับวัตถุดิบตามสูตร; เลือกตามผลกระทบของแต่ละ line ไม่ตัดสินค้าหรือวัตถุดิบเดียวกันผ่านทั้งสองเส้นทางซ้ำ. ชื่อ movement types ต้องตรวจ schema/รายงาน/valuation compatibility ก่อนยืนยัน
- [ ] หากสร้าง Delivery Order อ้างอิงบิล POS ให้ link กับ stock posting เดิมและป้องกัน delivered transition ตัดซ้ำด้วย source correlation/guard ฝั่ง server; ไม่อาศัยเพียง flag จาก POS และต้องคงพฤติกรรม DO ของ ERP เดิม พร้อมทดสอบ retry/เปลี่ยนสถานะ/คืนสินค้าร่วมกัน
- [ ] ERP ส่ง catalog allowlist ของสาขา: SKU/Barcode/name/unit/ราคา/ภาษี/status และ price version/validity; เพิ่ม online visibility/ตัวเลือก/หน่วยแปลง/lot-expiry หรือ recipe version เฉพาะที่จำเป็นและยืนยัน ownership แล้ว; initial bootstrap + incremental updates + deletion/disable reconciliation
- [ ] POS เก็บ catalog ที่พร้อมขายในฐานตัวเอง พร้อม last-sync/stale indication; กำหนด policy กรณีราคาเปลี่ยน สินค้าถูกปิด หรือ catalog เกินอายุ
- [ ] ERP Worker poll events แบบ bounded cursor จาก POS ลง inbox พร้อม retry/backoff และ lease/locking ป้องกัน worker ซ้อน; ห้าม POS call back เข้า ERP
- [ ] ตรวจ branch/terminal/product/unit/price version/discount permissions/tax/currency/ยอดรวม/เวลา และ payment evidence ฝั่ง ERP โดยใช้ price-policy snapshot ที่อนุมัติ ไม่รับ client ตั้ง GL/status paid เอง
- [ ] เรียก ERP business services เดิมเพื่อสร้างเอกสาร ไม่ direct SQL ข้าม validation/permissions/stock/accounting rules
- [ ] Stock Discrepancy: ไม่ทิ้งบิลขายจริงเพราะ stock ERP ไม่พอ; หลังผ่าน identity/ธุรกรรม/หลักฐานชำระแล้ว ให้รับส่วนการเงินตาม policy และสร้าง `needs_review: inventory_discrepancy` + Pending Stock Adjustment แจ้ง Inventory Manager; ห้าม auto-adjust/ยอม stock ติดลบ/ข้าม domain guards; payload ปลอมยังต้อง quarantine/reject พร้อมเหตุผล (Gemini 13.2 ข้อ2)
- [ ] กำหนด state machine ของ inventory_pending → reviewed/adjusted → inventory_posted พร้อม manager authorization, valuation/COGS policy และ idempotent stock/GL completion; ระหว่างรอรายงานต้องแสดงต้นทุน/สต็อกที่ยังไม่ครบ ไม่สมมติ COGS=0; ห้ามโพสต์ stock/COGS ซ้ำเมื่อ replay
- [ ] ส่งผล `accepted / rejected / needs_review` พร้อม reason code/ERP document ID กลับ POS หลัง commit; ack ซ้ำได้ ไม่ให้ ack failure ทำให้ import ซ้ำ
- [ ] รองรับ at-least-once, duplicate/out-of-order, missing events, crash-before/after-commit และ event payload เปลี่ยนทั้งที่ ID เดิม; รายการขัดแย้งพักตรวจแทนโพสต์ซ้ำ
- [ ] มี full reconciliation ต่อ instance/branch/ช่วงวัน เทียบ sales/payments/refunds/stock movements และติดตามรายการตกหล่นโดยไม่ใช้ยอดกะเป็นยอดเงินสดองค์กร
- [ ] เทียบ stock ตาม product/warehouse/unit และ lot เมื่อมี; recipe/unit conversion ที่ใช้ขายต้องเก็บ snapshot/version เพื่อให้ replay/refund ไม่ใช้สูตรหรืออัตราแปลงปัจจุบันแทนรายการเดิม

### 28.6 Checkout / Shift / Payment / Stock

- [ ] Release v1: cash-only, ABB/Full ออกที่ POS และ e-Tax signing/submission พร้อม durable pending/retry/rejection/status; เลือก provider/channel/key ownership และทดสอบ end-to-end ห้ามใช้ XML/PDF/mock เดิมแทนหลักฐาน live; ไม่เปิด inbound เข้า ERP
- [ ] Release v1: แนบใบสั่งยา optional โดยเภสัชกร พร้อม validation/quarantine/private storage/ACL/audit/retention ไม่สร้าง patient history หรือเปิดสั่งยาออนไลน์

- [ ] Direct POS Sale GL Contract (Gemini 15.2 ข้อ3): ประเมินเพิ่ม `FinancialJournalService::postPosSale()` สำหรับบิลที่ชำระครบ โดย debit drawer/QR clearing และ credit sales/output VAT ตาม account mapping ขององค์กร; เลข 1111/1113/4100/2130 เป็นตัวอย่าง ไม่ hard-code และไม่ใช้ fallback 1100 โดยไม่ตรวจความหมายบัญชี
- [ ] เลือก posting mode เดียวต่อบิล: direct paid sale หรือ Invoice/Payment ผ่าน AR ตาม contract; ถ้าเลือก direct sale ต้องไม่เรียก postInvoice/postPayment ให้ลงรายได้/ภาษี/รับเงินซ้ำ และยังต้องมี document/subledger linkage สำหรับรายงาน/refund/void. รายการยังไม่ชำระหรือจ่ายภายหลังต้องออกแบบ recognition/settlement แยก ไม่บังคับใช้ direct paid sale ทุกกรณี
- [ ] Suspended Gateway Receipts (Gemini 15.2 ข้อ4): ออกแบบ durable inbox/สถานะพักเงินที่ยืนยันแล้วแต่ apply ไม่ได้ เช่น `suspended_gateway_receipts` พร้อม unique provider reference, evidence, review/refund/reconcile lifecycle และแจ้งเตือน manager/accounting ตามสิทธิ์; ชื่อตารางเป็นข้อเสนอ ต้องตรวจ reuse ก่อนล็อก schema
- [ ] กำหนดการลงบัญชีเงินรับเกิน/ไม่ทราบบิลตามหลักฐาน provider และ mapping ที่ฝ่ายบัญชีตรวจรับ: ตัวอย่าง debit gateway clearing / credit suspended-receipt liability (2190 เป็นเพียงตัวอย่าง); ห้ามสร้างรายได้/ตัด AR บิลที่ชำระแล้วซ้ำ หรือ debit clearing ซ้ำหาก settlement เดิมบันทึกไว้แล้ว. Refund/reclass ต้องมี source linkage/idempotency และ audit ไม่คืนเงินอัตโนมัติจาก webhook
- [ ] Touch-friendly checkout, barcode lookup, quantity/ส่วนลดที่มีสิทธิ์, tax calculation, พิมพ์ thermal receipt และเลขอ้างอิงการขายไม่ซ้ำ
- [ ] Tax Invoicing: ออกใบเสร็จ/ใบกำกับภาษีตามรายการขาย พร้อมข้อมูลผู้ซื้อและเลขเอกสารที่ไม่ชนกันระหว่างสาขา/เครื่อง; ยืนยันชนิด ABB/Full Tax และนโยบายเลขเอกสารก่อนพัฒนา เก็บ source sale เดียว, original/replacement links และ void/replace events; การเปลี่ยนรูปเอกสารไม่สร้างรายได้/Output VAT ซ้ำหรือทำลายประวัติ (Gemini 13.2 ข้อ1)
- [ ] กำหนดการออกเอกสารเมื่อ ERP offline และข้อมูลผู้ซื้อที่จำเป็นต่อใบกำกับภาษี โดยแยกจากประวัติผู้ป่วย; e-Tax live submission/signing อยู่ในรุ่นแรกตามคำยืนยัน 2026-10-25 ไม่ใช้การมี XML/PDF เป็นหลักฐานว่าพร้อมส่งจริง
- [ ] เปิดกะ/เงินตั้งต้น/Cash In-Out/ปิดกะ/ยอดนับจริง/over-short พร้อม approval/audit ของรายการปรับปรุง
- [ ] รองรับเงินสดและ QR Code: เงินสดมียอดรับ/เงินทอน, QR ผูก Payment Intent/ยอดบิล/สาขาและ expiry; แยก cashier-declared cash จาก provider-verified payment และสถานะ pending/confirmed/failed/refunded; split/mixed tender รอยืนยัน ไม่เพิ่ม Card/EDC ในรุ่นแรก
- [ ] ออกแบบการเปลี่ยน QR → เงินสดตาม Gemini 14.3 ข้อ4 ด้วย order/payment lock หรือ version guard, idempotency และการหยุดใช้ intent เดิม; ตรวจ provider status/cancel capability ตาม contract ก่อนรับเงินสด หากผลยกเลิกยัง unknown ให้แสดงสถานะรอตรวจ/ใช้ exception policy ที่อนุมัติ ไม่อ้างว่ายกเลิกบนหน้าจอแล้วเงินเข้าไม่ได้
- [ ] รับ ตรวจ และเก็บ valid late webhook ของ intent ที่ขอยกเลิกแล้วต่อไป โดย dedupe provider reference และเทียบยอด/merchant/currency; ถ้าเงินสดรับแล้ว ให้เข้า double-payment/needs_review พร้อมกระบวนการคืนเงินหรือกระทบยอดที่มีสิทธิ์ ไม่สร้างยอดขาย/stock ซ้ำ และไม่ทิ้งหลักฐานเงินเข้าจริงด้วยการ reject webhook อัตโนมัติ
- [ ] กำหนด provider/merchant ownership และ verify provider_transaction_id/settled_amount/currency/merchant/status กับหลักฐาน gateway/acquirer ที่เชื่อถือได้; unique payment reference ป้องกันใช้รายการเดียวจ่ายหลายบิล; แยก gross/fees/net/partial/reversal และ pending verification; ไม่เชื่อรูป QR/สลิป/browser flag และไม่เก็บ PAN/CVV (Gemini 13.2 ข้อ3)
- [ ] Clearing Accounts แยก drawer cash กับ QR ตาม account mapping ขององค์กร (ต้องตรวจผังบัญชีจริง ไม่ hard-code); digital receipts พัก clearing จนตรวจ settlement/statement ก่อนย้ายเข้า bank, fees/refunds/partial settlement ต้อง trace ได้ ไม่ปน QR ในยอดเงินสดนับกะ (Gemini 13.2 ข้อ3)
- [ ] กำหนดคลังหรือ stock quota แยกสาขา และผู้มีสิทธิ์ขาย stock pool แต่ละส่วนเพื่อป้องกัน oversell ระหว่าง sync; ไม่ใช้ ERP stock cache เป็นการรับประกัน available-to-promise
- [ ] ตัดยอดขายภายใน POS แบบ atomic และนำ stock movements เข้า ERP ตาม event ที่ผ่าน validation เพียงครั้งเดียว; ระบุความหน่วงชัดเจน ไม่อ้าง ERP real-time ระหว่างขาดการเชื่อมต่อ
- [ ] ออกแบบ reservation/allocation กลางของสาขา ป้องกันหลาย Terminal และ online channel ขายหน่วยสุดท้ายซ้ำ; กำหนด expiry/release ของการจอง และแยกยอดจองออกจากการตัด on-hand จริง
- [ ] กำหนดจังหวะจอง/ตัด stock ตามกิจการ: retail เมื่อขายสำเร็จ, restaurant ตามการรับออเดอร์/เริ่มเตรียมที่ตกลง, pharmacy ตาม lot/หน่วยที่ส่งมอบ; เป็น policy ที่ต้องยืนยันและทดสอบก่อนใช้งาน ไม่ตัดซ้ำเมื่อสร้าง Invoice/Delivery Order หรือรับ event ชำระเงินภายหลัง
- [ ] กำหนด returns/void/refunds, partial refunds, manager authorization และ reversal ของ payment/stock/ภาษี/GL โดยอ้างต้นฉบับและไม่ลบประวัติ
- [ ] GL Contract: ลงรายบิลผ่าน source-linked transaction (cash/clearing/AR → sales/VAT และ COGS → inventory เมื่อรับรอง stock/cost แล้ว); ปิดกะไม่ลง sales/VAT/COGS/payment ของบิลซ้ำ บันทึกเฉพาะ over/short ตามสิทธิ์และ account mapping (5400/4400 เป็นเพียงตัวอย่าง); การนำเงินฝาก/settlement เป็นรายการโอนแยกมีหลักฐาน ไม่ใช่รายได้ใหม่ (Gemini 13.2 ข้อ4)

### 28.6.1 Retail Module — ร้านค้าขายสินค้าทั่วไป

- [ ] รองรับค้นหา/สแกน SKU/Barcode เลือกหน่วยและจำนวน คำนวณราคา/ส่วนลด/ภาษีฝั่ง server แล้วรับเงินสดหรือ QR
- [ ] จองสินค้าและยืนยันขายตาม checkout policy ที่อนุมัติ; ยกเลิกตะกร้าหรือ payment หมดอายุคืน reservation อย่างปลอดภัย ไม่สร้างยอดขาย/stock movement ซ้ำ

### 28.6.2 Shared Online Channel — Online Menu / Catalog ผ่าน QR

**Local implementation 2026-10-26:** Retail QR/token expiry/revoke + explicit online visibility + anonymous request/dedupe + staff acceptance/re-review + shared cash checkout + customer status ผ่าน browser desktop/mobile. ยังไม่ครบรูป/ตัวเลือก/โต๊ะ/production acceptance จึงไม่ติ๊ก composite requirements ด้านล่าง. เพิ่ม source-linked manager refunds/Credit Note/Payment/GL และ quarantine returns แล้วใน local-only; Cash In-Out/discount/inspection ยังต้องทำต่อ ดู [work register](../document/PHASE_28_IMPLEMENTATION_STATUS.md)

**เพิ่มเติม 2026-10-26:** Cash In-Out local-only + manager re-auth/dedupe/overdraw + trusted cash clearing GL ผ่าน POS tests/browser; ledger checks UI ฝั่ง ERP แยก permission/tenant scope และตรวจ source/Payment/GL/Stock ได้แล้ว แต่ยังไม่มี signed POS checkpoint จึงไม่ใช่ full reconciliation. Authorized discount/inspection และงานรุ่นแรกอื่นยังต้องทำต่อ

- [ ] ลูกค้าสแกน QR เข้าหน้าเมนู/แคตตาล็อกของสาขา ดูสินค้า ราคา รูป ตัวเลือก และเลือกสินค้าได้บนมือถือ; เปิดเฉพาะรายการที่สาขาอนุญาต
- [ ] แยก QR เข้าเมนูออกจาก QR ชำระเงิน; QR เมนูไม่ใช่หลักฐานรับชำระหรือสิทธิ์อ่านข้อมูล ERP ภายใน
- [ ] ยืนยัน workflow หลังเลือกสินค้า ก่อนพัฒนาส่งออเดอร์/ชำระออนไลน์: ส่งให้พนักงานตรวจรับหรือยืนยันอัตโนมัติ, จุดรับสินค้า, payment timing และสถานะที่ลูกค้าติดตามได้
- [ ] หากยืนยันรับออเดอร์ออนไลน์ ให้ตรวจ catalog/ราคา/ตัวเลือก/ภาษี/ยอดพร้อมขายใหม่ฝั่ง server, บันทึก idempotency, จำกัดคำขอ/จำนวน และป้องกัน spam/รายการซ้ำ โดยใช้ stock pool เดียวกับ POS สาขา
- [ ] หากใช้ QR โต๊ะ ให้กำหนด table session/การเปิดปิดโต๊ะและป้องกัน token เดาหรือใช้ซ้ำผิด session; ไม่เชื่อ table_id หรือ branch_id ที่ browser ส่งมาโดยตรง

### 28.6.3 Restaurant Module — ร้านอาหาร

**Local implementation 2026-10-26:** โต๊ะ/กลับบ้าน/จ่ายหลังเสิร์ฟ/แยกบิล, reservation, recipe snapshot, kitchen lifecycle และ waste พร้อม POS UI/ERP shared pipeline ทดสอบแล้ว. Browser 1440/390 ผ่าน; ยังไม่ติ๊กทั้งหัวข้อว่าเสร็จ เพราะ modifiers/online table scope และ production acceptance ยังเหลือ ดู [work register](../document/PHASE_28_IMPLEMENTATION_STATUS.md)

- [ ] Lightweight Recipe Consumption (Gemini 15.2 ข้อ5): ไม่สร้าง Work Order ต่อจาน; หลังยืนยัน workflow ใช้ BOM/recipe snapshot ณ event ที่ใช้วัตถุดิบจริงผ่านบริการ Stock กลาง (เสนอ `recipe_consumption`) พร้อม unit/lot/cost/source-line trace ไม่ให้ Connector สร้าง movement โดยข้าม service และไม่รอสูตรปัจจุบัน ณ เวลา import
- [ ] Waste/spoilage (เสนอ `food_waste_spoilage`) ต้องแยกกรณียังไม่ consume กับ consume แล้ว: ถ้ายังไม่ consume ให้บันทึก stock issue ที่เหมาะสมครั้งเดียว; ถ้าตัดวัตถุดิบแล้วให้จัดประเภทต้นทุน/เชื่อมหลักฐานของเสียโดยไม่ลดวัตถุดิบเดิมซ้ำ. บัญชีและ reversal กำหนดใน design พร้อมสิทธิ์ผู้จัดการ ไม่ถือว่าส่งครัวแล้วเกิดของเสียทุกกรณี
- [ ] ยืนยันและออกแบบการขายเมนูอาหาร ขนาด/ตัวเลือก/เพิ่มพิเศษ และรายการที่เป็นสินค้าสำเร็จรูป โดยเก็บราคา/สูตรตาม version ที่ใช้กับออเดอร์
- [ ] หากเลือกตัดวัตถุดิบตามสูตร ให้เชื่อม recipe/BOM กับ Stock และแปลงหน่วยอย่างชัดเจน รองรับผลของตัวเลือกต่อวัตถุดิบ; ตรวจ reuse บริการเดิมก่อนสร้าง adapter ไม่สร้าง Work Order ต่อจานโดยอัตโนมัติ
- [ ] พิจารณา `Order Sent to Kitchen` เป็นจังหวะ consume ตาม Gemini 14.3 ข้อ2 หลังยืนยัน workflow ครัว; แยก reservation ออกจาก consumption และ payment settlement, กำหนดเหตุการณ์ที่ server รับรอง/ครัวตอบรับให้ชัด พร้อม idempotency ต่อ order line/recipe version ไม่ตัดซ้ำจาก retry/พิมพ์ใบครัวซ้ำ/ชำระภายหลัง
- [ ] หลังยืนยันโต๊ะ/รับกลับ/จ่ายก่อนหรือหลัง จึงกำหนด order lifecycle, การส่งครัว/เตรียม/เสิร์ฟ, รวม/แยกบิล และอุปกรณ์ที่เกี่ยวข้อง
- [ ] แยกยกเลิกก่อนเริ่มเตรียมออกจากยกเลิกหลังใช้วัตถุดิบ; กำหนดคืน reservation/คืน stock/ของเสียตามหลักฐานและสิทธิ์ ไม่คืนวัตถุดิบเป็นสินค้าพร้อมขายโดยอัตโนมัติทุกกรณี
- [ ] ยกเลิกหลังส่งครัวต้องตรวจสถานะตอบรับ/การใช้วัตถุดิบจริง: ยังไม่ใช้ให้ release/reverse ตาม policy ที่ตรวจแล้ว, ใช้หรือเตรียมแล้วให้ลง waste/spoilage ตามสิทธิ์และหลักฐาน; ไม่เหมาว่าการส่งครัวทุกครั้งเท่ากับของเสียหรือคืน stock ดีได้ทุกครั้ง

### 28.6.4 Pharmacy Module — ร้านขายยา (ไม่มีประวัติผู้ป่วยในรุ่นแรก)

**นโยบายยืนยัน 2026-10-26:** ของคืนให้ผู้จัดการที่เป็นเภสัชกรตรวจ lot/expiry/สภาพและเลือกคืน Stock/ทำลายได้. เพิ่ม local inspection UI/signed outbox/ERP import และ original-cost restock แล้ว; default disabled, ไม่คืน quota อัตโนมัติ. Recall และ production acceptance ยังเหลือ

- [ ] ออกแบบข้อมูลสินค้าเฉพาะยา: ชื่อการค้า/ชื่อสามัญ/ความแรง/รูปแบบ/Barcode และกลุ่มสินค้าที่ร้านกำหนด โดยยังไม่เพิ่มเวชระเบียนหรือระบบวินิจฉัย
- [ ] ยืนยันและรองรับหน่วยขายกล่อง/แผง/เม็ดหรือหน่วยที่ร้านใช้จริง พร้อมอัตราแปลงหน่วย ราคา rounding และ stock ledger ในหน่วยฐาน; ทดสอบการขายแยกหน่วยไม่ให้ยอดคงเหลือผิด
- [ ] กำหนด base UoM ต่อ SKU เป็นหน่วยย่อยที่ตรวจนับได้จริงตาม Gemini 14.3 ข้อ3 เช่นยาเม็ดใช้เม็ดและแปลงแผง/กล่องด้วย factor ที่แน่นอน; ยาน้ำหรือสินค้าที่ใช้ปริมาตรต้องกำหนด decimal scale/ขั้นต่ำ/rounding ตามหน่วยจริง ไม่บังคับจำนวนเต็มทุก SKU และไม่ใช้ float สะสมยอด
- [ ] เก็บ conversion snapshot/version และตรวจ lot balance ในหน่วยฐานเดียวกันตลอด POS/ERP; หากสินค้าเดิมมี stock/ธุรกรรมแล้ว ต้องมี migration/reconciliation policy ก่อนเปลี่ยน base unit ไม่เปลี่ยนความหมายยอดเก่าย้อนหลัง
- [ ] เชื่อม Warehouse/Lot/Batch/Expiry เดิม เพิ่ม FEFO selection, บล็อก lot หมดอายุ และบันทึก lot ที่ส่งมอบจริงใน sale lines; การ override ต้องเป็นไปตาม policy/สิทธิ์ที่ยืนยันและไม่ข้าม expiry guard
- [ ] ติดตามย้อนหลัง sale/branch/terminal/product/lot เพื่อค้นหารายการและพักขายหรือเรียกคืน lot ตามคำสั่งผู้มีสิทธิ์ โดยไม่ต้องมี patient history
- [ ] ยืนยันรายการที่ต้องให้เภสัชกรตรวจรับ รวมถึงสิทธิ์และ audit ก่อนส่งมอบ/ชำระตาม workflow ของร้าน; กำหนดสินค้าที่แสดงหรือรับคำขอผ่านเมนูออนไลน์ ไม่ถือว่าทุกสินค้าขายออนไลน์อัตโนมัติ
- [ ] ของคืนเข้าพื้นที่/สถานะรอตรวจ แยกจาก stock พร้อมขาย; คืนเข้าขายหรือจัดการเป็นของเสียต้องผ่านการตรวจและสิทธิ์ พร้อม trace lot/ต้นฉบับ
- [ ] ไม่สร้าง patient history/visit/medical record ในรุ่นแรก; ข้อมูลผู้ซื้อเพื่อใบกำกับภาษีอยู่ในขอบเขตเอกสารขาย ส่วนแนบใบสั่งยารอยืนยันก่อนออกแบบ upload/storage/ACL/retention

### 28.7 Operations / Acceptance Tests

**ผลทดสอบ local 2026-10-25:** ERP regression 97 tests / 581 assertions; focused import/transport หลังเพิ่ม TLS assertions 16 tests / 89 assertions (ทับซ้อน ไม่บวกจำนวน); POS 19 tests / 69 assertions. Browser desktop/mobile รวม unit selector/cash shift ผ่าน ไม่มี JS error; TypeScript/build ผ่าน. ผู้ใช้อนุมัติเฉพาะ local สำหรับ transport/prescription ใช้ synthetic data/fake network; ไม่ใช้ผลแทน real TLS/firewall/MariaDB concurrency/e-Tax/production acceptance

- [ ] Release v1 acceptance: pilot 1 สาขา/1 PC, cash-only, branch stock/HQ read permissions, online order staff acceptance, ABB/Full และ e-Tax end-to-end ตามช่องทางที่เลือก รวม sign/submit/accept/reject/retry/duplicate/outage/recovery; pending submission ต้องไม่แสดงสำเร็จ และ optional prescription ไม่มี patient history

- [ ] Regression Service Extraction: ERP Delivery Order/negative-stock guard/moving average/low-stock alerts/สิทธิ์/audit ยังทำงานเดิม; concurrent imports รวม stock pool ว่าง และ DO อ้างอิง POS ไม่ตัดซ้ำแม้ retry/transition
- [ ] Tests GL modes: direct paid sale เทียบ Invoice/Payment path, unpaid restaurant sale, cash/QR mappings, refund/void/period lock และ reconciliation กับรายงานเดิม; หนึ่งบิลไม่ลง revenue/VAT/receipt/COGS ซ้ำ ไม่มี fallback บัญชีผิดประเภท
- [ ] Tests suspended receipts: callback หลังรับ cash/void, duplicate/out-of-order callback เทียบ settlement ที่ลงแล้ว, refund/reclass ล้มเหลวและ retry; เงินเข้าจริงมีหลักฐาน/ยอดพัก/notification และไม่ลง clearing/liability/refund ซ้ำ
- [ ] Tests recipe/waste: snapshot ต่างจากสูตรปัจจุบัน, ส่งครัวซ้ำ, waste ก่อนและหลัง consumption, ขายเมนูร่วมสินค้าสำเร็จรูป และยกเลิก/คืนเงิน; ยอดวัตถุดิบ/COGS/ต้นทุนสูญเสียถูกต้องโดยไม่ตัดซ้ำ
- [ ] Tests Shared Core + Business Modules: ทุกกิจการใช้ sale/payment/tax/stock/sync pipeline ร่วมโดยไม่สร้าง posting ซ้ำ; เปิด/ปิด capability ตรวจทั้ง UI/API/สิทธิ์, สินค้าทั่วไปไม่ถูกบังคับผ่าน workflow เฉพาะยา/อาหาร และอ่านประวัติ/คืนเงิน/กระทบยอดรายการเดิมหลังเปลี่ยน configuration ได้ตาม policy
- [ ] หน้าจัดการ POS instances/branch mappings/credentials และ pause/revoke instance โดย ERP admin พร้อมสิทธิ์และ re-authentication
- [ ] หน้าติดตาม pending/accepted/rejected/needs_review, sync lag, last successful sync และ reconciliation discrepancies; replay เฉพาะผู้มีสิทธิ์ผ่าน pipeline เดิม
- [ ] Tests network isolation/auth: POS zone เริ่ม connection เข้า ERP/DB ไม่ได้, credentials ไม่ใช้ข้าม instance/org/branch และ revoke/rotation มีผล
- [ ] Tests import validation: malformed/oversized events, foreign products/warehouses, forged price/tax/discount/paid status และ compromised POS ไม่สามารถเลือก GL/อ่านข้อมูล ERP เพิ่มได้
- [ ] Tests idempotency/concurrency: duplicate event/order, concurrent import, lost ack, crash/restart, out-of-order/refund-before-sale และ no duplicate invoice/payment/stock/GL
- [ ] Tests checkout/shift: เงินสด/เงินทอน/QR pending-confirmed-expired, over/under payment, day/timezone boundary, close/reopen policy และ over-short เฉพาะกะ; split/mixed tender ทดสอบเมื่ออนุมัติขอบเขตนั้น
- [ ] Tests stock/payment/accounting: oversell/quota contention, provider proof, returns/refunds, tax rounding, balanced journals และ period locks
- [ ] Tests tax invoicing/replacement: เลขเอกสารหลายสาขา/หลายเครื่องไม่ชนกัน, ข้อมูลผู้ซื้อ, conversion/reissue/refund/void/offline issuance ตาม policy ที่ยืนยัน ต้อง trace sale เดียว ไม่เพิ่มรายได้/VAT ซ้ำ; e-Tax signing/provider tests ทำเมื่ออนุมัติขอบเขตเพิ่ม
- [ ] Tests stock discrepancy: verified physical sale ถูกพัก inventory โดยไม่สูญหาย, forged sale ยังไม่ผ่าน, manager resolution/retry ทำ stock/COGS ครั้งเดียว และรายงานต้นทุนค้างตรงสถานะ
- [ ] Tests clearing/GL: QR ไม่เพิ่ม cash drawer, provider reference reuse/gross-fees-net/settlement/refund และ close shift/replay ไม่สร้าง sales/VAT/COGS ซ้ำ
- [ ] Tests multi-branch/multi-terminal: scope พนักงาน/ราคา/คลัง/กะถูกต้อง หลายเครื่องและ online channel แย่ง stock หน่วยสุดท้ายโดยไม่ oversell หรือสร้าง reservation ซ้ำ
- [ ] Tests terminal numbering: สร้างพร้อมกันข้ามเครื่อง/ภายในเครื่องเดียว, retry, timezone/เปลี่ยนวัน, restart/reprovision/restore และเลขบิล/เลขเอกสารภาษีไม่ชนกันตาม policy โดยไม่สร้าง sale ซ้ำ
- [ ] Tests QR → cash race: cancel ชนกับ settlement, callback มาก่อน/หลังเงินสด, cancel timeout/unknown, duplicate/late webhook และ provider reference ใช้ซ้ำ; เงินเข้าจริงไม่สูญหาย ยอดจ่ายเกินเข้ารอตรวจ/คืนเงิน และ sales/payment application/stock/GL ไม่ซ้ำ
- [ ] Tests online menu: QR ผูกสาขา/โต๊ะตามขอบเขต, ราคา/ตัวเลือก/availability ถูกตรวจใหม่, tampered payload, spam และ submit ซ้ำ; order/payment flows ทดสอบเฉพาะส่วนที่อนุมัติ
- [ ] Tests restaurant ตาม workflow ที่ยืนยัน: modifier/recipe version/หน่วยวัตถุดิบ, จังหวะตัด stock, ยกเลิกก่อนและหลังเตรียม, ป้องกัน payment ภายหลังตัด stock ซ้ำ และโต๊ะ/ครัว/รวมแยกบิลเมื่ออยู่ในขอบเขต
- [ ] Tests pharmacy: unit conversion/ขายแยกหน่วย, FEFO/lot หมดอายุ, lot trace/recall, ของคืนรอตรวจ, เภสัชกร gate และ online visibility; ยืนยันไม่มี patient history/เวชระเบียนใน schema/API/หน้าจอรุ่นแรก
- [ ] Tests restaurant kitchen timing: ส่งครัว/ตอบรับซ้ำ, ยกเลิกก่อนใช้และหลังใช้วัตถุดิบ, waste/reversal ที่มีสิทธิ์ และจ่ายก่อน/หลังไม่ทำให้ consume ซ้ำ ตาม workflow ที่ยืนยัน
- [ ] Tests pharmacy precision: กล่อง/แผง/เม็ดและหน่วยปริมาตร, fractional quantities/scale ที่อนุญาต, การปัดเศษและคืนสินค้า, conversion version เปลี่ยนหลังขาย และ lot balance POS/ERP ตรงกัน
- [ ] Tests integrity/DLQ: altered payload/hash, signature/key mismatch/rotation, ID เดิม payload ต่าง, malformed event ไม่บล็อกชุด และ Admin replay เก็บต้นฉบับ+ไม่สร้างเอกสารซ้ำ; ไม่ใช้ hash test เป็น proof ว่าทนต่อ compromised POS host
- [ ] Tests outages/recovery: ERP offline แล้ว POS ขายต่อ, POS downtime แสดงสถานะถูกต้อง, stale catalog policy และ reconnect/reconcile โดยไม่สูญหายหรือสร้างซ้ำ
- [ ] Tests retention/pruning: เก็บ pending/review/hold และหลักฐานที่จำเป็น, prune เฉพาะรายการผ่าน gate, archive restore ได้ และ replay หลัง prune ยัง dedupe ไม่สร้าง invoice/payment/stock/GL ซ้ำ; ตรวจ disk/backlog alerts ก่อนพื้นที่เต็ม
- [ ] ทดสอบ deployment สอง Server จริง, operational UAT, backup/restore, polling/load และ recovery targets ที่ตกลงก่อนเปิด production; บันทึกผลจริงแยกจาก design checklist

### 28.8 งานแยกภายหลัง — ไม่รวมการเริ่มต้น

- [ ] Patient history/เวชระเบียน/ข้อมูลการรักษาของร้านขายยา: ผู้ใช้ยืนยันยังไม่ทำในรุ่นแรก; หากเพิ่มต้องออก requirement/สิทธิ์/ข้อมูล/retention แยกก่อน
- [ ] QR payment/provider และ QR-specific acceptance: เลื่อนไปรุ่นภายหลังตามคำตอบ 2026-10-25; คง guardrails ที่ออกแบบไว้ ไม่บังคับ Provider เพื่อเปิดรุ่นเงินสด
- [ ] Card/EDC, split/mixed tender, delivery และระบบสมาชิก/สะสมแต้ม/โปรโมชั่น: ยังไม่ยืนยัน ไม่รวมเป็น requirement เริ่มต้นโดยปริยาย
- [ ] Browser/PWA offline เมื่อ POS Server หรือ internet ใช้ไม่ได้: ออก design local encrypted cache, device identity, transaction queue, receipt numbering, quota และ conflict recovery แยกก่อนอนุมัติพัฒนา
- [ ] หากต้องการ shared-stock real-time reservation หรือช่องทาง connection แบบอื่น ต้องออก contract/threat model เพิ่มก่อน ไม่ขยาย ERP public Write API เอง
