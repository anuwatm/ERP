import PrimaryButton from '@/Components/PrimaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Permission = {
    id: string;
    code: string;
    module: string;
    action: string;
    description?: string | null;
};
type Role = {
    id: string;
    code: string;
    name: string;
    permissions_count: number;
    users_count: number;
    permissions: Permission[];
};

export default function Roles({
    roles,
    permissions,
}: {
    roles: Role[];
    permissions: Permission[];
}) {
    const editableRoles = roles.filter((role) => role.code !== 'owner');
    const [selectedRoleId, setSelectedRoleId] = useState(
        editableRoles[0]?.id ?? roles[0]?.id ?? '',
    );
    const selectedRole =
        roles.find((role) => role.id === selectedRoleId) ?? roles[0];
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

    return (
        <AuthenticatedLayout>
            <Head title="Roles & Permissions" />

            <div className="space-y-6">
                <PageHeader
                    title="Roles & Permissions"
                    description="Role catalog and permission assignment matrix"
                />

                <Card
                    title="System Roles"
                    description={`${roles.length} roles configured`}
                >
                    <DataTable
                        data={roles}
                        keyExtractor={(role) => role.id}
                        columns={[
                            {
                                header: 'Role',
                                accessor: (role) => (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedRoleId(role.id)
                                        }
                                        className="text-left"
                                    >
                                        <Badge
                                            variant={
                                                role.code === 'owner'
                                                    ? 'purple'
                                                    : 'info'
                                            }
                                            dot
                                        >
                                            <span className="font-mono">
                                                {role.code}
                                            </span>
                                        </Badge>
                                        <span className="ml-3 font-semibold text-slate-900 dark:text-white">
                                            {role.name}
                                        </span>
                                    </button>
                                ),
                            },
                            {
                                header: 'Permissions',
                                accessor: (role) => (
                                    <span className="font-mono text-xs font-semibold text-slate-700">
                                        {role.permissions_count}
                                    </span>
                                ),
                            },
                            {
                                header: 'Users',
                                accessor: (role) => (
                                    <span className="text-sm text-slate-600">
                                        {role.users_count}
                                    </span>
                                ),
                            },
                            {
                                header: 'Policy',
                                accessor: (role) =>
                                    role.code === 'owner' ? (
                                        <Badge variant="warning">
                                            Immutable
                                        </Badge>
                                    ) : (
                                        <Badge variant="success">
                                            Editable
                                        </Badge>
                                    ),
                            },
                        ]}
                    />
                </Card>

                {selectedRole && (
                    <PermissionMatrix
                        role={selectedRole}
                        groupedPermissions={groupedPermissions}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function PermissionMatrix({
    role,
    groupedPermissions,
}: {
    role: Role;
    groupedPermissions: Record<string, Permission[]>;
}) {
    const form = useForm({
        permission_ids: role.permissions.map((permission) => permission.id),
    });
    const immutable = role.code === 'owner';

    const togglePermission = (permissionId: string, checked: boolean) => {
        const current = new Set(form.data.permission_ids);
        if (checked) current.add(permissionId);
        else current.delete(permissionId);
        form.setData('permission_ids', Array.from(current));
    };

    return (
        <Card
            title={`Permission Matrix: ${role.name}`}
            description={
                immutable
                    ? 'Owner permissions are immutable'
                    : 'Update role permission assignment'
            }
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!immutable)
                        form.patch(route('roles.permissions.update', role.id), {
                            preserveScroll: true,
                        });
                }}
                className="space-y-5"
            >
                {Object.entries(groupedPermissions).map(([module, items]) => (
                    <section
                        key={module}
                        className="border-b border-slate-100 pb-4 last:border-b-0"
                    >
                        <h4 className="mb-3 text-sm font-semibold uppercase text-slate-500">
                            {module}
                        </h4>
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            {items.map((permission) => (
                                <label
                                    key={permission.id}
                                    className="flex min-h-14 items-start gap-3 rounded-md border border-slate-200 p-3 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        disabled={immutable || form.processing}
                                        checked={form.data.permission_ids.includes(
                                            permission.id,
                                        )}
                                        onChange={(e) =>
                                            togglePermission(
                                                permission.id,
                                                e.target.checked,
                                            )
                                        }
                                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span>
                                        <span className="block font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                                            {permission.code}
                                        </span>
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            {permission.description ??
                                                permission.action}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </section>
                ))}
                <div className="flex justify-end">
                    <PrimaryButton
                        disabled={immutable || form.processing}
                        icon={
                            <svg
                                className="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        }
                    >
                        Save Permissions
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    );
}
