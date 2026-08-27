<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['draft', 'approved', 'paid', 'rejected'];

    public const CATEGORIES = ['salary', 'software', 'marketing', 'travel', 'office', 'contractor', 'hosting', 'misc'];

    protected $fillable = ['org_id', 'expense_no', 'category', 'title', 'amount', 'tax_mode', 'tax_invoice_no', 'tax_amount', 'withholding_tax_rate', 'withholding_tax_amount', 'withholding_tax_form', 'expense_date', 'project_id', 'supplier_id', 'purchase_order_id', 'bank_account_id', 'status', 'receipt_file_id', 'approved_by', 'approved_at', 'paid_at', 'note', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'withholding_tax_rate' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function receiptFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'receipt_file_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
