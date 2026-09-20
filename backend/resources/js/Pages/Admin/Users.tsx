import Badge, { BadgeVariant } from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import UserDialog, { UserItem } from './UserDialog';

type Option = {
    id: string;
    code?: string;
    name: string;
    branch_id?: string;
    division_id?: string;
};

const inputClass =
    'w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150';

export default function Users({
    users,
    roles,
    branches,
    divisions,
    departments,
    filters,
    canInviteUsers,
    canEditUsers,
    canDisableUsers,
}: {
    users: UserItem[];
    roles: Option[];
    branches: Option[];
    divisions: Option[];
    departments: Option[];
    filters?: {
        user_id?: string;
        status?: string;
        role_id?: string;
        expired?: string;
    };
    canInviteUsers: boolean;
    canEditUsers: boolean;
    canDisableUsers: boolean;
}) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState(filters?.status ?? 'all');
    const [roleFilter, setRoleFilter] = useState(filters?.role_id ?? 'all');
    const [branchFilter, setBranchFilter] = useState('all');

    // Dialog state
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserItem | null>(null);

    const openInviteDialog = () => {
        setEditingUser(null);
        setDialogOpen(true);
    };

    const openEditDialog = (user: UserItem) => {
        setEditingUser(user);
        setDialogOpen(true);
    };

    const filteredUsers = users.filter((user) => {
        const haystack =
            `${user.name} ${user.email} ${user.position ?? ''}`.toLowerCase();
        const matchesText = haystack.includes(query.toLowerCase());
        const matchesStatus = status === 'all' || user.status === status;
        const matchesRole =
            roleFilter === 'all' ||
            user.roles.some((role) => role.id === roleFilter);
        const matchesBranch =
            branchFilter === 'all' || user.branch_id === branchFilter;

        return matchesText && matchesStatus && matchesRole && matchesBranch;
    });

    const getStatusVariant = (value: string): BadgeVariant => {
        if (value === 'active') return 'success';
        if (value === 'invited') return 'warning';
        if (value === 'inactive') return 'danger';
        return 'neutral';
    };

    return (
        <AuthenticatedLayout>
            <Head title="User Management" />

            <div className="space-y-6">
                <PageHeader
                    title="User Management"
                    description="Users, organization assignment, and role access"
                    actions={
                        canInviteUsers ? (
                            <PrimaryButton
                                onClick={openInviteDialog}
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
                                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                                        />
                                    </svg>
                                }
                            >
                                Invite User
                            </PrimaryButton>
                        ) : undefined
                    }
                />
                {(filters?.user_id ||
                    filters?.status ||
                    filters?.role_id ||
                    filters?.expired) && (
                    <p className="text-sm text-slate-600 dark:text-slate-300">
                        Dashboard filter active.{' '}
                        <a
                            href={route('users.index')}
                            className="font-semibold text-indigo-700 underline dark:text-indigo-300"
                        >
                            Clear filter
                        </a>
                    </p>
                )}

                <Card
                    title="Users Directory"
                    description={`${filteredUsers.length} visible records`}
                    action={
                        <div className="grid min-w-[560px] gap-2 md:grid-cols-4">
                            <TextInput
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search"
                            />
                            <select
                                className={inputClass}
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                            >
                                <option value="all">All status</option>
                                <option value="active">Active</option>
                                <option value="invited">Invited</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <select
                                className={inputClass}
                                value={roleFilter}
                                onChange={(e) => setRoleFilter(e.target.value)}
                            >
                                <option value="all">All roles</option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                className={inputClass}
                                value={branchFilter}
                                onChange={(e) =>
                                    setBranchFilter(e.target.value)
                                }
                            >
                                <option value="all">All branches</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    }
                >
                    <DataTable
                        data={filteredUsers}
                        keyExtractor={(user) => user.id}
                        columns={[
                            {
                                header: 'Member',
                                accessor: (user) =>
                                    canEditUsers ? (
                                        <button
                                            type="button"
                                            onClick={() => openEditDialog(user)}
                                            className="text-left group"
                                        >
                                            <span className="block font-semibold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400 transition-colors">
                                                {user.name}
                                            </span>
                                            <span className="text-xs text-slate-500">
                                                {user.position || 'No position'}
                                            </span>
                                        </button>
                                    ) : (
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {user.name}
                                        </span>
                                    ),
                            },
                            {
                                header: 'Email',
                                accessor: (user) => (
                                    <span className="font-mono text-xs">
                                        {user.email}
                                    </span>
                                ),
                            },
                            {
                                header: 'Status',
                                accessor: (user) => (
                                    <Badge
                                        variant={getStatusVariant(user.status)}
                                        dot
                                    >
                                        {user.status}
                                    </Badge>
                                ),
                            },
                            {
                                header: 'Person ID',
                                accessor: (user) => (
                                    <span className="font-mono text-xs text-slate-500">
                                        {user.person_id || '-'}
                                    </span>
                                ),
                            },
                            {
                                header: 'Roles',
                                accessor: (user) => (
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((role) => (
                                            <Badge
                                                key={role.id}
                                                variant="purple"
                                                size="sm"
                                            >
                                                {role.code || role.name}
                                            </Badge>
                                        ))}
                                    </div>
                                ),
                            },
                            {
                                header: 'Actions',
                                accessor: (user) => (
                                    <div className="flex items-center gap-2">
                                        {canEditUsers && (
                                            <SecondaryButton
                                                onClick={() =>
                                                    openEditDialog(user)
                                                }
                                            >
                                                Edit
                                            </SecondaryButton>
                                        )}
                                        {canDisableUsers &&
                                            (user.status !== 'inactive' ? (
                                                <SecondaryButton
                                                    icon={
                                                        <svg
                                                            className="h-3.5 w-3.5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth="2"
                                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                                            />
                                                        </svg>
                                                    }
                                                    onClick={() =>
                                                        router.patch(
                                                            route(
                                                                'users.disable',
                                                                user.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Disable
                                                </SecondaryButton>
                                            ) : (
                                                <SecondaryButton
                                                    icon={
                                                        <svg
                                                            className="h-3.5 w-3.5"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                            />
                                                        </svg>
                                                    }
                                                    onClick={() =>
                                                        router.patch(
                                                            route(
                                                                'users.enable',
                                                                user.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Enable
                                                </SecondaryButton>
                                            ))}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>

            {(canInviteUsers || canEditUsers) && (
                <UserDialog
                    open={dialogOpen}
                    user={editingUser}
                    roles={roles}
                    branches={branches}
                    divisions={divisions}
                    departments={departments}
                    onClose={() => {
                        setDialogOpen(false);
                        setEditingUser(null);
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
