import PrimaryButton from '@/Components/PrimaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import RolePermissionsDialog, { RoleItem } from './RolePermissionsDialog';

type Permission = {
    id: string;
    code: string;
    module: string;
    action: string;
    description?: string | null;
};

export default function Roles({
    roles,
    permissions,
    canManage,
    filters,
}: {
    canManage: boolean;
    roles: RoleItem[];
    permissions: Permission[];
    filters?: { role_id?: string };
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedRole, setSelectedRole] = useState<RoleItem | null>(null);

    const openPermissionsDialog = (role: RoleItem) => {
        setSelectedRole(role);
        setDialogOpen(true);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Roles & Permissions" />

            <div className="space-y-6">
                <PageHeader
                    title="Roles & Permissions"
                    description="Role catalog and permission assignment matrix"
                />
                {filters?.role_id && (
                    <p className="text-sm text-slate-600 dark:text-slate-300">
                        Dashboard filter active.{' '}
                        <Link
                            href={route('roles.index')}
                            className="font-semibold text-indigo-700 underline dark:text-indigo-300"
                        >
                            Clear filter
                        </Link>
                    </p>
                )}

                <Card
                    title="Roles"
                    description={`${roles.length} roles configured`}
                    action={
                        canManage && (
                            <PrimaryButton
                                disabled={dialogOpen}
                                onClick={() => {
                                    setSelectedRole(null);
                                    setDialogOpen(true);
                                }}
                            >
                                Create Role
                            </PrimaryButton>
                        )
                    }
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
                                        disabled={dialogOpen}
                                        onClick={() =>
                                            openPermissionsDialog(role)
                                        }
                                        className="text-left group flex items-center"
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
                                        <span className="ml-3 font-semibold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 transition-colors">
                                            {role.name}
                                        </span>
                                    </button>
                                ),
                            },
                            {
                                header: 'Permissions',
                                accessor: (role) => (
                                    <span className="font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        {role.permissions_count}
                                    </span>
                                ),
                            },
                            {
                                header: 'Users',
                                accessor: (role) => (
                                    <span className="text-sm text-slate-600 dark:text-slate-400">
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
                            {
                                header: 'Actions',
                                accessor: (role) => (
                                    <SecondaryButton
                                        disabled={dialogOpen}
                                        onClick={() =>
                                            openPermissionsDialog(role)
                                        }
                                    >
                                        {role.code === 'owner' || !canManage
                                            ? 'View Role'
                                            : 'Edit Role'}
                                    </SecondaryButton>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>

            {dialogOpen && (
                <RolePermissionsDialog
                    key={selectedRole?.id ?? 'create'}
                    canManage={canManage}
                    open={dialogOpen}
                    role={selectedRole}
                    permissions={permissions}
                    onClose={() => {
                        setDialogOpen(false);
                        setSelectedRole(null);
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
