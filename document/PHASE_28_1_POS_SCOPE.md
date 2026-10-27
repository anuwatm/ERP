# Phase 28.1 — POS Scope & Requirement Decisions

สถานะ: Requirements Baseline Complete (2026-10-25); local design/implementation ผ่าน Geminiแล้ว 2026-10-27. Production acceptance ยัง pending

อ้างอิง [checklist Phase 28.1](../checklist.md). เอกสารนี้เก็บ requirement baseline; ผล design/implementation acceptance และการอัปเดตฐานที่ผู้ใช้อนุมัติภายหลังให้ดู [current status](../docs/PHASE_28_POS.md)

## ขอบเขตยืนยันล่าสุด 2026-10-25

| ID | ผลตัดสินใจ | ข้อค้าง |
| --- | --- | --- |
| D01 | นำร่อง 1 สาขา 1 Terminal ใช้ PC; ระบบยังรองรับหลายสาขา/หลายเครื่อง | มีเครื่องพิมพ์ใบเสร็จแล้ว; ประเภทกิจการนำร่อง/รุ่นเครื่องพิมพ์/driver ระบุใน setup/UAT gate ก่อนทดสอบอุปกรณ์จริง ไม่สมมติว่ามี scanner/ลิ้นชัก. จอครัวเป็นอุปกรณ์เพิ่มเติม ไม่ใช่ cashier Terminal เพิ่ม |
| D02 | หนึ่งสาขาหนึ่งกิจการ ใช้ Shared Core + Retail / Restaurant / Pharmacy Modules ใน codebase เดียว | ไม่เปิดหลายกิจการในสาขาเดียวรุ่นแรก; กฎเฉพาะยังเลือกตามสินค้า |
| D03 | Stock แยกแต่ละสาขา สำนักงานใหญ่ดูภาพรวม | ผู้ใช้ยืนยัน ERP ขาย/เบิก Stock สาขาได้ด้วย; 28.2 ต้องกำหนด reservation/quota/locking ทั้ง ERP และ POS ป้องกันแย่งยอดระหว่าง sync ไม่ใช้ cached on-hand เป็นสิทธิ์ขาย |
| D04 | รุ่นแรกเงินสดเท่านั้น ยังไม่มี Payment Provider; ทุกสาขารับเงินเข้าบัญชีเดียวกัน | QR payment เลื่อนไปภายหลัง; QR เข้าเมนูยังอยู่. เงินสดลิ้นชักแยกจากรายการนำฝากธนาคาร |
| D05 | ใบกำกับอย่างย่อและเต็มรูปออกที่ POS; รุ่นแรกต้องมี e-Tax | ยังไม่มี e-Tax provider/channel; ย้ายการเลือกเป็น gate 28.2 ตามคำถามและคำตอบล่าสุด ห้ามล็อก integration/schema หรือทำ live submission ก่อนเลือกและ review contract; ต้องทดสอบจริง ไม่ใช้ mock แทน |
| D06 | ลูกค้าเลือกสินค้าและส่งออเดอร์ให้พนักงานตรวจรับ | ไม่เพิ่ม self-payment/delivery; Pharmacy ใช้การสั่งยาหน้าร้านตาม D09 |
| D07 | ร้านอาหารรองรับโต๊ะและกลับบ้าน จ่ายหลังกิน มีจอ monitor ครัว | ยืนยันแยกบิล และ takeaway จ่ายตอนรับอาหาร; รวมบิลไม่อยู่ในขอบเขตยืนยัน. Sending/ack/preparing/consumption ต้องแยกใน design |
| D08 | ร้านขายยาขายกล่อง/แผง/เม็ด/ยาน้ำ เภสัชกรบันทึกข้อมูล | ต้องกำหนด precision/สิทธิ์/จังหวะตรวจรับใน design; คำว่าบันทึกข้อมูลไม่ใช่อนุมัติเก็บเวชระเบียน |
| D09 | สั่งยาหน้าร้าน แนบใบสั่งยาได้แต่ไม่บังคับ; ไม่มี patient history/visit/เวชระเบียน | ออกแบบ upload validation/quarantine/private storage/ACL/audit/retention; ไม่เปิดสั่งยาออนไลน์โดยอนุมาน |
| D10 | ยืนยัน baseline online และ POS Server อยู่ส่วนกลาง | Terminal/จอครัวต้องเชื่อมส่วนกลาง; browser offline ภายหลัง; ออกแบบ recovery ใน 28.2 |

