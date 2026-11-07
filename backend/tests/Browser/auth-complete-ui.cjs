const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const base = 'http://127.0.0.1:8139';
const output = 'temp/ui-redesign-acceptance';
const ids = JSON.parse(
    fs.readFileSync(output + '/ui-specialized-identities.json', 'utf8'),
);
const report = {
    scope: 'Actual Auth routes and local TOTP enrollment/recovery on synthetic accounts; mailer=array',
    cases: [],
    errors: [],
};
function totp(secret) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bits = [...secret]
        .map((char) => alphabet.indexOf(char).toString(2).padStart(5, '0'))
        .join('');
    const key = Buffer.from(
        bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)),
    );
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const hash = crypto.createHmac('sha1', key).update(counter).digest();
    return ((hash.readUInt32BE(hash[19] & 15) & 0x7fffffff) % 1000000)
        .toString()
        .padStart(6, '0');
}
(async () => {
    const browser = await chromium.launch({
        executablePath: process.env.CHROME_PATH,
        headless: true,
    });
    try {
        for (const width of [320, 1440])
            for (const theme of ['light', 'dark']) {
                async function open() {
                    const context = await browser.newContext({
                        viewport: { width, height: 950 },
                        colorScheme: theme,
                    });
                    await context.addInitScript(
                        (theme) => localStorage.setItem('theme', theme),
                        theme,
                    );
                    await context.route('**/*', (route) =>
                        route
                            .request()
                            .url()
                            .startsWith(base + '/')
                            ? route.continue()
                            : route.abort(),
                    );
                    const page = await context.newPage();
                    page.setDefaultTimeout(20000);
                    page.on('pageerror', (error) =>
                        report.errors.push(error.message),
                    );
                    return { context, page };
                }
                async function check(page, name, style) {
                    await page.locator('main').first().waitFor();
                    await page.evaluate(() => document.fonts.ready);
                    assert.equal(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth >
                                innerWidth + 1,
                        ),
                        false,
                        name + ' overflow',
                    );
                    assert.equal(
                        await page
                            .locator('html')
                            .evaluate((el) => getComputedStyle(el).colorScheme),
                        theme,
                    );
                    await page.screenshot({
                        path:
                            output +
                            '/auth-' +
                            name +
                            '-' +
                            width +
                            '-' +
                            theme +
                            '.png',
                        fullPage: true,
                        ...(style ? { style } : {}),
                    });
                    report.cases.push({
                        name,
                        width,
                        theme,
                        overflow: false,
                        result: 'PASS',
                    });
                }
                async function login(page, email) {
                    await page.goto(base + '/login');
                    await page.locator('[name=email]').fill(email);
                    await page
                        .locator('[name=password]')
                        .fill('local-acceptance-password');
                    await page.locator('button[type=submit]').click();
                    await page.waitForURL((url) => url.pathname != '/login');
                }
                let { context, page } = await open();
                for (const [name, url] of [
                    ['register', '/register'],
                    ['forgot', '/forgot-password'],
                    [
                        'reset',
                        '/reset-password/synthetic-ui-token?email=ui-setup%40fixture.test',
                    ],
                    ['invite', ids.invite],
                ]) {
                    const response = await page.goto(base + url);
                    assert.equal(response.status(), 200, name);
                    await page.locator('form').waitFor();
                    await check(page, name);
                }
                await login(
                    page,
                    'ui-setup-' + width + '-' + theme + '@fixture.test',
                );
                await page.goto(base + '/confirm-password');
                await page.locator('[type=password]').waitFor();
                await check(page, 'confirm-password');
                await page
                    .locator('[type=password]')
                    .fill('local-acceptance-password');
                await page
                    .getByRole('button', { name: 'Confirm', exact: true })
                    .click();
                await page.waitForURL(
                    (url) => url.pathname != '/confirm-password',
                );
                assert.equal(
                    (await page.goto(base + '/two-factor/setup')).status(),
                    200,
                );
                await page.locator('#two-factor-setup-code').waitFor();
                const secret = (
                    await page.locator('main code').first().innerText()
                ).trim();
                assert.match(secret, /^[A-Z2-7]{32}$/);
                await check(
                    page,
                    'two-factor-setup',
                    'code { color: transparent !important; }',
                );
                await page.locator('#two-factor-setup-code').fill(totp(secret));
                await page
                    .getByRole('button', {
                        name: 'Confirm and enable',
                        exact: true,
                    })
                    .click();
                await page.waitForURL(
                    (url) => url.pathname === '/two-factor/recovery-codes',
                );
                await page.locator('#recovery code').first().waitFor();
                assert.equal(await page.locator('#recovery code').count(), 8);
                await check(
                    page,
                    'recovery',
                    'code { color: transparent !important; }',
                );
                await page.reload();
                await page.waitForURL((url) => url.pathname === '/profile');
                report.cases.push({
                    name: 'one-time recovery refresh',
                    width,
                    theme,
                    result: 'PASS',
                });
                await context.close();
                ({ context, page } = await open());
                await login(page, 'ui-unverified@fixture.test');
                await page.goto(base + '/verify-email');
                await page
                    .getByRole('button', {
                        name: 'Resend Verification Email',
                        exact: true,
                    })
                    .waitFor();
                await check(page, 'verify-email');
                await context.close();
                ({ context, page } = await open());
                await login(page, 'ui-challenge@fixture.test');
                await page.waitForURL(
                    (url) => url.pathname === '/two-factor-challenge',
                );
                await page.locator('#two-factor-code').waitFor();
                await check(page, 'two-factor-challenge');
                await context.close();
            }
        assert.deepEqual(report.errors, []);
        report.status = 'PASS';
        console.log('PASS complete Auth UI ' + report.cases.length + ' cases');
    } finally {
        fs.writeFileSync(
            output + '/auth-complete-ui-report.json',
            JSON.stringify(report, null, 2),
        );
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
