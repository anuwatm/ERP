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
import { FormEvent, useMemo, useState } from 'react';

type Owner = { id: string; name: string; email: string };
type Contact = {
    id: string;
    customer_id: string;
    name: string;
    email?: string | null;
    phone?: string | null;
};
type Customer = {
    id: string;
    customer_code: string;
    company_name: string;
    owner_id?: string | null;
    contacts: Contact[];
};
type Activity = {
    id: string;
    entity_type: string;
    entity_id: string;
    activity_type: string;
    subject?: string | null;
    activity_at: string;
    follow_up_at?: string | null;
    completed_at?: string | null;
};
type Deal = {
    id: string;
    title: string;
    customer_id: string;
    contact_id?: string | null;
    stage: string;
    value_amount: string;
    currency: string;
    probability: number;
    expected_close_date?: string | null;
    owner_id?: string | null;
    source?: string | null;
    lost_reason?: string | null;
    note?: string | null;
    customer?: Customer | null;
    contact?: Contact | null;
    owner?: Owner | null;
    activities: Activity[];
};

type DealForm = {
    title: string;
    customer_id: string;
    contact_id: string;
    stage: string;
    value_amount: string;
    currency: string;
    probability: number;
    expected_close_date: string;
    owner_id: string;
    source: string;
    lost_reason: string;
    note: string;
};

type ActivityForm = {
    entity_type: 'deal';
    entity_id: string;
    activity_type: string;
    subject: string;
    body: string;
    activity_at: string;
    follow_up_at: string;
    completed_at: string;
    owner_id: string;
};

const emptyDeal: DealForm = {
    title: '',
    customer_id: '',
    contact_id: '',
    stage: 'new',
    value_amount: '0',
    currency: 'THB',
    probability: 0,
    expected_close_date: '',
    owner_id: '',
    source: '',
    lost_reason: '',
    note: '',
};

const emptyActivity: ActivityForm = {
    entity_type: 'deal',
    entity_id: '',
    activity_type: 'call',
    subject: '',
    body: '',
    activity_at: new Date().toISOString().slice(0, 16),
    follow_up_at: '',
    completed_at: '',
    owner_id: '',
};

