<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['todo', 'in_progress', 'blocked', 'done', 'cancelled'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = ['org_id', 'project_id', 'title', 'description', 'status', 'priority', 'assignee_id', 'due_date', 'completed_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }
}
