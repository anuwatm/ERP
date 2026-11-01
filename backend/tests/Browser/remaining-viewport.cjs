const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'Chrome responsive emulation; actual Product dialog; no physical mobile keyboard claim', cases: [], errors: [] };
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        for (const width of [1280, 375]) for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: theme });
            await context.addInitScript(t => localStorage.setItem('theme', t), theme);
            await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
            const page = await context.newPage(); page.setDefaultTimeout(20000);
            page.on('pageerror', e => report.errors.push(e.message));
            await page.goto(base + '/login');
            await page.locator('[name=email]').fill('remaining-owner@fixture.test');
            await page.locator('[name=password]').fill('local-acceptance-password');
            await page.locator('button[type=submit]').click();
            await page.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
            await page.goto(base + '/products');
            await page.getByRole('button', { name: '+ Create Item', exact: true }).click();
            const dialog = page.getByRole('dialog').last();
            await page.locator('#products-name').fill('Synthetic responsive draft');
            const expand = dialog.getByRole('button', { name: /^Expand .* dialog$/ });
            if (await expand.count()) {
                await expand.click();
                await dialog.getByRole('button', { name: 'Normal size', exact: true }).click();
            }
            const resizedWidth = width === 1280 ? 900 : 320;
            await page.setViewportSize({ width: resizedWidth, height: 640 });
            await page.waitForTimeout(100);
            const header = await dialog.locator('header').boundingBox();
            assert(header.x >= -1 && header.x + header.width <= resizedWidth + 1);
            assert.equal(await page.locator('#products-name').inputValue(), 'Synthetic responsive draft');
            for (let i = 0; i < 24; i++) {
                await page.keyboard.press(i < 12 ? 'Tab' : 'Shift+Tab');
                assert(await dialog.evaluate(e => e.contains(document.activeElement)), 'Focus escaped dialog');
            }
            await page.screenshot({ path: 'temp/remaining-20261029/viewport-' + width + '-' + theme + '.png' });
            await page.setViewportSize({ width, height: 900 });
            report.cases.push({ width, theme, resizedWidth, expanded: width >= 640, tabSteps: 24, draftRetained: true, headerContained: true, result: 'PASS' });
            await context.close();
        }
        report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/viewport-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
