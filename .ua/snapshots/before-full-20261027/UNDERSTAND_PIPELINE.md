# ผล Full Pipeline — Understand ERP

สถานะ: **ครบ Phase 0–7 และเปิด Dashboard แล้ว**

วิเคราะห์ working tree บน HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` วันที่ 2026-10-24 (Asia/Bangkok) ผ่าน skill Understand-Anything 2.9.7; runtime ที่ขาดถูกติดตั้งและ build แล้ว ใช้ขอบเขต `.ua/.understandignore` ที่ผู้ใช้ยืนยัน ไม่ได้เปิด auto-update hooks เพิ่ม

## ผลลัพธ์

- วิเคราะห์ **861/861 ไฟล์** ใน 40 batches; ตัดเพิ่ม 107 ไฟล์ตาม ignore rules
- กราฟ **2698 nodes / 7043 edges**; architectural layers 9 ชั้น; guided tour 15 ขั้น
- File coverage ครบ; imports 2,513 รายการครบ; missing files/imports และ dangling edges เป็นศูนย์
- Core schema และ default inline validation ผ่าน: 0 issues; 69 orphan warnings รายละเอียดใน review JSON
- Fingerprint baseline ครบ inventory และบันทึก metadata หลัง baseline สำเร็จ
- Dashboard HTTP page และ JSON ของกราฟผ่าน token ที่ถูกต้องตอบสำเร็จ; เปิดในแอปและเห็น Project Overview ครบ 9 layers ไม่ได้ทำ full browser visual audit

## ไฟล์อ้างอิง

- [Knowledge Graph](../.ua/knowledge-graph.json)
- [ผลสรุปแบบ JSON](../.ua/analysis-report.json)
- [Validation และ orphan warnings](../.ua/review.json)
- [Coverage/schema review](../.ua/coverage-review.json)
- [Semantic assemble review](../.ua/assemble-review.json)
- [Parser limitations และ unsupported files](../.ua/extraction-report.json)
- [Fingerprint baseline](../.ua/fingerprints.json), [Metadata](../.ua/meta.json)
- [Scan inventory](../.ua/intermediate/scan-result.json), [ขอบเขตที่อนุมัติ](../.ua/.understandignore)
- [คู่มือรายโมดูล 24 บท](modules/README.md)

## ประเภทไฟล์

| Category | จำนวน |
| --- | --- |
| code | 682 |
| docs | 127 |
| config | 31 |
| markup | 21 |

## Nodes

| Type | จำนวน |
| --- | --- |
| file | 703 |
| function | 1441 |
| class | 396 |
| document | 127 |
| config | 31 |

ชนิด table/endpoint/schema/service/pipeline/resource ไม่มี node แยกในผลนี้ ไม่ได้หมายความว่า ERP ไม่มีตารางหรือ API แต่เป็นขอบเขตผล parser และการจัดประเภทไฟล์

## Edges

| Type | จำนวน |
| --- | --- |
| contains | 1837 |
| exports | 599 |
| imports | 2513 |
| tested_by | 760 |
| calls | 275 |
| inherits | 24 |
| documents | 1003 |
| configures | 21 |
| depends_on | 11 |

## Architectural layers

| Layer | จำนวนไฟล์ |
| --- | --- |
| ส่วนติดต่อผู้ใช้และเอกสารพิมพ์ | 159 |
| คำขอเว็บและการควบคุมสิทธิ์ | 99 |
| กฎธุรกิจและงานเบื้องหลัง | 45 |
| ข้อมูลและการเปลี่ยนแปลงฐานข้อมูล | 197 |
| การเชื่อมบริการและแอปภายนอก | 44 |
| การทดสอบและข้อมูลตรวจสอบ | 108 |
| เอกสารอ้างอิงและภาพสถาปัตยกรรม | 168 |
| การตั้งค่าแอปและเครื่องมือพัฒนา | 24 |
| จุดเริ่มทำงานและการติดตั้ง | 17 |

## Guided tour

1. **ภาพรวม ERP และแผนที่อ่าน** — เริ่มจาก README เพื่อเห็นขอบเขต Company OS และโมดูลที่ใช้ข้อมูลร่วมกัน. อ่านภาพรวมสถาปัตยกรรมและสารบัญโมดูลประกอบ เพื่อแยกความรับผิดชอบของ Laravel, Inertia และ React ก่อนตามรายละเอียดธุรกิจ.
2. **เส้นทางเข้าแอป** — จากภาพรวม ตาม compatibility entry ใต้ /ERP ไปยัง HTTP entry ของ Laravel แล้วดูการตั้ง routing และ middleware. web.php แสดงว่าคำขอแต่ละโมดูลถูกส่งเข้า controller ใด ส่วน Composer manifest ระบุ dependency ของ backend. ความสัมพันธ์ในกราฟเป็น static evidence จึงไม่ควรตีความว่า traversal จับ require หรือ framework dispatch ได้ทั้งหมด.
3. **องค์กร บัญชี และสิทธิ์** — เมื่อรู้จุดรับ request แล้ว อ่าน User ซึ่งเป็นศูนย์กลางความสัมพันธ์ที่มี fan-in สูง และ OrganizationProvisioner ที่สร้างองค์กรกับข้อมูลเริ่มต้น. จากนั้นดู permission middleware และนโยบาย 2FA เพื่อแยกการยืนยันตัวตนออกจากการอนุญาตแต่ละงาน. สิทธิ์ระดับ route ยังต้องประกอบกับ tenant และ row visibility ของโมดูล.
4. **ข้อมูลร่วมและเลขเอกสาร** — ต่อจากองค์กร ดูโมเดล Customer, Invoice และ Product เพื่อเรียนรู้คำศัพท์ข้อมูลที่ธุรกิจหลายส่วนอ้างร่วมกัน. UsesOrderedUuid เป็น trait ที่มี fan-in สูงและ NumberSequenceService ดูแลเลขเอกสารตามรูปแบบกับช่วงเวลา. การอ่านกลุ่มนี้ช่วยแยก primary identifier ออกจากเลขเอกสารที่ผู้ใช้งานเห็น.
5. **หน้าเว็บและ draft editor** — เมื่อเข้าใจข้อมูลและสิทธิ์แล้ว ตาม app.tsx กับ shared Inertia props เพื่อเห็นวิธีส่งข้อมูลจาก Laravel มายัง React. CreateEditDialog และ useDiscardGuard อธิบายวงจรบันทึก ย่อหน้าต่าง และป้องกันการทิ้ง draft. การซ่อนปุ่มหรือเมนูเป็นส่วนหน้าจอ ส่วนการอนุญาตจริงยังต้องตรวจ backend.
6. **ลูกค้า Deal และ Quotation** — เริ่ม business flow จาก Customer และ Deal แล้วตามไป Quotation ที่ใช้ข้อมูลลูกค้ากับสินค้า. เปรียบเทียบ ownership scope ของ CRM กับขอบเขตองค์กรของเอกสารขาย ก่อนอ่านการคำนวณและการแปลงเอกสาร. คู่มือ commercial documents เชื่อมต่อไปยัง CN/DN, ใบวางบิล, ใบส่งของ และคำขอซื้อ.
7. **โครงการและงานส่งมอบ** — จาก Deal ที่เชื่อมงานส่งมอบ อ่าน Project และ Task controller เพื่อเห็นความสัมพันธ์ของเจ้าของ สมาชิก และผู้รับผิดชอบงาน. ProjectAccess กับ TaskAccess มีขอบเขตการมองเห็นต่างกัน จึงต้องอ่านทั้งคู่ก่อนเพิ่มหรือย้ายงาน. คู่มืออธิบายการเชื่อมต้นทุนโครงการกับค่าใช้จ่ายซึ่งจะตามต่อในขั้นการเงิน.
8. **ใบแจ้งหนี้และรับจ่าย** — ต่อจากเอกสารขายและต้นทุนโครงการ ตาม Invoice ไปยัง Payment และ Expense ไปยัง VendorPayment. อ่านสถานะ ยอดคงเหลือ transaction, idempotency และ reversal ร่วมกัน เพราะการรับจ่ายมีผลต่อทั้งเอกสารและบัญชี. เส้นทางจ่ายเต็มจำนวนกับจ่ายบางส่วนมี implementation แยกกัน จึงไม่ควรอนุมาน guards จากเส้นทางเดียว.
9. **จัดซื้อและคลังสินค้า** — จากต้นทุนและเจ้าหนี้ ตาม PO ไปยัง Goods Receipt และ stock movements ที่ใช้ติดตามปริมาณกับต้นทุน. InventoryOperations เพิ่ม warehouse, bin, lot, transfer และ stock count บนข้อมูลสินค้าเดียวกัน. อ่านเงื่อนไขรับเกิน ยอดติดลบ และการจัดสรร lot ในคู่มือ ก่อนเปลี่ยนการคำนวณ ledger.
10. **บัญชี FX และ Treasury** — จากธุรกรรมรับจ่ายกับ stock อ่าน FinancialJournalService เพื่อเห็นการแปลงเหตุการณ์ธุรกิจเป็นบัญชีคู่และการกลับรายการ. FxRateService เชื่อม snapshot สกุลเงิน ส่วน BankStatementController เชื่อมรายการธนาคารกับการกระทบยอด. คู่มือบัญชีและ Treasury แยก GL, งวดบัญชี, สินทรัพย์, เงินสดย่อย และเช็คให้เห็นขอบเขตของยอดแต่ละชนิด.
11. **พนักงาน เวลา และเงินเดือน** — เมื่อเข้าใจผลทางบัญชีแล้ว อ่าน Employee master แยกจากบัญชี User และตามเวลาเข้างานผ่าน attendance reconciliation. PayrollService นำข้อมูลค่าจ้าง เวลา OT และนโยบายมาคำนวณรอบเงินเดือน ก่อนอนุมัติและบันทึกบัญชี. คู่มือรวม roster, การลา และพนักงานไม่มี login เพื่อให้เห็นข้อจำกัดของข้อมูล employee-centric กับ user-centric.
12. **BOM และงานผลิต** — ต่อจากสินค้า คลัง และต้นทุนบัญชี อ่าน BOM กับ Work Order เพื่อเห็นการผลิตที่ใช้ข้อมูลร่วมกัน. ProductionCostingService เชื่อมการแตกวัตถุดิบ การเปลี่ยนสถานะ ต้นทุน stock และ GL. คู่มือระบุข้อจำกัดของ rework, currency และการตรวจ stock เพื่อไม่ตีความสถานะว่าแทนขั้นตอนโรงงานได้ครบทั้งหมด.
13. **เอกสาร อนุมัติ และแจ้งเตือน** — หลังรู้ธุรกรรมหลัก อ่าน DocumentService และ WorkflowEngineService ซึ่งเป็นส่วนกลางที่ใช้ข้ามโมดูล. แยกเวอร์ชันไฟล์ scan/parent access และ retention ออกจากขั้นอนุมัติที่เปลี่ยนสถานะเอกสารธุรกิจ. NotificationOutboxService แยกการเตรียมข้อความออกจากการส่ง พร้อมเงื่อนไข dedupe และ retry.
14. **ช่องทางเชื่อมภายนอก** — ต่อจากสิทธิ์และกลไกกลาง เปรียบเทียบ External Read API/Webhooks กับ Portal/Payment Gateway ซึ่งมี principal และ authentication ต่างกัน. อ่านคู่มือ OCR และ e-Tax ประกอบ เพื่อแยก provider configuration, human review, simulation และเส้นทาง network จริง. การมี class หรือสถานะในฐานข้อมูลไม่ยืนยันว่า credentials, signature หรือ provider production ใช้งานได้จริง.
15. **ตัวชี้วัด Tests และ Operations** — ปิดท้ายด้วย Dashboard และ Cash Flow เพื่อทวนว่าตัวชี้วัดดึงข้อมูลและสมมติฐานจากโมดูลใด. อ่าน end-to-end test กับ phpunit.xml เพื่อเข้าใจ contract ที่ทดสอบและ environment ที่ใช้ โดยการมี tested_by edge ไม่ใช่ผลว่าทดสอบผ่าน. จากนั้นใช้คู่มือ Operations เชื่อมการติดตั้ง queue และ scheduler กับพฤติกรรมของระบบที่เรียนรู้มา.

## ข้อจำกัดที่ยังต้องทราบ

- Parser คืนโครงสร้างได้ 827 ไฟล์; อีก 34 ไฟล์อ่านเนื้อหาและเก็บ semantic file-level nodes ครบ ไม่มี unreadable files
- Anonymous Laravel migrations ไม่คืน symbols ย่อย และ OpenAPI parser คืน sections จึงไม่อ้างว่ากราฟมี ERD ทุกตารางหรือ route catalog ทุก endpoint
- Calls/inherits มาจากหลักฐานใน source; ไม่ใช่ trace ของ runtime ทุก branch
- Neighbor-map soft cap 10 warnings ตัดเฉพาะ context รอบไฟล์ศูนย์กลาง แต่ importMap/edges ยังคงครบ
- Orphan warnings หมายถึงไม่มีความสัมพันธ์ที่จับได้ในกราฟ ไม่ได้แปลว่าไฟล์ถูกข้าม ไม่เพิ่มเส้นเชื่อมที่ไม่มีหลักฐานเพื่อกลบ warning
- `tested_by` แสดงความเกี่ยวข้องกับ tests ไม่ใช่ผลรันทดสอบผ่าน รอบนี้ไม่ได้รัน PHP/build/browser application suites ใหม่
- เอกสารและกราฟเป็น snapshot ของ working tree ไม่ได้ยืนยัน production credentials, provider compliance หรือความถูกต้องของธุรกรรมจริงทุกกรณี

## ปัญหาที่พบและผลการแก้

- รอบแรกไม่มี plugin core dist: ติดตั้ง dependencies และ build core สำเร็จก่อนเริ่ม scan
- Phase 4 เคยหยุดเพราะ agent usage limit: ทำต่อจาก topology/กราฟเดิมจน layers ครบ ไม่ได้ข้าม phase
- Auto-review ปฏิเสธการดาวน์โหลดแล้วรัน prebuilt package จาก GitHub โดยตรง เพราะโค้ดภายนอกยังไม่ผ่านการตรวจ; ใช้ Dashboard source/dependencies ที่ติดตั้งในเครื่องแทนสำเร็จ
- Sandbox ปกติบล็อกการตรวจ localhost ด้วย EACCES; รันคำสั่งอ่านตรวจ localhost ที่ได้รับอนุญาตแล้วผ่าน page/graph/token gate
- Dashboard แสดง working-tree warning 32 ไฟล์ที่ยังไม่ commit รวมเอกสารที่สร้างใหม่ เพราะ commit metadata ไม่แทน uncommitted changes; กราฟรอบนี้วิเคราะห์ working tree ตาม inventory และไม่ได้สร้าง commit หรือซ่อน warning

## เปิด Dashboard อีกครั้ง

ใช้ skill `understand-dashboard` กับ project `C:/LocalDevine/www/ERP` แล้วใช้ URL ที่มี `?token=` จาก server output; token เปลี่ยนตาม session จึงไม่บันทึก token ไว้ในเอกสารที่เข้า Git

Scratch files และ topology/batch logs ย้ายเก็บใน `.ua/.trash-*` โดยคง scan inventory ไว้สำหรับ incremental analysis
