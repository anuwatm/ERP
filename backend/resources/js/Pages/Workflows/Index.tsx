import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
type User = { id: string; name: string; email: string };
type Instance = {
    id: string;
    subject_type: string;
    subject_id: string;
    submitted_at: string;
    subject_snapshot: {
        expense_no?: string;
        po_no?: string;
        pr_no?: string;
        title?: string;
        total_days?: number;
    };
};
type Approval = {
    id: string;
    step_no: number;
    status: string;
    acted_at: string | null;
    instance: Instance;
    assigned_user: User;
    delegated_from_user?: User | null;
};
type Step = { step_no: number; assignment_type: string };
type Definition = {
    id: string;
    name: string;
    version: number;
    subject_type: string;
    amount_min: string | null;
    amount_max: string | null;
    is_active: boolean;
    steps: Step[];
};
type Delegation = {
    id: string;
    starts_at: string;
    ends_at: string;
    delegate_user: User;
};
type Props = {
    definitions: Definition[];
    inbox: Approval[];
    history: Approval[];
    users: User[];
    delegations: Delegation[];
    can: { manage: boolean; approve: boolean };
};
type BuilderStep = {
    assignment_type: 'user' | 'manager' | 'role';
    approver_user_id: string;
    approver_role_code: string;
    execution_mode: 'sequential' | 'parallel';
};

const newBuilderStep = (): BuilderStep => ({
    assignment_type: 'user',
    approver_user_id: '',
    approver_role_code: '',
    execution_mode: 'sequential',
});

const documentLabel = (instance: Instance) => {
    const snapshot = instance.subject_snapshot;

    return (
        snapshot.expense_no ??
        snapshot.pr_no ??
        snapshot.po_no ??
        snapshot.title ??
        (snapshot.total_days
            ? `Leave request · ${snapshot.total_days} day(s)`
            : `${instance.subject_type} · ${instance.subject_id}`)
    );
};

