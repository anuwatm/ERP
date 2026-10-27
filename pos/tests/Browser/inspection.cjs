const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = 'http://127.0.0.1:8128';
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true }); const errors = [];
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route('**/*', r => new URL(r.request().url()).origin === base ? r.continue() : r.abort());
    const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
    await page.goto(base, { waitUntil: 'networkidle' }); await page.getByLabel('อีเมล', { exact: true }).fill('pharmacy-manager@pos.test'); await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password'); await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
    const panel = page.getByRole('region', { name: 'ตรวจของคืนร้านขายยา', exact: true }); await panel.waitFor();
    await panel.getByRole('button', { name: 'โหลดของรอตรวจ', exact: true }).click();
    const select = panel.getByLabel('รายการของคืน', { exact: true });
    await select.locator('option').nth(1).waitFor({ state: 'attached' }); const value = await select.locator('option').nth(1).getAttribute('value');
    for (const decision of ['restock', 'destroy']) {
      await select.selectOption(value); await panel.getByLabel('จำนวนตรวจในหน่วยฐาน').fill('1.0000'); await panel.getByLabel('ผลตรวจ', { exact: true }).selectOption(decision);
      if (decision === 'restock') {
        assert.equal(await panel.getByRole('button', { name: 'ยืนยันผลตรวจของคืน' }).isDisabled(), true);
        for (const label of ['บรรจุภัณฑ์สมบูรณ์', 'ตรวจยืนยันการเก็บรักษา', 'ตรวจ lot ตรงต้นฉบับ']) await panel.getByLabel(label, { exact: true }).check();
      }
      await panel.getByLabel('หลักฐานและเหตุผลตรวจ').fill('Synthetic browser inspection'); await panel.getByLabel('รหัสผ่านผู้ตรวจ').fill('local-fixture-password');
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 }); assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Inspection overflow ${width}`);
        await page.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/inspection-${decision}-${width}.png`), fullPage: true });
      }
      const saved = page.waitForResponse(r => r.url().endsWith('/local-return-inspections') && r.request().method() === 'POST'); await panel.getByRole('button', { name: 'ยืนยันผลตรวจของคืน', exact: true }).click();
      const response = await saved; assert.equal(response.status(), 200); assert.equal((await response.json()).decision, decision);
      await panel.getByRole('status').filter({ hasText: 'รอ ERP ตรวจรับ' }).waitFor();
      await page.waitForFunction(() => !Array.from(document.querySelectorAll('button')).some(b => b.textContent === 'โหลดของรอตรวจ' && b.disabled));
    }
    assert.deepEqual(errors, []); const result = { restock: 'pending ERP acceptance', destroy: 'recorded', responsive: [1440, 390], consoleErrors: errors };
    fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/inspection-result.json'), JSON.stringify(result, null, 2)); console.log(JSON.stringify(result));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
