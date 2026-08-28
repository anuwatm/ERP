import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Account = { id: string; code: string; name: string; account_type: string };
type Category = {
    id: string;
    code: string;
    name: string;
    default_useful_life_months: number;
};
type Asset = {
    id: string;
    asset_no: string;
    name: string;
    status: string;
    cost: string;
    accumulated_depreciation: string;
    net_book_value: string;
    available_for_use_date: string;
    last_depreciated_for: string | null;
    category: Category;
    custodian: { name: string } | null;
    location: string | null;
    attachment: { id: string; file_name: string } | null;
};
type Source = {
    id: string;
    expense_no?: string;
    grn_no?: string;
    title?: string;
    amount?: string;
    received_date?: string;
    expense_date?: string;
    items?: { line_total: string; tax_amount: string }[];
};

const money = (value: string | number) =>
    Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function FixedAssets({
    categories,
    assets,
    accounts,
    users,
    expenses,
    goodsReceipts,
    summary,
}: {
    categories: Category[];
    assets: Asset[];
    accounts: Account[];
    users: { id: string; name: string }[];
    expenses: Source[];
    goodsReceipts: Source[];
    summary: {
        cost: string;
        accumulated_depreciation: string;
        net_book_value: string;
    };
}) {
    const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
    const [sourceType, setSourceType] = useState<'expense' | 'goods_receipt'>(
        'expense',
    );
    const [sourceId, setSourceId] = useState('');
    const [disposalDate, setDisposalDate] = useState(
        new Date().toISOString().slice(0, 10),
    );
    const [disposalProceeds, setDisposalProceeds] = useState('0');
    const assetAccounts = useMemo(
        () => accounts.filter((account) => account.account_type === 'asset'),
        [accounts],
    );
    const expenseAccounts = useMemo(
        () => accounts.filter((account) => account.account_type === 'expense'),
        [accounts],
    );
    const sources = sourceType === 'expense' ? expenses : goodsReceipts;

    function createCategory(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post(
            route('fixed-assets.categories.store'),
            Object.fromEntries(new FormData(event.currentTarget)),
        );
    }

    function capitalize(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post(route('fixed-assets.store'), {
            ...Object.fromEntries(new FormData(event.currentTarget)),
            source_type: sourceType,
            source_id: sourceId,
        });
    }

    return (
        <AuthenticatedLayout>
            <Head title="Fixed Assets" />
            <div className="space-y-6">
                <PageHeader
                    title="Fixed Assets"
                    description="Capitalization, straight-line depreciation, and disposal posting."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Card title="Asset Cost">
                        <div className="text-2xl font-semibold">
                            {money(summary.cost)}
                        </div>
                    </Card>
                    <Card title="Accumulated Depreciation">
                        <div className="text-2xl font-semibold">
                            {money(summary.accumulated_depreciation)}
                        </div>
                    </Card>
                    <Card title="Net Book Value">
                        <div className="text-2xl font-semibold">
                            {money(summary.net_book_value)}
                        </div>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card title="Asset Category">
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={createCategory}
                        >
                            <input
                                required
                                name="code"
                                placeholder="Category code"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="name"
                                placeholder="Category name"
                                className="rounded-md border-slate-300"
                            />
                            <select
                                required
                                name="asset_account_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Asset account</option>
                                {assetAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                required
                                name="accumulated_depreciation_account_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">
                                    Accumulated depreciation account
                                </option>
                                {assetAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                required
                                name="depreciation_expense_account_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">
                                    Depreciation expense account
                                </option>
                                {expenseAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                required
                                name="default_useful_life_months"
                                type="number"
                                min="1"
                                placeholder="Useful life (months)"
                                className="rounded-md border-slate-300"
                            />
                            <div className="md:col-span-2">
                                <PrimaryButton>Create Category</PrimaryButton>
                            </div>
                        </form>
                    </Card>

                    <Card title="Capitalize Asset">
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={capitalize}
                        >
                            <select
                                required
                                name="asset_category_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Category</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.code} - {category.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={sourceType}
                                onChange={(event) => {
                                    setSourceType(
                                        event.target.value as
                                            'expense' | 'goods_receipt',
                                    );
                                    setSourceId('');
                                }}
                                className="rounded-md border-slate-300"
                            >
                                <option value="expense">
                                    Approved/Paid Expense
                                </option>
                                <option value="goods_receipt">
                                    Goods Receipt
                                </option>
                            </select>
                            <select
                                required
                                value={sourceId}
                                onChange={(event) =>
                                    setSourceId(event.target.value)
                                }
                                className="rounded-md border-slate-300 md:col-span-2"
                            >
                                <option value="">Capitalization source</option>
                                {sources.map((source) => (
                                    <option key={source.id} value={source.id}>
                                        {sourceType === 'expense'
                                            ? `${source.expense_no} - ${source.title} (${money(source.amount ?? 0)})`
                                            : `${source.grn_no} - ${source.received_date}`}
                                    </option>
                                ))}
                            </select>
                            <input
                                required
                                name="name"
                                placeholder="Asset name"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="available_for_use_date"
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                name="salvage_value"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Salvage value"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                name="useful_life_months"
                                type="number"
                                min="1"
                                placeholder="Override useful life"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                name="location"
                                placeholder="Location"
                                className="rounded-md border-slate-300"
                            />
                            <select
                                name="custodian_user_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Custodian</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <div className="md:col-span-2">
                                <PrimaryButton>
                                    Capitalize and Post
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>

                <Card title="Monthly Depreciation">
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="grid gap-1 text-sm">
                            Month
                            <input
                                value={month}
                                onChange={(event) =>
                                    setMonth(event.target.value)
                                }
                                type="month"
                                className="rounded-md border-slate-300"
                            />
                        </label>
                        <PrimaryButton
                            type="button"
                            onClick={() =>
                                router.post(route('fixed-assets.depreciate'), {
                                    month,
                                })
                            }
                        >
                            Post Depreciation
                        </PrimaryButton>
                    </div>
                </Card>

                <Card title="Asset Register">
                    <div className="mb-4 flex flex-wrap items-end gap-3">
                        <label className="grid gap-1 text-sm">
                            Disposal date
                            <input
                                value={disposalDate}
                                onChange={(event) =>
                                    setDisposalDate(event.target.value)
                                }
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            Disposal proceeds
                            <input
                                value={disposalProceeds}
                                onChange={(event) =>
                                    setDisposalProceeds(event.target.value)
                                }
                                type="number"
                                min="0"
                                step="0.01"
                                className="rounded-md border-slate-300"
                            />
                        </label>
                    </div>
                    <DataTable
                        data={assets}
                        keyExtractor={(asset) => asset.id}
                        columns={[
                            {
                                header: 'Asset',
                                accessor: (asset) => (
                                    <div>
                                        <div className="font-medium">
                                            {asset.asset_no}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {asset.name}
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                header: 'Category',
                                accessor: (asset) => asset.category.name,
                            },
                            {
                                header: 'Cost',
                                accessor: (asset) => money(asset.cost),
                            },
                            {
                                header: 'Accum. Dep.',
                                accessor: (asset) =>
                                    money(asset.accumulated_depreciation),
                            },
                            {
                                header: 'NBV',
                                accessor: (asset) =>
                                    money(asset.net_book_value),
                            },
                            {
                                header: 'Status',
                                accessor: (asset) => asset.status,
                            },
                            {
                                header: 'Custody',
                                accessor: (asset) =>
                                    `${asset.location ?? '-'} / ${asset.custodian?.name ?? '-'}`,
                            },
                            {
                                header: 'Proof',
                                accessor: (asset) => (
                                    <form
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            router.post(
                                                route(
                                                    'fixed-assets.attachment.store',
                                                    asset.id,
                                                ),
                                                new FormData(
                                                    event.currentTarget,
                                                ),
                                                { forceFormData: true },
                                            );
                                        }}
                                        className="flex gap-2"
                                    >
                                        {asset.attachment && (
                                            <a
                                                className="text-sm text-indigo-700"
                                                href={route(
                                                    'files.download',
                                                    asset.attachment.id,
                                                )}
                                            >
                                                {asset.attachment.file_name}
                                            </a>
                                        )}
                                        <input
                                            required
                                            name="attachment"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            className="max-w-36 text-xs"
                                        />
                                        <button className="text-sm text-indigo-700">
                                            Upload
                                        </button>
                                    </form>
                                ),
                            },
                            {
                                header: 'Action',
                                accessor: (asset) =>
                                    asset.status === 'active' && (
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                className="text-sm text-amber-700"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'fixed-assets.dispose',
                                                            asset.id,
                                                        ),
                                                        {
                                                            status: 'disposed',
                                                            disposed_at:
                                                                disposalDate,
                                                            disposal_proceeds:
                                                                disposalProceeds,
                                                            disposal_reason:
                                                                'Disposed',
                                                        },
                                                    )
                                                }
                                            >
                                                Dispose
                                            </button>
                                            <button
                                                type="button"
                                                className="text-sm text-rose-700"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'fixed-assets.dispose',
                                                            asset.id,
                                                        ),
                                                        {
                                                            status: 'written_off',
                                                            disposed_at:
                                                                disposalDate,
                                                            disposal_reason:
                                                                'Written off',
                                                        },
                                                    )
                                                }
                                            >
                                                Write off
                                            </button>
                                        </div>
                                    ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
