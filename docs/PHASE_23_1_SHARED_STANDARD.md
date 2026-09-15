# Phase 23.1.2 Shared Dialog & Form Standard

สถานะ: shared standard เสร็จ 2026-09-13; rollout ทุกโมดูลยังอยู่ใน Phase 23.1.4

## สิ่งที่เปลี่ยน

| ส่วน | ผลลัพธ์ |
| --- | --- |
| CreateEditDialog | Create/Edit บนหน้าเดิม, normal 640px / expanded / minimized, header/footer คงที่, scroll เฉพาะ body |
| Layout | grid ตามพื้นที่ 1/2/3/4 คอลัมน์, visualViewport รองรับพื้นที่ที่เหลือเมื่อ keyboard เปิด |
| Draft | memory เท่านั้น; localStorage เก็บเฉพาะขนาด; active editor เดียว; parent suspended ระหว่าง quick-create |
| Discard / accessibility | X, Cancel, Escape, overlay, switch และ navigation ผ่าน guard; browser beforeunload; focus trap/restore และ indexed error focus |
| EditorFields | typed inputs, searchable references, เก็บ current value ที่เลือกไม่ได้แล้ว; create callback เฉพาะ workflow ที่อนุญาต |
| Deal → Contact | สร้าง Contact แล้วเลือกกลับอัตโนมัติ โดย title/ข้อมูล Deal เดิมยังอยู่; backend ตรวจ organization และสิทธิ์เดิม |
| Retry protection | UUID ต่อ draft ใน memory; สี่ Sales create routes บันทึกผลกับ domain write ใน transaction; retry เดิมคืนผลเดิม; payload ต่างกันถูกปฏิเสธ |
| Session confirmation | ตรวจ session ก่อน save และยืนยันรหัสผ่านใน dialog เดิม; password ไม่ persist; backend password.confirm ยังบังคับเหมือนเดิม |
| EditorLineItems | เพิ่ม/ลบ/แก้ในแถว, stable row key, internal scroll และ errors ตาม items.N.field |
| EditorFinancialSummary / useEditorPreview | แสดง string จำนวนเงินจาก backend; ซ่อนยอดเก่าระหว่างโหลด/error; ยกเลิกและละทิ้ง response ที่เก่ากว่า |
| EditorAttachments | File อยู่กับ draft, คง selected file และ existing links/status; ไฟล์ restricted ไม่มี URL; การปิดฟอร์มไม่ลบ DMS เดิม |

## ข้อตกลงสำหรับ rollout

- โมดูลเป็นเจ้าของ useForm, payload, validation, permissions, sections, associations และ domain calculations; shell ไม่ใช่ generic CRUD engine
- onSubmit ใช้ข้อมูลจาก form state; callback ทำงานหลัง asynchronous session preflight จึงไม่ควรอ่าน event.currentTarget หลัง await
- shared shell ปัจจุบันใช้ authenticated internal password-confirm flow; Portal ต้องมี adapter ตาม authentication ของ Portal ก่อนนำไปใช้
- Finance ต้องต่อ preview endpoint ของโมดูลเดิม ครอบคลุม VAT/WHT/discount/FX/rounding; component ไม่คำนวณภาษีใหม่ การ save ต้องคำนวณ/ตรวจซ้ำที่ backend
- Attachment adapter ต้องใช้ upload/DMS authorization และ lifecycle เดิม; ไม่สร้างลิงก์ให้ผู้ไม่มีสิทธิ์ ไม่ลบไฟล์เดิมตอน cancel
- เปลี่ยนหรือลบ line item ต้องจัดการ indexed errors ไม่ให้เลื่อนไปผิดแถว
- reset submission key เมื่อเริ่ม draft ใหม่หรือบันทึกสำเร็จเท่านั้น; เก็บ key เมื่อผลตอบกลับไม่แน่นอน
- สี่ Sales forms ใช้มาตรฐานแล้ว ส่วน Finance/DMS primitives ตรวจใน isolated fixture; ไม่ได้ย้ายทุกหน้าการเงินใน Phase นี้

## หลักฐานตรวจสอบ

- Backend: Phase2SalesTest + Phase231SalesDialogsTest + PasswordConfirmationTest ผ่าน 19 tests / 251 assertions
- Frontend: history guard 9, preview 3, component contracts 3 — รวม 15 tests ผ่าน
- TypeScript, scoped ESLint, Pint และ production build ผ่าน
- Browser บน isolated SQLite: Deal → quick-create Contact → เลือกกลับ พร้อม draft เดิม; re-auth ไม่เปลี่ยนหน้า; minimize/restore; switch/navigation discard
- Standard fixture: normal 640px, expanded 1280px; mobile 375×812 และ landscape 812×375 ไม่มี document/form horizontal overflow และ footer อยู่ใน viewport
- Invoice backend preview: 2×100 + 1×100, VAT 7% → VAT 21.00 / Total 321.00 THB
- validation items.1.description แสดงที่แถว 2 พร้อมย้าย focus; แถว 1 และ title ไม่หาย
- attachment.txt ยังคงแสดงหลัง minimize/restore และ validation failure; restricted existing document ยังคงเป็นข้อความไม่มีลิงก์
- Escape เปิด discard confirmation

ข้อจำกัด: ยังไม่อ้างผลจาก physical mobile keyboard, Safari หรือ MySQL concurrent-request stress test; module-specific Finance/DMS acceptance ต้องตรวจระหว่าง rollout อีกครั้ง ส่วน inventory 23.1.1 เป็น baseline วันที่ 2026-09-11 ไม่ใช่การอ้างว่าทุก surface migrate แล้ว

## Migration และ fixture

เพิ่ม database/migrations/2026_09_12_000001_create_form_submissions_table.php และ migrate ฐานข้อมูล local ที่ตั้งค่าไว้สำเร็จ 2026-09-13; environment อื่นต้อง migrate ก่อนใช้ frontend รุ่นนี้

```powershell
cd backend
php artisan migrate --path=database/migrations/2026_09_12_000001_create_form_submissions_table.php
php tests/Browser/prepare-phase231.php
php -S 127.0.0.1:8011 -t public tests/Browser/phase231-router.php
```

หน้าทดสอบ /phase231/standard ลงทะเบียนเฉพาะ fixture router ไม่อยู่ใน production routes; validation fixture ไม่สร้างเอกสารธุรกิจ และใช้ InvoiceController เป็นแหล่งคำนวณ
