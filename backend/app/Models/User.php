<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'org_id',
    'branch_id',
    'division_id',
    'department_id',
    'name',
    'display_name',
    'email',
    'password',
    'auth_provider',
    'auth_provider_user_id',
    'person_id',
    'position',
    'phone',
    'avatar_url',
    'status',
    'last_login_at',
    'invited_at',
    'invite_token_hash',
    'invite_expires_at',
    'invite_accepted_at',
    'email_verified_at',
    'created_by',
    'updated_by',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, UsesOrderedUuid;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot(['assigned_at', 'assigned_by']);
    }

    public function hasPermissionCode(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'invite_accepted_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }
}
