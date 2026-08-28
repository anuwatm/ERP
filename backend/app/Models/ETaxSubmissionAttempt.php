<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ETaxSubmissionAttempt extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'e_tax_document_id', 'attempt_no', 'status', 'provider_code', 'external_reference', 'response_code', 'response_message', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ETaxDocument::class, 'e_tax_document_id');
    }
}
