<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class QuotationAcceptance extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'quotation_id', 'portal_user_id', 'accepted_by_name', 'accepted_by_email', 'ip_hash', 'user_agent_hash', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
