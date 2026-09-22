import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
} from '@headlessui/react';
import { type InertiaFormProps } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import type { CustomerForm } from './Customers';
type Owner = { id: string; name: string; email: string };
const control =
    'w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
export default function CustomerDialog({
    open,
    minimized,
    onMinimize,
    onClose,
    editing,
    code,
    form,
    owners,
    canSeeAllSales,
    onSubmit,
}: {
    open: boolean;
    minimized: boolean;
    onMinimize: (value: boolean) => void;
    onClose: () => void;
    editing: boolean;
    code?: string;
    form: InertiaFormProps<CustomerForm>;
    owners: Owner[];
    canSeeAllSales: boolean;
    onSubmit: (event: FormEvent) => void;
}) {
    const [ownerQuery, setOwnerQuery] = useState('');
    const filteredOwners = owners.filter((owner) =>
        `${owner.name} ${owner.email}`
            .toLowerCase()
            .includes(ownerQuery.toLowerCase()),
    );
    const sectionTitle = 'mb-4 text-base font-semibold';
    const error = (name: keyof CustomerForm) =>
        form.errors[name] ? (
            <p
                id={`customer-${name}-error`}
                className="mt-1 text-sm text-red-700 dark:text-red-300"
            >
                {form.errors[name]}
            </p>
        ) : null;
    const field = (
        name: keyof CustomerForm,
        label: string,
        maxLength: number,
        type = 'text',
        multiline = false,
    ) => {
        const attributes = {
            id: `customer-${name}`,
            name,
            value: form.data[name],
            maxLength,
            required: name === 'company_name',
            'aria-invalid': !!form.errors[name],
            'aria-describedby': form.errors[name]
                ? `customer-${name}-error`
                : undefined,
            className: control,
            onChange: (
                event: React.ChangeEvent<
                    HTMLInputElement | HTMLTextAreaElement
                >,
            ) => form.setData(name, event.target.value),
        };
        return (
            <div className="min-w-0" key={name}>
                <label
                    htmlFor={attributes.id}
                    className="mb-1.5 block text-sm font-medium"
                >
                    {label}
                    {attributes.required && (
                        <span className="text-red-600"> *</span>
                    )}
                </label>
                {multiline ? (
                    <textarea {...attributes} rows={3} />
                ) : (
                    <input {...attributes} type={type} />
                )}
                {error(name)}
            </div>
        );
    };
    const select = (
        name: 'customer_type' | 'status',
        label: string,
        options: string[],
    ) => (
        <div>
            <label
                htmlFor={`customer-${name}`}
                className="mb-1.5 block text-sm font-medium"
            >
                {label}
            </label>
            <select
                id={`customer-${name}`}
                name={name}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
                className={control}
                required
                aria-invalid={!!form.errors[name]}
                aria-describedby={
                    form.errors[name] ? `customer-${name}-error` : undefined
                }
            >
                {options.map((value) => (
                    <option key={value} value={value}>
                        {value.charAt(0).toUpperCase() + value.slice(1)}
                    </option>
                ))}
            </select>
            {error(name)}
        </div>
    );

    return (
        <CreateEditDialog
            open={open}
            minimized={minimized}
            onMinimize={onMinimize}
            onClose={onClose}
            entityLabel="Customer"
            storageKey="erp.customer-dialog.size"
            formId="customer-dialog-form"
            saveLabel="Save Customer"
            processing={form.processing}
            errors={form.errors}
            onSubmit={onSubmit}
            title={editing ? 'Edit Customer' : 'Create Customer'}
            breadcrumb={`Customers / ${code || 'New'}`}
            draftLabel={form.data.company_name || 'New Customer'}
            status={
                <>
                    {form.data.status === 'active' ? 'Active' : 'Inactive'} ·{' '}
                    {form.isDirty
                        ? 'Unsaved changes'
                        : editing
                          ? 'No changes'
                          : 'New customer'}
                </>
            }
        >
            {(expanded) => {
                const grid = expanded
                    ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                    : 'grid gap-4 sm:grid-cols-2';
                return (
                    <>
                        {!canSeeAllSales && error('owner_id')}
                        <section aria-labelledby="customer-details-heading">
                            <h3
                                id="customer-details-heading"
                                className={sectionTitle}
                            >
                                Customer Details
                            </h3>
                            <div className={grid}>
                                {field('company_name', 'Company Name', 255)}
                                {field('tax_id', 'Tax ID', 50)}
                                {select('customer_type', 'Customer Type', [
                                    'lead',
                                    'prospect',
                                    'active',
                                    'inactive',
                                ])}
                                {select('status', 'Status', [
                                    'active',
                                    'inactive',
                                ])}
                            </div>
                        </section>
                        <section
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                            aria-labelledby="customer-ownership-heading"
                        >
                            <h3
                                id="customer-ownership-heading"
                                className={sectionTitle}
                            >
                                Ownership & Source
                            </h3>
                            <div className={grid}>
                                {canSeeAllSales && (
                                    <div className="relative min-w-0">
                                        <label
                                            htmlFor="customer-owner_id"
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Owner
                                        </label>
                                        <Combobox
                                            value={form.data.owner_id}
                                            onChange={(value: string | null) =>
                                                form.setData(
                                                    'owner_id',
                                                    value ?? '',
                                                )
                                            }
                                            onClose={() => setOwnerQuery('')}
                                        >
                                            <div className="relative">
                                                <ComboboxInput
                                                    id="customer-owner_id"
                                                    className={`${control} pr-10`}
                                                    displayValue={(
                                                        id: string,
                                                    ) =>
                                                        owners.find(
                                                            (owner) =>
                                                                owner.id === id,
                                                        )?.name ??
                                                        (id
                                                            ? 'Current owner'
                                                            : '')
                                                    }
                                                    onChange={(event) =>
                                                        setOwnerQuery(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder={
                                                        editing
                                                            ? 'Keep current owner'
                                                            : 'Assign to me'
                                                    }
                                                    aria-invalid={
                                                        !!form.errors.owner_id
                                                    }
                                                    aria-describedby={
                                                        form.errors.owner_id
                                                            ? 'customer-owner_id-error'
                                                            : undefined
                                                    }
                                                />
                                                <ComboboxButton
                                                    className="absolute inset-y-0 right-0 w-10 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                                    aria-label="Choose owner"
                                                >
                                                    ⌄
                                                </ComboboxButton>
                                            </div>
                                            <ComboboxOptions className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border border-slate-300 bg-white p-1 shadow-lg empty:invisible dark:border-slate-700 dark:bg-slate-800">
                                                <ComboboxOption
                                                    value=""
                                                    className="cursor-pointer rounded px-3 py-2 text-sm text-slate-900 dark:text-slate-100 data-[focus]:bg-indigo-100 dark:data-[focus]:bg-slate-700"
                                                >
                                                    {editing
                                                        ? 'Keep current owner'
                                                        : 'Assign to me'}
                                                </ComboboxOption>
                                                {filteredOwners.map((owner) => (
                                                    <ComboboxOption
                                                        key={owner.id}
                                                        value={owner.id}
                                                        className="cursor-pointer break-words rounded px-3 py-2 text-sm text-slate-900 dark:text-slate-100 data-[focus]:bg-indigo-100 data-[selected]:font-semibold dark:data-[focus]:bg-slate-700"
                                                    >
                                                        {owner.name}
                                                        <span className="block text-xs text-slate-500 dark:text-slate-400">
                                                            {owner.email}
                                                        </span>
                                                    </ComboboxOption>
                                                ))}
                                                {!filteredOwners.length && (
                                                    <div className="px-3 py-2 text-sm text-slate-500 dark:text-slate-400">
                                                        No matching owners
                                                    </div>
                                                )}
                                            </ComboboxOptions>
                                        </Combobox>
                                        {error('owner_id')}
                                    </div>
                                )}
                                {field('source', 'Source', 100)}
                            </div>
                        </section>
                        <section
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                            aria-labelledby="customer-contact-heading"
                        >
                            <h3
                                id="customer-contact-heading"
                                className={sectionTitle}
                            >
                                Contact Information
                            </h3>
                            <div className={grid}>
                                {field('email', 'Email', 255, 'email')}
                                {field('phone', 'Phone', 50, 'tel')}
                                {field('line_id', 'LINE ID', 100)}
                                {field('website', 'Website', 255)}
                            </div>
                        </section>
                        <section
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                            aria-labelledby="customer-notes-heading"
                        >
                            <h3
                                id="customer-notes-heading"
                                className={sectionTitle}
                            >
                                Address & Notes
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {field(
                                    'address',
                                    'Address',
                                    2000,
                                    'text',
                                    true,
                                )}
                                {field('note', 'Note', 2000, 'text', true)}
                            </div>
                        </section>
                    </>
                );
            }}
        </CreateEditDialog>
    );
}
