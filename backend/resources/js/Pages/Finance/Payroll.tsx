import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

type Profile = {
    id: string;
    user_id: string;
    monthly_salary: string;
    fixed_allowance: string;
    fixed_deduction: string;
    annual_tax_allowance: string;
    social_security_enabled: boolean;
    status: string;
    user: { name: string; email: string };
};
type Item = {
    id: string;
    user_id: string;
    salary_amount: string;
    allowance_amount: string;
    employee_social_security_amount: string;
    employer_social_security_amount: string;
    withholding_tax_amount: string;
    net_pay_amount: string;
    user: { name: string };
};
type Run = {
    id: string;
    run_no: string;
    period_start: string;
    period_end: string;
    payment_date: string;
    status: string;
    gross_amount: string;
    withholding_tax_amount: string;
    employee_social_security_amount: string;
    employer_social_security_amount: string;
    net_pay_amount: string;
    items: Item[];
};
const money = (value: string | number) =>
    Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

export default function Payroll({
    profiles,
    runs,
    users,
    bankAccounts,
    taxPolicies,
    socialSecurityPolicies,
    can,
}: {
    profiles: Profile[];
    runs: Run[];
    users: { id: string; name: string; email: string }[];
    bankAccounts: { id: string; bank_name: string; account_name: string }[];
    taxPolicies: {
        id: string;
        name: string;
        effective_from: string;
        source_url: string | null;
    }[];
    socialSecurityPolicies: {
        id: string;
        name: string;
        effective_from: string;
        wage_ceiling: string;
        employee_rate: string;
    }[];
    can: { manage: boolean; approve: boolean; pay: boolean; export: boolean };
}) {
    const submit = (event: FormEvent<HTMLFormElement>, name: string) => {
        event.preventDefault();
        router.post(
            route(name),
            Object.fromEntries(new FormData(event.currentTarget)),
        );
    };
    return (
        <AuthenticatedLayout>
            <Head title="Payroll" />
            <div className="space-y-6">
                <PageHeader
                    title="Payroll"
                    description="THB payroll, effective-dated tax and social security policies, approvals, payslips and GL."
                />
                <div className="grid gap-6 xl:grid-cols-2">
                    {can.manage && (
                        <Card title="Employee Payroll Profile">
                            <form
                                className="grid gap-3 md:grid-cols-2"
                                onSubmit={(e) =>
                                    submit(e, 'payroll.profiles.store')
                                }
                            >
                                <select
                                    required
                                    name="user_id"
                                    className="rounded-md border-slate-300"
                                >
                                    <option value="">Employee</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name} ({u.email})
                                        </option>
                                    ))}
                                </select>
                                <input
                                    required
                                    name="monthly_salary"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Monthly salary"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    name="fixed_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Fixed allowance"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    name="fixed_deduction"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Fixed deduction"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    name="annual_tax_allowance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue="60000"
                                    className="rounded-md border-slate-300"
                                />
                                <select
                                    name="payment_method"
                                    defaultValue="bank_transfer"
                                    className="rounded-md border-slate-300"
                                >
                                    <option value="bank_transfer">
                                        Bank transfer
                                    </option>
                                    <option value="cash">Cash</option>
                                </select>
                                <input
                                    name="payment_reference"
                                    placeholder="Payment reference"
                                    className="rounded-md border-slate-300"
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="social_security_enabled"
                                        defaultChecked
                                        value="1"
                                    />{' '}
                                    Social security
                                </label>
                                <input
                                    type="hidden"
                                    name="status"
                                    value="active"
                                />
                                <div className="md:col-span-2">
                                    <PrimaryButton>Save Profile</PrimaryButton>
                                </div>
                            </form>
                        </Card>
                    )}
                    {can.manage && (
                        <Card title="Create Payroll Run">
                            <form
                                className="grid gap-3 md:grid-cols-2"
                                onSubmit={(e) =>
                                    submit(e, 'payroll.runs.store')
                                }
                            >
                                <input
                                    required
                                    name="period_start"
                                    type="date"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    required
                                    name="period_end"
                                    type="date"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    required
                                    name="payment_date"
                                    type="date"
                                    className="rounded-md border-slate-300"
                                />
                                <select
                                    name="bank_account_id"
                                    className="rounded-md border-slate-300"
                                >
                                    <option value="">Cash on hand</option>
                                    {bankAccounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.bank_name} - {a.account_name}
                                        </option>
                                    ))}
                                </select>
                                <div className="md:col-span-2">
                                    <PrimaryButton>Create Run</PrimaryButton>
                                </div>
                            </form>
                        </Card>
                    )}
                </div>
                <Card title="Employee Payroll Profiles">
                    <DataTable
                        data={profiles}
                        keyExtractor={(profile) => profile.id}
                        columns={[
                            {
                                header: 'Employee',
                                accessor: (profile) => profile.user.name,
                            },
                            {
                                header: 'Salary',
                                accessor: (profile) =>
                                    money(profile.monthly_salary),
                            },
                            {
                                header: 'Allowance',
                                accessor: (profile) =>
                                    money(profile.fixed_allowance),
                            },
                            {
                                header: 'Social security',
                                accessor: (profile) =>
                                    profile.social_security_enabled
                                        ? 'Enabled'
                                        : 'Disabled',
                            },
                            {
                                header: 'Status',
                                accessor: (profile) => profile.status,
                            },
                        ]}
                    />
                </Card>
                <Card title="Payroll Runs">
                    <DataTable
                        data={runs}
                        keyExtractor={(run) => run.id}
                        columns={[
                            {
                                header: 'Run',
                                accessor: (run) => (
                                    <div>
                                        <div className="font-medium">
                                            {run.run_no}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {run.period_start} to{' '}
                                            {run.period_end}
                                        </div>
                                    </div>
                                ),
                            },
                            { header: 'Status', accessor: (run) => run.status },
                            {
                                header: 'Gross',
                                accessor: (run) => money(run.gross_amount),
                            },
                            {
                                header: 'Tax / SS',
                                accessor: (run) =>
                                    `${money(run.withholding_tax_amount)} / ${money(Number(run.employee_social_security_amount) + Number(run.employer_social_security_amount))}`,
                            },
                            {
                                header: 'Net pay',
                                accessor: (run) => money(run.net_pay_amount),
                            },
                            {
                                header: 'Actions',
                                accessor: (run) => (
                                    <div className="flex flex-wrap gap-2 text-sm">
                                        {can.manage &&
                                            run.status !== 'approved' &&
                                            run.status !== 'paid' && (
                                                <button
                                                    type="button"
                                                    className="text-indigo-700"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'payroll.runs.calculate',
                                                                run.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Calculate
                                                </button>
                                            )}
                                        {can.approve &&
                                            run.status === 'calculated' && (
                                                <button
                                                    type="button"
                                                    className="text-amber-700"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'payroll.runs.approve',
                                                                run.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Approve + GL
                                                </button>
                                            )}
                                        {can.pay &&
                                            run.status === 'approved' && (
                                                <button
                                                    type="button"
                                                    className="text-emerald-700"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'payroll.runs.pay',
                                                                run.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Pay
                                                </button>
                                            )}
                                        {can.export && (
                                            <>
                                                <a
                                                    className="text-slate-700"
                                                    href={route(
                                                        'payroll.runs.export',
                                                        [run.id, 'pnd1'],
                                                    )}
                                                >
                                                    PND1 CSV
                                                </a>
                                                <a
                                                    className="text-slate-700"
                                                    href={route(
                                                        'payroll.runs.export',
                                                        [
                                                            run.id,
                                                            'social-security',
                                                        ],
                                                    )}
                                                >
                                                    SSO CSV
                                                </a>
                                            </>
                                        )}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>
                <Card title="Payslips">
                    <DataTable
                        data={runs.flatMap((run) =>
                            run.items.map((item) => ({
                                ...item,
                                run_no: run.run_no,
                            })),
                        )}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Employee',
                                accessor: (item) => item.user.name,
                            },
                            { header: 'Run', accessor: (item) => item.run_no },
                            {
                                header: 'Net pay',
                                accessor: (item) => money(item.net_pay_amount),
                            },
                            {
                                header: 'Payslip',
                                accessor: (item) => (
                                    <a
                                        className="text-indigo-700"
                                        href={route(
                                            'payroll.payslips.pdf',
                                            item.id,
                                        )}
                                    >
                                        PDF
                                    </a>
                                ),
                            },
                        ]}
                    />
                </Card>
                {can.manage && (
                    <Card title="Policy Settings">
                        <form
                            className="grid gap-3 md:grid-cols-2"
                            onSubmit={(e) =>
                                submit(e, 'payroll.policies.store')
                            }
                        >
                            <input
                                required
                                name="effective_from"
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="employment_expense_rate"
                                type="number"
                                step="0.01"
                                defaultValue="50"
                                placeholder="Employment expense rate (%)"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="employment_expense_cap"
                                type="number"
                                step="0.01"
                                defaultValue="100000"
                                placeholder="Employment expense cap"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="wage_ceiling"
                                type="number"
                                step="0.01"
                                defaultValue="17500"
                                placeholder="Social security wage ceiling"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="employee_rate"
                                type="number"
                                step="0.01"
                                defaultValue="5"
                                placeholder="Employee SS rate (%)"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="employer_rate"
                                type="number"
                                step="0.01"
                                defaultValue="5"
                                placeholder="Employer SS rate (%)"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                name="source_url"
                                type="url"
                                placeholder="Official source URL"
                                className="rounded-md border-slate-300 md:col-span-2"
                            />
                            <textarea
                                required
                                name="brackets_json"
                                defaultValue={
                                    '[{"up_to":150000,"rate":0},{"up_to":300000,"rate":5},{"up_to":500000,"rate":10},{"up_to":750000,"rate":15},{"up_to":1000000,"rate":20},{"up_to":2000000,"rate":25},{"up_to":5000000,"rate":30},{"up_to":null,"rate":35}]'
                                }
                                className="min-h-24 rounded-md border-slate-300 font-mono text-xs md:col-span-2"
                            />
                            <div className="md:col-span-2">
                                <PrimaryButton>
                                    Save New Policy Version
                                </PrimaryButton>
                            </div>
                        </form>
                        <div className="mt-5 grid gap-4 text-sm md:grid-cols-2">
                            <div>
                                <div className="font-medium">
                                    Tax policy versions
                                </div>
                                {taxPolicies.map((p) => (
                                    <div key={p.id}>
                                        {p.effective_from} - {p.name}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <div className="font-medium">
                                    Social security versions
                                </div>
                                {socialSecurityPolicies.map((p) => (
                                    <div key={p.id}>
                                        {p.effective_from} - {p.name} (
                                        {p.employee_rate}% / ceiling{' '}
                                        {money(p.wage_ceiling)})
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}
                <p className="text-xs text-slate-500">
                    PND1 and social-security CSV are workpapers. Verify
                    submission format and legal values with current official
                    guidance before filing.
                </p>
            </div>
        </AuthenticatedLayout>
    );
}
