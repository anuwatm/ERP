<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoredFile extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $table = 'files';

    protected $fillable = ['org_id', 'storage_key', 'file_name', 'mime_type', 'size_bytes', 'category', 'entity_type', 'entity_id', 'uploaded_by'];
}
