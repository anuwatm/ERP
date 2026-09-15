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
    const requireMock = () => ({
        default: () => null,
        useForm: () => ({}),
        useSubmissionKey: () => ({ headers: () => ({}), reset: () => {} }),
        useDiscardGuard: () => ({}),
        useState: () => [null, () => {}],
        useEffect: () => {},
        useMemo: (fn) => fn(),
        useCallback: (fn) => fn,
        useRef: () => ({ current: null }),
        router: {},
    });
    new Function('require', 'exports', 'module', source)(
        requireMock,
        mod.exports,
        mod,
    );
    return mod.exports;
}

const inventoryDirty = loadModule(
    '../../resources/js/Pages/Finance/inventoryDirty.ts',
);
const {
    isWarehouseDirty,
    isBinDirty,
    isLotDirty,
    isStockTransferDirty,
    isStockCountDirty,
} = inventoryDirty;

const coordinatorModule = loadModule(
    '../../resources/js/Hooks/useEditorCoordinator.ts',
);
const { switchGuardedEditor } = coordinatorModule;

/* =========================================================================
   1. V01 Dirty Detection Tests (Testing Production Inventory Functions)
   ========================================================================= */

test('V01 fix: isLotDirty catches barcode-only draft (GPT-2315-DRAFT reproduce)', () => {
    const defaults = {
        product_id: 'prod-1',
        lot_no: '',
        manufactured_at: '',
        expires_at: '',
        barcode: '',
    };

    // Clean state
    assert.equal(isLotDirty({ ...defaults }, defaults), false);

    // Exact GPT reproduction: user fills ONLY barcode
    const barcodeOnly = {
        ...defaults,
        barcode: 'GPT-2315-DRAFT',
    };
    assert.equal(
        isLotDirty(barcodeOnly, defaults),
        true,
        'Barcode-only entry MUST be dirty to prevent data loss',
    );

    // Changing product_id from prefill default
    assert.equal(
        isLotDirty({ ...defaults, product_id: 'prod-2' }, defaults),
        true,
        'Changing product_id must be dirty',
    );

    // Dates and lot_no
    assert.equal(
        isLotDirty({ ...defaults, lot_no: 'LOT-999' }, defaults),
        true,
    );
    assert.equal(
        isLotDirty({ ...defaults, manufactured_at: '2026-09-01' }, defaults),
        true,
    );
    assert.equal(
        isLotDirty({ ...defaults, expires_at: '2027-09-01' }, defaults),
        true,
    );
});

test('V01 fix: isStockCountDirty catches count_date, warehouse_id and product_id changes', () => {
    const defaults = {
        warehouse_id: 'wh-main',
        product_id: 'prod-1',
        counted_quantity: '',
        count_date: '2026-09-14',
    };

    // Clean state
    assert.equal(isStockCountDirty({ ...defaults }, defaults), false);

    // GPT reproduction: user changes count_date from 2026-09-14 to 2026-09-15
    const dateChanged = {
        ...defaults,
        count_date: '2026-09-15',
    };
    assert.equal(
        isStockCountDirty(dateChanged, defaults),
        true,
        'Changing count_date must be dirty',
    );

    // User changes warehouse_id
    assert.equal(
        isStockCountDirty(
            { ...defaults, warehouse_id: 'wh-secondary' },
            defaults,
        ),
        true,
        'Changing warehouse_id must be dirty',
    );

    // User changes product_id
    assert.equal(
        isStockCountDirty({ ...defaults, product_id: 'prod-2' }, defaults),
        true,
        'Changing product_id must be dirty',
    );

    // User enters counted_quantity
    assert.equal(
        isStockCountDirty({ ...defaults, counted_quantity: '42' }, defaults),
        true,
        'Entering counted_quantity must be dirty',
    );
});

test('V01 fix: isBinDirty catches warehouse_id change even if code/name are empty', () => {
    const defaults = {
        warehouse_id: 'wh-default',
        code: '',
        name: '',
    };

    assert.equal(isBinDirty({ ...defaults }, defaults), false);
    assert.equal(
        isBinDirty({ ...defaults, warehouse_id: 'wh-other' }, defaults),
        true,
        'Changing warehouse_id must be dirty',
    );
    assert.equal(
        isBinDirty({ ...defaults, code: 'BIN-01' }, defaults),
        true,
    );
    assert.equal(
        isBinDirty({ ...defaults, name: 'Shelf A' }, defaults),
        true,
    );
});

test('V01 fix: isStockTransferDirty catches inventory_lot_id, destination_warehouse_id, and transfer_date', () => {
    const defaults = {
        product_id: 'prod-1',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        inventory_lot_id: '',
        quantity: '',
        transfer_date: '2026-09-15',
        note: '',
    };

    assert.equal(isStockTransferDirty({ ...defaults }, defaults), false);
    assert.equal(
        isStockTransferDirty({ ...defaults, inventory_lot_id: 'lot-1' }, defaults),
        true,
    );
    assert.equal(
        isStockTransferDirty(
            { ...defaults, destination_warehouse_id: 'wh-dest' },
            defaults,
        ),
        true,
    );
    assert.equal(
        isStockTransferDirty(
            { ...defaults, transfer_date: '2026-09-20' },
            defaults,
        ),
        true,
    );
    assert.equal(
        isStockTransferDirty({ ...defaults, quantity: '10' }, defaults),
        true,
    );
    assert.equal(
        isStockTransferDirty({ ...defaults, note: 'Urgent transfer' }, defaults),
        true,
    );
});

