# Local acceptance evidence — 2026-10-30

[ผลรวมและข้อจำกัด](../../../docs/LOCAL_ACCEPTANCE_20261030.md). ข้อมูลทั้งหมดsynthetic;ไม่มีAPIsecret,magiclink,configured DBหรือ.envในชุดนี้.

- [Main matrix](browser-all-fields-final.json):284PASS/60NO_CREATE_SURFACE/0FAIL;ไม่รวมNO_CREATEเป็นPASS
- [Contextual](context-browser.json):36PASS; [DMS](dms-browser.json):12PASS
- [Coordination](coordination-browser.json):8PASS; [Viewport/focus](viewport-browser.json):4PASS
- [Read-only role](permission-browser.json):14PASS
- [API HTTP](api-http.json):9PASS; [API browser lifecycle](api-browser.json):2PASS
- [e-Tax config](etax-config-browser.json):1PASS;ไม่มีprovider call
- [Network/session/upload](resilience-browser.json):อ่านstatusและcommit evidenceในreport
- [Synthetic restore](restore-drill.json):163tables/quick_check=ok/FK0;ไม่ใช่production DR
- [ERP regression](backend-regression-final.log):538tests/9424assertions/2skipped(MySQL)
- [POS](pos-regression.log):45tests/268assertions; [Frontend](frontend-final.log):38PASS

Reproduce:เตรียมfixtureด้วย backend/tests/Browser/prepare-remaining*.php และremaining-environment.php, เปิดremaining-router.phpที่127.0.0.1:8139 แล้วรันremaining-*.cjs. ต้องติดตั้งChrome/Playwrightและปรับruntime pathตามเครื่อง. Scriptsแก้เฉพาะSQLitefixtureภายใต้temp/remaining-20261029;ไม่ชี้ไปฐานจริง. Main matrixใช้MATRIX_PATHSจากdocument/acceptance/20261030/matrix-paths.json, MATRIX_INCLUDE_EDIT=1 และMATRIX_EXTRA_PATTERNตามscript/รายงาน. Browser Dateคง2026-10-29T12:00:00Zในmatrix;latency scriptsไม่freezeเวลา.

Diagram receiptsแยกที่document/DIAGRAM_DELIVERY_20261029.json และDIAGRAM_VISUAL_CHECK_20261029.json. Gemini POS Config scope6ไฟล์อยู่ในdocument/POS_CONFIG_GEMINI_REVIEW_20261029.md.
