import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { blockHistoryNavigation } from '@/Support/historyGuard';

// One guard per page, covering its active editor and minimized draft.
export default function useDiscardGuard(dirty: boolean, processing: boolean) {
    const [pending, setPending] = useState<(() => void) | null>(null);
    const bypassNavigation = useRef(false);
    const request = useCallback(
        (action: () => void, options?: { dirty: boolean }) => {
            if (processing) return;
            if (options?.dirty ?? dirty) setPending(() => action);
            else action();
        },
        [dirty, processing],
    );
    const keep = () => setPending(null);
    const discard = () => {
        if (processing) return;
        setPending(null);
        pending?.();
    };
    useEffect(() => {
        if (!dirty && !processing) return;
        const unload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', unload);
        const removeHistoryBlocker = blockHistoryNavigation(request);
        const remove = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get' || bypassNavigation.current)
                return;
            event.preventDefault();
            request(() => {
                // Replay the original GET, including filters and visit callbacks, exactly once.
                bypassNavigation.current = true;
                try {
                    router.visit(event.detail.visit.url, event.detail.visit);
                } finally {
                    bypassNavigation.current = false;
                }
            });
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            remove();
            removeHistoryBlocker();
        };
    }, [dirty, processing, request]);
    return { request, keep, discard, confirming: pending !== null };
}
