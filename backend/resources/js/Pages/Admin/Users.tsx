import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge, { BadgeVariant } from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Option = {
    id: string;
    code?: string;
    name: string;
    branch_id?: string;
    division_id?: string;
};

type User = {
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

const inputClass =
    'w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150';

export default function Users({
    users,
    roles,
    branches,
    divisions,
    departments,
}: {
    users: User[];
    roles: Option[];
    branches: Option[];
    divisions: Option[];
    departments: Option[];
}) {
    const [selectedUserId, setSelectedUserId] = useState(users[0]?.id ?? '');
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const [branchFilter, setBranchFilter] = useState('all');

    const selectedUser =
        users.find((user) => user.id === selectedUserId) ?? users[0];
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

    const { data, setData, post, processing, reset } = useForm({
        name: '',
        email: '',
        position: '',
        person_id: '',
        branch_id: branches[0]?.id ?? '',
        division_id: divisions[0]?.id ?? '',
        department_id: departments[0]?.id ?? '',
        role_id:
            roles.find((role) => role.code === 'member')?.id ??
            roles[0]?.id ??
            '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('users.invite'), {
            onSuccess: () => reset('name', 'email', 'position', 'person_id'),
        });
    };

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
                />

                <Card
                    title="Invite New User"
                    description="Assign branch, division, department, and role"
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 md:grid-cols-4"
                    >
                        <TextInput
                            placeholder="Full name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <TextInput
                            type="email"
                            placeholder="Email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <TextInput
                            placeholder="Position"
                            value={data.position}
                            onChange={(e) =>
                                setData('position', e.target.value)
                            }
                        />
                        <TextInput
                            placeholder="Person ID"
                            value={data.person_id}
                            onChange={(e) =>
                                setData('person_id', e.target.value)
                            }
                        />
                        <HierarchySelects
                            data={data}
                            setData={setData}
                            branches={branches}
                            divisions={divisions}
                            departments={departments}
                        />
                        <select
                            className={inputClass}
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                        >
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </select>
                        <div className="md:col-span-4 flex justify-end">
                            <PrimaryButton
                                disabled={processing}
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
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                        />
                                    </svg>
                                }
                            >
                                Send Invitation
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>

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
                                accessor: (user) => (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedUserId(user.id)
                                        }
                                        className="text-left"
                                    >
                                        <span className="block font-semibold text-slate-900">
                                            {user.name}
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            {user.position || 'No position'}
                                        </span>
                                    </button>
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
                                    <div className="flex gap-2">
                                        {user.status !== 'inactive' ? (
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
                                        )}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>

                {selectedUser && (
                    <EditUserCard
                        user={selectedUser}
                        roles={roles}
                        branches={branches}
                        divisions={divisions}
                        departments={departments}
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function HierarchySelects({
    data,
    setData,
    branches,
    divisions,
    departments,
}: {
    data: { branch_id: string; division_id: string; department_id: string };
    setData: (
        key: 'branch_id' | 'division_id' | 'department_id',
        value: string,
    ) => void;
    branches: Option[];
    divisions: Option[];
    departments: Option[];
}) {
    const filteredDivisions = divisions.filter(
        (division) => division.branch_id === data.branch_id,
    );
    const filteredDepartments = departments.filter(
        (department) =>
            department.branch_id === data.branch_id &&
            department.division_id === data.division_id,
    );
    return (
        <>
            <select
                className={inputClass}
                value={data.branch_id}
                onChange={(e) => setData('branch_id', e.target.value)}
            >
                {branches.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.code} - {item.name}
                    </option>
                ))}
            </select>
            <select
                className={inputClass}
                value={data.division_id}
                onChange={(e) => setData('division_id', e.target.value)}
            >
                {filteredDivisions.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.code} - {item.name}
                    </option>
                ))}
            </select>
            <select
                className={inputClass}
                value={data.department_id}
                onChange={(e) => setData('department_id', e.target.value)}
            >
                {filteredDepartments.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.code} - {item.name}
                    </option>
                ))}
            </select>
        </>
    );
}

function EditUserCard({
    user,
    roles,
    branches,
    divisions,
    departments,
}: {
    user: User;
    roles: Option[];
    branches: Option[];
    divisions: Option[];
    departments: Option[];
}) {
    const form = useForm({
        name: user.name,
        email: user.email,
        position: user.position ?? '',
        phone: user.phone ?? '',
        person_id: user.person_id?.replace(/\D/g, '') ?? '',
        branch_id: user.branch_id ?? branches[0]?.id ?? '',
        division_id: user.division_id ?? divisions[0]?.id ?? '',
        department_id: user.department_id ?? departments[0]?.id ?? '',
        role_id: user.roles[0]?.id ?? roles[0]?.id ?? '',
    });

    return (
        <Card
            title={`Edit ${user.name}`}
            description="Profile, hierarchy, and role"
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.patch(route('users.update', user.id), {
                        preserveScroll: true,
                    });
                }}
                className="grid gap-4 md:grid-cols-4"
            >
                <TextInput
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <TextInput
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    required
                />
                <TextInput
                    value={form.data.position}
                    onChange={(e) => form.setData('position', e.target.value)}
                    placeholder="Position"
                />
                <TextInput
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                    placeholder="Phone"
                />
                <TextInput
                    value={form.data.person_id}
                    onChange={(e) => form.setData('person_id', e.target.value)}
                    placeholder="Person ID"
                />
                <HierarchySelects
                    data={form.data}
                    setData={form.setData}
                    branches={branches}
                    divisions={divisions}
                    departments={departments}
                />
                <select
                    className={inputClass}
                    value={form.data.role_id}
                    onChange={(e) => form.setData('role_id', e.target.value)}
                >
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </select>
                <div className="md:col-span-4 flex justify-end">
                    <PrimaryButton disabled={form.processing}>
                        Save User
                    </PrimaryButton>
                </div>
            </form>
        </Card>
    );
}
