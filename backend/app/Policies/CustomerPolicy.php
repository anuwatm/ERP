<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\SalesAccess;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $customer->org_id === $user->org_id && (SalesAccess::canSeeAll($user) || $customer->owner_id === $user->id);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer) && ! $customer->deals()->exists();
    }
}
