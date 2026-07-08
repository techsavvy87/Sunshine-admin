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
  .questionnaire-detail {
    min-height: 1rem;
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
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Appointment Detail</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li><a href="{{ route('service-dashboard', $appointment->service_id) }}">Appointments</a></li>
      <li class="opacity-80">Detail</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  @php
    $archiveAgreementFlows = (isBoardingService($appointment->service) && isset($checkin) && $checkin && is_array($checkin->flows))
      ? $checkin->flows
      : [];
    $archiveAgreementOwnerName = trim((string) ($archiveAgreementFlows['boarding_owner_full_name'] ?? ''));
    $archiveAgreementSignatureData = trim((string) ($archiveAgreementFlows['boarding_signature_data'] ?? ''));
    $archiveAgreementAccepted = in_array(($archiveAgreementFlows['boarding_agreement_accepted'] ?? null), [true, 'true', 1, '1'], true);
    $archiveAgreementVetAuthorized = in_array(($archiveAgreementFlows['boarding_vet_authorized'] ?? null), [true, 'true', 1, '1'], true);
    $archiveAgreementIsSigned = $archiveAgreementAccepted
      && $archiveAgreementVetAuthorized
      && $archiveAgreementOwnerName !== ''
      && $archiveAgreementSignatureData !== '';

    $archiveAgreementSignedAt = null;
    $archiveAgreementSignedAtRaw = trim((string) ($archiveAgreementFlows['boarding_signature_signed_at'] ?? ''));
    $archiveAgreementSignedDateRaw = trim((string) ($archiveAgreementFlows['boarding_signature_date'] ?? ''));
    if ($archiveAgreementSignedAtRaw !== '') {
      try {
        $archiveAgreementSignedAt = \Carbon\Carbon::parse($archiveAgreementSignedAtRaw);
      } catch (\Throwable $e) {
        $archiveAgreementSignedAt = null;
      }
    }
    if (!$archiveAgreementSignedAt && $archiveAgreementSignedDateRaw !== '') {
      try {
        $archiveAgreementSignedAt = \Carbon\Carbon::parse($archiveAgreementSignedDateRaw);
      } catch (\Throwable $e) {
        $archiveAgreementSignedAt = null;
      }
    }

    $archiveAgreementSignedOnLabel = $archiveAgreementSignedAt
      ? $archiveAgreementSignedAt->format('M j, Y')
      : ($archiveAgreementSignedDateRaw !== '' ? $archiveAgreementSignedDateRaw : 'N/A');
    $archiveAgreementSignedAtLabel = $archiveAgreementSignedAt
      ? $archiveAgreementSignedAt->format('M j, Y g:i A')
      : ($archiveAgreementSignedDateRaw !== '' ? $archiveAgreementSignedDateRaw : 'N/A');
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
      <p class="font-medium">Date: </p>
      <p class="text-base-content/70">{{ \Carbon\Carbon::parse($appointment->date)->format('F j, Y') }}</p>
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Time: </p>
      <p class="text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'N/A' }}</p>
      @if($appointment->start_time && $appointment->end_time)
      <p>-</p>
      @endif
      <p class="text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'N/A' }}</p>
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Status: </p>
      @if($appointment->status === 'cancelled')
        <div class="badge badge-soft badge-error badge-sm">{{ ucfirst($appointment->status) }}</div>
      @else
      <div class="badge badge-soft badge-success badge-sm">{{ ucfirst($appointment->status) }}</div>
      @endif
    </div>
    @if($appointment->additional_service_ids)
      @php
        $additionalIds = explode(',', $appointment->additional_service_ids);
        $additionalServices = \App\Models\Service::whereIn('id', $additionalIds)->get();
      @endphp
      @if($additionalServices->count() > 0)
        <div class="flex items-center gap-2">
          <p class="font-medium">Additional: </p>
          <p class="text-base-content/70">{{ $additionalServices->pluck('name')->join(', ') }}</p>
        </div>
      @endif
    @endif
    @if ($appointment->estimated_price)
    <div class="flex items-center gap-2">
      <p class="font-medium">Estimated Price: </p>
      <p class="text-base-content/70">${{ number_format($appointment->estimated_price, 2) }}</p>
    </div>
    @php
      $archiveFleaTickFlows = $checkin && $checkin->flows
        ? (is_array($checkin->flows) ? $checkin->flows : json_decode($checkin->flows, true))
        : [];
      $archiveFleaTickBreakdown = isBoardingService($appointment->service)
        ? getBoardingFleaTickBreakdown($appointment, is_array($archiveFleaTickFlows) ? $archiveFleaTickFlows : [])
        : ['amount' => 0];
    @endphp
    @if (!empty($archiveFleaTickBreakdown['amount']))
    <div class="flex items-center gap-2">
      <p class="font-medium">Flea/Tick Fee: </p>
      <p class="text-base-content/70">${{ number_format($archiveFleaTickBreakdown['amount'], 2) }}</p>
    </div>
    @endif
    @endif
  </div>
  <div class="mt-3 grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5 2xl:col-span-5">
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
                    <a href="mailto:{{ $appointment->customer->email }}" class="btn btn-sm btn-outline btn-secondary">
                      <span class="iconify lucide--mail size-4"></span>
                      <span class="hidden sm:inline">Email</span>
                    </a>
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
            </div>
          </div>
        </div>
      </div>
      @if ($archiveAgreementIsSigned)
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body py-4">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="font-medium">Signed Boarding Agreement</p>
              <div class="mt-2 space-y-1 text-sm text-base-content/80">
                <p><span class="font-medium text-base-content">Status:</span> Signed</p>
                <p><span class="font-medium text-base-content">Signed by:</span> {{ $archiveAgreementOwnerName }}</p>
                <p><span class="font-medium text-base-content">Signed on:</span> {{ $archiveAgreementSignedOnLabel }}</p>
              </div>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('archive_boarding_signed_agreement_modal')?.showModal()">
              View Signed Agreement
            </button>
          </div>
        </div>
      </div>

      <dialog id="archive_boarding_signed_agreement_modal" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="font-semibold text-lg">Signed Boarding Agreement</h3>

          <div class="mt-4 rounded-box border border-base-300 bg-base-100 p-3 text-sm text-base-content/80 space-y-2">
            <p>
              <span class="font-medium text-base-content">Release and waiver:</span>
              I understand boarding activities carry inherent risks and that The Sunshine Spot is not liable for injury, illness, or loss during my pet's stay, except where prohibited by law.
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
              I acknowledge and agree to follow the facility's boarding policies, pickup requirements, cancellation policy, and payment terms.
            </p>
          </div>

          <div class="mt-4 space-y-2 text-sm">
            <p><span>{{ $archiveAgreementAccepted ? '☑' : '☐' }}</span> I have read and agree to the boarding agreement</p>
            <p><span>{{ $archiveAgreementVetAuthorized ? '☑' : '☐' }}</span> I authorize the facility to seek veterinary treatment if needed</p>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <p class="text-sm font-medium">Owner full name</p>
              <p class="text-sm text-base-content/80">{{ $archiveAgreementOwnerName !== '' ? $archiveAgreementOwnerName : 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium">Signed date/time</p>
              <p class="text-sm text-base-content/80">{{ $archiveAgreementSignedAtLabel }}</p>
            </div>
          </div>

          <div class="mt-4">
            <p class="text-sm font-medium mb-2">Signature</p>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
              @if ($archiveAgreementSignatureData !== '')
                <img src="{{ $archiveAgreementSignatureData }}" alt="Owner Signature" class="w-full max-h-56 object-contain" />
              @else
                <p class="text-sm text-base-content/70">Signature not available.</p>
              @endif
            </div>
          </div>

          <div class="modal-action">
            <form method="dialog">
              <button class="btn btn-primary btn-sm">Close</button>
            </form>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
      @endif
      @if ($appointment->status !== 'cancelled')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">{{ $appointment->service->name }} Report</div>
            @if (isBoardingService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                <!-- Check-in Details -->
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkin) && $checkin && $checkin->flows && is_array($checkin->flows))
                <div class="border-b border-base-300 pb-4">                  
                  <div class="text-sm space-y-4 ms-2">
                    @if(isset($checkin->flows['pickup_datetime']) || isset($checkin->flows['trip_location']) || isset($checkin->flows['trip_phone']) || isset($checkin->flows['alternate_contact_name']) || isset($checkin->flows['alternate_contact_phone']) || isset($checkin->flows['trip_notes']))
                    <div>
                      <p class="font-semibold mb-2 text-base">Trip Information</p>
                      <div class="space-y-2 ms-2">
                        @if(isset($checkin->flows['pickup_datetime']) && $checkin->flows['pickup_datetime'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Confirm pickup date and time:</p>
                          <p class="text-sm text-base-content/70">
                            @php
                              try {
                                $dt = \Carbon\Carbon::parse($checkin->flows['pickup_datetime']);
                                echo $dt->format('M j, Y g:i A');
                              } catch (\Exception $e) {
                                echo $checkin->flows['pickup_datetime'];
                              }
                            @endphp
                          </p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['trip_location']) && $checkin->flows['trip_location'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Trip location:</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['trip_location'] }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['trip_phone']) && $checkin->flows['trip_phone'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Trip phone number:</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['trip_phone'] }}</p>
                        </div>
                        @endif
                        @if((isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name']) || (isset($checkin->flows['alternate_contact_phone']) && $checkin->flows['alternate_contact_phone']))
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Alternate contact:</p>
                          <p class="text-sm text-base-content/70">
                            @if(isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name'])
                              {{ $checkin->flows['alternate_contact_name'] }}
                            @endif
                            @if(isset($checkin->flows['alternate_contact_phone']) && $checkin->flows['alternate_contact_phone'])
                              @if(isset($checkin->flows['alternate_contact_name']) && $checkin->flows['alternate_contact_name'])
                                - 
                              @endif
                              {{ $checkin->flows['alternate_contact_phone'] }}
                            @endif
                          </p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['trip_notes']) && $checkin->flows['trip_notes'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Notes (authorized pickup & payment arrangement):</p>
                          <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $checkin->flows['trip_notes'] }}</p>
                        </div>
                        @endif
                      </div>
                    </div>
                    @endif

                    @if(isset($checkin->flows['vet_name']) || isset($checkin->flows['vet_phone']) || isset($checkin->flows['vet_notification']) || isset($checkin->flows['health_status']) || isset($checkin->flows['medical_issues']) || isset($checkin->flows['flea_tick_treatment']) || isset($checkin->flows['pet_notes']) || isset($checkin->flows['has_leash']) || isset($checkin->flows['has_collar']) || isset($checkin->flows['has_other_items']) || isset($checkin->flows['other_items_description']))
                    <div>
                      <p class="font-semibold mb-2 text-base">Pet Information</p>
                      <div class="space-y-2 ms-2">
                        @if(isset($checkin->flows['has_leash']) || isset($checkin->flows['has_collar']) || isset($checkin->flows['has_other_items']) || isset($checkin->flows['other_items_description']))
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Items:</p>
                          <div class="space-y-1 ms-2">
                            @if(isset($checkin->flows['other_items_description']) && $checkin->flows['other_items_description'])
                            <p class="text-base-content/70 text-xs mt-1 ms-5 whitespace-pre-wrap">{{ $checkin->flows['other_items_description'] }}</p>
                            @endif
                          </div>
                        </div>
                        @endif
                      </div>
                    </div>
                    @endif

                    <!-- Dispense Information -->
                    @if(isset($checkin->flows['food_brand']) || isset($checkin->flows['feeding_am']) || isset($checkin->flows['feeding_pm']) || isset($checkin->flows['food_quantity']) || isset($checkin->flows['food_starting_amount']) || isset($checkin->flows['food_description']) || isset($checkin->flows['additional_feedings']) || isset($checkin->flows['additional_feedings_am']) || isset($checkin->flows['additional_feedings_pm']) || isset($checkin->flows['medications']) || isset($checkin->flows['medications_am']) || isset($checkin->flows['medications_pm']) || (isset($checkin->flows['meds_list']) && is_array($checkin->flows['meds_list']) && count($checkin->flows['meds_list']) > 0))
                    <div>
                      <p class="font-semibold mb-2 text-base">Dispense Information</p>
                      <div class="space-y-2 ms-2">
                        @if(isset($checkin->flows['food_brand']) && $checkin->flows['food_brand'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Food (name of brand):</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['food_brand'] }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['feeding_am']) || isset($checkin->flows['feeding_pm']) || (isset($checkin->flows['feeding_time']) && $checkin->flows['feeding_time']))
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Feeding:</p>
                          <div class="mb-1 ms-2">
                            @php
                              $feedingAm = false;
                              $feedingPm = false;
                              if (isset($checkin->flows['feeding_am']) && ($checkin->flows['feeding_am'] === true || $checkin->flows['feeding_am'] === 'true')) {
                                $feedingAm = true;
                              }
                              if (isset($checkin->flows['feeding_pm']) && ($checkin->flows['feeding_pm'] === true || $checkin->flows['feeding_pm'] === 'true')) {
                                $feedingPm = true;
                              }
                              // Backward compatibility: check old feeding_time format
                              if (!$feedingAm && !$feedingPm && isset($checkin->flows['feeding_time'])) {
                                $feedingTime = $checkin->flows['feeding_time'];
                                if ($feedingTime === 'AM' || $feedingTime === 'AM/PM') {
                                  $feedingAm = true;
                                }
                                if ($feedingTime === 'PM' || $feedingTime === 'AM/PM') {
                                  $feedingPm = true;
                                }
                              }
                            @endphp
                            <div class="flex items-center gap-4">
                              <div class="flex items-center gap-1">
                                <span>{{ $feedingAm ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">AM</span>
                              </div>
                              <div class="flex items-center gap-1">
                                <span>{{ $feedingPm ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">PM</span>
                              </div>
                            </div>
                          </div>
                        </div>
                        @endif
                        @if(isset($checkin->flows['food_quantity']) && $checkin->flows['food_quantity'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Quantity:</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['food_quantity'] }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['food_starting_amount']) && $checkin->flows['food_starting_amount'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Starting amount:</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['food_starting_amount'] }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['food_description']) && $checkin->flows['food_description'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Description:</p>
                          <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $checkin->flows['food_description'] }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['additional_feedings']) || isset($checkin->flows['additional_feedings_am']) || isset($checkin->flows['additional_feedings_pm']))
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Additional feedings:</p>
                          <div class="mb-1 ms-2">
                            <div class="flex items-center gap-4">
                              <div class="flex items-center gap-1">
                                <span>{{ isset($checkin->flows['additional_feedings_am']) && ($checkin->flows['additional_feedings_am'] === true || $checkin->flows['additional_feedings_am'] === 'true') ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">AM</span>
                              </div>
                              <div class="flex items-center gap-1">
                                <span>{{ isset($checkin->flows['additional_feedings_pm']) && ($checkin->flows['additional_feedings_pm'] === true || $checkin->flows['additional_feedings_pm'] === 'true') ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">PM</span>
                              </div>
                            </div>
                          </div>
                          @if(isset($checkin->flows['additional_feedings']) && $checkin->flows['additional_feedings'])
                          <p class="text-sm text-base-content/70 whitespace-pre-wrap ms-2">{{ $checkin->flows['additional_feedings'] }}</p>
                          @endif
                        </div>
                        @endif
                        @php
                          $medicationRows = isset($checkin->flows['meds_list']) && is_array($checkin->flows['meds_list']) ? $checkin->flows['meds_list'] : [];
                        @endphp
                        @if(count($medicationRows) > 0)
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Medications:</p>
                          <div class="space-y-2 ms-2">
                            @foreach($medicationRows as $medicationRow)
                              @php
                                if (!is_array($medicationRow)) {
                                  continue;
                                }

                                $conditionValue = $medicationRow['condition'] ?? '';
                                $mealConditionValue = $medicationRow['meal_condition'] ?? $conditionValue;
                                if ($mealConditionValue === 'after_meals') {
                                  $mealConditionValue = 'after_meal';
                                }
                                $conditionLabel = match ($mealConditionValue) {
                                  'after_meal' => 'After Meal',
                                  'before_meal' => 'Before Meal',
                                  'empty_stomach' => 'Empty Stomach',
                                  default => '',
                                };
                                $customTime = $medicationRow['custom_time'] ?? '';
                                $dispenseLabels = [];
                                if (!empty($medicationRow['dispense_am']) && ($medicationRow['dispense_am'] === true || $medicationRow['dispense_am'] === 'true')) {
                                  $dispenseLabels[] = 'AM';
                                }
                                if (!empty($medicationRow['dispense_pm']) && ($medicationRow['dispense_pm'] === true || $medicationRow['dispense_pm'] === 'true')) {
                                  $dispenseLabels[] = 'PM';
                                }
                                if (!empty($medicationRow['dispense_rest']) && ($medicationRow['dispense_rest'] === true || $medicationRow['dispense_rest'] === 'true')) {
                                  $dispenseLabels[] = 'Rest';
                                }
                                if (!empty($medicationRow['dispense_before_bed']) && ($medicationRow['dispense_before_bed'] === true || $medicationRow['dispense_before_bed'] === 'true')) {
                                  $dispenseLabels[] = 'Before Bed';
                                }
                                if (!empty($medicationRow['dispense_prn']) && ($medicationRow['dispense_prn'] === true || $medicationRow['dispense_prn'] === 'true')) {
                                  $dispenseLabels[] = 'PRN';
                                }
                                if (!empty($medicationRow['dispense_custom_time']) && ($medicationRow['dispense_custom_time'] === true || $medicationRow['dispense_custom_time'] === 'true')) {
                                  $dispenseLabels[] = !empty($customTime) ? 'Custom Time (' . $customTime . ')' : 'Custom Time';
                                }
                                if (empty($dispenseLabels) && $conditionValue === 'before_sleep') {
                                  $dispenseLabels[] = 'Before Bed';
                                }
                              @endphp
                              <div class="text-sm text-base-content/70">
                                <p><span class="font-medium text-base-content/80">Name:</span> {{ $medicationRow['name'] ?? '-' }}</p>
                                <p><span class="font-medium text-base-content/80">Dosage/Instruction:</span> {{ $medicationRow['amount'] ?? '-' }}</p>
                                <p><span class="font-medium text-base-content/80">Dispense:</span> {{ count($dispenseLabels) ? implode(', ', $dispenseLabels) : '-' }}</p>
                                <p>
                                  <span class="font-medium text-base-content/80">Meal Condition:</span>
                                  {{ $conditionLabel ?: '-' }}
                                </p>
                              </div>
                            @endforeach
                          </div>
                        </div>
                        @elseif(isset($checkin->flows['medications']) || isset($checkin->flows['medications_am']) || isset($checkin->flows['medications_pm']))
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Medications:</p>
                          <div class="mb-1 ms-2">
                            <div class="flex items-center gap-4">
                              <div class="flex items-center gap-1">
                                <span>{{ isset($checkin->flows['medications_am']) && ($checkin->flows['medications_am'] === true || $checkin->flows['medications_am'] === 'true') ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">AM</span>
                              </div>
                              <div class="flex items-center gap-1">
                                <span>{{ isset($checkin->flows['medications_pm']) && ($checkin->flows['medications_pm'] === true || $checkin->flows['medications_pm'] === 'true') ? '☑' : '☐' }}</span>
                                <span class="text-base-content/70 text-sm">PM</span>
                              </div>
                            </div>
                          </div>
                          @if(isset($checkin->flows['medications']) && $checkin->flows['medications'])
                          <p class="text-sm text-base-content/70 whitespace-pre-wrap ms-2">{{ $checkin->flows['medications'] }}</p>
                          @endif
                        </div>
                        @endif
                      </div>
                    </div>
                    @endif

                    <!-- Assignment or location for visit -->
                    @if(isset($checkin->flows['location_type']) || isset($checkin->flows['location_details']))
                    <div>
                      <p class="font-semibold mb-2 text-base">Assignment or location for visit</p>
                      <div class="space-y-2 ms-2">
                        @if(isset($checkin->flows['location_type']) && $checkin->flows['location_type'])
                        <div>
                          <p class="text-sm text-base-content/70"><span class="font-medium text-base-content/80">Location type:</span> {{ ucfirst($checkin->flows['location_type']) }}</p>
                        </div>
                        @endif
                        @if(isset($checkin->flows['location_details']) && $checkin->flows['location_details'])
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Location details:</p>
                          <p class="text-sm text-base-content/70">{{ $checkin->flows['location_details'] }}</p>
                        </div>
                        @endif
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif

                <!-- Issues (from Process) -->
                @if(isset($processes) && $processes->count() > 0)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Issues</h4>
                  @php
                    $processTableRows = [];
                    $appointmentId = $appointment->id;
                    $bodyPartsMap = [
                      'nose' => 'Nose',
                      'ears' => 'Ears',
                      'eyes' => 'Eyes',
                      'mouth' => 'Mouth',
                      'body_coat' => 'Body/Coat',
                      'paws_feet' => 'Paws/Feet',
                      'abdomen' => 'Abdomen',
                      'digestive' => 'Digestive',
                      'diarrhea' => 'Diarrhea',
                    ];
                    $conciergeItems = ['nose', 'eyes', 'ears', 'mouth', 'skin', 'paws', 'tail', 'genitals', 'overall'];

                    foreach ($processes as $processItem) {
                      $processDate = $processItem->date ? \Carbon\Carbon::parse($processItem->date)->format('M j, Y') : '';
                      $defaultRowTime = '—';
                      if ($processItem->start_time) {
                        try {
                          $defaultRowTime = \Carbon\Carbon::createFromFormat('H:i:s', $processItem->start_time)->format('g:i A');
                        } catch (\Exception $e) {
                          try {
                            $defaultRowTime = \Carbon\Carbon::createFromFormat('H:i', $processItem->start_time)->format('g:i A');
                          } catch (\Exception $e2) {
                            $defaultRowTime = (string) $processItem->start_time;
                          }
                        }
                      }
                      $rowIssues = [];
                      $rowTreatmentStrings = [];
                      $statusLabel = '—';
                      $flows = $processItem->flows;
                      if (!is_array($flows)) {
                        $flows = [];
                      }
                      $hasNoseTailIssues = false;
                      if (!empty($flows)) {
                        $reportsAmIds = array_map('intval', (array) ($flows['reports_am']['selected_pet_ids'] ?? []));
                        $reportsPmIds = array_map('intval', (array) ($flows['reports_pm']['selected_pet_ids'] ?? []));
                        if (in_array((int) $appointmentId, $reportsAmIds, true)) {
                          $rowIssues[] = 'Do not eat AM Meals';
                        }
                        if (in_array((int) $appointmentId, $reportsPmIds, true)) {
                          $rowIssues[] = 'Do not eat PM Meals';
                        }
                        $checkData = $flows['check_pet']['check_data'][$appointmentId] ?? [];
                        if (is_array($checkData)) {
                          foreach ($checkData as $partKey => $partData) {
                            if (is_array($partData) && ($partData['status'] ?? '') === 'issue') {
                              $rowIssues[] = $bodyPartsMap[$partKey] ?? ucfirst(str_replace('_', ' ', $partKey));
                              $hasNoseTailIssues = true;
                            }
                          }
                        }
                        $hasTreatmentIssues = false;
                        if (!empty($flows['treatment_issues']) && is_array($flows['treatment_issues'])) {
                          foreach ($flows['treatment_issues'] as $issue) {
                            if (!empty($issue['issue'])) {
                              $rowIssues[] = ucfirst(str_replace('_', ' ', $issue['issue']));
                              $hasTreatmentIssues = true;
                            }
                          }
                        }
                        foreach ($conciergeItems as $item) {
                          if (isset($flows['concierge_' . $item]) && $flows['concierge_' . $item] === 'issue') {
                            $issueDetails = [];
                            $issueFields = [];
                            switch ($item) {
                              case 'nose': $issueFields = ['discharge', 'dryness', 'cracking']; break;
                              case 'eyes': $issueFields = ['redness', 'cloudiness', 'discharge']; break;
                              case 'ears': $issueFields = ['odor', 'redness', 'swelling', 'buildup']; break;
                              case 'mouth': $issueFields = ['tartar', 'broken_teeth', 'foul_breath']; break;
                              case 'skin': $issueFields = ['dryness', 'irritation', 'hot_spots', 'lumps']; break;
                              case 'paws': $issueFields = ['cracking', 'irritation', 'swelling']; break;
                              case 'tail': $issueFields = ['irritation', 'swelling']; break;
                              case 'genitals': $issueFields = ['discharge', 'irritation', 'swelling']; break;
                              default: $issueFields = [];
                            }
                            foreach ($issueFields as $field) {
                              if (!empty($flows['concierge_' . $item . '_' . $field]) && ($flows['concierge_' . $item . '_' . $field] === true || $flows['concierge_' . $item . '_' . $field] === 'true')) {
                                $issueDetails[] = ucfirst(str_replace('_', ' ', $field));
                              }
                            }
                            $treatmentText = ucfirst($item);
                            if (count($issueDetails) > 0) {
                              $treatmentText .= ' (' . implode(', ', $issueDetails) . ')';
                            }
                            $rowIssues[] = $treatmentText;
                            $hasNoseTailIssues = true;
                          }
                        }
                        if (!empty($flows['am_meal_dispense_not_eating']) && ($flows['am_meal_dispense_not_eating'] === true || $flows['am_meal_dispense_not_eating'] === 'true')) {
                          if (!in_array('Do not eat AM Meals', $rowIssues, true)) {
                            $rowIssues[] = 'Do not eat AM Meals';
                          }
                        }
                        if (!empty($flows['pm_meal_dispense_not_eating']) && ($flows['pm_meal_dispense_not_eating'] === true || $flows['pm_meal_dispense_not_eating'] === 'true')) {
                          if (!in_array('Do not eat PM Meals', $rowIssues, true)) {
                            $rowIssues[] = 'Do not eat PM Meals';
                          }
                        }
                        $treatmentsTlrResults = $flows['treatments_tlr']['results'][$appointmentId] ?? [];
                        $resultVal = is_array($treatmentsTlrResults) ? ($treatmentsTlrResults['result'] ?? '') : '';
                        $statusLabel = $resultVal === 'continue' ? 'Continue' : ($resultVal === 'resolved' ? 'Resolved' : ($resultVal === 'escalate' ? 'Escalate' : '—'));
                        $treatmentPlanData = $flows['treatment_plan']['treatment_data'][$appointmentId] ?? [];
                        $treatmentDetail = is_array($treatmentPlanData) ? ($treatmentPlanData['detail'] ?? '') : '';
                        if ($treatmentDetail !== '') {
                          $rowTreatmentStrings[] = $treatmentDetail;
                        }
                        if (!empty($flows['treatment_issues']) && is_array($flows['treatment_issues'])) {
                          foreach ($flows['treatment_issues'] as $issue) {
                            if (!empty($issue['inhouse_treatment'])) {
                              $rowTreatmentStrings[] = 'In-house: ' . $issue['inhouse_treatment'];
                            }
                            if (!empty($issue['vet_treatment'])) {
                              $rowTreatmentStrings[] = 'Vet: ' . $issue['vet_treatment'];
                            }
                          }
                        }
                        foreach ($conciergeItems as $item) {
                          if (isset($flows['concierge_' . $item]) && $flows['concierge_' . $item] === 'issue' && !empty($flows['concierge_' . $item . '_notes'])) {
                            $rowTreatmentStrings[] = ucfirst($item) . ': ' . $flows['concierge_' . $item . '_notes'];
                          }
                        }
                        if (!empty($flows['am_meal_dispense_must_eat'])) {
                          $rowTreatmentStrings[] = 'AM must eat: ' . $flows['am_meal_dispense_must_eat'];
                        }
                        if (!empty($flows['am_med_dispense_instructions'])) {
                          $rowTreatmentStrings[] = 'AM instructions: ' . $flows['am_med_dispense_instructions'];
                        }
                        if (!empty($flows['pm_meal_dispense_must_eat'])) {
                          $rowTreatmentStrings[] = 'PM must eat: ' . $flows['pm_meal_dispense_must_eat'];
                        }
                        if (!empty($flows['pm_med_dispense_instructions'])) {
                          $rowTreatmentStrings[] = 'PM instructions: ' . $flows['pm_med_dispense_instructions'];
                        }
                        if (!empty($flows['am_med_dispense_must_receive']) && ($flows['am_med_dispense_must_receive'] === true || $flows['am_med_dispense_must_receive'] === 'true')) {
                          $rowTreatmentStrings[] = 'AM meds dispensed';
                        }
                        if (!empty($flows['pm_med_dispense_must_receive']) && ($flows['pm_med_dispense_must_receive'] === true || $flows['pm_med_dispense_must_receive'] === 'true')) {
                          $rowTreatmentStrings[] = 'PM meds dispensed';
                        }
                      }
                      $rowTime = $defaultRowTime;
                      if (!empty($flows)) {
                        $parseTime = function($t) {
                          if (!$t || trim((string)$t) === '') return null;
                          try {
                            return \Carbon\Carbon::parse($t)->format('g:i A');
                          } catch (\Exception $e) {
                            return null;
                          }
                        };
                        if ($hasNoseTailIssues && !empty($flows['nose_tail_time'])) {
                          $rowTime = $parseTime($flows['nose_tail_time']) ?: $rowTime;
                        } elseif (in_array('Do not eat AM Meals', $rowIssues, true)) {
                          $ram = $flows['reports_am'] ?? null;
                          $t = (is_array($ram) ? ($ram['process_time'] ?? $ram['processTime'] ?? null) : null) ?: ($flows['am_meal_dispense_time'] ?? null);
                          if ($t) $rowTime = $parseTime($t) ?: $rowTime;
                        } elseif (in_array('Do not eat PM Meals', $rowIssues, true)) {
                          $rpm = $flows['reports_pm'] ?? null;
                          $t = (is_array($rpm) ? ($rpm['process_time'] ?? $rpm['processTime'] ?? null) : null) ?: ($flows['pm_meal_dispense_time'] ?? null);
                          if ($t) $rowTime = $parseTime($t) ?: $rowTime;
                        } elseif (!empty($hasTreatmentIssues)) {
                          $ttlr = $flows['treatments_tlr'] ?? null;
                          $tlist = $flows['treatment_list_tlr'] ?? null;
                          $t = (is_array($ttlr) ? ($ttlr['process_time'] ?? $ttlr['processTime'] ?? null) : null) ?: (is_array($tlist) ? ($tlist['process_time'] ?? $tlist['processTime'] ?? null) : null);
                          if ($t) $rowTime = $parseTime($t) ?: $rowTime;
                        }
                        if ($rowTime === $defaultRowTime && !empty($flows['rest_1200_time'])) {
                          $rowTime = $parseTime($flows['rest_1200_time']) ?: $rowTime;
                        }
                      }
                      $dateTimeDisplay = $processDate ?: '—';
                      if ($rowTime !== '—') {
                        $dateTimeDisplay = trim($dateTimeDisplay . ' ' . $rowTime);
                      }
                      $processTableRows[] = [
                        'date_time' => $dateTimeDisplay,
                        'issues' => $rowIssues,
                        'status' => $statusLabel,
                        'treatment' => count($rowTreatmentStrings) > 0 ? implode('; ', $rowTreatmentStrings) : '—',
                      ];
                    }
                  @endphp
                  <div class="mt-3 overflow-x-auto">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Date/Time</th>
                          <th>Issues</th>
                          <th>Status</th>
                          <th>Treatment</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($processTableRows as $row)
                        <tr>
                          <td class="text-sm">{{ $row['date_time'] }}</td>
                          <td class="text-sm">{{ count($row['issues']) > 0 ? implode(', ', $row['issues']) : '—' }}</td>
                          <td class="text-sm">{{ $row['status'] }}</td>
                          <td class="text-sm max-w-xs">{{ Str::limit($row['treatment'], 60) ?: '—' }}</td>
                        </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
                @endif

                @if($appointment->additional_service_ids)
                  @php
                    $additionalIds = explode(',', $appointment->additional_service_ids);
                    $additionalServices = \App\Models\Service::whereIn('id', $additionalIds)->get();
                  @endphp
                  @if($additionalServices->count() > 0)
                    @php
                      // Check if any process has additional service details
                      $hasAdditionalServiceDetails = false;
                      if (isset($processes) && $processes->count() > 0) {
                        foreach ($processes as $processItem) {
                          if ($processItem->flows && is_array($processItem->flows)) {
                            foreach ($additionalServices as $addService) {
                              if (isset($processItem->flows['additional_service_' . $addService->id . '_start_time']) ||
                                  isset($processItem->flows['additional_service_' . $addService->id . '_end_time']) ||
                                  isset($processItem->flows['additional_service_' . $addService->id . '_notes'])) {
                                $hasAdditionalServiceDetails = true;
                                break 2;
                              }
                            }
                          }
                        }
                      }
                    @endphp
                    @if($hasAdditionalServiceDetails)
                    <div class="border-b border-base-300 pb-4">
                      <h4 class="font-semibold mb-3">Additional Services</h4>
                      <div class="space-y-4 ms-2">
                        @foreach($additionalServices as $addService)
                          @php
                            // Collect data from all processes for this service
                            $serviceStartTimes = [];
                            $serviceEndTimes = [];
                            $serviceNotes = [];
                            
                            if (isset($processes) && $processes->count() > 0) {
                              foreach ($processes as $processItem) {
                                if ($processItem->flows && is_array($processItem->flows)) {
                                  $processDate = $processItem->date ? \Carbon\Carbon::parse($processItem->date)->format('M j, Y') : '';
                                  
                                  if (isset($processItem->flows['additional_service_' . $addService->id . '_start_time']) && $processItem->flows['additional_service_' . $addService->id . '_start_time']) {
                                    $serviceStartTimes[] = [
                                      'time' => $processItem->flows['additional_service_' . $addService->id . '_start_time'],
                                      'date' => $processDate
                                    ];
                                  }
                                  if (isset($processItem->flows['additional_service_' . $addService->id . '_end_time']) && $processItem->flows['additional_service_' . $addService->id . '_end_time']) {
                                    $serviceEndTimes[] = [
                                      'time' => $processItem->flows['additional_service_' . $addService->id . '_end_time'],
                                      'date' => $processDate
                                    ];
                                  }
                                  if (isset($processItem->flows['additional_service_' . $addService->id . '_notes']) && $processItem->flows['additional_service_' . $addService->id . '_notes']) {
                                    $serviceNotes[] = [
                                      'notes' => $processItem->flows['additional_service_' . $addService->id . '_notes'],
                                      'date' => $processDate
                                    ];
                                  }
                                }
                              }
                            }
                          @endphp
                          @if(count($serviceStartTimes) > 0 || count($serviceEndTimes) > 0 || count($serviceNotes) > 0)
                          <div>
                            <p class="text-sm font-medium mb-2">{{ $addService->name }}</p>
                            <div class="space-y-2 ms-2">
                              @if(count($serviceStartTimes) > 0 || count($serviceEndTimes) > 0)
                              <div>
                                <div class="flex items-start gap-4">
                                  @if(count($serviceStartTimes) > 0)
                                  <div class="flex-1">
                                    <p class="font-medium text-sm text-base-content/80">Start Time:</p>
                                    @foreach($serviceStartTimes as $startTime)
                                      <p class="text-sm text-base-content/70">
                                        @if($startTime['date'])
                                          {{ $startTime['date'] }}
                                          @if($startTime['time'])
                                            @php
                                              try {
                                                $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $startTime['time'])->format('g:i A');
                                                echo ' ' . $timeFormatted;
                                              } catch (\Exception $e) {
                                                echo ' ' . $startTime['time'];
                                              }
                                            @endphp
                                          @endif
                                        @elseif($startTime['time'])
                                          @php
                                            try {
                                              $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $startTime['time'])->format('g:i A');
                                              echo $timeFormatted;
                                            } catch (\Exception $e) {
                                              echo $startTime['time'];
                                            }
                                          @endphp
                                        @endif
                                      </p>
                                    @endforeach
                                  </div>
                                  @endif
                                  @if(count($serviceEndTimes) > 0)
                                  <div class="flex-1">
                                    <p class="font-medium text-sm text-base-content/80">End Time:</p>
                                    @foreach($serviceEndTimes as $endTime)
                                      <p class="text-sm text-base-content/70">
                                        @if($endTime['date'])
                                          {{ $endTime['date'] }}
                                          @if($endTime['time'])
                                            @php
                                              try {
                                                $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $endTime['time'])->format('g:i A');
                                                echo ' ' . $timeFormatted;
                                              } catch (\Exception $e) {
                                                echo ' ' . $endTime['time'];
                                              }
                                            @endphp
                                          @endif
                                        @elseif($endTime['time'])
                                          @php
                                            try {
                                              $timeFormatted = \Carbon\Carbon::createFromFormat('H:i', $endTime['time'])->format('g:i A');
                                              echo $timeFormatted;
                                            } catch (\Exception $e) {
                                              echo $endTime['time'];
                                            }
                                          @endphp
                                        @endif
                                      </p>
                                    @endforeach
                                  </div>
                                  @endif
                                </div>
                              </div>
                              @endif
                              @if(count($serviceNotes) > 0)
                              <div>
                                <p class="font-medium text-sm text-base-content/80">Notes:</p>
                                @foreach($serviceNotes as $note)
                                  <div class="ms-2">
                                    @if($note['date'])
                                      <p class="text-sm text-base-content/70">{{ $note['date'] }}</p>
                                    @endif
                                    @if($note['notes'])
                                      <p class="text-sm text-base-content/70 whitespace-pre-wrap ms-2">{{ $note['notes'] }}</p>
                                    @endif
                                  </div>
                                @endforeach
                              </div>
                              @endif
                            </div>
                          </div>
                          @endif
                        @endforeach
                      </div>
                    </div>
                    @endif
                  @endif
                @endif

                <!-- Checkout Details -->
                @if(isset($checkout) && $checkout)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">Final Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($checkout->flows['rating']))
                    <div>
                      <p class="font-medium mb-1">Rating:</p>
                      <div class="ms-2">
                        @if($checkout->flows['rating'] === 'green')
                          <span class="badge badge-success badge-sm">Green</span>
                          <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                        @elseif($checkout->flows['rating'] === 'yellow')
                          <span class="badge badge-warning badge-sm">Yellow</span>
                          <span class="text-base-content/70 text-sm ms-2">(mild reaction to boarding)</span>
                          @if(isset($checkout->flows['rating_yellow_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                          @endif
                        @elseif($checkout->flows['rating'] === 'purple')
                          <span class="badge badge-error badge-sm">Purple</span>
                          <span class="text-base-content/70 text-sm ms-2">(reacts to boarding)</span>
                          @if(isset($checkout->flows['rating_purple_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                          @endif
                        @endif
                      </div>
                    </div>
                    @endif
                    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                    <div>
                      <p class="font-medium mb-1">Checkout Pictures:</p>
                      <div class="mt-2 flex flex-wrap gap-2 ms-2">
                        @foreach($checkout->flows['pictures'] as $picture)
                        <div class="relative">
                          <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                        </div>
                        @endforeach
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportBoardingReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isGroomingService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                <!-- Time/Notes Section -->
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($process) && $process)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Process</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $process->date ? \Carbon\Carbon::parse($process->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->start_time ? $process->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->pickup_time ? $process->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($process->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $process->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif
                @if(isset($checkin) && $checkin && $checkin->flows && is_array($checkin->flows))
                  <div class="border-b border-base-300 pb-4">
                    <h4 class="font-semibold mb-3">Initial Temperament Assessment</h4>
                    <div class="text-sm space-y-3 ms-2">
                      <div>
                        <p class="font-medium mb-1">Initial Greeting:</p>
                        <div class="space-y-1 ms-2">
                          <div class="flex items-start gap-2">
                            <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'approachable' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Approachable <span class="text-xs">(allows contact, loose body posture, will accept treats)</span></span>
                          </div>
                          <div class="flex items-start gap-2">
                            <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'shy' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Shy <span class="text-xs">(cautious, tail tucked, whale eye, does not want to be petted)</span></span>
                          </div>
                          <div class="flex items-start gap-2">
                            <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'uncomfortable' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Uncomfortable <span class="text-xs">(moves away, shows teeth, barks or snaps)</span></span>
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Body Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_body']) && $checkin->flows['touch_body'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_body']) && $checkin->flows['touch_body'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Legs Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_legs']) && $checkin->flows['touch_legs'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_legs']) && $checkin->flows['touch_legs'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Feet Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_feet']) && $checkin->flows['touch_feet'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_feet']) && $checkin->flows['touch_feet'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Tail Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_tail']) && $checkin->flows['touch_tail'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_tail']) && $checkin->flows['touch_tail'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Face Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_face']) && $checkin->flows['touch_face'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_face']) && $checkin->flows['touch_face'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium">Nails Touch:</span>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_nails']) && $checkin->flows['touch_nails'] === 'accept' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Accepts</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span>{{ isset($checkin->flows['touch_nails']) && $checkin->flows['touch_nails'] === 'react' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Reacts</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  @endif

                  @if(isset($process) && $process && $process->flows && is_array($process->flows))
                  <div class="border-b border-base-300 pb-4">
                    <h4 class="font-semibold mb-3">Process Activities</h4>
                    <div class="text-sm space-y-3 ms-2">
                      <div>
                        <p class="font-medium mb-1">Nail Trimming:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['nail_trimming']) && $process->flows['nail_trimming'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['nail_trimming']) && $process->flows['nail_trimming'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Ear Cleaning:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['ear_cleaning']) && $process->flows['ear_cleaning'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['ear_cleaning']) && $process->flows['ear_cleaning'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Wetting with Sprayer:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['wetting_sprayer']) && $process->flows['wetting_sprayer'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['wetting_sprayer']) && $process->flows['wetting_sprayer'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Shampooing:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['shampooing']) && $process->flows['shampooing'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['shampooing']) && $process->flows['shampooing'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Rinsing:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['rinsing']) && $process->flows['rinsing'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['rinsing']) && $process->flows['rinsing'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Drying:</p>
                        <div class="flex items-center gap-4 ms-2">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['drying']) && $process->flows['drying'] === 'accept' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Accepts</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['drying']) && $process->flows['drying'] === 'react' ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Reacts</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Brushing/Combing:</p>
                        <div class="flex items-center gap-4 ms-2 flex-wrap">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['brushing_body']) && ($process->flows['brushing_body'] === true || $process->flows['brushing_body'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Body</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['brushing_legs']) && ($process->flows['brushing_legs'] === true || $process->flows['brushing_legs'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Legs</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['brushing_feet']) && ($process->flows['brushing_feet'] === true || $process->flows['brushing_feet'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Feet</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['brushing_tail']) && ($process->flows['brushing_tail'] === true || $process->flows['brushing_tail'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Tail</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['brushing_face']) && ($process->flows['brushing_face'] === true || $process->flows['brushing_face'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Face</span>
                          </div>
                        </div>
                      </div>
                      <div>
                        <p class="font-medium mb-1">Clippers/Scissors:</p>
                        <div class="flex items-center gap-4 ms-2 flex-wrap">
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['clippers_body']) && ($process->flows['clippers_body'] === true || $process->flows['clippers_body'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Body</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['clippers_legs']) && ($process->flows['clippers_legs'] === true || $process->flows['clippers_legs'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Legs</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['clippers_feet']) && ($process->flows['clippers_feet'] === true || $process->flows['clippers_feet'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Feet</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['clippers_tail']) && ($process->flows['clippers_tail'] === true || $process->flows['clippers_tail'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Tail</span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span>{{ isset($process->flows['clippers_face']) && ($process->flows['clippers_face'] === true || $process->flows['clippers_face'] === 'true') ? '☑' : '☐' }}</span>
                            <span class="text-base-content/70">Face</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  @endif

                  @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                  <div class="pb-4">
                    <h4 class="font-semibold mb-3">Final Assessment</h4>
                    <div class="text-sm space-y-3 ms-2">
                      @if(isset($checkout->flows['rating']))
                      <div>
                        <p class="font-medium mb-1">Rating:</p>
                        <div class="ms-2">
                          @if($checkout->flows['rating'] === 'green')
                            <span class="badge badge-success badge-sm">Green</span>
                            <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                          @elseif($checkout->flows['rating'] === 'yellow')
                            <span class="badge badge-warning badge-sm">Yellow</span>
                            <span class="text-base-content/70 text-sm ms-2">(mild reaction to grooming)</span>
                            @if(isset($checkout->flows['rating_yellow_detail']))
                            <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                            @endif
                          @elseif($checkout->flows['rating'] === 'purple')
                            <span class="badge badge-error badge-sm">Purple</span>
                            <span class="text-base-content/70 text-sm ms-2">(reacts to grooming)</span>
                            @if(isset($checkout->flows['rating_purple_detail']))
                            <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                            @endif
                          @endif
                        </div>
                      </div>
                      @endif
                      @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                      <div>
                        <p class="font-medium mb-1">Checkout Pictures:</p>
                        <div class="mt-2 flex flex-wrap gap-2 ms-2">
                          @foreach($checkout->flows['pictures'] as $picture)
                          <div class="relative">
                            <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                          </div>
                          @endforeach
                        </div>
                      </div>
                      @endif
                    </div>
                  </div>
                  @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportGroomingReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isDaycareService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($process) && $process)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Process</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $process->date ? \Carbon\Carbon::parse($process->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->start_time ? $process->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->pickup_time ? $process->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($process->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $process->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($process) && $process && $process->flows && is_array($process->flows))
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">First Day Evaluation</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($process->flows['daycare_evaluation_date']))
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Date:</span>
                      <span class="text-base-content/70">{{ \Carbon\Carbon::parse($process->flows['daycare_evaluation_date'])->format('M j, Y') }}</span>
                    </div>
                    @endif

                    @if(isset($process->flows['daycare_evaluation_result']))
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Result:</span>
                      <div>
                        @if($process->flows['daycare_evaluation_result'] === 'passed_no_concerns')
                          <span class="badge badge-success badge-sm">Passed (no concerns)</span>
                        @elseif($process->flows['daycare_evaluation_result'] === 'passed_management_needed')
                          <span class="badge badge-warning badge-sm">Passed (management needed)</span>
                        @elseif($process->flows['daycare_evaluation_result'] === 'reintroduction')
                          <span class="badge badge-info badge-sm">Reintroduction</span>
                        @elseif($process->flows['daycare_evaluation_result'] === 'refer_to_trainer')
                          <span class="badge badge-error badge-sm">Refer to trainer</span>
                        @endif
                      </div>
                    </div>
                    @endif

                    <div>
                      <p class="font-medium mb-2">Socialization evaluation:</p>
                      <div class="space-y-2 ms-2">
                        @if(isset($process->flows['new_person_evaluation']))
                        <div class="flex items-center gap-2">
                          <span class="font-medium">New person:</span>
                          <div>
                            @if($process->flows['new_person_evaluation'] === 'accepted')
                              <span class="badge badge-success badge-sm">Accepted</span>
                            @elseif($process->flows['new_person_evaluation'] === 'issue_concern')
                              <span class="badge badge-error badge-sm">Issue/concern</span>
                            @endif
                          </div>
                        </div>
                        @endif

                        @if(isset($process->flows['new_dog_evaluation']))
                        <div class="flex items-center gap-2">
                          <span class="font-medium">New dog:</span>
                          <div>
                            @if($process->flows['new_dog_evaluation'] === 'accepted')
                              <span class="badge badge-success badge-sm">Accepted</span>
                            @elseif($process->flows['new_dog_evaluation'] === 'issue_concern')
                              <span class="badge badge-error badge-sm">Issue/concern</span>
                            @endif
                          </div>
                        </div>
                        @endif

                        @if(isset($process->flows['small_group_evaluation']))
                        <div class="flex items-center gap-2">
                          <span class="font-medium">Small group of dogs:</span>
                          <div>
                            @if($process->flows['small_group_evaluation'] === 'accepted')
                              <span class="badge badge-success badge-sm">Accepted</span>
                            @elseif($process->flows['small_group_evaluation'] === 'issue_concern')
                              <span class="badge badge-error badge-sm">Issue/concern</span>
                            @endif
                          </div>
                        </div>
                        @endif

                        @if(isset($process->flows['large_group_evaluation']))
                        <div class="flex items-center gap-2">
                          <span class="font-medium">Large group of dogs:</span>
                          <div>
                            @if($process->flows['large_group_evaluation'] === 'accepted')
                              <span class="badge badge-success badge-sm">Accepted</span>
                            @elseif($process->flows['large_group_evaluation'] === 'issue_concern')
                              <span class="badge badge-error badge-sm">Issue/concern</span>
                            @endif
                          </div>
                        </div>
                        @endif
                      </div>
                    </div>

                    @if(isset($process->flows['daycare_evaluation_notes']) && $process->flows['daycare_evaluation_notes'])
                    <div>
                      <p class="font-medium mb-1">Notes:</p>
                      <p class="text-base-content/70 whitespace-pre-wrap">{{ $process->flows['daycare_evaluation_notes'] }}</p>
                    </div>
                    @endif
                  </div>
                </div>
                @endif

                @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">Final Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($checkout->flows['rating']))
                    <div>
                      <p class="font-medium mb-1">Rating:</p>
                      <div class="ms-2">
                        @if($checkout->flows['rating'] === 'green')
                          <span class="badge badge-success badge-sm">Green</span>
                          <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                        @elseif($checkout->flows['rating'] === 'yellow')
                          <span class="badge badge-warning badge-sm">Yellow</span>
                          <span class="text-base-content/70 text-sm ms-2">(mild reaction to daycare)</span>
                          @if(isset($checkout->flows['rating_yellow_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                          @endif
                        @elseif($checkout->flows['rating'] === 'purple')
                          <span class="badge badge-error badge-sm">Purple</span>
                          <span class="text-base-content/70 text-sm ms-2">(reacts to daycare)</span>
                          @if(isset($checkout->flows['rating_purple_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                          @endif
                        @endif
                      </div>
                    </div>
                    @endif
                    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                    <div>
                      <p class="font-medium mb-1">Checkout Pictures:</p>
                      <div class="mt-2 flex flex-wrap gap-2 ms-2">
                        @foreach($checkout->flows['pictures'] as $picture)
                        <div class="relative">
                          <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                        </div>
                        @endforeach
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportDaycareReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isPrivateTrainingService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Training Check-in Info</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                  @if($checkin->flows && is_array($checkin->flows))
                    @php
                      $location = isset($checkin->flows['location']) ? $checkin->flows['location'] : '';
                      $pickupDateTime = '';
                      if (isset($checkin->flows['pickup_datetime_onsite']) && $checkin->flows['pickup_datetime_onsite']) {
                        try {
                          $dt = \Carbon\Carbon::parse($checkin->flows['pickup_datetime_onsite']);
                          $pickupDateTime = $dt->format('M j, Y g:i A');
                        } catch (\Exception $e) {
                          $pickupDateTime = $checkin->flows['pickup_datetime_onsite'];
                        }
                      } else {
                        $pickupDate = isset($checkin->flows['pickup_date_onsite']) ? $checkin->flows['pickup_date_onsite'] : '';
                        $pickupTime = isset($checkin->flows['pickup_time_onsite']) ? $checkin->flows['pickup_time_onsite'] : '';
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
                      $locationAddress = isset($checkin->flows['location_address']) ? $checkin->flows['location_address'] : '';
                      $descriptionNeeds = isset($checkin->flows['description_needs']) ? $checkin->flows['description_needs'] : '';
                      $trainingFocus = isset($checkin->flows['training_focus']) && is_array($checkin->flows['training_focus']) ? $checkin->flows['training_focus'] : [];
                      $additionalServicesLink = isset($checkin->flows['additional_services_link']) ? (is_array($checkin->flows['additional_services_link']) ? $checkin->flows['additional_services_link'] : []) : [];
                    @endphp
                    <div class="mt-3 ms-2 space-y-3">
                      @if($location)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Location</p>
                        <p class="text-sm text-base-content/70">{{ ucfirst($location) }}</p>
                      </div>
                      @endif
                      @if($location === 'onsite' && $pickupDateTime)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Pick up time/date</p>
                        <p class="text-sm text-base-content/70">{{ $pickupDateTime }}</p>
                      </div>
                      @endif
                      @if($location === 'offsite' && $locationAddress)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Location/address</p>
                        <p class="text-sm text-base-content/70">{{ $locationAddress }}</p>
                      </div>
                      @endif
                      @if(!empty($additionalServicesLink))
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Additional Services</p>
                        <p class="text-sm text-base-content/70">
                          @php
                            $services = \App\Models\Service::whereIn('id', $additionalServicesLink)->get();
                          @endphp
                          {{ $services->pluck('name')->join(', ') }}
                        </p>
                      </div>
                      @endif
                      @if($descriptionNeeds)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Goals/owner needs</p>
                        <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $descriptionNeeds }}</p>
                      </div>
                      @endif
                      @if(!empty($trainingFocus))
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Training Focus</p>
                        <div class="text-sm text-base-content/70">
                          @foreach($trainingFocus as $focus)
                            @if($focus === 'basic_obedience')
                              <span class="badge badge-sm">Basic obedience/management</span>
                            @elseif($focus === 'behavior_modification')
                              <span class="badge badge-sm">Behavior modification/aggression</span>
                            @elseif($focus === 'reactivity')
                              <span class="badge badge-sm">Reactivity/socialization</span>
                            @endif
                          @endforeach
                        </div>
                      </div>
                      @endif
                    </div>
                  @endif
                </div>
                @endif

                <!-- Checkout Info -->
                @if(isset($checkout) && $checkout)
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">2. Checkout Info</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                  @php
                    $descriptionNeeds = '';
                    if (isset($checkin) && $checkin && $checkin->flows && is_array($checkin->flows) && isset($checkin->flows['description_needs'])) {
                      $descriptionNeeds = $checkin->flows['description_needs'];
                    }
                  @endphp
                  @if($descriptionNeeds)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Customer Goal:</p>
                    <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $descriptionNeeds }}</p>
                  </div>
                  @endif
                  @if(!empty($lastAppointmentRatings))
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80 mb-2">Star Rating from Last Appointment:</p>
                    @php
                      $obedienceCommands = ['sit', 'down', 'stay', 'come', 'loose_leash_walking'];
                    @endphp
                    <div class="space-y-2">
                      @foreach($obedienceCommands as $command)
                        @php
                          $commandLabel = ucwords(str_replace('_', ' ', $command));
                          $lastRating = isset($lastAppointmentRatings[$command]) ? (int)$lastAppointmentRatings[$command] : 0;
                        @endphp
                        <div class="flex items-center gap-2">
                          <p class="text-sm font-medium">{{ $commandLabel }}:</p>
                          <div class="flex items-center gap-1">
                            @for($i = 0; $i <= 5; $i++)
                              <span class="iconify lucide--star size-4" style="color: {{ $i <= $lastRating ? '#fbbf24' : '#d1d5db' }};"></span>
                            @endfor
                            <span class="text-sm text-base-content/70 ms-2">({{ $lastRating }} star{{ $lastRating != 1 ? 's' : '' }})</span>
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                  @endif
                  @if($checkout->flows && is_array($checkout->flows))
                    @php
                      $obedienceRatings = isset($checkout->flows['obedience_ratings']) ? $checkout->flows['obedience_ratings'] : [];
                      $trainingCurrentRatings = isset($checkout->flows['training_current_ratings']) ? $checkout->flows['training_current_ratings'] : '';
                      $trainingTargets = isset($checkout->flows['training_targets']) ? $checkout->flows['training_targets'] : '';
                      $trainingHomework = isset($checkout->flows['training_homework']) ? $checkout->flows['training_homework'] : '';
                      $obedienceCommands = ['sit', 'down', 'stay', 'come', 'loose_leash_walking'];
                    @endphp
                    <div class="mt-3 ms-2 space-y-3">
                      @if(!empty($obedienceRatings))
                      <div>
                        <p class="font-medium text-sm text-base-content/80 mb-2">Basic obedience (5-star rating)</p>
                        <div class="space-y-2">
                          @foreach($obedienceCommands as $command)
                            @php
                              $commandLabel = ucwords(str_replace('_', ' ', $command));
                              $currentRating = isset($obedienceRatings[$command]) ? (int)$obedienceRatings[$command] : 0;
                            @endphp
                            <div class="flex items-center gap-2">
                              <p class="text-sm font-medium">{{ $commandLabel }}:</p>
                              <div class="flex items-center gap-1">
                                @for($i = 0; $i <= 5; $i++)
                                  <span class="iconify lucide--star size-4" style="color: {{ $i <= $currentRating ? '#fbbf24' : '#d1d5db' }};"></span>
                                @endfor
                                <span class="text-sm text-base-content/70 ms-2">({{ $currentRating }} star{{ $currentRating != 1 ? 's' : '' }})</span>
                              </div>
                            </div>
                          @endforeach
                        </div>
                      </div>
                      @endif
                      @if($trainingCurrentRatings)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Current ratings</p>
                        <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $trainingCurrentRatings }}</p>
                      </div>
                      @endif
                      @if($trainingTargets)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Goal for next lesson</p>
                        <p class="text-sm text-base-content/70">{{ $trainingTargets }}</p>
                      </div>
                      @endif
                      @if($trainingHomework)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Homework for owner</p>
                        <p class="text-sm text-base-content/70 whitespace-pre-wrap">{{ $trainingHomework }}</p>
                      </div>
                      @endif
                      @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                      <div>
                        <p class="font-medium text-sm text-base-content/80">Checkout Pictures</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                          @foreach($checkout->flows['pictures'] as $picture)
                          <div class="relative">
                            <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                          </div>
                          @endforeach
                        </div>
                      </div>
                      @endif
                    </div>
                  @endif
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportTrainingReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isGroupClassService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($process) && $process)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Process</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $process->date ? \Carbon\Carbon::parse($process->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->start_time ? $process->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->pickup_time ? $process->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($process->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $process->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout)
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">Final Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($checkout->flows['rating']))
                    <div>
                      <p class="font-medium mb-1">Rating:</p>
                      <div class="ms-2">
                        @if($checkout->flows['rating'] === 'green')
                          <span class="badge badge-success badge-sm">Green</span>
                          <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                        @elseif($checkout->flows['rating'] === 'yellow')
                          <span class="badge badge-warning badge-sm">Yellow</span>
                          <span class="text-base-content/70 text-sm ms-2">(mild reaction to group class)</span>
                          @if(isset($checkout->flows['rating_yellow_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                          @endif
                        @elseif($checkout->flows['rating'] === 'purple')
                          <span class="badge badge-error badge-sm">Purple</span>
                          <span class="text-base-content/70 text-sm ms-2">(reacts to group class)</span>
                          @if(isset($checkout->flows['rating_purple_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                          @endif
                        @endif
                      </div>
                    </div>
                    @endif
                    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                    <div>
                      <p class="font-medium mb-1">Checkout Pictures:</p>
                      <div class="mt-2 flex flex-wrap gap-2 ms-2">
                        @foreach($checkout->flows['pictures'] as $picture)
                        <div class="relative">
                          <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                        </div>
                        @endforeach
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportGroupClassReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isAlaCarteService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isAlaCarteService($appointment->service) && $appointment->metadata && isset($appointment->metadata['secondary_service_ids']))
                  @php
                    $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
                    $secondaryServices = \App\Models\Service::whereIn('id', $secondaryServiceIds)->get();
                  @endphp
                  @if($secondaryServices->count() > 0)
                  <div class="border-b border-base-300 pb-4">
                    <h4 class="font-semibold mb-3">2. Process - Secondary Services</h4>
                    <div class="space-y-4 ms-2">
                      @foreach($secondaryServices as $secondaryService)
                        @php
                          $serviceProcess = isset($alaCarteProcesses[$secondaryService->id]) ? $alaCarteProcesses[$secondaryService->id] : null;
                        @endphp
                        <div class="border border-base-300 rounded-lg p-4">
                          <h5 class="font-medium mb-3">{{ $secondaryService->name }}</h5>
                          <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                            @if($serviceProcess)
                              <div>
                                <p class="font-medium text-sm text-base-content/80">Date</p>
                                <p class="text-sm text-base-content/70">{{ $serviceProcess->date ? \Carbon\Carbon::parse($serviceProcess->date)->format('M j, Y') : 'Not set' }}</p>
                              </div>
                              <div>
                                <p class="font-medium text-sm text-base-content/80">Assigned Staff</p>
                                <p class="text-sm text-base-content/70">
                                  @if($serviceProcess->staff)
                                    @if($serviceProcess->staff->profile)
                                      {{ $serviceProcess->staff->profile->first_name }} {{ $serviceProcess->staff->profile->last_name }}
                                    @else
                                      {{ $serviceProcess->staff->name }}
                                    @endif
                                  @else
                                    Not assigned
                                  @endif
                                </p>
                              </div>
                              <div>
                                <p class="font-medium text-sm text-base-content/80">Start Time</p>
                                <p class="text-sm text-base-content/70">{{ $serviceProcess->start_time ? $serviceProcess->start_time : 'Not set' }}</p>
                              </div>
                              <div>
                                <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                                <p class="text-sm text-base-content/70">{{ $serviceProcess->pickup_time ? $serviceProcess->pickup_time : 'Not set' }}</p>
                              </div>
                              @if($serviceProcess->notes)
                              <div class="xl:col-span-2">
                                <p class="font-medium text-sm text-base-content/80">Notes</p>
                                <p class="text-sm text-base-content/70">{{ $serviceProcess->notes }}</p>
                              </div>
                              @endif
                            @else
                              <div class="xl:col-span-2">
                                <p class="text-sm text-base-content/70">No process information available for this service.</p>
                              </div>
                            @endif
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                  @endif
                @endif

                @if(isset($checkin) && $checkin && $checkin->flows && is_array($checkin->flows))
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">Initial Temperament Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    <div>
                      <p class="font-medium mb-1">Initial Greeting:</p>
                      <div class="space-y-1 ms-2">
                        <div class="flex items-start gap-2">
                          <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'approachable' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Approachable <span class="text-xs">(allows contact, loose body posture, will accept treats)</span></span>
                        </div>
                        <div class="flex items-start gap-2">
                          <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'shy' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Shy <span class="text-xs">(cautious, tail tucked, whale eye, does not want to be petted)</span></span>
                        </div>
                        <div class="flex items-start gap-2">
                          <span>{{ isset($checkin->flows['initial_greeting']) && $checkin->flows['initial_greeting'] === 'uncomfortable' ? '☑' : '☐' }}</span>
                          <span class="text-base-content/70">Uncomfortable <span class="text-xs">(moves away, shows teeth, barks or snaps)</span></span>
                        </div>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Body Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_body']) && $checkin->flows['touch_body'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_body']) && $checkin->flows['touch_body'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Legs Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_legs']) && $checkin->flows['touch_legs'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_legs']) && $checkin->flows['touch_legs'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Feet Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_feet']) && $checkin->flows['touch_feet'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_feet']) && $checkin->flows['touch_feet'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Tail Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_tail']) && $checkin->flows['touch_tail'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_tail']) && $checkin->flows['touch_tail'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Face Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_face']) && $checkin->flows['touch_face'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_face']) && $checkin->flows['touch_face'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium">Nails Touch:</span>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_nails']) && $checkin->flows['touch_nails'] === 'accept' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Accepts</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span>{{ isset($checkin->flows['touch_nails']) && $checkin->flows['touch_nails'] === 'react' ? '☑' : '☐' }}</span>
                        <span class="text-base-content/70">Reacts</span>
                      </div>
                    </div>
                  </div>
                </div>
                @endif

                @if(isset($checkout) && $checkout)
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->start_time ? $checkout->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->pickup_time ? $checkout->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">Final Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($checkout->flows['rating']))
                    <div>
                      <p class="font-medium mb-1">Rating:</p>
                      <div class="ms-2">
                        @if($checkout->flows['rating'] === 'green')
                          <span class="badge badge-success badge-sm">Green</span>
                          <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                        @elseif($checkout->flows['rating'] === 'yellow')
                          <span class="badge badge-warning badge-sm">Yellow</span>
                          <span class="text-base-content/70 text-sm ms-2">(mild reaction to grooming)</span>
                          @if(isset($checkout->flows['rating_yellow_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                          @endif
                        @elseif($checkout->flows['rating'] === 'purple')
                          <span class="badge badge-error badge-sm">Purple</span>
                          <span class="text-base-content/70 text-sm ms-2">(reacts to grooming)</span>
                          @if(isset($checkout->flows['rating_purple_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                          @endif
                        @endif
                      </div>
                    </div>
                    @endif
                    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                    <div>
                      <p class="font-medium mb-1">Checkout Pictures:</p>
                      <div class="mt-2 flex flex-wrap gap-2 ms-2">
                        @foreach($checkout->flows['pictures'] as $picture)
                        <div class="relative">
                          <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                        </div>
                        @endforeach
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportAlaCarteReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @elseif (isPackageService($appointment->service))
            <div class="collapse-content bg-base-100">
              <div class="text-sm mt-4 space-y-6">
                @if(isset($checkin) && $checkin)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">1. Check-in</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkin->date ? \Carbon\Carbon::parse($checkin->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->start_time ? $appointment->start_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkin->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkin->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                <!-- Process (Multiple) -->
                @if(isset($packageProcesses) && $packageProcesses->count() > 0)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Process</h4>
                  <div class="text-sm space-y-4 ms-2">
                    @foreach($packageProcesses as $processItem)
                    <div class="border border-base-300 rounded-lg p-4 {{ !$loop->last ? 'mb-4' : '' }}">
                      @if($processItem->detail_id)
                        @php
                          $service = \App\Models\Service::find($processItem->detail_id);
                        @endphp
                        @if($service)
                          <h5 class="font-medium mb-3">{{ $service->name }}</h5>
                        @else
                          <h5 class="font-medium mb-3">Service ID: {{ $processItem->detail_id }}</h5>
                        @endif
                      @else
                        <h5 class="font-medium mb-3">Main Process</h5>
                      @endif
                      <div class="grid grid-cols-1 gap-3 xl:grid-cols-3">
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Date</p>
                          <p class="text-sm text-base-content/70">{{ $processItem->date ? \Carbon\Carbon::parse($processItem->date)->format('M j, Y') : 'Not set' }}</p>
                        </div>
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Start Time</p>
                          <p class="text-sm text-base-content/70">{{ $processItem->start_time ? $processItem->start_time : 'Not set' }}</p>
                        </div>
                        <div>
                          <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                          <p class="text-sm text-base-content/70">{{ $processItem->pickup_time ? $processItem->pickup_time : 'Not set' }}</p>
                        </div>
                      </div>
                      @if($processItem->notes)
                      <div class="mt-3">
                        <p class="font-medium text-sm text-base-content/80">Notes</p>
                        <p class="text-sm text-base-content/70">{{ $processItem->notes }}</p>
                      </div>
                      @endif
                    </div>
                    @endforeach
                  </div>
                </div>
                @elseif(isset($process) && $process)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">2. Process</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $process->date ? \Carbon\Carbon::parse($process->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Start Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->start_time ? $process->start_time : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $process->pickup_time ? $process->pickup_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($process->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $process->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout)
                <div class="border-b border-base-300 pb-4">
                  <h4 class="font-semibold mb-3">3. Checkout</h4>
                  <div class="grid grid-cols-1 gap-3 xl:grid-cols-3 ms-2">
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Date</p>
                      <p class="text-sm text-base-content/70">{{ $checkout->date ? \Carbon\Carbon::parse($checkout->date)->format('M j, Y') : 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-sm text-base-content/80">Pickup Time</p>
                      <p class="text-sm text-base-content/70">{{ $appointment->end_time ? $appointment->end_time : 'Not set' }}</p>
                    </div>
                  </div>
                  @if($checkout->notes)
                  <div class="mt-3 ms-2">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $checkout->notes }}</p>
                  </div>
                  @endif
                </div>
                @endif

                @if(isset($checkout) && $checkout && $checkout->flows && is_array($checkout->flows))
                <div class="pb-4">
                  <h4 class="font-semibold mb-3">Final Assessment</h4>
                  <div class="text-sm space-y-3 ms-2">
                    @if(isset($checkout->flows['rating']))
                    <div>
                      <p class="font-medium mb-1">Rating:</p>
                      <div class="ms-2">
                        @if($checkout->flows['rating'] === 'green')
                          <span class="badge badge-success badge-sm">Green</span>
                          <span class="text-base-content/70 text-sm ms-2">(no issues)</span>
                        @elseif($checkout->flows['rating'] === 'yellow')
                          <span class="badge badge-warning badge-sm">Yellow</span>
                          <span class="text-base-content/70 text-sm ms-2">(mild reaction)</span>
                          @if(isset($checkout->flows['rating_yellow_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_yellow_detail'] }}</p>
                          @endif
                        @elseif($checkout->flows['rating'] === 'purple')
                          <span class="badge badge-error badge-sm">Purple</span>
                          <span class="text-base-content/70 text-sm ms-2">(reacts to service)</span>
                          @if(isset($checkout->flows['rating_purple_detail']))
                          <p class="text-base-content/70 text-xs mt-1">{{ $checkout->flows['rating_purple_detail'] }}</p>
                          @endif
                        @endif
                      </div>
                    </div>
                    @endif
                    @if(isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']) && count($checkout->flows['pictures']) > 0)
                    <div>
                      <p class="font-medium mb-1">Checkout Pictures:</p>
                      <div class="mt-2 flex flex-wrap gap-2 ms-2">
                        @foreach($checkout->flows['pictures'] as $picture)
                        <div class="relative">
                          <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                        </div>
                        @endforeach
                      </div>
                    </div>
                    @endif
                  </div>
                </div>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportPackageReportPDF()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cloud-download-icon lucide-cloud-download"><path d="M12 13v8l-4-4"/><path d="m12 21 4-4"/><path d="M4.393 15.269A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.436 8.284"/></svg>
                  Export PDF
                </button>
              </div>
            </div>
            @endif
          </div>
        </div>
      </div>
      @endif
    </div>
    <div class="lg:col-span-7 2xl:col-span-7">
      <div class="card card-border bg-base-100">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Pet Profile</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-4">
                <div class="lg:col-span-1">
                  @if($appointment->pet)
                  <a href="{{ route('edit-pet', $appointment->pet->id) }}" class="block w-fit hover:opacity-80 focus:opacity-80 rounded-box" title="View pet">
                    @if (empty($appointment->pet->pet_img))
                    <img src="{{ asset('images/no_image.jpg') }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                    @else
                    <img src="{{ asset('storage/pets/'. $appointment->pet->pet_img) }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                    @endif
                  </a>
                  @else
                  <img src="{{ asset('images/no_image.jpg') }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                  @endif
                </div>
                <div class="lg:col-span-3 space-y-1">
                  @if($appointment->pet)
                  <p class="font-medium"><a href="{{ route('edit-pet', $appointment->pet->id) }}" class="link link-hover" title="View pet">{{ $appointment->pet->name }}</a></p>
                  @else
                  <p class="font-medium">—</p>
                  @endif
                  <div class="flex items-center gap-4">
                    <div class="text-sm text-base-content/70">
                      <span class="iconify lucide--cake text-base-content/70 size-3"></span>
                      {{ $appointment->pet->birthdate ? \Carbon\Carbon::parse($appointment->pet->birthdate)->format('m/d/Y') . ' (' . $appointment->pet->age . ' years old)' : '' }}
                    </div>
                    @if ($appointment->pet->sex === 'male')
                    <div class="badge badge-dash badge-primary badge-sm">{{ ucfirst($appointment->pet->sex) }}</div>
                    @else
                    <div class="badge badge-dash badge-success badge-sm">{{ ucfirst($appointment->pet->sex) }}</div>
                    @endif
                  </div>
                  <div class="grid grid-cols-2 pt-2 gap-1">
                    <p class="text-sm text-base-content/70">
                      <span class="font-medium text-base-content/80">Breed:</span>
                      {{ $appointment->pet->breed->name }}
                    </p>
                    <p class="text-sm text-base-content/70">
                      <span class="font-medium text-base-content/80">Weight:</span>
                      {{ $appointment->pet->weight }}lbs
                      <span class="font-medium text-base-content/80 ps-5">Size:</span>
                      {{ $appointment->pet->size }}
                    </p>
                    <p class="text-sm text-base-content/70">
                      <span class="font-medium text-base-content/80">Color:</span>
                      {{ $appointment->pet->color->name }}
                    </p>
                    <p class="text-sm text-base-content/70">
                      <span class="font-medium text-base-content/80">Coat Type:</span>
                      {{ $appointment->pet->coatType->name }}
                    </p>
                  </div>
                  <div class="mt-3">
                    <span class="font-medium text-sm text-base-content/80">Veterinarian</span>
                    <div class="grid grid-cols-2 gap-1">
                      <p class="text-sm text-base-content/70 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-stethoscope-icon lucide-stethoscope"><path d="M11 2v2"/><path d="M5 2v2"/><path d="M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1"/><path d="M8 15a6 6 0 0 0 12 0v-3"/><circle cx="20" cy="10" r="2"/></svg>
                        {{ $appointment->pet->veterinarian_name }}
                      </p>
                      <p class="text-sm text-base-content/70 flex items-center gap-1">
                        <span class="iconify lucide--phone text-base-content/70 size-3"></span>
                        {{ $appointment->pet->veterinarian_phone }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-4">
                <span class="font-medium text-sm text-base-content/80">Note</span>
                <p class="text-sm text-base-content/70">{{ $appointment->pet->notes }}</p>
              </div>
              <div class="mt-4">
                <div class="inline-flex flex-wrap gap-2">
                  <span class="font-medium text-base-content/80 text-sm">Vaccination Status:</span>
                  @if ($appointment->pet->vaccine_status === 'missing')
                  <div class="badge badge-soft badge-error badge-sm">{{ ucfirst($appointment->pet->vaccine_status) }}</div>
                  @elseif ($appointment->pet->vaccine_status === 'submitted')
                  <div class="badge badge-soft badge-secondary badge-sm">{{ ucfirst($appointment->pet->vaccine_status) }}</div>
                  @elseif ($appointment->pet->vaccine_status === 'approved')
                  <div class="badge badge-soft badge-success badge-sm">{{ ucfirst($appointment->pet->vaccine_status) }}</div>
                  @elseif ($appointment->pet->vaccine_status === 'expired')
                  <div class="badge badge-soft badge-error badge-sm">{{ ucfirst($appointment->pet->vaccine_status) }}</div>
                  @else
                  <div class="badge badge-soft badge-warning badge-sm">{{ ucfirst($appointment->pet->vaccine_status) }}</div>
                  @endif
                </div>
                <table class="table text-base-content/70 text-xs mt-3">
                  <tbody>
                    @foreach ($appointment->pet->vaccinations as $vaccination)
                      <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ ucfirst($vaccination->type) }}</td>
                        <td>{{ \Carbon\Carbon::parse($vaccination->date)->format('m/d/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($vaccination->date)->addMonths($vaccination->months)->format('m/d/Y') }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
              <div class="mt-4">
                <span class="font-medium text-sm text-base-content/80">Certificates</span>
                <div class="mt-1 flex flex-wrap gap-2">
                  @foreach ($appointment->pet->certificates as $certificate)
                  <div class="flex items-center text-base-content/70 text-sm gap-1 ps-3">
                    <span class="iconify lucide--file-text size-3.5"></span>
                    <span class="hidden sm:inline">{{ $certificate->file_name }}</span>
                    <a href="{{ asset('storage/pets/' . $certificate->file_path) }}" target="_blank" class="btn btn-sm btn-link">
                      <span class="iconify lucide--external-link size-4 text-info"></span>
                    </a>
                  </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Concierge Report -->
      @if(isBoardingService($appointment->service) && isset($processes) && $processes->count() > 0)
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Concierge Report</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 mb-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Select Date</legend>
                  <input type="date" id="concierge_report_date" class="input input-bordered w-full input-sm" 
                    value="{{ $processes->first()->date ?? '' }}" />
                </fieldset>
              </div>
              <div id="concierge_report_content" class="text-sm space-y-4">
                @php
                  $selectedDate = $processes->first()->date ?? '';
                  $selectedProcess = $processes->firstWhere('date', $selectedDate);
                  $conciergeStaffNames = [];
                  if ($selectedProcess && is_array($selectedProcess->flows ?? null)) {
                    $signOffIds = [];
                    foreach ($selectedProcess->flows as $stepData) {
                      if (isset($stepData['staff_sign_off']) && is_array($stepData['staff_sign_off'])) {
                        foreach ($stepData['staff_sign_off'] as $uid) {
                          if ($uid !== null && $uid !== '') {
                            $signOffIds[] = is_numeric($uid) ? (int) $uid : $uid;
                          }
                        }
                      }
                    }
                    $signOffIds = array_unique(array_filter($signOffIds));
                    if (!empty($signOffIds)) {
                      $conciergeUsers = \App\Models\User::with('profile')->whereIn('id', $signOffIds)->get();
                      foreach ($conciergeUsers as $u) {
                        $name = $u->profile ? trim(($u->profile->first_name ?? '') . ' ' . ($u->profile->last_name ?? '')) : '';
                        if ($name === '') {
                          $name = $u->name ?? 'N/A';
                        }
                        $conciergeStaffNames[(string) $u->id] = $name;
                      }
                    }
                  }
                @endphp
                @if($selectedProcess && $selectedProcess->flows && is_array($selectedProcess->flows))
                  @include('archives.partials.concierge-report-details', ['process' => $selectedProcess, 'staff_names' => $conciergeStaffNames])
                @else
                  <p class="text-base-content/70">No concierge report data available for the selected date.</p>
                @endif
              </div>
              <div class="mt-4 flex justify-end">
                <button type="button" class="btn btn-sm btn-primary btn-outline" onclick="exportConciergeReportPDF()">
                  <span class="iconify lucide--download size-4"></span>
                  Export PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif

      <!-- Transaction Details -->
      @if($appointment->transactions && $appointment->transactions->count() > 0)
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Payment Transactions</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 space-y-4">
                @foreach($appointment->transactions as $transaction)
                <div class="border border-base-300 rounded-lg p-4">
                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 text-sm">
                    <div>
                      <p class="font-medium text-base-content/80">Amount</p>
                      <p class="text-lg font-semibold text-success">${{ number_format($transaction->amount, 2) }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-base-content/80">Invoice Number</p>
                      <p class="text-base-content/70">
                        @if($transaction->invoice && $transaction->invoice->invoice_number)
                          {{ $transaction->invoice->invoice_number }}
                        @elseif($appointment->invoice && $appointment->invoice->invoice_number)
                          {{ $appointment->invoice->invoice_number }}
                        @else
                          N/A
                        @endif
                      </p>
                    </div>
                    <div>
                      <p class="font-medium text-base-content/80">Payment Method</p>
                      <p class="text-base-content/70">{{ ucfirst(str_replace('_', ' ', $transaction->payment_method ?? 'N/A')) }}</p>
                    </div>
                    <div>
                      <p class="font-medium text-base-content/80">Transaction Date</p>
                      <p class="text-base-content/70">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M j, Y h:i A') }}</p>
                    </div>
                  </div>
                  @if($transaction->notes)
                  <div class="mt-3">
                    <p class="font-medium text-sm text-base-content/80">Notes</p>
                    <p class="text-sm text-base-content/70">{{ $transaction->notes }}</p>
                  </div>
                  @endif
                </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>
  </div>

  <div class="mt-6 flex justify-end gap-3">
    <a class="btn btn-sm btn-primary" href="{{ url()->previous() }}">
      <span class="iconify lucide--x size-4"></span>
      Close
    </a>
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
      <button class="btn btn-primary btn-sm btn-soft">Confirm</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
@endsection

@section('page-js')
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script>
  $(document).ready(function() {
    // Handle concierge report date picker change
    @if(isBoardingService($appointment->service) && isset($processes) && $processes->count() > 0)
    $('#concierge_report_date').on('change', function() {
      const selectedDate = $(this).val();
      if (!selectedDate) {
        $('#concierge_report_content').html('<p class="text-base-content/70">Please select a date.</p>');
        return;
      }

      // Show loading state
      $('#concierge_report_content').html('<div class="flex justify-center"><span class="loading loading-spinner loading-md"></span></div>');

      // Load the concierge report details via AJAX
      $.ajax({
        url: '{{ route("get-concierge-report", $appointment->id) }}',
        method: 'GET',
        data: {
          date: selectedDate
        },
        success: function(response) {
          if (response.html) {
            $('#concierge_report_content').html(response.html);
          } else {
            $('#concierge_report_content').html('<p class="text-base-content/70">No concierge report data available for the selected date.</p>');
          }
        },
        error: function(xhr, status, error) {
          console.error('Error loading concierge report:', error);
          $('#concierge_report_content').html('<p class="text-error">Error loading concierge report. Please try again.</p>');
        }
      });
    });
    @endif
  });

  function exportGroomingReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-grooming-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportTrainingReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-training-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportDaycareReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-daycare-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportGroupClassReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-group-class-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportAlaCarteReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-ala-carte-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportBoardingReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-boarding-report-pdf", $appointment->id) }}', '_blank');
  }

  function exportConciergeReportPDF() {
    // Get the selected date from the date picker
    const selectedDate = document.getElementById('concierge_report_date').value;
    if (!selectedDate) {
      alert('Please select a date first.');
      return;
    }
    // Open the PDF export route in a new tab with the selected date
    window.open('{{ route("export-concierge-report-pdf", $appointment->id) }}?date=' + selectedDate, '_blank');
  }

  function exportPackageReportPDF() {
    // Open the PDF export route in a new tab
    window.open('{{ route("export-package-report-pdf", $appointment->id) }}', '_blank');
  }
</script>
@endsection
