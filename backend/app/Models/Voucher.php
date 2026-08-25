<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Voucher extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'voucher_no', 'type', 'status', 'source_type', 'source_id', 'voucher_date', 'amount', 'partner_name', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['voucher_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
