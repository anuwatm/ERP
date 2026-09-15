/** The module supplies authoritative VAT/WHT/discount/FX/rounding; the shared shell never recalculates money. */
export default function EditorFinancialSummary({
    amounts,
    pending,
    error,
}: {
    amounts: Array<{ label: string; value: string; currency: string }>;
    pending: boolean;
    error?: string;
}) {
    if (error) return <p role="alert">Totals unavailable: {error}</p>;
    if (pending) return <p role="status">Updating totals…</p>;
    return (
        <dl
            aria-label="Document totals"
            className="flex flex-wrap gap-x-4 gap-y-1"
        >
            {amounts.map((amount) => (
                <div key={amount.label}>
                    <dt className="text-xs text-gray-500">{amount.label}</dt>
                    <dd className="font-semibold tabular-nums">
                        {amount.value} {amount.currency}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
