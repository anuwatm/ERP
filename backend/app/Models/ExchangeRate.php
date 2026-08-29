<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'base_currency', 'quote_currency', 'rate_date', 'rate', 'source', 'created_by'];

    protected function casts(): array
    {
        return ['rate_date' => 'date', 'rate' => 'decimal:6'];
    }
}
