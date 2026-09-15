import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { useState, type InputHTMLAttributes } from 'react';

const control =
    'w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
type FieldProps = {
    id: string;
    label: string;
    value: string;
    error?: string;
    required?: boolean;
    onChange: (value: string) => void;
};

export function EditorField({
    id,
    label,
    value,
    error,
    required,
    onChange,
    multiline = false,
    ...input
}: FieldProps & {
    multiline?: boolean;
    type?: string;
    maxLength?: number;
    min?: number;
    max?: number;
    step?: InputHTMLAttributes<HTMLInputElement>['step'];
}) {
    const common = {
        id,
        name: id,
        value,
        required,
        maxLength: input.maxLength,
        'aria-invalid': !!error,
        'aria-describedby': error ? `${id}-error` : undefined,
        className: control,
    };
    return (
        <div className="min-w-0">
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium">
                {label}
                {required && ' *'}
            </label>
            {multiline ? (
                <textarea
                    {...common}
                    rows={3}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <input
                    {...common}
                    {...input}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

export function EditorChoice({
    id,
    label,
    value,
    error,
    required,
    onChange,
    options,
}: FieldProps & { options: string[] }) {
    return (
        <div className="min-w-0">
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium">
                {label}
                {required && ' *'}
            </label>
            <select
                id={id}
                name={id}
                value={value}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className={control}
                aria-invalid={!!error}
                aria-describedby={error ? `${id}-error` : undefined}
            >
                {value && !options.includes(value) && (
                    <option value={value}>{value} (current)</option>
                )}
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
            {error && (
                <p
                    id={`${id}-error`}
                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

export function EditorReference({
    id,
    label,
    value,
    error,
    required,
    onChange,
    options,
    currentLabel,
    emptyLabel = 'None',
    onCreate,
}: FieldProps & {
    options: Array<{ id: string; label: string }>;
    currentLabel?: string;
    emptyLabel?: string;
    onCreate?: () => void;
}) {
    const [query, setQuery] = useState('');
    const filtered = options.filter((option) =>
        option.label.toLowerCase().includes(query.toLowerCase()),
    );
    const unavailable =
        !!value && !options.some((option) => option.id === value);
    return (
        <div className="relative min-w-0">
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium">
                {label}
                {required && ' *'}
            </label>
            <Combobox
                value={value}
                onChange={(selected: string | null) => {
                    // Typing/searching must not silently erase an existing reference.
                    if (selected !== null) onChange(selected);
                }}
                onClose={() => setQuery('')}
            >
                <div className="relative">
                    <ComboboxInput
                        id={id}
                        className={`${control} pr-10`}
                        displayValue={(selected: string) =>
                            options.find((option) => option.id === selected)
                                ?.label ??
                            (selected
                                ? currentLabel ||
                                  'Current selection (unavailable)'
                                : '')
                        }
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={emptyLabel}
                        aria-required={required}
                        aria-invalid={!!error}
                        aria-describedby={error ? `${id}-error` : undefined}
                    />
                    <ComboboxButton
                        className="absolute inset-y-0 right-0 w-10"
                        aria-label={`Choose ${label.toLowerCase()}`}
                    >
                        ⌄
                    </ComboboxButton>
                </div>
                <ComboboxOptions className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-gray-300 bg-white p-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                    {!required && (
                        <ComboboxOption
                            value=""
                            className="cursor-pointer rounded px-3 py-2 text-sm data-[focus]:bg-indigo-100 dark:data-[focus]:bg-gray-700"
                        >
                            {emptyLabel}
                        </ComboboxOption>
                    )}
                    {filtered.map((option) => (
                        <ComboboxOption
                            key={option.id}
                            value={option.id}
                            className="cursor-pointer break-words rounded px-3 py-2 text-sm data-[focus]:bg-indigo-100 data-[selected]:font-semibold dark:data-[focus]:bg-gray-700"
                        >
                            {option.label}
                        </ComboboxOption>
                    ))}
                    {!filtered.length && (
                        <p className="px-3 py-2 text-sm">No matching options</p>
                    )}
                </ComboboxOptions>
            </Combobox>
            {onCreate && (
                <button
                    type="button"
                    onClick={onCreate}
                    className="mt-2 min-h-10 text-sm font-medium text-indigo-700 underline dark:text-indigo-300"
                >
                    + Create {label}
                </button>
            )}
            {unavailable && (
                <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                    Current reference retained. Select a replacement only if
                    needed.
                </p>
            )}
            {error && (
                <p
                    id={`${id}-error`}
                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
