const assert = require('node:assert/strict'); const fs = require('node:fs'); const path = require('node:path');
const { chromium } = require(path.join(process.argv[2], 'playwright')); const base = 'http://127.0.0.1:8129';
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true }); const errors = [];
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route('**/*', r => new URL(r.request().url()).origin === base ? r.continue() : r.abort());
    const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
    await page.goto(base, { waitUntil: 'networkidle' }); await page.getByLabel('อีเมล', { exact: true }).fill('tax@pos.test'); await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password'); await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
    await page.getByRole('button', { name: 'เปิดกะขาย', exact: true }).waitFor(); await page.getByLabel('เงินตั้งต้น').fill('0.00'); await page.getByRole('button', { name: 'เปิดกะขาย', exact: true }).click();
    await page.getByRole('button', { name: /สินค้าภาษีจำลอง/ }).click(); await page.getByLabel('รับเงินสด', { exact: true }).fill('20.00');
    const paid = page.waitForResponse(r => r.url().endsWith('/checkout') && r.request().method() === 'POST'); await page.getByRole('button', { name: 'ยืนยันรับเงินสด', exact: true }).click(); const response = await paid; assert.equal(response.status(), 200); const sale = await response.json(); assert.equal(sale.total, '10.70');
    await page.getByRole('button', { name: 'ปิด', exact: true }).click();
    const panel = page.getByRole('region', { name: 'เอกสารภาษี POS', exact: true }); await panel.getByRole('button', { name: 'โหลดบิลและเอกสารภาษี' }).click(); await panel.getByLabel('บิลขายสำหรับภาษี', { exact: true }).selectOption(sale.sale_id);
    const abb = panel.getByRole('link', { name: /^ABB-/ }); await abb.waitFor(); const abbName = await abb.innerText();
    await panel.getByLabel('ชื่อผู้ซื้อ', { exact: true }).fill('ผู้ซื้อจำลอง'); await panel.getByLabel('เลขผู้เสียภาษีผู้ซื้อ', { exact: true }).fill('0000000000000'); await panel.getByLabel('ที่อยู่ผู้ซื้อ', { exact: true }).fill('ที่อยู่ผู้ซื้อทดสอบ');
    const issued = page.waitForResponse(r => r.url().endsWith('/tax-documents') && r.request().method() === 'POST'); await panel.getByRole('button', { name: 'ออกเอกสารภาษีทดสอบ' }).click(); const fullHttp = await issued; assert.equal(fullHttp.status(), 200); const full = await fullHttp.json(); assert.equal(full.replaces_document_no, abbName); assert.equal(full.total, '10.70');
    const link = panel.getByRole('link', { name: full.document_no, exact: true }); await link.waitFor();
    const print = await context.newPage(); print.on('pageerror', e => errors.push(e.message)); await print.goto(new URL(await link.getAttribute('href'), base).href, { waitUntil: 'networkidle' }); await print.getByText('ผู้ซื้อจำลอง', { exact: false }).waitFor();
    for (const width of [1440, 390]) { await print.setViewportSize({ width, height: 1000 }); assert(await print.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Tax print overflow ${width}`); await print.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/tax-full-${width}.png`), fullPage: true }); }
    await print.reload({ waitUntil: 'networkidle' }); await print.getByText(full.document_no, { exact: false }).first().waitFor();
    await page.getByLabel('ยอดนับจริง').fill('10.70'); await page.getByRole('button', { name: 'ปิดกะและบันทึกผลต่าง', exact: true }).click(); await page.getByText('ผลต่างเงินสด: 0.00', { exact: true }).waitFor();
    assert.deepEqual(errors, []); const result = { abb: abbName, full: full.document_no, replacesOriginal: true, total: '10.70', etax: 'awaiting_provider', printAndReprint: 'pass', responsive: [1440, 390], consoleErrors: errors }; fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/tax-documents-result.json'), JSON.stringify(result, null, 2)); console.log(JSON.stringify(result));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
