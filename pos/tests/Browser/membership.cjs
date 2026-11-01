const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8144';
const directory = 'temp/phase2882-membership';
const ids = JSON.parse(fs.readFileSync(directory + '/identities.json', 'utf8'));
const report = { run: Date.now().toString(), scope: 'Single isolated SQLite app fixture; three business branches; synthetic data only; no real mTLS/devices', cases: [], pageErrors: [] };
(async () => {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
  try {
    for (const type of ['retail', 'restaurant', 'pharmacy']) {
      const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
      const page = await context.newPage();
      page.setDefaultTimeout(20000);
      page.on('pageerror', e => report.pageErrors.push(e.message));
      const api = async (path, data) => page.evaluate(async ({ path, data }) => {
        const response = await fetch(path, { method: data === undefined ? 'GET' : 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, ...(data === undefined ? {} : { body: JSON.stringify(data) }) });
        const body = await response.json();
        if (!response.ok) throw new Error(path + ': ' + response.status + ' ' + JSON.stringify(body));
        return body;
      }, { path, data });
      await page.goto(base + '/');
      await api('/login', { email: type + '@membership.fixture.test', password: 'synthetic-membership-password' });
      let state = await api('/state');
      if (!state.shifts.length) await api('/shifts', { terminal_id: ids[type].terminal, opening_cash: '0.00' });
      for (const width of [1280, 375]) {
        report.stage = type + '/' + width;
        console.log('membership browser', report.stage);
        await page.setViewportSize({ width, height: 900 });
        await page.goto(base + '/');
        await page.locator('.toolbar select').waitFor();
        if (type === 'restaurant') {
          await api('/restaurant/orders', { terminal_id: ids[type].terminal, catalog_version: 'member-v1', request_key: 'browser-' + report.run + '-' + width, channel: 'takeaway', items: [{ product_id: ids[type].product, quantity: 1 }] });
          state = await api('/state');
          const line = state.restaurant_lines.find(l => l.status === 'reserved');
          assert.ok(line);
          for (const action of ['sent', 'acknowledged', 'preparing', 'ready', 'served']) await api('/restaurant/lines/' + line.id + '/' + action, {});
          await page.reload();
          await page.getByLabel('เลือกชำระ Synthetic restaurant item').check();
          await page.getByRole('button', { name: 'ตรวจยอดบิลอาหาร', exact: true }).click();
          await page.getByText('ยอดบิลอาหารรวม VAT 10.70', { exact: true }).waitFor();
        } else {
          await page.getByRole('button', { name: 'Synthetic ' + type + ' item', exact: false }).click();
        }
        const picker = page.locator('fieldset.membership-picker').last();
        await picker.getByLabel('สมาชิกของบิล', { exact: true }).selectOption(ids[type].member);
        await picker.getByLabel('โปรโมชันของบิล', { exact: true }).selectOption(ids[type].promotion);
        const checkout = page.getByRole('button', { name: type === 'restaurant' ? 'ยืนยันรับเงินค่าอาหาร' : 'ยืนยันรับเงินสด', exact: true });
        await (type === 'restaurant' ? page.getByLabel('รับเงินสดค่าอาหาร', { exact: true }) : page.getByLabel('รับเงินสด', { exact: true })).fill('20.00');
        assert.equal(await checkout.isDisabled(), true, 'Cannot sell without current server preview');
        await picker.getByRole('button', { name: 'ตรวจยอดโปรโมชัน', exact: true }).click();
        await picker.getByText('ยอดหลังโปรโมชันรวม VAT 9.63', { exact: true }).waitFor();
        const unmasked = await picker.innerText();
        assert.equal(unmasked.includes('0000000101'), false);
        assert.ok(unmasked.includes('***0101'));
        let cartInvalidatesPreview = false;
        if (type !== 'restaurant') {
          await page.getByRole('textbox', { name: 'จำนวน', exact: true }).fill('2');
          await page.waitForFunction(() => !document.querySelector('.membership-picker [role=status]'));
          assert.equal(await checkout.isDisabled(), true, 'Cart change invalidates quote even with sufficient cash');
          await page.getByRole('textbox', { name: 'จำนวน', exact: true }).fill('1');
          await picker.getByRole('button', { name: 'ตรวจยอดโปรโมชัน', exact: true }).click();
          await picker.getByText('ยอดหลังโปรโมชันรวม VAT 9.63', { exact: true }).waitFor();
          cartInvalidatesPreview = true;
        }
        assert.equal(await checkout.isEnabled(), true);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        assert.equal(overflow, false, 'No horizontal viewport overflow');
        await picker.scrollIntoViewIfNeeded();
        await page.screenshot({ path: directory + '/' + type + '-' + width + '.png' });
        const endpoint = type === 'restaurant' ? '/restaurant/checkout' : '/checkout';
        const successful = page.waitForResponse(r => r.url() === base + endpoint && r.status() === 200);
        let lostResponseReplay = false;
        if (type === 'retail' && width === 1280) {
          let original;
          await page.route(base + endpoint, async route => {
            original = route.request().postDataJSON();
            const committed = await route.fetch();
            assert.equal(committed.status(), 200);
            await route.abort('failed');
          }, { times: 1 });
          await checkout.click();
          await page.getByRole('alert').waitFor();
          const retried = page.waitForRequest(r => r.url() === base + endpoint && r.method() === 'POST');
          await checkout.click();
          assert.equal((await retried).postDataJSON().request_key, original.request_key);
          lostResponseReplay = true;
        } else await checkout.click();
        await page.locator('.receipt').getByText(/รวม 9\.63/).waitFor();
        const receipt = await (await successful).json();
        report.cases.push({ business: type, width, sale_id: receipt.sale_id, masked: true, blockedBeforePreview: true, cartInvalidatesPreview, total: '9.63', noHorizontalOverflow: true, lostResponseReplay });
      }
      await page.goto(base + '/membership');
      await page.locator('[name=member_name]').fill('Synthetic pending ' + type);
      await page.locator('[name=member_phone]').fill('0000000102');
      await page.locator('[name=consent_confirmed]').check();
      await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'ส่งคำขอให้ ERP ตรวจรับ' }).click()]);
      await page.getByText(/pending_review/).first().waitFor();
      state = await api('/state');
      assert.equal(state.membership.members.length, 1, 'Pending enrollment does not grant member access');
      report.cases.push({ business: type, enrollment: 'pending_review', activeMembersUnchanged: true });
      await context.close();
    }
    assert.deepEqual(report.pageErrors, []);
    report.result = 'PASS';
  } catch (error) {
    report.result = 'FAIL';
    report.error = error.message;
    console.error(error.message);
    process.exitCode = 1;
  } finally {
    fs.writeFileSync(directory + '/browser-report.json', JSON.stringify(report, null, 2));
    await browser.close();
  }
})();
