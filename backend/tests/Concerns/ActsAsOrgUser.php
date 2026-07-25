<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\User;

trait ActsAsOrgUser
{
    protected function actingAsOrgUser(User $user, ?Organization $organization = null): static
    {
        if ($organization && $user->org_id !== $organization->id) {
            $user->forceFill(['org_id' => $organization->id])->save();
        }

        return $this->actingAs($user);
    }
}