export default function Deals({
    deals,
    customers,
    owners,
    stages,
    canSeeAllSales,
}: {
    deals: Deal[];
    customers: Customer[];
    owners: Owner[];
    stages: string[];
    filters: Record<string, string | null>;
    canSeeAllSales: boolean;
}) {
    const [editingDeal, setEditingDeal] = useState<Deal | null>(null);
    const dealForm = useForm<DealForm>(emptyDeal);
    const activityForm = useForm<ActivityForm>(emptyActivity);
    const selectedCustomerContacts = useMemo(
        () =>
            customers.find(
                (customer) => customer.id === dealForm.data.customer_id,
            )?.contacts ?? [],
        [customers, dealForm.data.customer_id],
    );

    const submitDeal = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                dealForm.setData(emptyDeal);
                setEditingDeal(null);
            },
        };

        if (editingDeal) {
            dealForm.patch(route('deals.update', editingDeal.id), options);
        } else {
            dealForm.post(route('deals.store'), options);
        }
    };

    const submitActivity = (event: FormEvent) => {
        event.preventDefault();
        activityForm.post(route('activities.store'), {
            preserveScroll: true,
            onSuccess: () => activityForm.setData(emptyActivity),
        });
    };

    const editDeal = (deal: Deal) => {
        setEditingDeal(deal);
        dealForm.setData({
            title: deal.title ?? '',
            customer_id: deal.customer_id ?? '',
            contact_id: deal.contact_id ?? '',
            stage: deal.stage ?? 'new',
            value_amount: deal.value_amount ?? '0',
            currency: deal.currency ?? 'THB',
            probability: deal.probability ?? 0,
            expected_close_date: deal.expected_close_date?.slice(0, 10) ?? '',
            owner_id: deal.owner_id ?? '',
            source: deal.source ?? '',
            lost_reason: deal.lost_reason ?? '',
            note: deal.note ?? '',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Deals" />

            <div className="space-y-6">
                <PageHeader
                    title="Deals Pipeline"
                    description="Track sales opportunities, stage movement, and follow-up activity."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <Card
                        title="Pipeline Deals"
                        description="Open and closed opportunities"
                    >
                        <DataTable
                            data={deals}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Deal',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.title}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {row.customer?.company_name}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Stage',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.stage === 'won'
                                                    ? 'success'
                                                    : row.stage === 'lost'
                                                      ? 'danger'
                                                      : 'info'
                                            }
                                            size="sm"
                                        >
                                            {row.stage}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Value',
                                    accessor: (row) =>
                                        `${Number(row.value_amount).toLocaleString()} ${row.currency}`,
                                },
                                {
                                    header: 'Owner',
                                    accessor: (row) => row.owner?.name ?? '-',
                                },
                                {
                                    header: 'Expected Close',
                                    accessor: (row) =>
                                        row.expected_close_date?.slice(0, 10) ??
                                        '-',
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            <SecondaryButton
                                                type="button"
                                                onClick={() => editDeal(row)}
                                            >
                                                Edit
                                            </SecondaryButton>
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    activityForm.setData({
                                                        ...emptyActivity,
                                                        entity_id: row.id,
                                                    })
                                                }
                                            >
                                                Activity
                                            </SecondaryButton>
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <div className="mt-6 space-y-3">
                            {deals.map((deal) => (
                                <div
                                    key={deal.id}
                                    className="rounded-md border border-slate-200 p-3"
                                >
                                    <div className="mb-2 text-sm font-semibold text-slate-800">
                                        {deal.title} activities
                                    </div>
                                    <div className="space-y-2">
                                        {deal.activities.length === 0 && (
                                            <div className="text-sm text-slate-500">
                                                No recent activity
                                            </div>
                                        )}
                                        {deal.activities.map((activity) => (
                                            <div
                                                key={activity.id}
                                                className="flex items-center justify-between gap-3 rounded-md bg-slate-50 px-3 py-2 text-sm"
                                            >
                                                <div>
                                                    <span className="font-semibold text-slate-800">
                                                        {activity.subject ||
                                                            activity.activity_type}
                                                    </span>
                                                    {activity.follow_up_at &&
                                                        !activity.completed_at && (
                                                            <span className="ml-2 text-xs text-amber-700">
                                                                follow-up open
                                                            </span>
                                                        )}
                                                </div>
                                                {!activity.completed_at &&
                                                    activity.follow_up_at && (
                                                        <SecondaryButton
                                                            type="button"
                                                            onClick={() =>
                                                                router.patch(
                                                                    route(
                                                                        'activities.complete',
                                                                        activity.id,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            Done
                                                        </SecondaryButton>
                                                    )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <div className="space-y-6">
                        <Card
                            title={editingDeal ? 'Edit Deal' : 'Create Deal'}
                            description="Won/lost rules are enforced by server"
                        >
                            <form onSubmit={submitDeal} className="space-y-3">
                                <Field
                                    label="Title"
                                    value={dealForm.data.title}
                                    error={dealForm.errors.title}
                                    onChange={(value) =>
                                        dealForm.setData('title', value)
                                    }
                                    required
                                />
                                <SelectField
                                    label="Customer"
                                    value={dealForm.data.customer_id}
                                    onChange={(value) =>
                                        dealForm.setData('customer_id', value)
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
                                <SelectField
                                    label="Contact"
                                    value={dealForm.data.contact_id}
                                    onChange={(value) =>
                                        dealForm.setData('contact_id', value)
                                    }
                                    options={selectedCustomerContacts.map(
                                        (contact) => contact.id,
                                    )}
                                    labels={Object.fromEntries(
                                        selectedCustomerContacts.map(
                                            (contact) => [
                                                contact.id,
                                                contact.name,
                                            ],
                                        ),
                                    )}
                                />
                                <SelectField
                                    label="Stage"
                                    value={dealForm.data.stage}
                                    onChange={(value) =>
                                        dealForm.setData('stage', value)
                                    }
                                    options={stages}
                                />
                                <Field
                                    label="Value Amount"
                                    value={dealForm.data.value_amount}
                                    error={dealForm.errors.value_amount}
                                    onChange={(value) =>
                                        dealForm.setData('value_amount', value)
                                    }
                                />
                                <Field
                                    label="Probability"
                                    value={String(dealForm.data.probability)}
                                    error={dealForm.errors.probability}
                                    onChange={(value) =>
                                        dealForm.setData(
                                            'probability',
                                            Number(value),
                                        )
                                    }
                                />
                                <Field
                                    label="Expected Close Date"
                                    value={dealForm.data.expected_close_date}
                                    error={dealForm.errors.expected_close_date}
                                    onChange={(value) =>
                                        dealForm.setData(
                                            'expected_close_date',
                                            value,
                                        )
                                    }
                                    type="date"
                                />
                                {canSeeAllSales && (
                                    <SelectField
                                        label="Owner"
                                        value={dealForm.data.owner_id}
                                        onChange={(value) =>
                                            dealForm.setData('owner_id', value)
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
                                {dealForm.data.stage === 'lost' && (
                                    <Field
                                        label="Lost Reason"
                                        value={dealForm.data.lost_reason}
                                        error={dealForm.errors.lost_reason}
                                        onChange={(value) =>
                                            dealForm.setData(
                                                'lost_reason',
                                                value,
                                            )
                                        }
                                        required
                                    />
                                )}
                                <div className="flex justify-end gap-2">
                                    {editingDeal && (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => {
                                                setEditingDeal(null);
                                                dealForm.setData(emptyDeal);
                                            }}
                                        >
                                            Cancel
                                        </SecondaryButton>
                                    )}
                                    <PrimaryButton
                                        disabled={dealForm.processing}
                                    >
                                        Save Deal
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Create Activity"
                            description="Activity entity allowlist: deal"
                        >
                            <form
                                onSubmit={submitActivity}
                                className="space-y-3"
                            >
                                <SelectField
                                    label="Deal"
                                    value={activityForm.data.entity_id}
                                    onChange={(value) =>
                                        activityForm.setData('entity_id', value)
                                    }
                                    options={deals.map((deal) => deal.id)}
                                    labels={Object.fromEntries(
                                        deals.map((deal) => [
                                            deal.id,
                                            deal.title,
                                        ]),
                                    )}
                                />
                                <SelectField
                                    label="Activity Type"
                                    value={activityForm.data.activity_type}
                                    onChange={(value) =>
                                        activityForm.setData(
                                            'activity_type',
                                            value,
                                        )
                                    }
                                    options={[
                                        'call',
                                        'meeting',
                                        'email',
                                        'line',
                                        'note',
                                    ]}
                                />
                                <Field
                                    label="Subject"
                                    value={activityForm.data.subject}
                                    error={activityForm.errors.subject}
                                    onChange={(value) =>
                                        activityForm.setData('subject', value)
                                    }
                                />
                                <Field
                                    label="Activity At"
                                    value={activityForm.data.activity_at}
                                    error={activityForm.errors.activity_at}
                                    onChange={(value) =>
                                        activityForm.setData(
                                            'activity_at',
                                            value,
                                        )
                                    }
                                    type="datetime-local"
                                />
                                <Field
                                    label="Follow Up At"
                                    value={activityForm.data.follow_up_at}
                                    error={activityForm.errors.follow_up_at}
                                    onChange={(value) =>
                                        activityForm.setData(
                                            'follow_up_at',
                                            value,
                                        )
                                    }
                                    type="datetime-local"
                                />
                                <PrimaryButton
                                    disabled={activityForm.processing}
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
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                            />
                                        </svg>
                                    }
                                >
                                    Save Activity
                                </PrimaryButton>
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
    type = 'text',
    required = false,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
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
                type={type}
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
