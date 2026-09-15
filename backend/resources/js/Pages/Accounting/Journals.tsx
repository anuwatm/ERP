import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorLineItems from '@/Components/UI/EditorLineItems';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
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
type DraftLine = {
    key: string;
    account_code: string;
    debit: string;
    credit: string;
};
const emptyLine = (): DraftLine => ({
    key: crypto.randomUUID(),
    account_code: '',
    debit: '',
    credit: '',
});

export default function Journals({
    journals,
    accounts,
}: {
    journals: Journal[];
    accounts: Account[];
}) {
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const form = useForm({
        posting_date: date,
        description: '',
        idempotency_key: crypto.randomUUID(),
        lines: [emptyLine(), emptyLine()],
    });
    const { auth } = usePage<PageProps>().props;
    const canPost = auth.permissions.includes('accounting.journals.post');
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.clearErrors();
    };
    const create = () => {
        if (!canPost || form.processing) return;
        guard.request(() => {
            close();
            const data = {
                posting_date: date,
                description: '',
                idempotency_key: crypto.randomUUID(),
                lines: [emptyLine(), emptyLine()],
            };
            form.setDefaults(data);
            form.setData(data);
            setOpen(true);
        });
    };
    const setLine = (
        index: number,
        field: 'account_code' | 'debit' | 'credit',
        value: string,
    ) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === index
                    ? {
                          ...line,
                          [field]: value,
                          ...(field === 'debit'
                              ? { credit: '' }
                              : field === 'credit'
                                ? { debit: '' }
                                : {}),
                      }
                    : line,
            ),
        );
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canPost || form.processing) return;
        form.transform((data) => ({
            ...data,
            lines: data.lines.map(({ account_code, debit, credit }) => ({
                account_code,
                debit,
                credit,
            })),
        }));
        form.post(route('accounting.journals.store'), {
            preserveScroll: true,
            onSuccess: close,
        });
    }

    return (
        <AuthenticatedLayout>
            <Head title="Journals" />
            <div className="space-y-6">
                <PageHeader
                    actions={
                        canPost && (
                            <PrimaryButton onClick={create}>
                                + Manual Journal
                            </PrimaryButton>
                        )
                    }
                    title="Journal Entries"
                    description="Posted journals are immutable; use reversal for corrections."
                />
                <div className="space-y-6">
                    <DiscardChangesDialog
                        open={guard.confirming}
                        onKeep={guard.keep}
                        onDiscard={guard.discard}
                    />
                    <CreateEditDialog
                        open={open}
                        minimized={minimized}
                        onMinimize={setMinimized}
                        onClose={() => guard.request(close)}
                        title="Manual Journal"
                        breadcrumb="Accounting / Journals"
                        entityLabel="Journal"
                        draftLabel={form.data.description || 'Manual journal'}
                        storageKey="journal-editor-size"
                        formId="journal-editor"
                        processing={form.processing}
                        saveDisabled={!canPost}
                        saveLabel="Post Journal"
                        status="Post creates an immutable journal; backend validates balance and open period"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <div className="space-y-6 p-4 sm:p-6">
                                <section
                                    aria-label="Journal details"
                                    className={
                                        expanded
                                            ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                                            : 'grid gap-4 sm:grid-cols-2'
                                    }
                                >
                                    <EditorField
                                        id="journal-date"
                                        label="Posting Date"
                                        type="date"
                                        value={form.data.posting_date}
                                        error={form.errors.posting_date}
                                        onChange={(v) =>
                                            form.setData('posting_date', v)
                                        }
                                        required
                                    />
                                    <EditorField
                                        id="journal-description"
                                        label="Description"
                                        value={form.data.description}
                                        error={form.errors.description}
                                        onChange={(v) =>
                                            form.setData('description', v)
                                        }
                                        required
                                    />
                                </section>
                                <EditorLineItems
                                    rows={form.data.lines}
                                    rowKey={(line) => line.key}
                                    prefix="lines"
                                    columns={[
                                        {
                                            key: 'account_code',
                                            label: 'Account',
                                        },
                                        { key: 'debit', label: 'Debit' },
                                        { key: 'credit', label: 'Credit' },
                                    ]}
                                    disabled={form.processing}
                                    errors={form.errors}
                                    onAdd={() => {
                                        form.clearErrors();
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            emptyLine(),
                                        ]);
                                    }}
                                    onRemove={(i) => {
                                        form.clearErrors();
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (_, index) => index !== i,
                                            ),
                                        );
                                    }}
                                    renderCell={(
                                        line,
                                        index,
                                        field,
                                        errorId,
                                    ) =>
                                        field === 'account_code' ? (
                                            <EditorReference
                                                id={
                                                    'journal-account-' +
                                                    line.key
                                                }
                                                label={
                                                    'Line ' +
                                                    (index + 1) +
                                                    ' Account'
                                                }
                                                value={line.account_code}
                                                onChange={(v) =>
                                                    setLine(
                                                        index,
                                                        'account_code',
                                                        v,
                                                    )
                                                }
                                                error={
                                                    (
                                                        form.errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        'lines.' +
                                                            index +
                                                            '.account_code'
                                                    ]
                                                }
                                                options={accounts.map((a) => ({
                                                    id: a.code,
                                                    label:
                                                        a.code + ' - ' + a.name,
                                                }))}
                                                required
                                            />
                                        ) : (
                                            <input
                                                aria-label={
                                                    'Line ' +
                                                    (index + 1) +
                                                    ' ' +
                                                    field
                                                }
                                                aria-invalid={!!errorId}
                                                aria-describedby={errorId}
                                                className="w-full min-w-0 rounded border-gray-300 bg-white text-gray-900 dark:bg-gray-900 dark:text-white"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={
                                                    line[
                                                        field as
                                                            'debit' | 'credit'
                                                    ]
                                                }
                                                onChange={(e) =>
                                                    setLine(
                                                        index,
                                                        field as
                                                            'debit' | 'credit',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )
                                    }
                                />
                            </div>
                        )}
                    </CreateEditDialog>
                    <label className="block text-sm">
                        Reversal posting date
                        <input
                            className="ml-3 rounded border-gray-300"
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </label>
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
                                        auth.permissions.includes(
                                            'accounting.journals.reverse',
                                        ) &&
                                        item.status === 'posted' && (
                                            <button
                                                type="button"
                                                disabled={
                                                    open || form.processing
                                                }
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
