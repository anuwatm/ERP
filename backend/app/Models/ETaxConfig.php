<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class ETaxConfig extends Model
{
    use UsesOrderedUuid;

    public const MODES = ['disabled', 'manual_export', 'provider'];

    protected $fillable = ['org_id', 'mode', 'provider_code', 'certificate_reference', 'certificate_expires_at', 'signature_mode', 'provider_settings', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['provider_settings' => 'encrypted:array', 'certificate_expires_at' => 'datetime'];
    }
}
