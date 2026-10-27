const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(path.join(process.argv[2], 'playwright'));
const base = 'http://127.0.0.1:8128';
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const errors = [];
    try {
        async function surface() {
            const context = await browser.newContext({viewport:{width:1440,height:1000}});
            await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
            const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message)); return page;
        }
        const staff = await surface(); await staff.goto(base, {waitUntil:'networkidle'});
        await staff.getByLabel('อีเมล').fill('manager@pos.test'); await staff.getByLabel('รหัสผ่าน').fill('local-fixture-password');
        await staff.getByRole('button',{name:'เข้าสู่ระบบ',exact:true}).click();
        await staff.getByRole('button',{name:'เปิดกะขาย'}).waitFor();
        await staff.getByLabel('เงินตั้งต้น').fill('0.00'); await staff.getByRole('button',{name:'เปิดกะขาย'}).click();
        await staff.getByRole('button',{name:'สร้าง QR เมนูจำลอง'}).click();
        const link = staff.getByRole('link',{name:'เปิดเมนูจำลอง'}); await link.waitFor();
        const menu = await surface(); await menu.goto(new URL(await link.getAttribute('href'),base).href,{waitUntil:'networkidle'});
        await menu.getByLabel('จำนวน น้ำดื่มทดสอบ').fill('1');
        const submitted = menu.waitForResponse(r => r.url().endsWith('/menu/requests') && r.request().method()==='POST');
        await menu.getByRole('button',{name:'ส่งคำขอให้พนักงาน'}).click();
        const request = await (await submitted).json(); assert.equal(request.status,'pending');
        await staff.getByRole('button',{name:'ตรวจสถานะ',exact:true}).click();
        const card = staff.locator('div.panel').filter({hasText:request.id});
        await card.getByRole('button',{name:'ตรวจรับ / ตรวจใหม่'}).click();
        await card.getByRole('button',{name:'นำเข้าตะกร้า'}).click();
        await staff.getByLabel('รับเงินสด').fill('20.00'); await staff.getByRole('button',{name:'ยืนยันรับเงินสด',exact:true}).click();
        await staff.getByText('รวม 10.70 · รับ 20.00 · ทอน 9.30',{exact:true}).waitFor();
        await menu.getByRole('button',{name:'ตรวจสถานะ',exact:true}).click();
        await menu.getByRole('heading',{name:'ชำระเงินและรับสินค้าแล้ว'}).waitFor();
        for(const width of [1440,390]) {
            await menu.setViewportSize({width,height:1000});
            assert(await menu.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Public menu overflow ${width}`);
            await menu.screenshot({path:path.resolve(__dirname,`../../../temp/phase28-pos-browser/menu-${width}.png`),fullPage:true});
        }
        await staff.getByRole('button',{name:'ปิด',exact:true}).click();
        await staff.waitForFunction(()=>[...document.querySelectorAll('button')].some(b=>b.textContent==='ตรวจสถานะ'&&!b.disabled));
        await staff.getByLabel('ยอดนับจริง').fill('10.70'); await staff.getByRole('button',{name:'ปิดกะและบันทึกผลต่าง'}).click();
        await staff.getByText('ผลต่างเงินสด: 0.00',{exact:true}).waitFor();
        assert.deepEqual(errors,[]);
        const result={qrMenu:'pass',anonymousRequest:'pass',staffAcceptance:'pass',sharedCheckout:'pass',customerStatus:'completed',responsive:[1440,390],consoleErrors:errors};
        fs.writeFileSync(path.resolve(__dirname,'../../../temp/phase28-pos-browser/menu-result.json'),JSON.stringify(result,null,2));
        console.log(JSON.stringify(result));
    } finally {await browser.close();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
