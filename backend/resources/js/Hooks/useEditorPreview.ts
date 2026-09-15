import { useEffect, useMemo, useState } from 'react';
import { createEditorPreview } from '@/Support/editorPreview';

export default function useEditorPreview<T>(
    resolve: (signal: AbortSignal) => Promise<T>,
) {
    const [state, setState] = useState<{
        pending: boolean;
        value?: T;
        error?: string;
    }>({ pending: true });
    const preview = useMemo(() => createEditorPreview<T>(setState), []);
    useEffect(() => {
        void preview.load(resolve);
        return () => preview.cancel();
    }, [preview, resolve]);
    return state;
}
