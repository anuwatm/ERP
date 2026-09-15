import { useId } from 'react';

/** Selection stays in parent memory. Existing DMS links are never deleted by this control. */
export default function EditorAttachments({
    selected,
    onChange,
    existing,
    error,
    disabled = false,
    accept,
}: {
    selected: File | null;
    onChange: (file: File | null) => void;
    existing: Array<{ id: string; name: string; url?: string; status: string }>;
    error?: string;
    disabled?: boolean;
    accept?: string;
}) {
    const id = useId();
    return (
        <section aria-label="Attachments" className="min-w-0 space-y-2">
            <label htmlFor={id} className="block font-medium">
                Attachment
            </label>
            <input
                id={id}
                type="file"
                accept={accept}
                disabled={disabled}
                aria-invalid={!!error}
                aria-describedby={error ? `${id}-error` : undefined}
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
                className="block max-w-full text-sm"
            />
            {selected && (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="break-all">Selected: {selected.name}</span>
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={() => {
                            onChange(null);
                            const input = document.getElementById(
                                id,
                            ) as HTMLInputElement | null;
                            if (input) input.value = '';
                        }}
                        className="min-h-10 underline"
                    >
                        Clear selected file
                    </button>
                </div>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-sm text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
            <ul className="space-y-1 text-sm">
                {existing.map((file) => (
                    <li key={file.id} className="break-all">
                        {file.url ? (
                            <a href={file.url} className="underline">
                                {file.name}
                            </a>
                        ) : (
                            file.name
                        )}{' '}
                        · {file.status}
                    </li>
                ))}
            </ul>
        </section>
    );
}
