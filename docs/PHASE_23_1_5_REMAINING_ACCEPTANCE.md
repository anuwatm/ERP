# Phase 23.1.5 — Local Browser Acceptance (2026-10-30)

ผลเดิมที่ deferred วันที่2026-09-15 ถูกแทนด้วยหลักฐาน local รอบนี้. ใช้ Chrome156.0.8078.48, isolated SQLite/loopback, synthetic owner และ read-only user. ไม่แก้ฐานจริง. [ผลรวม](LOCAL_ACCEPTANCE_20261030.md) · [JSON/logs](../document/acceptance/20261030/README.md).

| ID | ผลและหลักฐาน | ขอบเขต |
|---|---|---|
| B01 | matrix284PASS/0FAIL;60NO_CREATEไม่นับผ่าน; contextual36และDMS12PASS | 1280/375px × light/dark; Create/Edit/Minimize/Restore/Keep/Discard; snapshotค่าeditableที่แสดงทั้งหมด แก้fieldหลักและdate/role/file edge cases ไม่ได้แก้ทุกfieldทีละช่อง; hidden/readonlyใช้backend compatibility tests |
| B02 | coordination8PASS รวมclean Inventory/Payroll switch และheld POST | processingปิด/ย่อ/Escไม่ได้, POST1ครั้ง |
| B03 | matrix + viewport4PASS + coordination history Back | keyboard Tab/Shift+Tab, resize900/320, Expand/Normal, focus/header containment, Keepรักษาdraft; responsive emulation ไม่ใช่physical phone |
| B04 | resilience-browser.json | ตัดresponseหลังProduct commit, draftอยู่, retryบันทึก1record; ไม่มีfalse success |
| B05 | resilience-browser.json | clear cookiesจำลองunauthenticated session → loginอีกtab → password reconfirm → retry;ไม่ใช่รอTTLจริงหรือbrowserทดสอบrecordลบทุกโมดูล |
| B06 | resilience + coordination | 1,000+products/reference search;จำนวนและเวลาจริงอยู่ในJSON ไม่ใช่MariaDB load/SLA |
| B07 | resilience-browser.json | PDF110824bytes, failure/file retention/retry/latency; multipart transportใช้bytesไฟล์เดิม เพราะChromiumตัดfilebytesจากintercepted postData |
| B08 | coordination + matrix | scroll lockขณะเปิดและคืนหลังปิด;ไม่มีphysical touchscreen acceptance |

แต่ละJSONระบุmodule/path/action/viewport/themeตามกรณีที่รัน. Main matrixมีexpected/actualผ่านassertionsและfield count; context/viewport/permissionแยกreport. หลักฐานChromeนี้ไม่ใช้แทนbrowserทุกยี่ห้อ, real devices, provider integration, production concurrencyหรือowner UAT.
