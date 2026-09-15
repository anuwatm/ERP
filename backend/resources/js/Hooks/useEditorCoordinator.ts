import { MutableRefObject, useCallback, useRef, useState } from 'react';

export type GuardRequestFn = (action: () => void) => void;

/**
 * Executes a switch to next editor, respecting the active dialog's guard request.
 * If the active editor is guarded (dirty), the guard will intercept and ask for confirmation.
 * If clean, it switches immediately.
 */
export function switchGuardedEditor<T>(
    nextEditor: T | null,
    activeGuardRef: MutableRefObject<GuardRequestFn | null>,
    setActiveEditor: (editor: T | null) => void,
) {
    if (activeGuardRef.current) {
        activeGuardRef.current(() => {
            activeGuardRef.current = null;
            setActiveEditor(nextEditor);
        });
    } else {
        setActiveEditor(nextEditor);
    }
}

/**
 * Single active editor switch coordinator hook.
 * Guarantees exactly one modal/dialog editor is mounted and guarded at a time.
 */
export function useEditorCoordinator<T>(initialEditor: T | null = null) {
    const [activeEditor, setActiveEditor] = useState<T | null>(initialEditor);
    const activeGuardRef = useRef<GuardRequestFn | null>(null);

    const switchEditor = useCallback(
        (nextEditor: T | null) => {
            switchGuardedEditor(nextEditor, activeGuardRef, setActiveEditor);
        },
        [activeGuardRef],
    );

    const closeActiveEditor = useCallback(() => {
        activeGuardRef.current = null;
        setActiveEditor(null);
    }, []);

    return {
        activeEditor,
        setActiveEditor,
        activeGuardRef,
        switchEditor,
        closeActiveEditor,
    };
}

export default useEditorCoordinator;
