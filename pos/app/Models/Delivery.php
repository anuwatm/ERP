<?php

namespace Pos\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $table = 'pos_deliveries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['contact', 'request_hash', 'request_key'];

    protected function casts(): array
    {
        return ['contact' => 'encrypted:array', 'snapshot' => 'array', 'version' => 'integer'];
    }
}
