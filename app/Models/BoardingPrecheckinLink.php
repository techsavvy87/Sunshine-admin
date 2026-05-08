<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardingPrecheckinLink extends Model
{
    protected $fillable = [
        'appointment_id',
        'token_hash',
        'scheduled_for',
        'sent_at',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'id');
    }
}
