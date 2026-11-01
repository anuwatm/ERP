const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8144';
(async () => {
  const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
  const report = { scope: 'Layout after button wrapping polish; no sale mutations', cases: [] };
  try {
    for (const type of ['retail', 'restaurant', 'pharmacy']) {
      const context = await browser.newContext();
      await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
      const page = await context.newPage();
      await page.goto(base + '/');
      const status = await page.evaluate(async type => (await fetch('/login', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ email: type + '@membership.fixture.test', password: 'synthetic-membership-password' }) })).status, type);
      assert.equal(status, 200);
      await page.reload();
      if (type !== 'restaurant') await page.getByRole('button', { name: 'Synthetic ' + type + ' item', exact: false }).click();
      for (const width of [1280, 375]) {
        await page.setViewportSize({ width, height: 900 });
        const picker = page.locator('.membership-picker');
        await picker.scrollIntoViewIfNeeded();
        const metrics = await picker.evaluate(p => ({ overflow: document.documentElement.scrollWidth > innerWidth + 1, labelsFit: [...p.querySelectorAll('button')].every(b => b.scrollWidth <= b.clientWidth + 1 && getComputedStyle(b).whiteSpace === 'nowrap') }));
        assert.equal(metrics.overflow, false);
        assert.equal(metrics.labelsFit, true);
        report.cases.push({ business: type, width, ...metrics });
      }
      await context.close();
    }
    report.result = 'PASS';
  } catch (e) {
    report.result = 'FAIL'; report.error = e.message; process.exitCode = 1; console.error(e.message);
  } finally {
    fs.writeFileSync('temp/phase2882-membership/layout-report.json', JSON.stringify(report, null, 2));
    await browser.close();
  }
})();
