import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type User = { id: string; name: string; email: string };
type BankAccount = { id: string; bank_name: string; account_name: string };

// -------------------------------------------------------------
// 1. Employee Payroll Profile Dialog
// -------------------------------------------------------------
export function PayrollProfileDialog({
    open,
    onClose,
    onRequestCloseRef,
    users,
}: {
    open: boolean;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
    users: User[];
}) {
    const initialProfile = {
        user_id: '',
        monthly_salary: '',
        fixed_allowance: '0',
        fixed_deduction: '0',
        annual_tax_allowance: '60000',
        payment_method: 'bank_transfer',
        payment_reference: '',
        social_security_enabled: true,
        status: 'active',
    };

    const form = useForm({ ...initialProfile });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        open &&
        (form.data.user_id !== initialProfile.user_id ||
            form.data.monthly_salary !== initialProfile.monthly_salary ||
            form.data.fixed_allowance !== initialProfile.fixed_allowance ||
            form.data.fixed_deduction !== initialProfile.fixed_deduction ||
            form.data.annual_tax_allowance !==
                initialProfile.annual_tax_allowance ||
            form.data.payment_method !== initialProfile.payment_method ||
            form.data.payment_reference !== initialProfile.payment_reference ||
            form.data.social_security_enabled !==
                initialProfile.social_security_enabled ||
            form.data.status !== initialProfile.status);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    const guard = useDiscardGuard(isDirty, form.processing);

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.user_id || !form.data.monthly_salary)
            return;
        form.transform((data) => ({
            ...data,
            social_security_enabled: data.social_security_enabled ? '1' : '0',
        }));
        form.post(route('payroll.profiles.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title="Employee Payroll Profile"
                breadcrumb="Finance / Payroll / Profiles"
                entityLabel="Payroll Profile"
                draftLabel={
                    form.data.user_id ? 'Employee Profile' : 'New Profile'
                }
                storageKey="payroll-profile-editor-size"
                formId="payroll-profile-form"
                processing={form.processing}
                saveDisabled={!form.data.user_id || !form.data.monthly_salary}
                saveLabel="Save Profile"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorReference
                            id="profile-employee-user-id"
                            label="Employee"
                            required
                            value={form.data.user_id}
                            error={form.errors.user_id}
                            emptyLabel="Select employee"
                            options={users.map((u) => ({
                                id: u.id,
                                label: `${u.name} (${u.email})`,
                            }))}
                            onChange={(value) => form.setData('user_id', value)}
                        />
                        <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            <h4 className="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Salary & Recurring Items
                            </h4>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <EditorField
                                    id="monthly-salary"
                                    label="Monthly Salary (THB)"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    required
                                    value={form.data.monthly_salary}
                                    error={form.errors.monthly_salary}
                                    onChange={(value) =>
                                        form.setData('monthly_salary', value)
                                    }
                                />
                                <EditorField
                                    id="fixed-allowance"
                                    label="Fixed Allowance"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.fixed_allowance}
                                    error={form.errors.fixed_allowance}
                                    onChange={(value) =>
                                        form.setData('fixed_allowance', value)
                                    }
                                />
                                <EditorField
                                    id="fixed-deduction"
                                    label="Fixed Deduction"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.fixed_deduction}
                                    error={form.errors.fixed_deduction}
                                    onChange={(value) =>
                                        form.setData('fixed_deduction', value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            <h4 className="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Disbursement & Statutory Withholding
                            </h4>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <EditorField
                                    id="annual-tax-allowance"
                                    label="Annual Tax Allowance"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.annual_tax_allowance}
                                    error={form.errors.annual_tax_allowance}
                                    onChange={(value) =>
                                        form.setData(
                                            'annual_tax_allowance',
                                            value,
                                        )
                                    }
                                />
                                <EditorReference
                                    id="profile-payment-method"
                                    label="Payment Method"
                                    required
                                    value={form.data.payment_method}
                                    error={form.errors.payment_method}
                                    options={[
                                        {
                                            id: 'bank_transfer',
                                            label: 'Bank Transfer',
                                        },
                                        { id: 'cash', label: 'Cash' },
                                    ]}
                                    onChange={(value) =>
                                        form.setData('payment_method', value)
                                    }
                                />
                                <EditorField
                                    id="payment-reference"
                                    label="Bank Account / Reference"
                                    value={form.data.payment_reference}
                                    error={form.errors.payment_reference}
                                    onChange={(value) =>
                                        form.setData('payment_reference', value)
                                    }
                                />
                            </div>
                            <div className="mt-3">
                                <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={
                                            form.data.social_security_enabled
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'social_security_enabled',
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                    <span>
                                        Enable Thai Social Security (SSO)
                                        statutory withholding
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 2. Payroll Run Dialog
// -------------------------------------------------------------
export function PayrollRunDialog({
    open,
    onClose,
    onRequestCloseRef,
    bankAccounts,
}: {
    open: boolean;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
    bankAccounts: BankAccount[];
}) {
    const initialRun = {
        period_start: '',
        period_end: '',
        payment_date: '',
        bank_account_id: '',
    };

    const form = useForm({ ...initialRun });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        open &&
        (form.data.period_start !== initialRun.period_start ||
            form.data.period_end !== initialRun.period_end ||
            form.data.payment_date !== initialRun.payment_date ||
            form.data.bank_account_id !== initialRun.bank_account_id);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    const guard = useDiscardGuard(isDirty, form.processing);

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.period_start ||
            !form.data.period_end ||
            !form.data.payment_date
        )
            return;

        form.post(route('payroll.runs.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title="Create Payroll Run"
                breadcrumb="Finance / Payroll / Batches"
                entityLabel="Payroll Run"
                draftLabel={
                    form.data.period_start
                        ? `${form.data.period_start} to ${form.data.period_end}`
                        : 'New Payroll Run'
                }
                storageKey="payroll-run-editor-size"
                formId="payroll-run-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.period_start ||
                    !form.data.period_end ||
                    !form.data.payment_date
                }
                saveLabel="Create Run"
                status="Draft / Uncalculated"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="run-period-start"
                                label="Period Start"
                                type="date"
                                required
                                value={form.data.period_start}
                                error={form.errors.period_start}
                                onChange={(value) =>
                                    form.setData('period_start', value)
                                }
                            />
                            <EditorField
                                id="run-period-end"
                                label="Period End"
                                type="date"
                                required
                                value={form.data.period_end}
                                error={form.errors.period_end}
                                onChange={(value) =>
                                    form.setData('period_end', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="run-payment-date"
                                label="Payment Date"
                                type="date"
                                required
                                value={form.data.payment_date}
                                error={form.errors.payment_date}
                                onChange={(value) =>
                                    form.setData('payment_date', value)
                                }
                            />
                            <EditorReference
                                id="run-bank-account-id"
                                label="Disbursement Account"
                                value={form.data.bank_account_id}
                                error={form.errors.bank_account_id}
                                emptyLabel="Cash on hand"
                                options={bankAccounts.map((a) => ({
                                    id: a.id,
                                    label: `${a.bank_name} - ${a.account_name}`,
                                }))}
                                onChange={(value) =>
                                    form.setData('bank_account_id', value)
                                }
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 3. Payroll Policy Dialog
// -------------------------------------------------------------
export function PayrollPolicyDialog({
    open,
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const initialPolicy = {
        effective_from: '',
        employment_expense_rate: '50',
        employment_expense_cap: '100000',
        wage_ceiling: '17500',
        employee_rate: '5',
        employer_rate: '5',
        source_url: '',
        brackets_json:
            '[{"up_to":150000,"rate":0},{"up_to":300000,"rate":5},{"up_to":500000,"rate":10},{"up_to":750000,"rate":15},{"up_to":1000000,"rate":20},{"up_to":2000000,"rate":25},{"up_to":5000000,"rate":30},{"up_to":null,"rate":35}]',
    };

    const form = useForm({ ...initialPolicy });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        open &&
        (form.data.effective_from !== initialPolicy.effective_from ||
            form.data.employment_expense_rate !==
                initialPolicy.employment_expense_rate ||
            form.data.employment_expense_cap !==
                initialPolicy.employment_expense_cap ||
            form.data.wage_ceiling !== initialPolicy.wage_ceiling ||
            form.data.employee_rate !== initialPolicy.employee_rate ||
            form.data.employer_rate !== initialPolicy.employer_rate ||
            form.data.source_url !== initialPolicy.source_url ||
            form.data.brackets_json !== initialPolicy.brackets_json);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetSubmissionKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    const guard = useDiscardGuard(isDirty, form.processing);

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.effective_from) return;

        form.post(route('payroll.policies.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(handleClose)}
                title="Create Payroll Policy Version"
                breadcrumb="Finance / Payroll / Statutory Policies"
                entityLabel="Policy Version"
                draftLabel={
                    form.data.effective_from
                        ? `Effective ${form.data.effective_from}`
                        : 'New Policy Version'
                }
                storageKey="payroll-policy-editor-size"
                formId="payroll-policy-form"
                processing={form.processing}
                saveDisabled={!form.data.effective_from}
                saveLabel="Save Policy Version"
                status="Configuration"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorField
                            id="policy-effective-from"
                            label="Effective From Date"
                            type="date"
                            required
                            value={form.data.effective_from}
                            error={form.errors.effective_from}
                            onChange={(value) =>
                                form.setData('effective_from', value)
                            }
                        />
                        <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            <h4 className="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Employment Expense Deduction
                            </h4>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <EditorField
                                    id="expense-rate"
                                    label="Expense Rate (%)"
                                    type="number"
                                    step="0.01"
                                    required
                                    value={form.data.employment_expense_rate}
                                    error={form.errors.employment_expense_rate}
                                    onChange={(value) =>
                                        form.setData(
                                            'employment_expense_rate',
                                            value,
                                        )
                                    }
                                />
                                <EditorField
                                    id="expense-cap"
                                    label="Expense Cap (THB)"
                                    type="number"
                                    step="0.01"
                                    required
                                    value={form.data.employment_expense_cap}
                                    error={form.errors.employment_expense_cap}
                                    onChange={(value) =>
                                        form.setData(
                                            'employment_expense_cap',
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700 dark:bg-slate-900/50">
                            <h4 className="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Social Security Rates
                            </h4>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <EditorField
                                    id="wage-ceiling"
                                    label="Wage Ceiling (THB)"
                                    type="number"
                                    step="0.01"
                                    required
                                    value={form.data.wage_ceiling}
                                    error={form.errors.wage_ceiling}
                                    onChange={(value) =>
                                        form.setData('wage_ceiling', value)
                                    }
                                />
                                <EditorField
                                    id="employee-rate"
                                    label="Employee Rate (%)"
                                    type="number"
                                    step="0.01"
                                    required
                                    value={form.data.employee_rate}
                                    error={form.errors.employee_rate}
                                    onChange={(value) =>
                                        form.setData('employee_rate', value)
                                    }
                                />
                                <EditorField
                                    id="employer-rate"
                                    label="Employer Rate (%)"
                                    type="number"
                                    step="0.01"
                                    required
                                    value={form.data.employer_rate}
                                    error={form.errors.employer_rate}
                                    onChange={(value) =>
                                        form.setData('employer_rate', value)
                                    }
                                />
                            </div>
                        </div>
                        <EditorField
                            id="policy-source-url"
                            label="Official Source URL"
                            type="url"
                            value={form.data.source_url}
                            error={form.errors.source_url}
                            onChange={(value) =>
                                form.setData('source_url', value)
                            }
                        />
                        <EditorField
                            id="brackets-json"
                            label="Tax Brackets (JSON)"
                            multiline
                            required
                            value={form.data.brackets_json}
                            error={form.errors.brackets_json}
                            onChange={(value) =>
                                form.setData('brackets_json', value)
                            }
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
