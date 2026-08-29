<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class OrganizationCurrency extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'code', 'name', 'decimal_places', 'status'];
}
