<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvite extends Model
{
    protected $fillable = [
        'invited_by',
        'email',
        'token_hash',
        'expires_at',
        'used_at',
        'used_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
