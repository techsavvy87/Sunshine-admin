<?php

namespace App\Http\Middleware;

use App\Services\Subscription\FacilitySubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacilitySubscribed
{
    // Routes that stay reachable without an active subscription.
    private const EXEMPT_ROUTES = ['logout', 'subscription.*'];

    public function __construct(private FacilitySubscriptionService $subscriptions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || $request->routeIs(...self::EXEMPT_ROUTES) || $this->subscriptions->hasActiveSubscription()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'An active subscription is required to continue using the platform.',
                'redirect' => route('subscription.required'),
            ], 402);
        }

        return redirect()->route('subscription.required');
    }
}
