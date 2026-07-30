<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskAccess
{
    public static function canSeeAll(User $user): bool
    {
        return $user->roles()->whereIn('code', ['owner', 'admin'])->exists();
    }

    public static function canManageProjectTasks(User $user, ?Project $project): bool
    {
        if (self::canSeeAll($user)) {
            return true;
        }

        if ($project === null) {
            return $user->hasPermissionCode('tasks.create');
        }

        return $project->org_id === $user->org_id && $project->owner_id === $user->id;
    }

    public static function scopeTasks(Builder $query, User $user): Builder
    {
        $query->where('org_id', $user->org_id);

        if (self::canSeeAll($user)) {
            return $query;
        }

        return $query->where(function ($inner) use ($user) {
            $inner->where('assignee_id', $user->id)
                ->orWhereHas('project', fn ($project) => $project->where('owner_id', $user->id));
        });
    }

    public static function assertTaskVisible(Task $task, User $user): void
    {
        abort_unless($task->org_id === $user->org_id, 404);

        if (self::canSeeAll($user)) {
            return;
        }

        $projectOwner = $task->project?->owner_id === $user->id;
        abort_unless($task->assignee_id === $user->id || $projectOwner, 403);
    }
}
