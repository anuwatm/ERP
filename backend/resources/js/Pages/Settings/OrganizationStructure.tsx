import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

type Branch = {
    id: string;
    code: string;
    name: string;
    address?: string | null;
    phone?: string | null;
    is_head_office: boolean;
    status: string;
};

type Division = {
    id: string;
    branch_id: string;
    code: string;
    name: string;
    status: string;
};

type Department = {
    id: string;
    branch_id: string;
    division_id: string;
    code: string;
    name: string;
    status: string;
};

type Tab = 'branches' | 'divisions' | 'departments';

const inputClass =
    'w-full rounded-xl border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm font-medium shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150';

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={status === 'active' ? 'success' : 'neutral'} dot>
            {status}
        </Badge>
    );
}

export default function OrganizationStructure({
    branches,
    divisions,
    departments,
}: {
    branches: Branch[];
    divisions: Division[];
    departments: Department[];
}) {
    const [tab, setTab] = useState<Tab>('branches');
    const activeBranches = branches.filter((item) => item.status === 'active');
    const activeDivisions = divisions.filter(
        (item) => item.status === 'active',
    );

    // Edit modal states
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [editingDivision, setEditingDivision] = useState<Division | null>(
        null,
    );
    const [editingDepartment, setEditingDepartment] =
        useState<Department | null>(null);

    // Creation forms
    const branchForm = useForm({
        name: '',
        address: '',
        phone: '',
        is_head_office: false,
    });

    const divisionForm = useForm({
        branch_id: activeBranches[0]?.id ?? '',
        name: '',
    });

    const departmentForm = useForm({
        branch_id: activeBranches[0]?.id ?? '',
        division_id:
            activeDivisions.filter(
                (d) => d.branch_id === (activeBranches[0]?.id ?? ''),
            )[0]?.id ?? '',
        name: '',
    });

    // Edit forms
    const editBranchForm = useForm({
        name: '',
        address: '',
        phone: '',
        is_head_office: false,
    });

    const editDivisionForm = useForm({
        branch_id: '',
        name: '',
    });

    const editDepartmentForm = useForm({
        branch_id: '',
        division_id: '',
        name: '',
    });

    const branchById = useMemo(
        () => new Map(branches.map((item) => [item.id, item])),
        [branches],
    );
    const divisionById = useMemo(
        () => new Map(divisions.map((item) => [item.id, item])),
        [divisions],
    );

    // Form Submissions - Create
    const createBranch: FormEventHandler = (event) => {
        event.preventDefault();
        branchForm.post(route('settings.branches.store'), {
            onSuccess: () => branchForm.reset(),
        });
    };

    const createDivision: FormEventHandler = (event) => {
        event.preventDefault();
        divisionForm.post(route('settings.divisions.store'), {
            onSuccess: () => divisionForm.reset('name'),
        });
    };

    const createDepartment: FormEventHandler = (event) => {
        event.preventDefault();
        departmentForm.post(route('settings.departments.store'), {
            onSuccess: () => departmentForm.reset('name'),
        });
    };

    // Open Edit Modals
    const openEditBranch = (branch: Branch) => {
        setEditingBranch(branch);
        editBranchForm.clearErrors();
        editBranchForm.setData({
            name: branch.name,
            address: branch.address ?? '',
            phone: branch.phone ?? '',
            is_head_office: branch.is_head_office,
        });
    };

    const openEditDivision = (division: Division) => {
        setEditingDivision(division);
        editDivisionForm.clearErrors();
        editDivisionForm.setData({
            branch_id: division.branch_id,
            name: division.name,
        });
    };

    const openEditDepartment = (department: Department) => {
        setEditingDepartment(department);
        editDepartmentForm.clearErrors();
        editDepartmentForm.setData({
            branch_id: department.branch_id,
            division_id: department.division_id,
            name: department.name,
        });
    };

    // Submit Edit Modals
    const updateBranchSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingBranch) return;
        editBranchForm.patch(
            route('settings.branches.update', editingBranch.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingBranch(null),
            },
        );
    };

    const updateDivisionSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingDivision) return;
        editDivisionForm.patch(
            route('settings.divisions.update', editingDivision.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingDivision(null),
            },
        );
    };

    const updateDepartmentSubmit: FormEventHandler = (event) => {
        event.preventDefault();
        if (!editingDepartment) return;
        editDepartmentForm.patch(
            route('settings.departments.update', editingDepartment.id),
            {
                preserveScroll: true,
                onSuccess: () => setEditingDepartment(null),
            },
        );
    };

    const patch = (
        name: string,
        id: string,
        data: Record<string, string | boolean | null>,
    ) => {
        router.patch(route(name, id), data, { preserveScroll: true });
    };

    const destroy = (name: string, id: string) => {
        if (window.confirm('Confirm delete this record?')) {
            router.delete(route(name, id), { preserveScroll: true });
        }
    };

    // Cascading dropdown handlers
    const handleCreateDeptBranchChange = (branchId: string) => {
        const branchDivisions = activeDivisions.filter(
            (d) => d.branch_id === branchId,
        );
        departmentForm.setData((prev) => ({
            ...prev,
            branch_id: branchId,
            division_id: branchDivisions[0]?.id ?? '',
        }));
    };

    const handleEditDeptBranchChange = (branchId: string) => {
        const branchDivisions = activeDivisions.filter(
            (d) => d.branch_id === branchId,
        );
        editDepartmentForm.setData((prev) => ({
            ...prev,
            branch_id: branchId,
            division_id: branchDivisions[0]?.id ?? '',
        }));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Organization Structure" />

            <div className="space-y-6">
                <PageHeader
                    title="Organization Structure"
                    description="Branch, division, and department master data management"
                />

                <div className="flex flex-wrap gap-2 border-b border-slate-200">
                    {(['branches', 'divisions', 'departments'] as Tab[]).map(
                        (item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setTab(item)}
                                className={`px-4 py-2 text-sm font-semibold ${
                                    tab === item
                                        ? 'border-b-2 border-indigo-600 text-indigo-700'
                                        : 'text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                {item[0].toUpperCase() + item.slice(1)}
                            </button>
                        ),
                    )}
                </div>

                {/* Branches Tab */}
                {tab === 'branches' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Branch"
                            description="Branch code is auto-generated by system"
                        >
                            <form
                                onSubmit={createBranch}
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <div>
                                    <InputLabel value="Branch Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Head Office"
                                        value={branchForm.data.name}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={branchForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Phone" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="Phone number"
                                        value={branchForm.data.phone}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={branchForm.errors.phone}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Address" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="Location address"
                                        value={branchForm.data.address}
                                        onChange={(e) =>
                                            branchForm.setData(
                                                'address',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={branchForm.errors.address}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end pb-2">
                                    <label className="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            checked={
                                                branchForm.data.is_head_office
                                            }
                                            onChange={(e) =>
                                                branchForm.setData(
                                                    'is_head_office',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Set as Head Office
                                    </label>
                                </div>
                                <div className="md:col-span-4 flex justify-end">
                                    <PrimaryButton
                                        disabled={branchForm.processing}
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
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Branch
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Branches"
                            description={`${branches.length} total branches registered`}
                        >
                            <DataTable
                                data={branches}
                                keyExtractor={(branch) => branch.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (branch) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {branch.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Branch Name',
                                        accessor: (branch) => (
                                            <span className="font-semibold text-slate-800">
                                                {branch.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Phone',
                                        accessor: (branch) =>
                                            branch.phone || '-',
                                    },
                                    {
                                        header: 'Address',
                                        accessor: (branch) =>
                                            branch.address || '-',
                                    },
                                    {
                                        header: 'Head Office',
                                        accessor: (branch) =>
                                            branch.is_head_office ? (
                                                <Badge variant="purple">
                                                    Head Office
                                                </Badge>
                                            ) : (
                                                '-'
                                            ),
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (branch) => (
                                            <StatusBadge
                                                status={branch.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (branch) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditBranch(branch)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {branch.status === 'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.branches.disable',
                                                                branch.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.branches.destroy',
                                                            branch.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}

                {/* Divisions Tab */}
                {tab === 'divisions' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Division"
                            description="Division code is auto-generated by system"
                        >
                            <form
                                onSubmit={createDivision}
                                className="grid gap-4 md:grid-cols-3"
                            >
                                <div>
                                    <InputLabel value="Belongs to Branch *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={divisionForm.data.branch_id}
                                        onChange={(e) =>
                                            divisionForm.setData(
                                                'branch_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeBranches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.code} - {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={divisionForm.errors.branch_id}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Division Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Technology & Product"
                                        value={divisionForm.data.name}
                                        onChange={(e) =>
                                            divisionForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={divisionForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <PrimaryButton
                                        disabled={divisionForm.processing}
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
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Division
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Divisions"
                            description={`${divisions.length} total divisions registered`}
                        >
                            <DataTable
                                data={divisions}
                                keyExtractor={(division) => division.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (division) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {division.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Division Name',
                                        accessor: (division) => (
                                            <span className="font-semibold text-slate-800">
                                                {division.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Belongs to Branch',
                                        accessor: (division) =>
                                            branchById.get(division.branch_id)
                                                ? `${branchById.get(division.branch_id)?.code} - ${branchById.get(division.branch_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (division) => (
                                            <StatusBadge
                                                status={division.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (division) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditDivision(
                                                            division,
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {division.status ===
                                                    'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.divisions.disable',
                                                                division.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.divisions.destroy',
                                                            division.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}

                {/* Departments Tab */}
                {tab === 'departments' && (
                    <div className="space-y-6">
                        <Card
                            title="Create Department"
                            description="Department code is auto-generated by system"
                        >
                            <form
                                onSubmit={createDepartment}
                                className="grid gap-4 md:grid-cols-4"
                            >
                                <div>
                                    <InputLabel value="Branch *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={departmentForm.data.branch_id}
                                        onChange={(e) =>
                                            handleCreateDeptBranchChange(
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeBranches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.code} - {branch.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            departmentForm.errors.branch_id
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Division *" />
                                    <select
                                        className={`mt-1 ${inputClass}`}
                                        value={departmentForm.data.division_id}
                                        onChange={(e) =>
                                            departmentForm.setData(
                                                'division_id',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {activeDivisions
                                            .filter(
                                                (item) =>
                                                    item.branch_id ===
                                                    departmentForm.data
                                                        .branch_id,
                                            )
                                            .map((division) => (
                                                <option
                                                    key={division.id}
                                                    value={division.id}
                                                >
                                                    {division.code} -{' '}
                                                    {division.name}
                                                </option>
                                            ))}
                                    </select>
                                    <InputError
                                        message={
                                            departmentForm.errors.division_id
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Department Name *" />
                                    <TextInput
                                        className="mt-1 w-full"
                                        placeholder="e.g. Core Software Dev"
                                        value={departmentForm.data.name}
                                        onChange={(e) =>
                                            departmentForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={departmentForm.errors.name}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex items-end">
                                    <PrimaryButton
                                        disabled={departmentForm.processing}
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
                                                    d="M12 4v16m8-8H4"
                                                />
                                            </svg>
                                        }
                                    >
                                        Create Department
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Departments"
                            description={`${departments.length} total departments registered`}
                        >
                            <DataTable
                                data={departments}
                                keyExtractor={(department) => department.id}
                                columns={[
                                    {
                                        header: 'Code',
                                        accessor: (department) => (
                                            <span className="font-mono text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                {department.code}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Department Name',
                                        accessor: (department) => (
                                            <span className="font-semibold text-slate-800">
                                                {department.name}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Division',
                                        accessor: (department) =>
                                            divisionById.get(
                                                department.division_id,
                                            )
                                                ? `${divisionById.get(department.division_id)?.code} - ${divisionById.get(department.division_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Branch',
                                        accessor: (department) =>
                                            branchById.get(department.branch_id)
                                                ? `${branchById.get(department.branch_id)?.code} - ${branchById.get(department.branch_id)?.name}`
                                                : '-',
                                    },
                                    {
                                        header: 'Status',
                                        accessor: (department) => (
                                            <StatusBadge
                                                status={department.status}
                                            />
                                        ),
                                    },
                                    {
                                        header: 'Actions',
                                        accessor: (department) => (
                                            <div className="flex flex-wrap gap-2">
                                                <SecondaryButton
                                                    onClick={() =>
                                                        openEditDepartment(
                                                            department,
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                                {department.status ===
                                                    'active' && (
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            patch(
                                                                'settings.departments.disable',
                                                                department.id,
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Disable
                                                    </SecondaryButton>
                                                )}
                                                <SecondaryButton
                                                    onClick={() =>
                                                        destroy(
                                                            'settings.departments.destroy',
                                                            department.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </SecondaryButton>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </div>
                )}
            </div>

            {/* Edit Branch Modal */}
            <Modal
                show={editingBranch !== null}
                onClose={() => setEditingBranch(null)}
            >
                <form onSubmit={updateBranchSubmit} className="p-6 space-y-4">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Branch - #{editingBranch?.code}
                    </h3>

                    <div>
                        <InputLabel value="Branch Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.name}
                            onChange={(e) =>
                                editBranchForm.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={editBranchForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Phone" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.phone}
                            onChange={(e) =>
                                editBranchForm.setData('phone', e.target.value)
                            }
                        />
                        <InputError
                            message={editBranchForm.errors.phone}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Address" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editBranchForm.data.address}
                            onChange={(e) =>
                                editBranchForm.setData(
                                    'address',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={editBranchForm.errors.address}
                            className="mt-1"
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer pt-2">
                        <input
                            type="checkbox"
                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            checked={editBranchForm.data.is_head_office}
                            onChange={(e) =>
                                editBranchForm.setData(
                                    'is_head_office',
                                    e.target.checked,
                                )
                            }
                        />
                        Set as Head Office
                    </label>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingBranch(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editBranchForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Division Modal */}
            <Modal
                show={editingDivision !== null}
                onClose={() => setEditingDivision(null)}
            >
                <form onSubmit={updateDivisionSubmit} className="p-6 space-y-4">
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Division - #{editingDivision?.code}
                    </h3>

                    <div>
                        <InputLabel value="Belongs to Branch *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDivisionForm.data.branch_id}
                            onChange={(e) =>
                                editDivisionForm.setData(
                                    'branch_id',
                                    e.target.value,
                                )
                            }
                            required
                        >
                            {activeBranches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.code} - {branch.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={editDivisionForm.errors.branch_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Division Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editDivisionForm.data.name}
                            onChange={(e) =>
                                editDivisionForm.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={editDivisionForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingDivision(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editDivisionForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Department Modal */}
            <Modal
                show={editingDepartment !== null}
                onClose={() => setEditingDepartment(null)}
            >
                <form
                    onSubmit={updateDepartmentSubmit}
                    className="p-6 space-y-4"
                >
                    <h3 className="text-lg font-bold text-slate-900 dark:text-white border-b pb-2">
                        Edit Department - #{editingDepartment?.code}
                    </h3>

                    <div>
                        <InputLabel value="Branch *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDepartmentForm.data.branch_id}
                            onChange={(e) =>
                                handleEditDeptBranchChange(e.target.value)
                            }
                            required
                        >
                            {activeBranches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.code} - {branch.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={editDepartmentForm.errors.branch_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Division *" />
                        <select
                            className={`mt-1 ${inputClass}`}
                            value={editDepartmentForm.data.division_id}
                            onChange={(e) =>
                                editDepartmentForm.setData(
                                    'division_id',
                                    e.target.value,
                                )
                            }
                            required
                        >
                            {activeDivisions
                                .filter(
                                    (item) =>
                                        item.branch_id ===
                                        editDepartmentForm.data.branch_id,
                                )
                                .map((division) => (
                                    <option
                                        key={division.id}
                                        value={division.id}
                                    >
                                        {division.code} - {division.name}
                                    </option>
                                ))}
                        </select>
                        <InputError
                            message={editDepartmentForm.errors.division_id}
                            className="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel value="Department Name *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={editDepartmentForm.data.name}
                            onChange={(e) =>
                                editDepartmentForm.setData(
                                    'name',
                                    e.target.value,
                                )
                            }
                            required
                        />
                        <InputError
                            message={editDepartmentForm.errors.name}
                            className="mt-1"
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditingDepartment(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton disabled={editDepartmentForm.processing}>
                            Save Changes
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
