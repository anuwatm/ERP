import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Role = {
    id: string;
    code: string;
    name: string;
    permissions_count: number;
    users_count: number;
};

export default function Roles({ roles }: { roles: Role[] }) {
    return (
        <AuthenticatedLayout>
            <Head title="Roles & Permissions" />

            <div className="space-y-6">
                <PageHeader
                    title="Role Management"
                    description="View system roles, permissions count, and assigned users."
                />

                <Card
                    title="System Roles Catalog"
                    description="Role permissions union strategy"
                >
                    <DataTable
                        data={roles}
                        keyExtractor={(role) => role.id}
                        columns={[
                            {
                                header: 'Role Code',
                                accessor: (role) => (
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
                                ),
                            },
                            {
                                header: 'Role Name',
                                accessor: (role) => (
                                    <span className="font-semibold text-slate-900">
                                        {role.name}
                                    </span>
                                ),
                            },
                            {
                                header: 'Permissions Granted',
                                accessor: (role) => (
                                    <span className="font-mono text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                        {role.permissions_count} permissions
                                    </span>
                                ),
                            },
                            {
                                header: 'Assigned Users',
                                accessor: (role) => (
                                    <span className="text-sm font-medium text-slate-600">
                                        {role.users_count} active users
                                    </span>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
