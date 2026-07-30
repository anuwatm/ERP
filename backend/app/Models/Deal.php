<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    public const OPEN_STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];

    protected $fillable = ['org_id', 'title', 'customer_id', 'contact_id', 'stage', 'value_amount', 'currency', 'probability', 'expected_close_date', 'owner_id', 'source', 'lost_reason', 'won_at', 'lost_at', 'note', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value_amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'entity_id')->where('entity_type', 'deal');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }
}
