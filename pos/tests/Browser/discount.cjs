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
        await page.goto(base, { waitUntil: 'networkidle' }); await page.getByLabel('อีเมล', {exact:true}).fill('cashier@pos.test'); await page.getByLabel('รหัสผ่าน', { exact: true }).fill('local-fixture-password');
        await page.getByRole('button', { name: 'เข้าสู่ระบบ', exact: true }).click(); await page.getByRole('button', { name: 'เปิดกะขาย' }).waitFor();
        await page.getByLabel('เงินตั้งต้น').fill('100.00'); await page.getByRole('button', { name: 'เปิดกะขาย' }).click(); await page.getByRole('button', { name: /น้ำดื่มทดสอบ/ }).click();
        await page.getByLabel('ส่วนลด น้ำดื่มทดสอบ').fill('2.00'); await page.getByLabel('อีเมล Manager อนุมัติส่วนลด').fill('manager@pos.test'); await page.getByLabel('รหัสผ่าน Manager อนุมัติส่วนลด').fill('local-fixture-password');
        await page.getByRole('button', { name: 'ตรวจและอนุมัติส่วนลด', exact:true }).click(); await page.getByRole('status').filter({hasText:'ยอดหลังส่วนลดและ VAT: 8.56'}).waitFor();
        for (const width of [1440,390]) {
            await page.setViewportSize({width,height:1000}); assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Discount overflow ${width}`);
            await page.screenshot({path:path.resolve(__dirname,`../../../temp/phase28-pos-browser/discount-${width}.png`),fullPage:true});
        }
        await page.getByLabel('รับเงินสด').fill('20.00'); const paid = page.waitForResponse(r=>r.url().endsWith('/checkout')&&r.request().method()==='POST'); await page.getByRole('button',{name:'ยืนยันรับเงินสด',exact:true}).click();
        const response = await paid; assert.equal(response.status(),200); const sale = await response.json(); assert.equal(sale.total,'8.56'); assert.equal(sale.change,'11.44'); assert.equal(sale.lines[0].discount_amount,'2.00'); assert(sale.discount_approval_id);
        await page.getByRole('button',{name:'ปิด',exact:true}).click(); await page.getByLabel('ยอดนับจริง').fill('108.56'); await page.getByRole('button',{name:'ปิดกะและบันทึกผลต่าง'}).click(); await page.getByText('ผลต่างเงินสด: 0.00',{exact:true}).waitFor();
        assert.deepEqual(errors,[]); const result={managerApproval:'pass',discount:'2.00',taxedNet:'8.56',change:'11.44',closeShift:'pass',responsive:[1440,390],consoleErrors:errors}; fs.writeFileSync(path.resolve(__dirname,'../../../temp/phase28-pos-browser/discount-result.json'),JSON.stringify(result,null,2)); console.log(JSON.stringify(result));
    } finally { await browser.close(); }
})().catch(e=>{console.error(e.message);process.exitCode=1;});
