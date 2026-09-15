import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorChoice } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import { PageProps } from '@/Types';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Currency = {
    id: string;
    code: string;
    name: string;
    decimal_places: number;
    status: string;
};
type Rate = {
    id: string;
    base_currency: string;
    quote_currency: string;
    rate_date: string;
    rate: string;
    source: string | null;
};
type Exposure = {
    accounts_receivable: string;
    accounts_payable: string;
    fcd: string;
};

export default function Currencies({
    baseCurrency,
    currencies,
    rates,
    exposure,
}: {
    baseCurrency: string;
    currencies: Currency[];
    rates: Rate[];
    exposure: Exposure;
}) {
    const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
    const currency = useForm({
        code: '',
        name: '',
        decimal_places: 2,
        status: 'active',
    });
    const rate = useForm({
        quote_currency: '',
        rate_date: new Date().toISOString().slice(0, 10),
        rate: '',
        source: 'manual',
    });
    const [editor, setEditor] = useState<'currency' | 'rate' | null>(null);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('fx.manage');
    const busy = currency.processing || rate.processing;
    const guard = useDiscardGuard(
        editor === 'currency'
            ? currency.isDirty
            : editor === 'rate' && rate.isDirty,
        busy,
    );
    const close = () => {
        setEditor(null);
        setMinimized(false);
        currency.reset();
        rate.reset();
        currency.clearErrors();
        rate.clearErrors();
    };
    const openEditor = (next: 'currency' | 'rate') => {
        if (!canManage || busy) return;
        guard.request(() => {
            close();
            const blankCurrency = {
                code: '',
                name: '',
                decimal_places: 2,
                status: 'active',
            };
            const blankRate = {
                quote_currency: '',
                rate_date: new Date().toISOString().slice(0, 10),
                rate: '',
                source: 'manual',
            };
            currency.setDefaults(blankCurrency);
            currency.setData(blankCurrency);
            rate.setDefaults(blankRate);
            rate.setData(blankRate);
            setEditor(next);
        });
    };
    const editCurrency = (row: Currency) => {
        if (!canManage || busy) return;
        guard.request(() => {
            close();
            const data = {
                code: row.code,
                name: row.name,
                decimal_places: row.decimal_places,
                status: row.status,
            };
            currency.setDefaults(data);
            currency.setData(data);
            setEditor('currency');
        });
    };
    const editRate = (row: Rate) => {
        if (!canManage || busy) return;
        guard.request(() => {
            close();
            const data = {
                quote_currency: row.quote_currency,
                rate_date: row.rate_date.slice(0, 10),
                rate: row.rate,
                source: row.source ?? '',
            };
            rate.setDefaults(data);
            rate.setData(data);
            setEditor('rate');
        });
    };
    const submitCurrency = (event: FormEvent) => {
        event.preventDefault();
        if (!canManage || busy) return;
        currency.post(route('currencies.store'), {
            preserveScroll: true,
            onSuccess: close,
        });
    };
    const submitRate = (event: FormEvent) => {
        event.preventDefault();
        if (!canManage || busy) return;
        rate.post(route('exchange-rates.store'), {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Currencies & FX" />
            <div className="space-y-6">
                <PageHeader
                    actions={
                        canManage && (
                            <>
                                <PrimaryButton
                                    onClick={() => openEditor('currency')}
                                >
                                    Set Currency
                                </PrimaryButton>
                                <PrimaryButton
                                    onClick={() => openEditor('rate')}
                                >
                                    Set Exchange Rate
                                </PrimaryButton>
                            </>
                        )
                    }
                    title="Currencies & FX"
                    description={`Base currency: ${baseCurrency}. Rates are snapshotted on documents and payments.`}
                />
                <DiscardChangesDialog
                    open={guard.confirming}
                    onKeep={guard.keep}
                    onDiscard={guard.discard}
                />
                <CreateEditDialog
                    open={editor !== null}
                    minimized={minimized}
                    onMinimize={setMinimized}
                    onClose={() => guard.request(close)}
                    title={
                        editor === 'currency'
                            ? 'Set Currency'
                            : 'Set Exchange Rate'
                    }
                    breadcrumb="Currencies & FX / Settings"
                    entityLabel="FX settings"
                    draftLabel={
                        editor === 'currency'
                            ? currency.data.code || 'Currency'
                            : rate.data.quote_currency || 'Exchange rate'
                    }
                    storageKey="fx-editor-size"
                    formId="fx-editor"
                    processing={busy}
                    saveDisabled={!canManage}
                    saveLabel="Save"
                    status="Existing document and payment snapshots remain unchanged"
                    errors={
                        editor === 'currency' ? currency.errors : rate.errors
                    }
                    onSubmit={
                        editor === 'currency' ? submitCurrency : submitRate
                    }
                >
                    {(expanded) => (
                        <section
                            aria-label={
                                editor === 'currency'
                                    ? 'Currency settings'
                                    : 'Rate settings'
                            }
                            className="space-y-4 p-4 sm:p-6"
                        >
                            <p className="text-sm">
                                {editor === 'currency'
                                    ? 'Saving an existing code updates its currency settings.'
                                    : 'Saving an existing pair and date updates the reference rate. Base currency: ' +
                                      baseCurrency}
                            </p>
                            <div
                                className={
                                    expanded
                                        ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                                        : 'grid gap-4 sm:grid-cols-2'
                                }
                            >
                                {editor === 'currency' ? (
                                    <>
                                        <EditorField
                                            id="currency-code"
                                            label="Code"
                                            value={String(currency.data.code)}
                                            error={currency.errors.code}
                                            onChange={(value) =>
                                                currency.setData(
                                                    'code',
                                                    value.toUpperCase(),
                                                )
                                            }
                                            required
                                            maxLength={3}
                                        />
                                        <EditorField
                                            id="currency-name"
                                            label="Name"
                                            value={String(currency.data.name)}
                                            error={currency.errors.name}
                                            onChange={(value) =>
                                                currency.setData('name', value)
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="currency-decimal_places"
                                            label="Decimal Places"
                                            value={String(
                                                currency.data.decimal_places,
                                            )}
                                            error={
                                                currency.errors.decimal_places
                                            }
                                            onChange={(value) =>
                                                currency.setData(
                                                    'decimal_places',
                                                    Number(value),
                                                )
                                            }
                                            type="number"
                                            min={0}
                                            max={6}
                                            step="1"
                                            required
                                        />
                                        <EditorChoice
                                            id="currency-status"
                                            label="Status"
                                            value={currency.data.status}
                                            error={currency.errors.status}
                                            onChange={(v) =>
                                                currency.setData('status', v)
                                            }
                                            options={['active', 'inactive']}
                                        />
                                    </>
                                ) : (
                                    <>
                                        <EditorField
                                            id="rate-quote_currency"
                                            label="Quote Currency"
                                            value={String(
                                                rate.data.quote_currency,
                                            )}
                                            error={rate.errors.quote_currency}
                                            onChange={(value) =>
                                                rate.setData(
                                                    'quote_currency',
                                                    value.toUpperCase(),
                                                )
                                            }
                                            required
                                            maxLength={3}
                                        />
                                        <EditorField
                                            id="rate-rate_date"
                                            label="Rate Date"
                                            value={String(rate.data.rate_date)}
                                            error={rate.errors.rate_date}
                                            onChange={(value) =>
                                                rate.setData('rate_date', value)
                                            }
                                            type="date"
                                            required
                                        />
                                        <EditorField
                                            id="rate-rate"
                                            label="Rate"
                                            value={String(rate.data.rate)}
                                            error={rate.errors.rate}
                                            onChange={(value) =>
                                                rate.setData('rate', value)
                                            }
                                            type="number"
                                            min={0.000001}
                                            step="0.000001"
                                            required
                                        />
                                        <EditorField
                                            id="rate-source"
                                            label="Source"
                                            value={String(rate.data.source)}
                                            error={rate.errors.source}
                                            onChange={(value) =>
                                                rate.setData('source', value)
                                            }
                                        />
                                    </>
                                )}
                            </div>
                        </section>
                    )}
                </CreateEditDialog>
                <Card>
                    <div className="flex flex-wrap items-end justify-between gap-3 p-5">
                        <div>
                            <h2 className="text-base font-semibold">
                                Month-end revaluation
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Revalues open foreign-currency AR, AP, and FCD.
                                Reversal is posted separately.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <TextInput
                                type="month"
                                value={month}
                                onChange={(e) => setMonth(e.target.value)}
                            />
                            <PrimaryButton
                                disabled={
                                    editor !== null ||
                                    busy ||
                                    !auth.permissions.includes('fx.revalue')
                                }
                                onClick={() =>
                                    router.post(
                                        route('fx-revaluations.store'),
                                        { month },
                                    )
                                }
                            >
                                Revalue
                            </PrimaryButton>
                            <button
                                type="button"
                                disabled={
                                    editor !== null ||
                                    busy ||
                                    !auth.permissions.includes('fx.revalue')
                                }
                                onClick={() =>
                                    router.post(
                                        route('fx-revaluations.reverse'),
                                        { month },
                                    )
                                }
                                className="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700"
                            >
                                Reverse
                            </button>
                        </div>
                    </div>
                </Card>
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['AR exposure', exposure.accounts_receivable],
                        ['AP exposure', exposure.accounts_payable],
                        ['FCD exposure', exposure.fcd],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <div className="p-4">
                                <div className="text-sm text-slate-500">
                                    {label}
                                </div>
                                <div className="mt-1 text-lg font-semibold">
                                    {Number(value).toFixed(2)} {baseCurrency}
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <DataTable
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Code',
                                    accessor: (row: Currency) => row.code,
                                },
                                {
                                    header: 'Name',
                                    accessor: (row: Currency) => row.name,
                                },
                                {
                                    header: 'Status',
                                    accessor: (row: Currency) => row.status,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row: Currency) =>
                                        canManage && (
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() =>
                                                    editCurrency(row)
                                                }
                                            >
                                                Edit currency
                                            </button>
                                        ),
                                },
                            ]}
                            data={currencies}
                        />
                    </Card>
                    <Card>
                        <DataTable
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Pair',
                                    accessor: (row: Rate) =>
                                        `${row.base_currency}/${row.quote_currency}`,
                                },
                                {
                                    header: 'Date',
                                    accessor: (row: Rate) => row.rate_date,
                                },
                                {
                                    header: 'Rate',
                                    accessor: (row: Rate) =>
                                        Number(row.rate).toFixed(6),
                                },
                                {
                                    header: 'Source',
                                    accessor: (row: Rate) => row.source ?? '-',
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row: Rate) =>
                                        canManage && (
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={() => editRate(row)}
                                            >
                                                Edit reference rate
                                            </button>
                                        ),
                                },
                            ]}
                            data={rates}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
