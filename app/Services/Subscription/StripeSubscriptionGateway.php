<?php

namespace App\Services\Subscription;

use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * Thin wrapper around the Stripe account that bills facilities for the
 * platform subscription. Kept separate from services.stripe, which handles
 * pet owner -> facility payments.
 */
class StripeSubscriptionGateway
{
    private ?StripeClient $client = null;

    public function createCheckoutSession(array $params): StripeObject
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function retrieveCheckoutSession(string $sessionId): StripeObject
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    public function retrieveSubscription(string $subscriptionId): StripeObject
    {
        return $this->client()->subscriptions->retrieve($subscriptionId);
    }

    public function updateSubscription(string $subscriptionId, array $params): StripeObject
    {
        return $this->client()->subscriptions->update($subscriptionId, $params);
    }

    public function cancelSubscription(string $subscriptionId): StripeObject
    {
        return $this->client()->subscriptions->cancel($subscriptionId);
    }

    public function retrievePrice(string $priceId): StripeObject
    {
        return $this->client()->prices->retrieve($priceId, ['expand' => ['product']]);
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl): StripeObject
    {
        return $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('subscription.secret_key'));
    }
}
