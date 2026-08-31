<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'document_id', 'version_no', 'storage_key', 'original_name', 'mime_type', 'size_bytes', 'checksum_sha256', 'scan_status', 'change_note', 'uploaded_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
