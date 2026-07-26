<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Support\SalesAccess;

class DealPolicy
{
    public function view(User $user, Deal $deal): bool
    {
        return $deal->org_id === $user->org_id && (SalesAccess::canSeeAll($user) || $deal->owner_id === $user->id);
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->view($user, $deal);
    }
}
