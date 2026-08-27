<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'branch_id', 'bank_account_id', 'custodian_user_id', 'fund_no', 'imprest_amount', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['imprest_amount' => 'decimal:2'];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PettyCashRequest::class);
    }
}
