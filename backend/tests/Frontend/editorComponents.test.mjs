import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);
function component(name) {
    const source = ts.transpileModule(readFileSync(new URL(`../../resources/js/Components/UI/${name}.tsx`, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true, target: ts.ScriptTarget.ES2022 } }).outputText;
    const exports = {};
    new Function('require', 'exports', source)(require, exports);
    return exports.default;
}

test('line-item errors are attached to their indexed cell, with accessible row actions', () => {
    const html = renderToStaticMarkup(React.createElement(component('EditorLineItems'), {
        rows: [{ key: 'a' }, { key: 'b' }], rowKey: row => row.key,
        columns: [{ key: 'quantity', label: 'Quantity' }],
        renderCell: (_, index, field, errorId) => React.createElement('input', { name: `items.${index}.${field}`, 'aria-describedby': errorId, 'aria-invalid': !!errorId }),
        onAdd() {}, onRemove() {}, errors: { 'items.1.quantity': 'Quantity must be positive' },
    }));
    assert.match(html, /name="items\.1\.quantity" aria-describedby="items-1-quantity-error" aria-invalid="true"/);
    assert.match(html, /id="items-1-quantity-error"/);
    assert.doesNotMatch(html, /name="items\.0\.quantity" aria-describedby/);
    assert.match(html, /aria-label="Remove item 2"/);
    assert.match(html, /max-h-80 overflow-auto/);
});

test('an attachment without an authorized URL stays text, selected file and status survive validation error', () => {
    const html = renderToStaticMarkup(React.createElement(component('EditorAttachments'), {
        selected: { name: 'retry.pdf' }, onChange() {},
        existing: [{ id: 'private', name: 'Restricted.pdf', status: 'Pending scan' }],
        error: 'Upload failed',
    }));
    assert.match(html, /retry\.pdf/);
    assert.match(html, /Restricted\.pdf/);
    assert.match(html, /Pending scan/);
    assert.match(html, /Upload failed/);
    assert.doesNotMatch(html, /href=/);
});

test('financial footer preserves authoritative currency and rounding strings, hides stale totals during errors', () => {
    const Summary = component('EditorFinancialSummary');
    const amounts = [{ label: 'VAT', value: '7.00', currency: 'THB' }, { label: 'WHT', value: '3.00', currency: 'THB' }, { label: 'FX base total', value: '3,712.34', currency: 'THB' }];
    const html = renderToStaticMarkup(React.createElement(Summary, { amounts, pending: false }));
    assert.match(html, /7\.00 THB/);
    assert.match(html, /3\.00 THB/);
    assert.match(html, /3,712\.34 THB/);
    for (const state of [{ pending: true }, { pending: false, error: 'Unavailable' }]) {
        assert.doesNotMatch(renderToStaticMarkup(React.createElement(Summary, { amounts, ...state })), /3,712\.34/);
    }
});
