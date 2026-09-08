<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'holiday_date', 'name'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }
}