## Baseline การเชื่อมต่อ — ผู้ใช้ยืนยัน 2026-10-25

- Terminal และจอครัวต้องเชื่อมกับ POS Server ตลอด; browser/PWA offline ทำภายหลัง
- POS และ ERP แยก Server/DB/credentials/session; ERP Connector เริ่ม connection ออกไป POS เท่านั้น ไม่เพิ่ม ERP public Write API
- ERP หยุดบริการ: POS รับเงินสดและขายต่อภายใต้ stock สาขา/catalog ที่ sync ไว้และ policy ที่ออกแบบ; outbox รอ ERP กลับมา import โดยไม่ทำรายการซ้ำ
- e-Tax ส่งไม่ได้: เก็บ durable queue พร้อมสถานะ pending/retry/error; ห้ามแสดงว่าส่งสำเร็จก่อนมีผลยืนยัน. การรับรองออกเอกสาร/ส่งล่าช้าต้องตรวจตามช่องทางที่เลือก ไม่อ้างว่าคิวรอเพียงอย่างเดียวทำให้ถูกต้องครบ
- POS Server หรือเครือข่ายที่ต้องใช้ล่ม: หยุดรับธุรกรรมบน Terminal พร้อมแจ้งสถานะ; ไม่รับประกัน offline sales ในรุ่นแรก
- POS Server อยู่ส่วนกลางตามคำยืนยัน แยกจาก PC Terminal ที่สาขา; สาขาขาดการเชื่อมต่อส่วนกลางจะขายบน browser ต่อไม่ได้ในรุ่นแรก

## ขอบเขตและ guardrails ร่วม

- Core ร่วม: สาขา/Terminal/สิทธิ์/กะ/ตะกร้า/ราคา/เอกสารขาย/เงินสด/คืนสินค้า/stock reservation/ERP sync; QR payment เป็น future capability
- Business Modules ให้ workflow เฉพาะ โดยใช้ payment/tax/stock/GL pipeline ร่วม ไม่ลงซ้ำ; Online เป็น channel ร่วมที่ใช้ราคา/stock pool สาขาเดียวกัน
- ERP เป็น master ตามแผน; POS payload ยังต้องตรวจ authority/values/source identity. Headquarters overview ไม่เปิดข้อมูลองค์กรอื่น
- ไม่มี card/EDC, browser offline, patient history หรือ online pharmacy ordering ในขอบเขตที่ยืนยันรุ่นแรก
- Gemini section 15 รับรองเชิงหลักการแบบ Approved with Conditions; Architecture & Contract รวม Service Extraction Plan ยังต้อง review ก่อน code/migrations
- ข้อเสนอเลขบัญชี/service/table/movement names ยังเป็น design candidates; e-Tax/optional prescription ย้ายจาก deferred มาเป็นงานรุ่นแรกตามคำตอบใหม่

## เกณฑ์ปิด Phase 28.1

- [x] สรุปคำตอบ D01–D09 และระบุคำถามที่ตอบบางส่วน
- [x] บันทึกการเปลี่ยน QR payment เป็น deferred และ e-Tax/optional prescription เป็นรุ่นแรก
- [x] จัด baseline ข้อเสนอ D10 และขอบเขต outage ชัดเจน
- [x] ยืนยัน D10/ส่วนกลาง, printer, ERP ใช้ Stock สาขา, แยกบิล/takeaway; e-Tax ยังไม่มี provider ย้ายเป็น decision gate 28.2. รุ่นอุปกรณ์และกิจการนำร่องเป็น setup/UAT gate ไม่ตีความว่าเลือกกิจการแล้ว
- [x] บันทึก baseline และ downstream gates ตรงกับ checklist/gpt.md; ปิดเฉพาะ requirement scope ไม่ติ๊กงานออกแบบหรือ implementation ว่าเสร็จ

การปิด 28.1 ไม่ใช่การผ่าน 28.2–28.7 หรือรับรอง production; งานนี้ยังไม่แก้ Application/Connector/schema
