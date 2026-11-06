<?php

use App\Models\FacilitySubscription;
use App\Models\FacilitySubscriptionInvoice;
use App\Models\Role;
use App\Models\StripeSubscriptionWebhookEvent;
use App\Models\User;
use App\Services\Subscription\StripeSubscriptionGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakeStripeSubscriptionGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'subscription.enabled' => true,
        'subscription.facility_key' => 'sunshine-test',
        'subscription.price_id' => 'price_test',
        'subscription.webhook_secret' => 'whsec_test',
        'subscription.grace_days' => 7,
    ]);

    $this->gateway = new FakeStripeSubscriptionGateway();
    $this->app->instance(StripeSubscriptionGateway::class, $this->gateway);

    Route::middleware(['web', 'auth', 'subscription'])->get('/_test/protected', fn () => 'protected ok');

    $this->owner = User::factory()->create();
    $this->owner->roles()->attach(Role::forceCreate(['title' => 'Owner', 'description' => 'Owner']));

    $this->staff = User::factory()->create();
    $this->staff->roles()->attach(Role::forceCreate(['title' => 'Staff Member', 'description' => 'Staff']));
});

function sendSubscriptionWebhook($test, string $type, array $object, ?string $eventId = null)
{
    $payload = json_encode([
        'id' => $eventId ?? 'evt_' . uniqid(),
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

    return $test->call('POST', '/stripe/subscription-webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

function activateSubscription($test, string $id = 'sub_1'): FacilitySubscription
{
    $test->gateway->putSubscription($id, ['metadata' => ['user_id' => (string) $test->owner->id]]);
    sendSubscriptionWebhook($test, 'checkout.session.completed', [
        'id' => 'cs_' . $id,
        'object' => 'checkout.session',
        'mode' => 'subscription',
        'client_reference_id' => 'sunshine-test',
        'metadata' => ['facility_key' => 'sunshine-test'],
        'subscription' => $id,
    ])->assertOk();

    return FacilitySubscription::where('stripe_subscription_id', $id)->firstOrFail();
}

function invoicePayload(string $id, string $subscriptionId, string $status): array
{
    return [
        'id' => $id,
        'object' => 'invoice',
        'number' => strtoupper($id),
        'status' => $status,
        'amount_due' => 4900,
        'amount_paid' => $status === 'paid' ? 4900 : 0,
        'currency' => 'usd',
        'parent' => ['subscription_details' => ['subscription' => $subscriptionId]],
        'lines' => ['data' => [['period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
        'status_transitions' => ['paid_at' => $status === 'paid' ? now()->timestamp : null],
    ];
}

test('facility without subscription sees the required modal after login', function () {
    $this->actingAs($this->owner)->get('/_test/protected')->assertRedirect(route('subscription.required'));

    $this->actingAs($this->owner)->get(route('subscription.required'))
        ->assertOk()
        ->assertSee('Subscription Required')
        ->assertSee(route('subscription.index'));

    $this->actingAs($this->staff)->get(route('subscription.required'))
        ->assertOk()
        ->assertSee('contact the facility owner');

    $this->actingAs($this->owner)->getJson('/_test/protected')->assertStatus(402);
});

test('owner starts the initial subscription payment through checkout', function () {
    $this->actingAs($this->owner)->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('Sunshine Monthly')
        ->assertSee('$49.00');

    $this->actingAs($this->owner)->post(route('subscription.checkout'))
        ->assertRedirect('https://checkout.stripe.test/cs_test');

    $params = $this->gateway->createdCheckoutSessions[0];
    expect($params['mode'])->toBe('subscription')
        ->and($params['line_items'][0]['price'])->toBe('price_test')
        ->and($params['subscription_data']['metadata']['facility_key'])->toBe('sunshine-test')
        ->and($params['customer_email'])->toBe($this->owner->email);
});

test('returning from checkout activates the subscription without waiting for the webhook', function () {
    $this->gateway->putSubscription('sub_1', []);
    $this->gateway->checkoutSessions['cs_1'] = [
        'id' => 'cs_1', 'mode' => 'subscription', 'subscription' => 'sub_1',
        'metadata' => ['facility_key' => 'sunshine-test'],
    ];

    $this->actingAs($this->owner)->get(route('subscription.success', ['session_id' => 'cs_1']))
        ->assertRedirect(route('dashboard'));

    expect(FacilitySubscription::first()->status)->toBe('active');
});

test('webhook activates the facility and owner and staff can access protected features', function () {
    $subscription = activateSubscription($this);

    expect($subscription->status)->toBe('active')
        ->and($subscription->facility_key)->toBe('sunshine-test')
        ->and($subscription->user_id)->toBe($this->owner->id);

    $this->actingAs($this->owner)->get('/_test/protected')->assertOk()->assertSee('protected ok');
    $this->actingAs($this->staff)->get('/_test/protected')->assertOk()->assertSee('protected ok');
    $this->actingAs($this->owner)->get(route('subscription.required'))->assertRedirect(route('dashboard'));
});

test('monthly renewal extends the period and records the invoice', function () {
    activateSubscription($this);

    $nextPeriodEnd = now()->addMonths(2)->startOfSecond();
    $this->gateway->putSubscription('sub_1', ['items' => ['data' => [['current_period_end' => $nextPeriodEnd->timestamp]]]]);

    sendSubscriptionWebhook($this, 'invoice.paid', invoicePayload('in_renew', 'sub_1', 'paid'))->assertOk();

    expect(FacilitySubscription::first()->current_period_end->timestamp)->toBe($nextPeriodEnd->timestamp)
        ->and(FacilitySubscriptionInvoice::where('stripe_invoice_id', 'in_renew')->value('status'))->toBe('paid');
});

test('failed payment keeps access during the grace period and restricts it afterwards', function () {
    activateSubscription($this);

    $this->gateway->putSubscription('sub_1', ['status' => 'past_due']);
    sendSubscriptionWebhook($this, 'invoice.payment_failed', invoicePayload('in_fail', 'sub_1', 'open'))->assertOk();

    $subscription = FacilitySubscription::first();
    expect($subscription->status)->toBe('past_due')
        ->and($subscription->isInGracePeriod())->toBeTrue();
    $this->actingAs($this->staff)->get('/_test/protected')->assertOk();

    $this->travel(8)->days();
    $this->actingAs($this->staff)->get('/_test/protected')->assertRedirect(route('subscription.required'));

    // Paying the retried invoice restores access and clears the grace period.
    $this->gateway->putSubscription('sub_1', ['status' => 'active']);
    sendSubscriptionWebhook($this, 'invoice.paid', invoicePayload('in_fail', 'sub_1', 'paid'))->assertOk();

    expect(FacilitySubscription::first()->grace_period_ends_at)->toBeNull();
    $this->actingAs($this->staff)->get('/_test/protected')->assertOk();
});

test('owner schedules cancellation, keeps access through the paid period, then loses it', function () {
    $subscription = activateSubscription($this);
    $periodEnd = $subscription->current_period_end;

    $this->actingAs($this->owner)->post(route('subscription.cancel'))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('status', 'success');

    expect($this->gateway->subscriptions['sub_1']['cancel_at_period_end'])->toBeTrue()
        ->and(FacilitySubscription::first()->isCancellationScheduled())->toBeTrue();
    $this->actingAs($this->staff)->get('/_test/protected')->assertOk();

    // Even before the deleted webhook arrives, access ends with the paid period.
    $this->travelTo($periodEnd->copy()->addMinute());
    $this->actingAs($this->owner)->get('/_test/protected')->assertRedirect(route('subscription.required'));

    $this->gateway->putSubscription('sub_1', ['status' => 'canceled', 'ended_at' => now()->timestamp]);
    sendSubscriptionWebhook($this, 'customer.subscription.deleted', ['id' => 'sub_1', 'object' => 'subscription'])->assertOk();

    expect(FacilitySubscription::first()->status)->toBe('canceled');
    $this->actingAs($this->owner)->get('/_test/protected')->assertRedirect(route('subscription.required'));
});

test('owner can undo a scheduled cancellation', function () {
    activateSubscription($this);
    $this->actingAs($this->owner)->post(route('subscription.cancel'));
    $this->actingAs($this->owner)->post(route('subscription.resume'))->assertSessionHas('status', 'success');

    expect(FacilitySubscription::first()->cancel_at_period_end)->toBeFalse();
});

test('owner can subscribe again after expiration using the same Stripe customer', function () {
    activateSubscription($this);
    $this->gateway->putSubscription('sub_1', ['status' => 'canceled', 'customer' => 'cus_existing']);
    sendSubscriptionWebhook($this, 'customer.subscription.deleted', ['id' => 'sub_1', 'object' => 'subscription']);

    $this->actingAs($this->owner)->post(route('subscription.checkout'))
        ->assertRedirect('https://checkout.stripe.test/cs_test');
    expect($this->gateway->createdCheckoutSessions[0]['customer'])->toBe('cus_existing');

    activateSubscription($this, 'sub_2');
    $this->actingAs($this->staff)->get('/_test/protected')->assertOk();
    expect(FacilitySubscription::count())->toBe(2);
});

test('checkout is refused while a subscription is active', function () {
    activateSubscription($this);

    $this->actingAs($this->owner)->post(route('subscription.checkout'))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('status', 'fail');
    expect($this->gateway->createdCheckoutSessions)->toBeEmpty();
});

test('subscribing again closes a lapsed past due subscription to avoid double billing', function () {
    activateSubscription($this);
    $this->gateway->putSubscription('sub_1', ['status' => 'past_due']);
    sendSubscriptionWebhook($this, 'customer.subscription.updated', ['id' => 'sub_1', 'object' => 'subscription']);
    $this->travel(8)->days();

    $this->actingAs($this->owner)->post(route('subscription.checkout'))->assertRedirect('https://checkout.stripe.test/cs_test');

    expect($this->gateway->canceledSubscriptions)->toBe(['sub_1'])
        ->and(FacilitySubscription::first()->status)->toBe('canceled');
});

test('duplicate webhook events do not create duplicate records', function () {
    activateSubscription($this);
    $invoice = invoicePayload('in_dup', 'sub_1', 'paid');

    sendSubscriptionWebhook($this, 'invoice.paid', $invoice, 'evt_dup')->assertOk();
    sendSubscriptionWebhook($this, 'invoice.paid', $invoice, 'evt_dup')->assertOk();
    // Same invoice delivered under a different event id is still upserted once.
    sendSubscriptionWebhook($this, 'invoice.paid', $invoice, 'evt_dup_2')->assertOk();

    expect(StripeSubscriptionWebhookEvent::where('stripe_event_id', 'evt_dup')->count())->toBe(1)
        ->and(FacilitySubscriptionInvoice::count())->toBe(1)
        ->and(FacilitySubscription::count())->toBe(1);
});

test('webhook rejects an invalid signature', function () {
    $this->call('POST', '/stripe/subscription-webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=bad',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['id' => 'evt_bad', 'type' => 'invoice.paid', 'data' => ['object' => []]]))->assertStatus(400);

    expect(StripeSubscriptionWebhookEvent::count())->toBe(0);
});

test('one facility cannot access another facility\'s billing', function () {
    // Events for a subscription owned by another facility are ignored.
    $this->gateway->putSubscription('sub_other', ['metadata' => ['facility_key' => 'other-facility']]);
    sendSubscriptionWebhook($this, 'customer.subscription.created', ['id' => 'sub_other', 'object' => 'subscription'])->assertOk();
    sendSubscriptionWebhook($this, 'checkout.session.completed', [
        'id' => 'cs_other', 'object' => 'checkout.session', 'mode' => 'subscription',
        'metadata' => ['facility_key' => 'other-facility'], 'subscription' => 'sub_other',
    ])->assertOk();
    sendSubscriptionWebhook($this, 'invoice.paid', invoicePayload('in_other', 'sub_other', 'paid'))->assertOk();

    expect(FacilitySubscription::count())->toBe(0)
        ->and(FacilitySubscriptionInvoice::count())->toBe(0);

    // A checkout session from another facility cannot be claimed via the success URL.
    $this->gateway->checkoutSessions['cs_other'] = [
        'id' => 'cs_other', 'mode' => 'subscription', 'subscription' => 'sub_other',
        'metadata' => ['facility_key' => 'other-facility'],
    ];
    $this->actingAs($this->owner)->get(route('subscription.success', ['session_id' => 'cs_other']))
        ->assertRedirect(route('subscription.index'));
    expect(FacilitySubscription::count())->toBe(0);

    // Staff cannot view or change billing.
    activateSubscription($this);
    $this->actingAs($this->staff)->get(route('subscription.index'))->assertRedirect(route('dashboard'));
    $this->actingAs($this->staff)->post(route('subscription.cancel'))->assertRedirect(route('dashboard'));
    expect(FacilitySubscription::first()->cancel_at_period_end)->toBeFalse();
});
