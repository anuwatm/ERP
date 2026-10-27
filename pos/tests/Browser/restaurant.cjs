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
    await page.getByLabel('อีเมล', { exact: true }).fill('restaurant@pos.test');
    await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password');
    await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click();
    const panel = page.getByRole('region', { name: 'ร้านอาหาร', exact: true });
    await panel.waitFor();
    if (await page.getByRole('button', { name: 'เปิดกะขาย' }).count()) {
      await page.getByLabel('เงินตั้งต้น').fill('0.00'); await page.getByRole('button', { name: 'เปิดกะขาย' }).click();
    }
    const table = `T-${Date.now()}`;
    await panel.getByLabel('ชื่อโต๊ะ', { exact: true }).fill(table);
    await panel.getByRole('button', { name: 'เพิ่มโต๊ะ', exact: true }).click();
    await panel.getByRole('button', { name: `เลือกโต๊ะ ${table}`, exact: true }).click();
    await panel.getByLabel('เมนูอาหาร', { exact: true }).selectOption({ label: 'อาหารทดสอบ' });
    await panel.getByLabel('จำนวนจาน').fill('2');
    const orderResponse = page.waitForResponse(r => r.url().endsWith('/restaurant/orders') && r.request().method() === 'POST');
    await panel.getByRole('button', { name: 'บันทึกออเดอร์อาหาร', exact: true }).click();
    const orderHttp = await orderResponse; assert.equal(orderHttp.status(), 200); const order = await orderHttp.json();
    for (let i = 0; i < 2; i++) {
      for (const name of ['ส่งครัว', 'ครัวรับออเดอร์', 'เริ่มเตรียม', 'อาหารพร้อม', 'เสิร์ฟ / ส่งมอบ']) {
        await panel.getByRole('button', { name, exact: true }).first().click();
      }
    }
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Restaurant overflow ${width}`);
      await page.screenshot({ path: path.resolve(__dirname, `../../../temp/phase28-pos-browser/restaurant-${width}.png`), fullPage: true });
    }
    const sales = [];
    for (let i = 0; i < 2; i++) {
      await panel.getByLabel('เลือกชำระ อาหารทดสอบ', { exact: true }).first().check();
      await panel.getByRole('button', { name: 'ตรวจยอดบิลอาหาร', exact: true }).click();
      await panel.getByRole('status').filter({ hasText: 'ยอดบิลอาหารรวม VAT 10.70' }).waitFor();
      await panel.getByLabel('รับเงินสดค่าอาหาร', { exact: true }).fill('20.00');
      const paid = page.waitForResponse(r => r.url().endsWith('/restaurant/checkout') && r.request().method() === 'POST');
      await panel.getByRole('button', { name: 'ยืนยันรับเงินค่าอาหาร', exact: true }).click();
      const response = await paid; assert.equal(response.status(), 200); const sale = await response.json();
      assert.equal(sale.total, '10.70'); assert.equal(sale.change, '9.30'); assert.equal(sale.lines[0].stock_effect, 'prepared'); assert.deepEqual(sale.lines[0].allocations, []); sales.push(sale.sale_id);
      await page.getByRole('button', { name: 'ปิด', exact: true }).click();
    }
    await panel.getByRole('button', { name: `ปิดโต๊ะ ${table}`, exact: true }).click();
    await page.getByLabel('ยอดนับจริง').fill('21.40'); await page.getByRole('button', { name: 'ปิดกะและบันทึกผลต่าง', exact: true }).click();
    await page.getByText('ผลต่างเงินสด: 0.00', { exact: true }).waitFor();
    assert.deepEqual(errors, []);
    const result = { order: order.order_id, sales, table: 'closed', splitBills: 2, totalEach: '10.70', responsive: [1440, 390], consoleErrors: errors };
    fs.writeFileSync(path.resolve(__dirname, '../../../temp/phase28-pos-browser/restaurant-result.json'), JSON.stringify(result, null, 2)); console.log(JSON.stringify(result));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