test('isWarehouseDirty checks code and name', () => {
    const defaults = { code: '', name: '' };
    assert.equal(isWarehouseDirty({ ...defaults }, defaults), false);
    assert.equal(isWarehouseDirty({ code: 'WH1', name: '' }, defaults), true);
    assert.equal(isWarehouseDirty({ code: '', name: 'Main' }, defaults), true);
});

/* =========================================================================
   2. Production Switch Coordinator & Guard Wiring Tests
   ========================================================================= */

function setupGuardedEnvironment(initialEditor = null) {
    let activeEditor = initialEditor;
    const activeGuardRef = { current: null };

    const setActiveEditor = (next) => {
        activeEditor = next;
    };

    const switchEditor = (next) => {
        switchGuardedEditor(next, activeGuardRef, setActiveEditor);
    };

    return {
        get activeEditor() {
            return activeEditor;
        },
        activeGuardRef,
        switchEditor,
        setActiveEditor,
    };
}

test('Coordinator: switching editor with dirty draft intercepts with confirmation, Keep preserves draft, Discard switches', () => {
    const env = setupGuardedEnvironment({ type: 'lot', productId: 'p1' });

    // Simulate LotDialog mounting and registering its guard
    let draft = { barcode: 'GPT-2315-DRAFT', lot_no: '' };
    const defaults = { barcode: '', lot_no: '' };
    let isProcessing = false;
    let confirming = false;
    let pendingAction = null;

    const isDirty = () => isLotDirty(draft, defaults);

    const handleClose = (afterClose) => {
        draft = null;
        env.activeGuardRef.current = null;
        afterClose?.();
    };

    const guardRequest = (action) => {
        if (isProcessing) return;
        if (isDirty()) {
            confirming = true;
            pendingAction = action;
        } else {
            action();
        }
    };

    // Dialog sets onRequestCloseRef
    env.activeGuardRef.current = (action) => {
        guardRequest(() => handleClose(action));
    };

    // 1. User clicks + Warehouse to switch
    env.switchEditor({ type: 'warehouse' });

    // Confirmation must be triggered; activeEditor must remain Lot; draft intact
    assert.equal(confirming, true, 'Discard prompt must appear');
    assert.deepEqual(env.activeEditor, { type: 'lot', productId: 'p1' });
    assert.deepEqual(draft, { barcode: 'GPT-2315-DRAFT', lot_no: '' });

    // 2. User clicks "Keep editing"
    confirming = false;
    pendingAction = null;
    assert.deepEqual(env.activeEditor, { type: 'lot', productId: 'p1' });
    assert.deepEqual(draft, { barcode: 'GPT-2315-DRAFT', lot_no: '' });

    // 3. User clicks + Warehouse again and clicks "Discard changes"
    env.switchEditor({ type: 'warehouse' });
    assert.equal(confirming, true);

    const actionToExecute = pendingAction;
    confirming = false;
    pendingAction = null;
    actionToExecute();

    assert.deepEqual(
        env.activeEditor,
        { type: 'warehouse' },
        'Editor switched to Warehouse after discard',
    );
    assert.equal(draft, null, 'Old draft was reset');
    assert.equal(env.activeGuardRef.current, null, 'Guard ref cleared');
});

test('Coordinator: clean draft switches immediately without prompting', () => {
    const env = setupGuardedEnvironment({ type: 'warehouse' });

    let draft = { code: '', name: '' };
    const defaults = { code: '', name: '' };

    const handleClose = (afterClose) => {
        draft = null;
        env.activeGuardRef.current = null;
        afterClose?.();
    };

    env.activeGuardRef.current = (action) => {
        if (isWarehouseDirty(draft, defaults)) {
            // would prompt
        } else {
            handleClose(action);
        }
    };

    env.switchEditor({ type: 'lot', productId: '' });

    assert.deepEqual(
        env.activeEditor,
        { type: 'lot', productId: '' },
        'Immediately switched because draft is clean',
    );
    assert.equal(draft, null);
});

test('Coordinator: processing draft blocks switching completely', () => {
    const env = setupGuardedEnvironment({ type: 'warehouse' });

    let draft = { code: 'WH-BUSY', name: 'Busy' };
    const isProcessing = true;

    env.activeGuardRef.current = (action) => {
        if (isProcessing) return; // Blocked
        action();
    };

    env.switchEditor({ type: 'bin', warehouseId: 'wh-1' });

    assert.deepEqual(
        env.activeEditor,
        { type: 'warehouse' },
        'Must stay on warehouse while processing',
    );
});
