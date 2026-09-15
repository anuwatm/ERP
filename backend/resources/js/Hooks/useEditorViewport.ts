import { useEffect, useState } from 'react';

// visualViewport accounts for an on-screen keyboard; dvh alone does not on all browsers.
export default function useEditorViewport(active: boolean) {
    const [viewport, setViewport] = useState<{ top: number; height: number }>();
    useEffect(() => {
        if (!active || !window.visualViewport) return;
        const visual = window.visualViewport;
        const update = () =>
            setViewport({ top: visual.offsetTop, height: visual.height });
        update();
        visual.addEventListener('resize', update);
        visual.addEventListener('scroll', update);
        return () => {
            visual.removeEventListener('resize', update);
            visual.removeEventListener('scroll', update);
        };
    }, [active]);
    return viewport
        ? { top: viewport.top, height: viewport.height, bottom: 'auto' }
        : undefined;
}
