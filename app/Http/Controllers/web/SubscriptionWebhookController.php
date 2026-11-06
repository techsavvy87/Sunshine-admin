<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\StripeSubscriptionWebhookEvent;
use App\Services\Subscription\FacilitySubscriptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Receives Stripe events for the facility -> platform subscription.
 * The pet owner -> facility payments use StripeWebhookController instead.
 */
class SubscriptionWebhookController extends Controller
{
    public function __construct(private FacilitySubscriptionService $subscriptions)
    {
    }

    public function handle(Request $request)
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('stripe-signature'),
                (string) config('subscription.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid subscription webhook payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid subscription webhook signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        try {
            // Recording the event id in the same transaction makes redelivered
            // events a no-op, while a failed attempt rolls back and is retried.
            DB::transaction(function () use ($event) {
                StripeSubscriptionWebhookEvent::create([
                    'stripe_event_id' => $event->id,
                    'type' => $event->type,
                ]);

                $this->dispatch($event);
            });
        } catch (UniqueConstraintViolationException $e) {
            Log::info('Duplicate subscription webhook skipped', ['event_id' => $event->id]);
        } catch (\Throwable $e) {
            report($e);
            return response('Webhook processing failed', 500);
        }

        return response('OK', 200);
    }

    private function dispatch(Event $event): void
    {
        $object = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
                if ($this->subscriptions->checkoutSessionBelongsToFacility($object) && $object->subscription) {
                    $this->subscriptions->syncFromStripe(is_string($object->subscription) ? $object->subscription : $object->subscription->id);
                }
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                // Re-fetch so out-of-order deliveries always land on Stripe's latest state.
                $this->subscriptions->syncFromStripe($object->id);
                break;

            case 'invoice.paid':
            case 'invoice.payment_failed':
                $this->handleInvoice($event->type, $object);
                break;

            default:
                Log::info('Unhandled subscription webhook event', ['type' => $event->type]);
        }
    }

    private function handleInvoice(string $type, $invoice): void
    {
        $subscriptionId = $this->subscriptions->invoiceSubscriptionId($invoice);
        if (!$subscriptionId) {
            return;
        }

        $subscription = $this->subscriptions->syncFromStripe($subscriptionId);
        if (!$subscription) {
            return;
        }

        if ($type === 'invoice.payment_failed') {
            $this->subscriptions->recordPaymentFailed($subscription);
        }

        $this->subscriptions->recordInvoice($subscription, $invoice);
    }
}
