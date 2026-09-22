import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmployeeImportDialog from './Partials/EmployeeImportDialog';
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Ref = {
    id: string;
    code: string;
    name: string;
    branch_id?: string;
    division_id?: string;
};
type Position = {
    id: string;
    code?: string | null;
    name: string;
    department_id?: string | null;
};
type Account = { id: string; name: string; status: string };
type Employee = {
    id: string;
    user_id?: string | null;
    employee_code: string;
    first_name: string;
    last_name?: string | null;
    nickname?: string | null;
    national_id?: string | null;
    tax_id?: string | null;
    email?: string | null;
    phone?: string | null;
    position_id?: string | null;
    position_title?: string | null;
    position?: Position | null;
    branch_id?: string | null;
    division_id?: string | null;
    department_id?: string | null;
    manager_id?: string | null;
    employment_type: string;
    employment_status: string;
    started_on?: string | null;
    probation_ends_on?: string | null;
    resigned_on?: string | null;
    payment_method: string;
    bank_name?: string | null;
    bank_account_number?: string | null;
    department?: Ref;
    user?: Account | null;
};
type Form = {
    employee_code: string;
    first_name: string;
    last_name: string;
    nickname: string;
    national_id: string;
    tax_id: string;
    email: string;
    phone: string;
    position_id: string;
    position_title: string;
    branch_id: string;
    division_id: string;
    department_id: string;
    manager_id: string;
    employment_type: string;
    employment_status: string;
    started_on: string;
    probation_ends_on: string;
    resigned_on: string;
    payment_method: string;
    bank_name: string;
    bank_account_number: string;
};
const empty: Form = {
    employee_code: '',
    first_name: '',
    last_name: '',
    nickname: '',
    national_id: '',
    tax_id: '',
    email: '',
    phone: '',
    position_id: '',
    position_title: '',
    branch_id: '',
    division_id: '',
    department_id: '',
    manager_id: '',
    employment_type: 'monthly',
    employment_status: 'probation',
    started_on: '',
    probation_ends_on: '',
    resigned_on: '',
    payment_method: 'bank_transfer',
    bank_name: '',
    bank_account_number: '',
};

