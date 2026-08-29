import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
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
    const submitCurrency = (event: FormEvent) => {
        event.preventDefault();
        currency.post(route('currencies.store'), {
            preserveScroll: true,
            onSuccess: () => currency.reset(),
        });
    };
    const submitRate = (event: FormEvent) => {
        event.preventDefault();
        rate.post(route('exchange-rates.store'), {
            preserveScroll: true,
            onSuccess: () => rate.reset('quote_currency', 'rate'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Currencies & FX" />
            <div className="space-y-6">
                <PageHeader
                    title="Currencies & FX"
                    description={`Base currency: ${baseCurrency}. Rates are snapshotted on documents and payments.`}
                />
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <form
                            onSubmit={submitCurrency}
                            className="space-y-3 p-5"
                        >
                            <h2 className="text-base font-semibold">
                                Currency
                            </h2>
                            <div className="grid grid-cols-2 gap-3">
                                <TextInput
                                    value={currency.data.code}
                                    onChange={(e) =>
                                        currency.setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="USD"
                                    maxLength={3}
                                    required
                                />
                                <TextInput
                                    value={currency.data.name}
                                    onChange={(e) =>
                                        currency.setData('name', e.target.value)
                                    }
                                    placeholder="US Dollar"
                                    required
                                />
                            </div>
                            <PrimaryButton disabled={currency.processing}>
                                Save currency
                            </PrimaryButton>
                        </form>
                    </Card>
                    <Card>
                        <form onSubmit={submitRate} className="space-y-3 p-5">
                            <h2 className="text-base font-semibold">
                                Exchange rate
                            </h2>
                            <div className="grid grid-cols-3 gap-3">
                                <TextInput
                                    value={rate.data.quote_currency}
                                    onChange={(e) =>
                                        rate.setData(
                                            'quote_currency',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="USD"
                                    maxLength={3}
                                    required
                                />
                                <TextInput
                                    type="date"
                                    value={rate.data.rate_date}
                                    onChange={(e) =>
                                        rate.setData(
                                            'rate_date',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <TextInput
                                    type="number"
                                    step="0.000001"
                                    value={rate.data.rate}
                                    onChange={(e) =>
                                        rate.setData('rate', e.target.value)
                                    }
                                    placeholder="36.500000"
                                    required
                                />
                            </div>
                            <PrimaryButton disabled={rate.processing}>
                                Save rate
                            </PrimaryButton>
                        </form>
                    </Card>
                </div>
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
                            ]}
                            data={rates}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
