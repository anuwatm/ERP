import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import ProjectEditorDialog from './ProjectEditorDialog';
import ProjectMembersDialog from './ProjectMembersDialog';

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
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingProject, setEditingProject] = useState<Project | null>(null);
    const [managingMembersProject, setManagingMembersProject] =
        useState<Project | null>(null);

    return (
        <AuthenticatedLayout>
            <Head title="Projects" />

            <div className="space-y-6">
                <PageHeader
                    title="Projects"
                    description="Delivery projects from won deals or manual work."
                    actions={
                        <PrimaryButton
                            type="button"
                            onClick={() => setIsCreateOpen(true)}
                        >
                            + New Project
                        </PrimaryButton>
                    }
                />

                <ProjectEditorDialog
                    open={isCreateOpen || editingProject !== null}
                    project={editingProject}
                    customers={customers}
                    deals={deals}
                    owners={owners}
                    statuses={statuses}
                    canSeeAllProjects={canSeeAllProjects}
                    canReassignProjects={canReassignProjects}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingProject(null);
                    }}
                />

                {managingMembersProject && (
                    <ProjectMembersDialog
                        open={managingMembersProject !== null}
                        project={managingMembersProject}
                        owners={owners}
                        onClose={() => setManagingMembersProject(null)}
                    />
                )}

                <Card
                    title="Project List"
                    description={`${projects.length} delivery project(s) tracking`}
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
                                            {row.customer?.company_name ?? '-'}
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
                                accessor: (row) => (
                                    <div className="flex items-center gap-2">
                                        <div className="h-2 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                className="h-full bg-indigo-600 dark:bg-indigo-400"
                                                style={{
                                                    width: `${Math.min(100, row.progress_percent)}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="text-xs font-medium">
                                            {row.progress_percent}%
                                        </span>
                                    </div>
                                ),
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
                                header: 'Members',
                                accessor: (row) => (
                                    <span className="text-xs text-slate-600 dark:text-slate-400">
                                        {row.members?.length ?? 0} member(s)
                                    </span>
                                ),
                            },
                            {
                                header: 'Actions',
                                accessor: (row) => (
                                    <div className="flex flex-wrap gap-2">
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setEditingProject(row)
                                            }
                                        >
                                            Edit
                                        </SecondaryButton>
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setManagingMembersProject(row)
                                            }
                                        >
                                            Members
                                        </SecondaryButton>
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function statusVariant(status: string) {
    if (status === 'completed') return 'success';
    if (status === 'in_progress') return 'info';
    if (status === 'blocked' || status === 'cancelled') return 'danger';
    return 'neutral';
}
