const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8143';
const directory = 'temp/phase2881-delivery';
const ids = JSON.parse(fs.readFileSync(directory + '/identities.json', 'utf8'));
const report = { scope: 'Synthetic local Chrome; no real payment/provider/operational DB', cases: [], pageErrors: [] };
(async () => {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
  try {
    for (const width of [1280, 375]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } });
      await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
      const page = await context.newPage();
      page.setDefaultTimeout(60000);
      page.on('pageerror', e => report.pageErrors.push(e.message));
      await page.goto(base + '/');
      const login = await page.evaluate(async () => (await fetch('/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ email: 'delivery-manager@fixture.test', password: 'synthetic-delivery-password' }) })).status);
      assert.equal(login, 200);
      const terminal = ids.terminals['D' + width];
      await page.evaluate(async terminal => {
        const state = await (await fetch('/state', { headers: { Accept: 'application/json' } })).json();
        if (!state.shifts.some(s => s.terminal_id === terminal)) {
          const opened = await fetch('/shifts', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ terminal_id: terminal, opening_cash: '0' }) });
          if (!opened.ok) throw new Error('Synthetic shift not available');
        }
      }, terminal);
      await page.goto(base + '/deliveries');
      const name = 'Synthetic browser COD ' + width + ' ' + Date.now();
      const create = page.locator('section form');
      await create.locator('[name=settlement_mode]').selectOption('cod');
      await create.locator('[name=recipient]').fill(name);
      await create.locator('[name=phone]').fill('000-TEST');
      await create.locator('[name=address]').fill('Synthetic private address only');
      await create.locator('summary').click();
      await create.locator('input[type=checkbox]').first().check();
      const submit = async form => {
        report.stage = await form.locator('[name=action]').inputValue().catch(() => 'create');
        console.log('browser stage', width, report.stage);
        try {
          await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), form.locator('button[type=submit]').click()]);
        } catch (error) {
          report.visibleAlerts = await page.getByRole('alert').allTextContents();
          await page.screenshot({ path: directory + '/failure-' + width + '.png', fullPage: true });
          throw error;
        }
      };
      await submit(create);
      const row = () => page.locator('article').filter({ hasText: name });
      const form = action => row().locator('form:has(input[name="action"][value="' + action + '"])');
      await row().waitFor();
      const id = await row().getAttribute('data-delivery-id');
      await row().locator('summary').filter({ hasText: 'ค่าจัดส่ง / บรรจุภัณฑ์' }).click();
      await form('fees').locator('[name=delivery_fee]').fill('5.00');
      await form('fees').locator('[name=packaging_fee]').fill('2.00');
      await form('fees').locator('[name=manager_password]').fill('synthetic-delivery-password');
      await submit(form('fees'));
      const stale = await context.newPage();
      await stale.goto(base + '/deliveries');
      await form('ready').locator('[name=terminal_id]').selectOption(terminal);
      await submit(form('ready'));
      const staleForm = stale.locator('article[data-delivery-id="' + id + '"] form:has(input[name="action"][value="ready"])');
      await staleForm.locator('[name=terminal_id]').selectOption(terminal);
      await staleForm.locator('button[type=submit]').click();
      await staleForm.getByRole('alert').filter({ hasText: 'ข้อมูลหรือสถานะเปลี่ยนแล้ว' }).waitFor();
      await stale.close();
      await form('dispatch').locator('[name=courier_id]').selectOption(ids.staff_id);
      await submit(form('dispatch'));
      await submit(form('deliver'));
      const collect = form('collect');
      assert.equal(await collect.locator('[name=amount]').inputValue(), '17.70');
      const routeUrl = base + await collect.getAttribute('action');
      let committedStatus = null;
      const loseResponse = async r => {
        const saved = await r.fetch({ maxRedirects: 0, postData: r.request().postDataBuffer() });
        committedStatus = saved.status();
        await r.abort('internetdisconnected');
      };
      await page.route(routeUrl, loseResponse);
      await collect.locator('button[type=submit]').click();
      await collect.getByRole('alert').filter({ hasText: 'เชื่อมต่อไม่สำเร็จ' }).waitFor();
      assert.equal(committedStatus, 200);
      assert.equal(await collect.locator('[name=amount]').inputValue(), '17.70');
      await page.unroute(routeUrl, loseResponse);
      await submit(collect);
      await form('remit').locator('[name=terminal_id]').selectOption(terminal);
      await form('remit').locator('[name=manager_password]').fill('synthetic-delivery-password');
      await submit(form('remit'));
      assert((await row().textContent()).includes('ส่งร้าน 17.70'));
      await submit(form('receive'));
      await form('refund').locator('[name=terminal_id]').selectOption(terminal);
      await form('refund').locator('[name=manager_password]').fill('synthetic-delivery-password');
      await submit(form('refund'));
      await form('inspect').locator('[name=decision]').selectOption('restock');
      await form('inspect').locator('[name=condition_verified]').check();
      await form('inspect').locator('[name=manager_password]').fill('synthetic-delivery-password');
      await submit(form('inspect'));
      assert((await row().textContent()).includes('คืนลูกค้า 17.70'));
      const { DatabaseSync } = require('node:sqlite');
      const fixture = new DatabaseSync(directory + '/database.sqlite', { readOnly: true });
      try {
        assert.equal(fixture.prepare('select count(*) as n from pos_delivery_cash where delivery_id = ?').get(id).n, 2);
        assert.equal(fixture.prepare("select count(*) as n from pos_delivery_reservations where delivery_id = ? and status = 'reserved'").get(id).n, 0);
        assert.equal(fixture.prepare('select count(*) as n from pos_orders').get().n, 0);
        assert.equal(fixture.prepare('select count(*) as n from pos_payments').get().n, 0);
      } finally { fixture.close(); }
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.screenshot({ path: directory + '/delivery-' + width + '.png', fullPage: true });
      const slip = await context.newPage();
      await slip.goto(base + '/deliveries/' + id + '/slip');
      assert((await slip.textContent('body')).includes('ไม่ใช่ใบเสร็จ'));
      await slip.close();
      report.cases.push({ width, deliveryId: id, result: 'PASS', checked: ['create', 'fees', 'reserve', 'stale-version-409', 'dispatch', 'deliver', 'lost-response-after-commit', 'retry', 'collect-once', 'remit', 'receive-return', 'custody-refund-once', 'manager-restock', 'db-no-sale-payment', 'slip', 'horizontal-containment'] });
      await context.close();
    }
    assert.deepEqual(report.pageErrors, []);
    report.status = 'PASS';
  } catch (error) {
    report.status = 'FAIL'; report.error = error.message; process.exitCode = 1;
  } finally {
    await browser.close();
    fs.writeFileSync(directory + '/browser.json', JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
  }
})();
