<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBillSubmission extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'supplier_id', 'portal_user_id', 'document_id', 'vendor_invoice_no', 'invoice_date', 'amount', 'currency', 'status', 'note', 'reviewed_at', 'reviewed_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
