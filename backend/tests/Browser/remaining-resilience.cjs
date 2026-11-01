const { chromium } = require('C:/Users/anuwat/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8139';
const report = { scope: 'Synthetic product create; actual HTTP browser; not load SLA', cases: [], pageErrors: [] };
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        await context.route('**/*', r => r.request().url().startsWith(base + '/') ? r.continue() : r.abort());
        const page = await context.newPage(); page.setDefaultTimeout(20000);
        page.on('pageerror', e => report.pageErrors.push(e.message));
        await page.goto(base + '/login');
        await page.locator('[name=email]').fill('remaining-owner@fixture.test');
        await page.locator('[name=password]').fill('local-acceptance-password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
        const start = performance.now(); await page.goto(base + '/products');
        await page.getByRole('button', { name: '+ Create Item', exact: true }).waitFor();
        const count = await page.evaluate(() => JSON.parse(document.querySelector('#app').getAttribute('data-page')).props.products.length);
        assert(count >= 1005);
        await page.getByRole('button', { name: '+ Create Item', exact: true }).click();
        const field = page.locator('#products-name'); await field.fill('Synthetic retry ' + Date.now());
        const name = await field.inputValue();
        report.cases.push({ id: 'B06', records: count, openMs: Math.round(performance.now() - start), result: 'PASS' });
        await page.evaluate(async () => { await window.axios.post('/confirm-password', { password: 'local-acceptance-password' }); });
        const failPost = async r => { if(r.request().method() !== 'POST') return r.continue(); const committed = await r.fetch({maxRedirects:0,postData:r.request().postDataBuffer()}); console.log('committed status',committed.status()); report.commitStatuses ??= []; report.commitStatuses.push(committed.status()); await r.abort('internetdisconnected'); };
        await page.route(base + '/products', failPost);
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().click();
        await page.getByRole('alert').filter({hasText:'เชื่อมต่อไม่สำเร็จ'}).waitFor();
        assert.equal(await field.inputValue(), name);
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().waitFor();
        assert(await page.getByRole('button', { name: 'Save Item', exact: true }).first().isEnabled());
        await page.unroute(base + '/products', failPost);
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().click();
        await field.waitFor({ state: 'detached' });
        await page.goto(base + '/products');
        const saved = await page.evaluate(n => JSON.parse(document.querySelector('#app').getAttribute('data-page')).props.products.filter(p => p.name === n).length, name);
        assert.equal(saved, 1);
        assert.equal(report.pageErrors.length, 0);
        report.cases.push({ id: 'B04', lostResponseAfterCommit: true, draftRetained: true, failureNotice: true, retryRows: saved, result: 'PASS' });
        await page.getByRole('button', { name: '+ Create Item', exact: true }).click();
        await field.fill('Synthetic expired session ' + Date.now());
        const sessionDraft = await field.inputValue();
        await context.clearCookies();
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().click();
        await page.getByRole('alert').filter({ hasText: 'Unable to verify your session' }).waitFor();
        assert.equal(await field.inputValue(), sessionDraft);
        const login = await context.newPage();
        await login.goto(base + '/login');
        await login.locator('[name=email]').fill('remaining-owner@fixture.test');
        await login.locator('[name=password]').fill('local-acceptance-password');
        await login.locator('button[type=submit]').click();
        await login.waitForURL(u => !u.pathname.includes('login'), { timeout: 60000 });
        await login.close();
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().click();
        await page.getByRole('textbox', { name: 'Current password', exact: true }).fill('local-acceptance-password');
        await page.getByRole('button', { name: 'Confirm password', exact: true }).click();
        assert.equal(await field.inputValue(), sessionDraft);
        await page.getByRole('button', { name: 'Save Item', exact: true }).first().click();
        await field.waitFor({ state: 'detached' });
        report.cases.push({ id: 'B05-session', draftRetained: true, loginInSecondTab: true, passwordReconfirmed: true, result: 'PASS' });
        await page.goto(base + '/documents');
        await page.getByRole('button', { name: 'Upload Document', exact: true }).click();
        const title = 'Synthetic upload retry ' + Date.now();
        await page.locator('#doc-title').fill(title);
        const upload = 'temp/pdf-acceptance-20261029/invoice.pdf';
        await page.locator('#doc-file-input').setInputFiles(upload);
        const uploadBytes = fs.statSync(upload).size;
        const failUpload = async r => {
            if (r.request().method() !== 'POST') return r.continue();
            try {
                // Chromium omits file bytes from intercepted multipart postData.
                // Rebuild the same fields with the selected synthetic PDF bytes.
                const headers = { ...r.request().headers() };
                const boundary = headers['content-type'].match(/boundary=(.+)$/)[1];
                const multipart = {};
                for (const part of r.request().postDataBuffer().toString('latin1').split('--' + boundary)) {
                    const split = part.indexOf('\r\n\r\n');
                    const name = part.slice(0, split).match(/;\s*name="([^"]+)"/);
                    if (split < 0 || !name) continue;
                    multipart[name[1]] = name[1] === 'file' ? { name: 'invoice.pdf', mimeType: 'application/pdf', buffer: fs.readFileSync(upload) } : part.slice(split + 4).replace(/\r\n$/, '');
                }
                delete headers['content-type']; delete headers['content-length']; delete headers.cookie;
                const committed = await context.request.post(r.request().url(), { headers, multipart, maxRedirects: 0 });
                report.commitStatuses.push(committed.status());
                const { DatabaseSync } = require('node:sqlite');
                const fixture = new DatabaseSync('temp/remaining-20261029/database.sqlite', { readOnly: true });
                try { report.uploadRowsBeforeLostResponse = fixture.prepare('select count(*) as n from documents where title = ?').get(title).n; } finally { fixture.close(); }
                report.uploadCommitLocation = committed.headers().location?.replace(base, '');
            } catch (e) { report.uploadTransportError = e.message; }
            await r.abort('internetdisconnected');
        };
        await page.route(base + '/documents', failUpload);
        await page.getByRole('button', { name: 'Upload Document', exact: true }).last().click();
        await page.getByRole('alert').filter({ hasText: 'เชื่อมต่อไม่สำเร็จ' }).waitFor();
        assert([302,303].includes(report.commitStatuses.at(-1)), 'Upload must commit before lost-response simulation');
        assert.equal(report.uploadRowsBeforeLostResponse, 1, report.uploadTransportError);
        assert.equal(await page.locator('#doc-title').inputValue(), title);
        assert.equal(await page.locator('#doc-file-input').evaluate(e => e.files.length), 1);
        await page.unroute(base + '/documents', failUpload);
        const uploadStarted = performance.now();
        await page.getByRole('button', { name: 'Upload Document', exact: true }).last().click();
        await page.locator('#doc-title').waitFor({ state: 'detached' });
        const uploadMs = Math.round(performance.now() - uploadStarted);
        await page.goto(base + '/documents');
        const uploads = await page.evaluate(t => JSON.parse(document.querySelector('#app').getAttribute('data-page')).props.documents.data.filter(d => d.title === t).length, title);
        assert.equal(uploads, 1);
        report.cases.push({ id: 'B07', lostResponseAfterCommit: true, mime: 'application/pdf', bytes: uploadBytes, retryMs: uploadMs, draftAndFileRetained: true, retryDocuments: uploads, result: 'PASS' });
        await context.close(); report.status = 'PASS';
    } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
    finally { await browser.close(); fs.writeFileSync('temp/remaining-20261029/resilience-browser.json', JSON.stringify(report, null, 2)); console.log(JSON.stringify(report)); }
})();
