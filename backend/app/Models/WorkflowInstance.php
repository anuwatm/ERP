<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'workflow_definition_id', 'subject_type', 'subject_id', 'requester_user_id', 'status', 'current_step_no', 'definition_snapshot', 'subject_snapshot', 'submitted_at', 'completed_at'];

    protected function casts(): array
    {
        return ['definition_snapshot' => 'array', 'subject_snapshot' => 'array', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkflowApproval::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }
}
