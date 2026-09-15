import { type InertiaFormProps } from '@inertiajs/react';
import { type FormEvent } from 'react';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import type { ContactForm } from './Customers';
const control =
    'block w-full min-w-0 rounded-md border-gray-300 bg-white text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white';
export default function ContactDialog({
    open,
    minimized,
    onMinimize,
    onClose,
    editing,
    customer,
    form,
    onSubmit,
}: {
    open: boolean;
    minimized: boolean;
    onMinimize: (value: boolean) => void;
    onClose: () => void;
    editing: boolean;
    customer: string;
    form: InertiaFormProps<ContactForm>;
    onSubmit: (event: FormEvent) => void;
}) {
    const error = (name: keyof ContactForm) =>
        form.errors[name] && (
            <p
                id={`contact-${name}-error`}
                className="mt-1 text-sm text-red-700 dark:text-red-300"
            >
                {form.errors[name]}
            </p>
        );
    const field = (
        name: 'name' | 'position' | 'phone' | 'email' | 'line_id' | 'note',
        label: string,
        maxLength: number,
        type = 'text',
    ) => {
        const attributes = {
            id: `contact-${name}`,
            name,
            value: form.data[name],
            maxLength,
            required: name === 'name',
            className: control,
            'aria-invalid': !!form.errors[name],
            'aria-describedby': form.errors[name]
                ? `contact-${name}-error`
                : undefined,
            onChange: (
                event: React.ChangeEvent<
                    HTMLInputElement | HTMLTextAreaElement
                >,
            ) => form.setData(name, event.target.value),
        };
        return (
            <div className="min-w-0">
                <label
                    htmlFor={attributes.id}
                    className="mb-1.5 block text-sm font-medium"
                >
                    {label}
                    {name === 'name' && (
                        <span className="text-red-600"> *</span>
                    )}
                </label>
                {name === 'note' ? (
                    <textarea {...attributes} rows={4} />
                ) : (
                    <input {...attributes} type={type} />
                )}
                {error(name)}
            </div>
        );
    };

    return (
        <CreateEditDialog
            open={open}
            minimized={minimized}
            onMinimize={onMinimize}
            onClose={onClose}
            entityLabel="Contact"
            storageKey="erp.contact-dialog.size"
            formId="contact-dialog-form"
            saveLabel="Save Contact"
            processing={form.processing}
            errors={form.errors}
            onSubmit={onSubmit}
            title={editing ? 'Edit Contact' : 'Add Contact'}
            breadcrumb="Customers / Contacts"
            draftLabel={form.data.name || customer}
            saveDisabled={!form.data.customer_id}
            status={
                <>
                    {form.data.is_primary ? 'Primary contact' : 'Contact'} ·{' '}
                    {form.isDirty
                        ? 'Unsaved changes'
                        : editing
                          ? 'No changes'
                          : 'New contact'}
                </>
            }
        >
            {(expanded) => {
                const grid = expanded
                    ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                    : 'grid gap-4 sm:grid-cols-2';
                return (
                    <>
                        {error('customer_id')}
                        <section aria-labelledby="contact-customer-heading">
                            <h3
                                id="contact-customer-heading"
                                className="mb-3 text-base font-semibold"
                            >
                                Customer
                            </h3>
                            <p className="break-words text-sm">{customer}</p>
                        </section>
                        <section
                            aria-labelledby="contact-person-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="contact-person-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Contact Details
                            </h3>
                            <div className={grid}>
                                {field('name', 'Name', 255)}
                                {field('position', 'Position', 100)}
                            </div>
                            <label className="mt-4 flex items-center gap-2 text-sm">
                                <input
                                    id="contact-is_primary"
                                    type="checkbox"
                                    checked={form.data.is_primary}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_primary',
                                            event.target.checked,
                                        )
                                    }
                                    className="rounded border-gray-400 text-indigo-600 focus:ring-indigo-500"
                                    aria-invalid={!!form.errors.is_primary}
                                    aria-describedby={
                                        form.errors.is_primary
                                            ? 'contact-is_primary-error'
                                            : undefined
                                    }
                                />
                                Primary contact
                            </label>
                            {error('is_primary')}
                        </section>
                        <section
                            aria-labelledby="contact-channels-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="contact-channels-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Contact Information
                            </h3>
                            <div className={grid}>
                                {field('email', 'Email', 255, 'email')}
                                {field('phone', 'Phone', 50, 'tel')}
                                {field('line_id', 'LINE ID', 100)}
                            </div>
                        </section>
                        <section
                            aria-labelledby="contact-notes-heading"
                            className="border-t border-gray-200 pt-6 dark:border-gray-700"
                        >
                            <h3
                                id="contact-notes-heading"
                                className="mb-4 text-base font-semibold"
                            >
                                Notes
                            </h3>
                            {field('note', 'Note', 2000)}
                        </section>
                    </>
                );
            }}
        </CreateEditDialog>
    );
}
