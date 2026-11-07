import { ReactNode } from 'react';

interface Column<T> {
    header: string;
    accessor?: keyof T | ((row: T) => ReactNode);
    className?: string;
}

interface DataTableProps<T> {
    label?: string;
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
    label = 'ตารางข้อมูล เลื่อนแนวนอนเพื่อดูคอลัมน์เพิ่มเติม',
}: DataTableProps<T>) {
    return (
        <div
            className={`overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 shadow-subtle transition-colors duration-200 ${className}`}
        >
            <div
                className="overflow-x-auto"
                role="region"
                aria-label={label}
                tabIndex={0}
            >
                <table className="w-full text-left text-sm text-slate-700 dark:text-white font-medium">
                    <thead className="border-b border-emerald-100 bg-emerald-50 text-xs font-semibold text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-100">
                        <tr>
                            {columns.map((col, idx) => (
                                <th
                                    key={idx}
                                    scope="col"
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
