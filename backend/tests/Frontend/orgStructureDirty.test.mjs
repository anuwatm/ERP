import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

function loadModule(relativePath) {
    const sourcePath = new URL(relativePath, import.meta.url);
    const source = ts.transpileModule(readFileSync(sourcePath, 'utf8'), {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
            jsx: ts.JsxEmit.ReactJSX,
            esModuleInterop: true,
        },
    }).outputText;
    const mod = { exports: {} };
    const requireMock = () => ({});
    new Function('require', 'exports', 'module', source)(
        requireMock,
        mod.exports,
        mod,
    );
    return mod.exports;
}

const orgStructureDirty = loadModule(
    '../../resources/js/Pages/Settings/orgStructureDirty.ts',
);
const { isBranchDirty, isDivisionDirty, isDepartmentDirty } = orgStructureDirty;

/* =========================================================================
   P2 — E01: Organization Structure Dirty Detection & Discard Tests
   ========================================================================= */

test('E01 Branch: Create Branch is clean when all fields are empty defaults', () => {
    const defaults = {
        name: '',
        phone: '',
        address: '',
        is_head_office: false,
    };
    assert.strictEqual(isBranchDirty({ ...defaults }, defaults), false);
});

test('E01 Branch: Create Branch catches draft when name is empty but phone/address/is_head_office changed', () => {
    const defaults = {
        name: '',
        phone: '',
        address: '',
        is_head_office: false,
    };

    // Phone only
    assert.strictEqual(
        isBranchDirty(
            { name: '', phone: '0812345678', address: '', is_head_office: false },
            defaults,
        ),
        true,
    );

    // Address only
    assert.strictEqual(
        isBranchDirty(
            { name: '', phone: '', address: '123 Bangkok', is_head_office: false },
            defaults,
        ),
        true,
    );

    // is_head_office toggle only
    assert.strictEqual(
        isBranchDirty(
            { name: '', phone: '', address: '', is_head_office: true },
            defaults,
        ),
        true,
    );

    // Name only
    assert.strictEqual(
        isBranchDirty(
            { name: 'Chiang Mai Branch', phone: '', address: '', is_head_office: false },
            defaults,
        ),
        true,
    );
});

test('E01 Branch: Edit Branch is clean on initial load with existing record values', () => {
    const existing = {
        name: 'Headquarters',
        phone: '021234567',
        address: '100 Silom Rd',
        is_head_office: true,
    };
    // Opening dialog without edits must not be dirty (must not falsely warn on close)
    assert.strictEqual(isBranchDirty({ ...existing }, existing), false);
});

test('E01 Branch: Edit Branch is dirty when user clears name or changes other fields', () => {
    const existing = {
        name: 'Headquarters',
        phone: '021234567',
        address: '100 Silom Rd',
        is_head_office: true,
    };

    // Clear name to empty string while changing phone -> must be dirty
    assert.strictEqual(
        isBranchDirty(
            { name: '', phone: '029999999', address: '100 Silom Rd', is_head_office: true },
            existing,
        ),
        true,
    );

    // Clear name only -> must be dirty
    assert.strictEqual(
        isBranchDirty(
            { name: '', phone: '021234567', address: '100 Silom Rd', is_head_office: true },
            existing,
        ),
        true,
    );

    // Toggle head office -> must be dirty
    assert.strictEqual(
        isBranchDirty(
            { ...existing, is_head_office: false },
            existing,
        ),
        true,
    );
});

test('E01 Division: Create Division is clean on default branch and empty name', () => {
    const defaults = {
        branch_id: 'branch-1',
        name: '',
    };
    assert.strictEqual(isDivisionDirty({ ...defaults }, defaults), false);
});

test('E01 Division: Create Division is dirty when changing branch reference with empty name', () => {
    const defaults = {
        branch_id: 'branch-1',
        name: '',
    };
    // Changing branch reference must trigger dirty detection even if name is empty
    assert.strictEqual(
        isDivisionDirty({ branch_id: 'branch-2', name: '' }, defaults),
        true,
    );

    // Entering name is dirty
    assert.strictEqual(
        isDivisionDirty({ branch_id: 'branch-1', name: 'Sales Division' }, defaults),
        true,
    );
});

test('E01 Division: Edit Division is clean on load and dirty on any modification', () => {
    const existing = {
        branch_id: 'branch-1',
        name: 'Engineering',
    };
    assert.strictEqual(isDivisionDirty({ ...existing }, existing), false);

    // Change branch
    assert.strictEqual(
        isDivisionDirty({ branch_id: 'branch-2', name: 'Engineering' }, existing),
        true,
    );

    // Clear name
    assert.strictEqual(
        isDivisionDirty({ branch_id: 'branch-1', name: '' }, existing),
        true,
    );
});

test('E01 Department: Create Department is clean on initial selection and empty name', () => {
    const defaults = {
        branch_id: 'branch-1',
        division_id: 'div-1',
        name: '',
    };
    assert.strictEqual(isDepartmentDirty({ ...defaults }, defaults), false);
});

test('E01 Department: Create Department is dirty when changing branch or division with empty name', () => {
    const defaults = {
        branch_id: 'branch-1',
        division_id: 'div-1',
        name: '',
    };

    // Change division reference with empty name
    assert.strictEqual(
        isDepartmentDirty(
            { branch_id: 'branch-1', division_id: 'div-2', name: '' },
            defaults,
        ),
        true,
    );

    // Change branch reference with empty name
    assert.strictEqual(
        isDepartmentDirty(
            { branch_id: 'branch-2', division_id: 'div-3', name: '' },
            defaults,
        ),
        true,
    );
});

test('E01 Department: Edit Department is clean on load and dirty when modifying any field', () => {
    const existing = {
        branch_id: 'branch-1',
        division_id: 'div-1',
        name: 'Frontend Team',
    };

    // Clean on load
    assert.strictEqual(isDepartmentDirty({ ...existing }, existing), false);

    // Change name
    assert.strictEqual(
        isDepartmentDirty({ ...existing, name: 'Core UI Team' }, existing),
        true,
    );

    // Clear name
    assert.strictEqual(
        isDepartmentDirty({ ...existing, name: '' }, existing),
        true,
    );

    // Move to another division
    assert.strictEqual(
        isDepartmentDirty({ ...existing, division_id: 'div-2' }, existing),
        true,
    );
});

test('E01 Discard Guard Flow: Discard vs Keep simulation', () => {
    let closed = false;
    let confirming = false;
    let pendingAction = null;

    const requestClose = (isDirty, action) => {
        if (!isDirty) {
            action();
        } else {
            confirming = true;
            pendingAction = action;
        }
    };

    const keep = () => {
        confirming = false;
        pendingAction = null;
    };

    const discard = () => {
        confirming = false;
        const act = pendingAction;
        pendingAction = null;
        act?.();
    };

    // Case A: Not dirty -> closes immediately without confirmation
    requestClose(false, () => { closed = true; });
    assert.strictEqual(closed, true);
    assert.strictEqual(confirming, false);

    // Reset
    closed = false;

    // Case B: Dirty -> asks confirmation; keep retains draft
    requestClose(true, () => { closed = true; });
    assert.strictEqual(closed, false);
    assert.strictEqual(confirming, true);
    keep();
    assert.strictEqual(closed, false);
    assert.strictEqual(confirming, false);

    // Case C: Dirty -> asks confirmation; discard executes close
    requestClose(true, () => { closed = true; });
    assert.strictEqual(closed, false);
    assert.strictEqual(confirming, true);
    discard();
    assert.strictEqual(closed, true);
    assert.strictEqual(confirming, false);
});
