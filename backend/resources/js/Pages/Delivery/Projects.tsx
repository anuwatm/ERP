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
import { money } from '@/Utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = {
    id: string;
    title: string;
    customer_id: string;
    owner_id?: string | null;
    value_amount: string;
};
type Owner = { id: string; name: string; email: string };
type ProjectMember = {
    id: string;
    user_id: string;
    role: string;
    user?: Owner | null;
};
type Project = {
    id: string;
    project_code: string;
    name: string;
    customer_id?: string | null;
    deal_id?: string | null;
    owner_id?: string | null;
    status: string;
    start_date?: string | null;
    due_date?: string | null;
    progress_percent: number;
    budget_amount: string;
    actual_cost?: string;
    gross_margin?: string;
    currency: string;
    note?: string | null;
    customer?: Customer | null;
    deal?: Deal | null;
    owner?: Owner | null;
    members?: ProjectMember[];
};
type ProjectForm = {
    name: string;
    customer_id: string;
    deal_id: string;
    owner_id: string;
    status: string;
    start_date: string;
    due_date: string;
    progress_percent: string;
    budget_amount: string;
    currency: string;
    note: string;
};

const emptyProject: ProjectForm = {
    name: '',
    customer_id: '',
    deal_id: '',
    owner_id: '',
    status: 'planning',
    start_date: '',
    due_date: '',
    progress_percent: '0',
    budget_amount: '0.00',
    currency: 'THB',
    note: '',
};

