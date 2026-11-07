const {
    chromium,
} = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const root = 'temp/remaining-20261029';
const base = 'http://127.0.0.1:8139';
const requestedPaths = process.env.MATRIX_PATHS
    ? JSON.parse(process.env.MATRIX_PATHS)
    : JSON.parse(fs.readFileSync(root + '/matrix-paths.json'));
const manufacturingPaths = ['/manufacturing/boms', '/manufacturing/work-orders'];
const paths = requestedPaths.filter(path => !manufacturingPaths.includes(path));
const output = process.env.MATRIX_OUTPUT || 'browser-matrix.json';
const report = {
    status: 'running',
    scope: 'Actual create/edit surfaces; isolated owner role; Chrome only; browser Date fixed for reproducible fresh-draft defaults',
    specialized: {
        paths: requestedPaths.filter(path => manufacturingPaths.includes(path)),
        suite: 'backend/tests/Browser/manufacturing-ui.cjs',
    },
    cases: [],
    errors: [],
};
const values = dialog => dialog.locator('input:not([type=password]):not([type=hidden]),textarea,select').evaluateAll(es => es.filter(e => e.getClientRects().length && !e.disabled && !e.readOnly).map(e => ({type:e.type,value:e.type==='file'?[...e.files].map(f=>f.name+':'+f.size):e.type==='checkbox'?e.checked:e.value})));
const save = () =>
    fs.writeFileSync(root + '/' + output, JSON.stringify(report, null, 2));
