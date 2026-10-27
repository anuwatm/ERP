const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = 'http://127.0.0.1:8128';
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true }); const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
        await page.goto(base, { waitUntil: 'networkidle' });
        await page.getByLabel('อีเมล').fill('manager@pos.test'); await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password');
        await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
        await page.getByRole('button', { name: 'เปิดกะขาย' }).waitFor(); await page.getByLabel('เงินตั้งต้น').fill('100.00'); await page.getByRole('button', { name: 'เปิดกะขาย' }).click();
        for (const [direction, amount] of [['in', '20.00'], ['out', '30.00']]) {
            await page.getByLabel('ทิศทางเงิน').selectOption(direction); await page.getByLabel('จำนวนเงิน Cash In-Out').fill(amount);
            await page.getByLabel('เอกสารอ้างอิง Cash In-Out').fill(`SYNTHETIC-BROWSER-${direction}`); await page.getByLabel('ยืนยันรหัสผ่าน Cash In-Out').fill('local-fixture-password');
            const response = page.waitForResponse(r => r.url().endsWith('/local-cash-movements') && r.request().method() === 'POST');
            await page.getByRole('button', { name: 'บันทึก Cash In-Out', exact: true }).click();
            const posted = await response; assert.equal(posted.status(), 200); const movement = await posted.json(); assert.equal(movement.amount, amount); assert.equal(movement.direction, direction);
            await page.getByRole('status').filter({ hasText: `${direction === 'in' ? 'นำเงินเข้า' : 'นำเงินออก'} ${amount}` }).waitFor();
        }
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 }); assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Cash movement overflow ${width}`);
            await page.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/cash-movements-${width}.png`), fullPage: true });
        }
        await page.getByLabel('ยอดนับจริง').fill('90.00'); await page.getByRole('button', { name: 'ปิดกะและบันทึกผลต่าง' }).click(); await page.getByText('ผลต่างเงินสด: 0.00', { exact: true }).waitFor();
        assert.deepEqual(errors, []); const result = { cashIn: '20.00', cashOut: '30.00', expectedCash: '90.00', closeShift: 'pass', responsive: [1440, 390], consoleErrors: errors };
        fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/cash-movements-result.json'), JSON.stringify(result, null, 2)); console.log(JSON.stringify(result));
    } finally { await browser.close(); }
})().catch(e => { console.error(e.message); process.exitCode = 1; });
