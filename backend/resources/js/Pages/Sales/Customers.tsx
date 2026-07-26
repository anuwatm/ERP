import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Owner = { id: string; name: string; email: string };
type Contact = {
    id: string;
    name: string;
    position?: string | null;
    phone?: string | null;
    email?: string | null;
    line_id?: string | null;
    is_primary: boolean;
    note?: string | null;
};
type Customer = {
    id: string;
    customer_code: string;
    company_name: string;
    tax_id?: string | null;
    customer_type: string;
    status: string;
    owner_id?: string | null;
    phone?: string | null;
    email?: string | null;
    line_id?: string | null;
    website?: string | null;
    address?: string | null;
    source?: string | null;
    note?: string | null;
    owner?: Owner | null;
    contacts: Contact[];
    contacts_count: number;
    deals_count: number;
};

type CustomerForm = {
    company_name: string;
    tax_id: string;
    customer_type: string;
    status: string;
    owner_id: string;
    phone: string;
    email: string;
    line_id: string;
    website: string;
    address: string;
    source: string;
    note: string;
};

type ContactForm = {
    customer_id: string;
    name: string;
    position: string;
    phone: string;
    email: string;
    line_id: string;
    is_primary: boolean;
    note: string;
};

const emptyCustomer: CustomerForm = {
    company_name: '',
    tax_id: '',
    customer_type: 'lead',
    status: 'active',
    owner_id: '',
    phone: '',
    email: '',
    line_id: '',
    website: '',
    address: '',
    source: '',
    note: '',
};

const emptyContact: ContactForm = {
    customer_id: '',
    name: '',
    position: '',
    phone: '',
    email: '',
    line_id: '',
    is_primary: false,
    note: '',
};

