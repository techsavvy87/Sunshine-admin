<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityAddress extends Model
{
    protected $fillable = [
        'address',
        'city',
        'state',
        'zip_code',
        'late_fees_enabled',
    ];

    protected $casts = [
        'late_fees_enabled' => 'boolean',
    ];
}
