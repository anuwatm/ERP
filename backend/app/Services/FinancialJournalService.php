<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\CreditDebitNote;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\PettyCashReimbursement;
use App\Models\VendorPayment;

class FinancialJournalService
{
    public function __construct(private readonly JournalPostingService $journals) {}

    public function postInvoice(Invoice $invoice, ?string $actorId): JournalEntry
    {
        $tax = round((float) $invoice->base_tax_amount ?: (float) $invoice->tax_amount, 2);
        $total = round((float) $invoice->base_total ?: (float) $invoice->total, 2);

        return $this->journals->post($invoice->org_id, $actorId, 'invoice', $invoice->id, 'issued', $invoice->issue_date->toDateString(), 'Invoice '.$invoice->invoice_no, array_filter([
            ['account_code' => '1120', 'debit' => $total, 'description' => 'Accounts receivable'],
            ['account_code' => '4100', 'credit' => $total - $tax, 'description' => 'Sales revenue'],
            $tax > 0 ? ['account_code' => '2130', 'credit' => $tax, 'description' => 'Output VAT'] : null,
        ]));
    }

    public function reverseInvoice(Invoice $invoice, ?string $actorId, string $date): ?JournalEntry
    {
        return $this->reverseSource($invoice->org_id, 'invoice', $invoice->id, $actorId, $date, 'Invoice void', 'voided');
    }

    public function postPayment(Payment $payment, ?string $actorId): JournalEntry
    {
        $cashAccount = $this->cashAccount($payment->bank_account_id, '1100');
        $isReceipt = $payment->entry_type === 'receipt';
        $cashAmount = round((float) $payment->base_amount, 2);
        $receivableAmount = round((float) $payment->invoice_base_amount, 2);
        $difference = round($cashAmount - $receivableAmount, 2);

        $lines = $isReceipt ? [
            ['account_code' => $cashAccount, 'debit' => $cashAmount, 'description' => 'Cash received'],
            ['account_code' => '1120', 'credit' => $receivableAmount, 'description' => 'Accounts receivable'],
        ] : [
            ['account_code' => '1120', 'debit' => $receivableAmount, 'description' => 'Accounts receivable'],
            ['account_code' => $cashAccount, 'credit' => $cashAmount, 'description' => 'Cash reversed'],
        ];
        if ($difference !== 0.0) {
            $gain = $difference > 0;
            $lines[] = $isReceipt
                ? ['account_code' => $gain ? '4300' : '5400', $gain ? 'credit' : 'debit' => abs($difference), 'description' => 'Realized foreign exchange difference']
                : ['account_code' => $gain ? '4300' : '5400', $gain ? 'debit' : 'credit' => abs($difference), 'description' => 'Reversed foreign exchange difference'];
        }

        return $this->journals->post($payment->org_id, $actorId, 'payment', $payment->id, $payment->entry_type, $payment->payment_date->toDateString(), 'Payment '.$payment->entry_type, $lines);
    }

    public function postExpenseApproval(Expense $expense, ?string $actorId): JournalEntry
    {
        $tax = round((float) $expense->base_tax_amount ?: (float) $expense->tax_amount, 2);
        $baseAmount = (float) $expense->base_amount ?: (float) $expense->amount;
        $gross = $expense->tax_mode === 'exclusive' ? round($baseAmount + $tax, 2) : round($baseAmount, 2);
        $expenseAmount = round($gross - $tax, 2);

        return $this->journals->post($expense->org_id, $actorId, 'expense', $expense->id, 'approved', $expense->expense_date->toDateString(), 'Expense '.$expense->expense_no.' approved', array_filter([
            ['account_code' => '5200', 'debit' => $expenseAmount, 'description' => 'Operating expense'],
            $tax > 0 ? ['account_code' => '1130', 'debit' => $tax, 'description' => 'Input VAT'] : null,
            ['account_code' => '2110', 'credit' => $gross, 'description' => 'Accounts payable'],
        ]));
    }

    public function postExpensePayment(Expense $expense, ?string $actorId): JournalEntry
    {
        $tax = round((float) $expense->base_tax_amount ?: (float) $expense->tax_amount, 2);
        $baseAmount = (float) $expense->base_amount ?: (float) $expense->amount;
        $gross = $expense->tax_mode === 'exclusive' ? round($baseAmount + $tax, 2) : round($baseAmount, 2);

        return $this->journals->post($expense->org_id, $actorId, 'expense', $expense->id, 'paid', $expense->paid_at->toDateString(), 'Expense '.$expense->expense_no.' paid', [
            ['account_code' => '2110', 'debit' => $gross, 'description' => 'Accounts payable'],
            ['account_code' => $this->cashAccount($expense->bank_account_id, '1100'), 'credit' => $gross, 'description' => 'Cash paid'],
        ]);
    }

