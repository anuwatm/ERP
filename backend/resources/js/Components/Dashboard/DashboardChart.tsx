import Card from '@/Components/UI/Card';
import type { DashboardWidgetState } from './DashboardKpiCard';

export type DashboardChartPoint = {
    label: string;
    value: number;
    secondaryValue?: number;
    primaryHref?: string;
    secondaryHref?: string;
};

type DashboardChartProps = {
    title: string;
    description: string;
    data: DashboardChartPoint[];
    kind: 'bar' | 'line';
    primaryLabel: string;
    secondaryLabel?: string;
    emptyMessage?: string;
    state?: DashboardWidgetState;
    visible?: boolean;
    onRetry?: () => void;
};

const number = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });

export default function DashboardChart({
    title,
    description,
    data,
    kind,
    primaryLabel,
    secondaryLabel,
    emptyMessage = 'No data in this period.',
    state = 'ready',
    visible = true,
    onRetry,
}: DashboardChartProps) {
    if (!visible) return null;

    if (state !== 'ready') {
        const message =
            state === 'loading'
                ? 'Loading…'
                : state === 'unavailable'
                  ? 'Unavailable'
                  : 'Could not load';
        return (
            <Card title={title} description={description}>
                <p
                    role={state === 'error' ? 'alert' : 'status'}
                    className="text-sm text-slate-600 dark:text-slate-300"
                >
                    {message}
                </p>
                {state === 'error' && onRetry && (
                    <button
                        type="button"
                        onClick={onRetry}
                        className="mt-3 text-sm font-semibold text-indigo-700 underline dark:text-indigo-300"
                    >
                        Retry
                    </button>
                )}
            </Card>
        );
    }

    if (data.length === 0)
        return (
            <Card title={title} description={description}>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {emptyMessage}
                </p>
            </Card>
        );

    const values = data.flatMap((point) => [
        point.value,
        ...(secondaryLabel ? [point.secondaryValue ?? 0] : []),
    ]);
    const minimum = Math.min(0, ...values);
    const maximum = Math.max(0, ...values);
    const range = Math.max(maximum - minimum, 1);
    const width = 640;
    const height = 260;
    const padding = { top: 24, right: 24, bottom: 42, left: 44 };
    const usableWidth = width - padding.left - padding.right;
    const usableHeight = height - padding.top - padding.bottom;
    const x = (index: number) =>
        padding.left +
        (data.length === 1
            ? usableWidth / 2
            : (index * usableWidth) / (data.length - 1));
    const y = (value: number) =>
        padding.top + ((maximum - value) / range) * usableHeight;
    const zeroY = y(0);
    const points = (field: 'value' | 'secondaryValue') =>
        data
            .map((point, index) => `${x(index)},${y(point[field] ?? 0)}`)
            .join(' ');
    const barWidth = Math.max(
        8,
        Math.min(28, usableWidth / data.length / (secondaryLabel ? 3 : 1.7)),
    );

    return (
        <Card title={title} description={description}>
            <figure>
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    role="img"
                    aria-label={`${title}. ${description}`}
                    className="h-64 w-full overflow-visible"
                >
                    <title>{title}</title>
                    <line
                        x1={padding.left}
                        y1={zeroY}
                        x2={width - padding.right}
                        y2={zeroY}
                        className="stroke-slate-400 dark:stroke-slate-500"
                    />
                    {kind === 'line' ? (
                        <>
                            <polyline
                                points={points('value')}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="3"
                                className="text-indigo-600"
                            />
                            {secondaryLabel && (
                                <polyline
                                    points={points('secondaryValue')}
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="3"
                                    className="text-emerald-600"
                                />
                            )}
                            {data.map((point, index) => (
                                <circle
                                    key={point.label}
                                    cx={x(index)}
                                    cy={y(point.value)}
                                    r="4"
                                    className="fill-indigo-600"
                                >
                                    <title>{`${point.label}: ${primaryLabel} ${number.format(point.value)}`}</title>
                                </circle>
                            ))}
                        </>
                    ) : (
                        data.map((point, index) => {
                            const primaryY = y(point.value);
                            const secondaryY = y(point.secondaryValue ?? 0);
                            return (
                                <g key={point.label}>
                                    <rect
                                        x={
                                            x(index) -
                                            (secondaryLabel
                                                ? barWidth + 2
                                                : barWidth / 2)
                                        }
                                        y={Math.min(primaryY, zeroY)}
                                        width={barWidth}
                                        height={Math.abs(zeroY - primaryY)}
                                        rx="3"
                                        className="fill-indigo-600"
                                    >
                                        <title>{`${point.label}: ${primaryLabel} ${number.format(point.value)}`}</title>
                                    </rect>
                                    {secondaryLabel && (
                                        <rect
                                            x={x(index) + 2}
                                            y={Math.min(secondaryY, zeroY)}
                                            width={barWidth}
                                            height={Math.abs(
                                                zeroY - secondaryY,
                                            )}
                                            rx="3"
                                            className="fill-emerald-600"
                                        >
                                            <title>{`${point.label}: ${secondaryLabel} ${number.format(point.secondaryValue ?? 0)}`}</title>
                                        </rect>
                                    )}
                                </g>
                            );
                        })
                    )}
                    {data.map((point, index) => (
                        <text
                            key={`${point.label}-label`}
                            x={x(index)}
                            y={height - 14}
                            textAnchor="middle"
                            className="fill-slate-600 text-[11px] dark:fill-slate-300"
                        >
                            {point.label}
                        </text>
                    ))}
                </svg>
                <figcaption className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                    <span>
                        <span
                            aria-hidden="true"
                            className="mr-1 inline-block h-2 w-2 rounded-full bg-indigo-600"
                        />
                        {primaryLabel}
                    </span>
                    {secondaryLabel && (
                        <span>
                            <span
                                aria-hidden="true"
                                className="mr-1 inline-block h-2 w-2 rounded-full bg-emerald-600"
                            />
                            {secondaryLabel}
                        </span>
                    )}
                </figcaption>
            </figure>
            <details className="mt-4 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                <summary className="cursor-pointer font-semibold">
                    View data table
                </summary>
                <div className="mt-3 overflow-x-auto">
                    <table className="min-w-full text-left text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 dark:border-slate-700">
                                <th className="p-2">Period</th>
                                <th className="p-2">{primaryLabel}</th>
                                {secondaryLabel && (
                                    <th className="p-2">{secondaryLabel}</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {data.map((point) => (
                                <tr
                                    key={`${point.label}-row`}
                                    className="border-b border-slate-100 dark:border-slate-800"
                                >
                                    <td className="p-2">{point.label}</td>
                                    <td className="p-2">
                                        {point.primaryHref ? (
                                            <a
                                                href={point.primaryHref}
                                                className="font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-indigo-300"
                                                aria-label={`View ${primaryLabel} for ${point.label}`}
                                            >
                                                {number.format(point.value)}
                                            </a>
                                        ) : (
                                            number.format(point.value)
                                        )}
                                    </td>
                                    {secondaryLabel && (
                                        <td className="p-2">
                                            {point.secondaryHref ? (
                                                <a
                                                    href={point.secondaryHref}
                                                    className="font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-indigo-300"
                                                    aria-label={`View ${secondaryLabel} for ${point.label}`}
                                                >
                                                    {number.format(
                                                        point.secondaryValue ??
                                                            0,
                                                    )}
                                                </a>
                                            ) : (
                                                number.format(
                                                    point.secondaryValue ?? 0,
                                                )
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </Card>
    );
}