export default function WorkflowIndex({
    definitions,
    inbox,
    history,
    users,
    delegations,
    can,
}: Props) {
    const [builderSteps, setBuilderSteps] = useState<BuilderStep[]>([
        newBuilderStep(),
    ]);
    const submit = (event: FormEvent<HTMLFormElement>, name: string) => {
        event.preventDefault();
        router.post(
            route(name),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveScroll: true },
        );
    };
    const submitDefinition = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const fields = Object.fromEntries(new FormData(event.currentTarget));

        router.post(route('workflows.definitions.store'), {
            ...fields,
            steps: builderSteps,
        });
    };
    return (
        <AuthenticatedLayout>
            <Head title="Approval Workflows" />
            <div className="space-y-6">
                <PageHeader
                    title="Approval Workflows"
                    description="Versioned, snapshot-based approvals with delegation and separation of duties."
                />
                <Card title="My Approval Inbox">
                    <DataTable
                        data={inbox}
                        keyExtractor={(item: Approval) => item.id}
                        columns={[
                            {
                                header: 'Document',
                                accessor: (item: Approval) =>
                                    documentLabel(item.instance),
                            },
                            {
                                header: 'Step',
                                accessor: (item: Approval) => item.step_no,
                            },
                            {
                                header: 'Routing',
                                accessor: (item: Approval) =>
                                    item.delegated_from_user
                                        ? `Delegated from ${item.delegated_from_user.name}`
                                        : item.assigned_user.name,
                            },
                            {
                                header: 'Submitted',
                                accessor: (item: Approval) =>
                                    item.instance.submitted_at,
                            },
                            {
                                header: 'Action',
                                accessor: (item: Approval) => (
                                    <div className="flex gap-3">
                                        {[
                                            'approved',
                                            'rejected',
                                            'revision_requested',
                                        ].map((action) => (
                                            <button
                                                key={action}
                                                className={
                                                    action === 'approved'
                                                        ? 'text-emerald-700'
                                                        : action === 'rejected'
                                                          ? 'text-rose-700'
                                                          : 'text-amber-700'
                                                }
                                                onClick={() => {
                                                    const comment =
                                                        window.prompt(
                                                            `Confirm ${action.replace('_', ' ')}. Comment is required for reject/revision.`,
                                                        );
                                                    if (
                                                        comment === null ||
                                                        (action !==
                                                            'approved' &&
                                                            !comment.trim())
                                                    )
                                                        return;
                                                    router.post(
                                                        route(
                                                            'workflows.approvals.act',
                                                            item.id,
                                                        ),
                                                        { action, comment },
                                                    );
                                                }}
                                            >
                                                {action.replace('_', ' ')}
                                            </button>
                                        ))}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>
                <Card title="Approval History">
                    <DataTable
                        data={history}
                        keyExtractor={(item: Approval) => item.id}
                        columns={[
                            {
                                header: 'Document',
                                accessor: (item: Approval) =>
                                    documentLabel(item.instance),
                            },
                            {
                                header: 'Decision',
                                accessor: (item: Approval) => item.status,
                            },
                            {
                                header: 'Routing',
                                accessor: (item: Approval) =>
                                    item.delegated_from_user
                                        ? `Delegated from ${item.delegated_from_user.name}`
                                        : item.assigned_user.name,
                            },
                            {
                                header: 'Acted',
                                accessor: (item: Approval) =>
                                    item.acted_at ?? '-',
                            },
                        ]}
                    />
                </Card>
                {can.approve && (
                    <Card title="Temporary Delegation">
                        <form
                            className="grid gap-3 md:grid-cols-4"
                            onSubmit={(e) =>
                                submit(e, 'workflows.delegations.store')
                            }
                        >
                            <select
                                required
                                name="delegate_user_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Delegate to</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="subject_type"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">
                                    All supported documents
                                </option>
                                <option value="leave_request">Leave</option>
                                <option value="purchase_request">
                                    Purchase request
                                </option>
                                <option value="purchase_order">
                                    Purchase order
                                </option>
                                <option value="expense">Expense</option>
                            </select>
                            <input
                                required
                                name="starts_at"
                                type="datetime-local"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="ends_at"
                                type="datetime-local"
                                className="rounded-md border-slate-300"
                            />
                            <div className="md:col-span-4">
                                <PrimaryButton>Save Delegation</PrimaryButton>
                            </div>
                        </form>
                        <div className="mt-4 text-sm text-slate-600">
                            {delegations.map((d) => (
                                <div key={d.id}>
                                    {d.delegate_user.name}: {d.starts_at} to{' '}
                                    {d.ends_at}
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
                {can.manage && (
                    <Card
                        title="Workflow Builder"
                        description="Creating the same code produces a new immutable version for future submissions."
                    >
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={submitDefinition}
                        >
                            <input
                                required
                                name="code"
                                placeholder="Workflow code"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="name"
                                placeholder="Workflow name"
                                className="rounded-md border-slate-300"
                            />
                            <select
                                required
                                name="subject_type"
                                className="rounded-md border-slate-300"
                            >
                                <option value="expense">Expense</option>
                                <option value="purchase_request">
                                    Purchase request
                                </option>
                                <option value="purchase_order">
                                    Purchase order
                                </option>
                                <option value="leave_request">
                                    Leave request
                                </option>
                            </select>
                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    name="amount_min"
                                    type="number"
                                    step="0.01"
                                    placeholder="Minimum amount"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    name="amount_max"
                                    type="number"
                                    step="0.01"
                                    placeholder="Maximum amount"
                                    className="rounded-md border-slate-300"
                                />
                            </div>
                            <div className="space-y-3 md:col-span-2">
                                {builderSteps.map((step, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-2 border-l-2 border-slate-200 pl-3 md:grid-cols-5"
                                    >
                                        <span className="self-center text-sm font-medium text-slate-700">
                                            Step {index + 1}
                                        </span>
                                        <select
                                            required
                                            name={`steps[${index}][assignment_type]`}
                                            value={step.assignment_type}
                                            onChange={(event) =>
                                                setBuilderSteps((current) =>
                                                    current.map(
                                                        (value, position) =>
                                                            position === index
                                                                ? {
                                                                      ...value,
                                                                      assignment_type:
                                                                          event
                                                                              .target
                                                                              .value as BuilderStep['assignment_type'],
                                                                  }
                                                                : value,
                                                    ),
                                                )
                                            }
                                            className="rounded-md border-slate-300"
                                        >
                                            <option value="user">
                                                Specific user
                                            </option>
                                            <option value="manager">
                                                Requester manager
                                            </option>
                                            <option value="role">Role</option>
                                        </select>
                                        <select
                                            name={`steps[${index}][approver_user_id]`}
                                            value={step.approver_user_id}
                                            disabled={
                                                step.assignment_type !== 'user'
                                            }
                                            onChange={(event) =>
                                                setBuilderSteps((current) =>
                                                    current.map(
                                                        (value, position) =>
                                                            position === index
                                                                ? {
                                                                      ...value,
                                                                      approver_user_id:
                                                                          event
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : value,
                                                    ),
                                                )
                                            }
                                            className="rounded-md border-slate-300"
                                        >
                                            <option value="">
                                                Specific approver
                                            </option>
                                            {users.map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.name}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            name={`steps[${index}][approver_role_code]`}
                                            value={step.approver_role_code}
                                            disabled={
                                                step.assignment_type !== 'role'
                                            }
                                            onChange={(event) =>
                                                setBuilderSteps((current) =>
                                                    current.map(
                                                        (value, position) =>
                                                            position === index
                                                                ? {
                                                                      ...value,
                                                                      approver_role_code:
                                                                          event
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : value,
                                                    ),
                                                )
                                            }
                                            placeholder="Role code"
                                            className="rounded-md border-slate-300"
                                        />
                                        <div className="flex gap-2">
                                            <select
                                                name={`steps[${index}][execution_mode]`}
                                                value={step.execution_mode}
                                                onChange={(event) =>
                                                    setBuilderSteps((current) =>
                                                        current.map(
                                                            (
                                                                value,
                                                                position,
                                                            ) =>
                                                                position ===
                                                                index
                                                                    ? {
                                                                          ...value,
                                                                          execution_mode:
                                                                              event
                                                                                  .target
                                                                                  .value as BuilderStep['execution_mode'],
                                                                      }
                                                                    : value,
                                                        ),
                                                    )
                                                }
                                                className="min-w-0 flex-1 rounded-md border-slate-300"
                                            >
                                                <option value="sequential">
                                                    Sequential
                                                </option>
                                                <option value="parallel">
                                                    Parallel
                                                </option>
                                            </select>
                                            {builderSteps.length > 1 && (
                                                <button
                                                    type="button"
                                                    className="text-sm text-rose-700"
                                                    onClick={() =>
                                                        setBuilderSteps(
                                                            (current) =>
                                                                current.filter(
                                                                    (
                                                                        _,
                                                                        position,
                                                                    ) =>
                                                                        position !==
                                                                        index,
                                                                ),
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {builderSteps.length < 10 && (
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-indigo-700"
                                        onClick={() =>
                                            setBuilderSteps((current) => [
                                                ...current,
                                                newBuilderStep(),
                                            ])
                                        }
                                    >
                                        Add step
                                    </button>
                                )}
                            </div>
                            <div className="md:col-span-2">
                                <PrimaryButton>
                                    Save Workflow Version
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                )}
                <Card title="Workflow Definitions">
                    <DataTable
                        data={definitions}
                        keyExtractor={(item: Definition) => item.id}
                        columns={[
                            {
                                header: 'Workflow',
                                accessor: (item: Definition) =>
                                    `${item.name} v${item.version}`,
                            },
                            {
                                header: 'Subject',
                                accessor: (item: Definition) =>
                                    item.subject_type,
                            },
                            {
                                header: 'Range',
                                accessor: (item: Definition) =>
                                    `${item.amount_min ?? '-'} to ${item.amount_max ?? '-'}`,
                            },
                            {
                                header: 'Steps',
                                accessor: (item: Definition) =>
                                    item.steps
                                        .map(
                                            (step) =>
                                                `${step.step_no}: ${step.assignment_type}`,
                                        )
                                        .join(', '),
                            },
                            {
                                header: 'Active',
                                accessor: (item: Definition) =>
                                    item.is_active ? 'Yes' : 'No',
                            },
                        ]}
                    />
                </Card>
                <Card title="My Approval History">
                    <DataTable
                        data={history}
                        keyExtractor={(item: Approval) => item.id}
                        columns={[
                            {
                                header: 'Document',
                                accessor: (item: Approval) =>
                                    item.instance.subject_type,
                            },
                            {
                                header: 'Step',
                                accessor: (item: Approval) => item.step_no,
                            },
                            {
                                header: 'Status',
                                accessor: (item: Approval) => item.status,
                            },
                            {
                                header: 'Acted',
                                accessor: (item: Approval) =>
                                    item.acted_at ?? '-',
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
