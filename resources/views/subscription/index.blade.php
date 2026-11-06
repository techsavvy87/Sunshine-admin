@extends('layouts.main')
@section('title', 'Subscription')

@section('content')
@php
  $status = $subscription?->status;
  $cancelScheduled = $subscription?->isCancellationScheduled() ?? false;
  $inGrace = $subscription?->isInGracePeriod() ?? false;

  [$statusLabel, $statusClass] = match (true) {
      !$subscription => ['Not subscribed', 'badge-soft badge-neutral'],
      $cancelScheduled => ['Cancels at period end', 'badge-soft badge-warning'],
      $inGrace => ['Payment failed', 'badge-soft badge-warning'],
      $hasAccess => ['Active', 'badge-soft badge-success'],
      default => ['Inactive', 'badge-soft badge-error'],
  };

  $formatMoney = fn ($cents, $currency) => strtoupper($currency) === 'USD'
      ? '$' . number_format($cents / 100, 2)
      : number_format($cents / 100, 2) . ' ' . strtoupper($currency);
@endphp

<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Subscription</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li class="opacity-80">Subscription</li>
    </ul>
  </div>
</div>

<div class="mt-3">
  @include('layouts.alerts')

  @if ($inGrace)
    <div class="alert alert-warning alert-soft mb-3" role="alert">
      <span class="iconify lucide--triangle-alert size-4"></span>
      <span>
        Your latest subscription payment failed. Update your payment method before
        <strong>{{ $subscription->grace_period_ends_at->format('M j, Y') }}</strong> to keep access.
      </span>
    </div>
  @endif

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="card-title">Monthly Plan</div>
        @if ($plan)
          <p class="font-medium mt-2">{{ $plan['name'] }}</p>
          @if ($plan['description'])
            <p class="text-sm text-base-content/70">{{ $plan['description'] }}</p>
          @endif
          <p class="mt-3">
            <span class="text-3xl font-semibold">
              {{ $plan['currency'] === 'USD' ? '$' . number_format($plan['amount'], 2) : number_format($plan['amount'], 2) . ' ' . $plan['currency'] }}
            </span>
            <span class="text-base-content/60">
              / {{ $plan['interval_count'] > 1 ? $plan['interval_count'] . ' ' . $plan['interval'] . 's' : $plan['interval'] }}
            </span>
          </p>
          <p class="text-sm text-base-content/60 mt-1">Billed automatically each month. Cancel anytime.</p>
        @else
          <p class="text-base-content/70 mt-2">Plan details are unavailable right now. Please try again later.</p>
        @endif

        @if (!$hasAccess)
          <form action="{{ route('subscription.checkout') }}" method="POST" class="mt-4">
            @csrf
            <button type="submit" class="btn btn-primary w-full sm:w-auto" @disabled(!$plan)>
              <span class="iconify lucide--credit-card size-4"></span>
              {{ $subscription ? 'Subscribe Again' : 'Subscribe Now' }}
            </button>
          </form>
        @endif
      </div>
    </div>

    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="card-title">Current Status</div>
        <div class="mt-2 space-y-2 text-sm">
          <div class="flex items-center justify-between gap-3">
            <span class="text-base-content/70">Status</span>
            <span class="badge badge-sm {{ $statusClass }}">{{ $statusLabel }}</span>
          </div>
          @if ($subscription?->current_period_end)
            <div class="flex items-center justify-between gap-3">
              <span class="text-base-content/70">
                @if ($cancelScheduled)
                  Access ends on
                @elseif ($hasAccess)
                  Next renewal
                @else
                  Ended on
                @endif
              </span>
              <span class="font-medium">{{ ($subscription->ended_at && !$hasAccess ? $subscription->ended_at : $subscription->current_period_end)->format('M j, Y') }}</span>
            </div>
          @endif
          @if ($subscription?->user)
            <div class="flex items-center justify-between gap-3">
              <span class="text-base-content/70">Subscribed by</span>
              <span>{{ $subscription->user->name }}</span>
            </div>
          @endif
        </div>

        @if ($subscription)
          <div class="mt-4 flex flex-wrap gap-2">
            @if ($hasAccess && !$cancelScheduled)
              <button type="button" class="btn btn-soft btn-error" onclick="document.getElementById('cancel_subscription_modal').showModal()">
                Cancel Subscription
              </button>
            @endif
            @if ($cancelScheduled)
              <form action="{{ route('subscription.resume') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-soft btn-primary">Keep Subscription</button>
              </form>
            @endif
            @if ($subscription->stripe_customer_id)
              <form action="{{ route('subscription.portal') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-soft">Manage Payment Method</button>
              </form>
            @endif
          </div>
          @if ($cancelScheduled)
            <p class="text-sm text-base-content/60 mt-3">
              Your subscription will not renew. You keep full access until the end of the paid period.
            </p>
          @endif
        @endif
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow mt-4">
    <div class="card-body p-4">
      <h4 class="font-medium">Billing History</h4>
      <div class="mt-4 overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Period</th>
              <th>Amount</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($invoices as $invoice)
              @php
                $invoiceClass = match ($invoice->status) {
                    'paid' => 'badge-soft badge-success',
                    'open' => 'badge-soft badge-warning',
                    'uncollectible', 'void' => 'badge-soft badge-error',
                    default => 'badge-soft badge-info',
                };
              @endphp
              <tr>
                <td class="text-sm"><span class="font-mono text-xs">{{ $invoice->number ?? $invoice->stripe_invoice_id }}</span></td>
                <td class="text-sm whitespace-nowrap">
                  {{ $invoice->period_start?->format('m/d/Y') ?? '-' }} – {{ $invoice->period_end?->format('m/d/Y') ?? '-' }}
                </td>
                <td class="text-sm font-medium">
                  {{ $formatMoney($invoice->status === 'paid' ? $invoice->amount_paid : $invoice->amount_due, $invoice->currency) }}
                </td>
                <td class="text-sm">
                  <span class="badge badge-sm {{ $invoiceClass }}">{{ $invoice->status === 'open' ? 'Payment failed' : ucfirst($invoice->status) }}</span>
                </td>
                <td class="text-sm text-end">
                  @if ($invoice->hosted_invoice_url)
                    <a href="{{ $invoice->hosted_invoice_url }}" target="_blank" rel="noopener" class="link link-primary">View</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="py-8 text-center text-base-content/60">No subscription invoices yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@if ($hasAccess && !$cancelScheduled)
<dialog id="cancel_subscription_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-center">
      <span class="iconify lucide--triangle-alert size-10 text-error"></span>
    </div>
    <h3 class="mt-3 text-center text-lg font-semibold">Cancel subscription?</h3>
    <p class="py-4 text-center text-base-content/80">
      Your subscription will stay active until
      <strong>{{ $subscription->current_period_end?->format('M j, Y') }}</strong>.
      After that, access to the portal's management features will be restricted.
    </p>
    <div class="flex items-center justify-center gap-2 mt-2">
      <form method="dialog">
        <button class="btn btn-ghost">Keep Subscription</button>
      </form>
      <form action="{{ route('subscription.cancel') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-error">Cancel Subscription</button>
      </form>
    </div>
  </div>
</dialog>
@endif
@endsection
