import FixedAssetAttachmentDialog from './FixedAssetAttachmentDialog';
import FixedAssetCreateDialog from './FixedAssetCreateDialog';
import { PageProps } from '@/Types';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

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
type BankAccount = {
    id: string;
    bank_name: string;
    account_name: string;
    currency: string;
    chart_of_account_id: string | null;
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
    bankAccounts,
    summary,
}: {
    categories: Category[];
    assets: Asset[];
    accounts: Account[];
    users: { id: string; name: string }[];
    expenses: Source[];
    goodsReceipts: Source[];
    bankAccounts: BankAccount[];
    summary: {
        cost: string;
        accumulated_depreciation: string;
        net_book_value: string;
    };
}) {
    const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
    const [editor, setEditor] = useState<'category' | 'asset' | null>(null);
    const [proofAsset, setProofAsset] = useState<Asset | null>(null);
    const editorOpen = editor !== null || proofAsset !== null;
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('fixed_assets.manage');
    const [disposalDate, setDisposalDate] = useState(
        new Date().toISOString().slice(0, 10),
    );
    const [disposalProceeds, setDisposalProceeds] = useState('0');
    const [disposalBankAccountId, setDisposalBankAccountId] = useState('');

    return (
        <AuthenticatedLayout>
            <Head title="Fixed Assets" />
            <div className="space-y-6">
                <PageHeader
                    title="Fixed Assets"
                    actions={
                        canManage && (
                            <div className="flex gap-2">
                                <PrimaryButton
                                    disabled={editorOpen}
                                    onClick={() => setEditor('category')}
                                >
                                    + Asset Category
                                </PrimaryButton>
                                <PrimaryButton
                                    disabled={editorOpen}
                                    onClick={() => setEditor('asset')}
                                >
                                    + Capitalize Asset
                                </PrimaryButton>
                            </div>
                        )
                    }
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

                {proofAsset && (
                    <FixedAssetAttachmentDialog
                        key={proofAsset.id}
                        asset={proofAsset}
                        onClose={() => setProofAsset(null)}
                    />
                )}

                <FixedAssetCreateDialog
                    mode={editor}
                    onClose={() => setEditor(null)}
                    accounts={accounts}
                    categories={categories}
                    users={users}
                    expenses={expenses}
                    goodsReceipts={goodsReceipts}
                />

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
                            disabled={
                                editorOpen ||
                                !auth.permissions.includes(
                                    'fixed_assets.depreciate',
                                )
                            }
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
                        <label className="grid gap-1 text-sm">
                            Proceeds bank / GL account
                            <select
                                value={disposalBankAccountId}
                                onChange={(event) =>
                                    setDisposalBankAccountId(event.target.value)
                                }
                                className="rounded-md border-slate-300"
                            >
                                <option value="">
                                    Select mapped bank account
                                </option>
                                {bankAccounts
                                    .filter(
                                        (account) =>
                                            account.chart_of_account_id,
                                    )
                                    .map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.bank_name} -{' '}
                                            {account.account_name} (
                                            {account.currency})
                                        </option>
                                    ))}
                            </select>
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
                                    <div className="flex flex-wrap items-center gap-2">
                                        {asset.attachment && (
                                            <a
                                                className="text-sm text-indigo-700 dark:text-indigo-300"
                                                href={route(
                                                    'files.download',
                                                    asset.attachment.id,
                                                )}
                                            >
                                                {asset.attachment.file_name}
                                            </a>
                                        )}
                                        {canManage && (
                                            <button
                                                type="button"
                                                disabled={editorOpen}
                                                onClick={() =>
                                                    setProofAsset(asset)
                                                }
                                                className="min-h-10 text-sm text-indigo-700 disabled:opacity-50 dark:text-indigo-300"
                                            >
                                                {asset.attachment
                                                    ? 'Replace Proof'
                                                    : 'Upload Proof'}
                                            </button>
                                        )}
                                    </div>
                                ),
                            },
                            {
                                header: 'Action',
                                accessor: (asset) =>
                                    auth.permissions.includes(
                                        'fixed_assets.dispose',
                                    ) &&
                                    asset.status === 'active' && (
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                disabled={editorOpen}
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
                                                            bank_account_id:
                                                                disposalBankAccountId ||
                                                                null,
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
                                                disabled={editorOpen}
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
