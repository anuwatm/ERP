<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'dedupe_key'];
}
