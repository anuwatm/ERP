import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { TaskChecklistDialog, TaskCommentsDialog } from './TaskDetailDialogs';
import TaskEditorDialog from './TaskEditorDialog';

type Project = {
    id: string;
    project_code: string;
    name: string;
    owner_id?: string | null;
};
type User = { id: string; name: string; email: string };
type Checklist = {
    id: string;
    task_id: string;
    title: string;
    is_done: boolean;
    sort_order: number;
};
type Comment = {
    id: string;
    body: string;
    user?: User | null;
    created_at?: string;
};
type Task = {
    id: string;
    project_id?: string | null;
    title: string;
    description?: string | null;
    status: string;
    priority: string;
    assignee_id?: string | null;
    due_date?: string | null;
    completed_at?: string | null;
    project?: Project | null;
    assignee?: User | null;
    checklists: Checklist[];
    comments: Comment[];
};

export default function Tasks({
    tasks,
    projects,
    assignees,
    statuses,
    priorities,
    canCreateTasks,
    canUpdateTasks,
}: {
    tasks: Task[];
    projects: Project[];
    assignees: User[];
    statuses: string[];
    priorities: string[];
    filters: Record<string, string | null>;
    canSeeAllTasks: boolean;
    canCreateTasks: boolean;
    canUpdateTasks: boolean;
    canCommentTasks: boolean;
}) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingTask, setEditingTask] = useState<Task | null>(null);
    const [checklistTask, setChecklistTask] = useState<Task | null>(null);
    const [commentTask, setCommentTask] = useState<Task | null>(null);

    const changeStatus = (task: Task, status: string) => {
        router.patch(
            route('tasks.update', task.id),
            {
                project_id: task.project_id ?? '',
                title: task.title,
                description: task.description ?? '',
                status,
                priority: task.priority,
                assignee_id: task.assignee_id ?? '',
                due_date: task.due_date?.slice(0, 10) ?? '',
            },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tasks" />

            <div className="space-y-6">
                <PageHeader
                    title="Tasks"
                    description="Sprint backlog, task assignment, checklist items, and discussion."
                    actions={
                        canCreateTasks && (
                            <PrimaryButton
                                type="button"
                                onClick={() => setIsCreateOpen(true)}
                            >
                                + New Task
                            </PrimaryButton>
                        )
                    }
                />

                <TaskEditorDialog
                    open={isCreateOpen || editingTask !== null}
                    task={editingTask}
                    projects={projects}
                    assignees={assignees}
                    statuses={statuses}
                    priorities={priorities}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingTask(null);
                    }}
                />

                {checklistTask && (
                    <TaskChecklistDialog
                        open={checklistTask !== null}
                        task={checklistTask}
                        onClose={() => setChecklistTask(null)}
                    />
                )}

                {commentTask && (
                    <TaskCommentsDialog
                        open={commentTask !== null}
                        task={commentTask}
                        onClose={() => setCommentTask(null)}
                    />
                )}

                <Card
                    title="Task List"
                    description={`${tasks.length} task(s) active`}
                >
                    <DataTable
                        data={tasks}
                        keyExtractor={(row) => row.id}
                        columns={[
                            {
                                header: 'Task',
                                accessor: (row) => (
                                    <div>
                                        <div className="font-semibold text-slate-900 dark:text-white">
                                            {row.title}
                                        </div>
                                        {row.project && (
                                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                                {row.project.project_code} ·{' '}
                                                {row.project.name}
                                            </div>
                                        )}
                                    </div>
                                ),
                            },
                            {
                                header: 'Priority',
                                accessor: (row) => (
                                    <Badge
                                        variant={priorityVariant(row.priority)}
                                        size="sm"
                                    >
                                        {row.priority}
                                    </Badge>
                                ),
                            },
                            {
                                header: 'Status',
                                accessor: (row) => (
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={statusVariant(row.status)}
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                        {canUpdateTasks && (
                                            <select
                                                aria-label="Change status"
                                                className="rounded border-slate-300 py-0.5 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                                value={row.status}
                                                onChange={(e) =>
                                                    changeStatus(
                                                        row,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {statuses.map((st) => (
                                                    <option key={st} value={st}>
                                                        {st}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                ),
                            },
                            {
                                header: 'Assignee',
                                accessor: (row) =>
                                    row.assignee?.name ?? (
                                        <span className="text-slate-400">
                                            Unassigned
                                        </span>
                                    ),
                            },
                            {
                                header: 'Due Date',
                                accessor: (row) =>
                                    row.due_date?.slice(0, 10) ?? '-',
                            },
                            {
                                header: 'Checklist',
                                accessor: (row) => {
                                    const done = row.checklists.filter(
                                        (c) => c.is_done,
                                    ).length;
                                    const total = row.checklists.length;
                                    return (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setChecklistTask(row)
                                            }
                                        >
                                            Checklist ({done}/{total})
                                        </SecondaryButton>
                                    );
                                },
                            },
                            {
                                header: 'Comments',
                                accessor: (row) => (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => setCommentTask(row)}
                                    >
                                        Chat ({row.comments.length})
                                    </SecondaryButton>
                                ),
                            },
                            {
                                header: 'Actions',
                                accessor: (row) =>
                                    canUpdateTasks ? (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => setEditingTask(row)}
                                        >
                                            Edit
                                        </SecondaryButton>
                                    ) : null,
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function priorityVariant(priority: string) {
    if (priority === 'urgent') return 'danger';
    if (priority === 'high') return 'warning';
    if (priority === 'normal') return 'info';
    return 'neutral';
}

function statusVariant(status: string) {
    if (status === 'done') return 'success';
    if (status === 'in_progress') return 'info';
    if (status === 'blocked') return 'danger';
    return 'neutral';
}
