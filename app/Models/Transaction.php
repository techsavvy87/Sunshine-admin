<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'appointment_id',
        'invoice_id',
        'user_id',
        'tran_date',
        'amount',
        'payment_method',
        'authorization_code',
        'notes',
        'last_payment_id',
        'stripe_transaction_id',
    ];

    protected $casts = [
        'tran_date' => 'datetime',
        'amount' => 'float',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
