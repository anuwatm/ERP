import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useState } from 'react';

type Category = { id: string; name: string; status?: boolean };
type Policy = { id: string; name: string };

// -------------------------------------------------------------
// 1. New Document Dialog
// -------------------------------------------------------------
export function DocumentDialog({
    open,
    onClose,
    categories,
}: {
    open: boolean;
    onClose: () => void;
    categories: Category[];
}) {
    const form = useForm({
        title: '',
        category_id: '',
        sensitivity: 'org_internal',
        expires_at: '',
        renewal_alert_days: '',
        file: null as File | null,
        change_note: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.title !== '' || form.data.file !== null;
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.title || !form.data.file) return;

        form.post(route('documents.store'), {
            headers: headers(),
            forceFormData: true,
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
                title="Upload Document"
                breadcrumb="Documents / Repository"
                entityLabel="Document"
                draftLabel={form.data.title || 'New Document'}
                storageKey="document-upload-editor-size"
                formId="document-upload-form"
                processing={form.processing}
                saveDisabled={!form.data.title || !form.data.file}
                saveLabel="Upload Document"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorField
                            id="doc-title"
                            label="Document Title"
                            required
                            value={form.data.title}
                            error={form.errors.title}
                            onChange={(value) => form.setData('title', value)}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="doc-category-id"
                                label="Category"
                                value={form.data.category_id}
                                error={form.errors.category_id}
                                emptyLabel="Uncategorized"
                                options={categories.map((c) => ({
                                    id: c.id,
                                    label: c.name,
                                }))}
                                onChange={(value) =>
                                    form.setData('category_id', value)
                                }
                            />
                            <EditorReference
                                id="doc-sensitivity"
                                label="Sensitivity Classification"
                                required
                                value={form.data.sensitivity}
                                error={form.errors.sensitivity}
                                options={[
                                    {
                                        id: 'org_internal',
                                        label: 'Organization internal',
                                    },
                                    {
                                        id: 'department_restricted',
                                        label: 'Department restricted',
                                    },
                                    {
                                        id: 'finance_confidential',
                                        label: 'Finance confidential',
                                    },
                                    {
                                        id: 'hr_confidential',
                                        label: 'HR confidential',
                                    },
                                    {
                                        id: 'executive_confidential',
                                        label: 'Executive confidential',
                                    },
                                ]}
                                onChange={(value) =>
                                    form.setData('sensitivity', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="doc-expires-at"
                                label="Expiry Date"
                                type="date"
                                value={form.data.expires_at}
                                error={form.errors.expires_at}
                                onChange={(value) =>
                                    form.setData('expires_at', value)
                                }
                            />
                            <EditorField
                                id="doc-renewal-alert-days"
                                label="Renewal Alert (Days Before)"
                                type="number"
                                value={form.data.renewal_alert_days}
                                error={form.errors.renewal_alert_days}
                                onChange={(value) =>
                                    form.setData('renewal_alert_days', value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="doc-file-input"
                                className="block text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Primary File Attachment *
                            </label>
                            <input
                                id="doc-file-input"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                    form.setData(
                                        'file',
                                        e.target.files
                                            ? e.target.files[0]
                                            : null,
                                    )
                                }
                                className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                            {form.errors.file && (
                                <p className="text-xs text-rose-600">
                                    {form.errors.file}
                                </p>
                            )}
                        </div>
                        <EditorField
                            id="doc-change-note"
                            label="Initial Version Note"
                            value={form.data.change_note}
                            error={form.errors.change_note}
                            onChange={(value) =>
                                form.setData('change_note', value)
                            }
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 2. Document Version Dialog
// -------------------------------------------------------------
export function DocumentVersionDialog({
    open,
    onClose,
    documentId,
    documentTitle,
}: {
    open: boolean;
    onClose: () => void;
    documentId: string;
    documentTitle: string;
}) {
    const form = useForm({
        file: null as File | null,
        change_note: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.file !== null || form.data.change_note !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.file || !documentId) return;

        form.post(route('documents.versions.store', documentId), {
            headers: headers(),
            forceFormData: true,
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
                title="Add New Document Version"
                breadcrumb="Documents / Versions"
                entityLabel="Document Version"
                draftLabel={documentTitle || 'New Version'}
                storageKey="document-version-editor-size"
                formId="document-version-form"
                processing={form.processing}
                saveDisabled={!form.data.file}
                saveLabel="Upload Version"
                status="Scanning / Clean"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <span className="text-slate-500">Document:</span>{' '}
                            <span className="font-semibold text-slate-800 dark:text-slate-100">
                                {documentTitle}
                            </span>
                        </div>
                        <div className="space-y-2">
                            <label
                                htmlFor="version-file-input"
                                className="block text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                New File Version *
                            </label>
                            <input
                                id="version-file-input"
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                    form.setData(
                                        'file',
                                        e.target.files
                                            ? e.target.files[0]
                                            : null,
                                    )
                                }
                                className="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                            {form.errors.file && (
                                <p className="text-xs text-rose-600">
                                    {form.errors.file}
                                </p>
                            )}
                        </div>
                        <EditorField
                            id="version-change-note"
                            label="Change Note / Reason for Update"
                            multiline
                            value={form.data.change_note}
                            error={form.errors.change_note}
                            onChange={(value) =>
                                form.setData('change_note', value)
                            }
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 3. Document Link Dialog
// -------------------------------------------------------------
export function DocumentLinkDialog({
    open,
    onClose,
    documentId,
    documentTitle,
}: {
    open: boolean;
    onClose: () => void;
    documentId: string;
    documentTitle: string;
}) {
    const form = useForm({
        linkable_type: 'customer',
        linkable_id: '',
        role: 'supporting',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.linkable_id !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.linkable_id || !documentId) return;

        form.post(route('documents.links.store', documentId), {
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
                title="Link Document to Entity"
                breadcrumb="Documents / Relations"
                entityLabel="Document Link"
                draftLabel={documentTitle || 'New Link'}
                storageKey="document-link-editor-size"
                formId="document-link-form"
                processing={form.processing}
                saveDisabled={!form.data.linkable_id}
                saveLabel="Create Link"
                status="Linked"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <span className="text-slate-500">Document:</span>{' '}
                            <span className="font-semibold text-slate-800 dark:text-slate-100">
                                {documentTitle}
                            </span>
                        </div>
                        <EditorReference
                            id="link-type"
                            label="Target Entity Type"
                            required
                            value={form.data.linkable_type}
                            error={form.errors.linkable_type}
                            options={[
                                { id: 'customer', label: 'Customer' },
                                { id: 'supplier', label: 'Supplier' },
                                { id: 'project', label: 'Project' },
                                { id: 'task', label: 'Task' },
                                { id: 'fixed_asset', label: 'Fixed Asset' },
                            ]}
                            onChange={(value) =>
                                form.setData('linkable_type', value)
                            }
                        />
                        <EditorField
                            id="link-id"
                            label="Target Entity UUID"
                            required
                            value={form.data.linkable_id}
                            error={form.errors.linkable_id}
                            onChange={(value) =>
                                form.setData('linkable_id', value)
                            }
                        />
                        <EditorReference
                            id="link-role"
                            label="Document Role"
                            required
                            value={form.data.role}
                            error={form.errors.role}
                            options={[
                                { id: 'supporting', label: 'Supporting' },
                                { id: 'primary', label: 'Primary' },
                                { id: 'generated', label: 'Generated' },
                            ]}
                            onChange={(value) => form.setData('role', value)}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 4. Document Category Dialog
// -------------------------------------------------------------
export function DocumentCategoryDialog({
    open,
    onClose,
    retentionPolicies,
}: {
    open: boolean;
    onClose: () => void;
    retentionPolicies: Policy[];
}) {
    const form = useForm({
        code: '',
        name: '',
        retention_policy_id: '',
        default_sensitivity: 'org_internal',
        expiry_tracking_enabled: false,
        default_renewal_alert_days: '30',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.code !== '' || form.data.name !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.code || !form.data.name) return;

        form.transform((data) => ({
            ...data,
            expiry_tracking_enabled: data.expiry_tracking_enabled ? '1' : '0',
        }));
        form.post(route('documents.categories.store'), {
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
                title="Create Document Category"
                breadcrumb="Documents / Governance / Categories"
                entityLabel="Category"
                draftLabel={form.data.name || form.data.code || 'New Category'}
                storageKey="document-category-editor-size"
                formId="document-category-form"
                processing={form.processing}
                saveDisabled={!form.data.code || !form.data.name}
                saveLabel="Save Category"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="cat-code"
                                label="Category Code"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                            />
                            <EditorField
                                id="cat-name"
                                label="Category Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="cat-retention-id"
                                label="Retention Policy"
                                value={form.data.retention_policy_id}
                                error={form.errors.retention_policy_id}
                                emptyLabel="No retention policy"
                                options={retentionPolicies.map((p) => ({
                                    id: p.id,
                                    label: p.name,
                                }))}
                                onChange={(value) =>
                                    form.setData('retention_policy_id', value)
                                }
                            />
                            <EditorReference
                                id="cat-sensitivity"
                                label="Default Sensitivity"
                                required
                                value={form.data.default_sensitivity}
                                error={form.errors.default_sensitivity}
                                options={[
                                    {
                                        id: 'org_internal',
                                        label: 'Organization internal',
                                    },
                                    {
                                        id: 'finance_confidential',
                                        label: 'Finance confidential',
                                    },
                                    {
                                        id: 'hr_confidential',
                                        label: 'HR confidential',
                                    },
                                    {
                                        id: 'executive_confidential',
                                        label: 'Executive confidential',
                                    },
                                ]}
                                onChange={(value) =>
                                    form.setData('default_sensitivity', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="cat-renewal-days"
                                label="Default Renewal Alert (Days)"
                                type="number"
                                value={form.data.default_renewal_alert_days}
                                error={form.errors.default_renewal_alert_days}
                                onChange={(value) =>
                                    form.setData(
                                        'default_renewal_alert_days',
                                        value,
                                    )
                                }
                            />
                            <div className="flex items-center pt-6">
                                <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={
                                            form.data.expiry_tracking_enabled
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'expiry_tracking_enabled',
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                    <span>Track Document Expiry</span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 5. Retention Policy Dialog
// -------------------------------------------------------------
export function RetentionPolicyDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        code: '',
        name: '',
        minimum_retention_days: '0',
        effective_from: '',
        legal_hold_required: false,
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.code !== '' || form.data.name !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.code ||
            !form.data.name ||
            !form.data.effective_from
        )
            return;

        form.transform((data) => ({
            ...data,
            legal_hold_required: data.legal_hold_required ? '1' : '0',
        }));
        form.post(route('documents.retention-policies.store'), {
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
                title="Create Retention Policy"
                breadcrumb="Documents / Governance / Retention"
                entityLabel="Retention Policy"
                draftLabel={form.data.name || form.data.code || 'New Policy'}
                storageKey="retention-policy-editor-size"
                formId="retention-policy-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.code ||
                    !form.data.name ||
                    !form.data.effective_from
                }
                saveLabel="Save Policy"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="ret-code"
                                label="Policy Code"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                            />
                            <EditorField
                                id="ret-name"
                                label="Policy Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="ret-days"
                                label="Minimum Retention (Days)"
                                type="number"
                                min={0}
                                required
                                value={form.data.minimum_retention_days}
                                error={form.errors.minimum_retention_days}
                                onChange={(value) =>
                                    form.setData(
                                        'minimum_retention_days',
                                        value,
                                    )
                                }
                            />
                            <EditorField
                                id="ret-effective"
                                label="Effective From Date"
                                type="date"
                                required
                                value={form.data.effective_from}
                                error={form.errors.effective_from}
                                onChange={(value) =>
                                    form.setData('effective_from', value)
                                }
                            />
                        </div>
                        <div>
                            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input
                                    type="checkbox"
                                    checked={form.data.legal_hold_required}
                                    onChange={(e) =>
                                        form.setData(
                                            'legal_hold_required',
                                            e.target.checked,
                                        )
                                    }
                                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <span>
                                    Legal Hold Required (prevents purge even
                                    after retention period)
                                </span>
                            </label>
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
