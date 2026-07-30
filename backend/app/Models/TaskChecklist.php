<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskChecklist extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $fillable = ['org_id', 'task_id', 'title', 'is_done', 'sort_order', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
