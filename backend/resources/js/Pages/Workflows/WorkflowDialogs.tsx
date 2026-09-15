import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type User = { id: string; name: string; email: string };

export type BuilderStep = {
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

// -------------------------------------------------------------
// 1. Workflow Definition Dialog
// -------------------------------------------------------------
export function WorkflowDefinitionDialog({
    open,
    onClose,
    users,
}: {
    open: boolean;
    onClose: () => void;
    users: User[];
}) {
    const form = useForm({
        code: '',
        name: '',
        subject_type: 'expense',
        amount_min: '',
        amount_max: '',
        steps: [newBuilderStep()] as BuilderStep[],
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.code !== '' ||
        form.data.name !== '' ||
        form.data.steps.length > 1;

    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.code || !form.data.name) return;

        form.post(route('workflows.definitions.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    const setStep = (index: number, patch: Partial<BuilderStep>) => {
        form.setData(
            'steps',
            form.data.steps.map((s, i) =>
                i === index ? { ...s, ...patch } : s,
            ),
        );
    };

    const addStep = () => {
        if (form.data.steps.length < 10) {
            form.setData('steps', [...form.data.steps, newBuilderStep()]);
        }
    };

    const removeStep = (index: number) => {
        if (form.data.steps.length > 1) {
            form.setData(
                'steps',
                form.data.steps.filter((_, i) => i !== index),
            );
        }
    };

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
                title="Create Approval Workflow"
                breadcrumb="Workflows / Definitions"
                entityLabel="Workflow"
                draftLabel={form.data.name || form.data.code || 'New Workflow'}
                storageKey="workflow-def-editor-size"
                formId="workflow-def-form"
                processing={form.processing}
                saveDisabled={!form.data.code || !form.data.name}
                saveLabel="Save Workflow Version"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="wf-code"
                                label="Workflow Code"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                            />
                            <EditorField
                                id="wf-name"
                                label="Workflow Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <EditorReference
                                id="wf-subject-type"
                                label="Subject Document"
                                required
                                value={form.data.subject_type}
                                error={form.errors.subject_type}
                                options={[
                                    { id: 'expense', label: 'Expense' },
                                    {
                                        id: 'purchase_request',
                                        label: 'Purchase Request',
                                    },
                                    {
                                        id: 'purchase_order',
                                        label: 'Purchase Order',
                                    },
                                    {
                                        id: 'leave_request',
                                        label: 'Leave Request',
                                    },
                                ]}
                                onChange={(value) =>
                                    form.setData('subject_type', value)
                                }
                            />
                            <EditorField
                                id="wf-amount-min"
                                label="Min Amount (THB)"
                                type="number"
                                step="0.01"
                                value={form.data.amount_min}
                                error={form.errors.amount_min}
                                onChange={(value) =>
                                    form.setData('amount_min', value)
                                }
                            />
                            <EditorField
                                id="wf-amount-max"
                                label="Max Amount (THB)"
                                type="number"
                                step="0.01"
                                value={form.data.amount_max}
                                error={form.errors.amount_max}
                                onChange={(value) =>
                                    form.setData('amount_max', value)
                                }
                            />
                        </div>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    Approval Steps ({form.data.steps.length})
                                </h4>
                                {form.data.steps.length < 10 && (
                                    <button
                                        type="button"
                                        onClick={addStep}
                                        className="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        + Add Step
                                    </button>
                                )}
                            </div>
                            <div className="space-y-3">
                                {form.data.steps.map((step, index) => (
                                    <div
                                        key={index}
                                        className="rounded-lg border border-slate-200 bg-slate-50/50 p-3.5 dark:border-slate-700 dark:bg-slate-900/50"
                                    >
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                Step {index + 1}
                                            </span>
                                            {form.data.steps.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeStep(index)
                                                    }
                                                    className="text-xs font-medium text-rose-600 hover:text-rose-800"
                                                >
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-4">
                                            <EditorReference
                                                id={`step-${index}-assignment`}
                                                label="Assignment Type"
                                                required
                                                value={step.assignment_type}
                                                options={[
                                                    {
                                                        id: 'user',
                                                        label: 'Specific User',
                                                    },
                                                    {
                                                        id: 'manager',
                                                        label: 'Requester Manager',
                                                    },
                                                    {
                                                        id: 'role',
                                                        label: 'Role Code',
                                                    },
                                                ]}
                                                onChange={(val) =>
                                                    setStep(index, {
                                                        assignment_type:
                                                            val as BuilderStep['assignment_type'],
                                                    })
                                                }
                                            />
                                            {step.assignment_type ===
                                                'user' && (
                                                <EditorReference
                                                    id={`step-${index}-user`}
                                                    label="Approver User"
                                                    value={
                                                        step.approver_user_id
                                                    }
                                                    emptyLabel="Select user"
                                                    options={users.map((u) => ({
                                                        id: u.id,
                                                        label: u.name,
                                                    }))}
                                                    onChange={(val) =>
                                                        setStep(index, {
                                                            approver_user_id:
                                                                val,
                                                        })
                                                    }
                                                />
                                            )}
                                            {step.assignment_type ===
                                                'role' && (
                                                <EditorField
                                                    id={`step-${index}-role`}
                                                    label="Role Code"
                                                    value={
                                                        step.approver_role_code
                                                    }
                                                    onChange={(val) =>
                                                        setStep(index, {
                                                            approver_role_code:
                                                                val,
                                                        })
                                                    }
                                                />
                                            )}
                                            <EditorReference
                                                id={`step-${index}-mode`}
                                                label="Execution Mode"
                                                required
                                                value={step.execution_mode}
                                                options={[
                                                    {
                                                        id: 'sequential',
                                                        label: 'Sequential',
                                                    },
                                                    {
                                                        id: 'parallel',
                                                        label: 'Parallel',
                                                    },
                                                ]}
                                                onChange={(val) =>
                                                    setStep(index, {
                                                        execution_mode:
                                                            val as BuilderStep['execution_mode'],
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 2. Workflow Delegation Dialog
// -------------------------------------------------------------
export function WorkflowDelegationDialog({
    open,
    onClose,
    users,
}: {
    open: boolean;
    onClose: () => void;
    users: User[];
}) {
    const form = useForm({
        delegate_user_id: '',
        subject_type: '',
        starts_at: '',
        ends_at: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.delegate_user_id !== '' || form.data.starts_at !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.delegate_user_id ||
            !form.data.starts_at ||
            !form.data.ends_at
        )
            return;

        form.post(route('workflows.delegations.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

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
                title="Create Approval Delegation"
                breadcrumb="Workflows / Delegation"
                entityLabel="Delegation"
                draftLabel="Temporary Delegation"
                storageKey="workflow-delegation-editor-size"
                formId="workflow-delegation-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.delegate_user_id ||
                    !form.data.starts_at ||
                    !form.data.ends_at
                }
                saveLabel="Save Delegation"
                status="Temporary"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorReference
                            id="del-user-id"
                            label="Delegate To (Colleague)"
                            required
                            value={form.data.delegate_user_id}
                            error={form.errors.delegate_user_id}
                            emptyLabel="Select colleague"
                            options={users.map((u) => ({
                                id: u.id,
                                label: u.name,
                            }))}
                            onChange={(value) =>
                                form.setData('delegate_user_id', value)
                            }
                        />
                        <EditorReference
                            id="del-subject-type"
                            label="Document Scope"
                            value={form.data.subject_type}
                            error={form.errors.subject_type}
                            emptyLabel="All supported documents"
                            options={[
                                { id: 'leave_request', label: 'Leave Request' },
                                {
                                    id: 'purchase_request',
                                    label: 'Purchase Request',
                                },
                                {
                                    id: 'purchase_order',
                                    label: 'Purchase Order',
                                },
                                { id: 'expense', label: 'Expense' },
                            ]}
                            onChange={(value) =>
                                form.setData('subject_type', value)
                            }
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="del-starts-at"
                                label="Starts At"
                                type="datetime-local"
                                required
                                value={form.data.starts_at}
                                error={form.errors.starts_at}
                                onChange={(value) =>
                                    form.setData('starts_at', value)
                                }
                            />
                            <EditorField
                                id="del-ends-at"
                                label="Ends At"
                                type="datetime-local"
                                required
                                value={form.data.ends_at}
                                error={form.errors.ends_at}
                                onChange={(value) =>
                                    form.setData('ends_at', value)
                                }
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
