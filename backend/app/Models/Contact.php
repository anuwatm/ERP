<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $fillable = ['org_id', 'customer_id', 'supplier_id', 'name', 'position', 'phone', 'email', 'line_id', 'is_primary', 'note', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
