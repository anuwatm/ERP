<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrFieldResult extends Model
{
    use UsesOrderedUuid;

    public const FIELDS = [
        'vendor_name' => 'Vendor Name',
        'tax_id' => 'Tax Identification Number (13 digits)',
        'invoice_no' => 'Invoice / Receipt No.',
        'document_date' => 'Document Date',
        'subtotal' => 'Subtotal (Amount before VAT)',
        'vat_rate' => 'VAT Rate (%)',
        'vat_amount' => 'VAT Amount (7%)',
        'total_amount' => 'Total Amount (ยอดรวมสุทธิ)',
    ];

    protected $fillable = [
        'org_id',
        'ocr_extraction_id',
        'field_key',
        'extracted_value',
        'confidence',
        'is_corrected_by_user',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:3',
            'is_corrected_by_user' => 'boolean',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(OcrExtraction::class, 'ocr_extraction_id');
    }
}
