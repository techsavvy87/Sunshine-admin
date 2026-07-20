@extends('layouts.main')
@section('title', 'Payments')

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Payments</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Financials</li>
      <li>Payments</li>
    </ul>
  </div>
</div>

<div class="mt-3">
  @include('layouts.alerts')

  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h4 class="font-medium">Payment History</h4>
        </div>
      </div>

      <form method="GET" action="{{ route('financials.payments') }}" class="mt-4">
        <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}" />
        <div class="grid grid-cols-1 gap-2 xl:grid-cols-4">
          <input
            type="search"
            name="search"
            value="{{ $search }}"
            class="input input-sm w-full"
            placeholder="Search invoice, customer, authorization code, or Stripe ID"
          />
          <select name="payment_type" class="select select-sm w-full">
            <option value="">All payment types</option>
            <option value="Cash" {{ $paymentType === 'Cash' ? 'selected' : '' }}>Cash</option>
            <option value="Check" {{ $paymentType === 'Check' ? 'selected' : '' }}>Check</option>
            <option value="Credit Card (Terminal)" {{ $paymentType === 'Credit Card (Terminal)' ? 'selected' : '' }}>Credit Card (Terminal)</option>
            <option value="Online Credit Card (Stripe)" {{ $paymentType === 'Online Credit Card (Stripe)' ? 'selected' : '' }}>Online Credit Card (Stripe)</option>
          </select>
          <select name="payment_status" class="select select-sm w-full">
            <option value="">All statuses</option>
            <option value="paid" {{ $paymentStatus === 'paid' ? 'selected' : '' }}>Paid</option>
            <option value="pending" {{ $paymentStatus === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="failed" {{ $paymentStatus === 'failed' ? 'selected' : '' }}>Failed</option>
            <option value="refunded" {{ $paymentStatus === 'refunded' ? 'selected' : '' }}>Refunded</option>
          </select>
          <div class="flex gap-2">
            <button class="btn btn-soft btn-primary btn-sm">
              <span class="iconify lucide--search size-4"></span>
              Search
            </button>
            @if($search !== '' || $paymentType !== '' || $paymentStatus !== '')
            <a href="{{ route('financials.payments') }}" class="btn btn-ghost btn-sm">Clear</a>
            @endif
          </div>
        </div>
      </form>

      <div class="mt-4 overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Invoice / Appointment</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Payment Type</th>
              <th>Status</th>
              <th>Payment Date</th>
              <th>Payment Reference</th>
            </tr>
          </thead>
          <tbody>
            @forelse($payments as $payment)
            <tr>
              <td class="text-sm">
                <div class="flex flex-col">
                  <span class="font-medium">{{ $payment->invoice_reference }}</span>
                  <span class="text-xs text-base-content/70">{{ $payment->appointment_reference ?: 'No appointment reference' }}</span>
                </div>
              </td>
              <td class="text-sm">
                <div class="flex items-center gap-3">
                  <div class="avatar">
                    <div class="mask mask-squircle w-9 h-9 bg-base-200 text-base-content/70 flex items-center justify-center">
                      @if(!empty($payment->customer_avatar_url))
                        <img src="{{ $payment->customer_avatar_url }}" alt="{{ $payment->customer_name }}" />
                      @else
                        <span class="text-xs font-semibold">{{ $payment->customer_initials }}</span>
                      @endif
                    </div>
                  </div>
                  <div class="truncate">
                    <span class="block font-medium truncate">{{ $payment->customer_name }}</span>
                  </div>
                </div>
              </td>
              <td class="text-sm font-medium">${{ number_format($payment->amount, 2) }}</td>
              <td class="text-sm">
                @php
                  $paymentTypeClasses = match($payment->payment_type) {
                    'Cash' => 'badge-soft badge-success',
                    'Check' => 'badge-soft badge-warning',
                    'Credit Card (Terminal)' => 'badge-soft badge-secondary',
                    default => 'badge-soft badge-info'
                  };
                @endphp
                <span class="badge badge-sm {{ $paymentTypeClasses }}">
                  {{ $payment->payment_type }}
                </span>
              </td>
              <td class="text-sm">
                @php
                  $statusClasses = match($payment->payment_status) {
                    'paid' => 'badge-soft badge-success',
                    'pending' => 'badge-soft badge-warning',
                    'failed' => 'badge-soft badge-error',
                    'refunded' => 'badge-soft badge-secondary',
                    default => 'badge-soft'
                  };
                @endphp
                <span class="badge badge-sm {{ $statusClasses }}">{{ ucfirst($payment->payment_status) }}</span>
              </td>
              <td class="text-sm whitespace-nowrap">
                {{ $payment->payment_date ? $payment->payment_date->format('m/d/Y h:i A') : '-' }}
              </td>
              <td class="text-sm">
                @if($payment->authorization_code)
                <span class="font-mono text-xs">{{ $payment->authorization_code }}</span>
                @elseif($payment->stripe_payment_id)
                <span class="font-mono text-xs">{{ $payment->stripe_payment_id }}</span>
                @else
                <span class="text-base-content/60">-</span>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="py-8 text-center text-base-content/60">No payments found.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        {{ $payments->links('layouts.pagination', ['items' => $payments, 'per_page_options' => [10, 20, 50, 100], 'default_per_page' => 20]) }}
      </div>
    </div>
  </div>
</div>
@endsection
