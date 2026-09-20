import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import {
    isBranchDirty,
    isDivisionDirty,
    isDepartmentDirty,
} from './orgStructureDirty';

export type Branch = {
    id: string;
    code: string;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_head_office: boolean;
    status: string;
};

export type Division = {
    id: string;
    branch_id: string;
    code: string;
    name: string;
    status: string;
};

export type Department = {
    id: string;
    branch_id: string;
    division_id: string;
    code: string;
    name: string;
    status: string;
};

// --- Branch Dialog ---
export function BranchDialog({
    open,
    branch,
    onClose,
}: {
    open: boolean;
    branch: Branch | null;
    onClose: () => void;
}) {
    const [minimized, setMinimized] = useState(false);
    const isEdit = Boolean(branch);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const defaultData = useMemo(
        () => ({
            name: branch?.name ?? '',
            phone: branch?.phone ?? '',
            address: branch?.address ?? '',
            is_head_office: branch?.is_head_office ?? false,
        }),
        [branch?.name, branch?.phone, branch?.address, branch?.is_head_office],
    );

    const form = useForm({ ...defaultData });

    useEffect(() => {
        if (open) {
            form.setData(defaultData);
            form.clearErrors();
            resetSubmissionKey();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, branch?.id]);

    const isDirty = open && isBranchDirty(form.data, defaultData);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = () => {
        form.setData(defaultData);
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && branch) {
            form.patch(route('settings.branches.update', branch.id), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        } else {
            form.post(route('settings.branches.store'), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        }
    };

    if (!open) return null;

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title={
                    isEdit
                        ? `Edit Branch: ${branch?.name}`
                        : 'Create New Branch'
                }
                breadcrumb={
                    isEdit
                        ? 'Settings / Org / Edit Branch'
                        : 'Settings / Org / New Branch'
                }
                entityLabel={isEdit ? (branch?.name ?? 'Branch') : 'New Branch'}
                draftLabel={form.data.name || 'Draft Branch'}
                storageKey={
                    isEdit ? `branch-edit-${branch?.id}` : 'branch-create'
                }
                formId="branch-dialog-form"
                processing={form.processing}
                saveDisabled={form.processing || !form.data.name}
                saveLabel={isEdit ? 'Save Changes' : 'Create Branch'}
                status={
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {isEdit
                            ? `Code: ${branch?.code}`
                            : 'Branch code auto-generated'}
                    </span>
                }
                errors={form.errors}
                onSubmit={handleSubmit}
            >
                {(expanded) => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <section>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Branch Details
                            </h4>
                            <div
                                className={`grid gap-4 ${
                                    expanded ? 'sm:grid-cols-2' : 'grid-cols-1'
                                }`}
                            >
                                <EditorField
                                    id="branch-name"
                                    label="Branch Name"
                                    required
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(v) => form.setData('name', v)}
                                />

                                <EditorField
                                    id="branch-phone"
                                    label="Phone Number"
                                    value={form.data.phone}
                                    error={form.errors.phone}
                                    onChange={(v) => form.setData('phone', v)}
                                />

                                <div
                                    className={expanded ? 'sm:col-span-2' : ''}
                                >
                                    <EditorField
                                        id="branch-address"
                                        label="Address"
                                        value={form.data.address}
                                        error={form.errors.address}
                                        onChange={(v) =>
                                            form.setData('address', v)
                                        }
                                    />
                                </div>

                                <div className="pt-2">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            checked={form.data.is_head_office}
                                            onChange={(e) =>
                                                form.setData(
                                                    'is_head_office',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        <span>Set as Head Office</span>
                                    </label>
                                </div>
                            </div>
                        </section>
                    </div>
                )}
            </CreateEditDialog>

            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
        </>
    );
}

// --- Division Dialog ---
export function DivisionDialog({
    open,
    division,
    branches,
    onClose,
}: {
    open: boolean;
    division: Division | null;
    branches: Branch[];
    onClose: () => void;
}) {
    const [minimized, setMinimized] = useState(false);
    const isEdit = Boolean(division);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();
    const activeBranches = useMemo(
        () => branches.filter((b) => b.status === 'active'),
        [branches],
    );

    const defaultData = useMemo(
        () => ({
            branch_id: division?.branch_id ?? activeBranches[0]?.id ?? '',
            name: division?.name ?? '',
        }),
        [division?.branch_id, division?.name, activeBranches],
    );

    const form = useForm({ ...defaultData });

    useEffect(() => {
        if (open) {
            form.setData(defaultData);
            form.clearErrors();
            resetSubmissionKey();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, division?.id]);

    const isDirty = open && isDivisionDirty(form.data, defaultData);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = () => {
        form.setData(defaultData);
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && division) {
            form.patch(route('settings.divisions.update', division.id), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        } else {
            form.post(route('settings.divisions.store'), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        }
    };

    if (!open) return null;

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title={
                    isEdit
                        ? `Edit Division: ${division?.name}`
                        : 'Create New Division'
                }
                breadcrumb={
                    isEdit
                        ? 'Settings / Org / Edit Division'
                        : 'Settings / Org / New Division'
                }
                entityLabel={
                    isEdit ? (division?.name ?? 'Division') : 'New Division'
                }
                draftLabel={form.data.name || 'Draft Division'}
                storageKey={isEdit ? `div-edit-${division?.id}` : 'div-create'}
                formId="division-dialog-form"
                processing={form.processing}
                saveDisabled={form.processing || !form.data.name}
                saveLabel={isEdit ? 'Save Changes' : 'Create Division'}
                status={
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {isEdit
                            ? `Code: ${division?.code}`
                            : 'Division code auto-generated'}
                    </span>
                }
                errors={form.errors}
                onSubmit={handleSubmit}
            >
                {(expanded) => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <section>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Division Information
                            </h4>
                            <div
                                className={`grid gap-4 ${
                                    expanded ? 'sm:grid-cols-2' : 'grid-cols-1'
                                }`}
                            >
                                <EditorReference
                                    id="division-branch-id"
                                    label="Belongs to Branch"
                                    required
                                    value={form.data.branch_id}
                                    error={form.errors.branch_id}
                                    options={activeBranches.map((b) => ({
                                        id: b.id,
                                        label: `${b.code} - ${b.name}`,
                                    }))}
                                    onChange={(v) =>
                                        form.setData('branch_id', v)
                                    }
                                />

                                <EditorField
                                    id="division-name"
                                    label="Division Name"
                                    required
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(v) => form.setData('name', v)}
                                />
                            </div>
                        </section>
                    </div>
                )}
            </CreateEditDialog>

            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
        </>
    );
}

