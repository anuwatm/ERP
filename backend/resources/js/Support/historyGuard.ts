type Blocker = (proceed: () => void) => void;
const indexKey = '__erpHistoryIndex';

function entryIndex(state: unknown): number | null {
    if (!state || typeof state !== 'object') return null;
    const index = (state as Record<string, unknown>)[indexKey];
    return typeof index === 'number' && Number.isSafeInteger(index)
        ? index
        : null;
}

// Install before Inertia initializes. Metadata contains only a position, never draft data.
export function installHistoryGuard(browser: Window) {
    const history = browser.history;
    const originalPush = history.pushState;
    const originalReplace = history.replaceState;
    let current = entryIndex(history.state) ?? 0;
    let blocker: Blocker | null = null;
    let restoring: { destination: number; blocker: Blocker } | null = null;
    let allowed: number | null = null;

    const stateAt = (state: unknown, index: number) => ({
        ...(state && typeof state === 'object' ? state : {}),
        [indexKey]: index,
    });
    originalReplace.call(history, stateAt(history.state, current), '');
    history.pushState = (
        state: unknown,
        unused: string,
        url?: string | URL | null,
    ) => {
        originalPush.call(history, stateAt(state, current + 1), unused, url);
        current++;
        allowed = null;
    };
    history.replaceState = (
        state: unknown,
        unused: string,
        url?: string | URL | null,
    ) => {
        originalReplace.call(history, stateAt(state, current), unused, url);
    };

    const onPopState = (event: PopStateEvent) => {
        const destination = entryIndex(event.state);
        // Unmarked entries belong to native navigation/older documents; beforeunload
        // protects document exits. Do not guess a direction or synthesize history entries.
        if (destination === null) return;

        if (restoring) {
            event.stopImmediatePropagation();
            if (destination !== current) {
                history.go(current - destination);
                return;
            }
            const attempted = restoring;
            restoring = null;
            let used = false;
            const origin = current;
            const proceed = () => {
                if (used || current !== origin) return;
                used = true;
                allowed = attempted.destination;
                history.go(attempted.destination - current);
            };
            if (blocker === attempted.blocker) blocker(proceed);
            // A save/unmount can remove the blocker while restoration is in flight.
            else if (!blocker) proceed();
            return;
        }

        if (destination === allowed) {
            allowed = null;
            current = destination;
            return;
        }
        if (destination === current) return;
        if (!blocker) {
            current = destination;
            return;
        }

        // popstate itself cannot be cancelled. Keep Inertia from unmounting the editor,
        // return to its entry, then ask using the existing in-app discard dialog.
        event.stopImmediatePropagation();
        restoring = { destination, blocker };
        history.go(current - destination);
    };
    browser.addEventListener('popstate', onPopState, true);
    return {
        block(callback: Blocker) {
            blocker = callback;
            return () => {
                if (blocker === callback) blocker = null;
            };
        },
        dispose() {
            browser.removeEventListener('popstate', onPopState, true);
            history.pushState = originalPush;
            history.replaceState = originalReplace;
        },
    };
}

let installed: ReturnType<typeof installHistoryGuard> | undefined;
export function initializeHistoryGuard() {
    installed ??= installHistoryGuard(window);
    return installed;
}

export function blockHistoryNavigation(blocker: Blocker) {
    return initializeHistoryGuard().block(blocker);
}
