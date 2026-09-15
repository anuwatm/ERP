import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import { EditorField } from '@/Components/UI/EditorFields';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';

type Permission = {
    id: string;
    code: string;
    module: string;
    action: string;
    description?: string | null;
};

export type RoleItem = {
    id: string;
    code: string;
    name: string;
    description?: string | null;
    is_system: boolean;
    permissions_count: number;
    users_count: number;
    permissions: Permission[];
};

export default function RolePermissionsDialog({
    open,
    role,
    permissions,
    onClose,
    canManage,
}: {
    canManage: boolean;
    open: boolean;
    role: RoleItem | null;
    permissions: Permission[];
    onClose: () => void;
}) {
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();
    const immutable = role?.code === 'owner' || !canManage;

    const groupedPermissions = useMemo(() => {
        return permissions.reduce<Record<string, Permission[]>>(
            (groups, permission) => {
                groups[permission.module] = groups[permission.module] ?? [];
                groups[permission.module].push(permission);
                return groups;
            },
            {},
        );
    }, [permissions]);

    const form = useForm({
        code: role?.code ?? '',
        name: role?.name ?? '',
        description: role?.description ?? '',
        permission_ids: role?.permissions.map((p) => p.id) ?? ([] as string[]),
    });

    const guard = useDiscardGuard(form.isDirty, form.processing);

    const togglePermission = (permissionId: string, checked: boolean) => {
        const current = new Set(form.data.permission_ids);
        if (checked) current.add(permissionId);
        else current.delete(permissionId);
        form.setData('permission_ids', Array.from(current));
    };

    const toggleModuleAll = (modulePerms: Permission[], selectAll: boolean) => {
        const current = new Set(form.data.permission_ids);
        modulePerms.forEach((p) => {
            if (selectAll) current.add(p.id);
            else current.delete(p.id);
        });
        form.setData('permission_ids', Array.from(current));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (immutable || form.processing) return;

        form.submit(
            role ? 'patch' : 'post',
            role ? route('roles.update', role.id) : route('roles.store'),
            {
                headers: headers(),
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    resetSubmissionKey();
                    onClose();
                },
            },
        );
    };

    if (!open) return null;

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={
                    role
                        ? `${immutable ? 'View' : 'Edit'} Role: ${role.name}`
                        : 'Create Role'
                }
                breadcrumb={
                    role ? 'Admin / Roles / Edit' : 'Admin / Roles / Create'
                }
                entityLabel={role?.name ?? 'Role'}
                draftLabel={`${form.data.permission_ids.length} permissions`}
                storageKey={`role-${role?.id ?? 'create'}`}
                formId="role-permissions-dialog-form"
                processing={form.processing}
                saveDisabled={form.processing || immutable}
                saveLabel={
                    immutable
                        ? 'Read Only'
                        : role
                          ? 'Save Changes'
                          : 'Create Role'
                }
                status={
                    <span className="text-xs text-gray-500 dark:text-gray-400">
                        {immutable
                            ? 'This role is read only'
                            : `${form.data.permission_ids.length} / ${permissions.length} permissions assigned`}
                    </span>
                }
                errors={form.errors}
                onSubmit={handleSubmit}
            >
                {(expanded) => (
                    <div className="space-y-6 p-4 sm:p-6">
                        {immutable && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                                <strong>Notice:</strong> This role is read only.
                                Owner roles are protected; editing other roles
                                requires roles.manage.
                            </div>
                        )}

                        <fieldset
                            disabled={immutable || form.processing}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    htmlFor="role-code"
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Role Code *
                                </label>
                                <input
                                    id="role-code"
                                    value={form.data.code}
                                    readOnly={!!role}
                                    required
                                    maxLength={50}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                    className="w-full rounded-md border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white read-only:opacity-60"
                                />
                                <p className="mt-1 text-xs text-slate-500">
                                    Lowercase letters, numbers and underscores.
                                    Code cannot change after creation.
                                </p>
                                {form.errors.code && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.code}
                                    </p>
                                )}
                            </div>
                            <EditorField
                                id="role-name"
                                label="Role Name"
                                required
                                maxLength={100}
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                            <div className="sm:col-span-2">
                                <EditorField
                                    id="role-description"
                                    label="Description"
                                    multiline
                                    maxLength={2000}
                                    value={form.data.description}
                                    error={form.errors.description}
                                    onChange={(value) =>
                                        form.setData('description', value)
                                    }
                                />
                            </div>
                        </fieldset>

                        {Object.entries(groupedPermissions).map(
                            ([module, items]) => {
                                const allSelected = items.every((item) =>
                                    form.data.permission_ids.includes(item.id),
                                );

                                return (
                                    <section
                                        key={module}
                                        className="border-b border-slate-100 dark:border-slate-800 pb-5 last:border-b-0"
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                {module} ({items.length})
                                            </h4>
                                            <button
                                                type="button"
                                                disabled={
                                                    immutable || form.processing
                                                }
                                                onClick={() =>
                                                    toggleModuleAll(
                                                        items,
                                                        !allSelected,
                                                    )
                                                }
                                                className="text-xs font-semibold text-indigo-600 hover:text-indigo-700 disabled:opacity-50 dark:text-indigo-400"
                                            >
                                                {allSelected
                                                    ? 'Deselect Module'
                                                    : 'Select All in Module'}
                                            </button>
                                        </div>
                                        <div
                                            className={`grid gap-3 ${
                                                expanded
                                                    ? 'sm:grid-cols-3'
                                                    : 'sm:grid-cols-2'
                                            }`}
                                        >
                                            {items.map((permission) => (
                                                <label
                                                    key={permission.id}
                                                    className="flex min-h-14 items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-850"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        disabled={
                                                            immutable ||
                                                            form.processing
                                                        }
                                                        checked={form.data.permission_ids.includes(
                                                            permission.id,
                                                        )}
                                                        onChange={(e) =>
                                                            togglePermission(
                                                                permission.id,
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                        className="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                                                            {permission.code}
                                                        </span>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {permission.description ??
                                                                permission.action}
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </section>
                                );
                            },
                        )}
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
