<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'retention_policy_id', 'code', 'name', 'default_sensitivity', 'expiry_tracking_enabled', 'default_renewal_alert_days', 'status'];

    protected function casts(): array
    {
        return ['expiry_tracking_enabled' => 'boolean', 'status' => 'boolean'];
    }
}
