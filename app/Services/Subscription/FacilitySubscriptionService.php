<?php

namespace App\Services\Subscription;

use App\Models\FacilitySubscription;
use App\Models\FacilitySubscriptionInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\StripeObject;

class FacilitySubscriptionService
{
    // Stripe subscription statuses that will never bill again.
    private const ENDED_STATUSES = ['canceled', 'incomplete_expired'];

    public function __construct(private StripeSubscriptionGateway $gateway)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('subscription.enabled');
    }

    public function facilityKey(): string
    {
        return (string) config('subscription.facility_key');
    }

    public function hasActiveSubscription(): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        return $this->current()?->grantsAccess() ?? false;
    }

    /**
     * The subscription to show and act on: the one granting access if any,
     * otherwise the most recent one.
     */
    public function current(): ?FacilitySubscription
    {
        $subscriptions = FacilitySubscription::forFacility($this->facilityKey())
            ->latest('id')
            ->get();

        return $subscriptions->first(fn (FacilitySubscription $s) => $s->grantsAccess())
            ?? $subscriptions->first();
    }

    public function invoices()
    {
        return FacilitySubscriptionInvoice::query()
            ->whereHas('subscription', fn ($q) => $q->forFacility($this->facilityKey()))
            ->latest('id')
            ->limit(24)
            ->get();
    }

    /**
     * Monthly plan details shown on the Subscription page.
     */
    public function plan(): ?array
    {
        $priceId = config('subscription.price_id');
        if (!$priceId) {
            return null;
        }

        try {
            return Cache::remember('facility_subscription_plan:' . $priceId, now()->addHour(), function () use ($priceId) {
                $price = $this->gateway->retrievePrice($priceId);

                return [
                    'name' => data_get($price, 'product.name') ?: (data_get($price, 'nickname') ?: 'Monthly Subscription'),
                    'description' => data_get($price, 'product.description'),
                    'amount' => ((int) data_get($price, 'unit_amount', 0)) / 100,
                    'currency' => strtoupper((string) data_get($price, 'currency', 'usd')),
                    'interval' => data_get($price, 'recurring.interval', 'month'),
                    'interval_count' => (int) data_get($price, 'recurring.interval_count', 1),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Unable to load subscription price', ['price_id' => $priceId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function createCheckoutUrl(User $owner): string
    {
        if ($this->hasActiveSubscription()) {
            throw new SubscriptionException('This facility already has an active subscription.');
        }

        // A lapsed subscription that Stripe may still try to collect would
        // double-bill the facility once the new one starts, so close it first.
        $this->cancelLapsedSubscriptions();

        $metadata = [
            'facility_key' => $this->facilityKey(),
            'user_id' => (string) $owner->id,
        ];

        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'price' => config('subscription.price_id'),
                'quantity' => 1,
            ]],
            'client_reference_id' => $this->facilityKey(),
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
            'success_url' => route('subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscription.index'),
        ];

        $customerId = $this->stripeCustomerId();
        if ($customerId) {
            $params['customer'] = $customerId;
        } else {
            $params['customer_email'] = $owner->email;
        }

        return $this->gateway->createCheckoutSession($params)->url;
    }

    /**
     * Sync the subscription right after Checkout redirects back, so the owner
     * does not have to wait for the webhook.
     */
    public function completeCheckout(string $sessionId): ?FacilitySubscription
    {
        $session = $this->gateway->retrieveCheckoutSession($sessionId);

        if (!$this->checkoutSessionBelongsToFacility($session)) {
            Log::warning('Checkout session does not belong to this facility', ['session_id' => $sessionId]);
            return null;
        }

        $subscriptionId = $this->objectId(data_get($session, 'subscription'));

        return $subscriptionId ? $this->syncFromStripe($subscriptionId) : null;
    }

    public function checkoutSessionBelongsToFacility(StripeObject $session): bool
    {
        $facilityKey = data_get($session, 'metadata.facility_key') ?? data_get($session, 'client_reference_id');

        return data_get($session, 'mode') === 'subscription' && $facilityKey === $this->facilityKey();
    }

    public function cancelAtPeriodEnd(): FacilitySubscription
    {
        $subscription = $this->current();
        if (!$subscription || !$subscription->grantsAccess() || $subscription->cancel_at_period_end) {
            throw new SubscriptionException('There is no active subscription to cancel.');
        }

        $stripeSubscription = $this->gateway->updateSubscription($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        return $this->sync($stripeSubscription);
    }

    public function resume(): FacilitySubscription
    {
        $subscription = $this->current();
        if (!$subscription || !$subscription->isCancellationScheduled()) {
            throw new SubscriptionException('There is no scheduled cancellation to undo.');
        }

        $stripeSubscription = $this->gateway->updateSubscription($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        return $this->sync($stripeSubscription);
    }

    public function billingPortalUrl(): string
    {
        $customerId = $this->stripeCustomerId();
        if (!$customerId) {
            throw new SubscriptionException('No billing account exists for this facility yet.');
        }

        return $this->gateway->createBillingPortalSession($customerId, route('subscription.index'))->url;
    }

    public function syncFromStripe(string $subscriptionId): ?FacilitySubscription
    {
        return $this->sync($this->gateway->retrieveSubscription($subscriptionId));
    }

    /**
     * Upsert the local copy of a Stripe subscription. Returns null when the
     * subscription belongs to a different facility.
     */
    public function sync(StripeObject $stripeSubscription): ?FacilitySubscription
    {
        $existing = FacilitySubscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        $metadataKey = data_get($stripeSubscription, 'metadata.facility_key');

        $belongsToFacility = $metadataKey !== null
            ? $metadataKey === $this->facilityKey()
            : $existing?->facility_key === $this->facilityKey();

        if (!$belongsToFacility || ($existing && $existing->facility_key !== $this->facilityKey())) {
            Log::info('Ignoring subscription for another facility', ['subscription_id' => $stripeSubscription->id]);
            return null;
        }

        $subscription = $existing ?? new FacilitySubscription([
            'facility_key' => $this->facilityKey(),
            'stripe_subscription_id' => $stripeSubscription->id,
            'user_id' => $this->existingUserId(data_get($stripeSubscription, 'metadata.user_id')),
        ]);

        $status = (string) data_get($stripeSubscription, 'status');

        $subscription->fill([
            'stripe_customer_id' => $this->objectId(data_get($stripeSubscription, 'customer')),
            'stripe_price_id' => data_get($stripeSubscription, 'items.data.0.price.id'),
            'status' => $status,
            'cancel_at_period_end' => (bool) data_get($stripeSubscription, 'cancel_at_period_end', false),
            // Newer Stripe API versions moved the billing period onto the items.
            'current_period_start' => $this->timestamp(data_get($stripeSubscription, 'current_period_start')
                ?? data_get($stripeSubscription, 'items.data.0.current_period_start')),
            'current_period_end' => $this->timestamp(data_get($stripeSubscription, 'current_period_end')
                ?? data_get($stripeSubscription, 'items.data.0.current_period_end')),
            'canceled_at' => $this->timestamp(data_get($stripeSubscription, 'canceled_at')),
            'ended_at' => $this->timestamp(data_get($stripeSubscription, 'ended_at')),
        ]);

        if ($status === 'past_due') {
            $this->startGracePeriod($subscription);
        } elseif (in_array($status, FacilitySubscription::ACTIVE_STATUSES, true)) {
            $subscription->payment_failed_at = null;
            $subscription->grace_period_ends_at = null;
        }

        $subscription->save();

        return $subscription;
    }

    public function recordPaymentFailed(FacilitySubscription $subscription): void
    {
        $this->startGracePeriod($subscription);
        $subscription->save();
    }

    public function recordInvoice(FacilitySubscription $subscription, StripeObject $invoice): FacilitySubscriptionInvoice
    {
        return FacilitySubscriptionInvoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'facility_subscription_id' => $subscription->id,
                'number' => data_get($invoice, 'number'),
                'status' => (string) data_get($invoice, 'status', 'open'),
                'amount_due' => (int) data_get($invoice, 'amount_due', 0),
                'amount_paid' => (int) data_get($invoice, 'amount_paid', 0),
                'currency' => (string) data_get($invoice, 'currency', 'usd'),
                'period_start' => $this->timestamp(data_get($invoice, 'lines.data.0.period.start') ?? data_get($invoice, 'period_start')),
                'period_end' => $this->timestamp(data_get($invoice, 'lines.data.0.period.end') ?? data_get($invoice, 'period_end')),
                'paid_at' => $this->timestamp(data_get($invoice, 'status_transitions.paid_at')),
                'hosted_invoice_url' => data_get($invoice, 'hosted_invoice_url'),
                'invoice_pdf' => data_get($invoice, 'invoice_pdf'),
            ]
        );
    }

    /**
     * Subscription id of an invoice across Stripe API versions.
     */
    public function invoiceSubscriptionId(StripeObject $invoice): ?string
    {
        return $this->objectId(data_get($invoice, 'subscription'))
            ?? $this->objectId(data_get($invoice, 'parent.subscription_details.subscription'));
    }

    private function startGracePeriod(FacilitySubscription $subscription): void
    {
        $subscription->payment_failed_at ??= now();
        $subscription->grace_period_ends_at ??= $subscription->payment_failed_at->copy()->addDays(config('subscription.grace_days'));
    }

    private function cancelLapsedSubscriptions(): void
    {
        FacilitySubscription::forFacility($this->facilityKey())
            ->whereNotIn('status', self::ENDED_STATUSES)
            ->get()
            ->reject(fn (FacilitySubscription $s) => $s->grantsAccess())
            ->each(function (FacilitySubscription $s) {
                try {
                    $this->sync($this->gateway->cancelSubscription($s->stripe_subscription_id));
                } catch (\Throwable $e) {
                    Log::warning('Unable to cancel lapsed subscription', [
                        'subscription_id' => $s->stripe_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    private function stripeCustomerId(): ?string
    {
        return FacilitySubscription::forFacility($this->facilityKey())
            ->whereNotNull('stripe_customer_id')
            ->latest('id')
            ->value('stripe_customer_id');
    }

    private function existingUserId($userId): ?int
    {
        return $userId && User::whereKey($userId)->exists() ? (int) $userId : null;
    }

    private function objectId($value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return data_get($value, 'id');
    }

    private function timestamp($value): ?Carbon
    {
        // Eloquent stores the wall-clock time, so convert to the app timezone first.
        return $value ? Carbon::createFromTimestamp((int) $value, config('app.timezone')) : null;
    }
}
