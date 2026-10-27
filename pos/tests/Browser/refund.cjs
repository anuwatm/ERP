const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = 'http://127.0.0.1:8128';
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const errors = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
        const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message));
        await page.goto(base, { waitUntil: 'networkidle' });
        await page.getByLabel('อีเมล').fill('manager@pos.test'); await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password');
        await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
        await page.getByRole('button', { name: 'เปิดกะขาย' }).waitFor();
        await page.getByLabel('เงินตั้งต้น').fill('100.00'); await page.getByRole('button', { name: 'เปิดกะขาย' }).click();
        await page.getByRole('button', { name: /น้ำดื่มทดสอบ/ }).click();
        await page.getByLabel('รับเงินสด').fill('20.00');
        const saleResponse = page.waitForResponse(r => r.url().endsWith('/checkout') && r.request().method() === 'POST');
        await page.getByRole('button', { name: 'ยืนยันรับเงินสด', exact: true }).click();
        const sale = await (await saleResponse).json(); assert.equal(sale.total, '10.70');
        await page.getByRole('button', { name: 'ปิด', exact: true }).click();
        await page.getByLabel('บิลต้นฉบับ').selectOption(sale.sale_id);
        await page.getByLabel('จำนวนคืน น้ำดื่มทดสอบ').fill('1');
        await page.getByLabel('รับสินค้ากลับเข้าพื้นที่รอตรวจ').check();
        await page.getByLabel('ยืนยันรหัสผ่าน Manager').fill('local-fixture-password');
        const refunded = page.waitForResponse(r => r.url().endsWith('/local-refunds') && r.request().method() === 'POST');
        await page.getByRole('button', { name: 'ยืนยันจ่ายคืนเงินสด' }).click();
        const response = await refunded; assert.equal(response.status(), 200); const refund = await response.json();
        assert.equal(refund.total, '10.70'); assert.equal(refund.lines[0].return_status, 'quarantined');
        await page.getByRole('status').filter({ hasText: 'คืนเงิน 10.70' }).waitFor();
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Refund overflow ${width}`);
            await page.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/refund-${width}.png`), fullPage: true });
        }
        await page.getByLabel('ยอดนับจริง').fill('100.00'); await page.getByRole('button', { name: 'ปิดกะและบันทึกผลต่าง' }).click();
        await page.getByText('ผลต่างเงินสด: 0.00', { exact: true }).waitFor(); assert.deepEqual(errors, []);
        const result = { managerReauthentication: 'pass', sourceRefund: 'pass', quarantine: 'pass', closeShift: 'pass', responsive: [1440, 390], consoleErrors: errors };
        fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/refund-result.json'), JSON.stringify(result, null, 2)); console.log(JSON.stringify(result));
    } finally { await browser.close(); }
})().catch(e => { console.error(e.message); process.exitCode = 1; });
