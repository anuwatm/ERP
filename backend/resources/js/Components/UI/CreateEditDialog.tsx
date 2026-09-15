import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import useEditorViewport from '@/Hooks/useEditorViewport';
import axios from 'axios';
import {
    type FormEvent,
    type ReactNode,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';

const secondary =
    'inline-flex min-h-10 items-center justify-center rounded-md border border-gray-300 px-3 text-sm font-medium hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-50 dark:border-gray-600 dark:hover:bg-gray-800';

export default function CreateEditDialog({
    open,
    minimized,
    suspended = false,
    onMinimize,
    onClose,
    title,
    breadcrumb,
    entityLabel,
    draftLabel,
    storageKey,
    formId,
    processing,
    saveDisabled = false,
    saveLabel,
    status,
    errors,
    onSubmit,
    children,
    requiresPasswordConfirmation = true,
}: {
    open: boolean;
    minimized: boolean;
    suspended?: boolean;
    onMinimize: (value: boolean) => void;
    onClose: () => void;
    title: string;
    breadcrumb: ReactNode;
    entityLabel: string;
    draftLabel: string;
    storageKey: string;
    formId: string;
    processing: boolean;
    saveDisabled?: boolean;
    saveLabel: string;
    status: ReactNode;
    errors: Record<string, string | undefined>;
    onSubmit: (event: FormEvent) => void;
    children: (expanded: boolean) => ReactNode;
    requiresPasswordConfirmation?: boolean;
}) {
    const [expanded, setExpanded] = useState(() => {
        try {
            return localStorage.getItem(storageKey) === 'expanded';
        } catch {
            return false;
        }
    });
    const formRef = useRef<HTMLFormElement>(null);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    const submitting = useRef(false);
    const [checking, setChecking] = useState(false);
    const [needsPassword, setNeedsPassword] = useState(false);
    const [password, setPassword] = useState('');
    const [requestError, setRequestError] = useState('');
    const processingRef = useRef(processing);
    const lastFocus = useRef<HTMLElement | null>(null);
    const passwordInput = useRef<HTMLInputElement>(null);
    const requestErrorElement = useRef<HTMLParagraphElement>(null);
    const viewport = useEditorViewport(open && !minimized && !suspended);
    const minimize = () => {
        if (processing || submitting.current) return;
        if (formRef.current?.contains(document.activeElement))
            lastFocus.current = document.activeElement as HTMLElement;
        onMinimize(true);
    };
    const restore = () => {
        onMinimize(false);
        requestAnimationFrame(() =>
            lastFocus.current?.focus({ preventScroll: true }),
        );
    };
    const label = entityLabel.toLowerCase();
    useLayoutEffect(() => {
        processingRef.current = processing;
        if (!processing) submitting.current = false;
    }, [processing, errors, open]);
    useEffect(() => {
        if (
            open &&
            !minimized &&
            !suspended &&
            !processing &&
            Object.keys(errors).length
        ) {
            const invalid = formRef.current?.querySelector<HTMLElement>(
                '[aria-invalid="true"]:not([disabled]):not([type="hidden"])',
            );
            (invalid ?? errorSummaryRef.current)?.focus();
        }
    }, [errors, open, minimized, suspended, processing]);
    useEffect(() => {
        if (needsPassword) passwordInput.current?.focus();
        else if (requestError) requestErrorElement.current?.focus();
    }, [needsPassword, requestError]);
    const close = () => {
        if (!processing && !submitting.current && !suspended) {
            setPassword('');
            onClose();
        }
    };
    const toggleSize = () => {
        setExpanded(!expanded);
        try {
            localStorage.setItem(storageKey, expanded ? 'normal' : 'expanded');
        } catch {
            /* Size preferences are optional, never draft storage. */
        }
    };
    const save = (
        <button
            type="submit"
            form={formId}
            disabled={processing || checking || saveDisabled}
            className="min-h-10 rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:opacity-50"
        >
            {processing || checking ? 'Saving...' : saveLabel}
        </button>
    );
    if (!open) return null;
    return (
        <>
            {minimized && !suspended && (
                <div
                    aria-label={`${entityLabel} draft`}
                    className="fixed bottom-4 right-4 z-40 flex w-[calc(100%-2rem)] max-w-sm items-center gap-2 rounded-lg border border-gray-300 bg-white p-3 text-gray-900 shadow-lg dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                >
                    <button
                        type="button"
                        onClick={restore}
                        className="min-w-0 flex-1 text-left focus-visible:outline focus-visible:outline-indigo-500"
                    >
                        <span className="block text-xs font-semibold text-amber-700 dark:text-amber-300">
                            Draft · {title}
                        </span>
                        <span className="block truncate text-sm">
                            {draftLabel}
                        </span>
                    </button>
                    <button
                        type="button"
                        title={`Restore ${label} draft`}
                        aria-label={`Restore ${label} draft`}
                        onClick={restore}
                        className={secondary}
                    >
                        ↗︎
                    </button>
                    <button
                        type="button"
                        title={`Close ${label} draft`}
                        aria-label={`Close ${label} draft`}
                        onClick={close}
                        disabled={processing}
                        className={secondary}
                    >
                        ×
                    </button>
                </div>
            )}
            <Dialog
                open={!minimized && !suspended}
                unmount={false}
                onClose={() => {
                    if (!minimized) close();
                }}
                className="relative z-50"
            >
                <div className="fixed inset-0 bg-black/40" aria-hidden="true" />
                <div
                    style={viewport}
                    className={`fixed inset-0 flex min-h-0 items-center justify-center ${expanded ? '' : 'sm:p-3'}`}
                >
                    <DialogPanel
                        className={`flex h-full w-full min-h-0 min-w-0 flex-col overflow-hidden bg-white text-gray-900 shadow-xl dark:bg-gray-900 dark:text-gray-100 ${expanded ? '' : 'sm:max-w-[640px] sm:rounded-lg'}`}
                    >
                        <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:px-6">
                            <div className="min-w-0">
                                <div className="break-words text-xs text-gray-600 dark:text-gray-300">
                                    {breadcrumb}
                                </div>
                                <DialogTitle className="break-words text-lg font-semibold">
                                    {title}
                                </DialogTitle>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {save}
                                <button
                                    type="button"
                                    title="Minimize"
                                    aria-label={`Minimize ${label} dialog`}
                                    onClick={minimize}
                                    disabled={processing || checking}
                                    className={secondary}
                                >
                                    −
                                </button>
                                <button
                                    type="button"
                                    title={expanded ? 'Normal size' : 'Expand'}
                                    aria-label={
                                        expanded
                                            ? 'Normal size'
                                            : `Expand ${label} dialog`
                                    }
                                    onClick={toggleSize}
                                    disabled={processing || checking}
                                    className={`${secondary} hidden sm:inline-flex`}
                                >
                                    {expanded ? '↙︎' : '↗︎'}
                                </button>
                                <button
                                    type="button"
                                    title="Close"
                                    aria-label={`Close ${label} dialog`}
                                    onClick={close}
                                    disabled={processing}
                                    className={secondary}
                                >
                                    ×
                                </button>
                            </div>
                        </header>
                        <form
                            id={formId}
                            ref={formRef}
                            aria-busy={processing}
                            onFocusCapture={(event) => {
                                if (event.target instanceof HTMLElement)
                                    lastFocus.current = event.target;
                            }}
                            onSubmit={async (event) => {
                                event.preventDefault();
                                if (
                                    processing ||
                                    saveDisabled ||
                                    submitting.current
                                ) {
                                    event.preventDefault();
                                    return;
                                }
                                submitting.current = true;
                                if (!requiresPasswordConfirmation) {
                                    onSubmit(event);
                                    return;
                                }
                                setChecking(true);
                                setRequestError('');
                                try {
                                    const response = await axios.get<{
                                        confirmed: boolean;
                                    }>(route('password.confirm.status'));
                                    if (response.data.confirmed)
                                        onSubmit(event);
                                    else setNeedsPassword(true);
                                } catch {
                                    setRequestError(
                                        'Unable to verify your session. Your draft is retained. Check your connection and retry.',
                                    );
                                } finally {
                                    setChecking(false);
                                    requestAnimationFrame(() => {
                                        if (!processingRef.current)
                                            submitting.current = false;
                                    });
                                }
                            }}
                            className="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain"
                        >
                            {(needsPassword || requestError) && (
                                <section
                                    className="mx-4 my-3 space-y-2 border-l-4 border-amber-500 p-3"
                                    aria-label="Session confirmation"
                                >
                                    {requestError && (
                                        <p
                                            ref={requestErrorElement}
                                            tabIndex={-1}
                                            role="alert"
                                        >
                                            {requestError}
                                        </p>
                                    )}
                                    {needsPassword && (
                                        <>
                                            <p>
                                                Confirm your password to
                                                continue. Your draft stays here;
                                                save again after confirmation.
                                            </p>
                                            <label className="block">
                                                Current password
                                                <input
                                                    ref={passwordInput}
                                                    aria-label="Current password"
                                                    type="password"
                                                    autoComplete="current-password"
                                                    value={password}
                                                    onChange={(event) =>
                                                        setPassword(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="block w-full min-w-0"
                                                />
                                            </label>
                                            <button
                                                type="button"
                                                disabled={checking || !password}
                                                className={secondary}
                                                onClick={async () => {
                                                    setChecking(true);
                                                    submitting.current = true;
                                                    try {
                                                        await axios.post(
                                                            route(
                                                                'password.confirm',
                                                            ),
                                                            { password },
                                                            {
                                                                headers: {
                                                                    Accept: 'application/json',
                                                                },
                                                            },
                                                        );
                                                        setNeedsPassword(false);
                                                        setRequestError('');
                                                    } catch {
                                                        setRequestError(
                                                            'Password confirmation failed. Check your password and connection, then retry.',
                                                        );
                                                    } finally {
                                                        setPassword('');
                                                        setChecking(false);
                                                        submitting.current = false;
                                                    }
                                                }}
                                            >
                                                Confirm password
                                            </button>
                                        </>
                                    )}
                                </section>
                            )}
                            <fieldset
                                disabled={
                                    processing || checking || needsPassword
                                }
                                className="mx-auto min-w-0 max-w-7xl space-y-6 px-4 py-6 sm:px-6"
                            >
                                {Object.keys(errors).length > 0 && (
                                    <div
                                        ref={errorSummaryRef}
                                        tabIndex={-1}
                                        role="alert"
                                        className="border-l-4 border-red-600 bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
                                    >
                                        Please correct the highlighted fields
                                        before saving.
                                        <ul className="mt-2 list-disc space-y-1 pl-5">
                                            {Object.entries(errors).map(
                                                ([field, message]) =>
                                                    message ? (
                                                        <li key={field}>
                                                            {message}
                                                        </li>
                                                    ) : null,
                                            )}
                                        </ul>
                                    </div>
                                )}
                                {children(expanded)}
                            </fieldset>
                        </form>
                        <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-950 sm:px-6">
                            <div
                                role="status"
                                className="min-w-0 break-words text-sm"
                            >
                                {status}
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={close}
                                    disabled={processing}
                                    className={secondary}
                                >
                                    Cancel
                                </button>
                                {save}
                            </div>
                        </footer>
                    </DialogPanel>
                </div>
            </Dialog>
        </>
    );
}
