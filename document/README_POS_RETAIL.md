# README — ใช้ POS ร้านขายของทั่วไป

> **Phase28.8.2 local — 2026-11-01:** สมาชิก/โปรโมชันทุกPOSผ่านlocal acceptance; ERP `/pos-membership`, POS `/membership` และตัวเลือกตอนรับเงิน. [คู่มือ/diagram](PHASE_28_8_2_MEMBERSHIP.md) · [หลักฐาน](acceptance/phase28_8_2/README.md). Default-off; configuredDBยังไม่migrateส่วนนี้ และ **ยังไม่ได้ทดสอบกับระบบจริง**

> **Phase28.8.1 — 2026-10-30:** Delivery local: /deliveries → รับรายการ → ready จอง quota → dispatch/deliver → collect → manager remit. รับคืนต้อง receive/inspect ก่อน restock; COD ไม่สร้างเอกสารการเงินอัตโนมัติ. [คู่มือ/diagram](PHASE_28_8_1_DELIVERY.md). **ยังไม่ได้ทดสอบกับระบบจริง**

> **ปรับปรุง 2026-10-29 — POS Config:** [คู่มือ/ขอบเขตและ diagram ปัจจุบัน](POS_CONFIGURATION.md). Manager จัดการเฉพาะสาขาตนผ่าน `/config` (local/loopback); POS 45 tests / 268 assertions. Gemini ตรวจ POS Config เฉพาะ 6 ไฟล์ที่ผู้ใช้อนุญาต วันที่ 2026-10-29 ไม่พบข้อบกพร่องสำคัญสำหรับ Local-only; ยังไม่ได้ทดสอบระบบ/อุปกรณ์จริง.

> **หมายเหตุ:** ทดสอบระดับ local เท่านั้น ยังไม่ได้ทดสอบกับเครื่องพิมพ์ เครื่องสแกน เครือข่าย หรือระบบจริง

## เรียกใช้

เปิด server ตาม [คู่มือเปิด POS](README_POS.md) แล้วเข้า **http://127.0.0.1:8128/**. สาขาต้องเป็น `retail`; ไม่ต้องเปิด restaurant/pharmacy flags. สำหรับ fixture ใช้ `cashier@pos.test` หรือ `manager@pos.test` ตามตารางบัญชีในคู่มือกลาง

## สร้างสาขาของตนเองครั้งแรก

ตัวอย่างนี้รันจาก `pos/` หลังตั้ง environment/dependencies ตามคู่มือกลางแล้ว ไม่ใช่คำสั่ง seed ฐานใช้งาน

```powershell
$posBranchId = [guid]::NewGuid().ToString()
$posTerminalId = [guid]::NewGuid().ToString()
php artisan pos:branch $posBranchId RT01 "ร้านค้าทดลอง" --type=retail
php artisan pos:terminal $posTerminalId $posBranchId T01
php artisan pos:staff $posBranchId cashier.retail@pos.test --role=cashier
php artisan pos:staff $posBranchId manager.retail@pos.test --role=manager
```

คำสั่ง staff ถามชื่อ/รหัสผ่านเอง. ผูก UUID กับ ERP instance/authority และ publish catalog/quota ก่อนขาย; การสร้างสาขาอย่างเดียวไม่ทำให้สินค้าและ Stock ปรากฏ

## เปิดกะและขายเงินสด

