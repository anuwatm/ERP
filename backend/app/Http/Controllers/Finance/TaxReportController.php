<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxReportController extends Controller
{
    private const SALES_STATUSES = ['sent', 'partially_paid', 'paid', 'overdue'];

    private const PURCHASE_STATUSES = ['approved', 'partially_received', 'received', 'closed'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return Inertia::render('Finance/TaxReports', [
            'filters' => $filters,
            'branches' => Branch::where('org_id', $request->user()->org_id)->orderBy('code')->get(['id', 'code', 'name']),
            'customers' => Customer::where('org_id', $request->user()->org_id)->orderBy('company_name')->get(['id', 'customer_code', 'company_name']),
            'suppliers' => Supplier::where('org_id', $request->user()->org_id)->orderBy('name')->get(['id', 'supplier_code', 'name']),
            'salesRows' => $this->salesRows($request, $filters),
            'purchaseRows' => $this->purchaseRows($request, $filters),
            'withholdingRows' => $this->withholdingRows($request, $filters),
            'arAgingRows' => $this->arAgingRows($request, $filters),
            'apAgingRows' => $this->apAgingRows($request, $filters),
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['sales', 'purchase', 'withholding'], true), HttpResponse::HTTP_NOT_FOUND);

        $filters = $this->filters($request);
        $rows = match ($type) {
            'sales' => $this->salesRows($request, $filters),
            'purchase' => $this->purchaseRows($request, $filters),
            default => $this->withholdingRows($request, $filters),
        };

        return response()->streamDownload(function () use ($rows, $type): void {
            $handle = fopen('php://output', 'w');
            $header = $type === 'withholding'
                ? ['date', 'document_no', 'supplier', 'tax_id', 'form', 'base_amount', 'wht_rate', 'wht_amount']
                : ['date', 'document_no', 'partner', 'tax_id', 'tax_mode', 'taxable_base', 'tax_amount', 'total'];
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                $line = $type === 'withholding' ? [
                    $row['date'],
                    $row['document_no'],
                    $row['partner'],
                    $row['tax_id'],
                    $row['form'],
                    $row['base_amount'],
                    $row['wht_rate'],
                    $row['wht_amount'],
                ] : [
                    $row['date'],
                    $row['document_no'],
                    $row['partner'],
                    $row['tax_id'],
                    $row['tax_mode'],
                    $row['taxable_base'],
                    $row['tax_amount'],
                    $row['total'],
                ];

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $type.'-tax-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function excel(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['sales', 'purchase', 'withholding', 'ar-aging', 'ap-aging'], true), HttpResponse::HTTP_NOT_FOUND);

        $filters = $this->filters($request);
        $rows = match ($type) {
            'sales' => $this->salesRows($request, $filters),
            'purchase' => $this->purchaseRows($request, $filters),
            'withholding' => $this->withholdingRows($request, $filters),
            'ar-aging' => $this->arAgingRows($request, $filters),
            default => $this->apAgingRows($request, $filters),
        };

        return response()->streamDownload(function () use ($rows, $type): void {
            echo '<html><head><meta charset="utf-8"></head><body>';
            echo '<table border="1"><thead><tr>';
            $headers = $this->reportHeaders($type);
            foreach ($headers as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($headers as $header) {
                    echo '<td>'.e($row[$header] ?? '').'</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $type.'-report.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /**
     * @return array{date_from: string|null, date_to: string|null, branch_id: string|null, status: string|null, customer_id: string|null, supplier_id: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:40'],
            'customer_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'],
        ]);

        return [
            'date_from' => $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? now()->endOfMonth()->toDateString(),
            'branch_id' => $validated['branch_id'] ?? null,
            'status' => $validated['status'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function salesRows(Request $request, array $filters): array
    {
        return Invoice::query()
            ->where('org_id', $request->user()->org_id)
            ->whereIn('status', self::SALES_STATUSES)
            ->with(['customer:id,company_name,tax_id', 'branch:id,code,name'])
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('issue_date', '<=', $date))
            ->when($filters['branch_id'], fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'], fn ($query, $status) => in_array($status, self::SALES_STATUSES, true) ? $query->where('status', $status) : $query)
            ->when($filters['customer_id'], fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->orderBy('issue_date')
            ->orderBy('invoice_no')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'date' => $invoice->issue_date?->toDateString() ?: '',
                'document_no' => $invoice->invoice_no,
                'partner' => $invoice->customer?->company_name ?: '-',
                'tax_id' => $invoice->customer?->tax_id ?: '',
                'branch' => $invoice->branch ? trim($invoice->branch->code.' '.$invoice->branch->name) : '',
                'tax_mode' => $invoice->tax_mode,
                'taxable_base' => $this->money((float) $invoice->total - (float) $invoice->tax_amount),
                'tax_amount' => $this->money($invoice->tax_amount),
                'total' => $this->money($invoice->total),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function purchaseRows(Request $request, array $filters): array
    {
        return PurchaseOrder::query()
            ->where('org_id', $request->user()->org_id)
            ->whereIn('status', self::PURCHASE_STATUSES)
            ->with('supplier:id,name,tax_id')
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('order_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('order_date', '<=', $date))
            ->when($filters['status'], fn ($query, $status) => in_array($status, self::PURCHASE_STATUSES, true) ? $query->where('status', $status) : $query)
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->orderBy('order_date')
            ->orderBy('po_no')
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                'date' => $po->order_date?->toDateString() ?: '',
                'document_no' => $po->po_no,
                'partner' => $po->supplier?->name ?: '-',
                'tax_id' => $po->supplier?->tax_id ?: '',
                'branch' => '',
                'tax_mode' => $po->tax_mode,
                'taxable_base' => $this->money((float) $po->total - (float) $po->tax_amount),
                'tax_amount' => $this->money($po->tax_amount),
                'total' => $this->money($po->total),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function withholdingRows(Request $request, array $filters): array
    {
        return Expense::query()
            ->where('org_id', $request->user()->org_id)
            ->whereIn('status', ['approved', 'paid'])
            ->where('withholding_tax_amount', '>', 0)
            ->with('supplier:id,name,tax_id')
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('expense_date', '<=', $date))
            ->when($filters['status'], fn ($query, $status) => in_array($status, ['approved', 'paid'], true) ? $query->where('status', $status) : $query)
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->orderBy('expense_date')
            ->orderBy('expense_no')
            ->get()
            ->map(fn (Expense $expense) => [
                'date' => $expense->expense_date?->toDateString() ?: '',
                'document_no' => $expense->expense_no,
                'partner' => $expense->supplier?->name ?: '-',
                'tax_id' => $expense->supplier?->tax_id ?: '',
                'form' => $expense->withholding_tax_form ?: 'pnd53',
                'base_amount' => $this->money($expense->amount),
                'wht_rate' => $this->money($expense->withholding_tax_rate),
                'wht_amount' => $this->money($expense->withholding_tax_amount),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function arAgingRows(Request $request, array $filters): array
    {
        $asOf = $filters['date_to'] ?: now()->toDateString();

        return Invoice::query()
            ->where('org_id', $request->user()->org_id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer:id,company_name')
            ->when($filters['branch_id'], fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['customer_id'], fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->whereDate('issue_date', '<=', $asOf)
            ->orderBy('due_date')
            ->orderBy('invoice_no')
            ->get()
            ->map(fn (Invoice $invoice) => $this->agingRow(
                $invoice->due_date?->toDateString() ?: $invoice->issue_date?->toDateString() ?: $asOf,
                $asOf,
                $invoice->invoice_no,
                $invoice->customer?->company_name ?: '-',
                (float) $invoice->balance_due
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function apAgingRows(Request $request, array $filters): array
    {
        $asOf = $filters['date_to'] ?: now()->toDateString();

        return PurchaseOrder::query()
            ->where('org_id', $request->user()->org_id)
            ->whereIn('status', ['approved', 'partially_received', 'received'])
            ->with('supplier:id,name')
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->whereDate('order_date', '<=', $asOf)
            ->orderBy('expected_date')
            ->orderBy('po_no')
            ->get()
            ->map(fn (PurchaseOrder $po) => $this->agingRow(
                $po->expected_date?->toDateString() ?: $po->order_date?->toDateString() ?: $asOf,
                $asOf,
                $po->po_no,
                $po->supplier?->name ?: '-',
                (float) $po->total
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function agingRow(string $dueDate, string $asOf, string $documentNo, string $partner, float $amount): array
    {
        $days = max(0, Carbon::parse($dueDate)->diffInDays(Carbon::parse($asOf), false));
        $bucket = match (true) {
            $days <= 30 => '0-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '>90',
        };

        return [
            'due_date' => $dueDate,
            'document_no' => $documentNo,
            'partner' => $partner,
            'days_overdue' => (string) $days,
            'bucket' => $bucket,
            'amount' => $this->money($amount),
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @return list<string>
     */
    private function reportHeaders(string $type): array
    {
        return match ($type) {
            'withholding' => ['date', 'document_no', 'partner', 'tax_id', 'form', 'base_amount', 'wht_rate', 'wht_amount'],
            'ar-aging', 'ap-aging' => ['due_date', 'document_no', 'partner', 'days_overdue', 'bucket', 'amount'],
            default => ['date', 'document_no', 'partner', 'tax_id', 'tax_mode', 'taxable_base', 'tax_amount', 'total'],
        };
    }
}