(async () => {
    const browser = await chromium.launch({
        executablePath:
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        headless: true,
    });
    report.browser = await browser.version();
    try {
        const login = await browser.newContext();
        await login.route('**/*', (r) =>
            r
                .request()
                .url()
                .startsWith(base + '/')
                ? r.continue()
                : r.abort(),
        );
        const p = await login.newPage();
        p.setDefaultTimeout(60000);
        await p.goto(base + '/login');
        await p.locator('[name=email]').fill('remaining-owner@fixture.test');
        await p.locator('[name=password]').fill('local-acceptance-password');
        await p.locator('button[type=submit]').click();
        await p.waitForURL((u) => !u.pathname.includes('login'), {
            timeout: 60000,
        });
        const auth = await login.storageState();
        await login.close();
        for (const width of [1280, 375])
            for (const theme of ['light', 'dark']) {
                const context = await browser.newContext({
                    storageState: auth,
                    viewport: { width, height: 900 },
                    colorScheme: theme,
                });
                await context.addInitScript(
                    (t) => localStorage.setItem('theme', t),
                    theme,
                );
                await context.route('**/*', (r) =>
                    r
                        .request()
                        .url()
                        .startsWith(base + '/')
                        ? r.continue()
                        : r.abort(),
                );
                const page = await context.newPage();
                await page.clock.setFixedTime(new Date('2026-10-29T12:00:00Z'));
                page.setDefaultTimeout(20000);
                page.on('pageerror', (e) =>
                    report.errors.push({ width, theme, message: e.message }),
                );
                for (const path of paths) {
                    const started = Date.now();
                    let response;
                    try {
                        response = await page.goto(base + path, {
                            timeout: 60000,
                        });
                        await page.waitForTimeout(120);
                    } catch (e) {
                        report.cases.push({
                            width,
                            theme,
                            path,
                            status: 'FAIL',
                            error: e.message.slice(0, 200),
                        });
                        save();
                        continue;
                    }
                    const buttons = await page
                        .getByRole('button')
                        .evaluateAll((es) =>
                            es.map(
                                (e) =>
                                    e.getAttribute('aria-label') ||
                                    e.getAttribute('title') ||
                                    e.textContent ||
                                    '',
                            ),
                        );
                    const labels = [
                        ...new Set(
                            buttons
                                .map((s) => s.trim())
                                .filter(
                                    (s) => process.env.MATRIX_ONLY_EXTRA
                                        ? new RegExp(process.env.MATRIX_EXTRA_PATTERN).test(s)
                                        :
                                        /^(\+\s*)?(Create|New|Add|Warehouse|Bin|Lot|Physical Count|Transfer Stock)\b/i.test(
                                            s,
                                        ) ||
                                        (process.env.MATRIX_INCLUDE_EDIT &&
                                            /^Edit\b/i.test(s)) || (process.env.MATRIX_EXTRA_PATTERN && new RegExp(process.env.MATRIX_EXTRA_PATTERN).test(s)),
                                ),
                        ),
                    ].slice(0, 12);
                    if (!labels.length) {
                        report.cases.push({
                            width,
                            theme,
                            path,
                            http: response.status(),
                            status: 'NO_CREATE_SURFACE',
                            ms: Date.now() - started,
                        });
                        save();
                        continue;
                    }
                    for (const label of labels) {
                        const row = {
                            width,
                            theme,
                            path,
                            label,
                            http: response.status(),
                            loadMs: Date.now() - started,
                        };
                        try {
                            if (
                                await page
                                    .getByRole('button', {
                                        name: label,
                                        exact: true,
                                    })
                                    .first()
                                    .isDisabled()
                            ) {
                                row.status = 'DISABLED_CONTEXT';
                                report.cases.push(row);
                                save();
                                continue;
                            }
                            await page
                                .getByRole('button', {
                                    name: label,
                                    exact: true,
                                })
                                .first()
                                .click();
                            const dialog = page.getByRole('dialog').last();
                            await dialog
                                .locator('header')
                                .waitFor({ state: 'visible' });
                            let fieldSelector = 'input:not([type=hidden]):not([type=password]):not([type=file]):not([type=checkbox]):not([type=radio]):not([role=combobox]):not([readonly]):not([disabled]):not([placeholder^=Scan]),textarea:not([readonly]):not([disabled])';
                            if (label.includes('Stock Adjustment')) fieldSelector = '#sm-quantity';
                            if (!await dialog.locator(fieldSelector).count()) fieldSelector = 'select:not([disabled])';
                            if (!await dialog.locator(fieldSelector).count()) fieldSelector = 'input[type=file]';
                            const field = dialog.locator(fieldSelector).first();
                            await field.waitFor({ state: 'visible' });
                            const original = await field.inputValue();
                            const initialValues = await values(dialog);
                            const type = await field.getAttribute('type');
                            if (type === 'file') { await field.setInputFiles('temp/pdf-acceptance-20261029/invoice.pdf'); } else if (await field.evaluate(e => e.tagName) === 'SELECT') {
                                const next = await field.locator('option').evaluateAll((es, original) => es.map(e => e.value).find(v => v !== original), original);
                                await field.selectOption(next);
                            } else await field.fill(
                                type === 'number'
                                    ? original === '91'
                                        ? '92'
                                        : '91'
                                    : type === 'month'
                                      ? '2026-09'
                                      : type === 'date'
                                        ? original === '2026-10-28'
                                            ? '2026-10-27'
                                            : '2026-10-28'
                                        : type === 'datetime-local'
                                          ? '2026-10-28T09:31'
                                          : type === 'time'
                                            ? '09:31'
                                            : 'Local draft 20261029',
                            );
                            await field.press('Tab');
                            const value = await field.inputValue();
                            const draftValues = await values(dialog);
                            row.checkedFields = draftValues.length;
                            row.field = {
                                id: await field.getAttribute('id'),
                                original,
                                value,
                            };
                            const beforeY = await page.evaluate(() => scrollY);
                            await page.mouse.wheel(0, 400);
                            row.scrollLocked =
                                (await page.evaluate(() => scrollY)) ===
                                beforeY;
                            await dialog
                                .getByRole('button', {
                                    name: /^Minimize .* dialog$/i,
                                })
                                .click();
                            await page
                                .getByRole('button', {
                                    name: /^Restore .* draft$/i,
                                })
                                .click();
                            if ((await field.inputValue()) !== value)
                                throw Error('Draft lost on minimize/restore');
                            if(JSON.stringify(await values(dialog))!==JSON.stringify(draftValues)) throw Error('Form fields changed on minimize/restore');
                            await page.keyboard.press('Tab');
                            await page.keyboard.press('Shift+Tab');
                            row.focusWithinDialog = await dialog.evaluate((e) =>
                                e.contains(document.activeElement),
                            );
                            const box = await dialog
                                .locator('header')
                                .boundingBox();
                            row.horizontalContained =
                                box.x >= -1 && box.x + box.width <= width + 1;
                            await dialog
                                .getByRole('button', {
                                    name: /^Close .* dialog$/i,
                                })
                                .click();
                            await page
                                .getByRole('button', {
                                    name: 'Keep editing',
                                    exact: true,
                                })
                                .click();
                            if ((await field.inputValue()) !== value)
                                throw Error('Draft lost on Keep');
                            if(JSON.stringify(await values(dialog))!==JSON.stringify(draftValues)) throw Error('Form fields changed on Keep');
                            await page.screenshot({
                                path:
                                    root +
                                    '/matrix-' +
                                    width +
                                    '-' +
                                    theme +
                                    '-' +
                                    report.cases.length +
                                    '.png',
                            });
                            await dialog
                                .getByRole('button', {
                                    name: /^Close .* dialog$/i,
                                })
                                .click();
                            await page
                                .getByRole('button', {
                                    name: 'Discard changes',
                                    exact: true,
                                })
                                .click();
                            await page
                                .getByRole('button', {
                                    name: label,
                                    exact: true,
                                })
                                .first()
                                .click();
                            const reopened = page.getByRole('dialog').last();
                            const reset = await reopened
                                .locator(
                                    fieldSelector,
                                )
                                .first()
                                .inputValue();
                            const resetValues = await values(reopened);
                            if(JSON.stringify(resetValues)!==JSON.stringify(initialValues)) throw Error('Discard retained form fields: '+JSON.stringify(initialValues.map((x,i)=>JSON.stringify(x)!==JSON.stringify(resetValues[i])?{index:i,expected:x,actual:resetValues[i]}:null).filter(Boolean).slice(0,3)));
                            if (reset !== original)
                                throw Error(
                                    'Discard did not reset to original defaults',
                                );
                            await reopened
                                .getByRole('button', {
                                    name: /^Close .* dialog$/i,
                                })
                                .click();
                            if (
                                !row.scrollLocked ||
                                !row.focusWithinDialog ||
                                !row.horizontalContained
                            )
                                throw Error('Scroll/focus/containment failed');
                            row.status = 'PASS';
                        } catch (e) {
                            row.status = 'FAIL';
                            row.error = e.message.slice(0, 1200);
                            await page
                                .screenshot({
                                    path:
                                        root +
                                        '/failed-' +
                                        report.cases.length +
                                        '.png',
                                })
                                .catch(() => {});
                            await page
                                .goto(base + path, { timeout: 60000 })
                                .catch(() => {});
                        }
                        report.cases.push(row);
                        save();
                    }
                }
                await context.close();
            }
        report.status = 'completed';
        save();
        const failed = report.cases.filter((x) => x.status === 'FAIL').length;
        console.log(
            JSON.stringify({
                cases: report.cases.length,
                passed: report.cases.filter((x) => x.status === 'PASS').length,
                failed,
                errors: report.errors,
            }),
        );
        if (failed || report.errors.length) process.exitCode = 1;
    } finally {
        await browser.close();
    }
})().catch((e) => {
    report.status = 'error';
    report.error = e.message;
    save();
    console.error(e);
    process.exit(1);
});
