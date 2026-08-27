import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Account = { id: string; code: string; name: string };
type Line = { id: string; debit: string; credit: string; account: Account };
type Journal = {
    id: string;
    entry_no: string;
    posting_date: string;
    description: string;
    status: string;
    source_type: string | null;
    lines: Line[];
};
type DraftLine = { account_code: string; debit: string; credit: string };

export default function Journals({
    journals,
    accounts,
}: {
    journals: Journal[];
    accounts: Account[];
}) {
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [description, setDescription] = useState('');
    const [lines, setLines] = useState<DraftLine[]>([
        { account_code: '', debit: '', credit: '' },
        { account_code: '', debit: '', credit: '' },
    ]);

    function submit(event: FormEvent) {
        event.preventDefault();
        router.post(route('accounting.journals.store'), {
            posting_date: date,
            description,
            idempotency_key: crypto.randomUUID(),
            lines,
        });
    }

    return (
        <AuthenticatedLayout>
            <Head title="Journals" />
            <div className="space-y-6">
                <PageHeader
                    title="Journal Entries"
                    description="Posted journals are immutable; use reversal for corrections."
                />
                <div className="grid gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
                    <Card title="Manual Journal">
                        <form className="space-y-3" onSubmit={submit}>
                            <input
                                required
                                type="date"
                                value={date}
                                onChange={(event) =>
                                    setDate(event.target.value)
                                }
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                required
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="Description"
                                className="w-full rounded-md border-slate-300"
                            />
                            {lines.map((line, index) => (
                                <div
                                    className="grid grid-cols-3 gap-2"
                                    key={index}
                                >
                                    <select
                                        required
                                        value={line.account_code}
                                        onChange={(event) =>
                                            setLines(
                                                lines.map((item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              account_code:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : item,
                                                ),
                                            )
                                        }
                                        className="col-span-3 rounded-md border-slate-300"
                                    >
                                        <option value="">Account</option>
                                        {accounts.map((account) => (
                                            <option
                                                key={account.id}
                                                value={account.code}
                                            >
                                                {account.code} - {account.name}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        value={line.debit}
                                        onChange={(event) =>
                                            setLines(
                                                lines.map((item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              debit: event
                                                                  .target.value,
                                                              credit: '',
                                                          }
                                                        : item,
                                                ),
                                            )
                                        }
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Debit"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        value={line.credit}
                                        onChange={(event) =>
                                            setLines(
                                                lines.map((item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              credit: event
                                                                  .target.value,
                                                              debit: '',
                                                          }
                                                        : item,
                                                ),
                                            )
                                        }
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Credit"
                                        className="rounded-md border-slate-300"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setLines(
                                                lines.filter(
                                                    (_, itemIndex) =>
                                                        itemIndex !== index,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </button>
                                </div>
                            ))}
                            <button
                                type="button"
                                onClick={() =>
                                    setLines([
                                        ...lines,
                                        {
                                            account_code: '',
                                            debit: '',
                                            credit: '',
                                        },
                                    ])
                                }
                            >
                                Add Line
                            </button>
                            <PrimaryButton>Post Journal</PrimaryButton>
                        </form>
                    </Card>
                    <Card title="Posted Journals">
                        <DataTable
                            data={journals}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'Entry',
                                    accessor: (item) => item.entry_no,
                                },
                                {
                                    header: 'Date',
                                    accessor: (item) => item.posting_date,
                                },
                                {
                                    header: 'Description',
                                    accessor: (item) => item.description,
                                },
                                {
                                    header: 'Source',
                                    accessor: (item) =>
                                        item.source_type ?? 'manual',
                                },
                                {
                                    header: 'Debit',
                                    accessor: (item) =>
                                        item.lines
                                            .reduce(
                                                (total, line) =>
                                                    total + Number(line.debit),
                                                0,
                                            )
                                            .toFixed(2),
                                },
                                {
                                    header: 'Credit',
                                    accessor: (item) =>
                                        item.lines
                                            .reduce(
                                                (total, line) =>
                                                    total + Number(line.credit),
                                                0,
                                            )
                                            .toFixed(2),
                                },
                                {
                                    header: 'Status',
                                    accessor: (item) => item.status,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (item) =>
                                        item.status === 'posted' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'accounting.journals.reverse',
                                                            item.id,
                                                        ),
                                                        {
                                                            posting_date: date,
                                                            reason: 'Manual correction',
                                                        },
                                                    )
                                                }
                                            >
                                                Reverse
                                            </button>
                                        ),
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
