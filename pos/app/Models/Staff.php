<?php

namespace Pos\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Staff extends Authenticatable
{
    protected $table = 'pos_staff';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'branch_id', 'name', 'email', 'password', 'role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'pharmacist_qualified' => 'boolean'];
    }
}
