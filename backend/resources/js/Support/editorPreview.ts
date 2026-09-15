/** Latest response wins. Never display a total calculated for an older draft revision. */
export function createEditorPreview<T>(
    publish: (state: { pending: boolean; value?: T; error?: string }) => void,
) {
    let controller: AbortController | undefined;
    let revision = 0;
    return {
        async load(resolve: (signal: AbortSignal) => Promise<T>) {
            const current = ++revision;
            controller?.abort();
            controller = new AbortController();
            publish({ pending: true });
            try {
                const value = await resolve(controller.signal);
                if (current === revision) publish({ pending: false, value });
            } catch {
                if (current === revision)
                    publish({
                        pending: false,
                        error: 'Check the document fields and retry.',
                    });
            }
        },
        cancel() {
            ++revision;
            controller?.abort();
        },
    };
}
