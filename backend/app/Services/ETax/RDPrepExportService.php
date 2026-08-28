<?php

namespace App\Services\ETax;

use App\Models\Expense;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RDPrepExportService
{
    public function export(Organization $organization, string $form, ?string $dateFrom, ?string $dateTo): string
    {
        if (! in_array($form, ['pnd3', 'pnd53'], true)) {
            throw ValidationException::withMessages(['form' => 'Only ภ.ง.ด. 3 and ภ.ง.ด. 53 are supported.']);
        }
        if (! $organization->tax_id) {
            throw ValidationException::withMessages(['tax_id' => 'Organization tax ID is required for RD Prep export.']);
        }

        return $this->rows($organization, $form, $dateFrom, $dateTo)
            ->map(fn (Expense $expense) => implode('|', [
                $form,
                preg_replace('/\D/', '', $organization->tax_id),
                $expense->expense_date?->format('Ymd'),
                $expense->expense_no,
                preg_replace('/\D/', '', $expense->supplier?->tax_id ?: ''),
                $this->clean($expense->supplier?->name ?: ''),
                number_format((float) $expense->amount, 2, '.', ''),
                number_format((float) $expense->withholding_tax_amount, 2, '.', ''),
                number_format((float) $expense->withholding_tax_rate, 2, '.', ''),
            ]))->prepend('ERP_RD_PREP_DRAFT_V1|VERIFY_AGAINST_CURRENT_RD_SCHEMA_BEFORE_UPLOAD')->implode("\r\n")."\r\n";
    }

    /** @return Collection<int, Expense> */
    private function rows(Organization $organization, string $form, ?string $dateFrom, ?string $dateTo): Collection
    {
        return Expense::query()->where('org_id', $organization->id)->whereIn('status', ['approved', 'paid'])->where('withholding_tax_form', $form)->where('withholding_tax_amount', '>', 0)->with('supplier:id,name,tax_id')->when($dateFrom, fn ($query) => $query->whereDate('expense_date', '>=', $dateFrom))->when($dateTo, fn ($query) => $query->whereDate('expense_date', '<=', $dateTo))->orderBy('expense_date')->orderBy('expense_no')->get();
    }

    private function clean(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ' ', trim($value));
    }
}
