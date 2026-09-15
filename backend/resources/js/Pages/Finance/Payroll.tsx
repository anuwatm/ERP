import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import useEditorCoordinator from '@/Hooks/useEditorCoordinator';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import {
    PayrollPolicyDialog,
    PayrollProfileDialog,
    PayrollRunDialog,
} from './PayrollDialogs';

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
    const { activeEditor, setActiveEditor, activeGuardRef, switchEditor } =
        useEditorCoordinator<'profile' | 'run' | 'policy' | null>(null);

    return (
        <AuthenticatedLayout>
            <Head title="Payroll" />
            <div className="space-y-6">
                <PageHeader
                    title="Payroll"
                    description="THB payroll, effective-dated tax and social security policies, approvals, payslips and GL."
                    actions={
                        can.manage ? (
                            <div className="flex flex-wrap gap-2">
                                <PrimaryButton
                                    onClick={() => switchEditor('run')}
                                >
                                    Create Payroll Run
                                </PrimaryButton>
                                <button
                                    type="button"
                                    onClick={() => switchEditor('profile')}
                                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    New Employee Profile
                                </button>
                                <button
                                    type="button"
                                    onClick={() => switchEditor('policy')}
                                    className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    New Policy Version
                                </button>
                            </div>
                        ) : undefined
                    }
                />
                <div className="grid gap-6 xl:grid-cols-2">
                    {can.manage && (
                        <Card
                            title="Employee Compensation Profiles"
                            description="Base salary, statutory social security deduction, and disbursement settings."
                        >
                            <div className="flex h-full flex-col justify-between space-y-4">
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    Manage fixed compensation, allowances, and
                                    withholding rules across active employees.
                                </p>
                                <PrimaryButton
                                    onClick={() => switchEditor('profile')}
                                    className="w-fit"
                                >
                                    New Employee Profile
                                </PrimaryButton>
                            </div>
                        </Card>
                    )}
                    {can.manage && (
                        <Card
                            title="Payroll Runs & Processing"
                            description="Periodic salary disbursement cycles, approvals, and general ledger posting."
                        >
                            <div className="flex h-full flex-col justify-between space-y-4">
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    Initiate monthly or semi-monthly wage runs,
                                    run calculations, and export PND1/SSO
                                    reports.
                                </p>
                                <PrimaryButton
                                    onClick={() => switchEditor('run')}
                                    className="w-fit"
                                >
                                    Create Payroll Run
                                </PrimaryButton>
                            </div>
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
                                                    Mark Paid
                                                </button>
                                            )}
                                        {run.status === 'paid' && (
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
                    <Card
                        title="Policy Settings"
                        description="Statutory tax brackets and social security contribution rates."
                    >
                        <div className="space-y-4">
                            <div>
                                <PrimaryButton
                                    onClick={() => switchEditor('policy')}
                                >
                                    New Policy Version
                                </PrimaryButton>
                            </div>
                            <div className="grid gap-4 text-sm md:grid-cols-2">
                                <div className="rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                                    <div className="mb-2 font-medium text-slate-800 dark:text-slate-200">
                                        Tax Policy Versions
                                    </div>
                                    <div className="space-y-1 text-slate-600 dark:text-slate-400">
                                        {taxPolicies.map((p) => (
                                            <div key={p.id}>
                                                {p.effective_from} - {p.name}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                                    <div className="mb-2 font-medium text-slate-800 dark:text-slate-200">
                                        Social Security Versions
                                    </div>
                                    <div className="space-y-1 text-slate-600 dark:text-slate-400">
                                        {socialSecurityPolicies.map((p) => (
                                            <div key={p.id}>
                                                {p.effective_from} - {p.name} (
                                                {p.employee_rate}% / ceiling{' '}
                                                {money(p.wage_ceiling)})
                                            </div>
                                        ))}
                                    </div>
                                </div>
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

            {activeEditor === 'profile' && (
                <PayrollProfileDialog
                    open={true}
                    onRequestCloseRef={activeGuardRef}
                    onClose={() => {
                        activeGuardRef.current = null;
                        setActiveEditor(null);
                    }}
                    users={users}
                />
            )}
            {activeEditor === 'run' && (
                <PayrollRunDialog
                    open={true}
                    onRequestCloseRef={activeGuardRef}
                    onClose={() => {
                        activeGuardRef.current = null;
                        setActiveEditor(null);
                    }}
                    bankAccounts={bankAccounts}
                />
            )}
            {activeEditor === 'policy' && (
                <PayrollPolicyDialog
                    open={true}
                    onRequestCloseRef={activeGuardRef}
                    onClose={() => {
                        activeGuardRef.current = null;
                        setActiveEditor(null);
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
