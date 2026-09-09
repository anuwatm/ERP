import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

type Config = {
    id: string;
    enabled: boolean;
    qr_type: string;
    bank_account_id: string;
};
export default function PaymentGateway({
    config,
    banks,
    transactions,
    events,
}: {
    config: Config | null;
    events: {
        id: string;
        event_id: string;
        status: string;
        reason: string | null;
    }[];
    banks: { id: string; bank_name: string; account_name: string }[];
    transactions: {
        id: string;
        reference: string;
        amount_minor: number;
        status: string;
        expires_at: string;
    }[];
}) {
    const form = useForm({
        enabled: config?.enabled ?? false,
        qr_type: config?.qr_type ?? 'biller',
        bank_account_id: config?.bank_account_id ?? '',
        recipient_id: '',
        webhook_secret: '',
    });
    return (
        <AuthenticatedLayout header={<h2>Payment gateway</h2>}>
            <Head title="Payment gateway" />
            <main className="mx-auto max-w-4xl space-y-6 p-6">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('gateway.configure'), {
                            onSuccess: () =>
                                form.reset('recipient_id', 'webhook_secret'),
                        });
                    }}
                    className="grid gap-4"
                >
                    <label>
                        <input
                            type="checkbox"
                            checked={form.data.enabled}
                            onChange={(e) =>
                                form.setData('enabled', e.target.checked)
                            }
                        />{' '}
                        Enable PromptPay and settlement bridge
                    </label>
                    <label>
                        Recipient type
                        <select
                            className="block w-full"
                            value={form.data.qr_type}
                            onChange={(e) =>
                                form.setData('qr_type', e.target.value)
                            }
                        >
                            <option value="biller">
                                Registered Biller ID (15 digits)
                            </option>
                            <option value="tax_id">
                                PromptPay Tax ID (13 digits)
                            </option>
                        </select>
                    </label>
                    <label>
                        Recipient ID
                        <input
                            className="block w-full"
                            autoComplete="off"
                            value={form.data.recipient_id}
                            onChange={(e) =>
                                form.setData('recipient_id', e.target.value)
                            }
                            placeholder={config ? 'Unchanged' : ''}
                        />
                    </label>
                    <label>
                        Receiving bank
                        <select
                            className="block w-full"
                            required
                            value={form.data.bank_account_id}
                            onChange={(e) =>
                                form.setData('bank_account_id', e.target.value)
                            }
                        >
                            <option value="">Select account</option>
                            {banks.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.bank_name} - {b.account_name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Settlement bridge signing secret
                        <input
                            className="block w-full"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.webhook_secret}
                            onChange={(e) =>
                                form.setData('webhook_secret', e.target.value)
                            }
                            placeholder={config ? 'Unchanged' : ''}
                        />
                    </label>
                    {Object.entries(form.errors).map(([key, value]) => (
                        <p role="alert" className="text-red-700" key={key}>
                            {value}
                        </p>
                    ))}
                    <button
                        className="w-fit bg-emerald-700 px-4 py-2 text-white disabled:opacity-50"
                        disabled={form.processing}
                    >
                        Save
                    </button>
                </form>
                <section>
                    <h2 className="mb-3 font-semibold">Recent QR payments</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>THB</th>
                                    <th>Status</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.map((t) => (
                                    <tr className="border-b" key={t.id}>
                                        <td className="py-3">{t.reference}</td>
                                        <td>
                                            {(t.amount_minor / 100).toFixed(2)}
                                        </td>
                                        <td>{t.status}</td>
                                        <td>{t.expires_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <section>
                    <h2 className="mb-3 font-semibold">Settlement events</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Status</th>
                                    <th>Review reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map((event) => (
                                    <tr key={event.id} className="border-b">
                                        <td className="max-w-xs break-all py-3">
                                            {event.event_id}
                                        </td>
                                        <td>{event.status}</td>
                                        <td>{event.reason ?? '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
