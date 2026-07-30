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
type TaskForm = {
    project_id: string;
    title: string;
    description: string;
    status: string;
    priority: string;
    assignee_id: string;
    due_date: string;
};

const emptyTask: TaskForm = {
    project_id: '',
    title: '',
    description: '',
    status: 'todo',
    priority: 'normal',
    assignee_id: '',
    due_date: '',
};

export default function Tasks({
    tasks,
    projects,
    assignees,
    statuses,
    priorities,
    canCreateTasks,
    canUpdateTasks,
    canCommentTasks,
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
    const [editingTask, setEditingTask] = useState<Task | null>(null);
    const [checklistTask, setChecklistTask] = useState<Task | null>(null);
    const [commentTask, setCommentTask] = useState<Task | null>(null);
    const taskForm = useForm<TaskForm>(emptyTask);
    const checklistForm = useForm({ title: '' });
    const commentForm = useForm({ body: '' });

    const submitTask = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                taskForm.setData(emptyTask);
                setEditingTask(null);
            },
        };

        if (editingTask) {
            taskForm.patch(route('tasks.update', editingTask.id), options);
        } else {
            taskForm.post(route('tasks.store'), options);
        }
    };

    const editTask = (task: Task) => {
        setEditingTask(task);
        taskForm.setData({
            project_id: task.project_id ?? '',
            title: task.title ?? '',
            description: task.description ?? '',
            status: task.status ?? 'todo',
            priority: task.priority ?? 'normal',
            assignee_id: task.assignee_id ?? '',
            due_date: task.due_date?.slice(0, 10) ?? '',
        });
    };

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

    const submitChecklist = (event: FormEvent) => {
        event.preventDefault();
        if (!checklistTask) return;
        checklistForm.post(route('tasks.checklists.store', checklistTask.id), {
            preserveScroll: true,
            onSuccess: () => checklistForm.setData('title', ''),
        });
    };

    const submitComment = (event: FormEvent) => {
        event.preventDefault();
        if (!commentTask) return;
        commentForm.post(route('tasks.comments.store', commentTask.id), {
            preserveScroll: true,
            onSuccess: () => commentForm.setData('body', ''),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tasks" />
            <div className="space-y-6">
                <PageHeader
                    title="Tasks"
                    description="Delivery and internal work queue."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
                    <Card
                        title="Task List"
                        description="Project and internal tasks"
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
                                            <div className="text-xs text-slate-500 dark:text-slate-300">
                                                {row.project
                                                    ? `${row.project.project_code} ${row.project.name}`
                                                    : 'Internal'}
                                            </div>
                                            {row.description && (
                                                <div className="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-300">
                                                    {row.description}
                                                </div>
                                            )}
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
                                    header: 'Priority',
                                    accessor: (row) => (
                                        <Badge
                                            variant={priorityVariant(
                                                row.priority,
                                            )}
                                            size="sm"
                                        >
                                            {row.priority}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Assignee',
                                    accessor: (row) =>
                                        row.assignee?.name ?? '-',
                                },
                                {
                                    header: 'Due',
                                    accessor: (row) =>
                                        row.due_date?.slice(0, 10) ?? '-',
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            {canUpdateTasks && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        editTask(row)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                            )}
                                            {canUpdateTasks &&
                                                row.status !== 'done' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            changeStatus(
                                                                row,
                                                                'done',
                                                            )
                                                        }
                                                    >
                                                        Done
                                                    </SecondaryButton>
                                                )}
                                            {canUpdateTasks && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        setChecklistTask(row)
                                                    }
                                                >
                                                    Checklist
                                                </SecondaryButton>
                                            )}
                                            {canCommentTasks && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        setCommentTask(row)
                                                    }
                                                >
                                                    Comment
                                                </SecondaryButton>
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />

                        <div className="mt-6 space-y-3">
                            {tasks.map((task) => (
                                <div
                                    key={task.id}
                                    className="rounded-md border border-slate-200 bg-white/5 p-3 dark:border-slate-800"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                            {task.title}
                                        </div>
                                        <div className="text-xs text-slate-500 dark:text-slate-300">
                                            {
                                                task.checklists.filter(
                                                    (item) => item.is_done,
                                                ).length
                                            }
                                            /{task.checklists.length} checklist
                                        </div>
                                    </div>
                                    <div className="mt-2 grid gap-3 lg:grid-cols-2">
                                        <div className="space-y-1">
                                            {task.checklists.length === 0 && (
                                                <div className="text-sm text-slate-500 dark:text-slate-400">
                                                    No checklist
                                                </div>
                                            )}
                                            {task.checklists.map((item) => (
                                                <label
                                                    key={item.id}
                                                    className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={item.is_done}
                                                        onChange={(event) =>
                                                            router.patch(
                                                                route(
                                                                    'task-checklists.update',
                                                                    item.id,
                                                                ),
                                                                {
                                                                    is_done:
                                                                        event
                                                                            .target
                                                                            .checked,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        disabled={
                                                            !canUpdateTasks
                                                        }
                                                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                    {item.title}
                                                </label>
                                            ))}
                                        </div>
                                        <div className="space-y-1">
                                            {task.comments.length === 0 && (
                                                <div className="text-sm text-slate-500 dark:text-slate-400">
                                                    No comment
                                                </div>
                                            )}
                                            {task.comments
                                                .slice(0, 3)
                                                .map((comment) => (
                                                    <div
                                                        key={comment.id}
                                                        className="rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-900"
                                                    >
                                                        <span className="font-semibold text-slate-800 dark:text-white">
                                                            {comment.user
                                                                ?.name ??
                                                                'User'}
                                                            :{' '}
                                                        </span>
                                                        <span className="text-slate-600 dark:text-slate-300">
                                                            {comment.body}
                                                        </span>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    <div className="space-y-6">
                        <Card
                            title={editingTask ? 'Edit Task' : 'Create Task'}
                            description="Leave project empty for internal task"
                        >
                            <form onSubmit={submitTask} className="space-y-3">
                                <SelectField
                                    label="Project"
                                    value={taskForm.data.project_id}
                                    onChange={(value) =>
                                        taskForm.setData('project_id', value)
                                    }
                                    options={projects.map(
                                        (project) => project.id,
                                    )}
                                    labels={Object.fromEntries(
                                        projects.map((project) => [
                                            project.id,
                                            `${project.project_code} ${project.name}`,
                                        ]),
                                    )}
                                />
                                <Field
                                    label="Title"
                                    value={taskForm.data.title}
                                    error={taskForm.errors.title}
                                    onChange={(value) =>
                                        taskForm.setData('title', value)
                                    }
                                    required
                                />
                                <Field
                                    label="Description"
                                    value={taskForm.data.description}
                                    error={taskForm.errors.description}
                                    onChange={(value) =>
                                        taskForm.setData('description', value)
                                    }
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <SelectField
                                        label="Status"
                                        value={taskForm.data.status}
                                        onChange={(value) =>
                                            taskForm.setData('status', value)
                                        }
                                        options={statuses}
                                    />
                                    <SelectField
                                        label="Priority"
                                        value={taskForm.data.priority}
                                        onChange={(value) =>
                                            taskForm.setData('priority', value)
                                        }
                                        options={priorities}
                                    />
                                </div>
                                <SelectField
                                    label="Assignee"
                                    value={taskForm.data.assignee_id}
                                    onChange={(value) =>
                                        taskForm.setData('assignee_id', value)
                                    }
                                    options={assignees.map((user) => user.id)}
                                    labels={Object.fromEntries(
                                        assignees.map((user) => [
                                            user.id,
                                            user.name,
                                        ]),
                                    )}
                                />
                                <Field
                                    label="Due Date"
                                    type="date"
                                    value={taskForm.data.due_date}
                                    error={taskForm.errors.due_date}
                                    onChange={(value) =>
                                        taskForm.setData('due_date', value)
                                    }
                                />
                                <div className="flex justify-end gap-2">
                                    {editingTask && (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => {
                                                setEditingTask(null);
                                                taskForm.setData(emptyTask);
                                            }}
                                        >
                                            Cancel
                                        </SecondaryButton>
                                    )}
                                    <PrimaryButton
                                        disabled={
                                            (!canCreateTasks && !editingTask) ||
                                            taskForm.processing
                                        }
                                    >
                                        Save Task
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        {checklistTask && (
                            <Card
                                title="Add Checklist"
                                description={checklistTask.title}
                            >
                                <form
                                    onSubmit={submitChecklist}
                                    className="space-y-3"
                                >
                                    <Field
                                        label="Checklist"
                                        value={checklistForm.data.title}
                                        error={checklistForm.errors.title}
                                        onChange={(value) =>
                                            checklistForm.setData(
                                                'title',
                                                value,
                                            )
                                        }
                                        required
                                    />
                                    <div className="flex justify-end gap-2">
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setChecklistTask(null)
                                            }
                                        >
                                            Close
                                        </SecondaryButton>
                                        <PrimaryButton
                                            disabled={checklistForm.processing}
                                        >
                                            Add
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </Card>
                        )}

                        {commentTask && (
                            <Card
                                title="Add Comment"
                                description={commentTask.title}
                            >
                                <form
                                    onSubmit={submitComment}
                                    className="space-y-3"
                                >
                                    <Field
                                        label="Comment"
                                        value={commentForm.data.body}
                                        error={commentForm.errors.body}
                                        onChange={(value) =>
                                            commentForm.setData('body', value)
                                        }
                                        required
                                    />
                                    <div className="flex justify-end gap-2">
                                        <SecondaryButton
                                            type="button"
                                            onClick={() => setCommentTask(null)}
                                        >
                                            Close
                                        </SecondaryButton>
                                        <PrimaryButton
                                            disabled={commentForm.processing}
                                        >
                                            Add
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </Card>
                        )}
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
                className="mb-1 text-xs font-semibold uppercase text-slate-700 dark:text-slate-200"
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
    if (status === 'done') return 'success';
    if (status === 'blocked') return 'warning';
    if (status === 'cancelled') return 'danger';
    if (status === 'in_progress') return 'info';
    return 'neutral';
}

function priorityVariant(priority: string) {
    if (priority === 'urgent') return 'danger';
    if (priority === 'high') return 'warning';
    if (priority === 'low') return 'neutral';
    return 'info';
}
