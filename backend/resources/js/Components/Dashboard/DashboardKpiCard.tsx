import Card from '@/Components/UI/Card';

export type DashboardWidgetState =
    'ready' | 'loading' | 'unavailable' | 'error';
export type DashboardTone = 'positive' | 'risk' | 'pending' | 'neutral';

const tones: Record<DashboardTone, string> = {
    positive: 'border-emerald-200 dark:border-emerald-900',
    risk: 'border-rose-200 dark:border-rose-900',
    pending: 'border-amber-200 dark:border-amber-900',
    neutral: 'border-slate-200 dark:border-slate-800',
};

type DashboardKpiCardProps = {
    question: string;
    value?: string | number;
    unit?: string;
    context: string;
    tone?: DashboardTone;
    state?: DashboardWidgetState;
    onRetry?: () => void;
    visible?: boolean;
};

export default function DashboardKpiCard({
    question,
    value,
    unit,
    context,
    tone = 'neutral',
    state = 'ready',
    onRetry,
    visible = true,
}: DashboardKpiCardProps) {
    if (!visible) return null;
    const content =
        state === 'loading'
            ? 'Loading…'
            : state === 'unavailable'
              ? 'Unavailable'
              : state === 'error'
                ? 'Could not load'
                : (value ?? 0);

    return (
        <Card
            className={tones[tone]}
            bodyClassName="space-y-2 p-5"
        >
            <h3 className="text-sm font-semibold text-erp-ink">{question}</h3>
            <output
                aria-label={question}
                className="block text-3xl font-bold tracking-tight text-slate-950 dark:text-white"
            >
                {content}
                {unit && state === 'ready' && (
                    <span className="ml-1.5 text-sm font-medium">{unit}</span>
                )}
            </output>
            <p className="text-xs text-slate-600 dark:text-slate-300">
                {context}
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
