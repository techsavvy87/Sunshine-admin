<?php

namespace Tests\Support;

use App\Services\Subscription\StripeSubscriptionGateway;
use Stripe\StripeObject;
use Stripe\Subscription;

/**
 * In-memory stand-in for the Stripe subscription account.
 */
class FakeStripeSubscriptionGateway extends StripeSubscriptionGateway
{
    /** @var array<string, array> */
    public array $subscriptions = [];

    /** @var array<string, array> */
    public array $checkoutSessions = [];

    public array $createdCheckoutSessions = [];

    public array $canceledSubscriptions = [];

    public function putSubscription(string $id, array $attributes): void
    {
        $this->subscriptions[$id] = array_replace_recursive($this->subscriptions[$id] ?? [
            'id' => $id,
            'object' => 'subscription',
            'customer' => 'cus_test',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ended_at' => null,
            'metadata' => ['facility_key' => config('subscription.facility_key')],
            'items' => ['object' => 'list', 'data' => [[
                'id' => 'si_' . $id,
                'price' => ['id' => 'price_test'],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ]]],
        ], $attributes);
    }

    public function createCheckoutSession(array $params): StripeObject
    {
        $this->createdCheckoutSessions[] = $params;

        return StripeObject::constructFrom(['id' => 'cs_test', 'url' => 'https://checkout.stripe.test/cs_test']);
    }

    public function retrieveCheckoutSession(string $sessionId): StripeObject
    {
        return StripeObject::constructFrom($this->checkoutSessions[$sessionId]);
    }

    public function retrieveSubscription(string $subscriptionId): StripeObject
    {
        return Subscription::constructFrom($this->subscriptions[$subscriptionId]);
    }

    public function updateSubscription(string $subscriptionId, array $params): StripeObject
    {
        $this->putSubscription($subscriptionId, $params);

        return $this->retrieveSubscription($subscriptionId);
    }

    public function cancelSubscription(string $subscriptionId): StripeObject
    {
        $this->canceledSubscriptions[] = $subscriptionId;
        $this->putSubscription($subscriptionId, ['status' => 'canceled', 'ended_at' => now()->timestamp]);

        return $this->retrieveSubscription($subscriptionId);
    }

    public function retrievePrice(string $priceId): StripeObject
    {
        return StripeObject::constructFrom([
            'id' => $priceId,
            'unit_amount' => 4900,
            'currency' => 'usd',
            'recurring' => ['interval' => 'month', 'interval_count' => 1],
            'product' => ['name' => 'Sunshine Monthly', 'description' => null],
        ]);
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl): StripeObject
    {
        return StripeObject::constructFrom(['url' => 'https://billing.stripe.test/' . $customerId]);
    }
}
