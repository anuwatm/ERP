# README — เรียกใช้และตั้งต้น POS

คู่มือวันที่ 2026-10-27 สำหรับ local development ที่ผ่านการตรวจรับแล้ว

> **หมายเหตุ:** ยังไม่ได้ทดสอบการเชื่อมต่อกับระบบและอุปกรณ์จริง เช่น Server/firewall/mTLS, เครื่องพิมพ์, เครื่องสแกน และ e-Tax provider. คำสั่งเปิด PHP development server ใช้ทดลองในเครื่องเท่านั้น

## 1. แต่ละประเภทร้านเปิดที่ไหน

ทั้งสามประเภทใช้แอป `pos/` และหน้า `/` เดียวกัน เช่น **http://127.0.0.1:8128/** หลังเปิด local server. ระบบใช้ `business_type` ของสาขาที่บัญชี POS สังกัด ร่วมกับ role และ feature flags; ไม่มี URL แยก `/retail`, `/restaurant` หรือ `/pharmacy` และไม่ได้เปลี่ยนประเภทร้านด้วย query string

| ประเภท | business_type | Flag หลักบน POS | คู่มือ |
| --- | --- | --- | --- |
| ร้านขายของทั่วไป | `retail` | Core ขายเงินสด ไม่ต้องเปิด restaurant/pharmacy | [README ร้านค้าทั่วไป](README_POS_RETAIL.md) |
| ร้านอาหาร | `restaurant` | `POS_LOCAL_RESTAURANT_ENABLED=true` | [README ร้านอาหาร](README_POS_RESTAURANT.md) |
| ร้านขายยา | `pharmacy` | `POS_LOCAL_PHARMACY_ENABLED=true` | [README ร้านขายยา](README_POS_PHARMACY.md) |

บัญชี POS เป็นคนละบัญชีกับ ERP. การสร้าง ERP user หรือเปิดหน้า ERP `/pos-integration` ไม่ได้สร้างบัญชีแคชเชียร์ให้อัตโนมัติ. รุ่นนำร่องใช้หนึ่งสาขาหนึ่งประเภทกิจการ; ไม่เปลี่ยนประเภทสาขาเดิมเพื่อทดลองอีกกิจการ

## 2. ทางเลือก A — ทดลองหน้าจอด้วยข้อมูลจำลอง

เหมาะกับการเปิดดู workflow ก่อน provision ฐาน POS ของตนเอง. ใช้ dependencies ที่ติดตั้งใน `backend/` อยู่แล้ว และ PHP ที่มี BCMath/OpenSSL/SQLite

เปิด PowerShell จาก repository root:

```powershell
Set-Location C:/LocalDevine/www/ERP
node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs
Set-Location pos

php tests/Browser/prepare.php
php tests/Browser/prepare-restaurant.php
php tests/Browser/prepare-pharmacy.php
php -S 127.0.0.1:8128 -t public tests/Browser/router.php
```

เปิด **http://127.0.0.1:8128/** แล้วเลือกบัญชีตามประเภทร้าน:

| ทดลอง | บัญชีจำลอง | Role / สิ่งที่เตรียมไว้ |
| --- | --- | --- |
| ขายของทั่วไป | `cashier@pos.test` | cashier / สินค้าและ quota จำลอง |
| งานผู้จัดการร้านทั่วไป | `manager@pos.test` | manager / ส่วนลด คืนเงิน และ Cash In-Out |
| ร้านอาหาร | `restaurant@pos.test` | manager / เมนู สูตร วัตถุดิบ และตัวเลือกขนาด |
| ร้านขายยา | `pharmacy-manager@pos.test` | qualified manager / ตัวอย่างขาย-คืนสินค้ารอตรวจ |

รหัสผ่านทุกบัญชีในตารางคือ **`local-fixture-password` ใช้เฉพาะ fixture นี้**. ตัวอย่าง Pharmacy เน้นตรวจของคืน ไม่ใช่ชุดสาธิตการจ่ายยาทุกชนิด และบัญชี manager ไม่ใช่ role `pharmacist`

ข้อมูลอยู่ใน `temp/phase28-pos-browser/database.sqlite` แยกจากฐานตาม config ที่ `pos/database/database.sqlite` และ ERP. Scripts เก็บข้อมูลเดิมไว้; `prepare-pharmacy.php` สร้างรายการขาย/คืนจำลองเพิ่มเติม จึงไม่ควรรันซ้ำเป็นงานประจำ. Catalog มีอายุและ quota มีจำนวนจำกัด หากหมดให้เตรียม fixture/catalog ที่เหมาะสม ไม่แก้ยอดหรือลบหลักฐานในฐานใช้งาน

เปลี่ยนประเภทร้านด้วยการออกจากระบบแล้วเข้าด้วยอีกบัญชี หรือแยก browser profile. หลายแท็บใน profile เดียวใช้ session เดียวกัน. หยุด server ด้วย `Ctrl+C`. โหมดนี้ไม่เชื่อม ERP จริง; ตัวเลข “รอ ERP” ไม่จำเป็นต้องลดเป็นศูนย์

