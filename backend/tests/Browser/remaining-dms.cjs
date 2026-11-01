const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'Isolated synthetic fixture; actual DMS dialogs', cases: [], errors: [] };
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        for (const width of [1280, 375]) for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: theme });
            await context.addInitScript(t => localStorage.setItem('theme', t), theme);
            await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
            const page = await context.newPage();
            page.setDefaultTimeout(15000);
            page.on('pageerror', e => report.errors.push(e.message));
            await page.goto(base + '/login');
            await page.locator('[name=email]').fill('remaining-owner@fixture.test');
            await page.locator('[name=password]').fill('local-acceptance-password');
            await page.locator('button[type=submit]').click();
            await page.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
            await page.goto(base + '/documents');
            for (const item of [
                { button: 'New Category', field: '#cat-renewal-days', value: '91' },
                { button: 'New Retention Policy', field: '#ret-days', value: '91' },
                { button: 'Upload Document', field: '#doc-change-note', value: 'Synthetic version note' },
            ]) {
                await page.getByRole('button', { name: item.button, exact: true }).click();
                const dialog = page.getByRole('dialog').last();
                await dialog.locator('header').waitFor({ state: 'visible' });
                const field = dialog.locator(item.field);
                const original = await field.inputValue();
                await field.fill(item.value);
                await field.press('Tab');
                await dialog.getByRole('button', { name: /^Minimize .* dialog$/i }).click();
                await page.getByRole('button', { name: /^Restore .* draft$/i }).click();
                if (await field.inputValue() !== item.value) throw Error('Minimize lost draft');
                await dialog.getByRole('button', { name: /^Close .* dialog$/i }).click();
                await page.getByRole('button', { name: 'Keep editing', exact: true }).click();
                if (await field.inputValue() !== item.value) throw Error('Keep lost draft');
                await dialog.getByRole('button', { name: /^Close .* dialog$/i }).click();
                await page.getByRole('button', { name: 'Discard changes', exact: true }).click();
                await page.getByRole('button', { name: item.button, exact: true }).click();
                if (await page.locator(item.field).inputValue() !== original) throw Error('Discard retained draft');
                await page.getByRole('dialog').last().getByRole('button', { name: /^Close .* dialog$/i }).click();
                report.cases.push({ width, theme, button: item.button, changedField: item.field, status: 'PASS' });
            }
            await context.close();
        }
        report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/dms-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
