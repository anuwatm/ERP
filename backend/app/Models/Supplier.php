<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = ['org_id', 'supplier_code', 'name', 'tax_id', 'email', 'phone', 'address', 'status', 'created_by', 'updated_by'];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
