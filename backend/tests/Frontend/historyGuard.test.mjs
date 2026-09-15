import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import ts from 'typescript';

// Exercise the production TS against asynchronous History API events without a DOM dependency.
const sourcePath = new URL(
    '../../resources/js/Support/historyGuard.ts',
    import.meta.url,
);
const source = ts.transpileModule(readFileSync(sourcePath, 'utf8'), {
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
    },
}).outputText;
const module = { exports: {} };
new Function('exports', source)(module.exports);
const { installHistoryGuard } = module.exports;

function fixture() {
    const browser = new EventTarget();
    const entries = [{ state: { page: { url: '/home' } }, url: '/home' }];
    let position = 0;
    const queued = [];
    browser.location = { href: '/home' };
    browser.history = {
        get state() {
            return structuredClone(entries[position].state);
        },
        get length() {
            return entries.length;
        },
        pushState(state, unused, url = browser.location.href) {
            entries.splice(position + 1);
            entries.push({ state: structuredClone(state), url });
            position++;
            browser.location.href = url;
        },
        replaceState(state, unused, url = browser.location.href) {
            entries[position] = { state: structuredClone(state), url };
            browser.location.href = url;
        },
        go(delta) {
            queued.push(() => {
                const next = position + delta;
                if (next < 0 || next >= entries.length) return;
                position = next;
                browser.location.href = entries[position].url;
                const event = new Event('popstate');
                event.state = browser.history.state;
                browser.dispatchEvent(event);
            });
        },
    };
    const guard = installHistoryGuard(browser);
    const rendered = [];
    browser.addEventListener('popstate', () =>
        rendered.push(browser.location.href),
    );
    const push = (url) =>
        browser.history.pushState(
            { page: { url }, encrypted: 'fixture' },
            '',
            url,
        );
    function flush() {
        let count = 0;
        while (queued.length) {
            assert.ok(++count < 20, 'history restoration must not loop');
            queued.shift()();
        }
    }
    return { browser, guard, rendered, push, flush };
}

test('dirty Back restores current entry before asking; Keep preserves draft and history', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    let asked = 0;
    guard.block(() => {
        asked++;
        assert.equal(browser.location.href, '/customers');
    });
    browser.history.go(-1);
    flush();
    assert.deepEqual(rendered, []);
    assert.equal(asked, 1);
    assert.equal(browser.location.href, '/customers');
    assert.equal(browser.history.length, 2);
});

test('Discard replays Back once and allows the router to render the destination', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    let proceed;
    guard.block((action) => {
        proceed = action;
    });
    browser.history.go(-1);
    flush();
    assert.equal(typeof proceed, 'function');
    proceed();
    proceed();
    flush();
    assert.deepEqual(rendered, ['/home']);
    assert.equal(browser.location.href, '/home');
});

test('Forward and multi-entry Back retain the same history destination', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    push('/deals');
    browser.history.go(-2);
    flush();
    rendered.length = 0;
    let proceed;
    guard.block((action) => {
        proceed = action;
    });
    browser.history.go(2);
    flush();
    assert.equal(browser.location.href, '/home');
    assert.deepEqual(rendered, []);
    proceed();
    flush();
    assert.deepEqual(rendered, ['/deals']);
    rendered.length = 0;
    browser.history.go(-2);
    flush();
    assert.equal(browser.location.href, '/deals');
    proceed();
    flush();
    assert.deepEqual(rendered, ['/home']);
});

test('processing blocks navigation without prompting or rendering another page', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    guard.block(() => {});
    browser.history.go(-1);
    flush();
    assert.equal(browser.location.href, '/customers');
    assert.deepEqual(rendered, []);
});

test('replaceState preserves entry index and all Inertia payload fields', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    browser.history.replaceState(
        { page: { url: '/customers?search=abc' }, encrypted: 'changed' },
        '',
        '/customers?search=abc',
    );
    assert.equal(browser.history.state.encrypted, 'changed');
    let proceed;
    guard.block((action) => {
        proceed = action;
    });
    browser.history.go(-1);
    flush();
    assert.equal(browser.location.href, '/customers?search=abc');
    proceed();
    flush();
    assert.deepEqual(rendered, ['/home']);
});

test('removing a blocker restores ordinary Back/Forward behavior', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    const remove = guard.block(() => assert.fail('unmounted blocker'));
    remove();
    browser.history.go(-1);
    flush();
    assert.deepEqual(rendered, ['/home']);
    browser.history.go(1);
    flush();
    assert.deepEqual(rendered, ['/home', '/customers']);
});

test('rapid Back attempts restore one editor without extra history entries', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/deals');
    push('/customers');
    let asked = 0;
    guard.block(() => asked++);
    browser.history.go(-1);
    browser.history.go(-1);
    flush();
    assert.equal(browser.location.href, '/customers');
    assert.equal(asked, 1);
    assert.deepEqual(rendered, []);
    assert.equal(browser.history.length, 3);
});

test('old React effect cleanup does not unregister a newer active editor', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    const removeOld = guard.block(() => assert.fail('stale editor'));
    let asked = 0;
    guard.block(() => asked++);
    removeOld();
    browser.history.go(-1);
    flush();
    assert.equal(asked, 1);
    assert.deepEqual(rendered, []);
});

test('save finishing during restoration permits the original pending destination', () => {
    const { browser, guard, rendered, push, flush } = fixture();
    push('/customers');
    const remove = guard.block(() => assert.fail('save has already finished'));
    // Remove the blocker just after the guarded traversal starts restoring the entry.
    const originalGo = browser.history.go;
    browser.history.go = delta => {
        originalGo(delta);
        if (delta > 0) remove();
    };
    browser.history.go(-1);
    flush();
    assert.deepEqual(rendered, ['/home']);
});
