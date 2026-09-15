import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export type PettyCashMode = 'fund' | 'request' | 'reimbursement';
const settings = {
    fund: {
        title: 'New Fund',
        save: 'Create Fund',
        route: 'petty-cash.funds.store',
        status: 'Create an active petty cash fund',
    },
    request: {
        title: 'New Request',
        save: 'Submit Request',
        route: 'petty-cash.requests.store',
        status: 'Submit for approval before payment',
    },
    reimbursement: {
        title: 'Reimburse Fund',
        save: 'Record Reimbursement',
        route: 'petty-cash.reimbursements.store',
        status: 'Recording reimbursement posts to the general ledger',
    },
};
export default function PettyCashDialog({
    mode,
    funds,
    accounts,
    users,
    onClose,
}: {
    mode: PettyCashMode;
    funds: { id: string; fund_no: string; status: string }[];
    accounts: { id: string; bank_name: string; account_name: string }[];
    users: { id: string; name: string }[];
    onClose: () => void;
}) {
    const form = useForm({
        custodian_user_id: '',
        bank_account_id: '',
        imprest_amount: '',
        petty_cash_fund_id: '',
        amount: '',
        expense_date: '',
        purpose: '',
        reimbursed_at: '',
        note: '',
    });
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('petty_cash.manage');
    const guard = useDiscardGuard(form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const config = settings[mode];
    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canManage || form.processing) return;
        form.transform((data) =>
            mode === 'fund'
                ? {
                      custodian_user_id: data.custodian_user_id,
                      bank_account_id: data.bank_account_id,
                      imprest_amount: data.imprest_amount,
                  }
                : mode === 'request'
                  ? {
                        petty_cash_fund_id: data.petty_cash_fund_id,
                        amount: data.amount,
                        expense_date: data.expense_date,
                        purpose: data.purpose,
                    }
                  : {
                        petty_cash_fund_id: data.petty_cash_fund_id,
                        bank_account_id: data.bank_account_id,
                        amount: data.amount,
                        reimbursed_at: data.reimbursed_at,
                        note: data.note,
                    },
        );
        form.post(route(config.route), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: onClose,
        });
    }
    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title={config.title}
                breadcrumb="Treasury / Petty Cash"
                entityLabel="Petty cash"
                draftLabel={form.data.purpose || config.title}
                storageKey="petty-cash-editor-size"
                formId="petty-cash-editor"
                processing={form.processing}
                saveDisabled={!canManage}
                saveLabel={config.save}
                status={config.status}
                errors={form.errors}
                onSubmit={submit}
            >
                {(expanded) => (
                    <section
                        aria-label={config.title}
                        className={`grid gap-4 p-4 sm:grid-cols-2 sm:p-6 ${expanded ? 'lg:grid-cols-3' : ''}`}
                    >
                        {mode === 'fund' ? (
                            <EditorReference
                                id="petty-custodian"
                                label="Custodian"
                                value={form.data.custodian_user_id}
                                error={form.errors.custodian_user_id}
                                onChange={(v) =>
                                    form.setData('custodian_user_id', v)
                                }
                                options={users.map((user) => ({
                                    id: user.id,
                                    label: user.name,
                                }))}
                                required
                            />
                        ) : (
                            <EditorReference
                                id="petty-fund"
                                label="Fund"
                                value={form.data.petty_cash_fund_id}
                                error={form.errors.petty_cash_fund_id}
                                onChange={(v) =>
                                    form.setData('petty_cash_fund_id', v)
                                }
                                options={funds
                                    .filter((fund) => fund.status === 'active')
                                    .map((fund) => ({
                                        id: fund.id,
                                        label: fund.fund_no,
                                    }))}
                                required
                            />
                        )}
                        {mode !== 'request' && (
                            <EditorReference
                                id="petty-bank"
                                label={
                                    mode === 'fund'
                                        ? 'Funding Account'
                                        : 'Source Account'
                                }
                                value={form.data.bank_account_id}
                                error={form.errors.bank_account_id}
                                onChange={(v) =>
                                    form.setData('bank_account_id', v)
                                }
                                options={accounts.map((account) => ({
                                    id: account.id,
                                    label: `${account.bank_name} - ${account.account_name}`,
                                }))}
                            />
                        )}
                        {mode === 'fund' ? (
                            <EditorField
                                id="petty-imprest"
                                label="Imprest Amount"
                                value={form.data.imprest_amount}
                                error={form.errors.imprest_amount}
                                onChange={(v) =>
                                    form.setData('imprest_amount', v)
                                }
                                type="number"
                                min={0.01}
                                step="0.01"
                                required
                            />
                        ) : (
                            <EditorField
                                id="petty-amount"
                                label="Amount"
                                value={form.data.amount}
                                error={form.errors.amount}
                                onChange={(v) => form.setData('amount', v)}
                                type="number"
                                min={0.01}
                                step="0.01"
                                required
                            />
                        )}
                        {mode === 'request' && (
                            <>
                                <EditorField
                                    id="petty-expense-date"
                                    label="Expense Date"
                                    value={form.data.expense_date}
                                    error={form.errors.expense_date}
                                    onChange={(v) =>
                                        form.setData('expense_date', v)
                                    }
                                    type="date"
                                    required
                                />
                                <EditorField
                                    id="petty-purpose"
                                    label="Purpose"
                                    value={form.data.purpose}
                                    error={form.errors.purpose}
                                    onChange={(v) => form.setData('purpose', v)}
                                    multiline
                                    maxLength={2000}
                                    required
                                />
                            </>
                        )}
                        {mode === 'reimbursement' && (
                            <>
                                <EditorField
                                    id="petty-reimbursed-date"
                                    label="Reimbursement Date"
                                    value={form.data.reimbursed_at}
                                    error={form.errors.reimbursed_at}
                                    onChange={(v) =>
                                        form.setData('reimbursed_at', v)
                                    }
                                    type="date"
                                    required
                                />
                                <EditorField
                                    id="petty-note"
                                    label="Note"
                                    value={form.data.note}
                                    error={form.errors.note}
                                    onChange={(v) => form.setData('note', v)}
                                    multiline
                                    maxLength={2000}
                                />
                            </>
                        )}
                    </section>
                )}
            </CreateEditDialog>
        </>
    );
}
