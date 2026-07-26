<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Support\SalesAccess;

class ContactPolicy
{
    public function update(User $user, Contact $contact): bool
    {
        return $contact->org_id === $user->org_id && $contact->customer && (SalesAccess::canSeeAll($user) || $contact->customer->owner_id === $user->id);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact) && ! $contact->deals()->exists();
    }
}
