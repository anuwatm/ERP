const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const output = 'temp/ui-redesign-acceptance';
const report = {
    scope: 'Actual BOM and Work Order dialogs on isolated synthetic SQLite; no posting of production transactions',
    cases: [],
    errors: [],
};
(async () => {
    const browser = await chromium.launch({
        executablePath: process.env.CHROME_PATH,
        headless: true,
    });
    try {
        for (const width of [320, 1440])
            for (const theme of ['light', 'dark']) {
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
                page.setDefaultTimeout(15000);
                page.on('pageerror', (error) =>
                    report.errors.push(error.message),
                );
                await page.goto(base + '/login');
                await page
                    .locator('[name=email]')
                    .fill('remaining-owner@fixture.test');
                await page
                    .locator('[name=password]')
                    .fill('local-acceptance-password');
                await page.locator('button[type=submit]').click();
                await page.waitForURL((url) => url.pathname != '/login');
                async function inspect(name, open, field) {
                    await open();
                    let dialog = page.getByRole('dialog', {
                        name,
                        exact: true,
                    });
                    await dialog.waitFor();
                    if (name === 'BOM Explosion') {
                        await dialog
                            .getByRole('heading', {
                                name: 'Multi-Level Assembly Tree',
                                exact: true,
                            })
                            .waitFor();
                        await dialog
                            .getByText(
                                'Synthetic Subassembly Recipe (UI-SUB-BOM-20261107)',
                                { exact: true },
                            )
                            .waitFor();
                        await dialog
                            .getByRole('region', {
                                name: 'Consolidated material demand',
                            })
                            .getByText('3.05', { exact: true })
                            .waitFor();
                        assert.doesNotMatch(
                            await dialog.innerText(),
                            /undefined|NaN/,
                        );
                    }
                    assert.equal(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth >
                                innerWidth + 1,
                        ),
                        false,
                        name + ' overflows',
                    );
                    for (let step = 0; step < 12; step++) {
                        await page.keyboard.press(
                            step < 6 ? 'Tab' : 'Shift+Tab',
                        );
                        assert(
                            await dialog.evaluate((el) =>
                                el.contains(document.activeElement),
                            ),
                            name + ' lost focus',
                        );
                    }
                    await page.screenshot({
                        path:
                            output +
                            '/manufacturing-' +
                            name.replaceAll(' ', '-') +
                            '-' +
                            width +
                            '-' +
                            theme +
                            '.png',
                    });
                    await page.keyboard.press('Escape');
                    await dialog.waitFor({ state: 'hidden' });
                    assert.equal(
                        await page.getByRole('dialog').count(),
                        0,
                        name + ' clean open unexpectedly dirty',
                    );
                    if (field) {
                        await open();
                        dialog = page.getByRole('dialog', {
                            name,
                            exact: true,
                        });
                        const control = dialog.getByLabel(field, {
                            exact: true,
                        });
                        const initial = await control.inputValue();
                        const type = await control.getAttribute('type');
                        const draft =
                            type === 'number'
                                ? '2.5'
                                : type === 'date'
                                  ? '2026-11-06'
                                  : 'ข้อมูลร่างสำหรับตรวจหน้าจอ';
                        await control.fill(draft);
                        await page.keyboard.press('Escape');
                        await page
                            .getByRole('button', {
                                name: 'Keep editing',
                                exact: true,
                            })
                            .click();
                        assert.equal(await control.inputValue(), draft);
                        await page.keyboard.press('Escape');
                        await page
                            .getByRole('button', {
                                name: 'Discard changes',
                                exact: true,
                            })
                            .click();
                        await dialog.waitFor({ state: 'hidden' });
                        await open();
                        dialog = page.getByRole('dialog', {
                            name,
                            exact: true,
                        });
                        assert.equal(
                            await dialog
                                .getByLabel(field, { exact: true })
                                .inputValue(),
                            initial,
                            name + ' discarded value remained',
                        );
                        await page.keyboard.press('Escape');
                        await dialog.waitFor({ state: 'hidden' });
                    }
                    report.cases.push({
                        name,
                        width,
                        theme,
                        focusTrapped: true,
                        overflow: false,
                        dirtyGuard: Boolean(field),
                        result: 'PASS',
                    });
                }
                if (width === 320 && theme === 'dark') {
                    await page.goto(base + '/confirm-password');
                    await page
                        .locator('[name=password]')
                        .fill('local-acceptance-password');
                    await page
                        .getByRole('button', { name: 'Confirm', exact: true })
                        .click();
                    await page.waitForURL(
                        (url) => url.pathname !== '/confirm-password',
                    );
                    await page.goto(base + '/manufacturing/boms');
                    await page
                        .getByRole('button', {
                            name: 'Create BOM',
                            exact: true,
                        })
                        .click();
                    const formDialog = page.getByRole('dialog', {
                        name: 'Bill of Materials',
                        exact: true,
                    });
                    await formDialog.locator('form').evaluate((form) => {
                        form.dispatchEvent(
                            new Event('submit', {
                                bubbles: true,
                                cancelable: true,
                            }),
                        );
                    });
                    await formDialog.locator('#bom-name-error').waitFor();
                    await formDialog
                        .locator('#bom-item-errors')
                        .getByRole('alert')
                        .first()
                        .waitFor();
                    assert.equal(
                        await formDialog
                            .locator('#bom-name')
                            .getAttribute('aria-invalid'),
                        'true',
                    );
                    await page.keyboard.press('Escape');
                    await formDialog.waitFor({ state: 'hidden' });
                    report.validation = 'PASS';
                }
                await page.goto(base + '/manufacturing/boms');
                await inspect(
                    'Bill of Materials',
                    () =>
                        page
                            .getByRole('button', {
                                name: 'Create BOM',
                                exact: true,
                            })
                            .click(),
                    'Recipe / BOM Name *',
                );
                const bom = page
                    .locator('tr')
                    .filter({ hasText: 'UI-BOM-20261107' });
                await inspect(
                    'Bill of Materials',
                    () =>
                        bom.getByTitle('Edit Recipe', { exact: true }).click(),
                    'Recipe / BOM Name *',
                );
                await inspect('BOM Explosion', () =>
                    bom
                        .getByTitle('Multi-Level Tree Explosion', {
                            exact: true,
                        })
                        .click(),
                );
                await page.route(
                    '**/manufacturing/boms/*/explosion',
                    (route) =>
                        route.fulfill({
                            status: 503,
                            body: 'Service unavailable',
                        }),
                    { times: 1 },
                );
                await bom
                    .getByTitle('Multi-Level Tree Explosion', { exact: true })
                    .click();
                const explosion = page.getByRole('dialog', {
                    name: 'BOM Explosion',
                    exact: true,
                });
                await explosion.getByRole('alert').waitFor();
                await explosion
                    .getByRole('button', { name: 'Retry', exact: true })
                    .click();
                await explosion
                    .getByRole('region', {
                        name: 'Consolidated material demand',
                    })
                    .getByText('3.05', { exact: true })
                    .waitFor();
                await page.keyboard.press('Escape');
                await explosion.waitFor({ state: 'hidden' });
                report.cases.push({
                    name: 'BOM retry',
                    width,
                    theme,
                    result: 'PASS',
                });
                await page.goto(base + '/manufacturing/work-orders');
                await inspect(
                    'Create Work Order',
                    () =>
                        page
                            .getByRole('button', {
                                name: 'Create Work Order',
                                exact: true,
                            })
                            .click(),
                    'Planned Quantity *',
                );
                const inProgress = page
                    .locator('tr')
                    .filter({ hasText: 'UI-WO-IN_PROGRESS' });
                const quality = page
                    .locator('tr')
                    .filter({ hasText: 'UI-WO-QUALITY_CHECK' });
                await inspect(
                    'Record Direct Cost',
                    () =>
                        inProgress
                            .getByTitle('Add Direct Cost (Labor / O/H)', {
                                exact: true,
                            })
                            .click(),
                    'Description *',
                );
                await inspect(
                    'Quality Assurance Inspection',
                    () =>
                        inProgress
                            .getByTitle('Submit to Quality Check', {
                                exact: true,
                            })
                            .click(),
                    'Actual Produced Qty *',
                );
                await inspect(
                    'Receive Finished Goods',
                    () =>
                        quality
                            .getByTitle(
                                'Complete Run & Receive Finished Goods',
                                { exact: true },
                            )
                            .click(),
                    'Completion Date *',
                );
                await inspect('Work Order Detail', () =>
                    inProgress
                        .getByTitle('View Details & Floor Log', { exact: true })
                        .click(),
                );
                await context.close();
            }
        assert.deepEqual(report.errors, []);
        report.status = 'PASS';
        console.log(
            'PASS manufacturing specialized UI ' +
                report.cases.length +
                ' cases',
        );
    } finally {
        fs.writeFileSync(
            output + '/manufacturing-ui-report.json',
            JSON.stringify(report, null, 2),
        );
        await browser.close();
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
