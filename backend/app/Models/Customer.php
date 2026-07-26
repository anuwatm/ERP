<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $fillable = ['org_id', 'customer_code', 'company_name', 'tax_id', 'customer_type', 'status', 'owner_id', 'phone', 'email', 'line_id', 'website', 'address', 'source', 'tags', 'note', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
