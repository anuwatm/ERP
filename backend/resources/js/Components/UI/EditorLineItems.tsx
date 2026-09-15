import type { ReactNode } from 'react';

/** Domain owns row values, stable IDs and calculations; server indexes always refer to current order. */
export default function EditorLineItems<T>({
    rows,
    rowKey,
    columns,
    renderCell,
    onAdd,
    onRemove,
    errors,
    disabled = false,
    prefix = 'items',
}: {
    rows: T[];
    rowKey: (row: T) => string;
    columns: Array<{ key: string; label: string }>;
    renderCell: (
        row: T,
        index: number,
        field: string,
        errorId?: string,
    ) => ReactNode;
    onAdd: () => void;
    onRemove: (index: number) => void;
    errors: Record<string, string | undefined>;
    disabled?: boolean;
    prefix?: string;
}) {
    return (
        <section aria-label="Line items" className="min-w-0 space-y-3">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">Line items</h3>
                <button
                    type="button"
                    disabled={disabled}
                    onClick={onAdd}
                    className="min-h-10 rounded border px-3"
                >
                    + Add item
                </button>
            </div>
            {errors[prefix] && (
                <p role="alert" className="text-sm text-red-700">
                    {errors[prefix]}
                </p>
            )}
            <div
                role="region"
                aria-label="Scrollable line items"
                tabIndex={0}
                className="max-h-80 overflow-auto overscroll-contain"
            >
                <table className="w-full min-w-[640px] text-left text-sm">
                    <thead className="sticky top-0 z-10 bg-white dark:bg-gray-900">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className="p-2"
                                >
                                    {column.label}
                                </th>
                            ))}
                            <th scope="col" className="p-2">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={rowKey(row)} className="border-b">
                                {columns.map((column) => {
                                    const name = `${prefix}.${index}.${column.key}`;
                                    const error = errors[name];
                                    const errorId = `${prefix}-${index}-${column.key}-error`;
                                    return (
                                        <td
                                            key={column.key}
                                            className="min-w-32 p-2 align-top"
                                        >
                                            {renderCell(
                                                row,
                                                index,
                                                column.key,
                                                error ? errorId : undefined,
                                            )}
                                            {error && (
                                                <p
                                                    id={errorId}
                                                    role="alert"
                                                    className="mt-1 text-sm text-red-700 dark:text-red-300"
                                                >
                                                    {error}
                                                </p>
                                            )}
                                        </td>
                                    );
                                })}
                                <td className="p-2 align-top">
                                    <button
                                        type="button"
                                        disabled={disabled}
                                        aria-label={`Remove item ${index + 1}`}
                                        onClick={() => onRemove(index)}
                                        className="min-h-10 rounded border px-3"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
