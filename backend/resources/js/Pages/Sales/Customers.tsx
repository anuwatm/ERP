import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CustomerDialog from './CustomerDialog';
import ContactDialog from './ContactDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@/Types/auth';
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

export type CustomerForm = {
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

export type ContactForm = {
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
    const { auth } = usePage<PageProps>().props;
    const canCreateCustomer = auth.permissions.includes('customers.create');
    const canUpdateCustomer = auth.permissions.includes('customers.update');
    const canDeleteCustomer = auth.permissions.includes('customers.delete');
    const canCreateContact = auth.permissions.includes('contacts.create');
    const canUpdateContact = auth.permissions.includes('contacts.update');
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(
        null,
    );
    const [editingContact, setEditingContact] = useState<Contact | null>(null);
    const customerForm = useForm<CustomerForm>(emptyCustomer);
    const customerSubmission = useSubmissionKey();
    const contactSubmission = useSubmissionKey();
    const [customerDialogOpen, setCustomerDialogOpen] = useState(false);
    const [customerMinimized, setCustomerMinimized] = useState(false);
    const contactForm = useForm<ContactForm>(emptyContact);
    const [contactDialogOpen, setContactDialogOpen] = useState(false);
    const [contactMinimized, setContactMinimized] = useState(false);
    const guard = useDiscardGuard(
        (customerDialogOpen && customerForm.isDirty) ||
            (contactDialogOpen && contactForm.isDirty),
        customerForm.processing || contactForm.processing,
    );

    const submitCustomer = (event: FormEvent) => {
        event.preventDefault();
        if (
            customerForm.processing ||
            (editingCustomer ? !canUpdateCustomer : !canCreateCustomer)
        )
            return;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                customerForm.setDefaults(emptyCustomer);
                customerForm.setData(emptyCustomer);
                setEditingCustomer(null);
                setCustomerDialogOpen(false);
                setCustomerMinimized(false);
            },
        };

        if (editingCustomer) {
            customerForm.patch(
                route('customers.update', editingCustomer.id),
                options,
            );
        } else {
            customerForm.post(route('customers.store'), {
                ...options,
                headers: customerSubmission.headers(),
            });
        }
    };

    const submitContact = (event: FormEvent) => {
        event.preventDefault();
        if (
            contactForm.processing ||
            (editingContact ? !canUpdateContact : !canCreateContact)
        )
            return;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                contactForm.setDefaults(emptyContact);
                contactForm.setData(emptyContact);
                setEditingContact(null);
                setContactDialogOpen(false);
                setContactMinimized(false);
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
                { ...options, headers: contactSubmission.headers() },
            );
        }
    };

    const closeCustomer = () => {
        setCustomerDialogOpen(false);
        setCustomerMinimized(false);
        setEditingCustomer(null);
        customerForm.setDefaults(emptyCustomer);
        customerForm.setData(emptyCustomer);
        customerForm.clearErrors();
    };
    const closeContact = () => {
        setContactDialogOpen(false);
        setContactMinimized(false);
        setEditingContact(null);
        contactForm.setDefaults(emptyContact);
        contactForm.setData(emptyContact);
        contactForm.clearErrors();
    };
    const startCustomer = (customer: Customer | null) => {
        customerSubmission.reset();
        closeContact();
        setEditingCustomer(customer);
        const data: CustomerForm = customer
            ? {
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
              }
            : { ...emptyCustomer };
        customerForm.setDefaults(data);
        customerForm.setData(data);
        customerForm.clearErrors();
        setCustomerDialogOpen(true);
        setCustomerMinimized(false);
    };
    const editCustomer = (customer: Customer) => {
        if (
            !canUpdateCustomer ||
            customerForm.processing ||
            contactForm.processing
        )
            return;
        if (customerDialogOpen && editingCustomer?.id === customer.id) {
            setCustomerMinimized(false);
            return;
        }
        guard.request(() => startCustomer(customer));
    };
    const createCustomer = () => {
        if (
            !canCreateCustomer ||
            customerForm.processing ||
            contactForm.processing
        )
            return;
        if (customerDialogOpen && !editingCustomer) {
            setCustomerMinimized(false);
            return;
        }
        guard.request(() => startCustomer(null));
    };
    const editContact = (customer: Customer, contact?: Contact) => {
        if (
            (contact ? !canUpdateContact : !canCreateContact) ||
            customerForm.processing ||
            contactForm.processing
        )
            return;
        if (
            contactDialogOpen &&
            editingContact?.id === contact?.id &&
            contactForm.data.customer_id === customer.id
        ) {
            setContactMinimized(false);
            return;
        }
        guard.request(() => {
            closeCustomer();
            setEditingContact(contact ?? null);
            const data = {
                customer_id: customer.id,
                name: contact?.name ?? '',
                position: contact?.position ?? '',
                phone: contact?.phone ?? '',
                email: contact?.email ?? '',
                line_id: contact?.line_id ?? '',
                is_primary: contact?.is_primary ?? false,
                note: contact?.note ?? '',
            };
            contactSubmission.reset();
            contactForm.setDefaults(data);
            contactForm.setData(data);
            contactForm.clearErrors();
            setContactDialogOpen(true);
            setContactMinimized(false);
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Customers" />
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />

            <div className="space-y-6">
                <PageHeader
                    title="Customers"
                    description="Customer master data, primary contacts, and owner assignment."
                    actions={
                        canCreateCustomer && (
                            <PrimaryButton
                                type="button"
                                onClick={createCustomer}
                                disabled={
                                    customerForm.processing ||
                                    contactForm.processing
                                }
                            >
                                + Create Customer
                            </PrimaryButton>
                        )
                    }
                />

                <CustomerDialog
                    open={customerDialogOpen}
                    minimized={customerMinimized}
                    onMinimize={setCustomerMinimized}
                    onClose={() => guard.request(closeCustomer)}
                    editing={!!editingCustomer}
                    code={editingCustomer?.customer_code}
                    form={customerForm}
                    owners={owners}
                    canSeeAllSales={canSeeAllSales}
                    onSubmit={submitCustomer}
                />

                <ContactDialog
                    open={contactDialogOpen}
                    minimized={contactMinimized}
                    onMinimize={setContactMinimized}
                    onClose={() => guard.request(closeContact)}
                    editing={!!editingContact}
                    customer={
                        customers.find(
                            (customer) =>
                                customer.id === contactForm.data.customer_id,
                        )?.company_name ?? ''
                    }
                    form={contactForm}
                    onSubmit={submitContact}
                />

                <div>
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
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.company_name}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500 dark:text-slate-400">
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
                                            {canUpdateCustomer && (
                                                <SecondaryButton
                                                    type="button"
                                                    disabled={
                                                        customerForm.processing ||
                                                        contactForm.processing
                                                    }
                                                    onClick={() =>
                                                        editCustomer(row)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                            )}
                                            {canCreateContact && (
                                                <SecondaryButton
                                                    type="button"
                                                    disabled={
                                                        customerForm.processing ||
                                                        contactForm.processing
                                                    }
                                                    onClick={() =>
                                                        editContact(row)
                                                    }
                                                >
                                                    Add Contact
                                                </SecondaryButton>
                                            )}
                                            {canDeleteCustomer &&
                                                row.deals_count === 0 && (
                                                    <SecondaryButton
                                                        type="button"
                                                        disabled={
                                                            customerForm.processing ||
                                                            contactForm.processing
                                                        }
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
                                    className="rounded-md border border-slate-200 dark:border-slate-800 p-3 bg-white/5"
                                >
                                    <div className="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        {customer.company_name} contacts
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {customer.contacts.length === 0 && (
                                            <span className="text-sm text-slate-500 dark:text-slate-400">
                                                No contacts
                                            </span>
                                        )}
                                        {customer.contacts.map((contact) => (
                                            <button
                                                key={contact.id}
                                                type="button"
                                                disabled={
                                                    !canUpdateContact ||
                                                    customerForm.processing ||
                                                    contactForm.processing
                                                }
                                                onClick={() =>
                                                    editContact(
                                                        customer,
                                                        contact,
                                                    )
                                                }
                                                className="rounded-md border border-slate-200 dark:border-slate-800 px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800 bg-white dark:bg-slate-900 transition-colors duration-150"
                                            >
                                                <span className="font-semibold text-slate-800 dark:text-white">
                                                    {contact.name}
                                                </span>
                                                {contact.is_primary && (
                                                    <span className="ml-2 text-xs text-indigo-600 dark:text-indigo-400 font-medium">
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