1. เข้าด้วย cashier หรือ manager ของสาขา เลือก Terminal
2. ใน **เปิดกะ** กรอกเงินสดตั้งต้น แล้วกด **เปิดกะขาย**. ถ้ามีกะเดิมอยู่ให้ใช้/ปิดกะตามสิทธิ์ ไม่เปิดซ้ำ
3. ค้นหาชื่อหรือสแกนบาร์โค้ดในช่องค้นหา แล้วเพิ่มสินค้าเข้าตะกร้า
4. ตรวจหน่วย จำนวน ราคา และ VAT จาก catalog. ระบบตรวจสินค้าหยุดขาย อายุ catalog และ quota ฝั่ง server
5. กรอก **รับเงินสด** แล้วกด **ยืนยันรับเงินสด** เมื่อเงินพอ. ตัวอย่าง fixture: น้ำดื่ม10.00 + VAT0.70 =10.70 รับ20.00 ทอน9.30 ถ้าไม่ได้ใช้ส่วนลด
6. ตรวจเลขรายการขายและเงินทอน. **พิมพ์ตัวอย่าง** ใช้หน้าพิมพ์ browser ซึ่งยังไม่ใช่การทดสอบ thermal printer จริง
7. ถ้า timeout/ผลไม่แน่ชัด ตรวจรายการเดิมก่อนรับเงินหรือสร้างบิลใหม่

## ส่วนลด เงินเข้าออก และคืนเงิน

- ส่วนลดต้องเปิด feature และมีเพดานจาก ERP; ใช้ manager approval ที่ผูกตะกร้า/Terminal/catalog และมีอายุจำกัด
- **Cash In-Out** ใช้ manager ระบุทิศทาง จำนวน เหตุผล และรหัสผ่าน ไม่ใช้แทนรายการขาย
- คืนเงินให้ manager เลือกบิลต้นฉบับ/รายการ/จำนวน/เหตุผลและยืนยันรหัสผ่าน. Refund ไม่เพิ่ม quota หรือคืน Stock อัตโนมัติ; เงินในกะต้องพอ
- ยอดขายที่เสร็จแล้วไม่ลบทิ้งหรือแก้ยอดเดิม. Void/refund ต้องอ้าง original sale ผ่าน workflow ที่มีสิทธิ์

## QR เมนูและคำขอออนไลน์

1. เปิด public-menu feature และ publish สินค้าที่ `online_visible`
2. Manager สร้าง QR token จากหน้าร้าน แล้วให้ลูกค้าเปิดลิงก์ที่ระบบสร้าง
3. ลูกค้าส่งคำขอ; cashier/manager ตรวจรับและทบทวนสินค้า/ราคา ก่อนนำเข้าขายเงินสด
4. การส่งคำขอยังไม่ชำระเงินหรือกัน Stock. QR นี้เป็นเมนู ไม่ใช่ QR รับชำระ

URL `127.0.0.1` เปิดได้เฉพาะเครื่องเดียวกัน; QR จาก loopback ไม่ได้ทำให้โทรศัพท์ลูกค้าเชื่อมต่อได้ การเปิดบนเครือข่ายจริงยังไม่ได้ทดสอบ

## ภาษี ปิดกะ และติดตาม ERP

- หาก provision local ABB/FULL แล้ว: เข้า **ABB / Full — ทดสอบ local**, เลือกบิลและกรอกข้อมูลผู้ซื้อสำหรับ Full; Full แทน ABB ของยอดเดิม ไม่เพิ่มรายได้/VAT. e-Tax ยัง `awaiting_provider`
- นับเงินในลิ้นชัก กรอก **ยอดนับจริง** และกด **ปิดกะและบันทึกผลต่าง** หลังจัดการตะกร้าค้างเรียบร้อย
- ดู **รอ ERP** และ **ตรวจสถานะ**. ผู้ดูแลตรวจ sync/reconciliation ฝั่ง ERP; ปิดกะไม่ทำให้ pending sync ถูกยกเลิก

[คู่มือกลาง](README_POS.md) · [ร้านอาหาร](README_POS_RESTAURANT.md) · [ร้านขายยา](README_POS_PHARMACY.md)

## Source ที่ใช้ตรวจคู่มือ

[หน้าขาย](../pos/resources/pos.tsx) · [Checkout](../pos/app/Services/CheckoutService.php) · [Refund](../pos/app/Services/RefundService.php) · [Public menu](../pos/app/Services/PublicMenuService.php)