// --- Department Dialog ---
export function DepartmentDialog({
    open,
    department,
    branches,
    divisions,
    onClose,
}: {
    open: boolean;
    department: Department | null;
    branches: Branch[];
    divisions: Division[];
    onClose: () => void;
}) {
    const [minimized, setMinimized] = useState(false);
    const isEdit = Boolean(department);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();
    const activeBranches = useMemo(
        () => branches.filter((b) => b.status === 'active'),
        [branches],
    );
    const activeDivisions = useMemo(
        () => divisions.filter((d) => d.status === 'active'),
        [divisions],
    );

    const defaultData = useMemo(() => {
        const bId = department?.branch_id ?? activeBranches[0]?.id ?? '';
        const bDivs = activeDivisions.filter((d) => d.branch_id === bId);
        const dId = department?.division_id ?? bDivs[0]?.id ?? '';
        return {
            branch_id: bId,
            division_id: dId,
            name: department?.name ?? '',
        };
    }, [
        department?.branch_id,
        department?.division_id,
        department?.name,
        activeBranches,
        activeDivisions,
    ]);

    const form = useForm({ ...defaultData });

    useEffect(() => {
        if (open) {
            form.setData(defaultData);
            form.clearErrors();
            resetSubmissionKey();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, department?.id]);

    const handleBranchChange = (newBranchId: string) => {
        const branchDivs = activeDivisions.filter(
            (d) => d.branch_id === newBranchId,
        );
        form.setData((prev) => ({
            ...prev,
            branch_id: newBranchId,
            division_id: branchDivs[0]?.id ?? '',
        }));
    };

    const isDirty = open && isDepartmentDirty(form.data, defaultData);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = () => {
        form.setData(defaultData);
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit && department) {
            form.patch(route('settings.departments.update', department.id), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        } else {
            form.post(route('settings.departments.store'), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
            });
        }
    };

    const currentBranchDivisions = activeDivisions.filter(
        (d) => d.branch_id === form.data.branch_id,
    );

    if (!open) return null;

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title={
                    isEdit
                        ? `Edit Department: ${department?.name}`
                        : 'Create New Department'
                }
                breadcrumb={
                    isEdit
                        ? 'Settings / Org / Edit Department'
                        : 'Settings / Org / New Department'
                }
                entityLabel={
                    isEdit
                        ? (department?.name ?? 'Department')
                        : 'New Department'
                }
                draftLabel={form.data.name || 'Draft Department'}
                storageKey={
                    isEdit ? `dept-edit-${department?.id}` : 'dept-create'
                }
                formId="department-dialog-form"
                processing={form.processing}
                saveDisabled={form.processing || !form.data.name}
                saveLabel={isEdit ? 'Save Changes' : 'Create Department'}
                status={
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {isEdit
                            ? `Code: ${department?.code}`
                            : 'Department code auto-generated'}
                    </span>
                }
                errors={form.errors}
                onSubmit={handleSubmit}
            >
                {(expanded) => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <section>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Department Hierarchy & Title
                            </h4>
                            <div
                                className={`grid gap-4 ${
                                    expanded ? 'sm:grid-cols-3' : 'grid-cols-1'
                                }`}
                            >
                                <EditorReference
                                    id="dept-branch-id"
                                    label="Branch"
                                    required
                                    value={form.data.branch_id}
                                    error={form.errors.branch_id}
                                    options={activeBranches.map((b) => ({
                                        id: b.id,
                                        label: `${b.code} - ${b.name}`,
                                    }))}
                                    onChange={handleBranchChange}
                                />

                                <EditorReference
                                    id="dept-division-id"
                                    label="Division"
                                    required
                                    value={form.data.division_id}
                                    error={form.errors.division_id}
                                    options={currentBranchDivisions.map(
                                        (d) => ({
                                            id: d.id,
                                            label: `${d.code} - ${d.name}`,
                                        }),
                                    )}
                                    onChange={(v) =>
                                        form.setData('division_id', v)
                                    }
                                />

                                <EditorField
                                    id="dept-name"
                                    label="Department Name"
                                    required
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(v) => form.setData('name', v)}
                                />
                            </div>
                        </section>
                    </div>
                )}
            </CreateEditDialog>

            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
        </>
    );
}
