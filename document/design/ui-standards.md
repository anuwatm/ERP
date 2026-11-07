# มาตรฐาน UI สำหรับ ERP และ POS

อ้างอิง [mockup ที่อนุมัติ](redesign-reference/) และ [checklist](../../checklist.md#checklist-ปรับ-layout-ทั้งระบบ--local-complete) ใช้พื้นงาช้าง เมนูเขียวเข้ม สี mint และปุ่มหลักสีปะการัง

## ตัวอักษรและระยะ

หน้าหลัก ERP และ POS ใช้ Sarabun จากไฟล์ในแอป มีน้ำหนัก 400 และ 700 พร้อม OFL ในโฟลเดอร์ fonts ของแต่ละแอป การพิมพ์เอกสารใช้ฟอนต์ที่ template เดิมฝังไว้

- เนื้อหาและช่องกรอกบนมือถือใช้ 16 px ข้อความรองใช้ 14 px
- ชื่อหน้าใช้ 24 px และ 30 px ตั้งแต่ breakpoint sm
- ช่องว่างภายในกลุ่มใช้ 8–12 px ระหว่างส่วนใช้ 24 px
- ปุ่มร่วมและช่องกรอกร่วมสูงอย่างน้อย 44 px ป้ายกำกับช่องกรอกอยู่เหนือช่อง
- ตัวเลขเงินในรายงานใช้สกุลเงินและการปัดเศษจาก server ตามกติกาเดิม

## สีตามความหมาย

ค่าของ ERP อยู่ใน `backend/resources/css/app.css` ตัวแปรคู่ Light/Dark เปลี่ยนตาม class `dark` ที่ root ส่วน standalone ERP ใช้ `erp-theme.js` และ POS ใช้ preference ของแต่ละแอป

| ความหมาย | Light | Dark | ตัวแปร ERP |
| --- | --- | --- | --- |
| พื้นหน้า | #F5F2EA | #101A18 | --erp-canvas |
| พื้นช่องกรอก | #FFFFFF | #182923 | --erp-surface |
| ข้อความหลัก | #112E28 | #EAF4EF | --erp-ink |
| ข้อความรอง | #52635D | #BDCEC4 | --erp-muted |
| Placeholder | #64746D | #A9BDB0 | --erp-placeholder |
| ขอบ | #76867D | #62786C | --erp-border |
| Focus | #08765E | #75D5B7 | --erp-focus |
| ปุ่มหลัก | #A33B2C | #FFAE9A | --erp-action |
| ข้อความบนปุ่มหลัก | #FFF8F1 | #32140E | --erp-on-action |
| การกระทำอันตราย / ข้อผิดพลาด | #B42318 | #FFB4AB | --erp-danger |
| ข้อความบนปุ่มอันตราย | #FFFFFF | #43120D | --erp-on-danger |
| สำเร็จ | #146C43 | #8BE0B0 | --erp-success |
| คำเตือน | #865300 | #FFCF85 | --erp-warning |

Chrome ตรวจ contrast ของข้อความหลัก ข้อความรอง Placeholder ปุ่มหลัก ปุ่มอันตราย และสถานะสำเร็จ/คำเตือนบนพื้นคู่กันได้อย่างน้อย 4.5:1 ทั้งสองธีม หลักฐาน `temp/ui-redesign-acceptance/ui-foundation-report.json` การตรวจนี้ครอบคลุม token คู่ที่ระบุ ส่วนหน้าจอเฉพาะต้องตรวจพื้นหลังและกราฟตามทะเบียนตรวจรับ

คู่สีขอบช่องกรอก focus และกราฟผ่านเกณฑ์ 3:1 ในชุดตรวจ token ส่วน Badge ทั้งหกแบบและปุ่มร่วมตรวจจาก CSS ที่ build ได้อย่างน้อย 4.5:1 ตามรายงาน `ui-status-contrast-report.json`

## โครงหน้าและ responsive

- ต่ำกว่า 640 px เรียงฟอร์มคอลัมน์เดียว ช่องกรอกอย่างน้อย 16 px
- ตั้งแต่ 640 px ใช้ระยะขอบและปุ่มแบบ desktop ตามพื้นที่จริง
- ตั้งแต่ 768 px เปิด sidebar ERP แบบคงที่ จอเล็กใช้เมนูเปิด/ปิด
- ตั้งแต่ 1280 px ฟอร์มหลายรายการที่ขยายแล้วแสดงส่วนสรุปข้างข้อมูลหลัก
- ตารางเลื่อนในพื้นที่ตารางเมื่อกว้างเกินจอ หน้าเอกสารและ dialog ต้องอ่านหัวข้อและเข้าถึงปุ่มได้
- เมื่อผู้ใช้ตั้ง reduced motion ลด animation และ transition ตาม preference ของระบบ

## Component และสถานะ

| งาน | Component / รูปแบบ | พฤติกรรม |
| --- | --- | --- |
| หัวข้อและทางเดินหน้า | PageHeader | ชื่อหน้า คำอธิบาย breadcrumb และ action ตามสิทธิ์ |
| ส่วนข้อมูล | Card | เนื้อหาและ action ห่อบรรทัดเมื่อพื้นที่แคบ |
| ตาราง | DataTable | หัวคอลัมน์ใช้ scope=col, พื้นที่เลื่อนรับ focus และปุ่มลูกศร, emptyMessage ระบุเหตุผลข้อมูลว่าง |
| ตัวกรองและการแบ่งหน้า | ฟอร์มกรองและ Inertia Link ของโมดูล | รักษาช่วงวัน ตัวกรองและ query ของรายการตามสิทธิ์ แสดงข้อความผิดพลาดข้างชุดตัวกรอง |
| ป้ายสถานะ | Badge | ใช้ข้อความระบุสถานะ สีช่วยแยกกลุ่ม |
| ปุ่มหลัก/รอง/อันตราย | PrimaryButton, SecondaryButton, DangerButton | สีตาม semantic tokens, focus มองเห็น, disabled ระหว่างประมวลผล |
| ฟอร์มสั้น | TextInput, InputLabel, InputError | Label เชื่อม id, aria-invalid และ aria-describedby เชื่อมข้อผิดพลาด, InputError ประกาศด้วย role=alert |
| ฟอร์ม master และรายการย่อย | CreateEditDialog, EditorFields, EditorLineItems | คง validation ระดับช่อง/แถว, loading/processing, สรุปข้อผิดพลาดและ focus ช่องที่ผิด |
| BOM และ Work Order | Modal, useDiscardGuard, InputError | Dialog มีชื่อ กัก focus และคืน focus เมื่อปิด แสดงข้อผิดพลาดข้างช่อง ป้องกันปิดขณะส่ง และยืนยัน Keep/Discard เมื่อมีร่าง |
| ข้อมูลร่าง | useDiscardGuard, DiscardChangesDialog | คง Keep/Discard, Back/Forward, Escape/backdrop และการกันปิดระหว่างบันทึกตามหน้าที่ใช้ guard เดิม |
| ความล้มเหลวเครือข่าย | ข้อความข้างฟอร์มและ action ลองใหม่เดิม | เก็บข้อมูลร่างและแสดงว่ายังยืนยันผลการบันทึกไม่ได้ |
| อ่านอย่างเดียว | ตัวควบคุมตาม can/permission เดิม | เปิดดูข้อมูลตามสิทธิ์และแสดงเหตุผลเมื่อ action ทำไม่ได้ |

หน้า Profile ใช้ Card คู่กันบนจอกว้างและเรียงต่อกันบนมือถือ หน้ายืนยันลบบัญชีมีชื่อ dialog สำหรับ screen reader ช่องรหัสผ่านเชื่อม label และข้อความผิดพลาด พร้อมบล็อกการปิดระหว่างส่งคำขอ

## ขอบเขตข้อมูลและเอกสารพิมพ์

Guest ใช้แบรนด์กลาง Portal แยกบริบทจากพนักงาน KPI ใช้นิยามและสิทธิ์เดิม สมาชิกและผู้ป่วยแสดงข้อมูลตามสิทธิ์และการ mask ของ server เงื่อนไขสาขา Terminal กะ และเภสัชกรตรวจรับอยู่ที่ backend

หน้าพิมพ์และ PDF ใช้พื้นขาว พร้อมเส้นตารางและฟอนต์ของเอกสาร ตรวจแยกจากสีหน้าจอ ธีมจดจำเฉพาะ preference และการเปลี่ยนธีมต้องคงข้อมูลร่างบนหน้าที่เปิดอยู่

[ทะเบียนหน้าจอ](ui-screen-inventory.md) · [ทะเบียนตรวจรับ](ui-acceptance-register.md)
