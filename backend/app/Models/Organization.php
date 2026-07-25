<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['name', 'legal_name', 'tax_id', 'email', 'phone', 'address', 'logo_url', 'currency', 'timezone', 'status'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'org_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_id');
    }
}
