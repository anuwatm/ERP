import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Badge, { BadgeVariant } from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

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
    person_id?: string | null;
    roles: Option[];
};

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

    const getStatusVariant = (status: string): BadgeVariant => {
        switch (status) {
            case 'active':
                return 'success';
            case 'invited':
                return 'warning';
            case 'inactive':
                return 'danger';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="User Management" />

            <div className="space-y-6">
                <PageHeader
                    title="User Management"
                    description="Manage organization members, invite new users, and assign roles."
                />

                {/* Invite User Card */}
                <Card
                    title="Invite New User"
                    description="Assign branch, division, department, and role to send invite"
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 md:grid-cols-4"
                    >
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Full Name
                            </label>
                            <TextInput
                                placeholder="e.g. Somchai Srisuk"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className="w-full"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Email Address
                            </label>
                            <TextInput
                                type="email"
                                placeholder="user@company.com"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                className="w-full"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Position / Title
                            </label>
                            <TextInput
                                placeholder="e.g. Sales Manager"
                                value={data.position}
                                onChange={(e) =>
                                    setData('position', e.target.value)
                                }
                                className="w-full"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Person ID (Optional)
                            </label>
                            <TextInput
                                placeholder="13 digits"
                                value={data.person_id}
                                onChange={(e) =>
                                    setData('person_id', e.target.value)
                                }
                                className="w-full"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Branch
                            </label>
                            <select
                                className="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.branch_id}
                                onChange={(e) =>
                                    setData('branch_id', e.target.value)
                                }
                            >
                                {branches.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.code} - {item.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Division
                            </label>
                            <select
                                className="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.division_id}
                                onChange={(e) =>
                                    setData('division_id', e.target.value)
                                }
                            >
                                {divisions.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.code} - {item.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Department
                            </label>
                            <select
                                className="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.department_id}
                                onChange={(e) =>
                                    setData('department_id', e.target.value)
                                }
                            >
                                {departments.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.code} - {item.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 uppercase mb-1">
                                Initial Role
                            </label>
                            <select
                                className="w-full rounded-md border-slate-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.role_id}
                                onChange={(e) =>
                                    setData('role_id', e.target.value)
                                }
                            >
                                {roles.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="md:col-span-4 flex justify-end">
                            <PrimaryButton
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700"
                            >
                                Send User Invitation
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>

                {/* User Table Card */}
                <Card
                    title="Organization Users Directory"
                    description={`Total ${users.length} members registered`}
                >
                    <DataTable
                        data={users}
                        keyExtractor={(user) => user.id}
                        columns={[
                            {
                                header: 'Member',
                                accessor: (user) => (
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 font-bold text-xs text-slate-700">
                                            {user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {user.position || 'No Position'}
                                            </div>
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                header: 'Email',
                                accessor: (user) => (
                                    <span className="font-mono text-xs text-slate-600">
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
                                        <span className="capitalize">
                                            {user.status}
                                        </span>
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
                                header: 'Assigned Roles',
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
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
