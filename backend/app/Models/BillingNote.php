<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingNote extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'customer_id', 'billing_no', 'status', 'issue_date', 'due_date', 'total', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'total' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingNoteLine::class);
    }
}
