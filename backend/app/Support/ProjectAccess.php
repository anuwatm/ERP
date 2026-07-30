<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectAccess
{
    public static function canSeeAll(User $user): bool
    {
        return $user->roles()->whereIn('code', ['owner', 'admin'])->exists();
    }

    public static function scopeProjects(Builder $query, User $user): Builder
    {
        $query->where('org_id', $user->org_id);

        if (! self::canSeeAll($user)) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    public static function assertProjectVisible(Project $project, User $user): void
    {
        abort_unless($project->org_id === $user->org_id, 404);
        abort_if(! self::canSeeAll($user) && $project->owner_id !== $user->id, 403);
    }
}
