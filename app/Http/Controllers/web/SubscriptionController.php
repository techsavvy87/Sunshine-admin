<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Services\Subscription\FacilitySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Subscription\SubscriptionException;

class SubscriptionController extends Controller
{
    public function __construct(private FacilitySubscriptionService $subscriptions)
    {
    }

    public function required()
    {
        if ($this->subscriptions->hasActiveSubscription()) {
            return redirect()->route('dashboard');
        }

        $isOwner = isFacilityOwner();

        return view('subscription.required', compact('isOwner'));
    }

    public function index()
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        return view('subscription.index', [
            'subscription' => $this->subscriptions->current(),
            'hasAccess' => $this->subscriptions->hasActiveSubscription(),
            'plan' => $this->subscriptions->plan(),
            'invoices' => $this->subscriptions->invoices(),
        ]);
    }

    public function checkout(Request $request)
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        return $this->attempt(fn () => redirect()->away($this->subscriptions->createCheckoutUrl($request->user())));
    }

    public function success(Request $request)
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        $sessionId = (string) $request->query('session_id');
        if ($sessionId !== '') {
            try {
                $this->subscriptions->completeCheckout($sessionId);
            } catch (\Throwable $e) {
                // The webhook will still activate the subscription.
                Log::warning('Unable to sync subscription after checkout', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }
        }

        if ($this->subscriptions->hasActiveSubscription()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('subscription.index')->with([
            'status' => 'success',
            'message' => 'Your payment is being processed. This page will reflect your subscription shortly.',
        ]);
    }

    public function cancel()
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        return $this->attempt(function () {
            $subscription = $this->subscriptions->cancelAtPeriodEnd();

            return redirect()->route('subscription.index')->with([
                'status' => 'success',
                'message' => 'Your subscription will end on ' . optional($subscription->current_period_end)->format('M j, Y') . '. You keep full access until then.',
            ]);
        });
    }

    public function resume()
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        return $this->attempt(function () {
            $this->subscriptions->resume();

            return redirect()->route('subscription.index')->with([
                'status' => 'success',
                'message' => 'Your subscription will continue to renew monthly.',
            ]);
        });
    }

    public function portal()
    {
        if ($redirect = $this->ensureOwner()) {
            return $redirect;
        }

        return $this->attempt(fn () => redirect()->away($this->subscriptions->billingPortalUrl()));
    }

    /**
     * Billing is limited to the facility owner; other staff are sent back to
     * the page that matches their access.
     */
    private function ensureOwner()
    {
        if (isFacilityOwner()) {
            return null;
        }

        return redirect()->route($this->subscriptions->hasActiveSubscription() ? 'dashboard' : 'subscription.required');
    }

    private function attempt(callable $action)
    {
        try {
            return $action();
        } catch (SubscriptionException $e) {
            $message = $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('Subscription action failed', ['error' => $e->getMessage()]);
            $message = 'We could not reach the payment provider. Please try again.';
        }

        return redirect()->route('subscription.index')->with([
            'status' => 'fail',
            'message' => $message,
        ]);
    }
}
