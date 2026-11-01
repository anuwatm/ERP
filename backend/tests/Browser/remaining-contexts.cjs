const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'Actual contextual forms; synthetic parents/portal only; no financial posting', cases: [], errors: [] };
const add = row => { report.cases.push(row); fs.writeFileSync('temp/remaining-20261029/context-browser.json', JSON.stringify(report,null,2)); };
const snapshot = dialog => dialog.locator('input:not([type=password]):not([type=hidden]),textarea,select').evaluateAll(es => es.filter(e => e.getClientRects().length && !e.disabled && !e.readOnly).map(e => ({ id: e.id.startsWith(':') ? 'generated' : e.id, name: e.name, type: e.type, value: e.type === 'file' ? [...e.files].map(f => f.name + ':' + f.size) : e.type === 'checkbox' ? e.checked : e.value })));
async function exercise(page, name, file = false) {
    const button = () => page.getByRole('button', { name, exact: true }).first();
    await button().click();
    const dialog = page.getByRole('dialog').last();
    await dialog.locator('header').waitFor({ state: 'visible' });
    const initial = await snapshot(dialog);
    if (file) await dialog.locator('input[type=file]').first().setInputFiles('temp/pdf-acceptance-20261029/invoice.pdf');
    else {
        const field = dialog.locator('input:not([type=hidden]):not([type=password]):not([type=file]):not([type=checkbox]):not([type=radio]):not([role=combobox]):not([readonly]):not([disabled]),textarea:not([readonly]):not([disabled])').first();
        await field.waitFor({ state: 'visible' });
        const type = await field.getAttribute('type');
        await field.fill(type === 'date' ? '2026-10-28' : type === 'number' ? '91' : 'Synthetic context draft');
        await field.press('Tab');
    }
    const draft = await snapshot(dialog);
    assert.notDeepEqual(draft, initial, name + ': no actual data changed');
    await dialog.getByRole('button', { name: /^Minimize .* dialog$/ }).click();
    await page.getByRole('button', { name: /^Restore .* draft$/ }).click();
    assert.deepEqual(await snapshot(dialog), draft, name + ': restore changed fields');
    await dialog.getByRole('button', { name: /^Close .* dialog$/ }).click();
    await page.getByRole('button', { name: 'Keep editing', exact: true }).click();
    assert.deepEqual(await snapshot(dialog), draft, name + ': Keep changed fields');
    await dialog.getByRole('button', { name: /^Close .* dialog$/ }).click();
    await page.getByRole('button', { name: 'Discard changes', exact: true }).click();
    await button().click();
    const reopened = page.getByRole('dialog').last();
    await reopened.locator('header').waitFor({ state: 'visible' });
    assert.deepEqual(await snapshot(reopened), initial, name + ': Discard retained fields');
    await reopened.getByRole('button', { name: /^Close .* dialog$/ }).click();
    return { name, checkedFields: initial.length, file, result: 'PASS' };
}
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    let portalState;
    try {
        report.browser = await browser.version();
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
            for (const [path, button, file] of [['/expenses','Edit',false],['/invoices','Edit',false],['/fixed-assets','Upload Proof',true],['/commercial-documents','Upload Proof',true]]) {
                await page.goto(base + path);
                add({ width, theme, path, ...await exercise(page, button, file) });
            }
            await page.goto(base + '/settings/organization-structure');
            for (const [tab, create] of [['Divisions','New Division'],['Departments','New Department']]) {
                await page.getByRole('button', { name: tab, exact: true }).click();
                add({ width, theme, path: '/settings/organization-structure', tab, ...await exercise(page, create) });
                add({ width, theme, path: '/settings/organization-structure', tab, ...await exercise(page, 'Edit') });
            }
            await context.close();
            const portal = await browser.newContext({ ...(portalState ? { storageState: portalState } : {}), viewport: { width, height: 900 }, colorScheme: theme });
            await portal.addInitScript(t => localStorage.setItem('theme', t), theme);
            await portal.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
            const p = await portal.newPage(); p.setDefaultTimeout(20000);
            p.on('pageerror', e => report.errors.push(e.message));
            if (!portalState) {
                const link = JSON.parse(fs.readFileSync('temp/remaining-20261029/synthetic-portal-link.json')).url;
                assert(link.startsWith(base + '/portal/access/'));
                await p.goto(link); await p.waitForURL(base + '/portal');
                portalState = await portal.storageState();
            } else await p.goto(base + '/portal');
            add({ width, theme, path: '/portal', ...await exercise(p, 'Submit Vendor Bill') });
            await portal.close();
        }
        report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/context-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
