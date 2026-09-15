import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Reference = { id: string; name: string; code?: string };
type Source = {
    id: string;
    expense_no?: string;
    amount?: string;
    title?: string;
    grn_no?: string;
    received_date?: string;
};
type Props = {
    mode: 'category' | 'asset' | null;
    onClose: () => void;
    accounts: (Reference & { account_type: string })[];
    categories: Reference[];
    users: Reference[];
    expenses: Source[];
    goodsReceipts: Source[];
};
const categoryDefaults = {
    code: '',
    name: '',
    asset_account_id: '',
    accumulated_depreciation_account_id: '',
    depreciation_expense_account_id: '',
    default_useful_life_months: '',
};
const assetDefaults = {
    asset_category_id: '',
    source_type: 'expense',
    source_id: '',
    name: '',
    available_for_use_date: '',
    salvage_value: '',
    useful_life_months: '',
    location: '',
    custodian_user_id: '',
};

export default function FixedAssetCreateDialog(props: Props) {
    // Mount one fresh draft per opening; minimizing keeps the same instance alive.
    return props.mode ? (
        <AssetEditor key={props.mode} {...props} mode={props.mode} />
    ) : null;
}
function AssetEditor({
    mode,
    onClose,
    accounts,
    categories,
    users,
    expenses,
    goodsReceipts,
}: Props & { mode: 'category' | 'asset' }) {
    const category = useForm(categoryDefaults);
    const asset = useForm(assetDefaults);
    const isCategory = mode === 'category';
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('fixed_assets.manage');
    const processing = category.processing || asset.processing;
    const guard = useDiscardGuard(
        isCategory ? category.isDirty : asset.isDirty,
        processing,
    );
    const submission = useSubmissionKey();
    const errors = isCategory ? category.errors : asset.errors;
    const references = (items: Reference[]) =>
        items.map((item) => ({
            id: item.id,
            label: item.code ? `${item.code} - ${item.name}` : item.name,
        }));
    const accountOptions = (type: string) =>
        references(accounts.filter((account) => account.account_type === type));
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage || processing) return;
        const options = {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: onClose,
        };
        if (isCategory)
            category.post(route('fixed-assets.categories.store'), options);
        else asset.post(route('fixed-assets.store'), options);
    }
    const categoryField = (
        key: 'code' | 'name' | 'default_useful_life_months',
        label: string,
        number = false,
    ) => (
        <EditorField
            id={`asset-category-${key}`}
            label={label}
            value={category.data[key]}
            error={category.errors[key]}
            onChange={(value) => category.setData(key, value)}
            required
            type={number ? 'number' : 'text'}
            min={number ? 1 : undefined}
            max={number ? 1200 : undefined}
            step={number ? 1 : undefined}
        />
    );
    const assetField = (
        key:
            | 'name'
            | 'available_for_use_date'
            | 'salvage_value'
            | 'useful_life_months'
            | 'location',
        label: string,
        type = 'text',
        required = false,
    ) => (
        <EditorField
            id={`asset-${key}`}
            label={label}
            value={asset.data[key]}
            error={asset.errors[key]}
            onChange={(value) => asset.setData(key, value)}
            type={type}
            required={required}
            min={
                type === 'number'
                    ? key === 'salvage_value'
                        ? 0
                        : 1
                    : undefined
            }
            step={
                key === 'salvage_value'
                    ? '0.01'
                    : type === 'number'
                      ? 1
                      : undefined
            }
        />
    );
    const sources =
        asset.data.source_type === 'expense' ? expenses : goodsReceipts;
    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={
                    isCategory ? 'Create Asset Category' : 'Capitalize Asset'
                }
                breadcrumb="Finance / Fixed Assets"
                entityLabel={isCategory ? 'Asset category' : 'Fixed asset'}
                draftLabel={
                    (isCategory ? category.data.name : asset.data.name) ||
                    'New asset draft'
                }
                storageKey="fixed-asset-editor-size"
                formId="fixed-asset-editor"
                processing={processing}
                saveDisabled={!canManage}
                saveLabel={
                    isCategory ? 'Create Category' : 'Capitalize and Post'
                }
                status={
                    isCategory
                        ? 'Configure ledger accounts and useful life'
                        : 'Capitalizing creates an asset and posts to the general ledger'
                }
                errors={errors}
                onSubmit={submit}
            >
                {(expanded) => (
                    <section
                        aria-label={
                            isCategory
                                ? 'Category details'
                                : 'Capitalization details'
                        }
                        className={`grid gap-4 p-4 sm:grid-cols-2 sm:p-6 ${expanded ? 'lg:grid-cols-3' : ''}`}
                    >
                        {isCategory ? (
                            <>
                                {categoryField('code', 'Category Code')}
                                {categoryField('name', 'Category Name')}
                                <EditorReference
                                    id="category-asset-account"
                                    label="Asset Account"
                                    value={category.data.asset_account_id}
                                    error={category.errors.asset_account_id}
                                    onChange={(value) =>
                                        category.setData(
                                            'asset_account_id',
                                            value,
                                        )
                                    }
                                    options={accountOptions('asset')}
                                    required
                                />
                                <EditorReference
                                    id="category-accumulated-account"
                                    label="Accumulated Depreciation Account"
                                    value={
                                        category.data
                                            .accumulated_depreciation_account_id
                                    }
                                    error={
                                        category.errors
                                            .accumulated_depreciation_account_id
                                    }
                                    onChange={(value) =>
                                        category.setData(
                                            'accumulated_depreciation_account_id',
                                            value,
                                        )
                                    }
                                    options={accountOptions('asset')}
                                    required
                                />
                                <EditorReference
                                    id="category-expense-account"
                                    label="Depreciation Expense Account"
                                    value={
                                        category.data
                                            .depreciation_expense_account_id
                                    }
                                    error={
                                        category.errors
                                            .depreciation_expense_account_id
                                    }
                                    onChange={(value) =>
                                        category.setData(
                                            'depreciation_expense_account_id',
                                            value,
                                        )
                                    }
                                    options={accountOptions('expense')}
                                    required
                                />
                                {categoryField(
                                    'default_useful_life_months',
                                    'Useful Life (months)',
                                    true,
                                )}
                            </>
                        ) : (
                            <>
                                <EditorReference
                                    id="asset-category"
                                    label="Category"
                                    value={asset.data.asset_category_id}
                                    error={asset.errors.asset_category_id}
                                    onChange={(value) =>
                                        asset.setData(
                                            'asset_category_id',
                                            value,
                                        )
                                    }
                                    options={references(categories)}
                                    required
                                />
                                <EditorChoice
                                    id="asset-source-type"
                                    label="Source Type"
                                    value={asset.data.source_type}
                                    error={asset.errors.source_type}
                                    onChange={(value) =>
                                        asset.setData((data) => ({
                                            ...data,
                                            source_type: value,
                                            source_id: '',
                                        }))
                                    }
                                    options={['expense', 'goods_receipt']}
                                    required
                                />
                                <EditorReference
                                    id="asset-source"
                                    label="Capitalization Source"
                                    value={asset.data.source_id}
                                    error={asset.errors.source_id}
                                    onChange={(value) =>
                                        asset.setData('source_id', value)
                                    }
                                    options={sources.map((source) => ({
                                        id: source.id,
                                        label:
                                            asset.data.source_type === 'expense'
                                                ? `${source.expense_no} - ${source.title} (${Number(source.amount ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`
                                                : `${source.grn_no} - ${source.received_date}`,
                                    }))}
                                    required
                                />
                                {assetField('name', 'Asset Name', 'text', true)}
                                {assetField(
                                    'available_for_use_date',
                                    'Available for Use Date',
                                    'date',
                                    true,
                                )}
                                {assetField(
                                    'salvage_value',
                                    'Salvage Value',
                                    'number',
                                )}
                                {assetField(
                                    'useful_life_months',
                                    'Override Useful Life (months)',
                                    'number',
                                )}
                                {assetField('location', 'Location')}
                                <EditorReference
                                    id="asset-custodian"
                                    label="Custodian"
                                    value={asset.data.custodian_user_id}
                                    error={asset.errors.custodian_user_id}
                                    onChange={(value) =>
                                        asset.setData(
                                            'custodian_user_id',
                                            value,
                                        )
                                    }
                                    options={references(users)}
                                />
                            </>
                        )}
                    </section>
                )}
            </CreateEditDialog>
        </>
    );
}
