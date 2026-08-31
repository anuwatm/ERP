<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLink extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'document_id', 'linkable_type', 'linkable_id', 'role', 'linked_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
