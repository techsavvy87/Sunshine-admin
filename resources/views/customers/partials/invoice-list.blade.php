@if(isset($invoices) && $invoices->count() > 0)
  <div class="overflow-x-auto">
    <table class="table text-base-content/70 text-xs mt-3">
      <thead>
        <tr>
          <th>Invoice Number</th>
          <th>Issued at</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($invoices as $invoice)
          <tr>
            <td>{{ $invoice->invoice_number }}</td>
            <td>{{ $invoice->issued_at ? \Carbon\Carbon::parse($invoice->issued_at)->format('m/d/Y h:i A') : '—' }}</td>
            <td>
              @if($invoice->status === 'paid')
                <div class="badge badge-soft badge-success badge-sm">Paid</div>
              @elseif($invoice->status === 'partially_paid')
                <div class="badge badge-soft badge-warning badge-sm">Partially Paid</div>
              @else
                <div class="badge badge-soft badge-info badge-sm">Sent</div>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div class="mt-3">
    {{ $invoices->links('layouts.pagination', ['items' => $invoices, 'per_page_options' => [5, 10, 20, 50], 'default_per_page' => 5]) }}
  </div>
@else
  <p class="text-base-content/70 text-sm mt-3">No invoices found (sent or paid).</p>
@endif
