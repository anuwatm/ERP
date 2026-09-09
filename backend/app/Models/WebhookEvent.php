<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use UsesOrderedUuid;

    protected $guarded = ['id'];
}
