<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'branch_id', 'branch_key', 'doc_type', 'prefix', 'year', 'year_key', 'last_number'];
}
