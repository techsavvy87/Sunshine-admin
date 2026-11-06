<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilitySubscriptionInvoice extends Model
{
    protected $fillable = [
        'facility_subscription_id',
        'stripe_invoice_id',
        'number',
        'status',
        'amount_due',
        'amount_paid',
        'currency',
        'period_start',
        'period_end',
        'paid_at',
        'hosted_invoice_url',
        'invoice_pdf',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(FacilitySubscription::class, 'facility_subscription_id');
    }
}
