import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import type { Deal, DealForm, Customer, Owner } from './Deals';

export default function DealDialog({
    open,
    minimized,
    suspended = false,
    onCreateContact,
    onMinimize,
    onClose,
    form,
    editing,
    customers,
    owners,
    stages,
    canSeeAllSales,
    onSubmit,
}: {
    open: boolean;
    minimized: boolean;
    suspended?: boolean;
    onCreateContact?: () => void;
    onMinimize: (value: boolean) => void;
    onClose: () => void;
    form: InertiaFormProps<DealForm>;
    editing: Deal | null;
    customers: Customer[];
    owners: Owner[];
    stages: string[];
    canSeeAllSales: boolean;
    onSubmit: (event: FormEvent) => void;
}) {
    const reference = (name: 'customer_id' | 'contact_id' | 'owner_id') => ({
        id: `deal-${name}`,
        value: form.data[name],
        error: form.errors[name],
        onChange: (value: string) => {
            form.setData(name, value);
            form.clearErrors(name);
        },
    });
    const contacts =
        customers.find((customer) => customer.id === form.data.customer_id)
            ?.contacts ?? [];
    const field = (name: Exclude<keyof DealForm, 'probability'>) => ({
        id: `deal-${name}`,
        value: form.data[name],
        error: form.errors[name],
        onChange: (value: string) => {
            form.setData(name, value);
            form.clearErrors(name);
        },
    });
    return (
        <CreateEditDialog
            open={open}
            minimized={minimized}
            suspended={suspended}
            onMinimize={onMinimize}
            onClose={onClose}
            entityLabel="Deal"
            storageKey="erp.deal-dialog.size"
            formId="deal-dialog-form"
            saveLabel="Save Deal"
            processing={form.processing}
            errors={form.errors}
            onSubmit={onSubmit}
            title={editing ? 'Edit Deal' : 'Create Deal'}
            breadcrumb={`Deals / ${editing?.title || 'New'}`}
            draftLabel={form.data.title || 'New Deal'}
            status={`${form.data.stage} · ${Number(form.data.value_amount || 0).toLocaleString()} ${form.data.currency} · ${form.isDirty ? 'Unsaved changes' : editing ? 'No changes' : 'New deal'}`}
        >
            {(expanded) => {
                const grid = expanded
                    ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                    : 'grid gap-4 sm:grid-cols-2';
                return (
                    <>
                        <section aria-labelledby="deal-references-heading">
                            <h3
                                id="deal-references-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Customer & Ownership
                            </h3>
                            <div className={grid}>
                                <EditorReference
                                    {...reference('customer_id')}
                                    label="Customer"
                                    required
                                    options={customers.map((customer) => ({
                                        id: customer.id,
                                        label: `${customer.customer_code} · ${customer.company_name}`,
                                    }))}
                                    currentLabel={
                                        editing?.customer?.company_name
                                    }
                                    emptyLabel="Select customer"
                                />
                                <EditorReference
                                    {...reference('contact_id')}
                                    label="Contact"
                                    onCreate={onCreateContact}
                                    options={contacts.map((contact) => ({
                                        id: contact.id,
                                        label: contact.name,
                                    }))}
                                    currentLabel={editing?.contact?.name}
                                />
                                {canSeeAllSales && (
                                    <EditorReference
                                        {...reference('owner_id')}
                                        label="Owner"
                                        options={owners.map((owner) => ({
                                            id: owner.id,
                                            label: `${owner.name} · ${owner.email}`,
                                        }))}
                                        currentLabel={editing?.owner?.name}
                                        emptyLabel={
                                            editing
                                                ? 'Keep current owner'
                                                : 'Assign to me'
                                        }
                                    />
                                )}
                                <EditorField
                                    {...field('source')}
                                    label="Source"
                                    maxLength={100}
                                />
                            </div>
                        </section>
                        <section
                            aria-labelledby="deal-details-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="deal-details-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Opportunity Details
                            </h3>
                            <div className={grid}>
                                <EditorField
                                    {...field('title')}
                                    label="Title"
                                    required
                                    maxLength={255}
                                />
                                <EditorChoice
                                    {...field('stage')}
                                    label="Stage"
                                    required
                                    options={stages}
                                />
                                <EditorField
                                    {...field('value_amount')}
                                    label="Value Amount"
                                    required
                                    type="number"
                                    min={0}
                                    max={999999999999.99}
                                    step="0.01"
                                />
                                <EditorField
                                    {...field('currency')}
                                    label="Currency"
                                    required
                                    maxLength={3}
                                />
                                <EditorField
                                    id="deal-probability"
                                    label="Probability (%)"
                                    required
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="1"
                                    value={String(form.data.probability)}
                                    error={form.errors.probability}
                                    onChange={(value) =>
                                        form.setData(
                                            'probability',
                                            Number(value),
                                        )
                                    }
                                />
                                <EditorField
                                    {...field('expected_close_date')}
                                    label="Expected Close Date"
                                    type="date"
                                />
                            </div>
                        </section>
                        <section
                            aria-labelledby="deal-notes-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="deal-notes-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Notes & Outcome
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {form.data.stage === 'lost' && (
                                    <EditorField
                                        {...field('lost_reason')}
                                        label="Lost Reason"
                                        required
                                        multiline
                                        maxLength={2000}
                                    />
                                )}
                                <EditorField
                                    {...field('note')}
                                    label="Note"
                                    multiline
                                    maxLength={2000}
                                />
                            </div>
                        </section>
                    </>
                );
            }}
        </CreateEditDialog>
    );
}
