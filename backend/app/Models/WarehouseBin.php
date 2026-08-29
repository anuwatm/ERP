<?php
namespace App\Models; use App\Models\Concerns\UsesOrderedUuid; use Illuminate\Database\Eloquent\Model; class WarehouseBin extends Model { use UsesOrderedUuid; protected $fillable=['org_id','warehouse_id','code','name','status']; }
