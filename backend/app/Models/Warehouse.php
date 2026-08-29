<?php
namespace App\Models; use App\Models\Concerns\UsesOrderedUuid; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany; class Warehouse extends Model { use UsesOrderedUuid; protected $fillable=['org_id','branch_id','code','name','status']; public function bins(): HasMany { return $this->hasMany(WarehouseBin::class); } }
