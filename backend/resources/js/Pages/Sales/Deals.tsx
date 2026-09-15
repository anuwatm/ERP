import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import type { PageProps } from '@/Types/auth';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import DealDialog from './DealDialog';
import ActivityDialog from './ActivityDialog';
import ContactDialog from './ContactDialog';
import type { ContactForm } from './Customers';

export type Owner = { id: string; name: string; email: string };
type Contact = {
    id: string;
    customer_id: string;
    name: string;
    email?: string | null;
    phone?: string | null;
};
export type Customer = {
    id: string;
    customer_code: string;
    company_name: string;
    owner_id?: string | null;
    contacts: Contact[];
};
export type Activity = {
    id: string;
    entity_type: string;
    entity_id: string;
    activity_type: string;
    subject?: string | null;
    body?: string | null;
    owner_id?: string | null;
    activity_at: string;
    follow_up_at?: string | null;
    completed_at?: string | null;
};
export type Deal = {
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
    project?: {
        id: string;
        deal_id: string;
        project_code: string;
        name: string;
    } | null;
    activities: Activity[];
    needs_sales_review?: boolean;
};

export type DealForm = {
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

export type ActivityForm = {
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

const emptyActivity = (): ActivityForm => ({
    entity_type: 'deal',
    entity_id: '',
    activity_type: 'call',
    subject: '',
    body: '',
    activity_at: new Date().toISOString().slice(0, 19),
    follow_up_at: '',
    completed_at: '',
    owner_id: '',
});

export default function Deals({
    deals,
    customers,
    owners,
    stages,
    canSeeAllSales,
    canCreateInvoice,
    canCreateProject,
}: {
    deals: Deal[];
    customers: Customer[];
    owners: Owner[];
    stages: string[];
    filters: Record<string, string | null>;
    canSeeAllSales: boolean;
    canCreateInvoice: boolean;
    canCreateProject: boolean;
}) {
    const [editingDeal, setEditingDeal] = useState<Deal | null>(null);
    const [editingActivity, setEditingActivity] = useState<Activity | null>(
        null,
    );
    const [editor, setEditor] = useState<'deal' | 'activity' | null>(null);
    const [minimized, setMinimized] = useState(false);
    const dealForm = useForm<DealForm>(emptyDeal);
    const activityForm = useForm<ActivityForm>(emptyActivity());
    const [creatingContact, setCreatingContact] = useState(false);
    const dealSubmission = useSubmissionKey();
    const activitySubmission = useSubmissionKey();
    const contactSubmission = useSubmissionKey();
    const contactForm = useForm<ContactForm>({
        customer_id: '',
        name: '',
        position: '',
        phone: '',
        email: '',
        line_id: '',
        is_primary: false,
        note: '',
    });
    const { auth } = usePage<PageProps>().props;
    const canCreateDeal = auth.permissions.includes('deals.create');
    const canUpdateDeal = auth.permissions.includes('deals.update');
    const canCreateActivity = auth.permissions.includes('activities.create');
    const canUpdateActivity = auth.permissions.includes('activities.update');
    const busy =
        dealForm.processing ||
        activityForm.processing ||
        contactForm.processing;
    const guard = useDiscardGuard(
        (editor === 'deal' && dealForm.isDirty) ||
            (editor === 'activity' && activityForm.isDirty) ||
            (creatingContact && contactForm.isDirty),
        busy,
    );

    const closeEditor = () => {
        dealSubmission.reset();
        activitySubmission.reset();
        setEditor(null);
        setCreatingContact(false);
        contactForm.reset();
        contactForm.clearErrors();
        setMinimized(false);
        setEditingDeal(null);
        setEditingActivity(null);
        dealForm.setDefaults(emptyDeal);
        dealForm.setData(emptyDeal);
        dealForm.clearErrors();
        const activity = emptyActivity();
        activityForm.setDefaults(activity);
        activityForm.setData(activity);
        activityForm.clearErrors();
    };

    const createContact = () => {
        if (
            busy ||
            !auth.permissions.includes('contacts.create') ||
            !dealForm.data.customer_id
        )
            return;
        const data = {
            customer_id: dealForm.data.customer_id,
            name: '',
            position: '',
            phone: '',
            email: '',
            line_id: '',
            is_primary: false,
            note: '',
        };
        contactSubmission.reset();
        contactForm.setDefaults(data);
        contactForm.setData(data);
        contactForm.clearErrors();
        setCreatingContact(true);
        setMinimized(false);
    };
    const submitQuickContact = (event: FormEvent) => {
        event.preventDefault();
        if (busy || !auth.permissions.includes('contacts.create')) return;
        contactForm.post(
            route('contacts.store', contactForm.data.customer_id),
            {
                headers: contactSubmission.headers(),
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const flash = page.props.flash as {
                        created_reference?: {
                            type: string;
                            id: string;
                            customer_id: string;
                        };
                    };
                    const reference = flash?.created_reference;
                    if (
                        reference?.type === 'contact' &&
                        reference.customer_id === dealForm.data.customer_id
                    ) {
                        dealForm.setData('contact_id', reference.id);
                        dealForm.clearErrors('contact_id');
                        setCreatingContact(false);
                        contactForm.reset();
                    } else {
                        contactForm.setError(
                            'name',
                            'Contact saved. Return to the deal and select it from the refreshed list.',
                        );
                    }
                },
            },
        );
    };

    const submitDeal = (event: FormEvent) => {
        event.preventDefault();
        if (busy || (editingDeal ? !canUpdateDeal : !canCreateDeal)) return;
        if (!dealForm.data.customer_id) {
            dealForm.setError('customer_id', 'Please select a customer.');
            return;
        }
        const customer = customers.find(
            (customer) => customer.id === dealForm.data.customer_id,
        );
        if (
            dealForm.data.contact_id &&
            customer &&
            !customer.contacts.some(
                (contact) => contact.id === dealForm.data.contact_id,
            )
        ) {
            dealForm.setError(
                'contact_id',
                'Select a contact belonging to this customer, or clear the contact.',
            );
            return;
        }
        const options = {
            preserveScroll: true,
            onSuccess: closeEditor,
        };

        if (editingDeal) {
            dealForm.patch(route('deals.update', editingDeal.id), options);
        } else {
            dealForm.post(route('deals.store'), {
                ...options,
                headers: dealSubmission.headers(),
            });
        }
    };

    const submitActivity = (event: FormEvent) => {
        event.preventDefault();
        if (busy || (editingActivity ? !canUpdateActivity : !canCreateActivity))
            return;
        if (!activityForm.data.entity_id) {
            activityForm.setError('entity_id', 'Please select a deal.');
            return;
        }
        const options = { preserveScroll: true, onSuccess: closeEditor };
        if (editingActivity)
            activityForm.patch(
                route('activities.update', editingActivity.id),
                options,
            );
        else
            activityForm.post(route('activities.store'), {
                ...options,
                headers: activitySubmission.headers(),
            });
    };

    const editDeal = (deal: Deal | null) => {
        if (busy || (deal ? !canUpdateDeal : !canCreateDeal)) return;
        if (editor === 'deal' && editingDeal?.id === deal?.id) {
            setMinimized(false);
            return;
        }
        guard.request(() => {
            closeEditor();
            setEditingDeal(deal);
            const data: DealForm = deal
                ? {
                      title: deal.title ?? '',
                      customer_id: deal.customer_id ?? '',
                      contact_id: deal.contact_id ?? '',
                      stage: deal.stage ?? 'new',
                      value_amount: deal.value_amount ?? '0',
                      currency: deal.currency ?? 'THB',
                      probability: deal.probability ?? 0,
                      expected_close_date:
                          deal.expected_close_date?.slice(0, 10) ?? '',
                      owner_id: deal.owner_id ?? '',
                      source: deal.source ?? '',
                      lost_reason: deal.lost_reason ?? '',
                      note: deal.note ?? '',
                  }
                : { ...emptyDeal };
            dealForm.setDefaults(data);
            dealForm.setData(data);
            setEditor('deal');
        });
    };

    const editActivity = (deal?: Deal, activity?: Activity) => {
        if (busy || (activity ? !canUpdateActivity : !canCreateActivity))
            return;
        if (
            editor === 'activity' &&
            editingActivity?.id === activity?.id &&
            activityForm.data.entity_id === (deal?.id ?? '')
        ) {
            setMinimized(false);
            return;
        }
        guard.request(() => {
            closeEditor();
            setEditingActivity(activity ?? null);
            const data: ActivityForm = activity
                ? {
                      entity_type: 'deal',
                      entity_id: activity.entity_id,
                      activity_type: activity.activity_type,
                      subject: activity.subject ?? '',
                      body: activity.body ?? '',
                      activity_at: activity.activity_at,
                      follow_up_at: activity.follow_up_at ?? '',
                      completed_at: activity.completed_at ?? '',
                      owner_id: activity.owner_id ?? '',
                  }
                : { ...emptyActivity(), entity_id: deal?.id ?? '' };
            activityForm.setDefaults(data);
            activityForm.setData(data);
            setEditor('activity');
        });
    };

    const runAction = (action: () => void) =>
        guard.request(() => {
            closeEditor();
            action();
        });

    return (
        <AuthenticatedLayout>
            <Head title="Deals" />
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <DealDialog
                open={editor === 'deal'}
                minimized={minimized}
                suspended={creatingContact}
                onCreateContact={
                    auth.permissions.includes('contacts.create') &&
                    customers.some(
                        (customer) => customer.id === dealForm.data.customer_id,
                    )
                        ? createContact
                        : undefined
                }
                onMinimize={setMinimized}
                onClose={() => guard.request(closeEditor)}
                form={dealForm}
                editing={editingDeal}
                customers={customers}
                owners={owners}
                stages={stages}
                canSeeAllSales={canSeeAllSales}
                onSubmit={submitDeal}
            />
            <ActivityDialog
                open={editor === 'activity'}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(closeEditor)}
                form={activityForm}
                editing={editingActivity}
                deals={deals}
                owners={owners}
                onSubmit={submitActivity}
            />
            <ContactDialog
                open={creatingContact}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() =>
                    guard.request(
                        () => {
                            setCreatingContact(false);
                            setMinimized(false);
                            contactForm.reset();
                            contactForm.clearErrors();
                        },
                        { dirty: contactForm.isDirty },
                    )
                }
                editing={false}
                customer={
                    customers.find(
                        (customer) =>
                            customer.id === contactForm.data.customer_id,
                    )?.company_name ?? 'Selected customer'
                }
                form={contactForm}
                onSubmit={submitQuickContact}
            />

            <div className="space-y-6">
                <PageHeader
                    title="Deals Pipeline"
                    description="Track sales opportunities, stage movement, and follow-up activity."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {canCreateDeal && (
                                <PrimaryButton
                                    type="button"
                                    onClick={() => editDeal(null)}
                                    disabled={busy}
                                >
                                    + Create Deal
                                </PrimaryButton>
                            )}
                            {canCreateActivity && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => editActivity()}
                                    disabled={busy || !deals.length}
                                >
                                    + Create Activity
                                </SecondaryButton>
                            )}
                        </div>
                    }
                />

                <div>
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
                                            {row.needs_sales_review && (
                                                <div className="mt-1">
                                                    <Badge
                                                        variant="warning"
                                                        size="sm"
                                                    >
                                                        needs sales review
                                                    </Badge>
                                                </div>
                                            )}
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
                                            {canUpdateDeal && (
                                                <SecondaryButton
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        editDeal(row)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                            )}
                                            {canCreateActivity && (
                                                <SecondaryButton
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() =>
                                                        editActivity(row)
                                                    }
                                                >
                                                    Activity
                                                </SecondaryButton>
                                            )}
                                            {canCreateInvoice &&
                                                [
                                                    'proposal',
                                                    'negotiation',
                                                    'won',
                                                ].includes(row.stage) && (
                                                    <SecondaryButton
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            router.get(
                                                                route(
                                                                    'invoices.index',
                                                                ),
                                                                {
                                                                    deal_id:
                                                                        row.id,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Create Invoice
                                                    </SecondaryButton>
                                                )}
                                            {canCreateProject &&
                                                row.stage === 'won' &&
                                                !row.project && (
                                                    <SecondaryButton
                                                        type="button"
                                                        disabled={busy}
                                                        onClick={() =>
                                                            runAction(() =>
                                                                router.post(
                                                                    route(
                                                                        'deals.projects.store',
                                                                        row.id,
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Create Project
                                                    </SecondaryButton>
                                                )}
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <div className="mt-6 space-y-3">
                            {deals.map((deal) => (
                                <div
                                    key={deal.id}
                                    className="rounded-md border border-slate-200 dark:border-slate-800 p-3 bg-white/5"
                                >
                                    <div className="mb-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        {deal.title} activities
                                    </div>
                                    <div className="space-y-2">
                                        {deal.activities.length === 0 && (
                                            <div className="text-sm text-slate-500 dark:text-slate-400">
                                                No recent activity
                                            </div>
                                        )}
                                        {deal.activities.map((activity) => (
                                            <div
                                                key={activity.id}
                                                className="flex items-center justify-between gap-3 rounded-md bg-slate-50 dark:bg-slate-900 px-3 py-2 text-sm"
                                            >
                                                <div>
                                                    <span className="font-semibold text-slate-800 dark:text-white">
                                                        {activity.subject ||
                                                            activity.activity_type}
                                                    </span>
                                                    {activity.follow_up_at &&
                                                        !activity.completed_at && (
                                                            <span className="ml-2 text-xs text-amber-700 dark:text-amber-500 font-medium">
                                                                follow-up open
                                                            </span>
                                                        )}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {canUpdateActivity && (
                                                        <SecondaryButton
                                                            type="button"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                editActivity(
                                                                    deal,
                                                                    activity,
                                                                )
                                                            }
                                                        >
                                                            Edit Activity
                                                        </SecondaryButton>
                                                    )}
                                                    {canUpdateActivity &&
                                                        !activity.completed_at &&
                                                        activity.follow_up_at && (
                                                            <SecondaryButton
                                                                type="button"
                                                                disabled={busy}
                                                                onClick={() =>
                                                                    runAction(
                                                                        () =>
                                                                            router.patch(
                                                                                route(
                                                                                    'activities.complete',
                                                                                    activity.id,
                                                                                ),
                                                                                {},
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            ),
                                                                    )
                                                                }
                                                            >
                                                                Done
                                                            </SecondaryButton>
                                                        )}
                                                </div>
                                            </div>
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