## 3. ทางเลือก B — ใช้ฐานและสาขาของตนเองใน local

### เตรียม environment และ assets

1. ติดตั้ง PHP/Composer และ Node dependencies ของ POS หรือใช้ `POS_SHARED_VENDOR=1` เฉพาะเครื่องพัฒนานี้
2. หากยังไม่มี `pos/.env` จึงคัดลอก `pos/.env.example`; อย่าเขียนทับ `.env` เดิม และอย่าคัดลอก ERP `.env`/APP_KEY/DB credentials
3. ปรับค่าต่อไปนี้ใน `pos/.env` ให้ตรงกับฐานที่ต้องการใช้ ตัวอย่างสำหรับ SQLite ที่สร้างไว้ในเครื่องนี้:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8128
DB_CONNECTION=sqlite
DB_DATABASE=C:/LocalDevine/www/ERP/pos/database/database.sqlite
SESSION_DRIVER=file
SESSION_COOKIE=pos_session
SESSION_SECURE_COOKIE=false
POS_RELEASE_APPROVED=false
```

`.env.example` มีค่าเริ่มต้น MySQL จึงต้องเลือก connection ให้ถูกก่อนใช้. ถ้ามี `DB_URL` เดิม ให้ตรวจว่าไม่ได้ชี้คนละฐาน. `SESSION_SECURE_COOKIE=false` เป็นค่าของตัวอย่าง HTTP loopback นี้; การติดตั้ง HTTPS จริงต้องตั้งค่าตามสภาพแวดล้อมจริง

4. ติดตั้งใหม่ที่ APP_KEY ว่างเท่านั้นจึงสร้าง key. **ห้ามสร้าง key ทับของเดิมเมื่อมี encrypted archives**. เก็บ POS RSA private key และตั้ง `POS_INSTANCE_ID`, `POS_SIGNING_KEY_PATH`, `POS_SIGNING_KEY_ID` ให้ตรงกับ public key/instance ที่ ERP ลงทะเบียน
5. ฐานตาม config ในเครื่องนี้รัน migrations แล้ว; ใช้ `migrate:status` ตรวจ ไม่ใช้ `migrate:fresh` หรือ seed ทับข้อมูล. หลังแก้ config ใช้ `config:clear` และเปิด server ใหม่

```powershell
# จาก repository root — build bundle ก่อนเปิดหน้าจอ
node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs
Set-Location pos
$env:POS_SHARED_VENDOR = '1'  # เฉพาะกรณีใช้ vendor ของ backend ใน local
php artisan migrate:status
php artisan config:clear

