import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

const source = ts.transpileModule(readFileSync(new URL('../../resources/js/Support/editorPreview.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
const exports = {};
new Function('exports', source)(exports);
const { createEditorPreview } = exports;

test('older tax response cannot overwrite totals for the current draft', async () => {
    const states = [];
    const preview = createEditorPreview(state => states.push(state));
    let finishOld;
    const old = preview.load(() => new Promise(resolve => { finishOld = resolve; }));
    await preview.load(async () => ({ total: '107.00' }));
    finishOld({ total: '999.00' });
    await old;
    assert.deepEqual(states.at(-1), { pending: false, value: { total: '107.00' } });
});

test('failed preview removes stale money; retry can succeed', async () => {
    const states = [];
    const preview = createEditorPreview(state => states.push(state));
    await preview.load(async () => '107.00');
    await preview.load(async () => { throw Error('offline'); });
    assert.equal(states.at(-1).value, undefined);
    assert.ok(states.at(-1).error);
    await preview.load(async () => '214.00');
    assert.equal(states.at(-1).value, '214.00');
});

test('closing a draft aborts pending work and ignores a late server response', async () => {
    const states = [];
    const preview = createEditorPreview(state => states.push(state));
    let finish, signal;
    const pending = preview.load(s => { signal = s; return new Promise(resolve => { finish = resolve; }); });
    preview.cancel();
    assert.equal(signal.aborted, true);
    finish('123.00');
    await pending;
    assert.equal(states.length, 1);
});
