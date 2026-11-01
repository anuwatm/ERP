# ผลตรวจงานค้าง Local — 2026-10-30

สถานะ: ผล local ผ่านตามรายการและข้อจำกัดด้านล่าง อ่าน [ทะเบียนงาน](REMAINING_WORK_20261029.md). Phase28.8ยังdeferred.

## ขอบเขตและการแยกข้อมูล

ใช้ HTTP loopback 127.0.0.1:8139 กับ SQLite fixture แยกใน temp/remaining-20261029. ผู้ใช้/องค์กร/เอกสาร/API keys/magic links ทั้งหมดเป็นsynthetic. ไม่เปิดเผยหรือแก้ .env, configured operational DB หรือข้อมูลลูกค้าจริง. Browserบล็อกnetworkนอกfixture; Bunny fontsที่ถูกบล็อกไม่ใช่JavaScript exception. ไม่มีการส่งอีเมลหรือเรียกproviderจริง.

## ผลที่ยืนยันแล้ว

- ERP regression: 538 tests รวม2skipped / 9,424 assertions; skippedต้องใช้isolated Phase23MySQL/Phase24MySQL. ไม่ถือว่าข้าม=ผ่าน
- POS:45 tests/268 assertions; Frontend38tests ผ่านทั้งหมด
- 2FA:10tests/68assertions. focused suiteสุดท้าย32tests/203assertions
- Thai PDF:Invoice/PO/50-Tawi อย่างละ1หน้า มีSarabun regular/bold ฝังในPDF; extractภาษาไทยได้ ไม่มี??? และตรวจภาพแล้ว
- Contextual dialogs:36กรณีที่1280/375px light/dark เทียบค่าฟอร์ม/ไฟล์หลังMinimize/Keep/Discard ครอบคลุมExpense/Invoice edit, Asset/Voucher proof, Division/Department create/edit และSupplier Portal
- ExternalAPI HTTP9กรณี; key UI desktop/mobile ครบcreate/secret-once/edit/replacement-keyลดscope/revoke; secretไม่ค้างหลังclose/reload
- Read-only role:14กรณี desktop/mobile; Productsใช้products.manageตามrouteเดิมจึง403, หน้าที่มีread permissionเข้าดูได้โดยไม่มีปุ่มเขียน และPOSTตรง403
- Diagram18/18ผ่านshowcase9checksและcontainmentทั้ง1440×900,1600×1000,1920×1080,2048×1320 light/dark. ตรวจภาพ14ไฟล์ที่แก้ด้วยAI; receipt visualReview=pending ไม่ใช่human sign-off
- Restore drill:SQLite synthetic backup→restore,163tables, row countsตรง,quick_check=ok,FK violations0. ไม่ใช่MySQL/production/POS full DR

- Browser matrix:344รายการ =284PASS +60NO_CREATE_SURFACE,0FAIL; 1280/375px × light/dark,เปรียบเทียบค่าทั้งฟอร์มหลังKeep/Discard ไม่ใช่แก้ทุกfieldทีละช่อง
- Coordination8กรณี; responsive/focus4กรณี; DMS12กรณี; e-Tax normalization/reopen1กรณี

## หลักฐาน

ผล JSON และ test logs อยู่ใน [acceptance/20261030](../document/acceptance/20261030/README.md). คำสั่งและfixture อยู่ใน backend/tests/Browser/remaining-*. ไม่ใช้NO_CREATE_SURFACEเป็นPASS.

- Resilience4PASS: ProductและPDF responseหายหลังcommit; uploadRowsBeforeLostResponse=1, retryDocuments=1;1019products/open3482ms, PDF110824bytes/retry1427ms; sessionหายจำลองด้วยclear cookiesและre-auth
- TypeScript/Vite build และ scoped ESLint/Pint ผ่าน; git diff --check ผ่าน (มีเพียงline-ending notices)
- Understand rescan/publishดู [pipeline](../document/UNDERSTAND_PIPELINE.md); source/coverage auditอยู่ .ua/analysis-report.json

## บั๊กที่แก้

- DMS/HR/Workflow/Delivery Order/GRN:Discardต้องresetข้อมูล/ข้อผิดพลาด/สถานะย่อ และdirty guardตรวจข้อมูลครบ
- Employee:Minimize/Restore,Keep/Discard และsubmission key
- Project members:เปลี่ยนเฉพาะroleต้องถือว่าdirty
- Expense:เทียบdefaultของrecordครบ ใช้form.isDirty รวมdate; project_idยังไม่มีcontrolในUI จึงไม่อ้างว่าทดสอบprojectผ่านbrowser
- Stock Adjustment:prefillสินค้า/ต้นทุนเป็นbaselineสะอาด;Discardresetค่าและbarcode lookup
- e-Tax F023:กลับเป็นCreateEditDialog คงfields/routesเดิม,step-up auth,ล้างsecretหลังsave และreopenใช้ค่าauthoritativeจากserver
- Network error:แจ้งผู้ใช้ในactive dialog ร่าง/ไฟล์อยู่และretryไม่ซ้ำ;ไม่ให้แถบแจ้งเตือนบังfooter
- Dashboard:แก้month overflow ให้trendครบ12เดือน;ปรับPhase5route assertionให้ตรงread-only APIที่เพิ่มภายหลัง

## ข้อจำกัด

Browserเป็นChromeบนเครื่องนี้และresponsive emulation ไม่ใช่physical mobile keyboard/touchหรือbrowserทุกยี่ห้อ. MatrixคงDateของbrowserเพื่อเทียบfresh-draft defaultsอย่างทำซ้ำได้ แต่timers/networkยังเดินจริง;การวัดlatencyอยู่ในscriptแยกที่ไม่freezeเวลา. ไม่อ้างผลquery-budget/concurrency/SLAจากSQLiteแทนMariaDB. ไม่เรียกwebhook receiver,OCR/e-Tax/payment providerหรืออุปกรณ์จริง.

Geminiตรวจเฉพาะ6ไฟล์POSConfigที่อนุญาต [ผลและhash](../document/POS_CONFIG_GEMINI_REVIEW_20261029.md);ไม่ได้ส่งERP UI fixesหรือsecretsไปตรวจ.
