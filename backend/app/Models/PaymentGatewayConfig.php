<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    use UsesOrderedUuid;

    protected $guarded = ['id'];

    protected $hidden = ['recipient_id', 'webhook_secret'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'recipient_id' => 'encrypted', 'webhook_secret' => 'encrypted'];
    }
}
