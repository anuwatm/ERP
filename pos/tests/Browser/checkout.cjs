const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = 'http://127.0.0.1:8128';
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const page = await context.newPage(); const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(base, { waitUntil: 'networkidle' });
        await page.getByLabel('อีเมล').fill('cashier@pos.test');
        await page.getByLabel('รหัสผ่าน').fill('local-fixture-password');
        await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
        await page.getByRole('button', { name: 'เปิดกะขาย' }).waitFor();
        await page.getByLabel('เงินตั้งต้น').fill('100.00');
        await page.getByRole('button', { name: 'เปิดกะขาย' }).click();
        await page.getByRole('button', { name: /น้ำดื่มทดสอบ/ }).click();
        await page.setViewportSize({ width: 390, height: 1000 });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Cart unit selector must fit mobile viewport');
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.getByLabel('รับเงินสด').fill('20.00');
        await page.getByRole('button', { name: 'ยืนยันรับเงินสด', exact: true }).click();
        await page.getByText('รวม 10.70 · รับ 20.00 · ทอน 9.30', { exact: true }).waitFor();
        await page.getByRole('button', { name: 'ปิด', exact: true }).click();
        await page.waitForFunction(() => [...document.querySelectorAll('button')].some(button => button.textContent === 'ตรวจสถานะ' && !button.disabled));
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Overflow at ${width}`);
            await page.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/checkout-${width}.png`), fullPage: true });
        }
        await page.getByLabel('ยอดนับจริง').fill('110.70');
        await page.getByRole('button', { name: 'ปิดกะและบันทึกผลต่าง' }).click();
        await page.getByText('ผลต่างเงินสด: 0.00', { exact: true }).waitFor();
        assert.deepEqual(errors, []);
        const result = { login: 'pass', openShift: 'pass', checkout: 'pass', change: '9.30', closeShift: 'pass', responsive: [1440, 390], consoleErrors: errors };
        fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/result.json'), JSON.stringify(result, null, 2));
        console.log(JSON.stringify(result));
    } finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
