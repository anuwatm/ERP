<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SalesAccess
{
    public static function canSeeAll(User $user): bool
    {
        return $user->roles()->whereIn('code', ['owner', 'admin'])->exists();
    }

    public static function scopeCustomers(Builder $query, User $user): Builder
    {
        $query->where('org_id', $user->org_id);

        if (! self::canSeeAll($user)) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    public static function scopeDeals(Builder $query, User $user): Builder
    {
        $query->where('org_id', $user->org_id);

        if (! self::canSeeAll($user)) {
            $query->where('owner_id', $user->id);
        }

        return $query;
    }

    public static function assertCustomerVisible(Customer $customer, User $user): void
    {
        abort_unless($customer->org_id === $user->org_id, 404);
        abort_if(! self::canSeeAll($user) && $customer->owner_id !== $user->id, 403);
    }

    public static function assertDealVisible(Deal $deal, User $user): void
    {
        abort_unless($deal->org_id === $user->org_id, 404);
        abort_if(! self::canSeeAll($user) && $deal->owner_id !== $user->id, 403);
    }
}
