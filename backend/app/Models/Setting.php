<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'key', 'value_json', 'updated_by'];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }
}
