import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type Project = {
    id: string;
    project_code: string;
    name: string;
};
type User = { id: string; name: string; email: string };

type Task = {
    id: string;
    project_id?: string | null;
    title: string;
    description?: string | null;
    status: string;
    priority: string;
    assignee_id?: string | null;
    due_date?: string | null;
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

export default function TaskEditorDialog({
    open,
    task,
    projects,
    assignees,
    statuses,
    priorities,
    onClose,
}: {
    open: boolean;
    task: Task | null;
    projects: Project[];
    assignees: User[];
    statuses: string[];
    priorities: string[];
    onClose: () => void;
}) {
    const form = useForm<TaskForm>(emptyTask);
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    useEffect(() => {
        if (!open) return;
        if (task) {
            form.setData({
                project_id: task.project_id ?? '',
                title: task.title ?? '',
                description: task.description ?? '',
                status: task.status ?? 'todo',
                priority: task.priority ?? 'normal',
                assignee_id: task.assignee_id ?? '',
                due_date: task.due_date?.slice(0, 10) ?? '',
            });
        } else {
            form.setData(emptyTask);
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, task?.id]);

    const isDirty =
        form.data.title !== (task ? task.title : '') ||
        form.data.project_id !== (task ? (task.project_id ?? '') : '') ||
        form.data.assignee_id !== (task ? (task.assignee_id ?? '') : '') ||
        form.data.status !== (task ? task.status : 'todo') ||
        form.data.priority !== (task ? task.priority : 'normal') ||
        form.data.description !== (task ? (task.description ?? '') : '');

    const guard = useDiscardGuard(isDirty, form.processing);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.title) return;

        const options = {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                onClose();
            },
        };

        if (task) {
            form.patch(route('tasks.update', task.id), options);
        } else {
            form.post(route('tasks.store'), options);
        }
    }

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={task ? 'Edit Task' : 'Create Task'}
                breadcrumb="Delivery / Tasks"
                entityLabel="Task"
                draftLabel={form.data.title || 'New Task'}
                storageKey="task-editor-size"
                formId="task-editor-form"
                processing={form.processing}
                saveDisabled={!form.data.title}
                saveLabel={task ? 'Save Task' : 'Create Task'}
                status={task ? task.status : 'To Do'}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorField
                            id="task-title"
                            label="Task Title"
                            value={form.data.title}
                            onChange={(val: string) =>
                                form.setData('title', val)
                            }
                            error={form.errors.title}
                            required
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="task-project-id"
                                label="Project"
                                emptyLabel="No Project linked"
                                value={form.data.project_id}
                                options={projects.map((p) => ({
                                    id: p.id,
                                    label: `${p.project_code} - ${p.name}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('project_id', val)
                                }
                                error={form.errors.project_id}
                            />

                            <EditorReference
                                id="task-assignee-id"
                                label="Assignee"
                                emptyLabel="Unassigned"
                                value={form.data.assignee_id}
                                options={assignees.map((u) => ({
                                    id: u.id,
                                    label: `${u.name} (${u.email})`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('assignee_id', val)
                                }
                                error={form.errors.assignee_id}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <EditorChoice
                                id="task-priority"
                                label="Priority"
                                value={form.data.priority}
                                options={priorities}
                                onChange={(val: string) =>
                                    form.setData('priority', val)
                                }
                                error={form.errors.priority}
                            />

                            <EditorChoice
                                id="task-status"
                                label="Status"
                                value={form.data.status}
                                options={statuses}
                                onChange={(val: string) =>
                                    form.setData('status', val)
                                }
                                error={form.errors.status}
                            />

                            <EditorField
                                id="task-due-date"
                                label="Due Date"
                                type="date"
                                value={form.data.due_date}
                                onChange={(val: string) =>
                                    form.setData('due_date', val)
                                }
                                error={form.errors.due_date}
                            />
                        </div>

                        <EditorField
                            id="task-description"
                            label="Description"
                            multiline
                            value={form.data.description}
                            onChange={(val: string) =>
                                form.setData('description', val)
                            }
                            error={form.errors.description}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
