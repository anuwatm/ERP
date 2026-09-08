<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowApproval extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['workflow_instance_id', 'step_no', 'assigned_user_id', 'delegated_from_user_id', 'status', 'acted_by_user_id', 'comment', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function delegatedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_user_id');
    }
}
