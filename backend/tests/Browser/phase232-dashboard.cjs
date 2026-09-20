// Run against the disposable fixture only. Optional argv[2]: bundled node_modules.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.argv[2] ? path.join(process.argv[2], 'playwright') : 'playwright');
const base = 'http://127.0.0.1:8012';
const output = path.resolve(__dirname, '../../../temp/phase232-browser');
const results = [];
const errors = [];

async function context(browser) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    // External fonts are unnecessary for functional checks and unavailable offline.
    await ctx.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
    const page = await ctx.newPage();
    page.on('pageerror', error => errors.push(error.message));
    return { ctx, page };
}

async function login(page, name) {
    await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
    await page.getByRole('textbox', { name: 'Email Address', exact: true }).fill(name + '@phase232.test');
    await page.getByRole('textbox', { name: 'Password', exact: true }).fill('password');
    await page.getByRole('button', { name: 'Sign in to ERP Dashboard', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
}

async function inspect(page, name, routes) {
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => localStorage.setItem('theme', theme), theme);
            for (const route of routes) {
                const response = await page.goto(base + route, { waitUntil: 'domcontentloaded' });
                assert.equal(response.status(), 200, name + route);
                await page.locator('h1').first().waitFor();
                const snapshot = await page.locator('body').ariaSnapshot();
                assert(snapshot.includes('heading'), 'Accessible heading missing');
                const dimensions = await page.evaluate(() => ({ width: document.documentElement.scrollWidth, viewport: innerWidth }));
                assert(dimensions.width <= dimensions.viewport, `${name}${route}: horizontal overflow ${JSON.stringify(dimensions)}`);
                const key = `${name}-${route.replaceAll('/', '') || 'root'}-${width}-${theme}`;
                fs.writeFileSync(path.join(output, key + '.aria.txt'), snapshot);
                await page.screenshot({ path: path.join(output, key + '.png'), fullPage: true });
                results.push({ role: name, route, width, theme, status: response.status(), overflow: false });
                console.log('PASS', key);
            }
        }
    }
}

(async () => {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        let { ctx, page } = await context(browser);
        await login(page, 'owner');
        await inspect(page, 'owner', ['/dashboard', '/executive-dashboard', '/sales-dashboard', '/finance-dashboard', '/delivery-dashboard', '/procurement-dashboard', '/hr-dashboard', '/my-work']);
        await page.setViewportSize({ width: 1440, height: 1000 });
        for (const route of ['/executive-dashboard', '/finance-dashboard', '/delivery-dashboard', '/sales-dashboard']) {
            await page.goto(base + route, { waitUntil: 'domcontentloaded' });
            await page.getByRole('combobox', { name: 'Period', exact: true }).selectOption('custom');
            await page.getByLabel('From', { exact: true }).fill('2026-09-20');
            await page.getByLabel('To', { exact: true }).fill('2026-09-01');
            await page.getByRole('button', { name: 'Apply', exact: true }).click();
            assert.match(await page.getByRole('alert').innerText(), /start date/);
            await page.getByRole('combobox', { name: 'Period', exact: true }).selectOption('all_time');
            await page.getByRole('button', { name: 'Apply', exact: true }).click();
            await page.waitForURL(/period=all_time/);
            await page.reload({ waitUntil: 'domcontentloaded' });
            assert.equal(await page.getByRole('combobox', { name: 'Period', exact: true }).inputValue(), 'all_time');
            results.push({ role: 'owner', route, filterErrorAndRefresh: true });
        }
        await page.goto(base + '/finance-dashboard', { waitUntil: 'domcontentloaded' });
        const summary = page.locator('summary').filter({ hasText: 'View data table' }).first();
        await summary.focus();
        await page.keyboard.press('Enter');
        assert(await summary.locator('..').getAttribute('open') !== null, 'Keyboard table disclosure');
        const drill = page.locator('main a[href*="invoice_id="]').first();
        if (await drill.count()) {
            await drill.focus();
            await page.keyboard.press('Enter');
            await page.waitForURL(/invoice_id=/);
            await page.goBack({ waitUntil: 'domcontentloaded' });
            assert(new URL(page.url()).pathname === '/finance-dashboard');
        }
        results.push({ role: 'owner', keyboardChart: true, invoiceDrilldown: await drill.count() > 0 });
        await ctx.close();
        ({ ctx, page } = await context(browser));
        await login(page, 'readonly');
        await inspect(page, 'readonly', ['/dashboard', '/finance-dashboard']);
        assert(!((await page.locator('body').innerText()).includes('Recent Payments')), 'Unauthorized payment widget');
        await page.goto(base + '/invoices', { waitUntil: 'domcontentloaded' });
        assert.equal(await page.getByRole('button', { name: /New Invoice|Create Invoice/ }).count(), 0);
        await ctx.close();
        ({ ctx, page } = await context(browser));
        await login(page, 'payroll');
        await inspect(page, 'payroll', ['/hr-dashboard']);
        assert(!((await page.locator('body').innerText()).includes('Pending Leave')), 'Unauthorized HR widget');
        await ctx.close();
        ({ ctx, page } = await context(browser));
        await login(page, 'denied');
        const denied = await page.goto(base + '/finance-dashboard', { waitUntil: 'domcontentloaded' });
        assert.equal(denied.status(), 403);
        results.push({ role: 'denied', financeStatus: 403 });
        await ctx.close();
        const links = JSON.parse(fs.readFileSync(path.join(output, 'links.json'), 'utf8'));
        for (const party of ['customer', 'supplier']) {
            ({ ctx, page } = await context(browser));
            await page.goto(links[party], { waitUntil: 'domcontentloaded' });
            await inspect(page, 'portal-' + party, ['/portal']);
            const text = await page.locator('body').innerText();
            assert(!text.includes('HIDDEN-INVOICE'), 'Portal party isolation');
            if (party === 'customer') {
                assert(text.includes('VISIBLE-INVOICE'));
                assert.equal(await page.getByRole('button', { name: 'Accept', exact: true }).count(), 1);
            } else {
                await page.getByRole('button', { name: 'Submit Vendor Bill', exact: true }).click();
                const dialog = page.locator('[role="dialog"][data-headlessui-state="open"]').last();
                await dialog.waitFor({ state: 'attached' });
                assert.equal(await dialog.getAttribute('data-headlessui-state'), 'open');
                await page.keyboard.press('Escape');
                await page.locator('[role="dialog"][data-headlessui-state="open"]').waitFor({ state: 'detached' });
            }
            results.push({ role: party, portalIsolation: true, permittedAction: true });
            await ctx.close();
        }
        assert.deepEqual(errors, [], 'Browser runtime errors');
    } finally {
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({ results, errors }, null, 2));
        await browser.close();
    }
    console.log(`Dashboard browser acceptance: ${results.length} checks passed`);
})().catch(error => { console.error(error); process.exitCode = 1; });
