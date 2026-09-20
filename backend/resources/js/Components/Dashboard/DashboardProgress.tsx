import Card from '@/Components/UI/Card';

type DashboardProgressProps = {
    title: string;
    description: string;
    value: number;
    label: string;
    visible?: boolean;
};

export default function DashboardProgress({
    title,
    description,
    value,
    label,
    visible = true,
}: DashboardProgressProps) {
    if (!visible) return null;
    const bounded = Math.max(0, Math.min(value, 100));

    return (
        <Card title={title} description={description}>
            <div className="flex items-center justify-between gap-3 text-sm">
                <span>{label}</span>
                <span className="font-bold">{bounded.toFixed(1)}%</span>
            </div>
            <div
                className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                role="progressbar"
                aria-label={title}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={bounded}
            >
                <div
                    className="h-full rounded-full bg-indigo-600"
                    style={{ width: `${bounded}%` }}
                />
            </div>
        </Card>
    );
}
