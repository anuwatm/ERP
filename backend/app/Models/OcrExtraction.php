<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OcrExtraction extends Model
{
    use UsesOrderedUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_EXTRACTED,
        self::STATUS_CONVERTED,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
    ];

    public const DOCUMENT_TYPES = [
        'receipt' => 'Receipt (ใบเสร็จรับเงิน/สลิปย่อย)',
        'tax_invoice' => 'Tax Invoice (ใบกำกับภาษีเต็มรูป)',
        'bank_slip' => 'Bank Transfer Slip (สลิปโอนเงิน/QR)',
        'unknown' => 'Other / Unknown (อื่นๆ)',
    ];

    protected $fillable = [
        'org_id',
        'stored_file_id',
        'status',
        'provider_code',
        'document_type',
        'confidence_score',
        'raw_response_text',
        'parsed_data_json',
        'target_type',
        'target_id',
        'math_mismatch',
        'tax_id_valid',
        'error_message',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:3',
            'parsed_data_json' => 'array',
            'math_mismatch' => 'boolean',
            'tax_id_valid' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function fieldResults(): HasMany
    {
        return $this->hasMany(OcrFieldResult::class, 'ocr_extraction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
