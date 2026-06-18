@extends('layouts.main')
@section('title', 'Payouts')

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Payouts</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Financials</li>
      <li>Payouts</li>
    </ul>
  </div>
</div>

<div class="mt-3">
  @include('layouts.alerts')

  @if($balanceError)
  <div class="alert alert-error alert-soft mt-3" role="alert">
    <span class="iconify lucide--info size-4"></span>
    <span>{{ $balanceError }}</span>
  </div>
  @endif

  <div class="card bg-base-100 shadow">
    <div class="card-body p-5">
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-lg border border-base-200 bg-base-100 p-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Available balance</p>
          <h4 class="mt-2 text-3xl font-semibold leading-none">
            {{ data_get($balanceSummary, 'available.display', '$0.00') }}
          </h4>
          <p class="mt-2 text-xs text-base-content/60">Includes cash and card income.</p>
        </div>

        <div class="rounded-lg border border-base-200 bg-base-100 p-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Card withdrawable</p>
          <h4 class="mt-2 text-3xl font-semibold leading-none">
            {{ data_get($balanceSummary, 'withdrawable.display', '$0.00') }}
          </h4>
          <p class="mt-2 text-xs text-base-content/60">Stripe payout limit after prior withdrawals.</p>
        </div>

        <div class="rounded-lg border border-base-200 bg-base-100 p-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Cash</p>
          <p class="mt-2 text-2xl font-semibold leading-none">
            {{ data_get($balanceSummary, 'breakdown.cash.display', '$0.00') }}
          </p>
        </div>

        <div class="rounded-lg border border-base-200 bg-base-100 p-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Card</p>
          <p class="mt-2 text-2xl font-semibold leading-none">
            {{ data_get($balanceSummary, 'breakdown.card.display', '$0.00') }}
          </p>
        </div>

        <div class="rounded-lg border border-base-200 bg-base-50 p-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Pending balance</p>
          <p class="mt-2 text-2xl font-semibold leading-none">
            {{ data_get($balanceSummary, 'pending.display', '$0.00') }}
          </p>
          <p class="mt-2 text-xs text-base-content/60">
            Paid out: {{ data_get($balanceSummary, 'breakdown.payouts.display', '$0.00') }}
          </p>
        </div>
      </div>

      <div class="mt-4 flex justify-start">
        <button
          type="button"
          class="btn btn-primary btn-sm"
          onclick="document.getElementById('withdraw_modal').showModal()"
          {{ data_get($balanceSummary, 'withdrawable.raw', 0) <= 0 || !hasPermission(14, 'can_create') ? 'disabled' : '' }}
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-landmark-icon lucide-landmark"><path d="M10 18v-7"/><path d="M11.119 2.205a2 2 0 0 1 1.762 0l7.84 3.846A.5.5 0 0 1 20.5 7h-17a.5.5 0 0 1-.22-.949z"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="M3 22h18"/><path d="M6 18v-7"/></svg>
          Withdraw
        </button>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow mt-4">
    <div class="card-body p-4">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h4 class="font-medium">Payout History</h4>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Amount</th>
              <th>Status</th>
              <th>Stripe Payout ID</th>
              <th>Requested Date</th>
              <th>Expected Arrival Date</th>
              <th>Requested By</th>
            </tr>
          </thead>
          <tbody>
            @forelse($payouts as $payout)
            <tr>
              <td class="text-sm font-medium">${{ number_format($payout->amount, 2) }}</td>
              <td class="text-sm">
                @php
                    $payoutStatusClasses = match($payout->status) {
                        'paid' => 'badge-soft badge-success',
                        'pending', 'in_transit' => 'badge-soft badge-warning',
                        'failed', 'canceled' => 'badge-soft badge-error',
                        default => 'badge-soft badge-info',
                    };

                    $payoutStatusLabel = match($payout->status) {
                        'paid' => 'Completed',
                        'pending' => 'Pending',
                        'in_transit' => 'Processing',
                        'failed' => 'Failed',
                        'canceled' => 'Canceled',
                        default => ucfirst(str_replace('_', ' ', $payout->status)),
                    };
                @endphp

                <span class="badge badge-sm {{ $payoutStatusClasses }}">
                    {{ $payoutStatusLabel }}
                </span>
              </td>
              <td class="text-sm"><span class="font-mono text-xs">{{ $payout->stripe_payout_id }}</span></td>
              <td class="text-sm whitespace-nowrap">{{ $payout->created_at?->format('m/d/Y h:i A') ?? '-' }}</td>
              <td class="text-sm whitespace-nowrap">{{ $payout->arrival_date?->format('m/d/Y') ?? '-' }}</td>
              <td class="text-sm">{{ $payout->creator?->name ?? 'System' }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="py-8 text-center text-base-content/60">No payouts requested yet.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        {{ $payouts->links('layouts.pagination', ['items' => $payouts, 'per_page_options' => [10, 20, 50, 100], 'default_per_page' => 10]) }}
      </div>
    </div>
  </div>
</div>

<dialog id="withdraw_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 class="text-lg font-semibold">Confirm Withdrawal</h3>
        <p class="mt-2 text-sm text-base-content/70">Enter how much card income to transfer to the linked bank account.</p>
      </div>
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>

    <form method="POST" action="{{ route('financials.payouts.withdraw') }}" id="withdraw_form" class="mt-5 space-y-4">
      @csrf
      <div>
        <label class="label" for="withdraw_amount">
          <span class="label-text font-medium">Withdrawal amount</span>
        </label>
        <input
          id="withdraw_amount"
          name="amount"
          type="number"
          min="0.01"
          step="0.01"
          max="{{ number_format((float) data_get($balanceSummary, 'withdrawable.raw', 0), 2, '.', '') }}"
          value="{{ number_format((float) data_get($balanceSummary, 'withdrawable.raw', 0), 2, '.', '') }}"
          class="input input-bordered w-full"
          {{ data_get($balanceSummary, 'withdrawable.raw', 0) <= 0 || !hasPermission(14, 'can_create') ? 'disabled' : '' }}
        />
        <div class="mt-2 flex items-center justify-between text-xs text-base-content/60">
          <span>Max card withdrawable: {{ data_get($balanceSummary, 'withdrawable.display', '$0.00') }}</span>
          <span id="withdraw_confirmation_preview">
            Withdraw {{ data_get($balanceSummary, 'withdrawable.display', '$0.00') }}
          </span>
        </div>
      </div>

      <p class="text-sm text-base-content/70">
        Are you sure you want to withdraw <span id="withdraw_confirmation_amount">{{ data_get($balanceSummary, 'withdrawable.display', '$0.00') }}</span> to the linked bank account?
      </p>

      <div class="modal-action mt-0">
        <button class="btn btn-ghost" type="button" onclick="document.getElementById('withdraw_modal').close()">Cancel</button>
        <button
          type="submit"
          class="btn btn-primary"
          {{ data_get($balanceSummary, 'withdrawable.raw', 0) <= 0 || !hasPermission(14, 'can_create') ? 'disabled' : '' }}
        >
          Confirm Withdrawal
        </button>
      </div>
    </form>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<script>
  (function () {
    const amountInput = document.getElementById('withdraw_amount');
    const amountText = document.getElementById('withdraw_confirmation_amount');
    const amountPreview = document.getElementById('withdraw_confirmation_preview');

    if (!amountInput || !amountText || !amountPreview) {
      return;
    }

    const formatCurrency = (value) => {
      const numericValue = Number.parseFloat(value);

      if (Number.isNaN(numericValue)) {
        return '$0.00';
      }

      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
      }).format(numericValue);
    };

    const syncWithdrawalText = () => {
      const formattedValue = formatCurrency(amountInput.value);
      amountText.textContent = formattedValue;
      amountPreview.textContent = `Withdraw ${formattedValue}`;
    };

    amountInput.addEventListener('input', syncWithdrawalText);
    syncWithdrawalText();
  })();
</script>
@endsection
