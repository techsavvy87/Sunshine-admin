<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Subscription
    |--------------------------------------------------------------------------
    |
    | Monthly subscription the facility pays to the platform. This is
    | independent from the pet owner -> facility payments, which use the
    | keys under services.stripe.
    |
    */

    'enabled' => (bool) env('SUBSCRIPTION_ENABLED', true),

    'secret_key' => env('STRIPE_SUBSCRIPTION_SECRET_KEY'),
    'public_key' => env('STRIPE_SUBSCRIPTION_PUBLIC_KEY'),
    'price_id' => env('STRIPE_SUBSCRIPTION_PRICE_ID'),
    'webhook_secret' => env('STRIPE_SUBSCRIPTION_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),

    // Identifies this facility in Stripe metadata so a shared Stripe account
    // never applies another facility's subscription events to this install.
    'facility_key' => env('SUBSCRIPTION_FACILITY_KEY', Str::slug(env('APP_NAME', 'facility'))),

    // Days a past_due subscription keeps access after the first failed payment.
    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

];
