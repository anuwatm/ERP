<?php

namespace App\Services;

use App\Models\CreditDebitNote;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PettyCashReimbursement;

class FinancialJournalService
{
    public function __construct(private readonly JournalPostingService $journals) {}

    public function postInvoice(Invoice $invoice, ?string $actorId): JournalEntry
    {
        $tax = round((float) $invoice->tax_amount, 2);
        $total = round((float) $invoice->total, 2);

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
        $cashAccount = $payment->bank_account_id ? '1110' : '1100';
        $isReceipt = $payment->entry_type === 'receipt';

        return $this->journals->post($payment->org_id, $actorId, 'payment', $payment->id, $payment->entry_type, $payment->payment_date->toDateString(), 'Payment '.$payment->entry_type, $isReceipt ? [
            ['account_code' => $cashAccount, 'debit' => $payment->amount, 'description' => 'Cash received'],
            ['account_code' => '1120', 'credit' => $payment->amount, 'description' => 'Accounts receivable'],
        ] : [
            ['account_code' => '1120', 'debit' => $payment->amount, 'description' => 'Accounts receivable'],
            ['account_code' => $cashAccount, 'credit' => $payment->amount, 'description' => 'Cash reversed'],
        ]);
    }

    public function postExpenseApproval(Expense $expense, ?string $actorId): JournalEntry
    {
        $tax = round((float) $expense->tax_amount, 2);
        $gross = $expense->tax_mode === 'exclusive' ? round((float) $expense->amount + $tax, 2) : round((float) $expense->amount, 2);
        $expenseAmount = round($gross - $tax, 2);

        return $this->journals->post($expense->org_id, $actorId, 'expense', $expense->id, 'approved', $expense->expense_date->toDateString(), 'Expense '.$expense->expense_no.' approved', array_filter([
            ['account_code' => '5200', 'debit' => $expenseAmount, 'description' => 'Operating expense'],
            $tax > 0 ? ['account_code' => '1130', 'debit' => $tax, 'description' => 'Input VAT'] : null,
            ['account_code' => '2110', 'credit' => $gross, 'description' => 'Accounts payable'],
        ]));
    }

    public function postExpensePayment(Expense $expense, ?string $actorId): JournalEntry
    {
        $tax = round((float) $expense->tax_amount, 2);
        $gross = $expense->tax_mode === 'exclusive' ? round((float) $expense->amount + $tax, 2) : round((float) $expense->amount, 2);

        return $this->journals->post($expense->org_id, $actorId, 'expense', $expense->id, 'paid', $expense->paid_at->toDateString(), 'Expense '.$expense->expense_no.' paid', [
            ['account_code' => '2110', 'debit' => $gross, 'description' => 'Accounts payable'],
            ['account_code' => $expense->bank_account_id ? '1110' : '1100', 'credit' => $gross, 'description' => 'Cash paid'],
        ]);
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
        $inventory = round((float) $receipt->items->sum(fn ($item) => (float) $item->line_total - (float) $item->tax_amount), 2);
        $inputVat = round((float) $receipt->items->sum('tax_amount'), 2);

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

    private function reverseSource(string $orgId, string $sourceType, string $sourceId, ?string $actorId, string $date, string $reason, string $event): ?JournalEntry
    {
        $entry = JournalEntry::where('org_id', $orgId)->where('source_type', $sourceType)->where('source_id', $sourceId)->where('posting_event', 'issued')->first()
            ?? JournalEntry::where('org_id', $orgId)->where('source_type', $sourceType)->where('source_id', $sourceId)->where('posting_event', 'approved')->first();

        return $entry ? $this->journals->reverse($entry, $actorId, $date, $reason, $event) : null;
    }
}
