import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

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
    title: string;
    checklists: Checklist[];
    comments: Comment[];
};

/* =========================================================================
   1. Task Checklist Dialog
   ========================================================================= */
export function TaskChecklistDialog({
    open,
    task,
    onClose,
}: {
    open: boolean;
    task: Task;
    onClose: () => void;
}) {
    const form = useForm({ title: '' });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();
    const isDirty = form.data.title !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.title.trim()) return;

        form.post(route('tasks.checklists.store', task.id), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetKey();
                form.reset('title');
            },
        });
    }

    const toggleItem = (checklistId: string) => {
        router.patch(
            route('task-checklists.update', checklistId),
            {},
            { preserveScroll: true },
        );
    };

    const completedCount = task.checklists.filter((c) => c.is_done).length;
    const totalCount = task.checklists.length;

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
                title="Task Checklists"
                breadcrumb="Delivery / Tasks"
                entityLabel="Checklist"
                draftLabel={task.title}
                storageKey="task-checklist-dialog-size"
                formId="task-checklist-form"
                processing={form.processing}
                saveDisabled={!form.data.title.trim()}
                saveLabel="Add Item"
                status={`${completedCount}/${totalCount} completed`}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="flex gap-2">
                            <TextInput
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                placeholder="Add new checklist item..."
                                className="w-full"
                            />
                            <PrimaryButton
                                disabled={
                                    form.processing || !form.data.title.trim()
                                }
                            >
                                Add
                            </PrimaryButton>
                        </div>

                        <div className="space-y-2">
                            {task.checklists.length > 0 ? (
                                task.checklists.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/50"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={item.is_done}
                                            onChange={() => toggleItem(item.id)}
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                                        />
                                        <span
                                            className={`text-sm ${
                                                item.is_done
                                                    ? 'text-slate-400 line-through dark:text-slate-500'
                                                    : 'text-slate-800 dark:text-slate-200'
                                            }`}
                                        >
                                            {item.title}
                                        </span>
                                    </label>
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-800">
                                    No checklist items yet. Add subtasks above.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

/* =========================================================================
   2. Task Comments Dialog
   ========================================================================= */
export function TaskCommentsDialog({
    open,
    task,
    onClose,
}: {
    open: boolean;
    task: Task;
    onClose: () => void;
}) {
    const form = useForm({ body: '' });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();
    const isDirty = form.data.body !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.body.trim()) return;

        form.post(route('tasks.comments.store', task.id), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetKey();
                form.reset('body');
            },
        });
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
                title="Task Activity & Comments"
                breadcrumb="Delivery / Tasks"
                entityLabel="Discussion"
                draftLabel={task.title}
                storageKey="task-comments-dialog-size"
                formId="task-comments-form"
                processing={form.processing}
                saveDisabled={!form.data.body.trim()}
                saveLabel="Post Comment"
                status={`${task.comments.length} comments`}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="space-y-3">
                            <textarea
                                rows={3}
                                value={form.data.body}
                                onChange={(e) =>
                                    form.setData('body', e.target.value)
                                }
                                placeholder="Write an update or reply..."
                                className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            <div className="flex justify-end">
                                <PrimaryButton
                                    disabled={
                                        form.processing ||
                                        !form.data.body.trim()
                                    }
                                >
                                    Post Comment
                                </PrimaryButton>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Discussion History
                            </h4>
                            {task.comments.length > 0 ? (
                                <div className="space-y-3">
                                    {task.comments.map((comment) => (
                                        <div
                                            key={comment.id}
                                            className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                                        >
                                            <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {comment.user?.name ??
                                                        'Anonymous'}
                                                </span>
                                                <span>
                                                    {comment.created_at?.slice(
                                                        0,
                                                        16,
                                                    ) ?? ''}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                                                {comment.body}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-800">
                                    No comments yet. Start the conversation
                                    above.
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
