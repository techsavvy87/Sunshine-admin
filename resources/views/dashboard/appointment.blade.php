@extends('layouts.main')
@section('title', 'Appointment Detail')

@section('page-css')
<style>
  .avatar-img {
    object-fit: cover;
    height: 150px;
    width: 150px;
  }
  .table th,
  .table td {
    padding-block: 0.4rem;
  }
  .ms-4 {
    margin-left: 1rem;
  }
  .collapse>input:is([type=checkbox],[type=radio]) {
    min-height: 1rem !important;
  }
  .collapse-title {
    min-height: 1rem !important;
  }
  .collapse-arrow>.collapse-title:after {
    top: 1.1rem;
    width: 0.4rem;
    height: 0.4rem;
  }

  #inventory_item + .select2-container .select2-selection--single {
    min-height: 32px;   /* Adjust as needed */
    height: 32px;       /* Adjust as needed */
    padding: 4px 8px;   /* Adjust as needed */
    font-size: 14px;    /* Optional: smaller text */
    line-height: 24px;  /* Optional: vertical align */
  }

  #inventory_item + .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 24px !important; /* Match the line-height of the selection box */
  }

  #additional_services_link + .select2-container--default .select2-selection--multiple {
    min-height: 32px;
    height: 32px;
    overflow-y: auto;
    overflow-x: hidden !important;
    white-space: normal !important;
  }

  #additional_services_link + .select2-container--default .select2-selection--multiple .select2-selection__choice {
    margin-top: 6px !important;
    margin-left: 6px !important;
  }

  #additional_services_link + .select2-container .select2-search--inline .select2-search__field {
    margin-top: 6px !important;
    margin-left: 6px !important;
  }

  .behavior-option {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .behavior-option .behavior-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    flex: 0 0 auto;
    color: var(--color-base-content);
  }

  .behavior-option .behavior-icon svg,
  .behavior-selection-icon svg {
    width: 1rem;
    height: 1rem;
    display: block;
  }

  .behavior-option .behavior-icon .iconify,
  .behavior-selection-icon .iconify {
    width: 1rem;
    height: 1rem;
    display: inline-block;
  }

  .behavior-selection-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.7rem;
    height: 1.7rem;
  }

  .behavior-selection-chip .behavior-selection-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.05rem;
    height: 1.05rem;
    line-height: 1;
  }

  #pet_behavior_id + .select2-container--default .select2-selection--multiple {
    min-height: 2.75rem;
    padding: 0.25rem;
  }

  .js-click-tooltip:hover::before,
  .js-click-tooltip:hover::after,
  .js-click-tooltip:focus::before,
  .js-click-tooltip:focus::after {
    opacity: 0 !important;
    visibility: hidden !important;
  }

  .js-click-tooltip:not(.tooltip-open)::before,
  .js-click-tooltip:not(.tooltip-open)::after {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
  }

  .js-click-tooltip.tooltip-open::before,
  .js-click-tooltip.tooltip-open::after {
    opacity: 1 !important;
    visibility: visible !important;
  }
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Appointment Detail</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('service-dashboard', $appointment->service_id) }}">Appointments</a></li>
      <li class="opacity-80">Detail</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  @php
    $showBoardingDatesInHeader = isBoardingService($appointment->service);
    $headerCheckinDate = $checkedIn->date ?? $appointment->date ?? null;
    $isCheckoutSaved = !empty($checkout->date);

    $checkoutFlowValues = [];
    if ($checkout && !empty($checkout->flows)) {
      if (is_array($checkout->flows)) {
        $checkoutFlowValues = $checkout->flows;
      } else {
        $decodedCheckoutFlows = json_decode($checkout->flows, true);
        $checkoutFlowValues = is_array($decodedCheckoutFlows) ? $decodedCheckoutFlows : [];
      }
    }

    $headerScheduledPickupAt = null;
    if (!empty($appointment->end_date) && !empty($appointment->end_time)) {
      $headerScheduledPickupAt = \Carbon\Carbon::parse($appointment->end_date . ' ' . $appointment->end_time);
    }

    $invoiceStatusForCheckoutHeader = strtolower((string) ($invoice->status ?? ''));
    $useLiveCheckoutClock = !in_array($invoiceStatusForCheckoutHeader, ['paid', 'finalized'], true);

    $headerActualCheckoutAt = null;
    if (!empty($checkoutFlowValues['actual_checkout_at'])) {
      try {
        $headerActualCheckoutAt = \Carbon\Carbon::parse($checkoutFlowValues['actual_checkout_at']);
      } catch (\Throwable $e) {
        $headerActualCheckoutAt = null;
      }
    }

    if (!$headerActualCheckoutAt && $isCheckoutSaved && !empty($checkout->date) && !empty($checkoutFlowValues['actual_checkout_time'])) {
      try {
        $headerActualCheckoutAt = \Carbon\Carbon::parse($checkout->date . ' ' . $checkoutFlowValues['actual_checkout_time']);
      } catch (\Throwable $e) {
        $headerActualCheckoutAt = null;
      }
    }

    if ($appointment->status === 'completed' && $useLiveCheckoutClock) {
      $headerActualCheckoutAt = now();
    } elseif (!$headerActualCheckoutAt && $appointment->status === 'completed') {
      $headerActualCheckoutAt = now();
    }

    $headerCheckinTime = $appointment->start_time ?? null;
    $headerCheckoutDate = $headerActualCheckoutAt
      ? $headerActualCheckoutAt->toDateString()
      : ($isCheckoutSaved
        ? $checkout->date
        : ($appointment->status === 'completed' ? now()->toDateString() : ($appointment->end_date ?? $appointment->date ?? null)));
    $headerCheckoutTime = $headerActualCheckoutAt
      ? $headerActualCheckoutAt->format('H:i:s')
      : (($appointment->status === 'completed' && !$isCheckoutSaved)
        ? now()->format('H:i:s')
        : ($appointment->end_time ?? null));

    $headerPickupIsLate = null;
    $headerPickupIsEarly = null;
    $headerLateDurationText = null;
    $headerLateHoursDecimal = 0;
    $headerEarlyDurationText = null;

    if ($appointment->status === 'completed' && $headerScheduledPickupAt && $headerActualCheckoutAt) {
      $headerLateSeconds = $headerScheduledPickupAt->diffInSeconds($headerActualCheckoutAt, false);
      $headerPickupIsLate = $headerLateSeconds > 0;
      $headerPickupIsEarly = $headerLateSeconds < 0;

      if ($headerPickupIsLate) {
        $headerLateHoursDecimal = round($headerLateSeconds / 3600, 2);
        $headerLateMinutesTotal = intdiv($headerLateSeconds, 60);
        $headerLateDays = intdiv($headerLateMinutesTotal, 1440);
        $headerLateHours = intdiv($headerLateMinutesTotal % 1440, 60);
        $headerLateMinutes = $headerLateMinutesTotal % 60;

        $headerLateParts = [];
        if ($headerLateDays > 0) {
          $headerLateParts[] = $headerLateDays . ' day' . ($headerLateDays > 1 ? 's' : '');
        }
        if ($headerLateHours > 0) {
          $headerLateParts[] = $headerLateHours . ' hour' . ($headerLateHours > 1 ? 's' : '');
        }
        if ($headerLateMinutes > 0 || empty($headerLateParts)) {
          $headerLateParts[] = $headerLateMinutes . ' minute' . ($headerLateMinutes > 1 ? 's' : '');
        }

        $headerLateDurationText = implode(' ', $headerLateParts);
      } elseif ($headerPickupIsEarly) {
        $headerEarlySeconds = abs($headerLateSeconds);
        $headerEarlyMinutesTotal = intdiv($headerEarlySeconds, 60);
        $headerEarlyDays = intdiv($headerEarlyMinutesTotal, 1440);
        $headerEarlyHours = intdiv($headerEarlyMinutesTotal % 1440, 60);
        $headerEarlyMinutes = $headerEarlyMinutesTotal % 60;

        $headerEarlyParts = [];
        if ($headerEarlyDays > 0) {
          $headerEarlyParts[] = $headerEarlyDays . ' day' . ($headerEarlyDays > 1 ? 's' : '');
        }
        if ($headerEarlyHours > 0) {
          $headerEarlyParts[] = $headerEarlyHours . ' hour' . ($headerEarlyHours > 1 ? 's' : '');
        }
        if ($headerEarlyMinutes > 0 || empty($headerEarlyParts)) {
          $headerEarlyParts[] = $headerEarlyMinutes . ' minute' . ($headerEarlyMinutes > 1 ? 's' : '');
        }

        $headerEarlyDurationText = implode(' ', $headerEarlyParts);
      }
    }

    $headerDaycarePricingService = \App\Models\Service::query()
      ->whereHas('category', function ($query) {
        $query->whereRaw('LOWER(name) LIKE ?', ['%daycare%']);
      })
      ->where('status', 'active')
      ->orderBy('id')
      ->first();
    $headerDaycarePrice = floatval($headerDaycarePricingService->price ?? $headerDaycarePricingService->price_medium ?? $headerDaycarePricingService->price_small ?? 0);
    $headerDaycareDuration = floatval($headerDaycarePricingService->duration ?? $headerDaycarePricingService->duration_medium ?? $headerDaycarePricingService->duration_small ?? 0);
    $headerLateCheckoutThresholdHours = 1;
    $headerLateCheckoutHourlyRate = ($headerDaycarePrice > 0 && $headerDaycareDuration > 0) ? round($headerDaycarePrice / $headerDaycareDuration, 2) : 0;
    $headerLateCheckoutFormulaFee = ($headerPickupIsLate && $headerLateHoursDecimal >= $headerLateCheckoutThresholdHours && $headerLateCheckoutHourlyRate > 0)
      ? round(floor($headerLateHoursDecimal) * $headerLateCheckoutHourlyRate, 2)
      : 0;
    $headerLateCheckoutServerFee = isset($lateCheckoutDaycareFeeDisplay)
      ? floatval($lateCheckoutDaycareFeeDisplay)
      : 0;
    $headerLateCheckoutDaycareFee = max($headerLateCheckoutServerFee, $headerLateCheckoutFormulaFee);
    $invoiceLateCheckoutDaycareFee = max($headerLateCheckoutServerFee, $headerLateCheckoutFormulaFee, $headerLateCheckoutDaycareFee);
  @endphp
  <div class="grid grid-cols-1 gap-2 xl:grid-cols-5 border border-base-300 rounded-box px-5 py-2 text-sm">
    <div class="flex items-center gap-2">
      <p class="font-medium">Service: </p>
      <p class="text-base-content/70">{{ $appointment->service->name }}</p>
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Staff: </p>
      @if ($appointment->staff)
        @if ($appointment->staff->profile)
          <p class="text-base-content/70">{{ $appointment->staff->profile->first_name }} {{ $appointment->staff->profile->last_name }}</p>
        @else
          <p class="text-base-content/70">{{ $appointment->staff->name }}</p>
        @endif
      @else
        <p class="text-base-content/70">Unassigned</p>
      @endif
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Check-in:</p>
      @if($showBoardingDatesInHeader && $headerCheckinDate)
        <p class="text-base-content/70">{{ \Carbon\Carbon::parse($headerCheckinDate)->format('M j, Y') }}{{ $headerCheckinTime ? ', ' . \Carbon\Carbon::parse($headerCheckinTime)->format('g:i A') : '' }}</p>
      @else
        <p class="text-base-content/70">N/A</p>
      @endif
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Checkout:</p>
      @if($showBoardingDatesInHeader && $headerCheckoutDate)
        <div class="flex items-center gap-2">
          <p class="text-base-content/70 whitespace-nowrap shrink-0">{!! \Carbon\Carbon::parse($headerCheckoutDate)->format('M j, Y') . ($headerCheckoutTime ? ', ' . \Carbon\Carbon::parse($headerCheckoutTime)->format('g:i') . '&nbsp;' . \Carbon\Carbon::parse($headerCheckoutTime)->format('A') : '') !!}</p>
          @if($appointment->status === 'completed')
            @if(is_null($headerPickupIsLate))
              <div class="badge badge-soft badge-secondary badge-sm whitespace-nowrap shrink-0 min-w-max">Unavailable</div>
            @elseif($headerPickupIsLate)
              <div class="badge badge-soft badge-warning badge-sm whitespace-nowrap shrink-0 min-w-max">Late</div>
            @elseif($headerPickupIsEarly)
              <div class="badge badge-soft badge-info badge-sm whitespace-nowrap shrink-0 min-w-max">Early</div>
            @else
              <div class="badge badge-soft badge-success badge-sm whitespace-nowrap shrink-0 min-w-max">In Time</div>
            @endif
            <div class="dropdown dropdown-end dropdown-hover dropdown-bottom flex">
              <div tabindex="0" role="button" class="cursor-help flex relative z-[2]">
                <span class="iconify lucide--info size-4 text-base-content/60"></span>
              </div>
              <div tabindex="0" class="dropdown-content z-[1] mt-2 menu p-3 shadow bg-base-100 rounded-box w-72 border border-base-300" style="left: -12px;">
                <div class="space-y-1 text-xs text-base-content/80">
                  @if($headerScheduledPickupAt)
                    <p>Scheduled pickup: {{ $headerScheduledPickupAt->format('M j, Y g:i A') }}</p>
                    <p>Actual checkout: {{ $headerActualCheckoutAt->format('M j, Y g:i A') }}</p>
                    @if($headerPickupIsLate && $headerLateDurationText)
                      <p class="text-warning">Late by {{ $headerLateDurationText }}</p>
                      @if($headerLateCheckoutDaycareFee > 0)
                        <p class="text-warning">Late Checkout Daycare Fee: ${{ number_format($headerLateCheckoutDaycareFee, 2) }}</p>
                      @endif
                    @elseif($headerPickupIsEarly && $headerEarlyDurationText)
                      <p class="text-info">Early by {{ $headerEarlyDurationText }}</p>
                    @endif
                  @else
                    <p>Scheduled pickup date/time is not available for this appointment.</p>
                  @endif
                </div>
              </div>
            </div>
            @endif
        </div>
      @else
        <p class="text-base-content/70">N/A</p>
      @endif
    </div>
    @if($appointment->status === 'completed' && $headerPickupIsLate && $headerLateDurationText)
    <div class="flex items-center gap-2">
      <p class="font-medium">Late:</p>
      <p class="text-base-content/70 whitespace-nowrap">{{ $headerLateDurationText }}</p>
    </div>
    @endif
    <div class="flex items-center gap-2" @if($appointment->status === 'completed' && !is_null($headerPickupIsLate) && !$headerPickupIsLate) style="padding-left: 15%;" @endif>
      <p class="font-medium">Status: </p>
      @if($appointment->status === 'checked_in')
        <div class="badge badge-soft badge-info badge-sm">Scheduled</div>
      @elseif($appointment->status === 'in_progress')
        <div class="badge badge-soft badge-primary badge-sm">{{ (isBoardingService($appointment->service) || isDaycareService($appointment->service)) ? 'On Property' : 'In Progress' }}</div>
      @elseif($appointment->status === 'completed')
        <div class="badge badge-soft badge-success badge-sm">{{ ucfirst($appointment->status) }}</div>
      @elseif($appointment->status === 'finished')
        <div class="badge badge-soft badge-success badge-sm">{{ ucfirst($appointment->status) }}</div>
      @elseif($appointment->status === 'cancelled')
        <div class="badge badge-soft badge-error badge-sm">{{ ucfirst($appointment->status) }}</div>
      @else
        <div class="badge badge-soft badge-secondary badge-sm">{{ ucfirst($appointment->status) }}</div>
      @endif
    </div>
    @if(isDaycareService($appointment->service) && $appointment->metadata)
      <div class="flex items-center gap-2">
        <p class="font-medium">Duration: </p>
        @if(isset($appointment->metadata['daycare_duration']))
          @if($appointment->metadata['daycare_duration'] === 'full_day')
            <div class="badge badge-soft badge-info badge-sm">Full Day</div>
          @else
            <div class="badge badge-soft badge-primary badge-sm">
              Half Day
              @if(isset($appointment->metadata['session']))
                ({{ ucfirst($appointment->metadata['session']) }})
              @endif
            </div>
          @endif
        @endif
      </div>
    @endif
    @if($appointment->additional_service_ids)
      @php
        $additionalServicesByPet = $appointment->additional_services_by_pet ?? [];
        $allAdditionalIds = collect($additionalServicesByPet)
          ->flatten()
          ->map(fn($id) => (int) $id)
          ->filter(fn($id) => $id > 0)
          ->unique()
          ->values();
        $additionalServiceNameMap = $allAdditionalIds->isNotEmpty()
          ? \App\Models\Service::whereIn('id', $allAdditionalIds->all())->pluck('name', 'id')
          : collect();

        $petsForAdditionalDisplay = $appointment->family_pets;
        if ($petsForAdditionalDisplay->isEmpty() && $appointment->pet) {
          $petsForAdditionalDisplay = collect([$appointment->pet]);
        }

        $groupedAdditionalNames = $petsForAdditionalDisplay->map(function ($pet) use ($additionalServicesByPet, $additionalServiceNameMap) {
          $petServiceNames = collect($additionalServicesByPet[$pet->id] ?? [])
            ->map(fn($serviceId) => $additionalServiceNameMap->get((int) $serviceId))
            ->filter()
            ->values();

          if ($petServiceNames->isEmpty()) {
            return null;
          }

          return $pet->name . ': ' . $petServiceNames->join(', ');
        })->filter()->values();
      @endphp
      @if($groupedAdditionalNames->isNotEmpty())
        <div class="flex items-center gap-2">
          <p class="font-medium">Additional: </p>
          <p class="text-base-content/70">{{ $groupedAdditionalNames->join(' | ') }}</p>
        </div>
      @endif
    @endif
    @php
      $headerInvoiceItems = collect();
      if ($invoice && $invoice->items) {
        $headerInvoiceItems = collect(dedupeBoardingAutoFeeInvoiceItems($invoice->items))->values();
      }
    @endphp
    @if (($appointment->estimated_price ?? 0) || ((float) ($dbEstimatedPrice ?? 0) > 0) || $headerInvoiceItems->count() > 0)
    @php
      $appointmentStateTaxRate = isBoardingService($appointment->service) ? (float) config('billing.state_tax_rate', 7) : 0;

      if ($headerInvoiceItems->count() > 0) {
        $invoiceItemsSubtotal = max(0, floatval($headerInvoiceItems->sum('price')) - floatval($invoice->discount_amount ?? 0));
        $estimatedPrice = round($invoiceItemsSubtotal, 2);
      } elseif ((float) ($dbEstimatedPrice ?? 0) > 0) {
        $estimatedPrice = (float) $dbEstimatedPrice;
      } else {
        $chauffeurServicePrices = $chauffeurPricingData['service_prices'] ?? [];
        $estimatedPrice = (float) ($appointment->estimated_price ?? 0) + array_sum($chauffeurServicePrices);
      }

      $estimatedNetPrice = $estimatedPrice;
      $estimatedPriceWithTax = $estimatedNetPrice * (1 + ($appointmentStateTaxRate / 100));
    @endphp
    <div class="flex items-center gap-2">
      <p class="font-medium">Estimated Price: </p>
      <p id="detail_estimated_price_text" class="text-base-content/70">${{ number_format($estimatedPriceWithTax, 2) }}</p>
      @if (isBoardingService($appointment->service) && $appointmentStateTaxRate > 0)
      <span class="badge badge-ghost badge-xs">incl. {{ number_format($appointmentStateTaxRate, 2) }}% tax</span>
      @endif
    </div>
    @php
      $detailInvoiceItemNames = $headerInvoiceItems
        ->filter(function ($invoiceItem) {
          $itemType = strtolower(trim((string) ($invoiceItem->item_type ?? '')));
          return in_array($itemType, ['custom', 'inventory'], true);
        })
        ->map(fn($invoiceItem) => trim((string) ($invoiceItem->item_name ?? '')))
        ->filter()
        ->unique()
        ->values();
    @endphp
    <div id="detail_invoice_items_row" class="flex items-center gap-2 {{ $detailInvoiceItemNames->isEmpty() ? 'hidden' : '' }}">
      <p class="font-medium">Invoice Items:</p>
      <p id="detail_invoice_items_text" class="text-base-content/70">{{ $detailInvoiceItemNames->join(', ') }}</p>
    </div>
    <div class="flex items-center gap-2 xl:col-span-2">
      <p class="font-medium">Assignment:</p>
      <p class="text-base-content/70">{{ $assignmentLabel ?? 'Not assigned' }}</p>
    </div>
    @php
      $appointmentCheckin = $checkedIn ?? null;
      $appointmentFleaTickFlows = $appointmentCheckin && !empty($appointmentCheckin->flows)
        ? (is_array($appointmentCheckin->flows) ? $appointmentCheckin->flows : json_decode($appointmentCheckin->flows, true))
        : [];
      $appointmentFleaTickBreakdown = isBoardingService($appointment->service)
        ? getBoardingFleaTickBreakdown($appointment, is_array($appointmentFleaTickFlows) ? $appointmentFleaTickFlows : [])
        : ['amount' => 0];
    @endphp
    @if (!empty($appointmentFleaTickBreakdown['amount']))
    <div class="flex items-center gap-2">
      <p class="font-medium">Flea/Tick Detection Fee: </p>
      <p class="text-base-content/70">${{ number_format($appointmentFleaTickBreakdown['amount'], 2) }}</p>
    </div>
    @endif
    @endif
    @if (isGroupClassService($appointment->service) && isset($appointment->class_name))
    <div class="xl:col-span-2 flex items-center gap-2">
      <p class="font-medium">Class Name: </p>
      <p class="text-base-content/70">{{ $appointment->class_name }}</p>
    </div>
    @endif
    @if (isAlaCarteService($appointment->service) && isset($appointment->secondary_service_names))
    <div class="xl:col-span-2 flex items-center gap-2">
      <p class="font-medium">Grooming Services: </p>
      <p class="text-base-content/70">{{ $appointment->secondary_service_names }}</p>
    </div>
    @endif
  </div>
  @php
    $layoutPets = $appointment->family_pets;
    if ($layoutPets->isEmpty() && $appointment->pet) {
      $layoutPets = collect([$appointment->pet]);
    }
    $isFamilyPetAppointmentLayout = $layoutPets->count() > 1;
  @endphp
  <div class="mt-3 grid grid-cols-1 gap-6 {{ $isFamilyPetAppointmentLayout ? '' : 'lg:grid-cols-12' }}">
    <div class="{{ $isFamilyPetAppointmentLayout ? '' : 'lg:col-span-5 2xl:col-span-5' }}">
      <div class="card card-border bg-base-100">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Customer Profile</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                @if($appointment->customer)
                <a href="{{ route('edit-customer', $appointment->customer->id) }}" class="block w-fit hover:opacity-80 focus:opacity-80 rounded-box" title="View customer">
                  @if (empty($appointment->customer->profile) || empty($appointment->customer->profile->avatar_img))
                  <img src="{{ asset('images/default-user-avatar.png') }}" alt="Seller Image" class="rounded-box bg-base-200 avatar-img">
                  @else
                  <img src="{{ asset('storage/profiles/'. $appointment->customer->profile->avatar_img) }}" alt="Seller Image" class="rounded-box bg-base-200 avatar-img">
                  @endif
                </a>
                @else
                <img src="{{ asset('images/default-user-avatar.png') }}" alt="Seller Image" class="rounded-box bg-base-200 avatar-img">
                @endif
                <div class="lg:col-span-2 space-y-1">
                  @if($appointment->customer)
                  <p class="font-medium">
                    <a href="{{ route('edit-customer', $appointment->customer->id) }}" class="link link-hover" title="View customer">{{ $appointment->customer->profile->first_name }} {{ $appointment->customer->profile->last_name }}</a>
                  </p>
                  @else
                  <p class="font-medium">—</p>
                  @endif
                  <p class="text-sm text-base-content/70">
                    @if ($appointment->customer->profile->gender === 'male')
                    <div class="badge badge-dash badge-primary badge-sm">{{ ucfirst($appointment->customer->profile->gender) }}</div>
                    @else
                    <div class="badge badge-dash badge-success badge-sm">{{ ucfirst($appointment->customer->profile->gender) }}</div>
                    @endif
                  </p>
                  <p class="text-sm text-base-content/70">
                    <span class="iconify lucide--mail text-base-content/70 size-3"></span>
                    {{ $appointment->customer->email }}
                  </p>
                  <p class="text-sm text-base-content/70">
                    <span class="iconify lucide--phone text-base-content/70 size-3"></span>
                    {{ $appointment->customer->profile->phone_number_1 }}
                  </p>
                  @if ($appointment->customer->profile->phone_number_2)
                  <p class="text-sm text-base-content/70">
                    <span class="iconify lucide--phone text-base-content/70 size-3"></span>
                    {{ $appointment->customer->profile->phone_number_2 }}
                  </p>
                  @endif
                  <div class="mt-3 inline-flex flex-wrap gap-2">
                    <a href="tel:{{ $appointment->customer->profile->phone_number_1 }}" class="btn btn-sm btn-outline btn-primary">
                      <span class="iconify lucide--phone size-4"></span>
                      <span class="hidden sm:inline">Call</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline btn-secondary" onclick="openCustomerEmailModal()">
                      <span class="iconify lucide--mail size-4"></span>
                      <span class="hidden sm:inline">Email</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline btn-success" onclick="openNotifyModal()">
                      <span class="iconify lucide--bell size-4"></span>
                      <span class="hidden sm:inline">Notify</span>
                    </button>
                  </div>
                </div>
              </div>
              @if (isset($appointment->customer->profile->address))
              <div class="mt-4">
                <p class="text-sm text-base-content/70">
                  <span class="iconify lucide--map-pin text-base-content/70 size-3.5"></span>
                  {{ $appointment->customer->profile->address }},
                  {{ $appointment->customer->profile->state }},
                  {{ $appointment->customer->profile->zip_code }},
                  {{ $appointment->customer->profile->city }}
                </p>
              </div>
              @endif
              @if($appointment->customer->appointmentCancellations && $appointment->customer->appointmentCancellations->count() > 0)
              <div class="mt-4">
                <span class="font-medium text-sm text-base-content/80">Cancellation/No Show History</span>
                <table class="table text-base-content/70 text-xs mt-3">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Type</th>
                      <th>Service</th>
                      <th>Date</th>
                      <th>Cancelled By</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($appointment->customer->appointmentCancellations->sortByDesc('occurred_at') as $cancellation)
                      <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                          @if($cancellation->type === 'cancel')
                            <div class="badge badge-soft badge-error badge-sm">Cancelled</div>
                          @else
                            <div class="badge badge-soft badge-warning badge-sm">No Show</div>
                          @endif
                        </td>
                        <td>{{ $cancellation->service->name ?? 'N/A' }}</td>
                        <td>{{ \Carbon\Carbon::parse($cancellation->occurred_at)->format('m/d/Y h:i A') }}</td>
                        <td>
                          @if($cancellation->cancelledBy)
                            {{ $cancellation->cancelledBy->name }}
                          @else
                            N/A
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
      @if ($appointment->status === 'checked_in')
        @if (isGroomingService($appointment->service) || isAlaCarteService($appointment->service))
        <div class="card card-border bg-base-100 mt-3">
          <div class="card-body gap-0">
            <div class="bg-base-200 rounded-box collapse collapse-arrow">
              <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
              <div class="collapse-title font-medium py-1">Initial Temperament</div>
              <div class="collapse-content bg-base-100">
                <div class="text-sm mt-4 space-y-4">
                  @php
                    $temperamentData = null;
                    if ($initialTemperament && $initialTemperament->temperament_data) {
                      $temperamentData = $initialTemperament->temperament_data;
                    } elseif ($checkedIn && $checkedIn->flows && isset($checkedIn->flows['initial_greeting'])) {
                      $temperamentData = $checkedIn->flows;
                    }
                  @endphp
                  <div>
                    <p class="font-medium mb-2">Initial Greeting:</p>
                    <div class="mb-2 space-y-1 ms-1">
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="initial_greeting" value="approachable"
                          {{ $temperamentData && isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'approachable' ? 'checked' : '' }} />
                        <span class="text-sm">Approachable<span class="text-base-content/70">(allows contact, loose body posture, will accept treats)</span></span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="initial_greeting" value="shy"
                          {{ $temperamentData && isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'shy' ? 'checked' : '' }} />
                        <span class="text-sm">Shy<span class="text-base-content/70">(cautious, tail tucked, whale eye, does not want to be petted)</span></span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="initial_greeting" value="uncomfortable"
                          {{ $temperamentData && isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'uncomfortable' ? 'checked' : '' }} />
                        <span class="text-sm">Uncomfortable<span class="text-base-content/70">(moves away, shows teeth, barks or snaps)</span></span>
                      </label>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium">Can you touch the following areas:</p>
                    <div class="mb-2 space-y-2 ms-4">
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Body</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_body"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_body']) && $temperamentData['touch_body'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_body"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_body']) && $temperamentData['touch_body'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Legs</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_legs"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_legs']) && $temperamentData['touch_legs'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_legs"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_legs']) && $temperamentData['touch_legs'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Feet</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_feet"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_feet']) && $temperamentData['touch_feet'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_feet"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_feet']) && $temperamentData['touch_feet'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Tail</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_tail"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_tail']) && $temperamentData['touch_tail'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_tail"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_tail']) && $temperamentData['touch_tail'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Face</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_face"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_face']) && $temperamentData['touch_face'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_face"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_face']) && $temperamentData['touch_face'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Nails</legend>
                        <div class="ms-4 flex items-center gap-5">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_nails"
                              value="accept" {{ $temperamentData && isset($temperamentData['touch_nails']) && $temperamentData['touch_nails'] === 'accept' ? 'checked' : '' }} />
                            <span class="text-sm">Accepts</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="touch_nails"
                              value="react" {{ $temperamentData && isset($temperamentData['touch_nails']) && $temperamentData['touch_nails'] === 'react' ? 'checked' : '' }} />
                            <span class="text-sm">Reacts</span>
                          </label>
                        </div>
                      </fieldset>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endif
        @if (isPrivateTrainingService($appointment->service))
        <div class="card card-border bg-base-100 mt-3">
          <div class="card-body gap-0">
            <div class="bg-base-200 rounded-box collapse collapse-arrow">
              <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
              <div class="collapse-title font-medium py-1">Training Location Type</div>
              <div class="collapse-content bg-base-100">
                <div class="text-sm mt-4 space-y-4">
                  <div>
                    <p class="font-medium mb-2">Location:</p>
                    <div class="mb-2 space-y-1 ms-1">
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="location" value="onsite"
                          {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location']) && $checkedIn->flows['location'] === 'onsite' ? 'checked' : '' }} />
                        <span class="text-sm">Onsite</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="location" value="offsite"
                          {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location']) && $checkedIn->flows['location'] === 'offsite' ? 'checked' : '' }} />
                        <span class="text-sm">Offsite</span>
                      </label>
                    </div>
                  </div>
                  <div class="onsite-fields" style="display: none;">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Link to additional services</legend>
                      <select class="select w-full" name="additional_services_link[]" id="additional_services_link" multiple>
                        @foreach($additionalServices ?? [] as $service)
                          @php
                            $selectedAdditionalServices = $checkedIn && $checkedIn->flows && isset($checkedIn->flows['additional_services_link']) ? (is_array($checkedIn->flows['additional_services_link']) ? $checkedIn->flows['additional_services_link'] : []) : [];
                          @endphp
                          <option value="{{ $service->id }}" {{ in_array($service->id, $selectedAdditionalServices) ? 'selected' : '' }}>{{ $service->name }}</option>
                        @endforeach
                      </select>
                    </fieldset>
                  </div>
                  <div class="offsite-fields" style="display: none;">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Location/Address*</legend>
                      <textarea id="location_address" class="textarea textarea-bordered w-full" placeholder="Enter location or address" rows="3">{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_address']) ? $checkedIn->flows['location_address'] : '' }}</textarea>
                    </fieldset>
                  </div>
                  <div>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Description/Owner Needs*</legend>
                      <textarea id="description_needs" class="textarea textarea-bordered w-full" placeholder="Enter description or owner needs" rows="4">{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['description_needs']) ? $checkedIn->flows['description_needs'] : '' }}</textarea>
                    </fieldset>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Training Focus:</p>
                    <div class="mb-2 space-y-2 ms-4">
                      <label class="flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs" name="training_focus" value="basic_obedience"
                          {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['training_focus']) && is_array($checkedIn->flows['training_focus']) && in_array('basic_obedience', $checkedIn->flows['training_focus']) ? 'checked' : '' }} />
                        <span class="text-sm">Basic obedience/management</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs" name="training_focus" value="behavior_modification"
                          {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['training_focus']) && is_array($checkedIn->flows['training_focus']) && in_array('behavior_modification', $checkedIn->flows['training_focus']) ? 'checked' : '' }} />
                        <span class="text-sm">Behavior modification/aggression</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs" name="training_focus" value="reactivity"
                          {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['training_focus']) && is_array($checkedIn->flows['training_focus']) && in_array('reactivity', $checkedIn->flows['training_focus']) ? 'checked' : '' }} />
                        <span class="text-sm">Reactivity/socialization</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endif
      @endif
      @if ($appointment->status === 'in_progress')
        @if (isGroomingService($appointment->service))
        <div class="card card-border bg-base-100 mt-3">
          <div class="card-body gap-0">
            <div class="bg-base-200 rounded-box collapse collapse-arrow">
              <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
              <div class="collapse-title font-medium py-1">{{ $appointment->service->name }} Process</div>
              <div class="collapse-content bg-base-100">
                <div class="text-sm mt-4 space-y-4">
                  <div>
                    <p class="font-medium mb-2">Nail trimming:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="nail_trimming"
                            value="accept" {{ $process && $process->flows && isset($process->flows['nail_trimming']) && $process->flows['nail_trimming'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="nail_trimming"
                            value="react" {{ $process && $process->flows && isset($process->flows['nail_trimming']) && $process->flows['nail_trimming'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Ear cleaning:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="ear_cleaning"
                            value="accept" {{ $process && $process->flows && isset($process->flows['ear_cleaning']) && $process->flows['ear_cleaning'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="ear_cleaning"
                            value="react" {{ $process && $process->flows && isset($process->flows['ear_cleaning']) && $process->flows['ear_cleaning'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Wetting the with sprayer:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="wetting_sprayer"
                            value="accept" {{ $process && $process->flows && isset($process->flows['wetting_sprayer']) && $process->flows['wetting_sprayer'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="wetting_sprayer"
                            value="react" {{ $process && $process->flows && isset($process->flows['wetting_sprayer']) && $process->flows['wetting_sprayer'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Shampooing:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="shampooing"
                            value="accept" {{ $process && $process->flows && isset($process->flows['shampooing']) && $process->flows['shampooing'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="shampooing"
                            value="react" {{ $process && $process->flows && isset($process->flows['shampooing']) && $process->flows['shampooing'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Rinsing:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="rinsing"
                            value="accept" {{ $process && $process->flows && isset($process->flows['rinsing']) && $process->flows['rinsing'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="rinsing"
                            value="react" {{ $process && $process->flows && isset($process->flows['rinsing']) && $process->flows['rinsing'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Drying:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex items-center gap-5">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="drying"
                            value="accept" {{ $process && $process->flows && isset($process->flows['drying']) && $process->flows['drying'] === 'accept' ? 'checked' : '' }} />
                          <span class="text-sm">Accepts</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="drying"
                            value="react" {{ $process && $process->flows && isset($process->flows['drying']) && $process->flows['drying'] === 'react' ? 'checked' : '' }} />
                          <span class="text-sm">Reacts</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Brushing/combing:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="brushing_body"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['brushing_body']) && ($process->flows['brushing_body'] === true || $process->flows['brushing_body'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Body</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="brushing_legs"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['brushing_legs']) && ($process->flows['brushing_legs'] === true || $process->flows['brushing_legs'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Legs</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="brushing_feet"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['brushing_feet']) && ($process->flows['brushing_feet'] === true || $process->flows['brushing_feet'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Feet</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="brushing_tail"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['brushing_tail']) && ($process->flows['brushing_tail'] === true || $process->flows['brushing_tail'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Tail</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="brushing_face"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['brushing_face']) && ($process->flows['brushing_face'] === true || $process->flows['brushing_face'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Face</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Clippers/scissors:</p>
                    <div class="mb-2 ms-4">
                      <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="clippers_body"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['clippers_body']) && ($process->flows['clippers_body'] === true || $process->flows['clippers_body'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Body</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="clippers_legs"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['clippers_legs']) && ($process->flows['clippers_legs'] === true || $process->flows['clippers_legs'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Legs</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="clippers_feet"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['clippers_feet']) && ($process->flows['clippers_feet'] === true || $process->flows['clippers_feet'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Feet</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="clippers_tail"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['clippers_tail']) && ($process->flows['clippers_tail'] === true || $process->flows['clippers_tail'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Tail</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="clippers_face"
                            {{ isset($process) && $process && isset($process->flows) && is_array($process->flows) && isset($process->flows['clippers_face']) && ($process->flows['clippers_face'] === true || $process->flows['clippers_face'] === 'true') ? 'checked' : '' }} />
                          <span class="text-sm">Face</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endif
        @if (isDaycareService($appointment->service))
        <div class="card card-border bg-base-100 mt-3">
          <div class="card-body gap-0">
            <div class="bg-base-200 rounded-box collapse collapse-arrow">
              <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
              <div class="collapse-title font-medium py-1">First Day Evaluation</div>
              <div class="collapse-content bg-base-100">
                <div class="text-sm mt-4 space-y-4">
                  <div>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Date</legend>
                      <input type="date" id="daycare_evaluation_date" name="daycare_evaluation_date" class="input input-bordered input-sm" style="max-width: 200px;"
                        value="{{ $process && $process->flows && isset($process->flows['daycare_evaluation_date']) ? $process->flows['daycare_evaluation_date'] : ($appointment->date ?? '') }}" />
                    </fieldset>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Result:</p>
                    <div class="mb-2 ms-4">
                      <div class="space-y-2">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="daycare_evaluation_result"
                            value="passed_no_concerns" {{ $process && $process->flows && isset($process->flows['daycare_evaluation_result']) && $process->flows['daycare_evaluation_result'] === 'passed_no_concerns' ? 'checked' : '' }} />
                          <span class="text-sm">Passed (no concerns)</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="daycare_evaluation_result"
                            value="passed_management_needed" {{ $process && $process->flows && isset($process->flows['daycare_evaluation_result']) && $process->flows['daycare_evaluation_result'] === 'passed_management_needed' ? 'checked' : '' }} />
                          <span class="text-sm">Passed (management needed)</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="daycare_evaluation_result"
                            value="reintroduction" {{ $process && $process->flows && isset($process->flows['daycare_evaluation_result']) && $process->flows['daycare_evaluation_result'] === 'reintroduction' ? 'checked' : '' }} />
                          <span class="text-sm">Reintroduction</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="daycare_evaluation_result"
                            value="refer_to_trainer" {{ $process && $process->flows && isset($process->flows['daycare_evaluation_result']) && $process->flows['daycare_evaluation_result'] === 'refer_to_trainer' ? 'checked' : '' }} />
                          <span class="text-sm">Refer to trainer</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div>
                    <p class="font-medium mb-2">Socialization evaluation</p>
                    <div class="mb-2 ms-4 space-y-3">
                      <div>
                        <p class="text-sm font-medium mb-1">New person:</p>
                        <div class="flex items-center gap-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="new_person_evaluation"
                              value="accepted" {{ $process && $process->flows && isset($process->flows['new_person_evaluation']) && $process->flows['new_person_evaluation'] === 'accepted' ? 'checked' : '' }} />
                            <span class="text-sm">Accepted</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="new_person_evaluation"
                              value="issue_concern" {{ $process && $process->flows && isset($process->flows['new_person_evaluation']) && $process->flows['new_person_evaluation'] === 'issue_concern' ? 'checked' : '' }} />
                            <span class="text-sm">Issue/concern</span>
                          </label>
                        </div>
                      </div>
                      <div>
                        <p class="text-sm font-medium mb-1">New dog:</p>
                        <div class="flex items-center gap-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="new_dog_evaluation"
                              value="accepted" {{ $process && $process->flows && isset($process->flows['new_dog_evaluation']) && $process->flows['new_dog_evaluation'] === 'accepted' ? 'checked' : '' }} />
                            <span class="text-sm">Accepted</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="new_dog_evaluation"
                              value="issue_concern" {{ $process && $process->flows && isset($process->flows['new_dog_evaluation']) && $process->flows['new_dog_evaluation'] === 'issue_concern' ? 'checked' : '' }} />
                            <span class="text-sm">Issue/concern</span>
                          </label>
                        </div>
                      </div>
                      <div>
                        <p class="text-sm font-medium mb-1">Small group of dogs:</p>
                        <div class="flex items-center gap-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="small_group_evaluation"
                              value="accepted" {{ $process && $process->flows && isset($process->flows['small_group_evaluation']) && $process->flows['small_group_evaluation'] === 'accepted' ? 'checked' : '' }} />
                            <span class="text-sm">Accepted</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="small_group_evaluation"
                              value="issue_concern" {{ $process && $process->flows && isset($process->flows['small_group_evaluation']) && $process->flows['small_group_evaluation'] === 'issue_concern' ? 'checked' : '' }} />
                            <span class="text-sm">Issue/concern</span>
                          </label>
                        </div>
                      </div>
                      <div>
                        <p class="text-sm font-medium mb-1">Large group of dogs:</p>
                        <div class="flex items-center gap-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="large_group_evaluation"
                              value="accepted" {{ $process && $process->flows && isset($process->flows['large_group_evaluation']) && $process->flows['large_group_evaluation'] === 'accepted' ? 'checked' : '' }} />
                            <span class="text-sm">Accepted</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="large_group_evaluation"
                              value="issue_concern" {{ $process && $process->flows && isset($process->flows['large_group_evaluation']) && $process->flows['large_group_evaluation'] === 'issue_concern' ? 'checked' : '' }} />
                            <span class="text-sm">Issue/concern</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Notes</legend>
                      <textarea class="textarea textarea-bordered w-full" placeholder="Add notes about the evaluation..." id="daycare_evaluation_notes" name="daycare_evaluation_notes" rows="3">{{ $process && $process->flows && isset($process->flows['daycare_evaluation_notes']) ? $process->flows['daycare_evaluation_notes'] : '' }}</textarea>
                    </fieldset>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        @endif
      @endif
      @if ($appointment->status === 'completed' && !isGroupClassService($appointment->service))
        @php
          $canOverrideInvoiceLock = auth()->check()
            && auth()->user()->roles()->whereRaw('LOWER(title) in (?, ?)', ['owner', 'admin'])->exists();
          $isInvoiceEditingLocked = $invoice
            && in_array(strtolower((string) ($invoice->status ?? '')), ['paid', 'finalized'], true)
            && !$canOverrideInvoiceLock;
        @endphp
        <div class="card card-border bg-base-100 mt-3">
          <div class="card-body gap-0">
            <div class="bg-base-200 rounded-box collapse collapse-arrow">
              <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
              <div class="collapse-title font-medium py-1">Invoice</div>
              <div class="collapse-content bg-base-100">
                @if($isInvoiceEditingLocked)
                  <div class="alert alert-soft alert-warning mb-3">
                    <span class="iconify lucide--lock size-4"></span>
                    <span>Invoice is finalized/paid and locked. Only owner/admin can edit.</span>
                  </div>
                @endif
                <div class="text-sm mt-3 space-y-1">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Invoice Number*</legend>
                    <input type="text" id="invoice_number" class="input input-bordered w-full input-sm" value="{{ $invoice ? $invoice->invoice_number : App\Models\Invoice::generateInvoiceNumber() }}" placeholder="Enter invoice number" />
                  </fieldset>
                  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">First Name*</legend>
                      <input type="text" id="first_name" class="input input-bordered w-full input-sm" value="{{ $invoice ? $invoice->first_name : ($appointment->customer->profile->first_name ?? '') }}" placeholder="Enter first name" />
                    </fieldset>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Last Name*</legend>
                      <input type="text" id="last_name" class="input input-bordered w-full input-sm" value="{{ $invoice ? $invoice->last_name : ($appointment->customer->profile->last_name ?? '') }}" placeholder="Enter last name" />
                    </fieldset>
                  </div>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Email*</legend>
                    <input type="text" id="email" class="input input-bordered w-full input-sm" value="{{ $invoice ? $invoice->email : ($appointment->customer->email ?? '') }}" placeholder="Enter email" />
                  </fieldset>
                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Issued At*</legend>
                      <input type="datetime-local" id="issued_at" class="input w-full input-sm" value="{{ $invoice ? ($invoice->issued_at ? \Carbon\Carbon::parse($invoice->issued_at)->format('Y-m-d\TH:i') : '') : \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}"/>
                    </fieldset>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Due Date</legend>
                      <input type="date" id="due_date" class="input w-full input-sm" value="{{ $invoice ? $invoice->due_date : '' }}"/>
                    </fieldset>
                  </div>
                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Paid At</legend>
                      <input type="datetime-local" id="paid_at" class="input w-full input-sm" value="{{ $invoice ? $invoice->paid_at : '' }}"/>
                    </fieldset>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Status</legend>
                      <select class="select w-full input-sm" id="status" value="{{ $invoice ? $invoice->status : '' }}">
                        <option value="draft" {{ $invoice && $invoice->status == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="sent" {{ $invoice && $invoice->status == 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="paid" {{ $invoice && $invoice->status == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="void" {{ $invoice && $invoice->status == 'void' ? 'selected' : '' }}>Void</option>
                      </select>
                    </fieldset>
                  </div>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Notes</legend>
                    <textarea placeholder="Type here" id="invoice_notes" class="textarea w-full">{{ $invoice ? $invoice->notes : '' }}</textarea>
                  </fieldset>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Inventory Items</legend>
                    <div class="flex items-center gap-2">
                      <select class="select w-full select-sm" name="inventory_item" id="inventory_item">
                      </select>
                      <button type="button" class="btn btn-sm btn-outline btn-primary" onclick="addInventoryItem()" {{ $isInvoiceEditingLocked ? 'disabled' : '' }}>
                        <span class="iconify lucide--plus size-3"></span>
                        <span class="hidden sm:inline">Add</span>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline" id="add_line_item_btn" onclick="addCustomLineItem()" {{ $isInvoiceEditingLocked ? 'disabled' : '' }}>
                        <span class="iconify lucide--plus size-3"></span>
                        <span class="hidden sm:inline">Add Line Item</span>
                      </button>
                    </div>
                  </fieldset>
                </div>
                <hr class="mt-4" style="color: lightgray"/>
                <div class="mt-4 text-sm">
                  <h4 class="font-medium mb-2">Items & Pricing</h4>
                  @if (($chauffeurPricingData['has_chauffeur'] ?? false) && !($chauffeurPricingData['is_route_valid'] ?? false))
                    <div class="alert alert-soft alert-warning mb-3">
                      <span class="iconify lucide--triangle-alert size-4"></span>
                      <span>{{ $chauffeurPricingData['error'] ?? 'Chauffeur distance cannot be calculated due to invalid address/route.' }}</span>
                    </div>
                  @endif
                  <table class="table table-sm w-full text-xs">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody id="pricing_table">
                      @php
                        $isGroupClasses = isGroupClassService($appointment->service);
                        $isAlaCarte = isAlaCarteService($appointment->service);
                        $groupClassIds = [];
                        if ($isGroupClasses && $appointment->metadata && isset($appointment->metadata['group_class_ids'])) {
                          $groupClassIds = explode(',', $appointment->metadata['group_class_ids']);
                        }
                        $secondaryServiceIds = [];
                        if ($isAlaCarte && $appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
                          $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
                        }
                        $chauffeurServicePrices = $chauffeurPricingData['service_prices'] ?? [];
                        $chauffeurDistanceMiles = $chauffeurPricingData['distance_miles'] ?? null;
                        $row = 1;
                      @endphp
                      @if($isGroupClasses && !empty($groupClassIds))
                        @php
                          $groupClasses = \App\Models\GroupClass::whereIn('id', $groupClassIds)->get();
                        @endphp
                        @foreach($groupClasses as $groupClass)
                          <tr class="service-row">
                            <td>{{ $row++ }}</td>
                            <td width="56%">{{ $groupClass->name }}</td>
                            <td>${{ number_format($groupClass->price, 2) }}</td>
                            <td></td>
                          </tr>
                        @endforeach
                      @elseif($isAlaCarte && !empty($secondaryServiceIds))
                        @php
                          $secondaryServices = \App\Models\Service::whereIn('id', $secondaryServiceIds)->get();
                          $petSize = $appointment->pet->size ?? 'medium';
                        @endphp
                        @foreach($secondaryServices as $secondaryService)
                          @php
                            $isChauffeurSecondary = array_key_exists($secondaryService->id, $chauffeurServicePrices);
                            $secondaryPrice = $isChauffeurSecondary
                              ? floatval($chauffeurServicePrices[$secondaryService->id])
                              : getServicePrice($secondaryService, $petSize);
                            $secondaryPricePerMile = floatval($secondaryService->price_per_mile ?? 0);
                          @endphp
                          <tr class="service-row">
                            <td>{{ $row++ }}</td>
                            <td width="56%">
                              <div>{{ $secondaryService->name }}</div>
                              @if($isChauffeurSecondary && $chauffeurDistanceMiles !== null)
                                <div class="text-[10px] text-base-content/60">
                                  {{ number_format($chauffeurDistanceMiles, 2) }} mi x ${{ number_format($secondaryPricePerMile, 2) }}/mi
                                </div>
                              @endif
                            </td>
                            <td>${{ number_format($secondaryPrice, 2) }}</td>
                            <td></td>
                          </tr>
                        @endforeach

                        @if($appointment->additional_service_ids)
                          @php
                            $secondaryServiceIdSet = collect($secondaryServiceIds)->map(fn($id) => (string) trim($id))->filter()->values();
                            $additionalIds = collect(explode(',', $appointment->additional_service_ids))
                              ->map(fn($id) => (string) trim($id))
                              ->filter()
                              ->reject(fn($id) => $secondaryServiceIdSet->contains($id))
                              ->values();

                            $alaCarteAdditionalServices = $additionalIds->isNotEmpty()
                              ? \App\Models\Service::whereIn('id', $additionalIds->all())->get()
                              : collect();
                          @endphp

                          @foreach($alaCarteAdditionalServices as $additionalService)
                            @php
                              $isChauffeurAdditional = array_key_exists($additionalService->id, $chauffeurServicePrices);
                              $additionalPrice = $isChauffeurAdditional
                                ? floatval($chauffeurServicePrices[$additionalService->id])
                                : getServicePrice($additionalService, $petSize);
                              $additionalPricePerMile = floatval($additionalService->price_per_mile ?? 0);
                            @endphp
                            <tr class="service-row">
                              <td>{{ $row++ }}</td>
                              <td width="56%">
                                <div>{{ $additionalService->name }}</div>
                                @if($isChauffeurAdditional && $chauffeurDistanceMiles !== null)
                                  <div class="text-[10px] text-base-content/60">
                                    {{ number_format($chauffeurDistanceMiles, 2) }} mi x ${{ number_format($additionalPricePerMile, 2) }}/mi
                                  </div>
                                @endif
                              </td>
                              <td>${{ number_format($additionalPrice, 2) }}</td>
                              <td></td>
                            </tr>
                          @endforeach
                        @endif
                      @else
                        @php
                          $familyPricingPets = $appointment->family_pets;
                          if ($familyPricingPets->isEmpty() && $appointment->pet) {
                            $familyPricingPets = collect([$appointment->pet]);
                          }
                          $isBoardingForPricing = isBoardingService($appointment->service);
                          $petCountForPricing = max(1, $familyPricingPets->count());

                          $servicePrice = getServicePrice($appointment->service, $appointment->pet->size, $appointment->metadata);
                          if ($isBoardingForPricing) {
                            $boardingPrice = getBoardingServicePrice($appointment->service, $appointment);
                            if ($boardingPrice !== null) {
                              $servicePrice = $boardingPrice;
                            }
                          }
                          if ($isBoardingForPricing && $petCountForPricing > 1) {
                            $servicePrice = $servicePrice * $petCountForPricing;
                          }
                        @endphp
                        @php
                          $isChauffeurMain = array_key_exists($appointment->service->id, $chauffeurServicePrices);
                          if ($isChauffeurMain) {
                            $servicePrice = floatval($chauffeurServicePrices[$appointment->service->id]);
                          }
                          $mainPricePerMile = floatval($appointment->service->price_per_mile ?? 0);
                        @endphp
                      @endif
                      @if(!($invoice && $invoice->items && $invoice->items->count() > 0))
                        <tr class="service-row">
                          <td>{{ $row++ }}</td>
                          <td width="56%">
                            <div>{{ $appointment->service->name }}</div>
                            @if($isBoardingForPricing && $petCountForPricing > 1 && !$isChauffeurMain)
                              <div class="text-[10px] text-base-content/60">{{ $petCountForPricing }} pets</div>
                            @endif
                            @if($isChauffeurMain && $chauffeurDistanceMiles !== null)
                              <div class="text-[10px] text-base-content/60">
                                {{ number_format($chauffeurDistanceMiles, 2) }} mi x ${{ number_format($mainPricePerMile, 2) }}/mi
                              </div>
                            @endif
                          </td>
                          <td>${{ number_format($servicePrice, 2) }}</td>
                          <td></td>
                        </tr>
                        @php
                          $additionalServicesByPet = $appointment->additional_services_by_pet ?? [];
                          $flatAdditionalIds = collect($additionalServicesByPet)
                            ->flatten()
                            ->map(fn($id) => (int) $id)
                            ->filter(fn($id) => $id > 0)
                            ->unique()
                            ->values();
                          $additionalServiceMap = $flatAdditionalIds->isNotEmpty()
                            ? \App\Models\Service::whereIn('id', $flatAdditionalIds->all())->get()->keyBy('id')
                            : collect();
                        @endphp

                        @if($flatAdditionalIds->isNotEmpty())
                          @if($isBoardingForPricing)
                            @foreach($familyPricingPets as $pricingPet)
                              @php
                                $petServiceIds = collect($additionalServicesByPet[$pricingPet->id] ?? [])
                                  ->map(fn($id) => (int) $id)
                                  ->filter(fn($id) => $id > 0)
                                  ->unique()
                                  ->values();
                              @endphp
                              @foreach($petServiceIds as $petServiceId)
                                @php
                                  $additionalService = $additionalServiceMap->get($petServiceId);
                                  if (!$additionalService) {
                                    continue;
                                  }

                                  $isChauffeurAdditional = array_key_exists($additionalService->id, $chauffeurServicePrices);
                                  $additionalPrice = $isChauffeurAdditional
                                    ? floatval($chauffeurServicePrices[$additionalService->id])
                                    : getServicePrice($additionalService, $pricingPet->size ?? ($appointment->pet->size ?? 'medium'));
                                  $additionalPricePerMile = floatval($additionalService->price_per_mile ?? 0);
                                @endphp
                                <tr class="service-row">
                                  <td>{{ $row++ }}</td>
                                  <td width="56%">
                                    <div>{{ $additionalService->name }} - {{ $pricingPet->name ?? 'Pet' }}</div>
                                    @if($isChauffeurAdditional && $chauffeurDistanceMiles !== null)
                                      <div class="text-[10px] text-base-content/60">
                                        {{ number_format($chauffeurDistanceMiles, 2) }} mi x ${{ number_format($additionalPricePerMile, 2) }}/mi
                                      </div>
                                    @endif
                                  </td>
                                  <td>${{ number_format($additionalPrice, 2) }}</td>
                                  <td></td>
                                </tr>
                              @endforeach
                            @endforeach
                          @else
                            @foreach($flatAdditionalIds as $petServiceId)
                              @php
                                $additionalService = $additionalServiceMap->get($petServiceId);
                                if (!$additionalService) {
                                  continue;
                                }

                                $isChauffeurAdditional = array_key_exists($additionalService->id, $chauffeurServicePrices);
                                $additionalPrice = $isChauffeurAdditional
                                  ? floatval($chauffeurServicePrices[$additionalService->id])
                                  : getServicePrice($additionalService, $appointment->pet->size);
                                $additionalPricePerMile = floatval($additionalService->price_per_mile ?? 0);
                              @endphp
                              <tr class="service-row">
                                <td>{{ $row++ }}</td>
                                <td width="56%">
                                  <div>{{ $additionalService->name }}</div>
                                  @if($isChauffeurAdditional && $chauffeurDistanceMiles !== null)
                                    <div class="text-[10px] text-base-content/60">
                                      {{ number_format($chauffeurDistanceMiles, 2) }} mi x ${{ number_format($additionalPricePerMile, 2) }}/mi
                                    </div>
                                  @endif
                                </td>
                                <td>${{ number_format($additionalPrice, 2) }}</td>
                                <td></td>
                              </tr>
                            @endforeach
                          @endif
                        @endif
                      @endif
                      @if($invoice && $invoice->items)
                        @php
                          $displayInvoiceItems = collect(dedupeBoardingAutoFeeInvoiceItems($invoice->items))->values();
                        @endphp
                        @foreach($displayInvoiceItems as $invoiceItem)
                          @php
                            $invoiceItemType = strtolower(trim((string) ($invoiceItem->item_type ?? 'service')));
                          @endphp
                          @if($invoiceItemType === 'inventory')
                          <tr class="inventory-row" data-item-id="{{ $invoiceItem->id }}" data-item-type="inventory">
                            <td>{{ $row++ }}</td>
                            <td width="56%">{{ $invoiceItem->item_name }}</td>
                            <td>${{ number_format($invoiceItem->price, 2) }}</td>
                            <td>
                              @if(!$isInvoiceEditingLocked)
                              <button type="button" class="btn btn-sm btn-ghost btn-circle" style="height: 16px" onclick="removeExistingInvoiceItem({{ $invoiceItem->id }})">
                                <span class="iconify lucide--trash-2 size-3 text-error"></span>
                              </button>
                              @endif
                            </td>
                          </tr>
                          @elseif($invoiceItemType === 'custom')
                          <tr class="service-row custom-line-row" data-item-id="{{ $invoiceItem->id }}" data-item-type="custom">
                            <td>{{ $row++ }}</td>
                            <td width="56%">{{ $invoiceItem->item_name }}</td>
                            <td>${{ number_format($invoiceItem->price, 2) }}</td>
                            <td>
                              @if(!$isInvoiceEditingLocked)
                              <button type="button" class="btn btn-sm btn-ghost btn-circle" style="height: 16px" onclick="removeExistingInvoiceItem({{ $invoiceItem->id }})">
                                <span class="iconify lucide--trash-2 size-3 text-error"></span>
                              </button>
                              @endif
                            </td>
                          </tr>
                          @else
                          <tr class="service-row" data-item-id="{{ $invoiceItem->id }}" data-item-type="{{ $invoiceItemType ?: 'service' }}">
                            <td>{{ $row++ }}</td>
                            <td width="56%">{{ $invoiceItem->item_name }}</td>
                            <td>${{ number_format($invoiceItem->price, 2) }}</td>
                            <td>
                              @if(!$isInvoiceEditingLocked)
                              <button type="button" class="btn btn-sm btn-ghost btn-circle" style="height: 16px" onclick="removeExistingInvoiceItem({{ $invoiceItem->id }})">
                                <span class="iconify lucide--trash-2 size-3 text-error"></span>
                              </button>
                              @endif
                            </td>
                          </tr>
                          @endif
                        @endforeach
                      @endif
                      @if($appointment->coat_extra_fee)
                      <tr class="coat-fee-row">
                        <td>{{ $row++ }}</td>
                        <td width="56%">Coat Extra Fee</td>
                        <td>${{ number_format($appointment->coat_extra_fee, 2) }}</td>
                        <td></td>
                      </tr>
                      @endif
                      @php
                        $checkedInFlowsForPricing = [];
                        if ($checkedIn && !empty($checkedIn->flows)) {
                          if (is_array($checkedIn->flows)) {
                            $checkedInFlowsForPricing = $checkedIn->flows;
                          } else {
                            $decodedCheckedInFlowsForPricing = json_decode($checkedIn->flows, true);
                            $checkedInFlowsForPricing = is_array($decodedCheckedInFlowsForPricing) ? $decodedCheckedInFlowsForPricing : [];
                          }
                        }

                        $boardingFleaTickBreakdown = isBoardingService($appointment->service)
                          ? getBoardingFleaTickBreakdown($appointment, $checkedInFlowsForPricing)
                          : ['checked_pet_count' => 0, 'amount' => 0];

                        $persistedLateCheckoutInvoiceFee = 0;
                        if ($invoice && $invoice->items) {
                          foreach ($invoice->items as $invoiceItem) {
                            if (($invoiceItem->item_type ?? '') === 'service' && trim((string) ($invoiceItem->item_name ?? '')) === 'Late Checkout Daycare Fee') {
                              $persistedLateCheckoutInvoiceFee = max($persistedLateCheckoutInvoiceFee, floatval($invoiceItem->price ?? 0));
                            }
                          }
                        }

                        $invoiceLateCheckoutBreakdown = isBoardingService($appointment->service)
                          ? getBoardingLateCheckoutDaycareBreakdown($appointment, $checkout, 1)
                          : ['fee' => 0];
                        $invoiceLateCheckoutDaycareFee = max(
                          floatval($invoiceLateCheckoutBreakdown['fee'] ?? 0),
                          floatval($persistedLateCheckoutInvoiceFee ?? 0),
                          floatval($lateCheckoutDaycareFeeDisplay ?? 0),
                          floatval($headerLateCheckoutFormulaFee ?? 0),
                          floatval($headerLateCheckoutDaycareFee ?? 0)
                        );
                      @endphp
                      <tr id="flea_tick_fee_row" class="service-row flea-tick-row" data-initial-fee="{{ number_format(($boardingFleaTickBreakdown['amount'] ?? 0), 2, '.', '') }}" style="{{ $invoice || ($boardingFleaTickBreakdown['amount'] ?? 0) <= 0 ? 'display:none;' : '' }}">
                        <td>{{ $row++ }}</td>
                        <td width="56%">Flea/Tick Detection Fee</td>
                        <td>${{ number_format($boardingFleaTickBreakdown['amount'] ?? 0, 2) }}</td>
                        <td></td>
                      </tr>
                      <tr id="late_checkout_daycare_fee_row" class="service-row late-checkout-daycare-row" data-initial-fee="{{ number_format($persistedLateCheckoutInvoiceFee > 0 ? 0 : $invoiceLateCheckoutDaycareFee, 2, '.', '') }}" style="{{ $invoice || $persistedLateCheckoutInvoiceFee > 0 || $invoiceLateCheckoutDaycareFee <= 0 ? 'display:none;' : '' }}">
                        <td>{{ $row++ }}</td>
                        <td width="56%">Late Checkout Daycare Fee</td>
                        <td>${{ number_format($persistedLateCheckoutInvoiceFee > 0 ? $persistedLateCheckoutInvoiceFee : $invoiceLateCheckoutDaycareFee, 2) }}</td>
                        <td></td>
                      </tr>
                    </tbody>
                  </table>
                  <hr class="mt-2" style="color: lightgray"/>
                  <table class="table table-sm w-full text-xs">
                    <tbody>
                      <tr>
                        <td colspan="2" class="font-medium text-end" width="66%">Total Price of Services:</td>
                        <td id="total_price_of_services"></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td colspan="2" class="font-medium text-end" width="66%">Estimated Price of Services:</td>
                        <td id="estimated_price_of_services">${{ number_format($appointment->estimated_price, 2) }}</td>
                        <td></td>
                      </tr>
                      @if ($invoice && $invoice->discount_amount)
                      @php
                        $discountTooltipTitle = $invoice->discount_title ?? '';
                        $discountCustomerName = trim((($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? ''))) ?: ($appointment->customer->name ?? 'customer');
                        $discountTooltipText = 'The discount "' . $discountTooltipTitle . '" is applied for ' . $discountCustomerName . '.';
                      @endphp
                      <tr id="invoice_discount_row">
                          <td id="invoice_discount_label" colspan="2" class="font-medium text-end" width="66%">{{ $invoice->discount_title ?: 'Discount' }}:</td>
                          <td id="invoice_discount_amount" width="10%">-${{ number_format($invoice->discount_amount, 2) }}</td>
                          <td style="padding-left: 0">
                              <span class="flex tooltip tooltip-info tooltip-left cursor-pointer js-click-tooltip js-invoice-discount-tooltip"
                                    data-tip="{{ $discountTooltipText }}">
                                  <span class="iconify lucide--info text-info size-5"></span>
                              </span>
                          </td>
                      </tr>
                      @elseif(!empty($invoiceDiscountRules))
                        <tr id="invoice_discount_row" style="display: none;">
                          <td id="invoice_discount_label" colspan="2" class="font-medium text-end" width="66%">Discount:</td>
                          <td id="invoice_discount_amount" width="10%"></td>
                          <td style="padding-left: 0">
                              <span class="flex tooltip tooltip-info tooltip-left cursor-pointer js-click-tooltip js-invoice-discount-tooltip"
                                    data-tip="">
                                  <span class="iconify lucide--help-circle text-info size-5"></span>
                              </span>
                          </td>
                      </tr>
                      @endif
                      <tr>
                        <td colspan="2" class="font-medium text-end" width="66%">Total Inventory Amount:</td>
                        <td id="inventory_total_amount">$0.00</td>
                        <td></td>
                      </tr>
                      <tr>
                        <td colspan="2" class="font-medium text-end" width="66%">Subtotal:</td>
                        <td id="invoice_subtotal_amount">$0.00</td>
                        <td></td>
                      </tr>
                      <tr id="invoice_state_tax_row" style="display: none;">
                        <td colspan="2" class="font-medium text-end" width="66%">State Tax (<span id="invoice_state_tax_rate_label">0</span>%):</td>
                        <td id="invoice_state_tax_amount">$0.00</td>
                        <td></td>
                      </tr>
                      <tr>
                        <td colspan="2" class="font-medium text-end" width="66%">Total Amount:</td>
                        <td id="grand_total_amount">$0.00</td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div class="mt-3">
                  @if(hasPermission(14, 'can_create') && !$isInvoiceEditingLocked)
                  <button type="button" id="save_invoice_btn" class="btn btn-primary btn-soft btn-sm" onclick="saveInvoice({{ $appointment->id }})">
                    <span class="loading loading-spinner loading-sm" style="display: none;"></span>
                    Save Invoice
                  </button>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </div>
      @endif
    </div>
    <div class="{{ $isFamilyPetAppointmentLayout ? '' : 'lg:col-span-7 2xl:col-span-7' }}">
      <div class="card card-border bg-base-100">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Pet Profile</div>
            <div class="collapse-content bg-base-100">
              @php
                $profilePets = $appointment->family_pets;
                if ($profilePets->isEmpty() && $appointment->pet) {
                  $profilePets = collect([$appointment->pet]);
                }
                $isFamilyProfile = $profilePets->count() > 1;
              @endphp

              <div class="{{ $isFamilyProfile ? 'mt-4 grid grid-cols-1 xl:grid-cols-2 gap-4' : '' }}">
              @forelse($profilePets as $pet)
              <div class="{{ $isFamilyProfile ? 'rounded-box border border-base-300 p-4 h-full' : 'mt-4' }} {{ !$isFamilyProfile && !$loop->first ? 'border-t border-base-300' : '' }}">
                <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-4">
                  <div class="lg:col-span-1">
                    <a href="{{ route('edit-pet', $pet->id) }}" class="block w-fit hover:opacity-80 focus:opacity-80 rounded-box" title="View pet">
                      @if (empty($pet->pet_img))
                      <img src="{{ asset('images/no_image.jpg') }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                      @else
                      <img src="{{ asset('storage/pets/'. $pet->pet_img) }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                      @endif
                    </a>
                  </div>
                  <div class="lg:col-span-3 space-y-1">
                    <p class="font-medium"><a href="{{ route('edit-pet', $pet->id) }}" class="link link-hover" title="View pet">{{ $pet->name }}</a></p>
                    <div class="flex items-center gap-4">
                      <div class="text-sm text-base-content/70">
                        <span class="iconify lucide--cake text-base-content/70 size-3"></span>
                        {{ $pet->birthdate ? \Carbon\Carbon::parse($pet->birthdate)->format('m/d/Y') . ' (' . $pet->age . ' years old)' : '' }}
                      </div>
                      @if (($pet->sex ?? '') === 'male')
                      <div class="badge badge-dash badge-primary badge-sm">{{ ucfirst($pet->sex) }}</div>
                      @else
                      <div class="badge badge-dash badge-success badge-sm">{{ ucfirst($pet->sex ?? 'unknown') }}</div>
                      @endif
                    </div>
                    <div class="grid grid-cols-2 pt-2 gap-1">
                      <p class="text-sm text-base-content/70">
                        <span class="font-medium text-base-content/80">Breed:</span>
                        {{ optional($pet->breed)->name ?? 'N/A' }}
                      </p>
                      <p class="text-sm text-base-content/70">
                        <span class="font-medium text-base-content/80">Weight:</span>
                        {{ $pet->weight ?? 'N/A' }}{{ isset($pet->weight) ? 'lbs' : '' }}
                        <span class="font-medium text-base-content/80 ps-5">Size:</span>
                        {{ $pet->size ?? 'N/A' }}
                      </p>
                      <p class="text-sm text-base-content/70">
                        <span class="font-medium text-base-content/80">Color:</span>
                        {{ optional($pet->color)->name ?? 'N/A' }}
                      </p>
                      <p class="text-sm text-base-content/70">
                        <span class="font-medium text-base-content/80">Coat Type:</span>
                        {{ optional($pet->coatType)->name ?? 'N/A' }}
                      </p>
                    </div>
                    <div class="mt-3">
                      <span class="font-medium text-sm text-base-content/80">Veterinarian</span>
                      <div class="grid grid-cols-2 gap-1">
                        <p class="text-sm text-base-content/70 flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-stethoscope-icon lucide-stethoscope"><path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>
                          {{ $pet->veterinarian_name ?? 'N/A' }}
                        </p>
                        <p class="text-sm text-base-content/70 flex items-center gap-1">
                          <span class="iconify lucide--phone text-base-content/70 size-3"></span>
                          {{ $pet->veterinarian_phone ?? 'N/A' }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-4">
                  <span class="font-medium text-sm text-base-content/80">Note</span>
                  <p class="text-sm text-base-content/70">{{ $pet->notes ?: 'N/A' }}</p>
                </div>

                <div class="mt-4">
                  <div class="inline-flex flex-wrap gap-2">
                    <span class="font-medium text-base-content/80 text-sm">Vaccination Status:</span>
                    @if (($pet->vaccine_status ?? '') === 'missing')
                    <div class="badge badge-soft badge-error badge-sm">{{ ucfirst($pet->vaccine_status) }}</div>
                    @elseif (($pet->vaccine_status ?? '') === 'submitted')
                    <div class="badge badge-soft badge-secondary badge-sm">{{ ucfirst($pet->vaccine_status) }}</div>
                    @elseif (($pet->vaccine_status ?? '') === 'approved')
                    <div class="badge badge-soft badge-success badge-sm">{{ ucfirst($pet->vaccine_status) }}</div>
                    @elseif (($pet->vaccine_status ?? '') === 'expired')
                    <div class="badge badge-soft badge-neutral badge-sm">{{ ucfirst($pet->vaccine_status) }}</div>
                    @else
                    <div class="badge badge-soft badge-warning badge-sm">{{ ucfirst($pet->vaccine_status ?? 'unknown') }}</div>
                    @endif
                  </div>
                  <table class="table text-base-content/70 text-xs mt-3">
                    <tbody>
                      @forelse (collect($pet->vaccinations ?? []) as $vaccination)
                        <tr>
                          <td>{{ $loop->iteration }}</td>
                          <td>{{ ucfirst($vaccination->type) }}</td>
                          <td>{{ \Carbon\Carbon::parse($vaccination->date)->format('m/d/Y') }}</td>
                          <td>{{ \Carbon\Carbon::parse($vaccination->date)->addMonths($vaccination->months)->format('m/d/Y') }}</td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="4" class="text-base-content/60">No vaccinations found.</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>

                <div class="mt-4">
                  <span class="font-medium text-sm text-base-content/80">Certificates</span>
                  <div class="mt-1 flex flex-wrap gap-2">
                    @forelse (collect($pet->certificates ?? []) as $certificate)
                    <div class="flex items-center text-base-content/70 text-sm gap-1 ps-3">
                      <span class="iconify lucide--file-text size-3.5"></span>
                      <span class="hidden sm:inline">{{ $certificate->file_name }}</span>
                      <a href="{{ asset('storage/pets/' . $certificate->file_path) }}" target="_blank" class="btn btn-sm btn-link">
                        <span class="iconify lucide--external-link size-4 text-info"></span>
                      </a>
                    </div>
                    @empty
                    <p class="text-sm text-base-content/60">No certificates found.</p>
                    @endforelse
                  </div>
                </div>
              </div>
              @empty
              <div class="mt-4">
                <p class="text-base-content/60">No pet profile found.</p>
              </div>
              @endforelse
              </div>
            </div>
          </div>
        </div>
      </div>
      @if (isBoardingService($appointment->service) && $appointment->status === 'checked_in')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Check-in Info</div>
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                <!-- Trip Information -->
                <div>
                  <p class="font-semibold mb-2 text-base">Trip Information</p>
                  <div class="space-y-3 ms-2">
                    <div>
                      <p class="font-medium mb-1">Confirm pickup date and time:</p>
                      <input type="datetime-local" id="boarding_pickup_datetime" class="input input-bordered w-full input-sm"
                        value="{{ \Carbon\Carbon::parse($appointment->end_date . ' ' . $appointment->end_time)->format('Y-m-d\TH:i') }}" />
                    </div>
                    <div>
                      <p class="font-medium mb-1">Trip location:</p>
                      <input type="text" id="boarding_trip_location" class="input input-bordered w-full input-sm"
                        placeholder="Enter trip location"
                        value="{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['trip_location']) ? $checkedIn->flows['trip_location'] : '' }}" />
                    </div>
                    <div>
                      <p class="font-medium mb-1">Trip phone number (if different from current on file):</p>
                      <input type="tel" id="boarding_trip_phone" class="input input-bordered w-full input-sm"
                        placeholder="Enter phone number"
                        value="{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['trip_phone']) ? $checkedIn->flows['trip_phone'] : '' }}"
                        oninput="formatPhoneNumber(this)" />
                    </div>
                    <div>
                      <p class="font-medium mb-1">Alternate contact (name and phone):</p>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input type="text" id="boarding_alternate_contact_name" class="input input-bordered w-full input-sm"
                          placeholder="Name"
                          value="{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['alternate_contact_name']) ? $checkedIn->flows['alternate_contact_name'] : '' }}" />
                        <input type="tel" id="boarding_alternate_contact_phone" class="input input-bordered w-full input-sm"
                          placeholder="Phone"
                          value="{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['alternate_contact_phone']) ? $checkedIn->flows['alternate_contact_phone'] : '' }}"
                          oninput="formatPhoneNumber(this)"/>
                      </div>
                    </div>
                    <div>
                      <p class="font-medium mb-1">Notes (authorized pickup & payment arrangement):</p>
                      <textarea id="boarding_trip_notes" class="textarea textarea-bordered w-full" rows="3"
                        placeholder="Enter notes...">{{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['trip_notes']) ? $checkedIn->flows['trip_notes'] : '' }}</textarea>
                    </div>
                  </div>
                </div>

                @php
                  $flows = ($checkedIn && $checkedIn->flows) ? $checkedIn->flows : [];
                  $checkinPets = $appointment->family_pets;
                  if ($checkinPets->isEmpty() && $appointment->pet) {
                    $checkinPets = collect([$appointment->pet]);
                  }
                  $isFamilyCheckin = $checkinPets->count() > 1;
                  $petSpecificFlows = isset($flows['pet_specific']) && is_array($flows['pet_specific']) ? $flows['pet_specific'] : [];
                @endphp

                <div class="{{ $isFamilyCheckin ? 'grid grid-cols-1 xl:grid-cols-2 gap-4' : '' }}">
                @foreach($checkinPets as $pet)
                  @php
                    $petIdKey = (string) $pet->id;
                    $petFlow = $petSpecificFlows[$petIdKey] ?? ($petSpecificFlows[$pet->id] ?? []);
                    $petFlow = is_array($petFlow) ? $petFlow : [];
                    $fallbackPetFlow = $flows;
                    $effectivePetFlow = array_merge($fallbackPetFlow, $petFlow);
                    $legacyFleaTickValue = $effectivePetFlow['flea_tick'] ?? null;
                    $legacyFleaTickTruthy = in_array($legacyFleaTickValue, [true, 'true', 1, '1'], true);
                    $petFleaTickPrevention = $effectivePetFlow['flea_tick_prevention'] ?? ($legacyFleaTickValue !== null ? ($legacyFleaTickTruthy ? 'yes' : 'no') : '');
                    $petFleaTickPreventionType = $effectivePetFlow['flea_tick_prevention_type'] ?? '';

                    $petOtherItemsDescription = $effectivePetFlow['other_items_description'] ?? '';
                    $petCareNotes = $effectivePetFlow['care_notes'] ?? '';

                    $dryFoodRows = [];
                    if (isset($effectivePetFlow['dry_food_list']) && is_array($effectivePetFlow['dry_food_list']) && count($effectivePetFlow['dry_food_list']) > 0) {
                      $dryFoodRows = $effectivePetFlow['dry_food_list'];
                    } else {
                      $dryFoodRows[] = [
                        'brand' => $effectivePetFlow['dry_food']['brand'] ?? '',
                        'amount' => $effectivePetFlow['dry_food']['amount'] ?? '',
                        'dispense_am' => !empty($effectivePetFlow['dry_food']['dispense_am']) && ($effectivePetFlow['dry_food']['dispense_am'] === true || $effectivePetFlow['dry_food']['dispense_am'] === 'true'),
                        'dispense_pm' => !empty($effectivePetFlow['dry_food']['dispense_pm']) && ($effectivePetFlow['dry_food']['dispense_pm'] === true || $effectivePetFlow['dry_food']['dispense_pm'] === 'true'),
                        'dispense_lunch' => !empty($effectivePetFlow['dry_food']['dispense_lunch']) && ($effectivePetFlow['dry_food']['dispense_lunch'] === true || $effectivePetFlow['dry_food']['dispense_lunch'] === 'true'),
                      ];
                    }

                    $wetFoodRows = [];
                    if (isset($effectivePetFlow['wet_food_list']) && is_array($effectivePetFlow['wet_food_list']) && count($effectivePetFlow['wet_food_list']) > 0) {
                      $wetFoodRows = $effectivePetFlow['wet_food_list'];
                    } else {
                      $wetFoodRows[] = [
                        'brand' => $effectivePetFlow['wet_food']['brand'] ?? '',
                        'amount' => $effectivePetFlow['wet_food']['amount'] ?? '',
                        'dispense_am' => !empty($effectivePetFlow['wet_food']['dispense_am']) && ($effectivePetFlow['wet_food']['dispense_am'] === true || $effectivePetFlow['wet_food']['dispense_am'] === 'true'),
                        'dispense_pm' => !empty($effectivePetFlow['wet_food']['dispense_pm']) && ($effectivePetFlow['wet_food']['dispense_pm'] === true || $effectivePetFlow['wet_food']['dispense_pm'] === 'true'),
                        'dispense_lunch' => !empty($effectivePetFlow['wet_food']['dispense_lunch']) && ($effectivePetFlow['wet_food']['dispense_lunch'] === true || $effectivePetFlow['wet_food']['dispense_lunch'] === 'true'),
                      ];
                    }

                    $medicationRows = [];
                    if (isset($effectivePetFlow['meds_list']) && is_array($effectivePetFlow['meds_list']) && count($effectivePetFlow['meds_list']) > 0) {
                      $medicationRows = $effectivePetFlow['meds_list'];
                    } elseif (isset($effectivePetFlow['meds']) && is_array($effectivePetFlow['meds'])) {
                      $medicationRows[] = [
                        'name' => $effectivePetFlow['meds']['name'] ?? '',
                        'amount' => $effectivePetFlow['meds']['amount'] ?? '',
                        'dispense_am' => !empty($effectivePetFlow['meds']['dispense_am']) && ($effectivePetFlow['meds']['dispense_am'] === true || $effectivePetFlow['meds']['dispense_am'] === 'true'),
                        'dispense_pm' => !empty($effectivePetFlow['meds']['dispense_pm']) && ($effectivePetFlow['meds']['dispense_pm'] === true || $effectivePetFlow['meds']['dispense_pm'] === 'true'),
                        'dispense_rest' => !empty($effectivePetFlow['meds']['dispense_rest']) && ($effectivePetFlow['meds']['dispense_rest'] === true || $effectivePetFlow['meds']['dispense_rest'] === 'true'),
                        'dispense_before_bed' => false,
                        'dispense_prn' => !empty($effectivePetFlow['meds']['dispense_prn']) && ($effectivePetFlow['meds']['dispense_prn'] === true || $effectivePetFlow['meds']['dispense_prn'] === 'true'),
                        'dispense_custom_time' => false,
                        'meal_condition' => null,
                        'custom_time' => '',
                      ];
                    }

                    if (count($medicationRows) === 0) {
                      $medicationRows[] = [
                        'name' => '',
                        'amount' => '',
                        'dispense_am' => false,
                        'dispense_pm' => false,
                        'dispense_rest' => false,
                        'dispense_before_bed' => false,
                        'dispense_prn' => false,
                        'dispense_custom_time' => false,
                        'meal_condition' => null,
                        'custom_time' => '',
                      ];
                    }
                  @endphp

                  <div class="boarding-pet-section {{ $isFamilyCheckin ? 'rounded-box border border-base-300 bg-base-100 p-4 h-full' : '' }}" data-pet-id="{{ $pet->id }}" data-pet-name="{{ $pet->name }}">
                    @if($isFamilyCheckin)
                      <div class="mb-4 flex items-center gap-3 border-b border-base-300 pb-3">
                        <img
                          src="{{ !empty($pet->pet_img) ? asset('storage/pets/' . $pet->pet_img) : asset('images/no_image.jpg') }}"
                          alt="{{ $pet->name }}"
                          class="mask mask-squircle bg-base-200 shrink-0"
                          style="width: 48px; height: 48px; object-fit: cover;"
                        />
                        <div>
                          <p class="font-semibold text-base">{{ $pet->name }}</p>
                          <p class="text-xs text-base-content/70">Family Pet #{{ $loop->iteration }}</p>
                        </div>
                      </div>
                    @endif

                    <div>
                      <p class="font-semibold mb-2 text-base">Pet Information</p>
                      <div class="space-y-3 ms-2">
                        <div>
                          <p class="font-medium mb-2">Is your dog on flea/tick prevention?</p>
                          <div class="flex items-center gap-4 flex-wrap">
                            <label class="label cursor-pointer justify-start gap-2 p-0">
                              <input
                                type="radio"
                                class="radio radio-sm boarding-flea-prevention-radio"
                                name="pet_specific[{{ $petIdKey }}][flea_tick_prevention]"
                                value="yes"
                                data-pet-id="{{ $pet->id }}"
                                {{ $petFleaTickPrevention === 'yes' ? 'checked' : '' }}
                              />
                              <span class="label-text">Yes</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2 p-0">
                              <input
                                type="radio"
                                class="radio radio-sm boarding-flea-prevention-radio"
                                name="pet_specific[{{ $petIdKey }}][flea_tick_prevention]"
                                value="no"
                                data-pet-id="{{ $pet->id }}"
                                {{ $petFleaTickPrevention === 'no' ? 'checked' : '' }}
                              />
                              <span class="label-text">No</span>
                            </label>
                          </div>
                          <div
                            class="mt-2 boarding-flea-prevention-type-wrapper"
                            data-pet-id="{{ $pet->id }}"
                            style="{{ $petFleaTickPrevention === 'yes' ? '' : 'display: none;' }}"
                          >
                            <label class="label p-0 mb-1">
                              <span class="label-text">Prevention Type</span>
                            </label>
                            <select
                              class="select select-bordered w-full select-sm boarding-flea-prevention-type"
                              name="pet_specific[{{ $petIdKey }}][flea_tick_prevention_type]"
                              data-pet-id="{{ $pet->id }}"
                            >
                              <option value="">Select prevention type</option>
                              <option value="Bravecto" {{ $petFleaTickPreventionType === 'Bravecto' ? 'selected' : '' }}>Bravecto</option>
                              <option value="Heartgard" {{ $petFleaTickPreventionType === 'Heartgard' ? 'selected' : '' }}>Heartgard</option>
                              <option value="NexGard" {{ $petFleaTickPreventionType === 'NexGard' ? 'selected' : '' }}>NexGard</option>
                              <option value="Simparica" {{ $petFleaTickPreventionType === 'Simparica' ? 'selected' : '' }}>Simparica</option>
                              <option value="Other" {{ $petFleaTickPreventionType === 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                          </div>
                        </div>
                        <div>
                          <p class="font-medium mb-2">Items:</p>
                          <div class="mt-2">
                            <textarea class="textarea textarea-bordered w-full boarding-other-items-description" rows="2" data-pet-id="{{ $pet->id }}"
                              placeholder="Please describe items brought for boarding (e.g., Leash, Collar, toys, bedding, etc)">{{ $petOtherItemsDescription }}</textarea>
                          </div>
                        </div>
                        <div>
                          <p class="font-medium mb-2">Care notes:</p>
                          <div class="mt-2">
                            <textarea class="textarea textarea-bordered w-full boarding-care-notes" rows="2" data-pet-id="{{ $pet->id }}"
                              placeholder="Add care notes for {{ $pet->name }}...">{{ $petCareNotes }}</textarea>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-4">
                      <p class="font-semibold mb-2 text-base">Feeding and Medication Information</p>
                      <div class="space-y-4 ms-2">
                        <div class="border-b border-base-300 pb-4">
                          <div class="flex items-center justify-between mb-2">
                            <p class="font-bold">Dry Food</p>
                            <button type="button" class="btn btn-primary btn-sm add-boarding-dry-food" data-pet-id="{{ $pet->id }}">
                              <span class="iconify lucide--plus size-4"></span>
                              Add Dry
                            </button>
                          </div>
                          <div class="space-y-3 ms-2 boarding-dry-food-container" id="boarding_dry_food_container_{{ $pet->id }}">
                            @foreach($dryFoodRows as $index => $dryFoodRow)
                              @php
                                $rowDryAm = !empty($dryFoodRow['dispense_am']) && ($dryFoodRow['dispense_am'] === true || $dryFoodRow['dispense_am'] === 'true');
                                $rowDryPm = !empty($dryFoodRow['dispense_pm']) && ($dryFoodRow['dispense_pm'] === true || $dryFoodRow['dispense_pm'] === 'true');
                                $rowDryLunch = !empty($dryFoodRow['dispense_lunch']) && ($dryFoodRow['dispense_lunch'] === true || $dryFoodRow['dispense_lunch'] === 'true');
                              @endphp
                              <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-dry-food-row" data-row-index="{{ $index }}">
                                <div class="flex items-center justify-between">
                                  <p class="text-sm font-medium">Dry Food #{{ $index + 1 }}</p>
                                  <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-dry-food" title="Remove dry food">
                                    <span class="iconify lucide--x size-4 text-error"></span>
                                  </button>
                                </div>
                                <div>
                                  <p class="text-sm mb-1">Brand:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-brand" placeholder="Enter dry food brand" value="{{ $dryFoodRow['brand'] ?? '' }}" />
                                </div>
                                <div class="flex items-end gap-4">
                                  <div class="flex-1">
                                    <p class="text-sm mb-1">Amount:</p>
                                    <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-amount" placeholder="e.g., 1 cup, 1/2 cup" value="{{ $dryFoodRow['amount'] ?? '' }}" />
                                  </div>
                                  <div class="flex-1 pb-2">
                                    <p class="text-sm mb-1">Dispense:</p>
                                    <div class="flex items-center gap-3 flex-wrap">
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-am" {{ $rowDryAm ? 'checked' : '' }} />
                                        <span class="text-sm">AM</span>
                                      </label>
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-pm" {{ $rowDryPm ? 'checked' : '' }} />
                                        <span class="text-sm">PM</span>
                                      </label>
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-lunch" {{ $rowDryLunch ? 'checked' : '' }} />
                                        <span class="text-sm">Lunch</span>
                                      </label>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            @endforeach
                          </div>
                        </div>

                        <div class="border-b border-base-300 pb-4">
                          <div class="flex items-center justify-between mb-2">
                            <p class="font-bold">Wet Food</p>
                            <button type="button" class="btn btn-primary btn-sm add-boarding-wet-food" data-pet-id="{{ $pet->id }}">
                              <span class="iconify lucide--plus size-4"></span>
                              Add Wet
                            </button>
                          </div>
                          <div class="space-y-3 ms-2 boarding-wet-food-container" id="boarding_wet_food_container_{{ $pet->id }}">
                            @foreach($wetFoodRows as $index => $wetFoodRow)
                              @php
                                $rowWetAm = !empty($wetFoodRow['dispense_am']) && ($wetFoodRow['dispense_am'] === true || $wetFoodRow['dispense_am'] === 'true');
                                $rowWetPm = !empty($wetFoodRow['dispense_pm']) && ($wetFoodRow['dispense_pm'] === true || $wetFoodRow['dispense_pm'] === 'true');
                                $rowWetLunch = !empty($wetFoodRow['dispense_lunch']) && ($wetFoodRow['dispense_lunch'] === true || $wetFoodRow['dispense_lunch'] === 'true');
                              @endphp
                              <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-wet-food-row" data-row-index="{{ $index }}">
                                <div class="flex items-center justify-between">
                                  <p class="text-sm font-medium">Wet Food #{{ $index + 1 }}</p>
                                  <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-wet-food" title="Remove wet food">
                                    <span class="iconify lucide--x size-4 text-error"></span>
                                  </button>
                                </div>
                                <div>
                                  <p class="text-sm mb-1">Brand:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-brand" placeholder="Enter wet food brand" value="{{ $wetFoodRow['brand'] ?? '' }}" />
                                </div>
                                <div class="flex items-end gap-4">
                                  <div class="flex-1">
                                    <p class="text-sm mb-1">Amount:</p>
                                    <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-amount" placeholder="e.g., 2 Tbsp, 1 container" value="{{ $wetFoodRow['amount'] ?? '' }}" />
                                  </div>
                                  <div class="flex-1 pb-2">
                                    <p class="text-sm mb-1">Dispense:</p>
                                    <div class="flex items-center gap-3 flex-wrap">
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-am" {{ $rowWetAm ? 'checked' : '' }} />
                                        <span class="text-sm">AM</span>
                                      </label>
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-pm" {{ $rowWetPm ? 'checked' : '' }} />
                                        <span class="text-sm">PM</span>
                                      </label>
                                      <label class="flex items-center gap-2">
                                        <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-lunch" {{ $rowWetLunch ? 'checked' : '' }} />
                                        <span class="text-sm">Lunch</span>
                                      </label>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            @endforeach
                          </div>
                        </div>

                        <div>
                          <div class="flex items-center justify-between mb-2">
                            <p class="font-bold">Medications</p>
                            <button type="button" class="btn btn-primary btn-sm add-boarding-medication" data-pet-id="{{ $pet->id }}">
                              <span class="iconify lucide--plus size-4"></span>
                              Add Medication
                            </button>
                          </div>
                          <div class="space-y-3 ms-2 boarding-meds-container" id="boarding_meds_container_{{ $pet->id }}">
                            @foreach($medicationRows as $index => $medicationRow)
                              @php
                                $rowMealCondition = $medicationRow['meal_condition'] ?? ($medicationRow['condition'] ?? '');
                                if ($rowMealCondition === 'after_meals') {
                                  $rowMealCondition = 'after_meal';
                                }
                                $rowDispenseAm = !empty($medicationRow['dispense_am']) && ($medicationRow['dispense_am'] === true || $medicationRow['dispense_am'] === 'true');
                                $rowDispensePm = !empty($medicationRow['dispense_pm']) && ($medicationRow['dispense_pm'] === true || $medicationRow['dispense_pm'] === 'true');
                                $rowDispenseRest = !empty($medicationRow['dispense_rest']) && ($medicationRow['dispense_rest'] === true || $medicationRow['dispense_rest'] === 'true');
                                $rowDispenseBeforeBed = !empty($medicationRow['dispense_before_bed']) && ($medicationRow['dispense_before_bed'] === true || $medicationRow['dispense_before_bed'] === 'true');
                                $rowDispensePrn = !empty($medicationRow['dispense_prn']) && ($medicationRow['dispense_prn'] === true || $medicationRow['dispense_prn'] === 'true');
                                $rowDispenseCustomTime = !empty($medicationRow['dispense_custom_time']) && ($medicationRow['dispense_custom_time'] === true || $medicationRow['dispense_custom_time'] === 'true');
                                if (($medicationRow['condition'] ?? '') === 'custom_time') {
                                  $rowDispenseCustomTime = true;
                                }
                                if (($medicationRow['condition'] ?? '') === 'before_sleep') {
                                  $rowDispenseBeforeBed = true;
                                }
                                $rowCustomTime = $medicationRow['custom_time'] ?? '';
                              @endphp
                              <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-med-row" data-row-index="{{ $index }}">
                                <div class="flex items-center justify-between">
                                  <p class="text-sm font-medium">Medication #{{ $index + 1 }}</p>
                                  <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-medication" title="Remove medication">
                                    <span class="iconify lucide--x size-4 text-error"></span>
                                  </button>
                                </div>
                                <div>
                                  <p class="text-sm mb-1">Medication Name:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-med-name" placeholder="Enter medication name" value="{{ $medicationRow['name'] ?? '' }}" />
                                </div>
                                <div>
                                  <p class="text-sm mb-1">Dosage/Instruction:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-med-amount" placeholder="e.g., 1 pill, 2 drops left ear" value="{{ $medicationRow['amount'] ?? '' }}" />
                                </div>
                                <div>
                                  <p class="text-sm mb-1">Dispense:</p>
                                  <div class="flex items-center gap-3 flex-wrap">
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-am" {{ $rowDispenseAm ? 'checked' : '' }} />
                                      <span class="text-sm">AM</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-pm" {{ $rowDispensePm ? 'checked' : '' }} />
                                      <span class="text-sm">PM</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-rest" {{ $rowDispenseRest ? 'checked' : '' }} />
                                      <span class="text-sm">Rest</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-before-bed" {{ $rowDispenseBeforeBed ? 'checked' : '' }} />
                                      <span class="text-sm">Before Bed</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-prn" {{ $rowDispensePrn ? 'checked' : '' }} />
                                      <span class="text-sm">PRN</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                      <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-custom-time" {{ $rowDispenseCustomTime ? 'checked' : '' }} />
                                      <span class="text-sm">Custom Time</span>
                                    </label>
                                  </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                  <div>
                                    <p class="text-sm mb-1">Meal Condition:</p>
                                    <select class="select select-bordered w-full select-sm boarding-med-meal-condition">
                                      <option value="">Select option</option>
                                      <option value="after_meal" {{ $rowMealCondition === 'after_meal' ? 'selected' : '' }}>After Meal</option>
                                      <option value="before_meal" {{ $rowMealCondition === 'before_meal' ? 'selected' : '' }}>Before Meal</option>
                                      <option value="empty_stomach" {{ $rowMealCondition === 'empty_stomach' ? 'selected' : '' }}>Empty Stomach</option>
                                    </select>
                                  </div>
                                  <div class="boarding-med-custom-time-wrap {{ $rowDispenseCustomTime ? '' : 'hidden' }}">
                                    <p class="text-sm mb-1">Custom Time:</p>
                                    <input type="time" class="input input-bordered w-full input-sm boarding-med-custom-time" value="{{ $rowCustomTime }}" />
                                  </div>
                                </div>
                              </div>
                            @endforeach
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
                </div>

                <!-- Assignment or location for visit -->
                <div>
                  <p class="font-semibold mb-2 text-base">Assignment or location for visit</p>
                  <div class="space-y-3 ms-2">
                    <div>
                      <p class="font-medium mb-2">Location type:</p>
                      <div class="flex items-center gap-2 mb-2 space-y-1 ms-1">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="boarding_location_type" value="suite"
                            {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_type']) && $checkedIn->flows['location_type'] === 'suite' ? 'checked' : '' }} />
                          <span class="text-sm">Suite</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="boarding_location_type" value="run"
                            {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_type']) && $checkedIn->flows['location_type'] === 'run' ? 'checked' : '' }} />
                          <span class="text-sm">Run</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="boarding_location_type" value="bedroom"
                            {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_type']) && $checkedIn->flows['location_type'] === 'bedroom' ? 'checked' : '' }} />
                          <span class="text-sm">Bedroom</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="boarding_location_type" value="kennel"
                            {{ $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_type']) && $checkedIn->flows['location_type'] === 'kennel' ? 'checked' : '' }} />
                          <span class="text-sm">Kennel</span>
                        </label>
                      </div>
                      @if($appointment->pet->age >= 16)
                      @php
                        $restRequired = !empty($checkedIn->flows['rest_required']) && ($checkedIn->flows['rest_required'] === true || $checkedIn->flows['rest_required'] === 'true' || $checkedIn->flows['rest_required'] === 1 || $checkedIn->flows['rest_required'] === '1');
                        $restNote = $checkedIn->flows['rest_note'] ?? '';
                      @endphp
                      <div class="flex items-center gap-2 mb-2 space-y-1 ms-1">
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" id="boarding_rest_required" name="rest_required" value="1" {{ $restRequired ? 'checked' : '' }} />
                          <span class="text-sm">Rest Required (Senior Pet – 16 years old)</span>
                        </label>
                      </div>
                      <div class="ms-1">
                        <p class="font-medium mb-1">Rest Note:</p>
                        <textarea id="boarding_rest_note" class="textarea textarea-bordered w-full" rows="2" placeholder="Enter rest note..." {{ $restRequired ? '' : 'disabled' }}>{{ $restNote }}</textarea>
                      </div>
                      @endif
                    </div>
                  </div>
                </div>

                @if ($appointment->status === 'checked_in')
                {{-- Check-in Info (combined with Boarding Check-In for boarding) --}}
                <div>
                  <div class="space-y-4 ms-2">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Assign Staff (optional)</legend>
                        <select name="staff_id" id="staff_id" class="select select-bordered w-full select-sm">
                          <option value="" hidden>Select Staff Member</option>
                          @foreach($staffs as $staff)
                            <option value="{{ $staff->id }}" {{ $appointment->staff_id == $staff->id ? 'selected' : '' }}>
                              @if($staff->profile)
                                {{ $staff->profile->first_name }} {{ $staff->profile->last_name }}
                              @else
                                {{ $staff->name }}
                              @endif
                            </option>
                          @endforeach
                        </select>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Estimated Price{{ isBoardingService($appointment->service) ? ' (incl. tax)' : '' }}*</legend>
                        <label class="input w-full focus:outline-0 input-sm">
                        @php
                            $checkinStateTaxRate = isBoardingService($appointment->service) ? (float) config('billing.state_tax_rate', 7) : 0;
                            $estimatedPriceValue = (float) ($dbEstimatedPrice ?? 0) > 0
                              ? (float) $dbEstimatedPrice
                              : (float) ($appointment->estimated_price ?? 0);
                            $checkinNetPrice = $estimatedPriceValue;
                            
                            $estimatedPriceDisplayValue = $checkinNetPrice > 0
                              ? ($checkinNetPrice * (1 + ($checkinStateTaxRate / 100)))
                              : '';
                          @endphp
                          <input class="grow focus:outline-0" id="estimated_price" name="estimated_price" type="text"
                            value="{{ $estimatedPriceDisplayValue !== '' ? number_format($estimatedPriceDisplayValue, 2, '.', '') : '' }}" placeholder="Enter estimated price"
                            data-boarding-tax-rate="{{ isBoardingService($appointment->service) ? config('billing.state_tax_rate', 7) : 0 }}"
                            oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" required />
                          <span class="badge badge-ghost badge-sm">USD</span>
                        </label>
                      </fieldset>
                    </div>
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Date*</legend>
                        <input class="input input-sm w-full" placeholder="Select date" id="checkin_date" name="checkin_date" type="date" value="{{ $checkedIn->date ?? ($appointment->date ?? '') }}"/>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Start Time*</legend>
                        <input class="input input-sm w-full" placeholder="Select time" id="start_time" name="start_time" type="time" min="09:00" max="18:00" value="{{ $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '' }}"/>
                      </fieldset>
                    </div>
                    <div>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Notes</legend>
                        <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the check-in process..." id="notes" name="notes" rows="3">{{ $checkedIn->notes ?? '' }}</textarea>
                      </fieldset>
                    </div>
                    <div class="alert alert-soft alert-info">
                      <span class="iconify lucide--info size-4"></span>
                      <span>Estimated price{{ isBoardingService($appointment->service) ? ' (tax included)' : '' }} is required before continuing. Staff assignment can be added now or later.</span>
                    </div>
                    <div class="border border-base-300 rounded-box p-4 space-y-4">
                      <p class="font-semibold text-base">Boarding Agreement</p>
                      <div class="rounded-box border border-base-300 bg-base-100 p-3 text-sm text-base-content/80 space-y-2">
                        <p>
                          <span class="font-medium text-base-content">Release and waiver:</span>
                          I understand boarding activities carry inherent risks and that The Sunshine Spot is not liable for injury, illness, or loss during my pet’s stay, except where prohibited by law.
                        </p>
                        <p>
                          <span class="font-medium text-base-content">Authorization to treat:</span>
                          I authorize the facility to arrange reasonable care and treatment for my pet when needed during boarding.
                        </p>
                        <p>
                          <span class="font-medium text-base-content">Emergency care consent:</span>
                          If I cannot be reached promptly, I consent to emergency veterinary care deemed necessary for my pet's welfare.
                        </p>
                        <p>
                          <span class="font-medium text-base-content">Parasite and flea/tick acknowledgement:</span>
                          I understand that if fleas, ticks, or parasites are detected during boarding, necessary treatment may be administered and related fees may apply.
                        </p>
                        <p>
                          <span class="font-medium text-base-content">Facility policy acknowledgement:</span>
                          I acknowledge and agree to follow the facility’s boarding policies, pickup requirements, cancellation policy, and payment terms.
                        </p>
                      </div>
                      @php
                        $agreementAccepted = !empty($flows['boarding_agreement_accepted']) && ($flows['boarding_agreement_accepted'] === true || $flows['boarding_agreement_accepted'] === 'true');
                        $vetAuthorized = !empty($flows['boarding_vet_authorized']) && ($flows['boarding_vet_authorized'] === true || $flows['boarding_vet_authorized'] === 'true');
                      @endphp
                      <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" id="boarding_agreement_accepted" class="checkbox checkbox-sm" {{ $agreementAccepted ? 'checked' : '' }} />
                        <span class="label-text">I have read and agree to the boarding agreement</span>
                      </label>
                      <label class="label cursor-pointer justify-start gap-2">
                        <input type="checkbox" id="boarding_vet_authorized" class="checkbox checkbox-sm" {{ $vetAuthorized ? 'checked' : '' }} />
                        <span class="label-text">I authorize the facility to seek veterinary treatment if needed</span>
                      </label>

                      <p class="font-semibold text-base">Owner Signature</p>
                      <div>
                        <p class="text-sm mb-1">Owner full name:</p>
                        <input type="text" id="boarding_owner_full_name" class="input input-bordered w-full input-sm"
                          placeholder="Enter owner full name"
                          value="{{ $flows['boarding_owner_full_name'] ?? '' }}" />
                      </div>
                      <div>
                        <p class="text-sm mb-1">Signature:</p>
                        <div class="rounded-box border border-base-300 bg-base-100 p-2">
                          <canvas id="boarding_signature_pad" width="900" height="180" class="w-full" style="height: 180px;"></canvas>
                        </div>
                        <input type="hidden" id="boarding_signature_data" value="{{ $flows['boarding_signature_data'] ?? '' }}" />
                        <p class="text-xs text-error mt-1 hidden" id="boarding_signature_error">Signature is required.</p>
                      </div>
                      <div>
                        <p class="text-sm mb-1">Date:</p>
                        <input type="date" id="boarding_signature_date" class="input input-bordered w-full input-sm"
                          value="{{ $flows['boarding_signature_date'] ?? now()->format('Y-m-d') }}" readonly />
                      </div>
                      <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-outline btn-sm" id="boarding_clear_signature">
                          Clear Signature
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="boarding_save_signature">
                          Save Signature
                        </button>
                      </div>
                      <p class="text-xs text-success hidden" id="boarding_signature_saved_note">Signature has been saved.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Appointment Status</legend>
                        <select name="appointment_status" id="appointment_status" class="select select-bordered w-full select-sm">
                          <option value="">-- Select Status --</option>
                          <option value="cancelled" {{ $appointment->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                          <option value="no_show" {{ $appointment->status === 'no_show' ? 'selected' : '' }}>No Show</option>
                        </select>
                      </fieldset>
                    </div>
                  </div>
                </div>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif
      @if ($appointment->status === 'checked_in' && !isBoardingService($appointment->service))
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Check-in Info</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Assign Staff (optional)</legend>
                  <select name="staff_id" id="staff_id" class="select select-bordered w-full select-sm">
                    <option value="" hidden>Select Staff Member</option>
                    @foreach($staffs as $staff)
                      <option value="{{ $staff->id }}" {{ $appointment->staff_id == $staff->id ? 'selected' : '' }}>
                        @if($staff->profile)
                          {{ $staff->profile->first_name }} {{ $staff->profile->last_name }}
                        @else
                          {{ $staff->name }}
                        @endif
                      </option>
                    @endforeach
                  </select>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Estimated Price{{ isBoardingService($appointment->service) ? ' (incl. tax)' : '' }}*</legend>
                  <label class="input w-full focus:outline-0 input-sm">
                    @php
                      $checkinStateTaxRate = isBoardingService($appointment->service) ? (float) config('billing.state_tax_rate', 7) : 0;
                      $estimatedPriceValue = (float) ($dbEstimatedPrice ?? 0) > 0
                        ? (float) $dbEstimatedPrice
                        : (float) ($appointment->estimated_price ?? 0);
                      $checkinNetPrice = $estimatedPriceValue;
                      
                      $estimatedPriceDisplayValue = $checkinNetPrice > 0
                        ? ($checkinNetPrice * (1 + ($checkinStateTaxRate / 100)))
                        : '';
                    @endphp
                    <input class="grow focus:outline-0" id="estimated_price" name="estimated_price" type="text"
                      value="{{ $estimatedPriceDisplayValue !== '' ? number_format($estimatedPriceDisplayValue, 2, '.', '') : '' }}" placeholder="Enter estimated price"
                      data-boarding-tax-rate="{{ isBoardingService($appointment->service) ? config('billing.state_tax_rate', 7) : 0 }}"
                      oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" required />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </fieldset>
              </div>
              <div class="mt-4 grid grid-cols-1 gap-6 {{ isBoardingService($appointment->service) ? 'xl:grid-cols-2' : 'xl:grid-cols-3' }}">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Date*</legend>
                  <input class="input input-sm w-full" placeholder="Select date" id="checkin_date" name="checkin_date" type="date" value="{{ $checkedIn->date ?? ($appointment->date ?? '') }}"/>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Start Time*</legend>
                  <input class="input input-sm w-full" placeholder="Select time" id="start_time" name="start_time" type="time" min="09:00" max="18:00" value="{{ $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '' }}"/>
                </fieldset>
                @if (!isBoardingService($appointment->service))
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Pickup Time*</legend>
                  <input class="input input-sm w-full" placeholder="Select time" id="pickup_time" name="pickup_time" type="time" min="09:00" max="18:00" value="{{ $appointment->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->end_time)->format('H:i') : '' }}"/>
                </fieldset>
                @endif
              </div>
              <div class="mt-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Notes</legend>
                  <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the check-in process..." id="notes" name="notes" rows="3">{{ $checkedIn->notes ?? '' }}</textarea>
                </fieldset>
              </div>
              <div class="alert alert-soft alert-info mt-4">
                <span class="iconify lucide--info size-4"></span>
                <span>Estimated price{{ isBoardingService($appointment->service) ? ' (tax included)' : '' }} is required before continuing. Staff assignment can be added now or later.</span>
              </div>
              <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Appointment Status</legend>
                  <select name="appointment_status" id="appointment_status" class="select select-bordered w-full select-sm">
                    <option value="">-- Select Status --</option>
                    <option value="cancelled" {{ $appointment->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="no_show" {{ $appointment->status === 'no_show' ? 'selected' : '' }}>No Show</option>
                  </select>
                </fieldset>
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif
      @if ($appointment->status === 'in_progress')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Process Info</div>
            <div class="collapse-content bg-base-100">
              @if (!isAlaCarteService($appointment->service) && !isBoardingService($appointment->service))
              <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Assign Staff*</legend>
                  <select name="process_staff_id" id="process_staff_id" class="select select-bordered w-full select-sm" required>
                    <option value="" hidden>Select Staff Member</option>
                    @foreach($staffs as $staff)
                      <option value="{{ $staff->id }}" {{ $appointment->staff_id == $staff->id ? 'selected' : '' }}>
                        @if($staff->profile)
                          {{ $staff->profile->first_name }} {{ $staff->profile->last_name }}
                        @else
                          {{ $staff->name }}
                        @endif
                      </option>
                    @endforeach
                  </select>
                </fieldset>
              </div>
              @endif
              @if (isPrivateTrainingService($appointment->service))
                @php
                  $location = $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location']) ? $checkedIn->flows['location'] : '';
                  $pickupDateTime = '';
                  if ($checkedIn && $checkedIn->flows) {
                    if (isset($checkedIn->flows['pickup_datetime_onsite']) && $checkedIn->flows['pickup_datetime_onsite']) {
                      try {
                        $dt = \Carbon\Carbon::parse($checkedIn->flows['pickup_datetime_onsite']);
                        $pickupDateTime = $dt->format('M j, Y g:i A');
                      } catch (\Exception $e) {
                        $pickupDateTime = $checkedIn->flows['pickup_datetime_onsite'];
                      }
                    } else {
                      $pickupDate = isset($checkedIn->flows['pickup_date_onsite']) ? $checkedIn->flows['pickup_date_onsite'] : '';
                      $pickupTime = isset($checkedIn->flows['pickup_time_onsite']) ? $checkedIn->flows['pickup_time_onsite'] : '';
                      if ($pickupDate && $pickupTime) {
                        try {
                          $dt = \Carbon\Carbon::parse($pickupDate . ' ' . $pickupTime);
                          $pickupDateTime = $dt->format('M j, Y g:i A');
                        } catch (\Exception $e) {
                          $pickupDateTime = $pickupDate . ' ' . $pickupTime;
                        }
                      } elseif ($pickupDate) {
                        try {
                          $dt = \Carbon\Carbon::parse($pickupDate);
                          $pickupDateTime = $dt->format('M j, Y');
                        } catch (\Exception $e) {
                          $pickupDateTime = $pickupDate;
                        }
                      }
                    }
                  }
                  $locationAddress = $checkedIn && $checkedIn->flows && isset($checkedIn->flows['location_address']) ? $checkedIn->flows['location_address'] : '';
                  $descriptionNeeds = $checkedIn && $checkedIn->flows && isset($checkedIn->flows['description_needs']) ? $checkedIn->flows['description_needs'] : '';
                  $previousNotes = $checkedIn && $checkedIn->notes ? $checkedIn->notes : '';
                @endphp
              <div class="text-sm mt-4 space-y-4">
                <div class="flex align-items-center">
                  <p class="font-medium mb-2" style="flex-grow: unset">Location:</p>
                  <div class="mb-2 ms-4">
                    <p class="text-sm text-base-content/70">{{ ucfirst($location) }}</p>
                  </div>
                </div>
                @if($location === 'onsite')
                  <div class="flex align-items-center">
                    <p class="font-medium mb-2" style="flex-grow: unset">Pick up time/date:</p>
                    <div class="mb-2 ms-4">
                      <p class="text-sm text-base-content/70">{{ $pickupDateTime ?: 'Not set' }}</p>
                    </div>
                  </div>
                @elseif($location === 'offsite')
                  <div class="flex align-items-center">
                    <p class="font-medium mb-2" style="flex-grow: unset">Location/address:</p>
                    <div class="mb-2 ms-4">
                      <p class="text-sm text-base-content/70">{{ $locationAddress ?: 'Not set' }}</p>
                    </div>
                  </div>
                @endif
                <div>
                  <p class="font-medium mb-2">Goals/owner needs:</p>
                  <div class="mb-2 ms-4">
                    <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $descriptionNeeds ?: 'Not set' }}</p>
                  </div>
                </div>
              </div>
              @endif
              @if (isAlaCarteService($appointment->service))
                {{-- Ala Carte Secondary Services Process Section --}}
                @php
                  $secondaryServiceIds = [];
                  $secondaryServices = collect();
                  if ($appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
                    $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
                    $secondaryServices = \App\Models\Service::whereIn('id', $secondaryServiceIds)->get();
                  }
                @endphp
                @if($secondaryServices->count() > 0)
                <div id="ala_carte_process_container" class="mt-4 space-y-6">
                  @foreach($secondaryServices as $secondaryService)
                    @php
                      $existingProcess = \App\Models\Process::where('appointment_id', $appointment->id)
                        ->where('detail_id', $secondaryService->id)
                        ->orderBy('updated_at', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    @endphp
                    <div class="border border-base-300 rounded-box p-4" data-service-id="{{ $secondaryService->id }}">
                      <h4 class="font-medium mb-4">{{ $secondaryService->name }}</h4>
                      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Assign Staff*</legend>
                          <select class="select select-bordered w-full select-sm ala-carte-staff-id" data-service-id="{{ $secondaryService->id }}" required>
                            <option value="" hidden>Select Staff Member</option>
                            @foreach($staffs as $staff)
                              <option value="{{ $staff->id }}" {{ $existingProcess && $existingProcess->staff_id == $staff->id ? 'selected' : '' }}>
                                @if($staff->profile)
                                  {{ $staff->profile->first_name }} {{ $staff->profile->last_name }}
                                @else
                                  {{ $staff->name }}
                                @endif
                              </option>
                            @endforeach
                          </select>
                        </fieldset>
                      </div>
                      <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Date*</legend>
                          <input class="input input-sm w-full ala-carte-date"
                            placeholder="Select date"
                            data-service-id="{{ $secondaryService->id }}"
                            type="date"
                            value="{{ $existingProcess ? $existingProcess->date : ($appointment->date ?? '') }}"/>
                        </fieldset>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Start Time*</legend>
                          <input class="input input-sm w-full ala-carte-start-time"
                            placeholder="Select time"
                            data-service-id="{{ $secondaryService->id }}"
                            type="time"
                            min="09:00"
                            max="18:00"
                            value="{{ $existingProcess && $existingProcess->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $existingProcess->start_time)->format('H:i') : '' }}"/>
                        </fieldset>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Pickup Time*</legend>
                          <input class="input input-sm w-full ala-carte-pickup-time"
                            placeholder="Select time"
                            data-service-id="{{ $secondaryService->id }}"
                            type="time"
                            min="09:00"
                            max="18:00"
                            value="{{ $existingProcess && $existingProcess->pickup_time ? \Carbon\Carbon::createFromFormat('H:i:s', $existingProcess->pickup_time)->format('H:i') : '' }}"/>
                        </fieldset>
                      </div>
                      <div class="mt-4">
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Notes</legend>
                          <textarea class="textarea textarea-bordered w-full ala-carte-notes"
                            placeholder="Add notes for this service..."
                            data-service-id="{{ $secondaryService->id }}"
                            rows="3">{{ $existingProcess ? $existingProcess->notes : '' }}</textarea>
                        </fieldset>
                      </div>
                      <div class="mt-4">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveAlaCarteProcess({{ $appointment->id }}, {{ $secondaryService->id }})">
                          Save Process
                        </button>
                      </div>
                    </div>
                  @endforeach
                </div>
                @endif
              @elseif (isBoardingService($appointment->service))
                @php
                  $flows = $process && $process->flows ? (is_string($process->flows) ? json_decode($process->flows, true) : $process->flows) : [];
                  $selectedStaffId = ($process && $process->staff_id) ? $process->staff_id : ($appointment->staff_id ?? null);
                  $selectedStaff = $selectedStaffId ? $staffs->firstWhere('id', $selectedStaffId) : null;
                  $selectedStaffName = $selectedStaff ? ($selectedStaff->profile ? $selectedStaff->profile->first_name . ' ' . $selectedStaff->profile->last_name : $selectedStaff->name) : 'N/A';
                  
                  function formatTime($time) {
                    if (!$time) return 'N/A';
                    try {
                      return \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('h:i A');
                    } catch (\Exception $e) {
                      try {
                        return \Carbon\Carbon::createFromFormat('H:i', $time)->format('h:i A');
                      } catch (\Exception $e2) {
                        return $time;
                      }
                    }
                  }
                  
                  function formatDate($date) {
                    if (!$date) return 'N/A';
                    try {
                      return \Carbon\Carbon::parse($date)->format('M d, Y');
                    } catch (\Exception $e) {
                      return $date;
                    }
                  }
                  
                  function getRadioValue($flows, $key, $options) {
                    $value = $flows[$key] ?? null;
                    foreach ($options as $optValue => $optLabel) {
                      if ($value === $optValue) return $optLabel;
                    }
                    return 'N/A';
                  }
                  
                  function getCheckedItems($flows, $keys) {
                    $checked = [];
                    foreach ($keys as $key => $label) {
                      if (isset($flows[$key]) && ($flows[$key] === true || $flows[$key] === 'true')) {
                        $checked[] = $label;
                      }
                    }
                    return $checked;
                  }
                  
                  function isPetSelected($flows, $key, $petId) {
                    if (!isset($flows[$key]) || !$petId) {
                      return false;
                    }
                    $petIds = $flows[$key];
                    if (!is_array($petIds)) {
                      // Handle legacy boolean values
                      return ($petIds === true || $petIds === 'true');
                    }
                    // Check if pet ID is in the array (handle both string and integer IDs)
                    return in_array($petId, $petIds) || in_array((string)$petId, $petIds);
                  }
                  
                  function displayConciergeSection($flows, $sectionKey, $sectionTitle, $issueKeys) {
                    $status = $flows[$sectionKey] ?? null;
                    $statusText = $status === 'okay' ? 'Okay' : ($status === 'issue' ? 'Issue' : 'N/A');
                    $issues = [];
                    if ($status === 'issue') {
                      foreach ($issueKeys as $key => $label) {
                        if (isset($flows[$key]) && ($flows[$key] === true || $flows[$key] === 'true')) {
                          $issues[] = $label;
                        }
                      }
                    }
                    $notes = $flows[$sectionKey . '_notes'] ?? '';
                    return [
                      'status' => $statusText,
                      'issues' => $issues,
                      'notes' => $notes
                    ];
                  }
                @endphp
                <div class="mb-4">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Previous Check-in Notes</legend>
                    <div class="textarea textarea-bordered w-full" style="background-color: #181c20; white-space: pre-wrap; word-wrap: break-word; min-height: 80px; padding: 0.75rem; color: #ffffff;">{{ isset($checkedIn) && $checkedIn ? ($checkedIn->notes ?? 'No previous notes available') : 'No previous notes available' }}</div>
                  </fieldset>
                </div>

                <div class="mt-4 mb-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Date</legend>
                    <input class="input input-bordered w-full input-sm" placeholder="Select date" id="boarding_workflow_date" name="boarding_workflow_date" type="date" value="{{ $appointment->date ?? '' }}"/>
                  </fieldset>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Assigned Staff</legend>
                    <div class="text-sm py-2" id="boarding_assigned_staff">{{ $selectedStaffName }}</div>
                  </fieldset>
                </div>

                <div class="mt-4 space-y-4">
                  <h4 class="font-semibold text-base mb-3">Daily Workflow Tasks</h4>
                  <div id="boarding_no_record_message" class="hidden">
                    <div class="alert">
                      <span class="iconify lucide--info size-5"></span>
                      <div>
                        <h3 class="font-bold">No Process Log Found</h3>
                        <div class="text-sm">No workflow data has been recorded for the selected date.</div>
                        <div class="mt-2">
                          <a href="{{ route('boarding-process-log-create') }}" class="btn btn-primary btn-sm">
                            <span class="iconify lucide--plus size-4"></span>
                            Create Process Log
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div id="boarding_workflow_content">
                    {{-- Daily process status matches boarding-process-log tabs: steps keyed by process id (e.g. food_prep_am, reports_am). --}}
                    <div id="boarding_workflow_steps_container" class="space-y-4" data-appointment-id="{{ $appointment->id ?? '' }}">
                      <div class="border border-base-300 rounded-box overflow-hidden" data-section="am-feeding-meds">
                        <p class="font-medium p-3 pb-0">AM Feeding/Meds</p>
                        <table class="table table-sm table-fixed w-full">
                          <thead>
                            <tr><th class="text-start w-auto">Task</th><th class="text-center w-24">Time</th><th class="text-center w-28">Employee</th><th class="text-start w-56 min-w-48">Details</th></tr>
                          </thead>
                          <tbody>
                            <tr data-step-id="food_prep_am"><td class="text-sm">Food Prep (AM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="meds_prep_am"><td class="text-sm">Meds Prep (AM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="feeding_am"><td class="text-sm">Feeding Dispense (AM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="meds_dispense_am"><td class="text-sm">Meds Dispense (AM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="reports_am"><td class="text-sm">Reports (AM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                          </tbody>
                        </table>
                      </div>
                      <div class="border border-base-300 rounded-box overflow-hidden" data-section="nose-to-tail">
                        <p class="font-medium p-3 pb-0">Nose to Tail</p>
                        <table class="table table-sm table-fixed w-full">
                          <thead>
                            <tr><th class="text-start w-auto">Task</th><th class="text-center w-24">Time</th><th class="text-center w-28">Employee</th><th class="text-start w-56 min-w-48">Details</th></tr>
                          </thead>
                          <tbody>
                            <tr data-step-id="check_pet"><td class="text-sm">Check Pet</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="treatment_plan"><td class="text-sm">Treatment Plan</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                          </tbody>
                        </table>
                      </div>
                      <div class="border border-base-300 rounded-box overflow-hidden" data-section="treatment-lunch-rest">
                        <p class="font-medium p-3 pb-0">Treatment/Lunch/Rest</p>
                        <table class="table table-sm table-fixed w-full">
                          <thead>
                            <tr><th class="text-start w-auto">Task</th><th class="text-center w-24">Time</th><th class="text-center w-28">Employee</th><th class="text-start w-56 min-w-48">Details</th></tr>
                          </thead>
                          <tbody>
                            <tr data-step-id="treatments_tlr"><td class="text-sm">Treatments</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="next_day_treatment_list_tlr"><td class="text-sm">Next Day's Treatment</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="lunch_tlr"><td class="text-sm">Lunch</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="rest_tlr"><td class="text-sm">Rest</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                          </tbody>
                        </table>
                      </div>
                      <div class="border border-base-300 rounded-box overflow-hidden" data-section="pm-feeding-meds">
                        <p class="font-medium p-3 pb-0">PM Feeding/Meds</p>
                        <table class="table table-sm table-fixed w-full">
                          <thead>
                            <tr><th class="text-start w-auto">Task</th><th class="text-center w-24">Time</th><th class="text-center w-28">Employee</th><th class="text-start w-56 min-w-48">Details</th></tr>
                          </thead>
                          <tbody>
                            <tr data-step-id="food_prep_pm"><td class="text-sm">Food Prep (PM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="meds_prep_pm"><td class="text-sm">Meds Prep (PM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="feeding_pm"><td class="text-sm">Feeding Dispense (PM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="meds_dispense_pm"><td class="text-sm">Meds Dispense (PM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                            <tr data-step-id="reports_pm"><td class="text-sm">Reports (PM)</td><td class="text-sm text-center w-24" data-display="time">—</td><td class="text-sm text-center w-28" data-display="employee"></td><td class="text-sm w-56 min-w-48" data-display="detail"></td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div class="alert mt-4">
                      <span class="iconify lucide--info size-5"></span>
                      <div>
                        <a href="{{ route('boarding-process-log') }}" class="btn btn-primary btn-sm">
                          <span class="iconify lucide--eye size-4"></span>
                          View Process Log
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="alert alert-soft alert-info mt-4">
                  <span class="iconify lucide--info size-4"></span>
                  <span>Confirming the process will mark the appointment as completed.</span>
                </div>
              @else
                {{-- Regular Process Info Section --}}
                <div class="mb-4">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Previous Check-in Notes</legend>
                    <div class="textarea textarea-bordered w-full" style="background-color: #181c20; white-space: pre-wrap; word-wrap: break-word; min-height: 80px; padding: 0.75rem; color: #ffffff;">{{ isset($checkedIn) && $checkedIn ? ($checkedIn->notes ?? 'No previous notes available') : 'No previous notes available' }}</div>
                  </fieldset>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-3">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Date*</legend>
                    <input class="input input-bordered w-full" placeholder="Select date" id="process_date" name="process_date" type="date" value="{{ $process->date ?? ($appointment->date ?? '') }}"/>
                  </fieldset>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Start Time*</legend>
                    <input class="input input-bordered w-full" placeholder="Select time" id="process_start_time" name="process_start_time" type="time" min="09:00" max="18:00" value="{{ $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '' }}" disabled/>
                  </fieldset>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Pickup Time*</legend>
                    <input class="input input-bordered w-full" placeholder="Select time" id="process_pickup_time" name="process_pickup_time" type="time" min="09:00" max="18:00" value="{{ $appointment->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->end_time)->format('H:i') : '' }}" disabled/>
                  </fieldset>
                </div>
                <div class="mt-4">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Notes</legend>
                    <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the {{ strtolower($appointment->service->name) }}..." id="process_notes" name="process_notes" rows="3">{{ $process->notes ?? '' }}</textarea>
                  </fieldset>
                </div>
                <div class="alert alert-soft alert-info mt-4">
                  <span class="iconify lucide--info size-4"></span>
                  <span>Confirming the process will mark the appointment as completed.</span>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endif
      @if ($appointment->status === 'completed')
      @php
        $checkoutFlowValues = [];
        if ($checkout && !empty($checkout->flows)) {
          if (is_array($checkout->flows)) {
            $checkoutFlowValues = $checkout->flows;
          } else {
            $decodedCheckoutFlows = json_decode($checkout->flows, true);
            $checkoutFlowValues = is_array($decodedCheckoutFlows) ? $decodedCheckoutFlows : [];
          }
        }

        $actualCheckoutAt = null;
        if (!empty($checkoutFlowValues['actual_checkout_at'])) {
          try {
            $actualCheckoutAt = \Carbon\Carbon::parse($checkoutFlowValues['actual_checkout_at']);
          } catch (\Throwable $e) {
            $actualCheckoutAt = null;
          }
        }

        if (!$actualCheckoutAt && !empty($checkout->date) && !empty($checkoutFlowValues['actual_checkout_time'])) {
          try {
            $actualCheckoutAt = \Carbon\Carbon::parse($checkout->date . ' ' . $checkoutFlowValues['actual_checkout_time']);
          } catch (\Throwable $e) {
            $actualCheckoutAt = null;
          }
        }

        $invoiceStatusForCheckoutInfo = strtolower((string) ($invoice->status ?? ''));
        $useLiveCheckoutClock = !in_array($invoiceStatusForCheckoutInfo, ['paid', 'finalized'], true);

        if ($appointment->status === 'completed' && $useLiveCheckoutClock) {
          $actualCheckoutAt = now();
        } elseif (!$actualCheckoutAt) {
          $actualCheckoutAt = now();
        }
      @endphp
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Checkout Info</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-3">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Date*</legend>
                  <input class="input input-bordered w-full" placeholder="Select date" id="checkout_date" name="checkout_date" type="date" value="{{ $actualCheckoutAt ? $actualCheckoutAt->toDateString() : ($checkout->date ?? now()->toDateString()) }}"/>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Start Time</legend>
                  <input class="input input-bordered w-full" placeholder="Select time" id="checkout_start_time" name="checkout_start_time" type="time" min="09:00" max="18:00" value="{{ $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '' }}" disabled/>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Pickup Time</legend>
                  <input class="input input-bordered w-full" placeholder="Select time" id="checkout_pickup_time" name="checkout_pickup_time" type="time" min="09:00" max="18:00" value="{{ $actualCheckoutAt ? $actualCheckoutAt->format('H:i') : now()->format('H:i') }}" disabled/>
                </fieldset>
              </div>
              @if (isGroomingService($appointment->service) && $process && $process->notes)
              <div class="mt-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Process Notes (from In Progress)</legend>
                  <div class="textarea textarea-bordered w-full" style="background-color: #181c20; white-space: pre-wrap; word-wrap: break-word; min-height: 80px; padding: 0.75rem; color: #ffffff;">{{ $process->notes }}</div>
                </fieldset>
              </div>
              @endif
              @if(!empty($staySummary['has_data']))
              <div class="mt-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Summary of Stay</legend>
                  <div class="space-y-3">
                    @foreach(($staySummary['pets'] ?? []) as $petSummary)
                      @if(!empty($petSummary['has_data']))
                      <div class="rounded-box border border-base-300 p-3">
                        @if(($staySummary['pet_count'] ?? 1) > 1)
                          <p class="font-medium">{{ $petSummary['pet_name'] ?? 'Pet' }}</p>
                        @endif
                        <div class="mt-2 space-y-2">
                          @if(!empty($petSummary['dnes']))
                          <div>
                            <p class="font-medium text-sm">DNEs</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['dnes'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['nose_to_tail']))
                          <div>
                            <p class="font-medium text-sm">Nose-to-tail issues</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['nose_to_tail'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['treatments']))
                          <div>
                            <p class="font-medium text-sm">Treatments</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['treatments'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['prn_meds']))
                          <div>
                            <p class="font-medium text-sm">PRN Medications</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['prn_meds'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['incidents']))
                          <div>
                            <p class="font-medium text-sm">Incidents</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['incidents'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['behavior']))
                          <div>
                            <p class="font-medium text-sm">Behavior</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['behavior'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif

                          @if(!empty($petSummary['notes']))
                          <div>
                            <p class="font-medium text-sm">Notes</p>
                            <ul class="list-disc ms-4 text-sm text-base-content/80 space-y-1">
                              @foreach($petSummary['notes'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          </div>
                          @endif
                        </div>
                      </div>
                      @endif
                    @endforeach
                  </div>
                </fieldset>
              </div>
              @endif
              @if (isPrivateTrainingService($appointment->service))
              @php
                $descriptionNeeds = $checkedIn && $checkedIn->flows && isset($checkedIn->flows['description_needs']) ? $checkedIn->flows['description_needs'] : '';
              @endphp
              @if($descriptionNeeds)
              <div class="mt-4">
                <p class="font-medium mb-2">Customer Goal:</p>
                <div class="mb-2 ms-4">
                  <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $descriptionNeeds }}</p>
                </div>
              </div>
              @endif
              @endif
              <hr class="mt-5" style="color: lightgray"/>
              <div class="mt-4 space-y-2 text-sm">
                @if (isPrivateTrainingService($appointment->service))
                @if(!empty($lastAppointmentRatings))
                <div>
                  <p class="font-medium mb-2">Star Rating from Last Appointment:</p>
                  @php
                    $obedienceCommands = ['sit', 'down', 'stay', 'come', 'loose_leash_walking'];
                  @endphp
                  <div class="mb-4 ms-4 space-y-3">
                    @foreach($obedienceCommands as $command)
                      @php
                        $commandLabel = ucwords(str_replace('_', ' ', $command));
                        $lastRating = isset($lastAppointmentRatings[$command]) ? (int)$lastAppointmentRatings[$command] : 0;
                      @endphp
                      <div class="flex items-center gap-2">
                        <p class="text-sm font-medium mb-1">{{ $commandLabel }}:</p>
                        <div class="flex items-center gap-1">
                          @for($i = 0; $i <= 5; $i++)
                            <span class="iconify lucide--star size-5" style="color: {{ $i <= $lastRating ? '#fbbf24' : '#d1d5db' }};"></span>
                          @endfor
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
                <hr class="my-4" style="color: lightgray"/>
                @endif
                <div>
                  <div class="flex items-center gap-3 mb-2">
                    <p class="font-medium" style="flex-grow: unset">Basic obedience (5-star rating)</p>
                    <div class="dropdown dropdown-end dropdown-hover">
                      <div tabindex="0" role="button" class="cursor-help">
                        <span class="iconify lucide--info size-4 mt-1"></span>
                      </div>
                      <div tabindex="0" class="dropdown-content z-[1] menu p-4 shadow bg-base-100 rounded-box w-80 border border-base-300" style="left: -10px">
                        <div class="space-y-2 text-xs">
                          <p class="font-semibold text-sm mb-2">5 Star Rating System:</p>
                          <p><span class="font-medium">0 star</span> – pet does not recognize command</p>
                          <p><span class="font-medium">1 star</span> – pet has infrequent response to command in a quiet environment</p>
                          <p><span class="font-medium">2 stars</span> – pet has intermittent response to command but highly distracted</p>
                          <p><span class="font-medium">3 stars</span> – pet has regular response to command but effected by distractions</p>
                          <p><span class="font-medium">4 stars</span> – pet has reliable response to command but has some challenges when distracted</p>
                          <p><span class="font-medium">5 stars</span> – pet has dependable response to command regardless of environment</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  @php
                    $obedienceRatings = $checkout && $checkout->flows && isset($checkout->flows['obedience_ratings']) ? $checkout->flows['obedience_ratings'] : [];
                    $obedienceCommands = ['sit', 'down', 'stay', 'come', 'loose_leash_walking'];
                    $starDescriptions = [
                      0 => 'pet does not recognize command',
                      1 => 'pet has infrequent response to command in a quiet environment',
                      2 => 'pet has intermittent response to command but highly distracted',
                      3 => 'pet has regular response to command but effected by distractions',
                      4 => 'pet has reliable response to command but has some challenges when distracted',
                      5 => 'pet has dependable response to command regardless of environment'
                    ];
                  @endphp
                  <div class="mb-2 ms-4 space-y-3">
                    @foreach($obedienceCommands as $command)
                      @php
                        $commandLabel = ucwords(str_replace('_', ' ', $command));
                        $currentRating = isset($obedienceRatings[$command]) ? $obedienceRatings[$command] : 0;
                      @endphp
                      <div class="flex items-center gap-2">
                        <p class="text-sm font-medium mb-1">{{ $commandLabel }}:</p>
                        <div class="flex items-center gap-1 star-rating-container" data-rating="{{ $currentRating }}" data-command="{{ $command }}">
                          @for($i = 1; $i <= 5; $i++)
                            <input type="radio" class="hidden" name="obedience_rating_{{ $command }}" value="{{ $i }}" id="rating_{{ $command }}_{{ $i }}" {{ $currentRating == $i ? 'checked' : '' }} />
                            <label for="rating_{{ $command }}_{{ $i }}" class="cursor-pointer">
                              <span class="iconify lucide--star size-5 star-rating-icon" data-star-value="{{ $i }}" data-command="{{ $command }}" style="color: {{ $i <= $currentRating ? '#fbbf24' : '#d1d5db' }}; transition: color 0.2s;"></span>
                            </label>
                          @endfor
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
                @endif
                <div class="mt-3">
                  <p class="font-medium mb-2">Notes for {{ $appointment->service->name }}:</p>
                  <div class="mb-2 ms-4">
                    <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the {{ strtolower($appointment->service->name) }}..." id="checkout_service_notes" name="checkout_service_notes" rows="3">{{ $checkout && $checkout->flows && isset($checkout->flows['service_notes']) ? $checkout->flows['service_notes'] : '' }}</textarea>
                  </div>
                </div>
              </div>
              @if (isPrivateTrainingService($appointment->service))
              <hr class="mt-5" style="color: lightgray"/>
              <div class="mt-4 text-sm space-y-4">
                <div class="divider my-4"></div>
                <div>
                  <p class="font-medium mb-2">Current ratings:</p>
                  <div class="mb-2 ms-4">
                    <textarea class="textarea textarea-bordered w-full" id="training_current_ratings" name="training_current_ratings" rows="3" placeholder="Enter current ratings summary...">{{ $checkout && $checkout->flows && isset($checkout->flows['training_current_ratings']) ? $checkout->flows['training_current_ratings'] : '' }}</textarea>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Goal for next lesson:</p>
                  <div class="mb-2 ms-4">
                    <input class="input input-bordered w-full" id="training_targets" name="training_targets" type="text" placeholder="Enter targets for next lesson..." value="{{ $checkout && $checkout->flows && isset($checkout->flows['training_targets']) ? $checkout->flows['training_targets'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Homework for owner:</p>
                  <div class="mb-2 ms-4">
                    <textarea class="textarea textarea-bordered w-full" id="training_homework" name="training_homework" rows="3" placeholder="Enter homework for owner...">{{ $checkout && $checkout->flows && isset($checkout->flows['training_homework']) ? $checkout->flows['training_homework'] : '' }}</textarea>
                  </div>
                </div>
              </div>
              @endif
              <hr class="mt-5" style="color: lightgray"/>
              <div class="mt-4 text-sm space-y-4">
                <div>
                  <p class="font-medium mb-2">Behavior:</p>
                  <div class="mb-2 ms-4 pet-behavior">
                    @php
                      $selectedBehaviorIds = is_array($appointment->pet->pet_behavior_id ?? null)
                        ? collect($appointment->pet->pet_behavior_id)->map(fn ($id) => (string) $id)->toArray()
                        : (!empty($appointment->pet->pet_behavior_id) ? [(string) $appointment->pet->pet_behavior_id] : []);
                    @endphp
                    <select class="select w-full" id="pet_behavior_id" name="checkout_behavior_ids[]" multiple>
                      @foreach($petBehaviors as $behavior)
                        <option
                          value="{{ $behavior->id }}"
                          data-icon-b64="{{ base64_encode($behavior->icon?->icon ?? '') }}"
                          {{ in_array((string) $behavior->id, $selectedBehaviorIds, true) ? 'selected' : '' }}
                        >
                          {{ $behavior->description }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>

  <div class="mt-6 flex justify-end gap-3">
    <div class="flex gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ url()->previous() }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      @if (isBoardingService($appointment->service))
      <button type="button" class="btn btn-sm btn-success gb-blur-background-image" onclick="exportBoardingReportPDF()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
        Export PDF
      </button>
      @endif
      <button type="button" class="btn btn-sm btn-primary" onclick="openConfirmModal()">
        <span class="iconify lucide--check size-4"></span>
        Confirm
      </button>
    </div>
  </div>
</div>
<dialog id="confirm_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Confirm
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <p class="py-4" id="confirm_message">Are you sure to confirm this action?</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost btn-sm">No</button>
      </form>
      <button class="btn btn-primary btn-sm btn-soft" id="confirm_status_button">Confirm</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<dialog id="customer_email_modal" class="modal">
  <div class="modal-box">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium mb-4">Email Customer</h3>
    <div class="space-y-4">
      <fieldset class="fieldset">
        <legend class="fieldset-legend">To</legend>
        <input type="text" class="input input-bordered w-full input-sm" value="{{ $appointment->customer->email ?? '' }}" disabled />
      </fieldset>
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Message</legend>
        <textarea id="customer_email_body" class="textarea textarea-bordered w-full" rows="6" placeholder="Type the email message..."></textarea>
      </fieldset>
    </div>
    <div class="modal-action">
      <button type="button" class="btn btn-sm" onclick="customer_email_modal.close()">Cancel</button>
      <button type="button" id="send_customer_email_btn" class="btn btn-sm btn-primary" onclick="sendCustomerEmail()">
        <span class="loading loading-spinner loading-sm" style="display: none;"></span>
        Send
      </button>
    </div>
  </div>
</dialog>

<dialog id="notify_modal" class="modal">
  <div class="modal-box">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium mb-4">Notify Customer</h3>
    <div class="space-y-4">
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Notification Message</legend>
        <textarea id="notify_body" class="textarea textarea-bordered w-full" rows="6" placeholder="Type the notification message..."></textarea>
      </fieldset>
    </div>
    <div class="modal-action">
      <button type="button" class="btn btn-sm" onclick="notify_modal.close()">Cancel</button>
      <button type="button" id="send_notify_btn" class="btn btn-sm btn-primary" onclick="sendCustomerNotification()">
        <span class="loading loading-spinner loading-sm" style="display: none;"></span>
        Send
      </button>
    </div>
  </div>
</dialog>

<dialog id="payment_modal" class="modal">
  <div class="modal-box">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium mb-4">Payment Information</h3>
    <div class="space-y-4">
      <fieldset class="fieldset">
        <div class="flex items-center gap-4">
          <legend class="fieldset-legend">Amount*</legend>
          <span id="payment_discount_meta" class="flex items-center gap-2" style="display: none;">
            <legend class="fieldset-legend">(<del>${{ number_format($appointment->estimated_price, 2) }}</del>)</legend>
            <span id="invoice_total_tooltip" class="flex tooltip tooltip-info tooltip-bottom cursor-pointer js-click-tooltip" data-tip="">
              <span class="iconify lucide--help-circle text-info size-5"></span>
            </span>
          </span>
        </div>
        <input type="number" id="payment_amount" class="input input-bordered w-full input-sm" step="0.01" min="0" placeholder="0.00" />
      </fieldset>
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Payment Type*</legend>
        <select id="payment_method" class="select w-full input-sm">
          <option value="">Select payment type</option>
          <option value="cash">Cash</option>
          <option value="check">Check</option>
          <option value="cc">Credit Card</option>
        </select>
      </fieldset>
      <fieldset class="fieldset">
        <legend class="fieldset-legend">Notes</legend>
        <textarea id="payment_notes" class="textarea textarea-bordered w-full" rows="3" placeholder="Enter payment notes..."></textarea>
      </fieldset>
    </div>
    <div class="modal-action">
      <button type="button" class="btn btn-sm" onclick="payment_modal.close()">Cancel</button>
      <button type="button" id="confirm_payment_btn" class="btn btn-sm btn-primary" onclick="confirmPayment({{ $appointment->id }})">
        <span class="loading loading-spinner loading-sm" style="display: none;"></span>
        Confirm
      </button>
    </div>
  </div>
</dialog>
@endsection

@section('page-js')
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script>
  const confirm_modal = document.getElementById('confirm_modal');
  const success_modal = document.getElementById('success_modal') || null;
  const alert_modal = document.getElementById('alert_modal') || null;
  const customer_email_modal = document.getElementById('customer_email_modal');
  const notify_modal = document.getElementById('notify_modal');
  const payment_modal = document.getElementById('payment_modal');
  const appointmentDate = "{{ $appointment->date ? \Carbon\Carbon::parse($appointment->date)->format('Y-m-d') : '' }}";
  const appointmentStartTime = "{{ $appointment->start_time ? \Carbon\Carbon::parse($appointment->start_time)->format('H:i:s') : '' }}";
  const LATE_CANCELLATION_MESSAGE = 'This cancellation is within 24 hours of the appointment check-in time. Late cancellations may incur an additional fee. Do you want to continue?';

  function parseAppointmentCheckinDateTime(dateValue, timeValue) {
    if (!dateValue || !timeValue) {
      return null;
    }

    const dateParts = dateValue.split('-').map(Number);
    const timeParts = timeValue.split(':').map(Number);

    if (dateParts.length !== 3 || timeParts.length < 2) {
      return null;
    }

    const [year, month, day] = dateParts;
    const [hour, minute] = timeParts;
    const second = timeParts[2] ?? 0;
    const checkinDateTime = new Date(year, month - 1, day, hour, minute, second);

    if (Number.isNaN(checkinDateTime.getTime())) {
      return null;
    }

    return checkinDateTime;
  }

  function requiresLateCancellationModal(dateValue, timeValue) {
    const checkinDateTime = parseAppointmentCheckinDateTime(dateValue, timeValue);

    if (!checkinDateTime) {
      return true;
    }

    const millisecondsUntilCheckin = checkinDateTime.getTime() - Date.now();
    const twentyFourHoursInMilliseconds = 24 * 60 * 60 * 1000;

    return millisecondsUntilCheckin <= twentyFourHoursInMilliseconds;
  }

  $(document).ready(function() {
    const clickTooltipSelector = '.js-click-tooltip';

    $(document).on('click', clickTooltipSelector, function(e) {
      e.preventDefault();
      e.stopPropagation();

      const tooltip = $(this);
      const isOpen = tooltip.hasClass('tooltip-open');
      $(clickTooltipSelector).removeClass('tooltip-open');

      if (!isOpen) {
        tooltip.addClass('tooltip-open');
      }
    });

    $(document).on('keydown', clickTooltipSelector, function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }

      if (e.key === 'Escape') {
        $(clickTooltipSelector).removeClass('tooltip-open');
      }
    });

    $(document).on('click', function() {
      $(clickTooltipSelector).removeClass('tooltip-open');
    });

    // Save temperament data when any radio button changes
    $('input[name^="initial_greeting"], input[name^="touch_"]').on('change', function() {
      saveTemperamentData();
    });

    // Save grooming process data when any form element changes
    $('input[name^="nail_trimming"], input[name^="ear_cleaning"], input[name^="wetting_sprayer"], input[name^="shampooing"], input[name^="rinsing"], input[name^="drying"], input[name^="brushing_"], input[name^="clippers_"]').on('change', function() {
      saveProcessData();
    });

    // Save daycare evaluation data when any form element changes
    @if (isDaycareService($appointment->service))
    $('#daycare_evaluation_date, input[name="daycare_evaluation_result"], input[name="new_person_evaluation"], input[name="new_dog_evaluation"], input[name="small_group_evaluation"], input[name="large_group_evaluation"], #daycare_evaluation_notes').on('change input', function() {
      saveDaycareEvaluationData();
    });
    @endif

    // Handle training check-in location toggle
    @if (isPrivateTrainingService($appointment->service))
    $('input[name="location"]').on('change', function() {
      const location = $(this).val();
      if (location === 'onsite') {
        $('.onsite-fields').show();
        $('.offsite-fields').hide();
      } else if (location === 'offsite') {
        $('.onsite-fields').hide();
        $('.offsite-fields').show();
      }
      saveTrainingCheckinData();
    });

    // Show/hide fields on page load based on saved location
    const savedLocation = $('input[name="location"]:checked').val();
    if (savedLocation === 'onsite') {
      $('.onsite-fields').show();
    } else if (savedLocation === 'offsite') {
      $('.offsite-fields').show();
    }

    // Save training check-in data when any form element changes
    $('input[name="location"], #additional_services_link, #pickup_datetime_onsite, #location_address, #description_needs, input[name="training_focus"]').on('change', function() {
      saveTrainingCheckinData();
    });
    @endif

    // Auto-save boarding check-in data
    @if (isBoardingService($appointment->service))
    $('#boarding_pickup_datetime, #boarding_trip_location, #boarding_trip_phone, #boarding_alternate_contact_name, #boarding_alternate_contact_phone, #boarding_trip_notes, #boarding_vet_name, #boarding_vet_phone, input[name="boarding_vet_notification"], #boarding_health_status, #boarding_medical_issues, #boarding_flea_tick_treatment, #boarding_pet_notes, #boarding_has_leash, #boarding_has_collar, #boarding_has_other_items, .boarding-other-items-description, .boarding-care-notes, input[name="boarding_location_type"], #boarding_location_details').on('change input', function() {
      saveBoardingCheckinData();
    });

    $('#boarding_agreement_accepted, #boarding_vet_authorized, #boarding_owner_full_name').on('change input', function() {
      $('#boarding_signature_saved_note').addClass('hidden');
      saveBoardingCheckinData();
    });

    $(document).on('change', '#boarding_rest_required', function() {
      const checked = $(this).is(':checked');
      $('#boarding_rest_note').prop('disabled', !checked);
      if (!checked) {
        $('#boarding_rest_note').val('');
      }
      saveBoardingCheckinData();
    });

    $(document).on('change input', '#boarding_rest_note', function() {
      saveBoardingCheckinData();
    });

    $(document).on('click', '#boarding_clear_signature', function() {
      clearBoardingSignaturePad();
      $('#boarding_signature_saved_note').addClass('hidden');
      saveBoardingCheckinData();
    });

    $(document).on('click', '#boarding_save_signature', function() {
      if (!validateBoardingAgreementSignature(true)) {
        return;
      }

      $('#boarding_signature_saved_note').removeClass('hidden');
      saveBoardingCheckinData();
    });

    $(document).on('change input', '.boarding-pet-section .boarding-dry-food-row input', function() {
      saveBoardingCheckinData();
    });

    $(document).on('click', '.add-boarding-dry-food', function() {
      const $section = $(this).closest('.boarding-pet-section');
      addBoardingDryFoodRow($section);
      saveBoardingCheckinData();
    });

    $(document).on('click', '.remove-boarding-dry-food', function() {
      const $row = $(this).closest('.boarding-dry-food-row');
      const $section = $(this).closest('.boarding-pet-section');
      const totalRows = $section.find('.boarding-dry-food-container .boarding-dry-food-row').length;

      if (totalRows <= 1) {
        $row.find('.boarding-dry-food-brand').val('');
        $row.find('.boarding-dry-food-amount').val('');
        $row.find('.boarding-dry-food-dispense-am').prop('checked', false);
        $row.find('.boarding-dry-food-dispense-pm').prop('checked', false);
        $row.find('.boarding-dry-food-dispense-lunch').prop('checked', false);
      } else {
        $row.remove();
        refreshBoardingDryFoodRowLabels($section);
      }

      updateBoardingDryFoodRemoveButtons($section);
      saveBoardingCheckinData();
    });

    $(document).on('change input', '.boarding-pet-section .boarding-wet-food-row input', function() {
      saveBoardingCheckinData();
    });

    $(document).on('click', '.add-boarding-wet-food', function() {
      const $section = $(this).closest('.boarding-pet-section');
      addBoardingWetFoodRow($section);
      saveBoardingCheckinData();
    });

    $(document).on('click', '.remove-boarding-wet-food', function() {
      const $row = $(this).closest('.boarding-wet-food-row');
      const $section = $(this).closest('.boarding-pet-section');
      const totalRows = $section.find('.boarding-wet-food-container .boarding-wet-food-row').length;

      if (totalRows <= 1) {
        $row.find('.boarding-wet-food-brand').val('');
        $row.find('.boarding-wet-food-amount').val('');
        $row.find('.boarding-wet-food-dispense-am').prop('checked', false);
        $row.find('.boarding-wet-food-dispense-pm').prop('checked', false);
        $row.find('.boarding-wet-food-dispense-lunch').prop('checked', false);
      } else {
        $row.remove();
        refreshBoardingWetFoodRowLabels($section);
      }

      updateBoardingWetFoodRemoveButtons($section);
      saveBoardingCheckinData();
    });

    $('.boarding-pet-section').each(function() {
      const $section = $(this);
      refreshBoardingDryFoodRowLabels($section);
      updateBoardingDryFoodRemoveButtons($section);
      refreshBoardingWetFoodRowLabels($section);
      updateBoardingWetFoodRemoveButtons($section);
    });

    $(document).on('change input', '.boarding-pet-section .boarding-med-row input, .boarding-pet-section .boarding-med-row select', function() {
      const $row = $(this).closest('.boarding-med-row');
      toggleBoardingMedicationCustomTime($row);
      saveBoardingCheckinData();
    });

    $(document).on('click', '.add-boarding-medication', function() {
      const $section = $(this).closest('.boarding-pet-section');
      addBoardingMedicationRow($section);
      saveBoardingCheckinData();
    });

    $(document).on('click', '.remove-boarding-medication', function() {
      const $row = $(this).closest('.boarding-med-row');
      const $section = $(this).closest('.boarding-pet-section');
      const totalRows = $section.find('.boarding-meds-container .boarding-med-row').length;

      if (totalRows <= 1) {
        $row.find('.boarding-med-name').val('');
        $row.find('.boarding-med-amount').val('');
        $row.find('.boarding-med-meal-condition').val('');
        $row.find('.boarding-med-dispense-am').prop('checked', false);
        $row.find('.boarding-med-dispense-pm').prop('checked', false);
        $row.find('.boarding-med-dispense-rest').prop('checked', false);
        $row.find('.boarding-med-dispense-before-bed').prop('checked', false);
        $row.find('.boarding-med-dispense-prn').prop('checked', false);
        $row.find('.boarding-med-dispense-custom-time').prop('checked', false);
        $row.find('.boarding-med-custom-time').val('');
        toggleBoardingMedicationCustomTime($row);
      } else {
        $row.remove();
        refreshBoardingMedicationRowLabels($section);
      }

      updateBoardingMedicationRemoveButtons($section);
      saveBoardingCheckinData();
    });

    $('.boarding-pet-section').each(function() {
      const $section = $(this);
      refreshBoardingMedicationRowLabels($section);
      updateBoardingMedicationRemoveButtons($section);
      $section.find('.boarding-meds-container .boarding-med-row').each(function() {
        toggleBoardingMedicationCustomTime($(this));
      });
    });
    initializeBoardingSignaturePad();
    @endif

    @if (isAlaCarteService($appointment->service))
    $(document).on('change', '.ala-carte-date', function() {
      const serviceId = $(this).data('service-id');
      const date = $(this).val();
      if (date && serviceId) {
        loadAlaCarteProcessData({{ $appointment->id }}, serviceId, date);
      }
    });
    @endif

    @if (isBoardingService($appointment->service))
    $('#boarding_workflow_date').on('change', function() {
      loadBoardingWorkflowData($(this).val());
    });

    // Save staff_id when changed
    $('#process_staff_id').on('change', function() {
      saveBoardingWorkflowData();
    });

    // Handle concierge reports radio buttons to enable/disable issue checkboxes
    $('input[name="concierge_nose"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-nose-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-nose-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_eyes"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-eyes-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-eyes-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_ears"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-ears-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-ears-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_mouth"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-mouth-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-mouth-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_body"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-body-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-body-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_paws"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-paws-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-paws-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_abdomen"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-abdomen-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-abdomen-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_rear"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-rear-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-rear-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_digestive"]').on('change', function() {
      if ($(this).val() === 'issue') {
        $('.concierge-digestive-issue-details input[type="checkbox"]').prop('disabled', false);
      } else {
        $('.concierge-digestive-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);
      }
      saveBoardingWorkflowData();
    });
    $('input[name="concierge_other"]').on('change', function() {
      saveBoardingWorkflowData();
    });

    // Add issue button handler - addTreatmentIssue function is defined globally
    $('#add_treatment_issue_btn').on('click', function() {
      addTreatmentIssue();
      saveBoardingWorkflowData();
    });

    $('#boarding_am_meal_prep_time, input[name="boarding_am_meal_preparation"], #boarding_am_meal_foods, #boarding_am_med_prep_time, #boarding_am_med_prep_notes, #boarding_am_meal_dispense_time, #boarding_am_meal_dispense_hand_feed, #boarding_am_meal_dispense_food_aggressive, #boarding_am_meal_dispense_quiet_spot, #boarding_am_meal_dispense_must_eat, #boarding_am_meal_dispense_not_eating, #boarding_am_med_dispense_time, #boarding_am_med_dispense_instructions, #boarding_am_med_dispense_must_receive, #boarding_nose_tail_time, input[name="concierge_nose"], input[name="concierge_eyes"], input[name="concierge_ears"], input[name="concierge_mouth"], input[name="concierge_body"], input[name="concierge_paws"], input[name="concierge_abdomen"], input[name="concierge_rear"], input[name="concierge_digestive"], input[name="concierge_other"], #concierge_nose_discharge, #concierge_nose_dryness, #concierge_nose_cracking, #concierge_eyes_redness, #concierge_eyes_cloudiness, #concierge_eyes_discharge, #concierge_ears_odor, #concierge_ears_redness, #concierge_ears_swelling, #concierge_ears_buildup, #concierge_mouth_tartar, #concierge_mouth_broken_teeth, #concierge_mouth_foul_breath, #concierge_body_lumps, #concierge_body_fleas, #concierge_body_matted, #concierge_paws_debris, #concierge_paws_swelling, #concierge_paws_injury, #concierge_paws_overgrown, #concierge_abdomen_bloating, #concierge_abdomen_tenderness, #concierge_abdomen_rashes, #concierge_rear_irritation, #concierge_rear_swelling, #concierge_digestive_vomit, #concierge_digestive_diarrhea, #concierge_nose_notes, #concierge_eyes_notes, #concierge_ears_notes, #concierge_mouth_notes, #concierge_body_notes, #concierge_paws_notes, #concierge_abdomen_notes, #concierge_rear_notes, #concierge_digestive_notes, #concierge_other_notes, #boarding_nose_tail_treatment, #boarding_rest_1200_time, #boarding_food_med_prep_time, #boarding_pm_meal_prep_time, input[name="boarding_pm_meal_preparation"], #boarding_pm_meal_foods, #boarding_pm_med_prep_time, #boarding_pm_med_prep_notes, #boarding_pm_meal_dispense_time, #boarding_pm_meal_dispense_hand_feed, #boarding_pm_meal_dispense_food_aggressive, #boarding_pm_meal_dispense_quiet_spot, #boarding_pm_meal_dispense_must_eat, #boarding_pm_meal_dispense_not_eating, #boarding_pm_med_dispense_time, #boarding_pm_med_dispense_instructions, #boarding_pm_med_dispense_must_receive, input[id^="boarding_additional_service_"], textarea[id^="boarding_additional_service_"]').on('change input', function() {
      saveBoardingWorkflowData();
    });

    // Initialize treatment issues container with existing data if available
    @if($process && $process->flows && is_array($process->flows))
      @php
        $treatmentIssues = [];
        if (isset($process->flows['treatment_issues']) && is_array($process->flows['treatment_issues'])) {
          $treatmentIssues = $process->flows['treatment_issues'];
        } elseif (isset($process->flows['treatment_issue'])) {
          // Legacy single issue support
          $treatmentIssues = [[
            'issue' => $process->flows['treatment_issue'] ?? '',
            'inhouse' => $process->flows['treatment_inhouse'] ?? '',
            'vet' => $process->flows['treatment_vet'] ?? ''
          ]];
        }
      @endphp
      @if(!empty($treatmentIssues))
        $(document).ready(function() {
          treatmentIssueCounter = 0;
          var initialIssues = @json($treatmentIssues);
          initialIssues.forEach(function(issue) {
            addTreatmentIssue({
              issue: issue.issue || '',
              inhouse: issue.inhouse || '',
              vet: issue.vet || ''
            });
          });
        });
      @endif
    @endif

    const initialDate = $('#boarding_workflow_date').val();
    if (initialDate) {
      loadBoardingWorkflowData(initialDate);
    }
    @endif

    // Initialize Select2 for training additional services
    @if (isPrivateTrainingService($appointment->service))
    $('#additional_services_link').select2({
      placeholder: "Choose additional services (optional)",
      allowClear: true,
      multiple: true,
      width: '100%',
      closeOnSelect: false
    });
    @endif

    function decodeBase64(value) {
      if (!value) {
        return '';
      }

      try {
        return atob(value);
      } catch (error) {
        return '';
      }
    }

    function normalizeIconMarkup(markup) {
      if (!markup) {
        return '';
      }

      let normalized = String(markup).trim();
      if (!normalized) {
        return '';
      }

      if (normalized.includes('&lt;')) {
        normalized = $('<textarea/>').html(normalized).text().trim();
      }

      if (normalized.startsWith('<svg') || normalized.startsWith('<span') || normalized.startsWith('<i')) {
        return normalized;
      }

      if (normalized.includes('iconify')) {
        return '<span class="' + normalized + '"></span>';
      }

      return '';
    }

    function getBehaviorIconMarkup(item) {
      if (!item || !item.id) {
        return '';
      }

      let iconB64 = item.element ? item.element.getAttribute('data-icon-b64') : '';

      if (!iconB64) {
        const optionEl = $('#pet_behavior_id').find('option[value="' + item.id + '"]');
        if (optionEl.length) {
          iconB64 = optionEl.attr('data-icon-b64') || '';
        }
      }

      return normalizeIconMarkup(decodeBase64(iconB64));
    }

    function renderBehaviorOption(item) {
      if (!item.id) {
        return item.text;
      }

      const svg = getBehaviorIconMarkup(item);
      if (!svg) {
        return item.text;
      }

      return $(
        '<span class="behavior-option">' +
          '<span class="behavior-icon">' + svg + '</span>' +
          '<span>' + item.text + '</span>' +
        '</span>'
      );
    }

    function renderBehaviorSelection(item) {
      if (!item.id) {
        return item.text;
      }

      const svg = getBehaviorIconMarkup(item);
      if (!svg) {
        return item.text;
      }

      const safeTitle = $('<div>').text(item.text || '').html();
      return $(
        '<span class="behavior-selection-chip" title="' + safeTitle + '" aria-label="' + safeTitle + '">' +
          '<span class="behavior-selection-icon">' + svg + '</span>' +
        '</span>'
      );
    }

    $('#pet_behavior_id').select2({
      width: '100%',
      placeholder: 'Select behavior',
      closeOnSelect: false,
      templateResult: renderBehaviorOption,
      templateSelection: renderBehaviorSelection,
      escapeMarkup: function(markup) {
        return markup;
      }
    });

    // Totals are initialized later after invoice constants are ready.
    $('#status, #issued_at, #paid_at').on('change', function() {
      updateTotals();
    });

    // Initialize Select2 for inventory items
    $('#inventory_item').select2({
      placeholder: "Choose inventory items",
      ajax: {
        url: '{{ route("get-inventory-items") }}',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            q: params.term // Send the search term as 'q'
          };
        },
        processResults: function (data) {
          return {
            results: data.map(function (item) {
              return {
                id: item.id,
                text: item.brand,
                brand: item.brand,
                description: item.description,
                price: item.wholesale_cost
              };
            })
          };
        }
      },
      templateResult: function (item) {
        if (!item.id) {
          return item.text;
        }
        var $container = $(`
          <div class="flex items-center gap-2">
            <span class="font-medium">${item.brand}</span>
            <span class="text-sm text-base-content/70">(${item.description})</span>
          </div>
        `);
        return $container;
      },
      templateSelection: function (item) {
        return item.text
      }
    })
  });

  function openCustomerEmailModal() {
    $('#customer_email_body').val('');
    customer_email_modal.showModal();
  }

  function sendCustomerEmail() {
    const message = $('#customer_email_body').val().trim();
    if (!message) {
      $('#alert_message').text('Please enter email content before sending.');
      alert_modal.showModal();
      return;
    }

    $('#send_customer_email_btn .loading').css('display', 'inline-block');
    $('#send_customer_email_btn').prop('disabled', true);

    $.ajax({
      url: '{{ route("send-appointment-customer-email", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        message: message
      },
      success: function(response) {
        $('#send_customer_email_btn .loading').css('display', 'none');
        $('#send_customer_email_btn').prop('disabled', false);

        if (response.status) {
          customer_email_modal.close();
          $('#success_message').text(response.message || 'Email sent successfully.');
          success_modal.showModal();
        } else {
          $('#alert_message').text(response.message || 'Failed to send email.');
          alert_modal.showModal();
        }
      },
      error: function(xhr) {
        $('#send_customer_email_btn .loading').css('display', 'none');
        $('#send_customer_email_btn').prop('disabled', false);
        const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send email. Please try again.';
        $('#alert_message').text(message);
        alert_modal.showModal();
      }
    });
  }

  function openNotifyModal() {
    $('#notify_body').val('');
    notify_modal.showModal();
  }

  function sendCustomerNotification() {
    const message = $('#notify_body').val().trim();
    if (!message) {
      $('#alert_message').text('Please enter notification content before sending.');
      alert_modal.showModal();
      return;
    }

    $('#send_notify_btn .loading').css('display', 'inline-block');
    $('#send_notify_btn').prop('disabled', true);

    $.ajax({
      url: '{{ route("send-appointment-customer-notification", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        message: message
      },
      success: function(response) {
        $('#send_notify_btn .loading').css('display', 'none');
        $('#send_notify_btn').prop('disabled', false);

        if (response.status) {
          notify_modal.close();
          $('#success_message').text(response.message || 'Notification sent successfully.');
          success_modal.showModal();
        } else {
          $('#alert_message').text(response.message || 'Failed to send notification.');
          alert_modal.showModal();
        }
      },
      error: function(xhr) {
        $('#send_notify_btn .loading').css('display', 'none');
        $('#send_notify_btn').prop('disabled', false);
        const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send notification. Please try again.';
        $('#alert_message').text(message);
        alert_modal.showModal();
      }
    });
  }

  function openConfirmModal() {
    const selectedStatus = $('#appointment_status').val();
    const currentStatus = '{{ $appointment->status }}';

    if (selectedStatus === 'cancelled') {
      const requiresModal = requiresLateCancellationModal(appointmentDate, appointmentStartTime);

      if (!requiresModal) {
        updateAppointmentStatus(selectedStatus, true);
        return;
      }

      $('#confirm_message').text(LATE_CANCELLATION_MESSAGE);
      confirm_modal.showModal();
      $('#confirm_status_button').off('click').on('click', function() {
        confirm_modal.close();
        updateAppointmentStatus(selectedStatus, true);
      });
      return;
    }

    if (selectedStatus === 'no_show') {
      $('#confirm_message').text('Are you sure you want to mark as no show this appointment?');
      confirm_modal.showModal();
      $('#confirm_status_button').off('click').on('click', function() {
        confirm_modal.close();
        updateAppointmentStatus(selectedStatus, true);
      });
    } else {
      proceedWithConfirm();
    }
  }

  function proceedWithConfirm() {
    $('#confirm_message').text('Are you sure to confirm this action?');
    confirm_modal.showModal();
    // On confirm button click, submit the form
    $('#confirm_modal .btn-primary').off('click').on('click', function() {
      confirm_modal.close();

      // status of appointment
      const appointmentStatus = '{{ $appointment->status }}';

      if (appointmentStatus === 'checked_in') {
        return confirmCheckedIn();
      } else if (appointmentStatus === 'in_progress') {
        return confirmInProgress();
      } else if (appointmentStatus === 'completed') {
        return confirmCompleted();
      } else {
        console.log('No action defined for status:', appointmentStatus);
        alert('No action defined for this appointment status');
      }
    });
  }

  function confirmCheckedIn() {
    // Get check-in form values
    const date = $('#checkin_date').val();
    const startTime = $('#start_time').val();
    const pickupTime = $('#pickup_time').val();
    const notes = $('#notes').val();
    const estimatedPrice = $('#estimated_price').val();
    const parsedEstimatedPrice = parseFloat(estimatedPrice);
    const isBoarding = {{ isBoardingService($appointment->service) ? 'true' : 'false' }};
    const checkinStateTaxRate = parseFloat(@json((float) config('billing.state_tax_rate', 7)));

    if (!estimatedPrice || isNaN(parsedEstimatedPrice)) {
      $('#alert_message').text('Please provide a valid estimated price.');
      alert_modal.showModal();
      return;
    }

    const estimatedPriceForSave = (isBoarding && checkinStateTaxRate > 0)
      ? (parsedEstimatedPrice / (1 + (checkinStateTaxRate / 100)))
      : parsedEstimatedPrice;

    // Validate required fields - pickup_time is not required for boarding services
    if (!date || !startTime || (!isBoarding && !pickupTime)) {
      const requiredFields = isBoarding ? 'Date and Start Time' : 'Date, Start Time and Pickup Time';
      $('#alert_message').text('Please fill in all required fields (' + requiredFields + ').');
      alert_modal.showModal();
      return;
    }

    if (isBoarding && !validateBoardingAgreementSignature(true)) {
      return;
    }

    const runAppointmentValidation = function() {
      $.ajax({
        url: '{{ route("get-validation-info") }}',
        method: 'POST',
        data: {
          pet_id: '{{ $appointment->pet_id }}',
          service_id: '{{ $appointment->service_id }}',
        },
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        dataType: 'json',
        success: function(response) {
          if (response.vaccine_status === 'expired' || !response.vaccine_status) {
            $('#alert_message').text(response.vaccine_message || (response.vaccine_status === 'expired' ? 'Pet vaccination is expired.' : 'Pet vaccination records is not approved.'));
            alert_modal.showModal();
            return;
          }

          submitCheckedInConfirmation(date, startTime, pickupTime, notes, estimatedPriceForSave.toFixed(2));
        },
        error: function() {
          console.error('Failed to validate appointment details.');
          $('#alert_message').text('An error occurred while validating the appointment. Please try again.');
          alert_modal.showModal();
        }
      });
    };

    if (isBoarding) {
      const saveRequest = saveBoardingCheckinData();

      if (saveRequest && typeof saveRequest.done === 'function') {
        saveRequest.done(function() {
          runAppointmentValidation();
        }).fail(function() {
          $('#alert_message').text('Unable to save boarding agreement/signature. Please try again.');
          alert_modal.showModal();
        });
      } else {
        runAppointmentValidation();
      }

      return;
    }

    runAppointmentValidation();
  }

  function submitCheckedInConfirmation(date, startTime, pickupTime, notes, estimatedPrice) {

    // Create the form dynamically
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("confirm-checked-in-appointment", $appointment->id) }}';
    form.style.display = 'none';

    // Add CSRF token
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);
    const staffId = $('#staff_id').val();
    if (staffId) {
      const staffInput = document.createElement('input');
      staffInput.type = 'hidden';
      staffInput.name = 'staff_id';
      staffInput.value = staffId;
      form.appendChild(staffInput);
    }

    const estimatedPriceInput = document.createElement('input');
    estimatedPriceInput.type = 'hidden';
    estimatedPriceInput.name = 'estimated_price';
    estimatedPriceInput.value = estimatedPrice;
    form.appendChild(estimatedPriceInput);

    // Add appointment_id
    const appointmentInput = document.createElement('input');
    appointmentInput.type = 'hidden';
    appointmentInput.name = 'id';
    appointmentInput.value = '{{ $appointment->id }}';
    form.appendChild(appointmentInput);

    // Add check-in fields
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'date';
    dateInput.value = date || '';
    form.appendChild(dateInput);

    const startTimeInput = document.createElement('input');
    startTimeInput.type = 'hidden';
    startTimeInput.name = 'start_time';
    startTimeInput.value = startTime;
    form.appendChild(startTimeInput);

    const pickupTimeInput = document.createElement('input');
    pickupTimeInput.type = 'hidden';
    pickupTimeInput.name = 'pickup_time';
    pickupTimeInput.value = pickupTime || '';
    form.appendChild(pickupTimeInput);

    const notesInput = document.createElement('input');
    notesInput.type = 'hidden';
    notesInput.name = 'notes';
    notesInput.value = notes || '';
    form.appendChild(notesInput);

    // Append and submit
    document.body.appendChild(form);
    form.submit();
  }

  function confirmInProgress() {
    const isAlaCarte = {{ isAlaCarteService($appointment->service) ? 'true' : 'false' }};
    const isBoarding = {{ isBoardingService($appointment->service) ? 'true' : 'false' }};

    // For ala carte services, staff is assigned per secondary service, so skip this validation
    // For boarding services, staff and time fields are optional (daily-based workflow)
    if (!isAlaCarte && !isBoarding) {
      const processStaffId = $('#process_staff_id').val();

      if (!processStaffId) {
        $('#alert_message').text('Please select a staff member before continuing.');
        alert_modal.showModal();
        return;
      }
    }

    // Get process form values - use different fields for group classes
    let date, startTime, pickupTime, notes;

    if (isAlaCarte) {
      // For ala carte services, get values from ala carte fields
      date = $('#ala_carte_process_date').val();
      startTime = $('#ala_carte_process_start_time').val();
      pickupTime = $('#ala_carte_process_pickup_time').val();
      notes = $('#ala_carte_process_notes').val();
    } else {
      // For regular appointments, get values from process fields
      date = $('#process_date').val();
      startTime = $('#process_start_time').val();
      pickupTime = $('#process_pickup_time').val();
      notes = $('#process_notes').val();
    }

    // Validate required fields - date, startTime, and pickupTime are optional for group classes and boarding services
    if (!isAlaCarte && !isBoarding) {
      if (!date || !startTime || !pickupTime) {
        $('#alert_message').text('Please fill in all required fields (Date, Start Time and Pickup Time).');
        alert_modal.showModal();
        return;
      }
    }

    // Create the form dynamically
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("confirm-in-progress-appointment", $appointment->id) }}';
    form.style.display = 'none';

    // Add CSRF token
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    // Add appointment_id
    const appointmentInput = document.createElement('input');
    appointmentInput.type = 'hidden';
    appointmentInput.name = 'id';
    appointmentInput.value = '{{ $appointment->id }}';
    form.appendChild(appointmentInput);

    // Add process fields
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'date';
    dateInput.value = date || '';
    form.appendChild(dateInput);

    const startTimeInput = document.createElement('input');
    startTimeInput.type = 'hidden';
    startTimeInput.name = 'start_time';
    startTimeInput.value = startTime || '';
    form.appendChild(startTimeInput);

    const pickupTimeInput = document.createElement('input');
    pickupTimeInput.type = 'hidden';
    pickupTimeInput.name = 'pickup_time';
    pickupTimeInput.value = pickupTime || '';
    form.appendChild(pickupTimeInput);

    // For ala carte services, staff is assigned per secondary service, so don't send appointment-level staff_id
    if (!isAlaCarte) {
      const processStaffId = $('#process_staff_id').val();
      const staffInput = document.createElement('input');
      staffInput.type = 'hidden';
      staffInput.name = 'staff_id';
      staffInput.value = processStaffId;
      form.appendChild(staffInput);
    }

    const notesInput = document.createElement('input');
    notesInput.type = 'hidden';
    notesInput.name = 'notes';
    notesInput.value = notes || '';
    form.appendChild(notesInput);

    // Append and submit
    document.body.appendChild(form);
    form.submit();
  }

  function saveTemperamentData() {
    // Collect all form data
    const temperamentData = {
      initial_greeting: $('input[name="initial_greeting"]:checked').val(),
      touch_body: $('input[name="touch_body"]:checked').val(),
      touch_legs: $('input[name="touch_legs"]:checked').val(),
      touch_feet: $('input[name="touch_feet"]:checked').val(),
      touch_tail: $('input[name="touch_tail"]:checked').val(),
      touch_face: $('input[name="touch_face"]:checked').val(),
      touch_nails: $('input[name="touch_nails"]:checked').val()
    };

    // Send AJAX request
    $.ajax({
      url: '{{ route("update-checkin-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: temperamentData
      },
      success: function(response) {
        console.log('Temperament data saved successfully');
      },
      error: function(xhr, status, error) {
        console.error('Error saving temperament data:', error);
      }
    });
  }

  function saveProcessData() {
    // Collect all form data
    const processData = {
      nail_trimming: $('input[name="nail_trimming"]:checked').val(),
      ear_cleaning: $('input[name="ear_cleaning"]:checked').val(),
      wetting_sprayer: $('input[name="wetting_sprayer"]:checked').val(),
      shampooing: $('input[name="shampooing"]:checked').val(),
      rinsing: $('input[name="rinsing"]:checked').val(),
      drying: $('input[name="drying"]:checked').val(),
      brushing_body: $('input[name="brushing_body"]').is(':checked'),
      brushing_legs: $('input[name="brushing_legs"]').is(':checked'),
      brushing_feet: $('input[name="brushing_feet"]').is(':checked'),
      brushing_tail: $('input[name="brushing_tail"]').is(':checked'),
      brushing_face: $('input[name="brushing_face"]').is(':checked'),
      clippers_body: $('input[name="clippers_body"]').is(':checked'),
      clippers_legs: $('input[name="clippers_legs"]').is(':checked'),
      clippers_feet: $('input[name="clippers_feet"]').is(':checked'),
      clippers_tail: $('input[name="clippers_tail"]').is(':checked'),
      clippers_face: $('input[name="clippers_face"]').is(':checked')
    };

    // Send AJAX request
    $.ajax({
      url: '{{ route("update-process-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: processData
      },
      success: function(response) {
        console.log('Grooming process data saved successfully');
      },
      error: function(xhr, status, error) {
        console.error('Error saving grooming process data:', error);
      }
    });
  }

  function saveDaycareEvaluationData() {
    const daycareData = {
      daycare_evaluation_date: $('#daycare_evaluation_date').val() || null,
      daycare_evaluation_result: $('input[name="daycare_evaluation_result"]:checked').val() || null,
      new_person_evaluation: $('input[name="new_person_evaluation"]:checked').val() || null,
      new_dog_evaluation: $('input[name="new_dog_evaluation"]:checked').val() || null,
      small_group_evaluation: $('input[name="small_group_evaluation"]:checked').val() || null,
      large_group_evaluation: $('input[name="large_group_evaluation"]:checked').val() || null,
      daycare_evaluation_notes: $('#daycare_evaluation_notes').val() || null
    };

    // Send AJAX request
    $.ajax({
      url: '{{ route("update-process-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: daycareData
      },
      success: function(response) {
        console.log('Daycare evaluation data saved successfully');
      },
      error: function(xhr, status, error) {
        console.error('Error saving daycare evaluation data:', error);
      }
    });
  }

  function saveTrainingCheckinData() {
    const trainingData = {
      location: $('input[name="location"]:checked').val(),
      additional_services_link: $('#additional_services_link').val() || null,
      pickup_datetime_onsite: $('#pickup_datetime_onsite').val() || null,
      location_address: $('#location_address').val() || null,
      description_needs: $('#description_needs').val() || null,
      training_focus: $('input[name="training_focus"]:checked').map(function() {
        return $(this).val();
      }).get()
    };

    // Send AJAX request
    $.ajax({
      url: '{{ route("update-checkin-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: trainingData
      },
      success: function(response) {
        console.log('Training check-in data saved successfully');
      },
      error: function(xhr, status, error) {
        console.error('Error saving training check-in data:', error);
      }
    });
  }

  function saveBoardingCheckinData() {
    const petSpecific = {};
    const allMedications = [];
    const allDryFood = [];
    const allWetFood = [];

    $('.boarding-pet-section').each(function() {
      const $section = $(this);
      const petId = String($section.data('pet-id') || '').trim();
      if (!petId) {
        return;
      }

      const dryFoodList = getBoardingDryFoodData($section);
      const wetFoodList = getBoardingWetFoodData($section);
      const medicationsList = getBoardingMedicationsData($section);

      const firstDryFood = dryFoodList.length > 0 ? dryFoodList[0] : {};
      const firstWetFood = wetFoodList.length > 0 ? wetFoodList[0] : {};
      const firstMedication = medicationsList.length > 0 ? medicationsList[0] : {};

      petSpecific[petId] = {
        other_items_description: ($section.find('.boarding-other-items-description').val() || '').trim() || null,
        care_notes: ($section.find('.boarding-care-notes').val() || '').trim() || null,
        flea_tick_prevention: (($section.find('.boarding-flea-prevention-radio:checked').val() || '').trim() || null),
        flea_tick_prevention_type: (($section.find('.boarding-flea-prevention-type').val() || '').trim() || null),
        dry_food_list: dryFoodList,
        wet_food_list: wetFoodList,
        meds_list: medicationsList,
        dry_food: {
          brand: firstDryFood.brand || null,
          amount: firstDryFood.amount || null,
          dispense_am: dryFoodList.some(item => item.dispense_am === true),
          dispense_pm: dryFoodList.some(item => item.dispense_pm === true),
          dispense_lunch: dryFoodList.some(item => item.dispense_lunch === true)
        },
        wet_food: {
          brand: firstWetFood.brand || null,
          amount: firstWetFood.amount || null,
          dispense_am: wetFoodList.some(item => item.dispense_am === true),
          dispense_pm: wetFoodList.some(item => item.dispense_pm === true),
          dispense_lunch: wetFoodList.some(item => item.dispense_lunch === true)
        },
        meds: {
          name: firstMedication.name || null,
          amount: firstMedication.amount || null,
          dispense_am: medicationsList.some(item => item.dispense_am === true),
          dispense_pm: medicationsList.some(item => item.dispense_pm === true || item.dispense_before_bed === true),
          dispense_rest: medicationsList.some(item => item.dispense_rest === true || item.dispense_before_bed === true),
          dispense_prn: medicationsList.some(item => item.dispense_prn === true)
        }
      };

      allDryFood.push(...dryFoodList);
      allWetFood.push(...wetFoodList);
      allMedications.push(...medicationsList);
    });

    const petSpecificKeys = Object.keys(petSpecific);
    const primaryPetData = petSpecificKeys.length > 0 ? petSpecific[petSpecificKeys[0]] : {
      other_items_description: null,
      care_notes: null,
      dry_food_list: [],
      wet_food_list: [],
      meds_list: [],
      dry_food: {},
      wet_food: {},
      meds: {}
    };

    const medicationsList = allMedications;
    const dryFoodList = allDryFood;
    const wetFoodList = allWetFood;
    const hasAm = medicationsList.some(item => item.dispense_am === true);
    const hasPm = medicationsList.some(item => item.dispense_pm === true || item.dispense_before_bed === true);
    const hasRest = medicationsList.some(item => item.dispense_rest === true || item.dispense_before_bed === true);
    const hasPrn = medicationsList.some(item => item.dispense_prn === true);
    const hasDryAm = dryFoodList.some(item => item.dispense_am === true);
    const hasDryPm = dryFoodList.some(item => item.dispense_pm === true);
    const hasDryLunch = dryFoodList.some(item => item.dispense_lunch === true);
    const hasWetAm = wetFoodList.some(item => item.dispense_am === true);
    const hasWetPm = wetFoodList.some(item => item.dispense_pm === true);
    const hasWetLunch = wetFoodList.some(item => item.dispense_lunch === true);

    const firstMedication = medicationsList.length > 0 ? medicationsList[0] : {};
    const firstDryFood = dryFoodList.length > 0 ? dryFoodList[0] : {};
    const firstWetFood = wetFoodList.length > 0 ? wetFoodList[0] : {};
    const legacyMeds = {
      name: firstMedication.name || null,
      amount: firstMedication.amount || null,
      dispense_am: hasAm,
      dispense_pm: hasPm,
      dispense_rest: hasRest,
      dispense_prn: hasPrn
    };

    const ownerName = ($('#boarding_owner_full_name').val() || '').trim();
    const signatureData = $('#boarding_signature_data').val() || null;
    const restRequired = $('#boarding_rest_required').is(':checked');
    const restNote = ($('#boarding_rest_note').val() || '').trim();

    const boardingData = {
      // Trip Information
      pickup_datetime: $('#boarding_pickup_datetime').val() || null,
      trip_location: $('#boarding_trip_location').val() || null,
      trip_phone: $('#boarding_trip_phone').val() || null,
      alternate_contact_name: $('#boarding_alternate_contact_name').val() || null,
      alternate_contact_phone: $('#boarding_alternate_contact_phone').val() || null,
      trip_notes: $('#boarding_trip_notes').val() || null,

      // Pet Information
      vet_name: $('#boarding_vet_name').val() || null,
      vet_phone: $('#boarding_vet_phone').val() || null,
      vet_notification: $('input[name="boarding_vet_notification"]:checked').val() || null,
      health_status: $('#boarding_health_status').val() || null,
      medical_issues: $('#boarding_medical_issues').val() || null,
      flea_tick_treatment: $('#boarding_flea_tick_treatment').val() || null,
      pet_notes: primaryPetData.care_notes || ($('#boarding_pet_notes').val() || null),
      has_leash: $('#boarding_has_leash').is(':checked'),
      has_collar: $('#boarding_has_collar').is(':checked'),
      has_other_items: $('#boarding_has_other_items').is(':checked'),
      other_items_description: primaryPetData.other_items_description || null,

      dry_food_list: primaryPetData.dry_food_list || [],
      dry_food: {
        brand: (primaryPetData.dry_food || {}).brand || null,
        amount: (primaryPetData.dry_food || {}).amount || null,
        dispense_am: (primaryPetData.dry_food || {}).dispense_am === true,
        dispense_pm: (primaryPetData.dry_food || {}).dispense_pm === true,
        dispense_lunch: (primaryPetData.dry_food || {}).dispense_lunch === true
      },
      wet_food_list: primaryPetData.wet_food_list || [],
      wet_food: {
        brand: (primaryPetData.wet_food || {}).brand || null,
        amount: (primaryPetData.wet_food || {}).amount || null,
        dispense_am: (primaryPetData.wet_food || {}).dispense_am === true,
        dispense_pm: (primaryPetData.wet_food || {}).dispense_pm === true,
        dispense_lunch: (primaryPetData.wet_food || {}).dispense_lunch === true
      },
      meds: (primaryPetData.meds && typeof primaryPetData.meds === 'object') ? primaryPetData.meds : legacyMeds,
      meds_list: primaryPetData.meds_list || [],
      medications: medicationsList.length
        ? medicationsList.map(item => {
            const dispenseLabels = [];
            if (item.dispense_am) dispenseLabels.push('AM');
            if (item.dispense_pm) dispenseLabels.push('PM');
            if (item.dispense_rest) dispenseLabels.push('Rest');
            if (item.dispense_before_bed) dispenseLabels.push('Before Bed');
            if (item.dispense_prn) dispenseLabels.push('PRN');
            if (item.dispense_custom_time && item.custom_time) dispenseLabels.push(`Custom Time (${item.custom_time})`);
            const mealConditionLabel = getMedicationConditionLabel(item.meal_condition);
            const details = [
              item.name || '',
              item.amount || '',
              dispenseLabels.length ? `Dispense: ${dispenseLabels.join(', ')}` : '',
              mealConditionLabel ? `Meal Condition: ${mealConditionLabel}` : ''
            ].filter(Boolean).join(' | ');
            return details;
          }).join('\n')
        : null,
      medications_am: hasAm,
      medications_pm: hasPm,
      feeding_am: hasDryAm || hasWetAm,
      feeding_pm: hasDryPm || hasWetPm,
      feeding_lunch: hasDryLunch || hasWetLunch,
      rest_required: restRequired,
      rest_note: restRequired ? (restNote || null) : null,

      pet_specific: petSpecific,

      boarding_agreement_accepted: $('#boarding_agreement_accepted').is(':checked'),
      boarding_vet_authorized: $('#boarding_vet_authorized').is(':checked'),
      boarding_owner_full_name: ownerName || null,
      boarding_signature_data: signatureData,
      boarding_signature_date: $('#boarding_signature_date').val() || null,

      // Assignment or location
      location_type: $('input[name="boarding_location_type"]:checked').val() || null,
      location_details: $('#boarding_location_details').val() || null
    };

    // Send AJAX request
    return $.ajax({
      url: '{{ route("update-checkin-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: boardingData
      },
      success: function(response) {
        console.log('Boarding check-in data saved successfully');
      },
      error: function(xhr, status, error) {
        console.error('Error saving boarding check-in data:', error);
      }
    });
  }

  function isTruthyBoardingValue(value) {
    return value === true || value === 'true' || value === 1 || value === '1';
  }

  function updateBoardingFleaPreventionTypeVisibility(petId, value) {
    const wrapper = document.querySelector('.boarding-flea-prevention-type-wrapper[data-pet-id="' + petId + '"]');
    const select = wrapper ? wrapper.querySelector('.boarding-flea-prevention-type') : null;
    if (!wrapper || !select) {
      return;
    }

    const showType = value === 'yes';
    wrapper.style.display = showType ? '' : 'none';
    if (!showType) {
      select.value = '';
    }
  }

  document.querySelectorAll('.boarding-flea-prevention-radio:checked').forEach(function(radio) {
    updateBoardingFleaPreventionTypeVisibility(radio.dataset.petId, radio.value);
  });

  document.addEventListener('change', function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('boarding-flea-prevention-radio')) {
      updateBoardingFleaPreventionTypeVisibility(event.target.dataset.petId, event.target.value);
    }
  });

  function validateBoardingAgreementSignature(showErrors = false) {
    const isBoarding = {{ isBoardingService($appointment->service) ? 'true' : 'false' }};
    const isCheckedIn = '{{ $appointment->status }}' === 'checked_in';
    if (!isBoarding || !isCheckedIn) {
      return true;
    }

    const agreementAccepted = $('#boarding_agreement_accepted').is(':checked');
    const vetAuthorized = $('#boarding_vet_authorized').is(':checked');
    const ownerName = ($('#boarding_owner_full_name').val() || '').trim();
    const signatureData = $('#boarding_signature_data').val() || '';

    const errors = [];

    $('#boarding_agreement_accepted').toggleClass('checkbox-error', showErrors && !agreementAccepted);
    $('#boarding_vet_authorized').toggleClass('checkbox-error', showErrors && !vetAuthorized);
    $('#boarding_owner_full_name').toggleClass('input-error', showErrors && !ownerName);
    $('#boarding_signature_error').toggleClass('hidden', !!signatureData);

    if (!agreementAccepted) {
      errors.push('Please confirm the boarding agreement.');
    }

    if (!vetAuthorized) {
      errors.push('Please authorize veterinary treatment if needed.');
    }

    if (!ownerName) {
      errors.push('Please enter the owner full name.');
    }

    if (!signatureData) {
      errors.push('Please provide an owner signature.');
    }

    if (errors.length > 0 && showErrors) {
      $('#alert_message').text(errors[0]);
      alert_modal.showModal();
    }

    return errors.length === 0;
  }

  function initializeBoardingSignaturePad() {
    const $canvas = $('#boarding_signature_pad');
    if ($canvas.length === 0) {
      return;
    }

    const canvas = $canvas[0];
    const context = canvas.getContext('2d');
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#111827';
    context.lineWidth = 2;

    let isDrawing = false;

    function getCanvasPoint(event) {
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / rect.width;
      const scaleY = canvas.height / rect.height;
      return {
        x: (event.clientX - rect.left) * scaleX,
        y: (event.clientY - rect.top) * scaleY,
      };
    }

    function startDrawing(event) {
      isDrawing = true;
      const point = getCanvasPoint(event);
      context.beginPath();
      context.moveTo(point.x, point.y);
      event.preventDefault();
    }

    function draw(event) {
      if (!isDrawing) {
        return;
      }

      const point = getCanvasPoint(event);
      context.lineTo(point.x, point.y);
      context.stroke();
      event.preventDefault();
    }

    function stopDrawing(event) {
      if (!isDrawing) {
        return;
      }

      isDrawing = false;
      context.closePath();
      $('#boarding_signature_data').val(canvas.toDataURL('image/png'));
      $('#boarding_signature_error').addClass('hidden');
      $('#boarding_signature_saved_note').addClass('hidden');
      validateBoardingAgreementSignature(false);

      if (event) {
        event.preventDefault();
      }
    }

    canvas.onpointerdown = startDrawing;
    canvas.onpointermove = draw;
    canvas.onpointerup = stopDrawing;
    canvas.onpointerleave = stopDrawing;
    canvas.onpointercancel = stopDrawing;

    const savedSignature = $('#boarding_signature_data').val();
    if (savedSignature) {
      const image = new Image();
      image.onload = function() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
      };
      image.src = savedSignature;
    }

    validateBoardingAgreementSignature(false);
  }

  function clearBoardingSignaturePad() {
    const canvas = $('#boarding_signature_pad')[0];
    if (!canvas) {
      return;
    }

    const context = canvas.getContext('2d');
    context.clearRect(0, 0, canvas.width, canvas.height);
    $('#boarding_signature_data').val('');
    $('#boarding_signature_error').removeClass('hidden');
    validateBoardingAgreementSignature(false);
  }

  function getMedicationConditionLabel(condition) {
    const labels = {
      after_meal: 'After Meal',
      before_meal: 'Before Meal',
      empty_stomach: 'Empty Stomach'
    };

    return labels[condition] || '';
  }

  function getBoardingDryFoodData($section = null) {
    const foods = [];
    const $root = $section && $section.length ? $section : $(document);

    $root.find('.boarding-dry-food-container .boarding-dry-food-row').each(function() {
      const brand = ($(this).find('.boarding-dry-food-brand').val() || '').trim();
      const amount = ($(this).find('.boarding-dry-food-amount').val() || '').trim();
      const dispenseAm = $(this).find('.boarding-dry-food-dispense-am').is(':checked');
      const dispensePm = $(this).find('.boarding-dry-food-dispense-pm').is(':checked');
      const dispenseLunch = $(this).find('.boarding-dry-food-dispense-lunch').is(':checked');

      if (!brand && !amount && !dispenseAm && !dispensePm && !dispenseLunch) {
        return;
      }

      foods.push({
        brand: brand || null,
        amount: amount || null,
        dispense_am: dispenseAm,
        dispense_pm: dispensePm,
        dispense_lunch: dispenseLunch
      });
    });

    return foods;
  }

  function getBoardingWetFoodData($section = null) {
    const foods = [];
    const $root = $section && $section.length ? $section : $(document);

    $root.find('.boarding-wet-food-container .boarding-wet-food-row').each(function() {
      const brand = ($(this).find('.boarding-wet-food-brand').val() || '').trim();
      const amount = ($(this).find('.boarding-wet-food-amount').val() || '').trim();
      const dispenseAm = $(this).find('.boarding-wet-food-dispense-am').is(':checked');
      const dispensePm = $(this).find('.boarding-wet-food-dispense-pm').is(':checked');
      const dispenseLunch = $(this).find('.boarding-wet-food-dispense-lunch').is(':checked');

      if (!brand && !amount && !dispenseAm && !dispensePm && !dispenseLunch) {
        return;
      }

      foods.push({
        brand: brand || null,
        amount: amount || null,
        dispense_am: dispenseAm,
        dispense_pm: dispensePm,
        dispense_lunch: dispenseLunch
      });
    });

    return foods;
  }

  function refreshBoardingDryFoodRowLabels($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    $root.find('.boarding-dry-food-container .boarding-dry-food-row').each(function(index) {
      $(this).attr('data-row-index', index);
      $(this).find('.text-sm.font-medium').first().text('Dry Food #' + (index + 1));
    });
  }

  function refreshBoardingWetFoodRowLabels($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    $root.find('.boarding-wet-food-container .boarding-wet-food-row').each(function(index) {
      $(this).attr('data-row-index', index);
      $(this).find('.text-sm.font-medium').first().text('Wet Food #' + (index + 1));
    });
  }

  function updateBoardingDryFoodRemoveButtons($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const totalRows = $root.find('.boarding-dry-food-container .boarding-dry-food-row').length;
    const shouldDisable = totalRows <= 1;

    $root.find('.boarding-dry-food-container .remove-boarding-dry-food').prop('disabled', shouldDisable);
    $root.find('.boarding-dry-food-container .remove-boarding-dry-food .iconify').toggleClass('text-base-content/30', shouldDisable);
    $root.find('.boarding-dry-food-container .remove-boarding-dry-food .iconify').toggleClass('text-error', !shouldDisable);
  }

  function updateBoardingWetFoodRemoveButtons($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const totalRows = $root.find('.boarding-wet-food-container .boarding-wet-food-row').length;
    const shouldDisable = totalRows <= 1;

    $root.find('.boarding-wet-food-container .remove-boarding-wet-food').prop('disabled', shouldDisable);
    $root.find('.boarding-wet-food-container .remove-boarding-wet-food .iconify').toggleClass('text-base-content/30', shouldDisable);
    $root.find('.boarding-wet-food-container .remove-boarding-wet-food .iconify').toggleClass('text-error', !shouldDisable);
  }

  function addBoardingDryFoodRow($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const rowIndex = $root.find('.boarding-dry-food-container .boarding-dry-food-row').length;
    const rowHtml = `
      <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-dry-food-row" data-row-index="${rowIndex}">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium">Dry Food #${rowIndex + 1}</p>
          <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-dry-food" title="Remove dry food">
            <span class="iconify lucide--x size-4 text-error"></span>
          </button>
        </div>
        <div>
          <p class="text-sm mb-1">Brand:</p>
          <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-brand" placeholder="Enter dry food brand" />
        </div>
        <div class="flex items-end gap-4">
          <div class="flex-1">
            <p class="text-sm mb-1">Amount:</p>
            <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-amount" placeholder="e.g., 1 cup, 1/2 cup" />
          </div>
          <div class="flex-1 pb-2">
            <p class="text-sm mb-1">Dispense:</p>
            <div class="flex items-center gap-3 flex-wrap">
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-am" />
                <span class="text-sm">AM</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-pm" />
                <span class="text-sm">PM</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-lunch" />
                <span class="text-sm">Lunch</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    `;

    $root.find('.boarding-dry-food-container').first().append(rowHtml);
    refreshBoardingDryFoodRowLabels($root);
    updateBoardingDryFoodRemoveButtons($root);
  }

  function addBoardingWetFoodRow($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const rowIndex = $root.find('.boarding-wet-food-container .boarding-wet-food-row').length;
    const rowHtml = `
      <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-wet-food-row" data-row-index="${rowIndex}">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium">Wet Food #${rowIndex + 1}</p>
          <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-wet-food" title="Remove wet food">
            <span class="iconify lucide--x size-4 text-error"></span>
          </button>
        </div>
        <div>
          <p class="text-sm mb-1">Brand:</p>
          <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-brand" placeholder="Enter wet food brand" />
        </div>
        <div class="flex items-end gap-4">
          <div class="flex-1">
            <p class="text-sm mb-1">Amount:</p>
            <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-amount" placeholder="e.g., 2 Tbsp, 1 container" />
          </div>
          <div class="flex-1 pb-2">
            <p class="text-sm mb-1">Dispense:</p>
            <div class="flex items-center gap-3 flex-wrap">
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-am" />
                <span class="text-sm">AM</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-pm" />
                <span class="text-sm">PM</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-lunch" />
                <span class="text-sm">Lunch</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    `;

    $root.find('.boarding-wet-food-container').first().append(rowHtml);
    refreshBoardingWetFoodRowLabels($root);
    updateBoardingWetFoodRemoveButtons($root);
  }

  function getBoardingMedicationsData($section = null) {
    const medications = [];
    const $root = $section && $section.length ? $section : $(document);

    $root.find('.boarding-meds-container .boarding-med-row').each(function() {
      const name = ($(this).find('.boarding-med-name').val() || '').trim();
      const amount = ($(this).find('.boarding-med-amount').val() || '').trim();
      const mealCondition = ($(this).find('.boarding-med-meal-condition').val() || '').trim();
      const dispenseAm = $(this).find('.boarding-med-dispense-am').is(':checked');
      const dispensePm = $(this).find('.boarding-med-dispense-pm').is(':checked');
      const dispenseRest = $(this).find('.boarding-med-dispense-rest').is(':checked');
      const dispenseBeforeBed = $(this).find('.boarding-med-dispense-before-bed').is(':checked');
      const dispensePrn = $(this).find('.boarding-med-dispense-prn').is(':checked');
      const dispenseCustomTime = $(this).find('.boarding-med-dispense-custom-time').is(':checked');
      const customTime = ($(this).find('.boarding-med-custom-time').val() || '').trim();

      if (!name && !amount && !mealCondition && !dispenseAm && !dispensePm && !dispenseRest && !dispenseBeforeBed && !dispensePrn && !dispenseCustomTime && !customTime) {
        return;
      }

      medications.push({
        name: name || null,
        amount: amount || null,
        dispense_am: dispenseAm,
        dispense_pm: dispensePm,
        dispense_rest: dispenseRest,
        dispense_before_bed: dispenseBeforeBed,
        dispense_prn: dispensePrn,
        dispense_custom_time: dispenseCustomTime,
        meal_condition: mealCondition || null,
        custom_time: dispenseCustomTime ? (customTime || null) : null,
      });
    });

    return medications;
  }

  function toggleBoardingMedicationCustomTime($row) {
    const shouldShow = $row.find('.boarding-med-dispense-custom-time').is(':checked');
    const $timeWrap = $row.find('.boarding-med-custom-time-wrap');
    const $timeInput = $row.find('.boarding-med-custom-time');

    if (shouldShow) {
      $timeWrap.removeClass('hidden');
    } else {
      $timeWrap.addClass('hidden');
      $timeInput.val('');
    }
  }

  function refreshBoardingMedicationRowLabels($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    $root.find('.boarding-meds-container .boarding-med-row').each(function(index) {
      $(this).attr('data-row-index', index);
      $(this).find('.text-sm.font-medium').first().text('Medication #' + (index + 1));
    });
  }

  function updateBoardingMedicationRemoveButtons($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const totalRows = $root.find('.boarding-meds-container .boarding-med-row').length;
    const shouldDisable = totalRows <= 1;

    $root.find('.boarding-meds-container .remove-boarding-medication').prop('disabled', shouldDisable);
    $root.find('.boarding-meds-container .remove-boarding-medication .iconify').toggleClass('text-base-content/30', shouldDisable);
    $root.find('.boarding-meds-container .remove-boarding-medication .iconify').toggleClass('text-error', !shouldDisable);
  }

  function addBoardingMedicationRow($section = null) {
    const $root = $section && $section.length ? $section : $(document);
    const rowIndex = $root.find('.boarding-meds-container .boarding-med-row').length;
    const rowHtml = `
      <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-med-row" data-row-index="${rowIndex}">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium">Medication #${rowIndex + 1}</p>
          <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-medication" title="Remove medication">
            <span class="iconify lucide--x size-4 text-error"></span>
          </button>
        </div>
        <div>
          <p class="text-sm mb-1">Medication Name:</p>
          <input type="text" class="input input-bordered w-full input-sm boarding-med-name" placeholder="Enter medication name" />
        </div>
        <div>
          <p class="text-sm mb-1">Dosage/Instruction:</p>
          <input type="text" class="input input-bordered w-full input-sm boarding-med-amount" placeholder="e.g., 1 pill, 2 drops left ear" />
        </div>
        <div>
          <p class="text-sm mb-1">Dispense:</p>
          <div class="flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-am" />
              <span class="text-sm">AM</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-pm" />
              <span class="text-sm">PM</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-rest" />
              <span class="text-sm">Rest</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-before-bed" />
              <span class="text-sm">Before Bed</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-prn" />
              <span class="text-sm">PRN</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-custom-time" />
              <span class="text-sm">Custom Time</span>
            </label>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <p class="text-sm mb-1">Meal Condition:</p>
            <select class="select select-bordered w-full select-sm boarding-med-meal-condition">
              <option value="">Select option</option>
              <option value="after_meal">After Meal</option>
              <option value="before_meal">Before Meal</option>
              <option value="empty_stomach">Empty Stomach</option>
            </select>
          </div>
          <div class="boarding-med-custom-time-wrap hidden">
            <p class="text-sm mb-1">Custom Time:</p>
            <input type="time" class="input input-bordered w-full input-sm boarding-med-custom-time" />
          </div>
        </div>
      </div>
    `;

    $root.find('.boarding-meds-container').first().append(rowHtml);
    refreshBoardingMedicationRowLabels($root);
    updateBoardingMedicationRemoveButtons($root);
  }

  // Treatment issues management
  let treatmentIssueCounter = 0;
  const treatmentMappings = {};

  function addTreatmentIssue(issueData = null) {
    const issueId = treatmentIssueCounter++;
    const issueHtml = `
      <div class="p-3 treatment-issue-item" data-issue-id="${issueId}">
        <div class="flex items-center justify-between mb-2">
          <p class="font-medium text-sm">Issue #${issueId + 1}</p>
          <button type="button" class="btn btn-xs btn-ghost btn-circle remove-treatment-issue" data-issue-id="${issueId}">
            <span class="iconify lucide--x size-4 font-medium"></span>
          </button>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-sm font-medium mb-1 block">Issue:</label>
            <select class="select select-bordered select-sm w-full treatment-issue-select" data-issue-id="${issueId}">
              <option value="">Select an issue...</option>
              <option value="diarrhea" ${issueData && issueData.issue === 'diarrhea' ? 'selected' : ''}>Diarrhea</option>
              <option value="eye_discharge" ${issueData && issueData.issue === 'eye_discharge' ? 'selected' : ''}>Eye discharge</option>
              <option value="ear_discharge" ${issueData && issueData.issue === 'ear_discharge' ? 'selected' : ''}>Ear discharge</option>
              <option value="vomit" ${issueData && issueData.issue === 'vomit' ? 'selected' : ''}>Vomit</option>
              <option value="hot_spot" ${issueData && issueData.issue === 'hot_spot' ? 'selected' : ''}>Hot spot</option>
              <option value="puncture" ${issueData && issueData.issue === 'puncture' ? 'selected' : ''}>Puncture</option>
              <option value="laceration" ${issueData && issueData.issue === 'laceration' ? 'selected' : ''}>Laceration</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium mb-1 block">In-house treatment:</label>
            <input type="text" class="input input-bordered input-sm w-full treatment-issue-inhouse"
              data-issue-id="${issueId}"
              placeholder="In-house treatment"
              value="${issueData && issueData.inhouse ? issueData.inhouse : ''}" />
          </div>
          <div>
            <label class="text-sm font-medium mb-1 block">Vet treatment:</label>
            <input type="text" class="input input-bordered input-sm w-full treatment-issue-vet"
              data-issue-id="${issueId}"
              placeholder="Vet treatment"
              value="${issueData && issueData.vet ? issueData.vet : ''}" />
          </div>
        </div>
      </div>
    `;
    $('#treatment_issues_container').append(issueHtml);

    // Auto-populate treatment fields when issue is selected
    $(`.treatment-issue-select[data-issue-id="${issueId}"]`).on('change', function() {
      const selectedIssue = $(this).val();
      const issueId = $(this).data('issue-id');
      if (selectedIssue && treatmentMappings[selectedIssue]) {
        $(`.treatment-issue-inhouse[data-issue-id="${issueId}"]`).val(treatmentMappings[selectedIssue].inhouse);
        $(`.treatment-issue-vet[data-issue-id="${issueId}"]`).val(treatmentMappings[selectedIssue].vet);
      }
      saveBoardingWorkflowData();
    });

    // Save on input change
    $(`.treatment-issue-inhouse[data-issue-id="${issueId}"], .treatment-issue-vet[data-issue-id="${issueId}"]`).on('input', function() {
      saveBoardingWorkflowData();
    });

    // Remove issue handler
    $(`.remove-treatment-issue[data-issue-id="${issueId}"]`).on('click', function() {
      $(`.treatment-issue-item[data-issue-id="${issueId}"]`).remove();
      // Update issue numbers
      $('.treatment-issue-item').each(function(index) {
        $(this).find('.font-medium').text('Issue #' + (index + 1));
      });
      saveBoardingWorkflowData();
    });
  }

  // Helper function to format time
  function formatTimeDisplay(time) {
    if (!time) return 'N/A';
    try {
      // Try H:i:s format first
      const parts = time.split(':');
      if (parts.length >= 2) {
        let hours = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        return hours + ':' + minutes + ' ' + ampm;
      }
      return time;
    } catch (e) {
      return time || 'N/A';
    }
  }

  // Helper function to get radio value label
  function getRadioLabel(value, options) {
    return options[value] || 'N/A';
  }

  // Helper function to get checked items
  function getCheckedItemsDisplay(workflowData, keys) {
    const checked = [];
    for (const [key, label] of Object.entries(keys)) {
      if (workflowData[key] === true || workflowData[key] === 'true') {
        checked.push(label);
      }
    }
    return checked.length > 0 ? checked.join(', ') : 'N/A';
  }

  function loadBoardingWorkflowData(date) {
    if (!date) {
      $('#boarding_workflow_steps_container [data-step-id]').each(function() {
        $(this).find('[data-display="time"]').text('—');
        $(this).find('[data-display="employee"]').text('');
        $(this).find('[data-display="detail"]').text('');
      });
      $('[id^="display_"]').text('N/A');
      return;
    }

    $.ajax({
      url: '{{ route("get-process-flows", $appointment->id) }}',
      method: 'GET',
      data: {
        date: date
      },
      success: function(response) {
        const workflowData = response.flows || {};
        const hasData = workflowData && Object.keys(workflowData).length > 0;

        // Show/hide content based on whether data exists
        if (hasData) {
          $('#boarding_no_record_message').addClass('hidden');
          $('#boarding_workflow_content').show();
        } else {
          $('#boarding_no_record_message').removeClass('hidden');
          $('#boarding_workflow_content').hide();
        }

        // Update staff name
        if (response.staff_name) {
          $('#boarding_assigned_staff').text(response.staff_name);
          } else {
          $('#boarding_assigned_staff').text('N/A');
        }

        // If no data, stop here
        if (!hasData) {
          console.log('No workflow data found for date:', date);
          return;
        }

        // Daily process steps: boarding process log uses step keys (e.g. food_prep_am, reports_am). Each step has process_time, staff_sign_off, selected_pet_ids (appointment IDs).
        // If this appointment's pet was not involved in a step (not in selected_pet_ids when present), do not show time/status/details.
        const currentAppointmentId = parseInt($('#boarding_workflow_steps_container').data('appointment-id'), 10) || null;
        const aidStr = currentAppointmentId != null ? String(currentAppointmentId) : '';
        const isNewFlowFormat = workflowData && (typeof workflowData.food_prep_am === 'object' || typeof workflowData.check_pet === 'object' || typeof workflowData.reports_am === 'object');
        const legacyTimeKeys = {
          food_prep_am: 'am_meal_prep_time',
          meds_prep_am: 'am_med_prep_time',
          feeding_am: 'am_meal_dispense_time',
          meds_dispense_am: 'am_med_dispense_time',
          check_pet: 'nose_tail_time',
          lunch_tlr: 'rest_1200_time',
          food_prep_pm: 'pm_meal_prep_time',
          meds_prep_pm: 'pm_med_prep_time',
          feeding_pm: 'pm_meal_dispense_time',
          meds_dispense_pm: 'pm_med_dispense_time'
        };

        function isPetInvolvedInStep(stepData) {
          if (!stepData || typeof stepData !== 'object') return false;
          if ($('#boarding_workflow_steps_container').length && currentAppointmentId && stepData.process_type === 'rest_tlr') {
            const ids = Array.isArray(stepData.selected_pet_ids)
              ? stepData.selected_pet_ids.map(function(id) { return parseInt(id, 10); }).filter(function(id) { return !isNaN(id); })
              : [];
            const idMatch = ids.indexOf(currentAppointmentId) !== -1;
            if (ids.length > 0) {
              const treatmentPlanData = workflowData['treatment_plan'] && workflowData['treatment_plan'].treatment_data
                ? (workflowData['treatment_plan'].treatment_data[aidStr] || workflowData['treatment_plan'].treatment_data[currentAppointmentId])
                : null;
              if (treatmentPlanData && typeof treatmentPlanData === 'object') {
                const hasAssignedRest = treatmentPlanData.assign_rest === true || treatmentPlanData.assign_rest === 'true' || treatmentPlanData.assign_rest === 1 || treatmentPlanData.assign_rest === '1';
                if (hasAssignedRest) return true;
              }
              return idMatch;
            }
            const treatmentPlanData = workflowData['treatment_plan'] && workflowData['treatment_plan'].treatment_data
              ? (workflowData['treatment_plan'].treatment_data[aidStr] || workflowData['treatment_plan'].treatment_data[currentAppointmentId])
              : null;
            if (!treatmentPlanData || typeof treatmentPlanData !== 'object') return false;
            return treatmentPlanData.assign_rest === true || treatmentPlanData.assign_rest === 'true' || treatmentPlanData.assign_rest === 1 || treatmentPlanData.assign_rest === '1';
          }
          if (!stepData.selected_pet_ids || !Array.isArray(stepData.selected_pet_ids) || stepData.selected_pet_ids.length === 0) return true;
          if (!currentAppointmentId) return true;
          const ids = stepData.selected_pet_ids.map(function(id) { return parseInt(id, 10); }).filter(function(id) { return !isNaN(id); });
          return ids.indexOf(currentAppointmentId) !== -1;
        }

        function getStepDetailForAppointment(stepId, stepData) {
          if (!stepData || !aidStr) return '';
          // Reports (AM/PM): single key — show value only (no "Issue:" label)
          const issuesVal = stepData.issues && (stepData.issues[aidStr] != null ? stepData.issues[aidStr] : stepData.issues[currentAppointmentId]);
          if ((stepId === 'reports_am' || stepId === 'reports_pm') && issuesVal !== undefined && issuesVal !== null && String(issuesVal).trim() !== '') {
            return String(issuesVal).trim();
          }
          const checkData = stepData.check_data && (stepData.check_data[aidStr] || stepData.check_data[currentAppointmentId]);
          if (stepId === 'check_pet' && checkData && typeof checkData === 'object') {
            const parts = [];
            ['nose', 'ears', 'eyes', 'mouth', 'body/coat', 'paws/feet', 'abdomen', 'digestive', 'diarrhea'].forEach(function(k) {
              const keyAlt = k.replace('/', '\\/');
              const section = checkData[k] || checkData[keyAlt];
              const s = section && (section.status || section);
              if (s && s !== 'okay') parts.push(k);
            });
            if (parts.length === 1) return parts[0];
            if (parts.length > 1) return parts.join(', ');
            return 'Okay';
          }
          // Treatment Plan: two keys keep labels; single key show value only
          const treatmentData = stepData.treatment_data && (stepData.treatment_data[aidStr] || stepData.treatment_data[currentAppointmentId]);
          if (stepId === 'treatment_plan' && treatmentData && typeof treatmentData === 'object') {
            const opt = treatmentData.option || '';
            const optLabel = opt === 'in-house' ? 'In-house' : opt === 'vet-watch' ? 'Vet watch' : opt || '';
            const treatmentArr = Array.isArray(treatmentData.additional_options) ? treatmentData.additional_options : [];
            const treatmentText = treatmentArr.length > 0 ? treatmentArr.join(', ') : (treatmentData.additional_option || '').toString().trim();
            const det = (treatmentData.detail || '').trim();
            const parts = [];
            if (optLabel) parts.push('Option: ' + optLabel);
            if (treatmentText) parts.push('Treatment: ' + treatmentText);
            if (det) parts.push('Detail: ' + det);
            if (parts.length > 1) return parts.join(', ');
            if (parts.length === 1) return parts[0].replace(/^Option:\s*/, '').replace(/^Treatment:\s*/, '').replace(/^Detail:\s*/, '');
            return '';
          }
          if (stepId === 'rest_tlr') {
            const restSource = workflowData['treatment_plan'] && workflowData['treatment_plan'].treatment_data
              ? (workflowData['treatment_plan'].treatment_data[aidStr] || workflowData['treatment_plan'].treatment_data[currentAppointmentId])
              : null;
            const restDetailFromPlan = (restSource && typeof restSource === 'object')
              ? ((restSource.rest_detail || restSource.rest_details || restSource.detail_rest || '') + '').trim()
              : '';
            if (restDetailFromPlan) return restDetailFromPlan;
            const restNotes = stepData && stepData.notes ? stepData.notes : {};
            return ((restNotes[aidStr] || restNotes[currentAppointmentId] || '') + '').trim();
          }
          const completed = stepData.completed_treatments && (stepData.completed_treatments[aidStr] != null ? stepData.completed_treatments[aidStr] : stepData.completed_treatments[currentAppointmentId]);
          if (stepId === 'treatment_list' && completed !== undefined) return completed === true || completed === 'true' ? 'Completed' : 'Not completed';
          // Treatments (TLR): two keys keep labels; single key show value only
          const result = stepData.results && (stepData.results[aidStr] || stepData.results[currentAppointmentId]);
          if (stepId === 'treatments_tlr' && result && typeof result === 'object') {
            const r = (result.result || '').toLowerCase();
            const resultLabel = r === 'continue' ? 'Continue' : r === 'resolved' ? 'Resolved' : r === 'escalate' ? 'Escalate' : r || '';
            const d = (result.detail || '').trim();
            if (resultLabel && d) return 'Result: ' + resultLabel + ', Detail: ' + d;
            if (resultLabel) return resultLabel;
            return d || '';
          }
          const reported = stepData.reported && (stepData.reported[aidStr] != null ? stepData.reported[aidStr] : stepData.reported[currentAppointmentId]);
          if (stepId === 'treatment_list_tlr' && reported) return reported;
          const vetVisit = stepData.vet_visit && (stepData.vet_visit[aidStr] != null ? stepData.vet_visit[aidStr] : stepData.vet_visit[currentAppointmentId]);
          if (stepId === 'next_day_treatment_list_tlr' && vetVisit !== undefined) return vetVisit === true || vetVisit === 'true' ? 'Yes' : 'No';
          return '';
        }

        const staffNames = response.staff_names || {};
        function getEmployeeDisplay(stepData) {
          if (!stepData || !stepData.staff_sign_off || !Array.isArray(stepData.staff_sign_off) || stepData.staff_sign_off.length === 0) return '';
          const names = stepData.staff_sign_off.map(function(uid) {
            const key = String(uid);
            return staffNames[key] || staffNames[parseInt(key, 10)] || key;
          }).filter(Boolean);
          return names.join(', ');
        }

        $('#boarding_workflow_steps_container [data-step-id]').each(function() {
          const stepId = $(this).data('step-id');
          const $row = $(this);
          const stepData = workflowData[stepId];
          const stepAvailable = isNewFlowFormat && stepData && typeof stepData === 'object';
          const involved = isPetInvolvedInStep(stepData);
          const restDetail = stepId === 'rest_tlr' ? getStepDetailForAppointment(stepId, stepData) : '';
          let timeStr = '—';
          let employeeStr = '';
          let detailStr = '';
          if (stepAvailable) {
            const t = stepData.process_time || stepData.processTime;
            timeStr = (stepId === 'rest_tlr' && !involved) ? '—' : (t ? formatTimeDisplay(t) : '—');
            employeeStr = (stepId === 'rest_tlr' && !involved) ? '' : getEmployeeDisplay(stepData);
            detailStr = stepId === 'rest_tlr' ? (involved ? restDetail : '') : (involved ? getStepDetailForAppointment(stepId, stepData) : '');
          } else if (legacyTimeKeys[stepId] && workflowData[legacyTimeKeys[stepId]]) {
            timeStr = (stepId === 'rest_tlr' && !involved) ? '—' : formatTimeDisplay(workflowData[legacyTimeKeys[stepId]]);
            employeeStr = (stepId === 'rest_tlr' && !involved) ? '' : (response.staff_name || '');
            detailStr = stepId === 'rest_tlr' ? (involved ? restDetail : '') : (involved ? getStepDetailForAppointment(stepId, stepData) : '');
          }
          $row.find('[data-display="time"]').text(timeStr);
          $row.find('[data-display="employee"]').text(employeeStr);
          $row.find('[data-display="detail"]').text(detailStr);
        });

        console.log('Boarding workflow data loaded for date:', date);
      },
      error: function(xhr, status, error) {
        console.error('Error loading boarding workflow data:', error);
        $('#boarding_workflow_steps_container [data-step-id]').each(function() {
          $(this).find('[data-display="time"]').text('—');
          $(this).find('[data-display="employee"]').text('');
          $(this).find('[data-display="detail"]').text('');
        });
        $('[id^="display_"]').text('N/A');
        $('#boarding_no_record_message').removeClass('hidden');
        $('#boarding_workflow_content').hide();
      }
    });
  }

  function clearBoardingWorkflowFields() {
    // Clear all time inputs
    $('#boarding_am_meal_prep_time, #boarding_am_med_prep_time, #boarding_am_meal_dispense_time, #boarding_am_med_dispense_time, #boarding_nose_tail_time, #boarding_rest_1200_time, #boarding_pm_meal_prep_time, #boarding_pm_med_prep_time, #boarding_pm_meal_dispense_time, #boarding_pm_med_dispense_time').val('');

    // Clear all checkboxes
    $('#boarding_am_meal_dispense_hand_feed, #boarding_am_meal_dispense_food_aggressive, #boarding_am_meal_dispense_quiet_spot, #boarding_am_meal_dispense_not_eating, #boarding_am_med_dispense_must_receive, #boarding_pm_meal_dispense_hand_feed, #boarding_pm_meal_dispense_food_aggressive, #boarding_pm_meal_dispense_quiet_spot, #boarding_pm_meal_dispense_not_eating, #boarding_pm_med_dispense_must_receive').prop('checked', false);

    // Clear radio buttons
    $('input[name="boarding_am_meal_preparation"], input[name="boarding_pm_meal_preparation"]').prop('checked', false);

    // Clear radio buttons
    $('input[name="concierge_nose"], input[name="concierge_eyes"], input[name="concierge_ears"], input[name="concierge_mouth"], input[name="concierge_body"], input[name="concierge_paws"], input[name="concierge_abdomen"], input[name="concierge_rear"], input[name="concierge_digestive"], input[name="concierge_other"]').prop('checked', false);
    $('.concierge-nose-issue-details input[type="checkbox"], .concierge-eyes-issue-details input[type="checkbox"], .concierge-ears-issue-details input[type="checkbox"], .concierge-mouth-issue-details input[type="checkbox"], .concierge-body-issue-details input[type="checkbox"], .concierge-paws-issue-details input[type="checkbox"], .concierge-abdomen-issue-details input[type="checkbox"], .concierge-rear-issue-details input[type="checkbox"], .concierge-digestive-issue-details input[type="checkbox"]').prop('disabled', true).prop('checked', false);

    // Clear concierge checkboxes
    $('#concierge_nose_discharge, #concierge_nose_dryness, #concierge_nose_cracking, #concierge_eyes_redness, #concierge_eyes_cloudiness, #concierge_eyes_discharge, #concierge_ears_odor, #concierge_ears_redness, #concierge_ears_swelling, #concierge_ears_buildup, #concierge_mouth_tartar, #concierge_mouth_broken_teeth, #concierge_mouth_foul_breath, #concierge_body_lumps, #concierge_body_fleas, #concierge_body_matted, #concierge_paws_debris, #concierge_paws_swelling, #concierge_paws_injury, #concierge_paws_overgrown, #concierge_abdomen_bloating, #concierge_abdomen_tenderness, #concierge_abdomen_rashes, #concierge_rear_irritation, #concierge_rear_swelling, #concierge_digestive_vomit, #concierge_digestive_diarrhea').prop('checked', false);

    // Clear treatment issues
    $('#treatment_issues_container').empty();
    treatmentIssueCounter = 0;

    // Clear textareas and inputs
    $('#boarding_am_meal_foods, #boarding_am_med_prep_notes, #boarding_am_meal_dispense_must_eat, #boarding_am_med_dispense_instructions, #concierge_nose_notes, #concierge_eyes_notes, #concierge_ears_notes, #concierge_mouth_notes, #concierge_body_notes, #concierge_paws_notes, #concierge_abdomen_notes, #concierge_rear_notes, #concierge_digestive_notes, #concierge_other_notes, #boarding_nose_tail_treatment, #boarding_pm_meal_foods, #boarding_pm_med_prep_notes, #boarding_pm_meal_dispense_must_eat, #boarding_pm_med_dispense_instructions').val('');

  }

  function saveBoardingWorkflowData() {
    const selectedDate = $('#boarding_workflow_date').val();
    if (!selectedDate) {
      console.warn('No date selected for workflow data');
      return;
    }

    const workflowData = {
      am_meal_prep_time: $('#boarding_am_meal_prep_time').val() || null,
      am_med_prep_time: $('#boarding_am_med_prep_time').val() || null,
      nose_tail_time: $('#boarding_nose_tail_time').val() || null,
      rest_1200_time: $('#boarding_rest_1200_time').val() || null,
      am_meal_preparation: $('input[name="boarding_am_meal_preparation"]:checked').val() || null,
      am_meal_foods: $('#boarding_am_meal_foods').val() || null,
      am_med_prep_notes: $('#boarding_am_med_prep_notes').val() || null,
      am_meal_dispense_time: $('#boarding_am_meal_dispense_time').val() || null,
      am_meal_dispense_hand_feed: $('#boarding_am_meal_dispense_hand_feed').is(':checked'),
      am_meal_dispense_food_aggressive: $('#boarding_am_meal_dispense_food_aggressive').is(':checked'),
      am_meal_dispense_quiet_spot: $('#boarding_am_meal_dispense_quiet_spot').is(':checked'),
      am_meal_dispense_must_eat: $('#boarding_am_meal_dispense_must_eat').val() || null,
      am_meal_dispense_not_eating: $('#boarding_am_meal_dispense_not_eating').is(':checked'),
      am_med_dispense_time: $('#boarding_am_med_dispense_time').val() || null,
      am_med_dispense_instructions: $('#boarding_am_med_dispense_instructions').val() || null,
      am_med_dispense_must_receive: $('#boarding_am_med_dispense_must_receive').is(':checked'),

      // Concierge reports
      concierge_nose: $('input[name="concierge_nose"]:checked').val() || null,
      concierge_nose_discharge: $('#concierge_nose_discharge').is(':checked'),
      concierge_nose_dryness: $('#concierge_nose_dryness').is(':checked'),
      concierge_nose_cracking: $('#concierge_nose_cracking').is(':checked'),
      concierge_nose_notes: $('#concierge_nose_notes').val() || null,
      concierge_eyes: $('input[name="concierge_eyes"]:checked').val() || null,
      concierge_eyes_redness: $('#concierge_eyes_redness').is(':checked'),
      concierge_eyes_cloudiness: $('#concierge_eyes_cloudiness').is(':checked'),
      concierge_eyes_discharge: $('#concierge_eyes_discharge').is(':checked'),
      concierge_eyes_notes: $('#concierge_eyes_notes').val() || null,
      concierge_ears: $('input[name="concierge_ears"]:checked').val() || null,
      concierge_ears_odor: $('#concierge_ears_odor').is(':checked'),
      concierge_ears_redness: $('#concierge_ears_redness').is(':checked'),
      concierge_ears_swelling: $('#concierge_ears_swelling').is(':checked'),
      concierge_ears_buildup: $('#concierge_ears_buildup').is(':checked'),
      concierge_ears_notes: $('#concierge_ears_notes').val() || null,
      concierge_mouth: $('input[name="concierge_mouth"]:checked').val() || null,
      concierge_mouth_tartar: $('#concierge_mouth_tartar').is(':checked'),
      concierge_mouth_broken_teeth: $('#concierge_mouth_broken_teeth').is(':checked'),
      concierge_mouth_foul_breath: $('#concierge_mouth_foul_breath').is(':checked'),
      concierge_mouth_notes: $('#concierge_mouth_notes').val() || null,
      concierge_body: $('input[name="concierge_body"]:checked').val() || null,
      concierge_body_lumps: $('#concierge_body_lumps').is(':checked'),
      concierge_body_fleas: $('#concierge_body_fleas').is(':checked'),
      concierge_body_matted: $('#concierge_body_matted').is(':checked'),
      concierge_body_notes: $('#concierge_body_notes').val() || null,
      concierge_paws: $('input[name="concierge_paws"]:checked').val() || null,
      concierge_paws_debris: $('#concierge_paws_debris').is(':checked'),
      concierge_paws_swelling: $('#concierge_paws_swelling').is(':checked'),
      concierge_paws_injury: $('#concierge_paws_injury').is(':checked'),
      concierge_paws_overgrown: $('#concierge_paws_overgrown').is(':checked'),
      concierge_paws_notes: $('#concierge_paws_notes').val() || null,
      concierge_abdomen: $('input[name="concierge_abdomen"]:checked').val() || null,
      concierge_abdomen_bloating: $('#concierge_abdomen_bloating').is(':checked'),
      concierge_abdomen_tenderness: $('#concierge_abdomen_tenderness').is(':checked'),
      concierge_abdomen_rashes: $('#concierge_abdomen_rashes').is(':checked'),
      concierge_abdomen_notes: $('#concierge_abdomen_notes').val() || null,
      concierge_rear: $('input[name="concierge_rear"]:checked').val() || null,
      concierge_rear_irritation: $('#concierge_rear_irritation').is(':checked'),
      concierge_rear_swelling: $('#concierge_rear_swelling').is(':checked'),
      concierge_rear_notes: $('#concierge_rear_notes').val() || null,
      concierge_digestive: $('input[name="concierge_digestive"]:checked').val() || null,
      concierge_digestive_vomit: $('#concierge_digestive_vomit').is(':checked'),
      concierge_digestive_diarrhea: $('#concierge_digestive_diarrhea').is(':checked'),
      concierge_digestive_notes: $('#concierge_digestive_notes').val() || null,
      concierge_other: $('input[name="concierge_other"]:checked').val() || null,
      concierge_other_notes: $('#concierge_other_notes').val() || null,
      nose_tail_treatment: $('#boarding_nose_tail_treatment').val() || null,

      treatment_issues: (function() {
        const issues = [];
        $('.treatment-issue-item').each(function() {
          const $item = $(this);
          const issueSelect = $item.find('.treatment-issue-select');
          const inhouseInput = $item.find('.treatment-issue-inhouse');
          const vetInput = $item.find('.treatment-issue-vet');

          const issue = issueSelect.val() || '';
          const inhouse = inhouseInput.val() || '';
          const vet = vetInput.val() || '';

          // Save even if only one field has data
          if (issue || inhouse || vet) {
            issues.push({
              issue: issue || null,
              inhouse: inhouse || null,
              vet: vet || null
            });
          }
        });
        console.log('Treatment issues collected:', issues);
        console.log('Number of issue items found:', $('.treatment-issue-item').length);
        return issues.length > 0 ? issues : null;
      })(),

      pm_meal_prep_time: $('#boarding_pm_meal_prep_time').val() || null,
      pm_meal_preparation: $('input[name="boarding_pm_meal_preparation"]:checked').val() || null,
      pm_meal_foods: $('#boarding_pm_meal_foods').val() || null,
      pm_med_prep_time: $('#boarding_pm_med_prep_time').val() || null,
      pm_med_prep_notes: $('#boarding_pm_med_prep_notes').val() || null,
      pm_meal_dispense_time: $('#boarding_pm_meal_dispense_time').val() || null,
      pm_meal_dispense_hand_feed: $('#boarding_pm_meal_dispense_hand_feed').is(':checked'),
      pm_meal_dispense_food_aggressive: $('#boarding_pm_meal_dispense_food_aggressive').is(':checked'),
      pm_meal_dispense_quiet_spot: $('#boarding_pm_meal_dispense_quiet_spot').is(':checked'),
      pm_meal_dispense_must_eat: $('#boarding_pm_meal_dispense_must_eat').val() || null,
      pm_meal_dispense_not_eating: $('#boarding_pm_meal_dispense_not_eating').is(':checked'),
      pm_med_dispense_time: $('#boarding_pm_med_dispense_time').val() || null,
      pm_med_dispense_instructions: $('#boarding_pm_med_dispense_instructions').val() || null,
      pm_med_dispense_must_receive: $('#boarding_pm_med_dispense_must_receive').is(':checked'),

    };

    console.log('Saving workflow data:', workflowData);
    console.log('Treatment issues in workflowData:', workflowData.treatment_issues);
    console.log('Number of issue items in DOM:', $('.treatment-issue-item').length);

    // Debug: Log each issue item
    $('.treatment-issue-item').each(function(index) {
      const $item = $(this);
      const issueSelect = $item.find('.treatment-issue-select');
      const inhouseInput = $item.find('.treatment-issue-inhouse');
      const vetInput = $item.find('.treatment-issue-vet');
      console.log(`Issue ${index + 1}:`, {
        issue: issueSelect.val(),
        inhouse: inhouseInput.val(),
        vet: vetInput.val()
      });
    });

    $.ajax({
      url: '{{ route("update-process-flows", $appointment->id) }}',
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        flows: workflowData,
        workflow_date: selectedDate,
        staff_id: $('#process_staff_id').val() || null
      },
      dataType: 'json',
      success: function(response) {
        console.log('Boarding workflow data saved successfully for date:', selectedDate);
        console.log('Response:', response);
      },
      error: function(xhr, status, error) {
        console.error('Error saving boarding workflow data:', error);
        console.error('Status:', status);
        console.error('Response:', xhr.responseText);
        if (xhr.responseJSON) {
          console.error('Response JSON:', xhr.responseJSON);
        }
      }
    });
  }

  let itemIdx = 0;
  const invoiceDiscountRules = @json($invoiceDiscountRules ?? []);
  const invoiceDefaultStatus = @json($invoice?->status ?? 'draft');
  const invoiceIssuedAt = @json(optional($invoice?->issued_at)->format('Y-m-d H:i:s'));
  const invoicePaidAt = @json(optional($invoice?->paid_at)->format('Y-m-d H:i:s'));
  const invoiceExists = {{ $invoice ? 'true' : 'false' }};
  const hasPersistedInvoiceDiscount = {{ ($invoice && (float) $invoice->discount_amount > 0) ? 'true' : 'false' }};
  const persistedInvoiceDiscountAmount = parseFloat(@json($invoice->discount_amount ?? 0));
  const persistedInvoiceDiscountTitle = @json($invoice->discount_title ?? null);
  const invoiceStateTaxRate = parseFloat(@json((float) config('billing.state_tax_rate', 7)));
  const isBoardingInvoice = {{ isBoardingService($appointment->service) ? 'true' : 'false' }};
  const lateCheckoutThresholdHours = parseFloat(@json($headerLateCheckoutThresholdHours ?? 1));
  const daycareHourlyRate = parseFloat(@json($headerLateCheckoutHourlyRate ?? 0));
  const lateCheckoutInitialFee = parseFloat(@json($invoiceLateCheckoutDaycareFee ?? 0));
  const hasPersistedLateCheckoutInvoiceFee = {{ ($persistedLateCheckoutInvoiceFee ?? 0) > 0 ? 'true' : 'false' }};
  const lateCheckoutFormulaFee = parseFloat(@json($headerLateCheckoutFormulaFee ?? 0));
  const scheduledPickupDateTime = @json((!empty($appointment->end_date) && !empty($appointment->end_time)) ? ($appointment->end_date . ' ' . $appointment->end_time) : null);
  const canOverrideInvoiceLock = {{ ($canOverrideInvoiceLock ?? false) ? 'true' : 'false' }};
  const invoiceEditingLocked = {{ ($isInvoiceEditingLocked ?? false) ? 'true' : 'false' }};
  let currentDiscountTitle = null;
  const invoiceCustomerFullName = @json(trim((($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? ''))) ?: ($appointment->customer->name ?? 'customer'));

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function parseMoney(value) {
    const numeric = parseFloat(String(value || '').replace(/[^0-9.-]/g, ''));
    return Number.isFinite(numeric) ? numeric : 0;
  }

  function getInvoiceRowDescription($row) {
    const input = $row.find('.invoice-item-description');
    if (input.length) {
      return (input.val() || '').trim();
    }

    return ($row.find('td:nth-child(2)').text() || '').trim();
  }

  function getInvoiceRowPrice($row) {
    const input = $row.find('.invoice-item-price');
    if (input.length) {
      return parseMoney(input.val());
    }

    return parseMoney($row.find('td:nth-child(3)').text());
  }

  function resolveInvoiceRowType($row) {
    if ($row.hasClass('custom-line-row')) {
      return 'custom';
    }

    if ($row.hasClass('inventory-row')) {
      return 'inventory';
    }

    return String($row.attr('data-item-type') || 'service').trim() || 'service';
  }

  function refreshPricingRowIndexes() {
    $('#pricing_table tr:visible').each(function(index) {
      $(this).find('td:first').text(index + 1);
    });
  }

  function shouldSkipDuplicateAutoFee(description, seenAutoFees) {
    const normalizedDescription = String(description || '').trim().toLowerCase();
    const autoFeeDescriptions = ['late checkout daycare fee', 'flea/tick detection fee'];

    if (!autoFeeDescriptions.includes(normalizedDescription)) {
      return false;
    }

    if (seenAutoFees.has(normalizedDescription)) {
      return true;
    }

    seenAutoFees.add(normalizedDescription);
    return false;
  }

  function collectInvoiceItems() {
    const items = [];
    const seenAutoFees = new Set();

    $('#pricing_table tr.service-row, #pricing_table tr.inventory-row, #pricing_table tr.coat-fee-row').each(function() {
      const $row = $(this);
      if (!$row.is(':visible')) {
        return;
      }

      const description = getInvoiceRowDescription($row);
      if (shouldSkipDuplicateAutoFee(description, seenAutoFees)) {
        return;
      }
      const price = getInvoiceRowPrice($row);
      if (!description) {
        return;
      }

      items.push({
        description: description,
        price: price,
        type: resolveInvoiceRowType($row)
      });
    });

    return items;
  }

  function applyInvoiceLockState() {
    if (!invoiceEditingLocked) {
      return;
    }

    $('#invoice_number, #first_name, #last_name, #email, #issued_at, #due_date, #paid_at, #status, #invoice_notes').prop('disabled', true);
    $('#inventory_item, #add_line_item_btn').prop('disabled', true);
    $('#save_invoice_btn').prop('disabled', true);
    $('#pricing_table .btn').prop('disabled', true);
    $('#pricing_table input').prop('disabled', true);
  }

  function enhanceInvoiceLineItemsForEditing() {
    if (invoiceEditingLocked) {
      applyInvoiceLockState();
      return;
    }

    $('#pricing_table tr.service-row, #pricing_table tr.inventory-row, #pricing_table tr.coat-fee-row').each(function() {
      const $row = $(this);
      if ($row.hasClass('line-item-edit-ready')) {
        return;
      }

      const description = escapeHtml(getInvoiceRowDescription($row));
      const price = getInvoiceRowPrice($row);

      $row.find('td:nth-child(2)').html('<input type="text" class="input input-bordered input-xs w-full invoice-item-description" value="' + description + '" />');
      $row.find('td:nth-child(3)').html('<input type="number" step="0.01" class="input input-bordered input-xs w-full invoice-item-price" value="' + price.toFixed(2) + '" />');

      if ($row.find('td:nth-child(4)').length === 0) {
        $row.append('<td></td>');
      }

      if ($row.find('.remove-line-item-btn').length === 0) {
        $row.find('td:nth-child(4)').html(
          '<button type="button" class="btn btn-sm btn-ghost btn-circle remove-line-item-btn" style="height: 16px">'
          + '<span class="iconify lucide--trash-2 size-3 text-error"></span>'
          + '</button>'
        );
      }

      $row.addClass('line-item-edit-ready');
    });

    refreshPricingRowIndexes();
  }

  function syncLateCheckoutDaycareFeeRow() {
    const row = $('#late_checkout_daycare_fee_row');
    if (!row.length) {
      return { fee: 0, lateHours: 0, isLate: false };
    }

    if (invoiceExists) {
      row.hide();
      row.attr('data-initial-fee', '0.00');
      row.find('td:nth-child(3)').text('$0.00');
      return { fee: 0, lateHours: 0, isLate: false };
    }

    if (hasPersistedLateCheckoutInvoiceFee) {
      row.hide();
      row.attr('data-initial-fee', '0.00');
      row.find('td:nth-child(3)').text('$0.00');
      return { fee: 0, lateHours: 0, isLate: false };
    }

    const initialFee = Number.isFinite(lateCheckoutInitialFee)
      ? lateCheckoutInitialFee
      : 0;
    const formulaFee = Number.isFinite(lateCheckoutFormulaFee)
      ? lateCheckoutFormulaFee
      : 0;
    const rowFee = parseFloat(row.attr('data-initial-fee')) || 0;
    const fallbackFee = Math.max(initialFee, formulaFee, rowFee);

    const checkoutDate = ($('#checkout_date').val() || '').trim();
    const checkoutTime = ($('#checkout_pickup_time').val() || '').trim();

    if (!scheduledPickupDateTime || !checkoutDate || !checkoutTime || !(daycareHourlyRate > 0)) {
      if (fallbackFee > 0) {
        row.show();
        row.find('td:nth-child(3)').text('$' + fallbackFee.toFixed(2));
        row.attr('data-initial-fee', fallbackFee.toFixed(2));
        return { fee: fallbackFee, lateHours: 0, isLate: true };
      }

      row.hide();
      row.find('td:nth-child(3)').text('$0.00');
      row.attr('data-initial-fee', '0.00');
      return { fee: 0, lateHours: 0, isLate: false };
    }

    const scheduledAt = moment(scheduledPickupDateTime);
    const actualAt = moment(checkoutDate + ' ' + checkoutTime);

    if (!scheduledAt.isValid() || !actualAt.isValid()) {
      if (fallbackFee > 0) {
        row.show();
        row.find('td:nth-child(3)').text('$' + fallbackFee.toFixed(2));
        row.attr('data-initial-fee', fallbackFee.toFixed(2));
        return { fee: fallbackFee, lateHours: 0, isLate: true };
      }

      row.hide();
      row.find('td:nth-child(3)').text('$0.00');
      row.attr('data-initial-fee', '0.00');
      return { fee: 0, lateHours: 0, isLate: false };
    }

    const lateSeconds = actualAt.diff(scheduledAt, 'seconds');
    if (lateSeconds <= 0) {
      if (fallbackFee > 0) {
        row.show();
        row.find('td:nth-child(3)').text('$' + fallbackFee.toFixed(2));
        row.attr('data-initial-fee', fallbackFee.toFixed(2));
        return { fee: fallbackFee, lateHours: 0, isLate: false };
      }

      row.hide();
      row.find('td:nth-child(3)').text('$0.00');
      row.attr('data-initial-fee', '0.00');
      return { fee: 0, lateHours: 0, isLate: false };
    }

    const lateHours = lateSeconds / 3600;
    if (lateHours < lateCheckoutThresholdHours) {
      if (fallbackFee > 0) {
        row.show();
        row.find('td:nth-child(3)').text('$' + fallbackFee.toFixed(2));
        row.attr('data-initial-fee', fallbackFee.toFixed(2));
        return { fee: fallbackFee, lateHours, isLate: true };
      }

      row.hide();
      row.find('td:nth-child(3)').text('$0.00');
      row.attr('data-initial-fee', '0.00');
      return { fee: 0, lateHours, isLate: true };
    }

    const billableHours = Math.floor(lateHours);
    const fee = Math.round((billableHours * daycareHourlyRate + Number.EPSILON) * 100) / 100;
    row.show();
    row.find('td:nth-child(3)').text('$' + fee.toFixed(2));
    row.attr('data-initial-fee', fee.toFixed(2));

    return { fee, lateHours, isLate: true };
  }

  window.__invoiceTotalsReady = true;
  enhanceInvoiceLineItemsForEditing();
  applyInvoiceLockState();

  $(document).on('input', '.invoice-item-price, .invoice-item-description', function() {
    updateTotals();
  });

  $(document).on('click', '.remove-line-item-btn', function() {
    if (invoiceEditingLocked) {
      $('#alert_message').text('Invoice is locked and cannot be edited.');
      alert_modal.showModal();
      return;
    }

    $(this).closest('tr').remove();
    refreshPricingRowIndexes();
    const deleteTotals = updateTotals();
    updateDetailSectionSummaryFromInvoice(collectInvoiceItems(), deleteTotals);
  });

  if (typeof updateTotals === 'function') {
    syncLateCheckoutDaycareFeeRow();
    updateTotals();
  }

  function addInventoryItem() {
    if (invoiceEditingLocked) {
      $('#alert_message').text('Invoice is locked and cannot be edited.');
      alert_modal.showModal();
      return;
    }

    const selectedItem = $('#inventory_item').select2('data')[0];
    if (!selectedItem) {
      $('#alert_message').text('Please select an inventory item.');
      alert_modal.showModal();
      return;
    }

    const newRow = `
      <tr id="item_row_${itemIdx}" class="inventory-row" data-item-type="inventory">
        <td>${$('#pricing_table tr').length}</td>
        <td width="56%"><input type="text" class="input input-bordered input-xs w-full invoice-item-description" value="${escapeHtml(selectedItem.brand)}" /></td>
        <td><input type="number" step="0.01" class="input input-bordered input-xs w-full invoice-item-price" value="${parseFloat(selectedItem.price).toFixed(2)}" /></td>
        <td>
          <button type="button" class="btn btn-sm btn-ghost btn-circle remove-line-item-btn" style="height: 16px">
            <span class="iconify lucide--trash-2 size-3 text-error"></span>
          </button>
        </td>
      </tr>
    `;
    $('#pricing_table').append(newRow);
    refreshPricingRowIndexes();
    updateTotals();
    // Clear the selection
    $('#inventory_item').val(null).trigger('change');
    itemIdx++;
  }

  function addCustomLineItem() {
    if (invoiceEditingLocked) {
      $('#alert_message').text('Invoice is locked and cannot be edited.');
      alert_modal.showModal();
      return;
    }

    const newRow = `
      <tr id="custom_item_row_${itemIdx}" class="service-row custom-line-row" data-item-type="custom">
        <td>${$('#pricing_table tr').length}</td>
        <td width="56%"><input type="text" class="input input-bordered input-xs w-full invoice-item-description" placeholder="Line item description" value="" /></td>
        <td><input type="number" step="0.01" class="input input-bordered input-xs w-full invoice-item-price" value="0.00" /></td>
        <td>
          <button type="button" class="btn btn-sm btn-ghost btn-circle remove-line-item-btn" style="height: 16px">
            <span class="iconify lucide--trash-2 size-3 text-error"></span>
          </button>
        </td>
      </tr>
    `;

    $('#pricing_table').append(newRow);
    refreshPricingRowIndexes();
    updateTotals();
    itemIdx++;
  }

  function removeInventoryItem(idx) {
    $(`#item_row_${idx}`).remove();
    refreshPricingRowIndexes();
    updateTotals();
  }

  function removeExistingInvoiceItem(itemId) {
    if (invoiceEditingLocked) {
      $('#alert_message').text('Invoice is locked and cannot be edited.');
      alert_modal.showModal();
      return;
    }

    $(`tr[data-item-id="${itemId}"]`).remove();
    refreshPricingRowIndexes();
    const deleteTotals = updateTotals();
    updateDetailSectionSummaryFromInvoice(collectInvoiceItems(), deleteTotals);
  }

  function toMomentOrNow(value) {
    return value ? moment(value) : moment();
  }

  function resolveInvoiceDiscountReferenceDate(status, overrides = {}) {
    const normalizedStatus = (status || '').toLowerCase();
    const effectiveIssuedAt = overrides.issuedAt || invoiceIssuedAt || $('#issued_at').val();
    const effectivePaidAt = overrides.paidAt || invoicePaidAt || $('#paid_at').val();
    const effectiveInvoiceExists = typeof overrides.invoiceExists === 'boolean' ? overrides.invoiceExists : invoiceExists;
    const effectiveInvoiceStatus = (overrides.invoiceStatus || invoiceDefaultStatus || '').toLowerCase();

    if (normalizedStatus === 'paid') {
      return toMomentOrNow(effectivePaidAt);
    }

    if (effectiveInvoiceExists && effectiveInvoiceStatus !== 'paid') {
      return moment();
    }

    if (!effectiveInvoiceExists && normalizedStatus === 'sent') {
      return toMomentOrNow(effectiveIssuedAt);
    }

    return moment();
  }

  function calculateInvoiceDiscount(referenceDate, estimatedPrice) {
    if (!Array.isArray(invoiceDiscountRules) || invoiceDiscountRules.length === 0) {
      return { amount: 0, rule: null };
    }

    const reference = moment(referenceDate);
    let bestRule = null;
    let bestAmount = 0;

    invoiceDiscountRules.forEach(function(rule) {
      const start = rule.start_date ? moment(rule.start_date) : null;
      const end = rule.end_date ? moment(rule.end_date) : null;

      if (start && reference.isBefore(start)) {
        return;
      }

      if (end && reference.isAfter(end)) {
        return;
      }

      const ruleValue = parseFloat(rule.amount || 0);
      let candidateAmount = 0;

      if (rule.type === 'percent') {
        candidateAmount = (estimatedPrice * ruleValue) / 100;
      } else {
        candidateAmount = ruleValue;
      }

      candidateAmount = Math.max(0, Math.min(estimatedPrice, candidateAmount));
      if (candidateAmount > bestAmount) {
        bestAmount = candidateAmount;
        bestRule = rule;
      }
    });

    return { amount: bestAmount, rule: bestRule };
  }

  function updateTotals(statusOverride = null, dateOverrides = {}) {
    syncLateCheckoutDaycareFeeRow();

    // Calculate the Total Price of Services
    let serviceTotal = 0;
    const seenAutoFees = new Set();
    $('.service-row:visible, .coat-fee-row:visible').each(function() {
      const $row = $(this);
      const description = getInvoiceRowDescription($row);
      if (shouldSkipDuplicateAutoFee(description, seenAutoFees)) {
        return;
      }

      const price = getInvoiceRowPrice($row);
      if (!isNaN(price)) {
        serviceTotal += price;
      }
    });

    // Use rendered service rows as the source of truth for service totals.
    const estimatedPrice = serviceTotal;

    // Set inventory total (excluding service and additional services)
    let inventoryRowsTotal = 0;
    $('.inventory-row').each(function() {
      const price = getInvoiceRowPrice($(this));
      if (!isNaN(price)) {
        inventoryRowsTotal += price;
      }
    });

    const effectiveStatus = statusOverride || $('#status').val() || invoiceDefaultStatus;
    const discountRow = $('#invoice_discount_row');
    const canRenderDiscount = discountRow.length > 0;

    let discountResult = { amount: 0, rule: null };
    if (canRenderDiscount) {
      try {
        const referenceDate = resolveInvoiceDiscountReferenceDate(effectiveStatus, dateOverrides);
        discountResult = calculateInvoiceDiscount(referenceDate, estimatedPrice) || { amount: 0, rule: null };
      } catch (error) {
        console.error('Failed to calculate invoice discount:', error);
        discountResult = { amount: 0, rule: null };
      }
    }

    const calculatedDiscountAmount = parseFloat(discountResult.amount || 0);
    const discountAmount = (hasPersistedInvoiceDiscount && canRenderDiscount)
      ? Math.max(0, Math.min(estimatedPrice, persistedInvoiceDiscountAmount || 0))
      : calculatedDiscountAmount;
    const subtotalAmount = Math.max(0, estimatedPrice - discountAmount + inventoryRowsTotal);
    const hasBoardingServiceRow = $('#pricing_table tr.service-row td:nth-child(2)').filter(function() {
      return (($(this).text() || '').trim().toLowerCase().includes('boarding'));
    }).length > 0;
    const effectiveTaxRate = (isBoardingInvoice || hasBoardingServiceRow) ? Math.max(0, invoiceStateTaxRate || 0) : 0;
    const stateTaxAmount = subtotalAmount * (effectiveTaxRate / 100);
    const totalAmount = subtotalAmount + stateTaxAmount;

    $('#total_price_of_services').text('$' + serviceTotal.toFixed(2));
    $('#estimated_price_of_services').text('$' + estimatedPrice.toFixed(2));
    $('#inventory_total_amount').text('$' + inventoryRowsTotal.toFixed(2));
    $('#invoice_subtotal_amount').text('$' + subtotalAmount.toFixed(2));
    $('#invoice_state_tax_rate_label').text(effectiveTaxRate.toFixed(2).replace(/\.00$/, ''));
    $('#invoice_state_tax_amount').text('$' + stateTaxAmount.toFixed(2));
    if (effectiveTaxRate > 0) {
      $('#invoice_state_tax_row').show();
    } else {
      $('#invoice_state_tax_row').hide();
    }
    $('#grand_total_amount').text('$' + totalAmount.toFixed(2));

    const discountTooltip = $('.js-invoice-discount-tooltip');
    const discountLabel = $('#invoice_discount_label');
    const appliedDiscountTitle = (discountResult.rule && discountResult.rule.title) ? discountResult.rule.title : null;
    currentDiscountTitle = hasPersistedInvoiceDiscount ? (persistedInvoiceDiscountTitle || null) : appliedDiscountTitle;

    if (discountRow.length) {
      if (discountAmount > 0) {
        discountRow.show();
        $('#invoice_discount_amount').text('-$' + discountAmount.toFixed(2));
        discountLabel.text((currentDiscountTitle || 'Discount') + ':');
        const titleForTooltip = currentDiscountTitle || 'Discount';
        const tooltipText = 'The discount "' + titleForTooltip + '" is applied for ' + invoiceCustomerFullName + '.';
        discountTooltip.attr('data-tip', tooltipText);
      } else {
        discountRow.hide();
        discountLabel.text('Discount:');
        discountTooltip.removeClass('tooltip-open');
      }
    }

    return {
      serviceTotal,
      inventoryRowsTotal,
      subtotalAmount,
      stateTaxAmount,
      stateTaxRate: effectiveTaxRate,
      discountAmount,
      discountRule: discountResult.rule || null,
      totalAmount
    };
  }

  function updateDetailSectionSummaryFromInvoice(items, totals) {
    const estimatedPriceEl = $('#detail_estimated_price_text');
    if (estimatedPriceEl.length && totals && Number.isFinite(totals.totalAmount)) {
      estimatedPriceEl.text('$' + Number(totals.totalAmount).toFixed(2));
    }

    const names = Array.isArray(items)
      ? items
          .filter(function(item) {
            const itemType = String((item && item.type) || '').trim().toLowerCase();
            return itemType === 'custom' || itemType === 'inventory';
          })
          .map(function(item) {
            return String((item && item.description) || '').trim();
          })
          .filter(function(name) {
            return name.length > 0;
          })
      : [];

    const uniqueNames = Array.from(new Set(names));
    const itemsRow = $('#detail_invoice_items_row');
    const itemsText = $('#detail_invoice_items_text');

    if (!itemsRow.length || !itemsText.length) {
      return;
    }

    if (uniqueNames.length > 0) {
      itemsText.text(uniqueNames.join(', '));
      itemsRow.removeClass('hidden');
    } else {
      itemsText.text('');
      itemsRow.addClass('hidden');
    }
  }

  function saveInvoice(appointmentId) {
    const invoice_number = $('#invoice_number').val();
    const first_name = $('#first_name').val();
    const last_name = $('#last_name').val();
    const email = $('#email').val();
    const issued_at = $('#issued_at').val();
    const due_date = $('#due_date').val();
    const paid_at = $('#paid_at').val();
    const status = $('#status').val();
    const notes = $('#invoice_notes').val();
    const discount_amount = parseFloat($('#invoice_discount_amount').text().replace(/[^0-9.]/g, '')) || 0;
    const discount_title = currentDiscountTitle;

    // Validate required fields
    if (!invoice_number || !first_name || !last_name || !email || !issued_at) {
      $('#alert_message').text('Please fill in all required fields in the invoice form.');
      alert_modal.showModal();
      return;
    }

    if (status === 'paid' && !paid_at) {
      $('#alert_message').text('Please fill in the Paid At field when Status is Paid.');
      alert_modal.showModal();
      return;
    }

    const isGroupClass = {{ isGroupClassService($appointment->service) ? 'true' : 'false' }};

    if (status === 'paid' && !isGroupClass) {
      if (!moment(paid_at).isValid()) {
        $('#alert_message').text('Please provide a valid Paid At date/time.');
        alert_modal.showModal();
        return;
      }

      const totals = updateTotals('paid', {
        paidAt: paid_at,
        issuedAt: issued_at,
        invoiceExists,
        invoiceStatus: status
      });

      $('#payment_amount').val(totals.totalAmount.toFixed(2));
      $('#payment_method').val('');
      $('#payment_notes').val('');

      const paymentDiscountSummary = $('#payment_discount_summary');
      const paymentDiscountText = $('#payment_discount_text');
      const paymentDiscountMeta = $('#payment_discount_meta');
      const invoiceTotalTooltip = $('#invoice_total_tooltip');
      if (totals.discountAmount > 0) {
        const discountTitle = totals.discountRule && totals.discountRule.title ? totals.discountRule.title : (currentDiscountTitle || 'Discount');
        paymentDiscountText.text('The discount "' + discountTitle + '" is applied to ' + invoiceCustomerFullName + '.');
        paymentDiscountSummary.removeClass('hidden');
        paymentDiscountMeta.show();
        invoiceTotalTooltip.attr('data-tip', 'The discount "' + discountTitle + '" is applied for ' + invoiceCustomerFullName + '.');
      } else {
        paymentDiscountText.text('No discount applies to ' + invoiceCustomerFullName + '.');
        paymentDiscountSummary.removeClass('hidden');
        paymentDiscountMeta.hide();
        invoiceTotalTooltip.attr('data-tip', '');
      }
      
      window.pendingInvoiceData = {
        invoice_number: invoice_number,
        first_name: first_name,
        last_name: last_name,
        email: email,
        issued_at: issued_at,
        due_date: due_date,
        paid_at: paid_at,
        status: status,
        notes: notes,
        appointmentId: appointmentId,
        discount_amount: totals.discountAmount || 0,
        discount_title: totals.discountRule && totals.discountRule.title ? totals.discountRule.title : (persistedInvoiceDiscountTitle || null)
      };
      
      payment_modal.showModal();
      return;
    }

    const items = collectInvoiceItems();
    const currentTotals = updateTotals(status, {
      paidAt: paid_at,
      issuedAt: issued_at,
      invoiceExists,
      invoiceStatus: status
    });

    // Show loading spinner in the button and disable it
    $('#save_invoice_btn .loading').css('display', 'inline-block');
    $('#save_invoice_btn').prop('disabled', true);
    // Remove the original 'Save Invoice' text
    $('#save_invoice_btn').contents().filter(function() {
      return this.nodeType === 3 && this.nodeValue.trim() === 'Save Invoice';
    }).remove();
    // Add 'Loading' text
    $('#save_invoice_btn').append('Loading');

    // Send AJAX request
    $.ajax({
      url: '{{ route("save-invoice-appointment", ":id") }}'.replace(':id', appointmentId),
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        invoice_number: invoice_number,
        first_name: first_name,
        last_name: last_name,
        email: email,
        issued_at: issued_at ? moment(issued_at).format('YYYY-MM-DD HH:mm:ss') : null,
        due_date: due_date ? moment(due_date).format('YYYY-MM-DD') : null,
        paid_at: paid_at ? moment(paid_at).format('YYYY-MM-DD HH:mm:ss') : null,
        status: status,
        notes: notes,
        items: items,
        discount_amount: discount_amount,
        discount_title: currentDiscountTitle || null
      },
      success: function(response) {
        // Reset button state
        $('#save_invoice_btn .loading').css('display', 'none');
        $('#save_invoice_btn').prop('disabled', false);
        $('#save_invoice_btn').contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Loading';
        }).remove();
        $('#save_invoice_btn').append('Save Invoice');

        if (response.status) {
          updateDetailSectionSummaryFromInvoice(items, currentTotals);
          $('#success_message').text(response.message || 'Invoice saved successfully!');
          success_modal.showModal();
        } else {
          $('#alert_message').text('Error: ' + (response.message || 'Unknown error'));
          alert_modal.showModal();
        }
      },
      error: function(xhr, status, error) {
        // Reset button state
        $('#save_invoice_btn .loading').css('display', 'none');
        $('#save_invoice_btn').prop('disabled', false);
        $('#save_invoice_btn').contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Loading';
        }).remove();
        $('#save_invoice_btn').append('Save Invoice');

        console.error('Error saving invoice:', error);
        const backendMessage = xhr && xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : null;
        $('#alert_message').text(backendMessage || 'Error saving invoice. Please try again.');
        alert_modal.showModal();
      }
    });
  }

  function confirmPayment(appointmentId) {
    const amount = $('#payment_amount').val();
    const paymentMethod = $('#payment_method').val();
    const paymentNotes = $('#payment_notes').val();

    if (!amount || parseFloat(amount) <= 0) {
      $('#alert_message').text('Please enter a valid payment amount.');
      alert_modal.showModal();
      return;
    }

    if (!paymentMethod) {
      $('#alert_message').text('Please select a payment type.');
      alert_modal.showModal();
      return;
    }

    const items = collectInvoiceItems();

    $('#confirm_payment_btn .loading').css('display', 'inline-block');
    $('#confirm_payment_btn').prop('disabled', true);

    const invoiceData = window.pendingInvoiceData || {};
    const currentPaidAt = invoiceData.paid_at || $('#paid_at').val() || moment().format('YYYY-MM-DD HH:mm:ss');

    $.ajax({
      url: '{{ route("save-invoice-appointment", ":id") }}'.replace(':id', appointmentId),
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        invoice_number: invoiceData.invoice_number || $('#invoice_number').val(),
        first_name: invoiceData.first_name || $('#first_name').val(),
        last_name: invoiceData.last_name || $('#last_name').val(),
        email: invoiceData.email || $('#email').val(),
        issued_at: invoiceData.issued_at ? moment(invoiceData.issued_at).format('YYYY-MM-DD HH:mm:ss') : ($('#issued_at').val() ? moment($('#issued_at').val()).format('YYYY-MM-DD HH:mm:ss') : null),
        due_date: invoiceData.due_date ? moment(invoiceData.due_date).format('YYYY-MM-DD') : ($('#due_date').val() ? moment($('#due_date').val()).format('YYYY-MM-DD') : null),
        paid_at: currentPaidAt ? moment(currentPaidAt).format('YYYY-MM-DD HH:mm:ss') : null,
        status: 'paid',
        notes: invoiceData.notes || $('#invoice_notes').val(),
        items: items,
        discount_amount: invoiceData.discount_amount !== undefined ? invoiceData.discount_amount : null,
        discount_title: invoiceData.discount_title || null,
        payment_amount: amount,
        payment_method: paymentMethod,
        payment_notes: paymentNotes
      },
      success: function(response) {
        $('#confirm_payment_btn .loading').css('display', 'none');
        $('#confirm_payment_btn').prop('disabled', false);
        payment_modal.close();
        delete window.pendingInvoiceData;

        if (response.status) {
          $('#success_message').text('Invoice saved and payment recorded successfully!');
          success_modal.showModal();
          
          setTimeout(function() {
            window.location.reload();
          }, 1500)
        } else {
          $('#alert_message').text('Error: ' + (response.message || 'Unknown error'));
          alert_modal.showModal();
        }
      },
      error: function(xhr, status, error) {
        $('#confirm_payment_btn .loading').css('display', 'none');
        $('#confirm_payment_btn').prop('disabled', false);

        console.error('Error confirming payment:', error);
        $('#alert_message').text('Error confirming payment. Please try again.');
        alert_modal.showModal();
      }
    });
  }

  function confirmCompleted() {
    // Get checkout form values
    const date = $('#checkout_date').val();
    const notes = $('#checkout_notes').val();
    const serviceNotes = $('#checkout_service_notes').length ? $('#checkout_service_notes').val() : null;
    const pictures = $('#checkout_pictures').length ? $('#checkout_pictures')[0].files : [];
    const selectedBehaviorIds = $('#pet_behavior_id').length ? ($('#pet_behavior_id').val() || []) : [];

    // Check if rating field exists (for non-training services)
    const ratingElement = $('input[name="rating"]:checked');
    const rating = ratingElement.length ? ratingElement.val() : null;
    const ratingYellowDetail = $('#rating_yellow_detail').length ? $('#rating_yellow_detail').val() : null;
    const ratingPurpleDetail = $('#rating_purple_detail').length ? $('#rating_purple_detail').val() : null;

    // Get training completion data if training service (check if obedience rating fields exist)
    const obedienceCommands = ['sit', 'down', 'stay', 'come', 'loose_leash_walking'];
    const obedienceRatings = {};
    let hasObedienceRatings = false;

    obedienceCommands.forEach(command => {
      const ratingInput = $('input[name="obedience_rating_' + command + '"]:checked');
      if (ratingInput.length) {
        const selectedRating = ratingInput.val();
        if (selectedRating !== undefined) {
          obedienceRatings[command] = parseInt(selectedRating);
          hasObedienceRatings = true;
        }
      }
    });

    // Get training fields if they exist
    const trainingCurrentRatings = $('#training_current_ratings').length ? $('#training_current_ratings').val() : null;
    const trainingTargets = $('#training_targets').length ? $('#training_targets').val() : null;
    const trainingHomework = $('#training_homework').length ? $('#training_homework').val() : null;

    // Validate required fields
    if (!date) {
      $('#alert_message').text('Please fill in Date.');
      alert_modal.showModal();
      return;
    }

    const pickupTime = $('#checkout_pickup_time').length ? $('#checkout_pickup_time').val() : null;
    const actualCheckoutAt = (date && pickupTime)
      ? moment(date + ' ' + pickupTime, 'YYYY-MM-DD HH:mm').format('YYYY-MM-DD HH:mm:ss')
      : moment().format('YYYY-MM-DD HH:mm:ss');

    // Use FormData for file upload
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('date', date || '');
    formData.append('pickup_time', pickupTime || '');
    formData.append('actual_checkout_at', actualCheckoutAt);
    formData.append('notes', notes || '');

    // Build flows data dynamically based on what exists
    const flows = {};

    // Add rating data if it exists (for non-training services)
    if (rating !== null) {
      flows.rating = rating;
      if (ratingYellowDetail) flows.rating_yellow_detail = ratingYellowDetail;
      if (ratingPurpleDetail) flows.rating_purple_detail = ratingPurpleDetail;
    }

    // Add service notes if it exists
    if (serviceNotes) {
      flows.service_notes = serviceNotes;
    }

    // Add training completion data if it exists
    if (hasObedienceRatings && Object.keys(obedienceRatings).length > 0) {
      flows.obedience_ratings = obedienceRatings;
    }
    if (trainingCurrentRatings) {
      flows.training_current_ratings = trainingCurrentRatings;
    }
    if (trainingTargets) {
      flows.training_targets = trainingTargets;
    }
    if (trainingHomework) {
      flows.training_homework = trainingHomework;
    }

    flows.behavior_ids = selectedBehaviorIds
      .map(function (id) { return parseInt(id, 10); })
      .filter(function (id) { return !isNaN(id); });

    formData.append('flows', JSON.stringify(flows));

    // Add multiple pictures
    if (pictures.length > 0) {
      for (let i = 0; i < pictures.length; i++) {
        formData.append('pictures[]', pictures[i]);
      }
    }

    // Send via AJAX with FormData
    $.ajax({
      url: '{{ route("confirm-completed-appointment", $appointment->id) }}',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        if (response.status === 'success') {
          window.location.href = '{{ route("service-dashboard", $appointment->service_id) }}';
        } else {
          alert('Error: ' + (response.message || 'Unknown error'));
        }
      },
      error: function(xhr, status, error) {
        console.error('Error confirming checkout:', error);
        alert('Error confirming checkout. Please try again.');
      }
    });
  }

  // Star rating component interactions
  $(document).ready(function() {
    // Handle star rating clicks
    $('.star-rating-icon').on('click', function(e) {
      e.preventDefault();
      const starValue = parseInt($(this).data('star-value'));
      const command = $(this).data('command');
      const container = $(this).closest('.star-rating-container');

      // Update the hidden radio button
      $(`#rating_${command}_${starValue}`).prop('checked', true).trigger('change');

      // Update visual appearance of all stars in this container
      container.find('.star-rating-icon[data-command="' + command + '"]').each(function() {
        const currentStarValue = parseInt($(this).data('star-value'));
        if (currentStarValue <= starValue) {
          $(this).css('color', '#fbbf24'); // Yellow/gold color
        } else {
          $(this).css('color', '#d1d5db'); // Gray color
        }
      });

      // Update container data
      container.data('rating', starValue);
    });
  });

  function updateAppointmentStatus(status, showSuccess = true) {
    // Create the form dynamically
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("update-appointment-status", $appointment->id) }}';
    form.style.display = 'none';

    // Add CSRF token
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    // Add status input
    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'status';
    statusInput.value = status;
    form.appendChild(statusInput);

    document.body.appendChild(form);
    form.submit();
  }

  function loadAlaCarteProcessData(appointmentId, serviceId, date) {
    if (!date) {
      return;
    }

    $.ajax({
      url: '{{ route("get-process-flows", ":id") }}'.replace(':id', appointmentId),
      method: 'GET',
      data: {
        date: date,
        service_id: serviceId
      },
      success: function(response) {
        if (response.staff_id) {
          $(`.ala-carte-staff-id[data-service-id="${serviceId}"]`).val(response.staff_id).trigger('change');
        } else {
          $(`.ala-carte-staff-id[data-service-id="${serviceId}"]`).val('').trigger('change');
        }

        if (response.start_time) {
          $(`.ala-carte-start-time[data-service-id="${serviceId}"]`).val(response.start_time);
        } else {
          $(`.ala-carte-start-time[data-service-id="${serviceId}"]`).val('');
        }

        if (response.pickup_time) {
          $(`.ala-carte-pickup-time[data-service-id="${serviceId}"]`).val(response.pickup_time);
        } else {
          $(`.ala-carte-pickup-time[data-service-id="${serviceId}"]`).val('');
        }

        if (response.notes) {
          $(`.ala-carte-notes[data-service-id="${serviceId}"]`).val(response.notes);
        } else {
          $(`.ala-carte-notes[data-service-id="${serviceId}"]`).val('');
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading ala carte process data:', error);
        $(`.ala-carte-staff-id[data-service-id="${serviceId}"]`).val('').trigger('change');
        $(`.ala-carte-start-time[data-service-id="${serviceId}"]`).val('');
        $(`.ala-carte-pickup-time[data-service-id="${serviceId}"]`).val('');
        $(`.ala-carte-notes[data-service-id="${serviceId}"]`).val('');
      }
    });
  }

  function saveAlaCarteProcess(appointmentId, secondaryServiceId) {
    const staffId = $(`.ala-carte-staff-id[data-service-id="${secondaryServiceId}"]`).val();
    const date = $(`.ala-carte-date[data-service-id="${secondaryServiceId}"]`).val();
    const startTime = $(`.ala-carte-start-time[data-service-id="${secondaryServiceId}"]`).val();
    const pickupTime = $(`.ala-carte-pickup-time[data-service-id="${secondaryServiceId}"]`).val();
    const notes = $(`.ala-carte-notes[data-service-id="${secondaryServiceId}"]`).val();

    if (!staffId) {
      $('#alert_message').text('Please select a staff member for this service.');
      alert_modal.showModal();
      return;
    }

    if (!date) {
      $('#alert_message').text('Please select a date.');
      alert_modal.showModal();
      return;
    }

    if (!startTime || !pickupTime) {
      $('#alert_message').text('Please fill in all required fields (Start Time and Pickup Time).');
      alert_modal.showModal();
      return;
    }

    $.ajax({
      url: '{{ route("save-ala-carte-process", ":id") }}'.replace(':id', appointmentId),
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        secondary_service_id: secondaryServiceId,
        staff_id: staffId,
        date: date,
        start_time: startTime,
        pickup_time: pickupTime,
        notes: notes || ''
      },
      success: function(response) {
        if (response.status) {
          $('#success_message').text(response.message || 'Process saved successfully!');
          success_modal.showModal();
        } else {
          $('#alert_message').text(response.message || 'Error saving process.');
          alert_modal.showModal();
        }
      },
      error: function(xhr, status, error) {
        console.error('Error saving ala carte process:', error);
        $('#alert_message').text('Error saving process. Please try again.');
        alert_modal.showModal();
      }
    });
  }

  function exportBoardingReportPDF() {
    const medsList = getBoardingMedicationsData();
    const hasMedication = medsList.length > 0 ? '1' : '0';
    const medsAm = hasMedication;
    const medsPm = hasMedication;
    const careNotesByPet = {};
    $('.boarding-pet-section').each(function() {
      const $section = $(this);
      const petId = String($section.data('pet-id') || '').trim();
      if (!petId) {
        return;
      }

      const note = ($section.find('.boarding-care-notes').val() || '').trim();
      if (note !== '') {
        careNotesByPet[petId] = note;
      }
    });

    const careNotes = ($('#notes').val() || '').trim();
    let exportUrl = '{{ route("export-boarding-detail-report-pdf", $appointment->id) }}' + '?meds_am=' + medsAm + '&meds_pm=' + medsPm;
    if (Object.keys(careNotesByPet).length > 0) {
      exportUrl += '&care_notes_by_pet=' + encodeURIComponent(JSON.stringify(careNotesByPet));
    }
    if (careNotes !== '') {
      exportUrl += '&care_notes=' + encodeURIComponent(careNotes);
    }
    window.open(exportUrl, '_blank');
  }

  @if ($appointment->status === 'completed')
  (function() {
    var checkoutSaved = @json(!empty($checkout->date));
    if (!checkoutSaved) {
      var now = new Date();
      var dateField = document.getElementById('checkout_date');
      var pickupTimeField = document.getElementById('checkout_pickup_time');
      if (dateField) {
        var y = now.getFullYear();
        var mo = String(now.getMonth() + 1).padStart(2, '0');
        var d = String(now.getDate()).padStart(2, '0');
        dateField.value = y + '-' + mo + '-' + d;
      }
      if (pickupTimeField) {
        var h = String(now.getHours()).padStart(2, '0');
        var mi = String(now.getMinutes()).padStart(2, '0');
        pickupTimeField.value = h + ':' + mi;
      }
    }

    const checkoutDateField = document.getElementById('checkout_date');
    if (checkoutDateField) {
      checkoutDateField.addEventListener('change', function () {
        if (typeof updateTotals === 'function') {
          updateTotals();
        }
      });
    }

    if (typeof updateTotals === 'function') {
      updateTotals();
    }
  })();
  @endif
</script>
@endsection