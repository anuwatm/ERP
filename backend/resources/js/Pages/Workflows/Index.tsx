import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    WorkflowDefinitionDialog,
    WorkflowDelegationDialog,
} from './WorkflowDialogs';

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
    const [definitionOpen, setDefinitionOpen] = useState(false);
    const [delegationOpen, setDelegationOpen] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title="Approval Workflows" />
            <div className="space-y-6">
                <PageHeader
                    title="Approval Workflows"
                    description="Versioned, snapshot-based approvals with delegation and separation of duties."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.manage && (
                                <PrimaryButton
                                    onClick={() => setDefinitionOpen(true)}
                                >
                                    New Workflow
                                </PrimaryButton>
                            )}
                            {can.approve && (
                                <button
                                    type="button"
                                    onClick={() => setDelegationOpen(true)}
                                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    New Delegation
                                </button>
                            )}
                        </div>
                    }
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
                                                        ? 'text-emerald-700 font-medium'
                                                        : action === 'rejected'
                                                          ? 'text-rose-700 font-medium'
                                                          : 'text-amber-700 font-medium'
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
                    <Card
                        title="Temporary Delegations"
                        description="Active and scheduled approval delegation rules."
                    >
                        <div className="space-y-4">
                            <div>
                                <PrimaryButton
                                    onClick={() => setDelegationOpen(true)}
                                >
                                    New Delegation
                                </PrimaryButton>
                            </div>
                            <div className="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                {delegations.length === 0 && (
                                    <p className="text-slate-500">
                                        No active delegations configured.
                                    </p>
                                )}
                                {delegations.map((d) => (
                                    <div
                                        key={d.id}
                                        className="flex items-center justify-between rounded-lg border border-slate-100 p-3 dark:border-slate-800"
                                    >
                                        <span className="font-medium text-slate-800 dark:text-slate-100">
                                            {d.delegate_user.name}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {d.starts_at} to {d.ends_at}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}
                <Card
                    title="Workflow Definitions"
                    description="Versioned routing definitions for financial transactions and leave applications."
                >
                    <div className="space-y-4">
                        {can.manage && (
                            <div>
                                <PrimaryButton
                                    onClick={() => setDefinitionOpen(true)}
                                >
                                    Create New Workflow
                                </PrimaryButton>
                            </div>
                        )}
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
                    </div>
                </Card>
            </div>

            <WorkflowDefinitionDialog
                open={definitionOpen}
                onClose={() => setDefinitionOpen(false)}
                users={users}
            />
            <WorkflowDelegationDialog
                open={delegationOpen}
                onClose={() => setDelegationOpen(false)}
                users={users}
            />
        </AuthenticatedLayout>
    );
}