    public function postVendorPayment(VendorPayment $payment, ?string $actorId): JournalEntry
    {
        $cash = round((float) $payment->base_amount, 2);
        $payable = round((float) $payment->expense_base_amount, 2);
        $difference = round($cash - $payable, 2);
        $isPayment = $payment->entry_type === 'payment';
        $lines = $isPayment ? [
            ['account_code' => '2110', 'debit' => $payable, 'description' => 'Accounts payable settlement'],
            ['account_code' => $this->cashAccount($payment->bank_account_id, '1100'), 'credit' => $cash, 'description' => 'Cash paid'],
        ] : [
            ['account_code' => $this->cashAccount($payment->bank_account_id, '1100'), 'debit' => $cash, 'description' => 'Cash payment reversal'],
            ['account_code' => '2110', 'credit' => $payable, 'description' => 'Accounts payable restored'],
        ];
        if ($difference !== 0.0) {
            $loss = $difference > 0;
            $lines[] = $isPayment
                ? ['account_code' => $loss ? '5400' : '4300', $loss ? 'debit' : 'credit' => abs($difference), 'description' => 'Realized foreign exchange difference']
                : ['account_code' => $loss ? '5400' : '4300', $loss ? 'credit' : 'debit' => abs($difference), 'description' => 'Reversed foreign exchange difference'];
        }

        return $this->journals->post($payment->org_id, $actorId, 'vendor_payment', $payment->id, $payment->entry_type, $payment->payment_date->toDateString(), 'Vendor payment '.$payment->entry_type, $lines);
    }

    public function postBankTransfer(BankTransfer $transfer, ?string $actorId): JournalEntry
    {
        $source = $this->cashAccount($transfer->source_bank_account_id, '1110');
        $destination = $this->cashAccount($transfer->destination_bank_account_id, '1110');
        $difference = round((float) $transfer->destination_base_amount - (float) $transfer->source_base_amount, 2);
        $lines = [
            ['account_code' => $destination, 'debit' => $transfer->destination_base_amount, 'description' => 'Internal bank transfer received'],
            ['account_code' => $source, 'credit' => $transfer->source_base_amount, 'description' => 'Internal bank transfer sent'],
        ];
        if ($difference !== 0.0) {
            $gain = $difference > 0;
            $lines[] = [
                'account_code' => $gain ? '4300' : '5400',
                $gain ? 'credit' : 'debit' => abs($difference),
                'description' => 'Realized foreign exchange difference',
            ];
        }

        return $this->journals->post($transfer->org_id, $actorId, 'bank_transfer', $transfer->id, 'posted', $transfer->transfer_date->toDateString(), 'Internal bank transfer', $lines);
    }

    public function reverseExpenseApproval(Expense $expense, ?string $actorId, string $date): ?JournalEntry
    {
        return $this->reverseSource($expense->org_id, 'expense', $expense->id, $actorId, $date, 'Expense rejected', 'rejected');
    }

    public function postCreditDebitNote(CreditDebitNote $note, ?string $actorId): JournalEntry
    {
        $tax = round((float) $note->tax_amount, 2);
        $total = round((float) $note->total, 2);
        $revenue = round($total - $tax, 2);
        $isCredit = $note->type === 'credit';

        return $this->journals->post($note->org_id, $actorId, 'credit_debit_note', $note->id, 'issued', $note->issue_date->toDateString(), strtoupper($note->type).' note '.$note->note_no, array_filter($isCredit ? [
            ['account_code' => '4100', 'debit' => $revenue, 'description' => 'Sales return'],
            $tax > 0 ? ['account_code' => '2130', 'debit' => $tax, 'description' => 'Output VAT reversal'] : null,
            ['account_code' => '1120', 'credit' => $total, 'description' => 'Accounts receivable'],
        ] : [
            ['account_code' => '1120', 'debit' => $total, 'description' => 'Accounts receivable'],
            ['account_code' => '4100', 'credit' => $revenue, 'description' => 'Additional revenue'],
            $tax > 0 ? ['account_code' => '2130', 'credit' => $tax, 'description' => 'Output VAT'] : null,
        ]));
    }

