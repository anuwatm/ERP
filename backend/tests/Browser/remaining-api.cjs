const fs = require('fs');
const assert = require('node:assert/strict');
const root = 'temp/remaining-20261029';
const keys = JSON.parse(fs.readFileSync(root + '/synthetic-api-credentials.json'));
const report = { scope: 'Actual loopback HTTP; isolated synthetic SQLite; no production SLA or cross-request rate-limit claim', cases: [] };
(async () => {
    for (const [name, key, expected, suffix, method] of [
        ['missing credential', null, 401, '', 'GET'],
        ['invalid credential', 'invalid-fixture-token', 401, '', 'GET'],
        ['wrong scope', keys.denied, 403, '', 'GET'],
        ['expired key', keys.expired, 401, '', 'GET'],
        ['revoked key', keys.revoked, 401, '', 'GET'],
        ['invalid filter', keys.read, 422, '?per_page=20', 'GET'],
        ['read products', keys.read, 200, '', 'GET'],
        ['missing product', keys.read, 404, '/00000000-0000-0000-0000-000000000000', 'GET'],
        ['write forbidden', keys.read, 405, '', 'POST'],
    ]) {
        const started = performance.now();
        const response = await fetch('http://127.0.0.1:8139/api/v1/products' + suffix, {
            method, redirect: 'manual', headers: { Accept: 'application/json', ...(key ? { Authorization: 'Bearer ' + key } : {}) },
        });
        const body = await response.text();
        assert.equal(response.status, expected, name + ': ' + body.slice(0, 120));
        assert(!Object.values(keys).some(k => body.includes(k)), 'Credential leaked');
        if (expected === 200) {
            assert.match(response.headers.get('cache-control'), /no-store/);
            assert(response.headers.get('x-request-id'));
            assert(!body.includes('secret_hash'));
            const data = JSON.parse(body);
            assert(Array.isArray(data.data));
            assert(data.data.every(p => p.sku.startsWith('LOCAL-')));
            assert(!data.data.some(p => 'cost' in p || 'price' in p));
        }
        report.cases.push({ name, status: response.status, expected, latencyMs: Math.round(performance.now() - started), result: 'PASS' });
    }
    report.status = 'PASS';
})().catch(e => { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }).finally(() => {
    fs.writeFileSync(root + '/api-http.json', JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
});
