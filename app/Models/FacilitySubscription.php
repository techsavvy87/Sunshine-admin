<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FacilitySubscription extends Model
{
    public const ACTIVE_STATUSES = ['active', 'trialing'];

    protected $fillable = [
        'facility_key',
        'user_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'status',
        'cancel_at_period_end',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ended_at',
        'payment_failed_at',
        'grace_period_ends_at',
    ];

    protected $casts = [
        'cancel_at_period_end' => 'boolean',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
    ];

    public function scopeForFacility(Builder $query, string $facilityKey): Builder
    {
        return $query->where('facility_key', $facilityKey);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(FacilitySubscriptionInvoice::class);
    }

    /**
     * Whether this subscription currently grants portal access.
     */
    public function grantsAccess(): bool
    {
        // A scheduled cancellation whose paid period is over is expired even
        // if the customer.subscription.deleted webhook has not arrived yet.
        if ($this->cancel_at_period_end && $this->current_period_end && $this->current_period_end->isPast()) {
            return false;
        }

        if (in_array($this->status, self::ACTIVE_STATUSES, true)) {
            return true;
        }

        return $this->isInGracePeriod();
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === 'past_due'
            && $this->grace_period_ends_at !== null
            && $this->grace_period_ends_at->isFuture();
    }

    public function isCancellationScheduled(): bool
    {
        return $this->cancel_at_period_end && $this->grantsAccess();
    }
}