export default function Projects({
    projects,
    customers,
    deals,
    owners,
    statuses,
    canSeeAllProjects,
    canReassignProjects,
}: {
    projects: Project[];
    customers: Customer[];
    deals: Deal[];
    owners: Owner[];
    statuses: string[];
    filters: Record<string, string | null>;
    canSeeAllProjects: boolean;
    canReassignProjects: boolean;
}) {
    const [editingProject, setEditingProject] = useState<Project | null>(null);
    const form = useForm<ProjectForm>(emptyProject);
    const memberForm = useForm({ user_id: '', role: 'member' });
    const availableDeals = useMemo(
        () =>
            deals.filter((deal) => deal.customer_id === form.data.customer_id),
        [deals, form.data.customer_id],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyProject);
                setEditingProject(null);
            },
        };

        if (editingProject) {
            form.patch(route('projects.update', editingProject.id), options);
        } else {
            form.post(route('projects.store'), options);
        }
    };

    const editProject = (project: Project) => {
        setEditingProject(project);
        form.setData({
            name: project.name ?? '',
            customer_id: project.customer_id ?? '',
            deal_id: project.deal_id ?? '',
            owner_id: project.owner_id ?? '',
            status: project.status ?? 'planning',
            start_date: project.start_date?.slice(0, 10) ?? '',
            due_date: project.due_date?.slice(0, 10) ?? '',
            progress_percent: String(project.progress_percent ?? 0),
            budget_amount: project.budget_amount ?? '0.00',
            currency: project.currency ?? 'THB',
            note: project.note ?? '',
        });
    };

    const setCustomer = (customerId: string) => {
        form.setData({
            ...form.data,
            customer_id: customerId,
            deal_id: '',
        });
    };

    const setDeal = (dealId: string) => {
        const deal = deals.find((item) => item.id === dealId);
        form.setData({
            ...form.data,
            deal_id: dealId,
            name: deal && !form.data.name ? deal.title : form.data.name,
            budget_amount: deal
                ? (deal.value_amount ?? form.data.budget_amount)
                : form.data.budget_amount,
            owner_id:
                deal?.owner_id && canReassignProjects
                    ? deal.owner_id
                    : form.data.owner_id,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Projects" />

            <div className="space-y-6">
                <PageHeader
                    title="Projects"
                    description="Delivery projects from won deals or manual work."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
                    <Card
                        title="Project List"
                        description="Delivery work tracking"
                    >
                        <DataTable
                            data={projects}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Project',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.name}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500 dark:text-slate-300">
                                                {row.project_code}
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-300">
                                                {row.customer?.company_name ??
                                                    '-'}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={statusVariant(row.status)}
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Owner',
                                    accessor: (row) => row.owner?.name ?? '-',
                                },
                                {
                                    header: 'Progress',
                                    accessor: (row) =>
                                        `${row.progress_percent}%`,
                                },
                                {
                                    header: 'Budget',
                                    accessor: (row) =>
                                        `${money(row.budget_amount)} ${row.currency}`,
                                },
                                {
                                    header: 'Actual Cost',
                                    accessor: (row) =>
                                        `${money(row.actual_cost ?? '0.00')} ${row.currency}`,
                                },
                                {
                                    header: 'Margin',
                                    accessor: (row) =>
                                        `${money(row.gross_margin ?? row.budget_amount)} ${row.currency}`,
                                },
                                {
                                    header: 'Due',
                                    accessor: (row) =>
                                        row.due_date?.slice(0, 10) ?? '-',
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => editProject(row)}
                                        >
                                            Edit
                                        </SecondaryButton>
                                    ),
                                },
                            ]}
                        />
                    </Card>

                    <Card
                        title={
                            editingProject ? 'Edit Project' : 'Create Project'
                        }
                        description="Manual project setup"
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <Field
                                label="Name"
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                                required
                            />
                            <SelectField
                                label="Customer"
                                value={form.data.customer_id}
                                onChange={setCustomer}
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
                                label="Won Deal"
                                value={form.data.deal_id}
                                onChange={setDeal}
                                options={availableDeals.map((deal) => deal.id)}
                                labels={Object.fromEntries(
                                    availableDeals.map((deal) => [
                                        deal.id,
                                        deal.title,
                                    ]),
                                )}
                            />
                            {canSeeAllProjects && canReassignProjects && (
                                <SelectField
                                    label="Owner"
                                    value={form.data.owner_id}
                                    onChange={(value) =>
                                        form.setData('owner_id', value)
                                    }
                                    options={owners.map((owner) => owner.id)}
                                    labels={Object.fromEntries(
                                        owners.map((owner) => [
                                            owner.id,
                                            owner.name,
                                        ]),
                                    )}
                                />
                            )}
                            <SelectField
                                label="Status"
                                value={form.data.status}
                                onChange={(value) =>
                                    form.setData('status', value)
                                }
                                options={statuses}
                            />
                            <div className="grid grid-cols-2 gap-2">
                                <Field
                                    label="Start Date"
                                    type="date"
                                    value={form.data.start_date}
                                    error={form.errors.start_date}
                                    onChange={(value) =>
                                        form.setData('start_date', value)
                                    }
                                />
                                <Field
                                    label="Due Date"
                                    type="date"
                                    value={form.data.due_date}
                                    error={form.errors.due_date}
                                    onChange={(value) =>
                                        form.setData('due_date', value)
                                    }
                                />
                                <Field
                                    label="Progress %"
                                    type="number"
                                    value={form.data.progress_percent}
                                    error={form.errors.progress_percent}
                                    onChange={(value) =>
                                        form.setData('progress_percent', value)
                                    }
                                />
                                <Field
                                    label="Budget"
                                    type="number"
                                    value={form.data.budget_amount}
                                    error={form.errors.budget_amount}
                                    onChange={(value) =>
                                        form.setData('budget_amount', value)
                                    }
                                />
                            </div>
                            <Field
                                label="Currency"
                                value={form.data.currency}
                                error={form.errors.currency}
                                onChange={(value) =>
                                    form.setData(
                                        'currency',
                                        value.toUpperCase(),
                                    )
                                }
                                required
                            />
                            <Field
                                label="Note"
                                value={form.data.note}
                                error={form.errors.note}
                                onChange={(value) =>
                                    form.setData('note', value)
                                }
                            />
                            <div className="flex justify-end gap-2">
                                {editingProject && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            setEditingProject(null);
                                            form.setData(emptyProject);
                                        }}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                )}
                                <PrimaryButton disabled={form.processing}>
                                    Save Project
                                </PrimaryButton>
                            </div>
                        </form>

                        {editingProject && (
                            <div className="mt-5 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                                <div className="text-sm font-semibold text-slate-800 dark:text-white">
                                    Project Members
                                </div>
                                <form
                                    className="grid grid-cols-[1fr_110px_auto] gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        memberForm.post(
                                            route(
                                                'projects.members.store',
                                                editingProject.id,
                                            ),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    memberForm.setData({
                                                        user_id: '',
                                                        role: 'member',
                                                    }),
                                            },
                                        );
                                    }}
                                >
                                    <select
                                        className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        value={memberForm.data.user_id}
                                        onChange={(event) =>
                                            memberForm.setData(
                                                'user_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Select user</option>
                                        {owners.map((owner) => (
                                            <option
                                                key={owner.id}
                                                value={owner.id}
                                            >
                                                {owner.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                        value={memberForm.data.role}
                                        onChange={(event) =>
                                            memberForm.setData(
                                                'role',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="manager">manager</option>
                                        <option value="member">member</option>
                                        <option value="viewer">viewer</option>
                                    </select>
                                    <PrimaryButton
                                        disabled={memberForm.processing}
                                    >
                                        Add
                                    </PrimaryButton>
                                </form>
                                <div className="space-y-2">
                                    {(editingProject.members ?? []).map(
                                        (member) => (
                                            <div
                                                key={member.id}
                                                className="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-900"
                                            >
                                                <div>
                                                    <div className="font-semibold text-slate-800 dark:text-white">
                                                        {member.user?.name ??
                                                            member.user_id}
                                                    </div>
                                                    <div className="text-xs text-slate-500">
                                                        {member.role}
                                                    </div>
                                                </div>
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.delete(
                                                            route(
                                                                'projects.members.destroy',
                                                                [
                                                                    editingProject.id,
                                                                    member.id,
                                                                ],
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                    </Card>
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
    const id = label
        .toLowerCase()
        .replaceAll(' ', '-')
        .replaceAll('%', 'percent');

    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700 dark:text-slate-200"
            />
            <TextInput
                id={id}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                min={type === 'number' ? '0' : undefined}
                max={label === 'Progress %' ? '100' : undefined}
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
                className="mb-1 text-xs font-semibold uppercase text-slate-700 dark:text-slate-200"
            />
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-xl border-slate-300 bg-white text-sm font-medium text-slate-900 shadow-sm transition-colors duration-150 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
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

function statusVariant(status: string) {
    if (status === 'completed') {
        return 'success';
    }
    if (status === 'on_hold') {
        return 'warning';
    }
    if (status === 'cancelled') {
        return 'danger';
    }
    if (status === 'active') {
        return 'info';
    }
    return 'neutral';
}
