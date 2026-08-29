<?php
namespace App\Models; use App\Models\Concerns\UsesOrderedUuid; use Illuminate\Database\Eloquent\Model; class InventoryLot extends Model { use UsesOrderedUuid; protected $fillable=['org_id','product_id','lot_no','manufactured_at','expires_at','barcode']; protected function casts(): array { return ['manufactured_at'=>'date','expires_at'=>'date']; } }
