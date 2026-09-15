import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useState } from 'react';

type Option = {
    id: string;
    code?: string;
    name: string;
    branch_id?: string;
    division_id?: string;
};

export type UserItem = {
    id: string;
    name: string;
    email: string;
    status: string;
    position?: string | null;
    phone?: string | null;
    person_id?: string | null;
    branch_id?: string | null;
    division_id?: string | null;
    department_id?: string | null;
    roles: Option[];
};

export default function UserDialog({
    open,
    user,
    roles,
    branches,
    divisions,
    departments,
    onClose,
}: {
    open: boolean;
    user: UserItem | null;
    roles: Option[];
    branches: Option[];
    divisions: Option[];
    departments: Option[];
    onClose: () => void;
}) {
    const [minimized, setMinimized] = useState(false);
    const isEdit = Boolean(user);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const defaultBranchId = user?.branch_id ?? branches[0]?.id ?? '';
    const validDivisionsForBranch = divisions.filter(
        (div) => div.branch_id === defaultBranchId,
    );
    const defaultDivisionId =
        user?.division_id ?? validDivisionsForBranch[0]?.id ?? '';
    const validDepartments = departments.filter(
        (dept) =>
            dept.branch_id === defaultBranchId &&
            dept.division_id === defaultDivisionId,
    );
    const defaultDepartmentId =
        user?.department_id ?? validDepartments[0]?.id ?? '';

    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        position: user?.position ?? '',
        phone: user?.phone ?? '',
        person_id: user?.person_id?.replace(/\D/g, '') ?? '',
        branch_id: defaultBranchId,
        division_id: defaultDivisionId,
        department_id: defaultDepartmentId,
        role_id:
            user?.roles[0]?.id ??
            roles.find((role) => role.code === 'member')?.id ??
            roles[0]?.id ??
            '',
    });

    useEffect(() => {
        if (open) {
            const bId = user?.branch_id ?? branches[0]?.id ?? '';
            const branchDivs = divisions.filter((div) => div.branch_id === bId);
            const dId = user?.division_id ?? branchDivs[0]?.id ?? '';
            const deptList = departments.filter(
                (dept) => dept.branch_id === bId && dept.division_id === dId,
            );
            const deptId = user?.department_id ?? deptList[0]?.id ?? '';

            form.setData({
                name: user?.name ?? '',
                email: user?.email ?? '',
                position: user?.position ?? '',
                phone: user?.phone ?? '',
                person_id: user?.person_id?.replace(/\D/g, '') ?? '',
                branch_id: bId,
                division_id: dId,
                department_id: deptId,
                role_id:
                    user?.roles[0]?.id ??
                    roles.find((role) => role.code === 'member')?.id ??
                    roles[0]?.id ??
                    '',
            });
            form.clearErrors();
            resetSubmissionKey();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user]);

    const handleBranchChange = (newBranchId: string) => {
        const branchDivs = divisions.filter(
            (div) => div.branch_id === newBranchId,
        );
        const newDivisionId = branchDivs[0]?.id ?? '';
        const deptList = departments.filter(
            (dept) =>
                dept.branch_id === newBranchId &&
                dept.division_id === newDivisionId,
        );
        const newDeptId = deptList[0]?.id ?? '';

        form.setData((prev) => ({
            ...prev,
            branch_id: newBranchId,
            division_id: newDivisionId,
            department_id: newDeptId,
        }));
    };

    const handleDivisionChange = (newDivisionId: string) => {
        const deptList = departments.filter(
            (dept) =>
                dept.branch_id === form.data.branch_id &&
                dept.division_id === newDivisionId,
        );
        const newDeptId = deptList[0]?.id ?? '';

        form.setData((prev) => ({
            ...prev,
            division_id: newDivisionId,
            department_id: newDeptId,
        }));
    };

    const isDirty = form.data.name !== '' || form.data.email !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (isEdit && user) {
            form.patch(route('users.update', user.id), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    resetSubmissionKey();
                    onClose();
                },
            });
        } else {
            form.post(route('users.invite'), {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    resetSubmissionKey();
                    onClose();
                },
            });
        }
    };

    const filteredDivisions = divisions.filter(
        (div) => div.branch_id === form.data.branch_id,
    );
    const filteredDepartments = departments.filter(
        (dept) =>
            dept.branch_id === form.data.branch_id &&
            dept.division_id === form.data.division_id,
    );

    if (!open) return null;

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={isEdit ? `Edit User: ${user?.name}` : 'Invite New User'}
                breadcrumb={
                    isEdit ? 'Admin / Users / Edit' : 'Admin / Users / Invite'
                }
                entityLabel={isEdit ? (user?.name ?? 'User') : 'New User'}
                draftLabel={form.data.name || 'Draft User'}
                storageKey={isEdit ? `user-edit-${user?.id}` : 'user-invite'}
                formId="user-dialog-form"
                processing={form.processing}
                saveDisabled={
                    form.processing || !form.data.name || !form.data.email
                }
                saveLabel={isEdit ? 'Save Changes' : 'Send Invitation'}
                status={
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {isEdit
                            ? 'Updating existing user'
                            : 'Inviting new organization member'}
                    </span>
                }
                errors={form.errors}
                onSubmit={handleSubmit}
            >
                {(expanded) => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <section>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Personal Information
                            </h4>
                            <div
                                className={`grid gap-4 ${
                                    expanded
                                        ? 'sm:grid-cols-3'
                                        : 'sm:grid-cols-2'
                                }`}
                            >
                                <EditorField
                                    id="user-name"
                                    label="Full Name"
                                    required
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(v) => form.setData('name', v)}
                                />

                                <EditorField
                                    id="user-email"
                                    label="Email Address"
                                    type="email"
                                    required
                                    value={form.data.email}
                                    error={form.errors.email}
                                    onChange={(v) => form.setData('email', v)}
                                />

                                <EditorField
                                    id="user-position"
                                    label="Position"
                                    value={form.data.position}
                                    error={form.errors.position}
                                    onChange={(v) =>
                                        form.setData('position', v)
                                    }
                                />

                                <EditorField
                                    id="user-phone"
                                    label="Phone Number"
                                    value={form.data.phone}
                                    error={form.errors.phone}
                                    onChange={(v) => form.setData('phone', v)}
                                />

                                <EditorField
                                    id="user-person-id"
                                    label="Person ID (13 digits)"
                                    value={form.data.person_id}
                                    error={form.errors.person_id}
                                    maxLength={13}
                                    onChange={(v) =>
                                        form.setData('person_id', v)
                                    }
                                />
                            </div>
                        </section>

                        <section className="border-t border-slate-200 dark:border-slate-800 pt-5">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                                Organization Assignment & Role
                            </h4>
                            <div
                                className={`grid gap-4 ${
                                    expanded
                                        ? 'sm:grid-cols-4'
                                        : 'sm:grid-cols-2'
                                }`}
                            >
                                <EditorReference
                                    id="user-branch-id"
                                    label="Branch"
                                    required
                                    value={form.data.branch_id}
                                    error={form.errors.branch_id}
                                    options={branches.map((b) => ({
                                        id: b.id,
                                        label: `${b.code ?? ''} - ${b.name}`,
                                    }))}
                                    onChange={handleBranchChange}
                                />

                                <EditorReference
                                    id="user-division-id"
                                    label="Division"
                                    required
                                    value={form.data.division_id}
                                    error={form.errors.division_id}
                                    options={filteredDivisions.map((d) => ({
                                        id: d.id,
                                        label: `${d.code ?? ''} - ${d.name}`,
                                    }))}
                                    onChange={handleDivisionChange}
                                />

                                <EditorReference
                                    id="user-department-id"
                                    label="Department"
                                    required
                                    value={form.data.department_id}
                                    error={form.errors.department_id}
                                    options={filteredDepartments.map(
                                        (dept) => ({
                                            id: dept.id,
                                            label: `${dept.code ?? ''} - ${dept.name}`,
                                        }),
                                    )}
                                    onChange={(v) =>
                                        form.setData('department_id', v)
                                    }
                                />

                                <EditorReference
                                    id="user-role-id"
                                    label="Role"
                                    required
                                    value={form.data.role_id}
                                    error={form.errors.role_id}
                                    options={roles.map((r) => ({
                                        id: r.id,
                                        label: `${r.name} (${r.code ?? ''})`,
                                    }))}
                                    onChange={(v) => form.setData('role_id', v)}
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
