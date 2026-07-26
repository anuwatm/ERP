import { ReactNode } from 'react';

interface Column<T> {
    header: string;
    accessor?: keyof T | ((row: T) => ReactNode);
    className?: string;
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    keyExtractor: (item: T, index: number) => string | number;
    emptyMessage?: string;
    className?: string;
}

export default function DataTable<T>({
    columns,
    data,
    keyExtractor,
    emptyMessage = 'No data available',
    className = '',
}: DataTableProps<T>) {
    return (
        <div
            className={`overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 shadow-subtle transition-colors duration-200 ${className}`}
        >
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm text-slate-700 dark:text-white font-medium">
                    <thead className="bg-slate-50/80 dark:bg-slate-950/80 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-white border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            {columns.map((col, idx) => (
                                <th
                                    key={idx}
                                    className={`px-5 py-3.5 ${col.className || ''}`}
                                >
                                    {col.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-5 py-8 text-center text-slate-500 dark:text-slate-100 font-semibold"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            data.map((row, rowIdx) => (
                                <tr
                                    key={keyExtractor(row, rowIdx)}
                                    className="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/50"
                                >
                                    {columns.map((col, colIdx) => (
                                        <td
                                            key={colIdx}
                                            className={`px-5 py-3.5 align-middle ${col.className || ''}`}
                                        >
                                            {typeof col.accessor === 'function'
                                                ? col.accessor(row)
                                                : col.accessor
                                                  ? (row[
                                                        col.accessor
                                                    ] as ReactNode)
                                                  : null}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
