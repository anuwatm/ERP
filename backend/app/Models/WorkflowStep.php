<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['workflow_definition_id', 'step_no', 'assignment_type', 'approver_user_id', 'approver_role_code', 'execution_mode'];
}
