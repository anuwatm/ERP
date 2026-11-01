const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'Read-only role; isolated synthetic user', cases: [], errors: [] };
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        for (const width of [1280, 375]) {
            const context = await browser.newContext({ viewport: { width, height: 900 } });
            await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
            const page = await context.newPage(); page.setDefaultTimeout(15000);
            page.on('pageerror', e => report.errors.push(e.message));
            await page.goto(base + '/login');
            await page.locator('[name=email]').fill('remaining-viewer@fixture.test');
            await page.locator('[name=password]').fill('local-acceptance-password');
            await page.locator('button[type=submit]').click();
            await page.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
            for (const path of ['/products', '/customers', '/projects', '/tasks', '/roles', '/settings/organization-structure']) {
                const response = await page.goto(base + path);
                if (path === '/products') {
                    assert.equal(response.status(), 403, 'Products page requires products.manage');
                    report.cases.push({ width, path, http: 403, result: 'PASS' });
                    continue;
                }
                assert.equal(response.status(), 200, path);
                const enabledWrites = await page.getByRole('button').evaluateAll(es => es.filter(e => !e.disabled && /^(\+\s*)?(Create|New|Add|Edit|Delete|Disable|Enable)\b/i.test(e.textContent.trim())).map(e => e.textContent.trim()));
                assert.equal(enabledWrites.length, 0, path + ': ' + enabledWrites.join(','));
                report.cases.push({ width, path, enabledWrites: 0, result: 'PASS' });
            }
            const denied = await page.evaluate(() => window.axios.post('/products', { name: 'Forbidden synthetic product', type: 'service', price: 0, cost: 0 }, { headers: { Accept: 'application/json' }, validateStatus: () => true }).then(r => r.status));
            assert.equal(denied, 403);
            report.cases.push({ width, directWriteStatus: denied, result: 'PASS' });
            await context.close();
        }
        report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/permission-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