export default function Customers({
    customers,
    owners,
    canSeeAllSales,
}: {
    customers: Customer[];
    owners: Owner[];
    filters: Record<string, string | null>;
    canSeeAllSales: boolean;
}) {
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(
        null,
    );
    const [editingContact, setEditingContact] = useState<Contact | null>(null);
    const customerForm = useForm<CustomerForm>(emptyCustomer);
    const contactForm = useForm<ContactForm>(emptyContact);

    const submitCustomer = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                customerForm.setData(emptyCustomer);
                setEditingCustomer(null);
            },
        };

        if (editingCustomer) {
            customerForm.patch(
                route('customers.update', editingCustomer.id),
                options,
            );
        } else {
            customerForm.post(route('customers.store'), options);
        }
    };

    const submitContact = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                contactForm.setData(emptyContact);
                setEditingContact(null);
            },
        };

        if (editingContact) {
            contactForm.patch(
                route('contacts.update', editingContact.id),
                options,
            );
        } else if (contactForm.data.customer_id) {
            contactForm.post(
                route('contacts.store', contactForm.data.customer_id),
                options,
            );
        }
    };

    const editCustomer = (customer: Customer) => {
        setEditingCustomer(customer);
        customerForm.setData({
            company_name: customer.company_name ?? '',
            tax_id: customer.tax_id ?? '',
            customer_type: customer.customer_type ?? 'lead',
            status: customer.status ?? 'active',
            owner_id: customer.owner_id ?? '',
            phone: customer.phone ?? '',
            email: customer.email ?? '',
            line_id: customer.line_id ?? '',
            website: customer.website ?? '',
            address: customer.address ?? '',
            source: customer.source ?? '',
            note: customer.note ?? '',
        });
    };

    const editContact = (customer: Customer, contact: Contact) => {
        setEditingContact(contact);
        contactForm.setData({
            customer_id: customer.id,
            name: contact.name ?? '',
            position: contact.position ?? '',
            phone: contact.phone ?? '',
            email: contact.email ?? '',
            line_id: contact.line_id ?? '',
            is_primary: contact.is_primary,
            note: contact.note ?? '',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Customers" />

            <div className="space-y-6">
                <PageHeader
                    title="Customers"
                    description="Customer master data, primary contacts, and owner assignment."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card
                        title="Customer List"
                        description="Active CRM accounts"
                    >
                        <DataTable
                            data={customers}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Customer',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {row.company_name}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500">
                                                {row.customer_code}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Type',
                                    accessor: (row) => (
                                        <Badge variant="info" size="sm">
                                            {row.customer_type}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Owner',
                                    accessor: (row) => row.owner?.name ?? '-',
                                },
                                {
                                    header: 'Primary Contact',
                                    accessor: (row) =>
                                        row.contacts.find(
                                            (item) => item.is_primary,
                                        )?.name ?? '-',
                                },
                                {
                                    header: 'Contacts',
                                    accessor: (row) => row.contacts_count,
                                },
                                {
                                    header: 'Deals',
                                    accessor: (row) => row.deals_count,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    editCustomer(row)
                                                }
                                            >
                                                Edit
                                            </SecondaryButton>
                                            <SecondaryButton
                                                type="button"
                                                onClick={() => {
                                                    contactForm.setData({
                                                        ...emptyContact,
                                                        customer_id: row.id,
                                                    });
                                                    setEditingContact(null);
                                                }}
                                            >
                                                Contact
                                            </SecondaryButton>
                                            {row.deals_count === 0 && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.delete(
                                                            route(
                                                                'customers.destroy',
                                                                row.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <div className="mt-6 space-y-3">
                            {customers.map((customer) => (
                                <div
                                    key={customer.id}
                                    className="rounded-md border border-slate-200 p-3"
                                >
                                    <div className="mb-2 text-sm font-semibold text-slate-800">
                                        {customer.company_name} contacts
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {customer.contacts.length === 0 && (
                                            <span className="text-sm text-slate-500">
                                                No contacts
                                            </span>
                                        )}
                                        {customer.contacts.map((contact) => (
                                            <button
                                                key={contact.id}
                                                type="button"
                                                onClick={() =>
                                                    editContact(
                                                        customer,
                                                        contact,
                                                    )
                                                }
                                                className="rounded-md border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50"
                                            >
                                                <span className="font-semibold text-slate-800">
                                                    {contact.name}
                                                </span>
                                                {contact.is_primary && (
                                                    <span className="ml-2 text-xs text-indigo-600">
                                                        primary
                                                    </span>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <div className="space-y-6">
                        <Card
                            title={
                                editingCustomer
                                    ? 'Edit Customer'
                                    : 'Create Customer'
                            }
                            description="Generate customer code automatically"
                        >
                            <form
                                onSubmit={submitCustomer}
                                className="space-y-3"
                            >
                                <Field
                                    label="Company Name"
                                    value={customerForm.data.company_name}
                                    error={customerForm.errors.company_name}
                                    onChange={(value) =>
                                        customerForm.setData(
                                            'company_name',
                                            value,
                                        )
                                    }
                                    required
                                />
                                <SelectField
                                    label="Customer Type"
                                    value={customerForm.data.customer_type}
                                    onChange={(value) =>
                                        customerForm.setData(
                                            'customer_type',
                                            value,
                                        )
                                    }
                                    options={[
                                        'lead',
                                        'prospect',
                                        'active',
                                        'inactive',
                                    ]}
                                />
                                <SelectField
                                    label="Status"
                                    value={customerForm.data.status}
                                    onChange={(value) =>
                                        customerForm.setData('status', value)
                                    }
                                    options={['active', 'inactive']}
                                />
                                {canSeeAllSales && (
                                    <SelectField
                                        label="Owner"
                                        value={customerForm.data.owner_id}
                                        onChange={(value) =>
                                            customerForm.setData(
                                                'owner_id',
                                                value,
                                            )
                                        }
                                        options={owners.map(
                                            (owner) => owner.id,
                                        )}
                                        labels={Object.fromEntries(
                                            owners.map((owner) => [
                                                owner.id,
                                                owner.name,
                                            ]),
                                        )}
                                    />
                                )}
                                <Field
                                    label="Email"
                                    value={customerForm.data.email}
                                    error={customerForm.errors.email}
                                    onChange={(value) =>
                                        customerForm.setData('email', value)
                                    }
                                />
                                <Field
                                    label="Phone"
                                    value={customerForm.data.phone}
                                    error={customerForm.errors.phone}
                                    onChange={(value) =>
                                        customerForm.setData('phone', value)
                                    }
                                />
                                <Field
                                    label="Source"
                                    value={customerForm.data.source}
                                    error={customerForm.errors.source}
                                    onChange={(value) =>
                                        customerForm.setData('source', value)
                                    }
                                />
                                <div className="flex justify-end gap-2">
                                    {editingCustomer && (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => {
                                                setEditingCustomer(null);
                                                customerForm.setData(
                                                    emptyCustomer,
                                                );
                                            }}
                                        >
                                            Cancel
                                        </SecondaryButton>
                                    )}
                                    <PrimaryButton
                                        disabled={customerForm.processing}
                                        icon={
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                        }
                                    >
                                        Save Customer
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title={
                                editingContact ? 'Edit Contact' : 'Add Contact'
                            }
                            description="Only one primary contact per customer"
                        >
                            <form
                                onSubmit={submitContact}
                                className="space-y-3"
                            >
                                <SelectField
                                    label="Customer"
                                    value={contactForm.data.customer_id}
                                    onChange={(value) =>
                                        contactForm.setData(
                                            'customer_id',
                                            value,
                                        )
                                    }
                                    options={customers.map(
                                        (customer) => customer.id,
                                    )}
                                    labels={Object.fromEntries(
                                        customers.map((customer) => [
                                            customer.id,
                                            customer.company_name,
                                        ]),
                                    )}
                                />
                                <Field
                                    label="Name"
                                    value={contactForm.data.name}
                                    error={contactForm.errors.name}
                                    onChange={(value) =>
                                        contactForm.setData('name', value)
                                    }
                                    required
                                />
                                <Field
                                    label="Position"
                                    value={contactForm.data.position}
                                    error={contactForm.errors.position}
                                    onChange={(value) =>
                                        contactForm.setData('position', value)
                                    }
                                />
                                <Field
                                    label="Email"
                                    value={contactForm.data.email}
                                    error={contactForm.errors.email}
                                    onChange={(value) =>
                                        contactForm.setData('email', value)
                                    }
                                />
                                <Field
                                    label="Phone"
                                    value={contactForm.data.phone}
                                    error={contactForm.errors.phone}
                                    onChange={(value) =>
                                        contactForm.setData('phone', value)
                                    }
                                />
                                <label className="flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={contactForm.data.is_primary}
                                        onChange={(event) =>
                                            contactForm.setData(
                                                'is_primary',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Primary contact
                                </label>
                                <div className="flex justify-end gap-2">
                                    {editingContact && (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => {
                                                setEditingContact(null);
                                                contactForm.setData(
                                                    emptyContact,
                                                );
                                            }}
                                        >
                                            Cancel
                                        </SecondaryButton>
                                    )}
                                    <PrimaryButton
                                        disabled={contactForm.processing}
                                        icon={
                                            <svg
                                                className="h-4 w-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth="2"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                        }
                                    >
                                        Save Contact
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    error,
    onChange,
    required = false,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    required?: boolean;
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');

    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700"
            />
            <TextInput
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                className="block w-full text-sm"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function SelectField({
    label,
    value,
    options,
    labels = {},
    onChange,
}: {
    label: string;
    value: string;
    options: string[];
    labels?: Record<string, string>;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <InputLabel
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700"
            />
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150"
            >
                <option value="">Select</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {labels[option] ?? option}
                    </option>
                ))}
            </select>
        </div>
    );
}