    public function postGoodsReceipt(GoodsReceipt $receipt, ?string $actorId): JournalEntry
    {
        $receipt->loadMissing('items');
        $inventory = round((float) $receipt->items->sum(fn ($item) => (float) $item->base_line_total - (float) $item->base_tax_amount), 2);
        $inputVat = round((float) $receipt->items->sum('base_tax_amount'), 2);

        return $this->journals->post($receipt->org_id, $actorId, 'goods_receipt', $receipt->id, 'received', $receipt->received_date->toDateString(), 'Goods receipt '.$receipt->grn_no, array_filter([
            ['account_code' => '1140', 'debit' => $inventory, 'description' => 'Inventory received'],
            $inputVat > 0 ? ['account_code' => '1130', 'debit' => $inputVat, 'description' => 'Input VAT'] : null,
            ['account_code' => '1150', 'credit' => $inventory + $inputVat, 'description' => 'GRNI'],
        ]));
    }

    public function postPettyCashReimbursement(PettyCashReimbursement $reimbursement, ?string $actorId): JournalEntry
    {
        return $this->journals->post($reimbursement->org_id, $actorId, 'petty_cash_reimbursement', $reimbursement->id, 'reimbursed', $reimbursement->reimbursed_at->toDateString(), 'Petty cash reimbursement', [
            ['account_code' => '1112', 'debit' => $reimbursement->amount, 'description' => 'Petty cash replenishment'],
            ['account_code' => $reimbursement->bank_account_id ? '1110' : '1100', 'credit' => $reimbursement->amount, 'description' => 'Cash source'],
        ]);
    }

    public function postPayrollApproval(PayrollRun $run, ?string $actorId): JournalEntry
    {
        $gross = round((float) $run->gross_amount, 2);
        $employeeSocial = round((float) $run->employee_social_security_amount, 2);
        $employerSocial = round((float) $run->employer_social_security_amount, 2);
        $tax = round((float) $run->withholding_tax_amount, 2);
        $net = round((float) $run->net_pay_amount, 2);
        $otherDeductions = round(max(0, $gross - $employeeSocial - $tax - $net), 2);

        return $this->journals->post($run->org_id, $actorId, 'payroll_run', $run->id, 'approved', $run->payment_date->toDateString(), 'Payroll '.$run->run_no.' approved', array_filter([
            $gross > 0 ? ['account_code' => '5500', 'debit' => $gross, 'description' => 'Salary expense'] : null,
            $employerSocial > 0 ? ['account_code' => '5510', 'debit' => $employerSocial, 'description' => 'Employer social security expense'] : null,
            $net > 0 ? ['account_code' => '2140', 'credit' => $net, 'description' => 'Payroll payable'] : null,
            $tax > 0 ? ['account_code' => '2150', 'credit' => $tax, 'description' => 'Withholding tax payable'] : null,
            ($employeeSocial + $employerSocial) > 0 ? ['account_code' => '2160', 'credit' => $employeeSocial + $employerSocial, 'description' => 'Social security payable'] : null,
            $otherDeductions > 0 ? ['account_code' => '2170', 'credit' => $otherDeductions, 'description' => 'Other payroll deductions payable'] : null,
        ]));
    }

    public function postPayrollPayment(PayrollRun $run, ?string $actorId): JournalEntry
    {
        $net = round((float) $run->net_pay_amount, 2);

        return $this->journals->post($run->org_id, $actorId, 'payroll_run', $run->id, 'paid', $run->payment_date->toDateString(), 'Payroll '.$run->run_no.' paid', [
            ['account_code' => '2140', 'debit' => $net, 'description' => 'Payroll payable settlement'],
            ['account_code' => $this->cashAccount($run->bank_account_id, '1100'), 'credit' => $net, 'description' => 'Payroll payment'],
        ]);
    }

    private function reverseSource(string $orgId, string $sourceType, string $sourceId, ?string $actorId, string $date, string $reason, string $event): ?JournalEntry
    {
        $entry = JournalEntry::where('org_id', $orgId)->where('source_type', $sourceType)->where('source_id', $sourceId)->where('posting_event', 'issued')->first()
            ?? JournalEntry::where('org_id', $orgId)->where('source_type', $sourceType)->where('source_id', $sourceId)->where('posting_event', 'approved')->first();

        return $entry ? $this->journals->reverse($entry, $actorId, $date, $reason, $event) : null;
    }

    private function cashAccount(?string $bankAccountId, string $fallback): string
    {
        if (! $bankAccountId) {
            return $fallback;
        }

        return BankAccount::with('chartOfAccount')->find($bankAccountId)?->chartOfAccount?->code ?: '1110';
    }
}
