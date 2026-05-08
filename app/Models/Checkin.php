<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $fillable = [
        'appointment_id',
        'date',
        'notes',
        'flows',
    ];
}
