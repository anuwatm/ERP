<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDelegation extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'delegator_user_id', 'delegate_user_id', 'subject_type', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function delegateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }
}