# เฉพาะติดตั้งใหม่และ APP_KEY ว่างเท่านั้น: php artisan key:generate
```

### สร้างสาขา Terminal และพนักงาน

ใช้คำสั่งในคู่มือประเภทร้านที่เลือก. `pos:branch`/`pos:terminal` ใช้สร้างครั้งแรก; ถ้ามีแล้วให้ใช้ UUID เดิม ไม่รันสร้างซ้ำ. `pos:staff` ถามชื่อและรหัสผ่านผ่าน terminal โดยรหัสผ่านยาวอย่างน้อย 12 ตัวอักษร. เก็บ UUID ที่คำสั่งแสดงเพื่อผูก ERP mapping/authority

จากนั้นผู้ดูแลต้องเตรียม:

- ERP POS Integration ที่ `<ERP_BASE_URL>/pos-integration`: branch/warehouse/customer/actor, business type, Terminal UUID/code, public key และ GL mappings ให้ตรงกับ POS
- Active POS instance/staff authority, catalog/ราคา/VAT/อายุข้อมูล และ quota ของสินค้า หรือวัตถุดิบสำหรับร้านอาหาร. ฐานว่างที่ migrate แล้วไม่ได้มีรายการพร้อมขายให้อัตโนมัติ
- Restaurant ต้องมี BOM/recipe และ option SKU; Pharmacy ต้องมี published unit/conversion policy, lot/expiry และ pharmacist requirements ที่เกี่ยวข้อง
- ถ้าเปิด local ABB/FULL ต้อง provision issuer profile ก่อน; ถ้าเปิด flag แต่ยังไม่มี profile การ checkout จะไม่สำเร็จ
- การ publish/pull จริงผ่าน connector ต้องตั้ง local HTTPS/mTLS และ connection allowlist ก่อนใช้ `php artisan pos:sync <INSTANCE_UUID>` ที่ `backend/`. PHP HTTP server ด้านล่างเพียงเปิด UI ไม่ได้ทำให้ HTTPS connector พร้อมเอง

ค่าระดับสินค้าที่หน้า POS Integration ยังไม่มีฟอร์มแก้ เช่น pharmacy conversion policy ต้องให้ผู้ดูแลเตรียมผ่าน provisioning ที่รองรับก่อน publish; แคชเชียร์ไม่ตั้ง factor/ราคา/lot policy เองในตะกร้า

### เปิดหน้า POS

```powershell
# จาก pos/ หลังเตรียม config, identity, catalog และ quota แล้ว
$env:POS_SHARED_VENDOR = '1'  # ไม่จำเป็นถ้า pos/vendor ติดตั้งแยกแล้ว
php artisan serve --host=127.0.0.1 --port=8128
```

เปิด **http://127.0.0.1:8128/** เข้าด้วยบัญชี POS ของสาขา แล้วเลือก Terminal. ถ้าเปลี่ยน port ให้ใช้ port ที่ server แสดงและปรับ APP_URL ให้ตรง; ไม่ใช้ test router ของทางเลือก A กับฐานสาขาของตนเอง

## 4. Feature flags เพิ่มเติม

| ต้องการใช้ | Flag ใน POS | เงื่อนไข |
| --- | --- | --- |
| QR เมนูร้านทั่วไป/ร้านอาหาร | `POS_LOCAL_PUBLIC_MENU_ENABLED=true` | local/testing และ loopback; Pharmacy ไม่เปิด public menu |
| คืนเงิน/ยกเลิกยอดขาย | `POS_LOCAL_REFUNDS_ENABLED=true` | manager re-auth และ original sale; ERP ต้องเปิด feature ที่ตรงกัน |
| Cash In-Out | `POS_LOCAL_CASH_MOVEMENTS_ENABLED=true` | manager และ ERP cash-clearing mapping |
| ส่วนลด | `POS_LOCAL_DISCOUNTS_ENABLED=true` | published manager ceiling และ fresh manager approval |
| ตรวจของคืนร้านยา | `POS_LOCAL_RETURN_INSPECTIONS_ENABLED=true` | Pharmacy manager + pharmacist qualification ทั้ง POS/ERP |
| Recall | `POS_LOCAL_RECALLS_ENABLED=true` | ต้อง publish คำสั่ง recall จาก ERP ด้วย feature ที่ตรงกัน |
| ABB/FULL | `POS_LOCAL_TAX_DOCUMENTS_ENABLED=true` | issuer profile และ terminal/branch identity พร้อมก่อนขาย |

Flags เหล่านี้ไม่ใช่สวิตช์เลือกประเภทร้าน และไม่ใช่การเปิด production. รายการที่ต้อง sync ให้ตั้งค่าทั้ง POS/ERP ตาม feature; ไม่เปิด flags ทั้งหมดเพื่อแก้ error

## 5. ปัญหาที่พบบ่อย

| อาการ | ตรวจอะไร |
| --- | --- |
| เปิดหน้าแล้วว่าง/ไม่พบ Vite manifest | build POS ด้วย `pos/vite.config.mjs` และตรวจ assets ใต้ `pos/public/build` |
| เข้าไม่ได้ด้วย ERP user | ใช้บัญชีที่สร้างด้วย `pos:staff`; ERP user กับ POS staff แยกกัน |
| ไม่พบ Terminal | บัญชีอยู่สาขาถูกต้อง และ Terminal ของสาขา active หรือไม่ |
| ไม่พบสินค้า/catalog หมดอายุ | publish/sync catalog ที่ยัง valid และ scope ตรงสาขา |
| quota ไม่พอ/lot ขายไม่ได้ | ตรวจ grant, expiry และ recall; อย่าเพิ่ม Stock หรือล้าง quota โดยตรง |
| หน้าร้านอาหารไม่ขึ้น | branch เป็น restaurant, flag เปิด และ APP_ENV เป็น local/testing |
| `403` ตอนขายสินค้าที่ต้องมีเภสัชกร | ใช้ role pharmacist; qualified manager สำหรับตรวจคืนไม่แทน role นี้ |
| `Terminal shift already open` | มีกะเดิมอยู่ ให้ผู้รับผิดชอบปิด/ตรวจตามสิทธิ์ก่อน ไม่เปิดกะซ้อน |
| Cookie/login บน HTTP loopback ไม่คงอยู่ | APP_URL/session cookie/secure-cookie ต้องตรงกับโหมด local ที่ใช้ |
| `423` / recovery fence | หยุดขายและทำ recovery ตาม runbook ห้ามล้าง anchor/fence เพื่อข้ามการตรวจ |
| ไม่ทราบว่ารับเงินสำเร็จหรือยัง | ตรวจ receipt/status ก่อนทำรายการใหม่ ห้ามรับเงินซ้ำเพียงเพราะ timeout |

คู่มือต่อเนื่อง: [Operations](../docs/PHASE_28_OPERATIONS.md) · [API/routes](PHASE_28_API.md) · [Database upgrade](DATABASE_UPGRADE_20261027.md) · [Local acceptance/recovery](PHASE_28_LOCAL_ACCEPTANCE.md)

## Source ที่ใช้ตรวจคู่มือ

[Staff routes](../pos/routes/web.php) · [Local commands](../pos/routes/console.php) · [Config/flags](../pos/config/pos.php) · [Frontend build](../pos/vite.config.mjs)
