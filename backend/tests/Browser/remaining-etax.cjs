const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'e-Tax configuration UI; isolated fixture; mock configuration only; no provider call', cases: [] };
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
        const page = await context.newPage(); page.setDefaultTimeout(20000);
        await page.goto(base + '/login');
        await page.locator('[name=email]').fill('remaining-owner@fixture.test');
        await page.locator('[name=password]').fill('local-acceptance-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
        await page.goto(base + '/e-tax');
        await page.evaluate(async () => { await window.axios.post('/confirm-password', { password: 'local-acceptance-password' }); });
        await page.getByRole('button', { name: 'Configure Submission', exact: true }).click();
        const form = page.locator('#etax-config-editor');
        const originalMode = await form.locator('select:has(option[value=manual_export])').inputValue();
        const originalSignature = await form.locator('select:has(option[value=xml_dsig])').inputValue();
        await form.locator('select:has(option[value=manual_export])').selectOption('mock');
        await form.locator('select:has(option[value=xml_dsig])').selectOption('external');
        await page.getByRole('button', { name: 'Save Configuration', exact: true }).first().click();
        await form.waitFor({ state: 'detached' });
        await page.getByRole('button', { name: 'Configure Submission', exact: true }).click();
        assert.equal(await form.locator('select:has(option[value=manual_export])').inputValue(), 'mock');
        assert.equal(await form.locator('select:has(option[value=xml_dsig])').inputValue(), 'none');
        report.cases.push({ modeSaved: 'mock', serverNormalizedSignature: 'none', reopenUsesServerValues: true, result: 'PASS' });
        await form.locator('select:has(option[value=manual_export])').selectOption(originalMode);
        await form.locator('select:has(option[value=xml_dsig])').selectOption(originalSignature);
        await page.getByRole('button', { name: 'Save Configuration', exact: true }).first().click();
        await form.waitFor({ state: 'detached' });
        await context.close(); report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/etax-config-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
