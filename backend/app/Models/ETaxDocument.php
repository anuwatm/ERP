<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ETaxDocument extends Model
{
    use UsesOrderedUuid;

    public const TYPES = ['tax_invoice', 'receipt', 'credit_note', 'debit_note'];

    public const STATUSES = ['generated', 'signed', 'submitted', 'accepted', 'rejected'];

    protected $fillable = ['org_id', 'source_type', 'source_id', 'document_type', 'document_no', 'status', 'xml_storage_path', 'xml_sha256', 'payload_json', 'signed_at', 'submitted_at', 'accepted_at', 'rejected_at', 'last_error', 'created_by'];

    protected function casts(): array
    {
        return ['payload_json' => 'array', 'signed_at' => 'datetime', 'submitted_at' => 'datetime', 'accepted_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ETaxSubmissionAttempt::class)->latest('attempt_no');
    }
}