function PositionCombobox({
    value,
    positions,
    departmentId,
    onChange,
    onPositionCreated,
    error,
}: {
    value: string;
    positions: Position[];
    departmentId?: string;
    onChange: (positionId: string, positionName: string) => void;
    onPositionCreated: (pos: Position) => void;
    error?: string;
}) {
    const [query, setQuery] = useState('');
    const [quickAddOpen, setQuickAddOpen] = useState(false);
    const [creating, setCreating] = useState(false);
    const [newPositionName, setNewPositionName] = useState('');
    const [newPositionCode, setNewPositionCode] = useState('');

    const filtered =
        query === ''
            ? positions
            : positions.filter(
                  (p) =>
                      p.name.toLowerCase().includes(query.toLowerCase()) ||
                      (p.code &&
                          p.code.toLowerCase().includes(query.toLowerCase())),
              );

    const selectedPosition = positions.find((p) => p.id === value);

    const handleCreate = async (nameToCreate: string) => {
        if (!nameToCreate.trim()) return;
        setCreating(true);
        try {
            const token =
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement
                )?.content || '';
            const res = await fetch(route('hr.positions.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    name: nameToCreate.trim(),
                    code: newPositionCode.trim() || null,
                    department_id: departmentId || null,
                }),
            });

            if (res.ok) {
                const data = await res.json();
                const newPos = data.position;
                onPositionCreated(newPos);
                onChange(newPos.id, newPos.name);
                setQuickAddOpen(false);
                setNewPositionName('');
                setNewPositionCode('');
            }
        } finally {
            setCreating(false);
        }
    };

    return (
        <div className="relative">
            <div className="flex items-center justify-between">
                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Position (ตำแหน่งงาน)
                </label>
                <button
                    type="button"
                    onClick={() => {
                        setNewPositionName(query);
                        setQuickAddOpen(true);
                    }}
                    className="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    + Quick Add
                </button>
            </div>
            <Combobox
                value={value}
                onChange={(selectedId: string | null) => {
                    const pos = positions.find((p) => p.id === selectedId);
                    onChange(selectedId || '', pos?.name || '');
                }}
                onClose={() => setQuery('')}
            >
                <div className="relative mt-1">
                    <ComboboxInput
                        className="w-full rounded-md border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        displayValue={() =>
                            selectedPosition
                                ? `${selectedPosition.name}${selectedPosition.code ? ` (${selectedPosition.code})` : ''}`
                                : ''
                        }
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Type to search position..."
                    />
                    <ComboboxButton className="absolute inset-y-0 right-0 flex items-center pr-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <svg
                            className="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </ComboboxButton>
                </div>
                <ComboboxOptions className="absolute z-50 mt-1 max-h-56 w-full overflow-auto rounded-md border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                    <ComboboxOption
                        value=""
                        className="cursor-pointer rounded px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
                    >
                        -- None (Clear Position) --
                    </ComboboxOption>
                    {filtered.map((pos) => (
                        <ComboboxOption
                            key={pos.id}
                            value={pos.id}
                            className="flex cursor-pointer items-center justify-between rounded px-3 py-2 text-sm text-slate-900 data-[focus]:bg-indigo-50 data-[focus]:text-indigo-900 dark:text-slate-100 dark:data-[focus]:bg-slate-700 dark:data-[focus]:text-white"
                        >
                            <span>{pos.name}</span>
                            {pos.code && (
                                <span className="text-xs font-mono text-slate-400 dark:text-slate-500">
                                    {pos.code}
                                </span>
                            )}
                        </ComboboxOption>
                    ))}
                    {filtered.length === 0 && query.trim() !== '' && (
                        <div className="p-2">
                            <p className="px-2 py-1 text-xs text-slate-500 dark:text-slate-400">
                                No position found for "{query}"
                            </p>
                            <button
                                type="button"
                                disabled={creating}
                                onClick={() => handleCreate(query)}
                                className="mt-1 flex w-full items-center gap-1 rounded bg-indigo-50 px-2 py-1.5 text-left text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900"
                            >
                                <span>+</span> Create position "{query}"
                            </button>
                        </div>
                    )}
                </ComboboxOptions>
            </Combobox>
            {error && (
                <span className="text-xs text-red-600 dark:text-red-400">
                    {error}
                </span>
            )}

            {/* Quick Add Position Modal */}
            {quickAddOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                        <h4 className="text-base font-semibold text-slate-900 dark:text-slate-100">
                            Create New Position
                        </h4>
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Add a new job position to the organization master
                            list.
                        </p>
                        <div className="mt-4 space-y-3">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Position Name *
                                </label>
                                <input
                                    type="text"
                                    value={newPositionName}
                                    onChange={(e) =>
                                        setNewPositionName(e.target.value)
                                    }
                                    placeholder="e.g. Senior Software Engineer"
                                    className="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                    autoFocus
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Position Code (Optional)
                                </label>
                                <input
                                    type="text"
                                    value={newPositionCode}
                                    onChange={(e) =>
                                        setNewPositionCode(e.target.value)
                                    }
                                    placeholder="e.g. POS-ENG-01"
                                    className="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>
                        </div>
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setQuickAddOpen(false)}
                                className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={creating || !newPositionName.trim()}
                                onClick={() => handleCreate(newPositionName)}
                                className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {creating ? 'Saving...' : 'Save Position'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function EmployeeDialog({
    employee,
    open,
    close,
    branches,
    divisions,
    departments,
    positions: initialPositions,
    employees,
    accounts,
}: {
    employee?: Employee;
    open: boolean;
    close: () => void;
    branches: Ref[];
    divisions: Ref[];
    departments: Ref[];
    positions: Position[];
    employees: Employee[];
    accounts: Account[];
}) {
    const [positionsList, setPositionsList] =
        useState<Position[]>(initialPositions);

    const initial: Form = employee
        ? {
              ...empty,
              ...employee,
              position_id: employee.position_id ?? '',
              position_title: employee.position_title ?? '',
              last_name: employee.last_name ?? '',
              nickname: employee.nickname ?? '',
              national_id: employee.national_id ?? '',
              tax_id: employee.tax_id ?? '',
              email: employee.email ?? '',
              phone: employee.phone ?? '',
              branch_id: employee.branch_id ?? '',
              division_id: employee.division_id ?? '',
              department_id: employee.department_id ?? '',
              manager_id: employee.manager_id ?? '',
              started_on: employee.started_on ?? '',
              probation_ends_on: employee.probation_ends_on ?? '',
              resigned_on: employee.resigned_on ?? '',
              bank_name: employee.bank_name ?? '',
              bank_account_number: employee.bank_account_number ?? '',
          }
        : empty;
    const form = useForm<Form>(initial);
    const [accountId, setAccountId] = useState('');
    if (!open) return null;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (employee) {
            form.patch(route('hr.employees.update', employee.id), options);
        } else {
            form.post(route('hr.employees.store'), options);
        }
    };
    const selectControl =
        'mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500';

    const input = (key: keyof Form, label: string, type = 'text') => (
        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {label}
            <input
                type={type}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500"
            />
            {form.errors[key] && (
                <span className="text-xs text-red-600 dark:text-red-400">
                    {form.errors[key]}
                </span>
            )}
        </label>
    );
    return (
        <CreateEditDialog
            open={open}
            minimized={false}
            onMinimize={() => {}}
            onClose={close}
            title={employee ? 'Edit Employee' : 'New Employee'}
            breadcrumb="HR / Employees"
            entityLabel="Employee"
            draftLabel={employee?.employee_code ?? 'New employee'}
            storageKey="employee-editor-size"
            status="Draft"
            formId="employee-form"
            processing={form.processing}
            saveLabel="Save Employee"
            errors={form.errors}
            onSubmit={submit}
        >
            {() => (
                <div className="grid gap-4 p-5 sm:grid-cols-2">
                    {input('employee_code', 'Employee code')}
                    {input('first_name', 'First name')}
                    {input('last_name', 'Last name')}
                    {input('nickname', 'Nickname')}
                    {input('email', 'Email', 'email')}
                    {input('phone', 'Phone')}
                    <PositionCombobox
                        value={form.data.position_id}
                        positions={positionsList}
                        departmentId={form.data.department_id}
                        onChange={(posId, posName) => {
                            form.setData({
                                ...form.data,
                                position_id: posId,
                                position_title: posName,
                            });
                        }}
                        onPositionCreated={(newPos) => {
                            setPositionsList((prev) => [...prev, newPos]);
                        }}
                        error={form.errors.position_id}
                    />
                    {input('national_id', 'National ID / Passport')}
                    {input('tax_id', 'Tax ID')}
                    {input('bank_name', 'Bank name')}
                    {input('bank_account_number', 'Bank account number')}
                    {input('started_on', 'Start date', 'date')}
                    {input('probation_ends_on', 'Probation ends', 'date')}
                    {input('resigned_on', 'Resigned on', 'date')}
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Employment type
                        <select
                            value={form.data.employment_type}
                            onChange={(e) =>
                                form.setData('employment_type', e.target.value)
                            }
                            className={selectControl}
                        >
                            <option value="monthly">Monthly</option>
                            <option value="daily">Daily</option>
                            <option value="hourly">Hourly</option>
                            <option value="contractor">Contractor</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Status
                        <select
                            value={form.data.employment_status}
                            onChange={(e) =>
                                form.setData(
                                    'employment_status',
                                    e.target.value,
                                )
                            }
                            className={selectControl}
                        >
                            {[
                                'probation',
                                'active',
                                'suspended',
                                'resigned',
                                'terminated',
                            ].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Branch
                        <select
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData('branch_id', e.target.value)
                            }
                            className={selectControl}
                        >
                            <option value="">None</option>
                            {branches.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.code} — {x.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Division
                        <select
                            value={form.data.division_id}
                            onChange={(e) =>
                                form.setData('division_id', e.target.value)
                            }
                            className={selectControl}
                        >
                            <option value="">None</option>
                            {divisions
                                .filter(
                                    (x) =>
                                        !form.data.branch_id ||
                                        x.branch_id === form.data.branch_id,
                                )
                                .map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.code} — {x.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Department
                        <select
                            value={form.data.department_id}
                            onChange={(e) =>
                                form.setData('department_id', e.target.value)
                            }
                            className={selectControl}
                        >
                            <option value="">None</option>
                            {departments
                                .filter(
                                    (x) =>
                                        !form.data.division_id ||
                                        x.division_id === form.data.division_id,
                                )
                                .map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.code} — {x.name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                        Manager
                        <select
                            value={form.data.manager_id}
                            onChange={(e) =>
                                form.setData('manager_id', e.target.value)
                            }
                            className={selectControl}
                        >
                            <option value="">None</option>
                            {employees
                                .filter((x) => x.id !== employee?.id)
                                .map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.employee_code} — {x.first_name}
                                    </option>
                                ))}
                        </select>
                    </label>
                    {employee && !employee.user_id && (
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Link ERP account
                            <select
                                value={accountId}
                                onChange={(e) => setAccountId(e.target.value)}
                                className={selectControl}
                            >
                                <option value="">Select account</option>
                                {accounts.map((x) => (
                                    <option key={x.id} value={x.id}>
                                        {x.name} ({x.status})
                                    </option>
                                ))}
                            </select>
                            {accountId && (
                                <button
                                    type="button"
                                    className="mt-2 text-sm text-indigo-600 dark:text-indigo-400"
                                    onClick={() =>
                                        router.patch(
                                            route('hr.employees.account.link', {
                                                employee: employee.id,
                                            }),
                                            { user_id: accountId },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Link selected account
                                </button>
                            )}
                        </label>
                    )}
                </div>
            )}
        </CreateEditDialog>
    );
}

export default function Employees({
    employees,
    branches,
    divisions,
    departments,
    positions,
    accounts,
    canManage,
    filters,
}: {
    employees: Employee[];
    branches: Ref[];
    divisions: Ref[];
    departments: Ref[];
    positions: Position[];
    accounts: Account[];
    canManage: boolean;
    filters: Record<string, string | null>;
}) {
    const [editing, setEditing] = useState<Employee | undefined>();
    const [open, setOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const filter = (key: string, value: string) =>
        router.get(
            route('hr.employees.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true },
        );
    return (
        <AuthenticatedLayout>
            <Head title="Employee Directory" />
            <div className="space-y-6">
                <PageHeader
                    title="Employee Directory"
                    description="Employee master records are separate from ERP user accounts."
                    actions={
                        canManage ? (
                            <div className="flex items-center gap-2">
                                <SecondaryButton
                                    onClick={() => setImportOpen(true)}
                                >
                                    Import
                                </SecondaryButton>
                                <PrimaryButton
                                    onClick={() => {
                                        setEditing(undefined);
                                        setOpen(true);
                                    }}
                                >
                                    New Employee
                                </PrimaryButton>
                            </div>
                        ) : undefined
                    }
                />
                <div className="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 sm:grid-cols-5">
                    <select
                        aria-label="Branch filter"
                        value={filters.branch_id ?? ''}
                        onChange={(e) => filter('branch_id', e.target.value)}
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All branches</option>
                        {branches.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Department filter"
                        value={filters.department_id ?? ''}
                        onChange={(e) =>
                            filter('department_id', e.target.value)
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All departments</option>
                        {departments.map((x) => (
                            <option key={x.id} value={x.id}>
                                {x.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Employment status filter"
                        value={filters.employment_status ?? ''}
                        onChange={(e) =>
                            filter('employment_status', e.target.value)
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All statuses</option>
                        {[
                            'probation',
                            'active',
                            'suspended',
                            'resigned',
                            'terminated',
                        ].map((x) => (
                            <option key={x} value={x}>
                                {x}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Employment type filter"
                        value={filters.employment_type ?? ''}
                        onChange={(e) =>
                            filter('employment_type', e.target.value)
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All types</option>
                        {['monthly', 'daily', 'hourly', 'contractor'].map(
                            (x) => (
                                <option key={x} value={x}>
                                    {x}
                                </option>
                            ),
                        )}
                    </select>
                    <select
                        aria-label="Account status filter"
                        value={filters.account_status ?? ''}
                        onChange={(e) =>
                            filter('account_status', e.target.value)
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="">All accounts</option>
                        {[
                            'linked',
                            'unlinked',
                            'active',
                            'inactive',
                            'invited',
                        ].map((x) => (
                            <option key={x} value={x}>
                                {x}
                            </option>
                        ))}
                    </select>
                </div>
                <DataTable
                    columns={[
                        { header: 'Code', accessor: 'employee_code' },
                        {
                            header: 'Employee',
                            accessor: (x: Employee) =>
                                `${x.first_name} ${x.last_name ?? ''}`,
                        },
                        {
                            header: 'Position',
                            accessor: (x: Employee) =>
                                x.position?.name ?? x.position_title ?? '—',
                        },
                        {
                            header: 'Department',
                            accessor: (x: Employee) =>
                                x.department?.name ?? '—',
                        },
                        { header: 'Status', accessor: 'employment_status' },
                        {
                            header: 'ERP account',
                            accessor: (x: Employee) =>
                                x.user
                                    ? `${x.user.name} (${x.user.status})`
                                    : 'Not linked',
                        },
                        ...(canManage
                            ? [
                                  {
                                      header: '',
                                      accessor: (x: Employee) => (
                                          <button
                                              className="text-indigo-600 dark:text-indigo-400"
                                              onClick={() => {
                                                  setEditing(x);
                                                  setOpen(true);
                                              }}
                                          >
                                              Edit
                                          </button>
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                    data={employees}
                    keyExtractor={(x) => x.id}
                    emptyMessage="No employees found."
                />
            </div>
            <EmployeeDialog
                key={editing?.id ?? 'new'}
                open={open}
                close={() => setOpen(false)}
                employee={editing}
                branches={branches}
                divisions={divisions}
                departments={departments}
                positions={positions || []}
                employees={employees}
                accounts={accounts}
            />
            <EmployeeImportDialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
