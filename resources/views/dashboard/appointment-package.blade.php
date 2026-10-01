@extends('layouts.main')
@section('title', 'Package Appointment Detail')

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
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Package Appointment Detail</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li><a href="{{ route('packages') }}">Packages</a></li>
      <li class="opacity-80">Detail</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  @if($appointment->status === 'in_progress' && $customerPackage && $customerPackage->remaining_days <= 0)
  <div class="alert alert-error alert-soft mb-2" role="alert">
    <span class="iconify lucide--info size-4"></span>
    <p>Package days exhausted (Remaining: {{ $customerPackage->remaining_days }}). Process fields disabled.</p>
  </div>
  @endif
  <div class="grid grid-cols-1 gap-2 xl:grid-cols-5 border border-base-300 rounded-box px-5 py-2 text-sm">
    <div class="flex items-center gap-2">
      <p class="font-medium">Package: </p>
      <p class="text-base-content/70">{{ $appointment->package_name ?? 'Package' }}</p>
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Start Date: </p>
      <p class="text-base-content/70">{{ $appointment->date ? \Carbon\Carbon::parse($appointment->date)->format('F j, Y') : 'N/A' }}</p>
    </div>
    <div class="flex items-center gap-2">
      <p class="font-medium">Status: </p>
      @if($appointment->status === 'checked_in')
        <div class="badge badge-soft badge-info badge-sm">Scheduled</div>
      @elseif($appointment->status === 'in_progress')
        <div class="badge badge-soft badge-primary badge-sm">{{ ($appointment->service && (isBoardingService($appointment->service) || isDaycareService($appointment->service))) ? 'On Property' : 'In Progress' }}</div>
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
    @if($appointment->package_services && $appointment->package_services->count() > 0)
      <div class="flex items-center gap-2">
        <p class="font-medium">Services: </p>
        <p class="text-base-content/70">{{ $appointment->package_services->pluck('name')->join(', ') }}</p>
      </div>
    @endif
    @if ($appointment->estimated_price)
    <div class="flex items-center gap-2">
      <p class="font-medium">Paid Amount: </p>
      <p class="text-base-content/70">${{ number_format($appointment->estimated_price, 2) }}</p>
    </div>
    @php
      $packageCheckin = $appointment->checkin;
      $packageFlows = $packageCheckin && $packageCheckin->flows
        ? (is_array($packageCheckin->flows) ? $packageCheckin->flows : json_decode($packageCheckin->flows, true))
        : [];
      $packageFleaTickBreakdown = isBoardingService($appointment->service)
        ? getBoardingFleaTickBreakdown($appointment, is_array($packageFlows) ? $packageFlows : [])
        : ['amount' => 0];
    @endphp
    @if (!empty($packageFleaTickBreakdown['amount']))
    <div class="flex items-center gap-2">
      <p class="font-medium">Flea/Tick Fee: </p>
      <p class="text-base-content/70">${{ number_format($packageFleaTickBreakdown['amount'], 2) }}</p>
    </div>
    @endif
    @endif
  </div>
  <div class="mt-3 grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="lg:col-span-5 2xl:col-span-5">
      {{-- Customer Profile --}}
      <div class="card card-border bg-base-100">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Customer Profile</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                @if (empty($appointment->customer->profile) || empty($appointment->customer->profile->avatar_img))
                <img src="{{ asset('images/default-user-avatar.png') }}" alt="Seller Image" class="rounded-box bg-base-200 avatar-img">
                @else
                <img src="{{ asset('storage/profiles/'. $appointment->customer->profile->avatar_img) }}" alt="Seller Image" class="rounded-box bg-base-200 avatar-img">
                @endif
                <div class="lg:col-span-2 space-y-1">
                  <p class="font-medium">
                    {{ $appointment->customer->profile->first_name }} {{ $appointment->customer->profile->last_name }}
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
      @if (filled($appointment->metadata['appointment_notes'] ?? null))
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Toggle appointment notes" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Appointment Notes</div>
            <div class="collapse-content bg-base-100">
              <p class="mt-4 text-sm whitespace-pre-wrap break-words text-base-content/70">{{ $appointment->metadata['appointment_notes'] }}</p>
            </div>
          </div>
        </div>
      </div>
      @endif
    </div>
    <div class="lg:col-span-7 2xl:col-span-7">
      {{-- Pet Profile --}}
      <div class="card card-border bg-base-100">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" checked="" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Pet Profile</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-4">
                <div class="lg:col-span-1">
                  @if (empty($appointment->pet) || empty($appointment->pet->pet_img))
                  <img src="{{ asset('images/no_image.jpg') }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                  @else
                  <img src="{{ asset('storage/pets/'. $appointment->pet->pet_img) }}" alt="Pet Image" class="rounded-box bg-base-200 avatar-img">
                  @endif
                </div>
                <div class="lg:col-span-3 space-y-1">
                  <p class="font-medium">{{ $appointment->pet->name }}</p>
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
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      {{-- Check-in Info --}}
      @if ($appointment->status === 'checked_in')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Check-in Info</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Date*</legend>
                  <input class="input input-sm w-full" placeholder="Select date" id="checkin_date" name="checkin_date" type="date" value="{{ $checkedIn->date ?? ($appointment->date ?? '') }}"/>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Start Time*</legend>
                  <input class="input input-sm w-full" placeholder="Select time" id="start_time" name="start_time" type="time" min="09:00" max="18:00" value="{{ $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '' }}"/>
                </fieldset>
              </div>
              <div class="mt-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Notes</legend>
                  <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the check-in process..." id="notes" name="notes" rows="3">{{ $checkedIn->notes ?? '' }}</textarea>
                </fieldset>
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

      {{-- Process Info --}}
      @if ($appointment->status === 'in_progress')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Process Info</div>
            <div class="collapse-content bg-base-100">
              @if($appointment->package_services && $appointment->package_services->count() > 0)
                <div id="package_process_container" class="mt-4 space-y-6">
                  @foreach($appointment->package_services as $packageService)
                    @php
                      $existingProcess = \App\Models\Process::where('appointment_id', $appointment->id)
                        ->where(function($query) use ($packageService) {
                          $query->where(function($q) use ($packageService) {
                            $q->where('detail_id', $packageService->id);
                          })->orWhere(function($q) use ($packageService) {
                            $q->whereRaw("JSON_EXTRACT(flows, '$.service_id') = ?", [$packageService->id]);
                          });
                        })
                        ->orderBy('updated_at', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    @endphp
                    <div class="border border-base-300 rounded-box p-4" data-service-id="{{ $packageService->id }}">
                      <h4 class="font-medium mb-4">{{ $packageService->name }}</h4>
                      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Assign Staff*</legend>
                          <select class="select select-bordered w-full select-sm package-staff-id" data-service-id="{{ $packageService->id }}" required>
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
                          <input class="input input-sm w-full package-date"
                            placeholder="Select date"
                            data-service-id="{{ $packageService->id }}"
                            data-current-date="{{ $existingProcess ? $existingProcess->date : ($appointment->date ?? '') }}"
                            type="date"
                            value="{{ $existingProcess ? $existingProcess->date : ($appointment->date ?? '') }}"/>
                        </fieldset>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Start Time*</legend>
                          <input class="input input-sm w-full package-start-time"
                            placeholder="Select time"
                            data-service-id="{{ $packageService->id }}"
                            type="time"
                            min="09:00"
                            max="18:00"
                            value="{{ $existingProcess && $existingProcess->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $existingProcess->start_time)->format('H:i') : '' }}"/>
                        </fieldset>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Pickup Time*</legend>
                          <input class="input input-sm w-full package-pickup-time"
                            placeholder="Select time"
                            data-service-id="{{ $packageService->id }}"
                            type="time"
                            min="09:00"
                            max="18:00"
                            value="{{ $existingProcess && $existingProcess->pickup_time ? \Carbon\Carbon::createFromFormat('H:i:s', $existingProcess->pickup_time)->format('H:i') : '' }}"/>
                        </fieldset>
                      </div>
                      <div class="mt-4">
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Notes</legend>
                          <textarea class="textarea textarea-bordered w-full package-notes"
                            placeholder="Add notes for this service..."
                            data-service-id="{{ $packageService->id }}"
                            rows="3">{{ $existingProcess ? $existingProcess->notes : '' }}</textarea>
                        </fieldset>
                      </div>
                      <div class="mt-4">
                        <button type="button" class="btn btn-primary btn-sm save-package-process-btn" data-service-id="{{ $packageService->id }}" onclick="savePackageProcess({{ $appointment->id }}, {{ $packageService->id }})">
                          <span class="loading loading-spinner loading-sm" style="display: none;"></span>
                          <span class="btn-text">Save Process</span>
                        </button>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
      @endif

      {{-- Checkout Info --}}
      @if ($appointment->status === 'completed')
      <div class="card card-border bg-base-100 mt-3">
        <div class="card-body gap-0">
          <div class="bg-base-200 rounded-box collapse collapse-arrow">
            <input aria-label="Collapse trigger" type="checkbox" name="accordion-multiple" />
            <div class="collapse-title font-medium py-1">Checkout Info</div>
            <div class="collapse-content bg-base-100">
              <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Date*</legend>
                  <input class="input input-bordered w-full" placeholder="Select date" id="checkout_date" name="checkout_date" type="date" value="{{ $checkout->date ?? ($appointment->end_date ?? ($appointment->date ?? '')) }}"/>
                </fieldset>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Pickup Time*</legend>
                  <input class="input input-bordered w-full" placeholder="Select time" id="checkout_pickup_time" name="checkout_pickup_time" type="time" min="09:00" max="18:00" value="{{ $appointment->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->end_time)->format('H:i') : '' }}"/>
                </fieldset>
              </div>
              <div class="mt-4">
                <fieldset class="fieldset">
                  <legend class="fieldset-legend">Notes</legend>
                  <textarea class="textarea textarea-bordered w-full" placeholder="Add any notes about the checkout process..." id="checkout_notes" name="checkout_notes" rows="3">{{ $checkout->notes ?? '' }}</textarea>
                </fieldset>
              </div>
              <hr class="mt-5" style="color: lightgray"/>
              <div class="mt-4 space-y-2 text-sm">
                <div>
                  <p class="font-medium mb-2">Rating:</p>
                  <div class="mb-2 ms-4">
                    <div class="space-y-2">
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="rating"
                          value="green" {{ $checkout && $checkout->flows && isset($checkout->flows['rating']) && $checkout->flows['rating'] === 'green' ? 'checked' : '' }} />
                        <span class="text-sm">Green (no issues)</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="rating"
                          value="yellow" {{ $checkout && $checkout->flows && isset($checkout->flows['rating']) && $checkout->flows['rating'] === 'yellow' ? 'checked' : '' }} />
                        <span class="text-sm">Yellow (mild reaction to package, specifically</span>
                        <input placeholder="Touch to write" id="rating_yellow_detail" name="rating_yellow_detail" class="input input-ghost input-xs" aria-label="Input" type="text" style="max-width: 220px;" value="{{ $checkout && $checkout->flows && isset($checkout->flows['rating_yellow_detail']) ? $checkout->flows['rating_yellow_detail'] : '' }}"/>
                        <span class="text-sm">)</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="rating"
                          value="purple" {{ $checkout && $checkout->flows && isset($checkout->flows['rating']) && $checkout->flows['rating'] === 'purple' ? 'checked' : '' }} />
                        <span class="text-sm">Purple (reacts to package, go slow with</span>
                        <input placeholder="Touch to write" id="rating_purple_detail" name="rating_purple_detail" class="input input-ghost input-xs" aria-label="Input" type="text" style="max-width: 220px;" value="{{ $checkout && $checkout->flows && isset($checkout->flows['rating_purple_detail']) ? $checkout->flows['rating_purple_detail'] : '' }}"/>
                        <span class="text-sm">)</span>
                      </label>
                    </div>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Pictures:</p>
                  <div class="mb-2 ms-4">
                    <input aria-label="File" id="checkout_pictures" name="checkout_pictures[]" class="file-input w-full" type="file" multiple accept="image/*" />
                  </div>
                  @if($checkout && $checkout->flows && isset($checkout->flows['pictures']) && is_array($checkout->flows['pictures']))
                  <div class="mt-2 ms-4 flex flex-wrap gap-2">
                    @foreach($checkout->flows['pictures'] as $picture)
                    <div class="relative">
                      <img src="{{ asset('storage/checkouts/' . $picture) }}" alt="Checkout Picture" class="w-24 h-24 object-cover rounded-lg border">
                    </div>
                    @endforeach
                  </div>
                  @endif
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

<dialog id="success_modal" class="modal">
  <div class="modal-box">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium mb-4">Success</h3>
    <p id="success_message"></p>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<dialog id="alert_modal" class="modal">
  <div class="modal-box">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium mb-4">Alert</h3>
    <p id="alert_message"></p>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
@endsection

@section('page-js')
<script>
  const confirm_modal = document.getElementById('confirm_modal');
  const success_modal = document.getElementById('success_modal');
  const alert_modal = document.getElementById('alert_modal');

  @php
    $customerPackageId = $appointment->metadata && isset($appointment->metadata['customer_package_id']) ? $appointment->metadata['customer_package_id'] : null;
    $remainingDays = $customerPackage ? $customerPackage->remaining_days : null;
    $originalDays = $customerPackage ? $customerPackage->original_days : null;
    
    // Get all unique dates used across all processes for this appointment
    $usedDates = [];
    if ($customerPackageId) {
      $usedDates = \App\Models\Process::where('appointment_id', $appointment->id)
        ->whereNotNull('date')
        ->distinct()
        ->pluck('date')
        ->toArray();
    }
    $usedDatesCount = count(array_unique($usedDates));
  @endphp

  let customerPackageId = @json($customerPackageId);
  let originalDays = @json($originalDays);
  let currentRemainingDays = @json($remainingDays);
  const usedDates = @json($usedDates);

  function toggleProcessFields(disabled) {
    $('.package-staff-id').prop('disabled', disabled);
    $('.package-date').prop('disabled', disabled);
    $('.package-start-time').prop('disabled', disabled);
    $('.package-pickup-time').prop('disabled', disabled);
    $('.package-notes').prop('disabled', disabled);
    $('.save-package-process-btn').prop('disabled', disabled);
  }

  $(document).ready(function() {
    if (currentRemainingDays !== null && currentRemainingDays <= 0) {
      toggleProcessFields(true);
    }
    $('.package-date').on('change', function() {
      const serviceId = $(this).data('service-id');
      const date = $(this).val();
      const appointmentId = {{ $appointment->id }};

      if (!date) {
        $(`.package-start-time[data-service-id="${serviceId}"]`).val('');
        $(`.package-pickup-time[data-service-id="${serviceId}"]`).val('');
        $(`.package-notes[data-service-id="${serviceId}"]`).val('');
        return;
      }

      if (customerPackageId && originalDays !== null) {
        const $dateInput = $(this);
        $.ajax({
          url: '{{ route("get-process-flows", ":id") }}'.replace(':id', appointmentId),
          method: 'GET',
          data: {
            get_used_dates: true
          },
          success: function(response) {
            const usedDatesList = response.used_dates || [];
            const currentServiceDate = $dateInput.data('current-date') || '';
            
            const usedDatesWithoutCurrent = usedDatesList.filter(d => d !== currentServiceDate);
            
            const isDateAlreadyUsed = usedDatesWithoutCurrent.includes(date);
            
            if (!isDateAlreadyUsed) {
              const currentUsedCount = usedDatesWithoutCurrent.length;
              const wouldBeUsedCount = currentUsedCount + 1;
              const wouldBeRemaining = originalDays - wouldBeUsedCount;
              
              if (wouldBeRemaining < 0) {
                $('#alert_message').text('Cannot select this date. Would exceed package days (Remaining: ' + (originalDays - currentUsedCount) + '). Fields disabled.');
                alert_modal.showModal();
                $dateInput.val(currentServiceDate);
                toggleProcessFields(true);
                return;
              }
            }
            
            $dateInput.data('current-date', date);
            
            loadProcessDataForDate(appointmentId, serviceId, date);
          },
          error: function() {
            loadProcessDataForDate(appointmentId, serviceId, date);
          }
        });
        return;
      }

      loadProcessDataForDate(appointmentId, serviceId, date);
    });
  });

  function loadProcessDataForDate(appointmentId, serviceId, date) {

    $.ajax({
      url: '{{ route("get-process-flows", ":id") }}'.replace(':id', appointmentId),
      method: 'GET',
      data: {
        date: date,
        service_id: serviceId
      },
      success: function(response) {
        // Update start time
        if (response.start_time) {
          $(`.package-start-time[data-service-id="${serviceId}"]`).val(response.start_time);
        } else {
          $(`.package-start-time[data-service-id="${serviceId}"]`).val('');
        }

        // Update pickup time
        if (response.pickup_time) {
          $(`.package-pickup-time[data-service-id="${serviceId}"]`).val(response.pickup_time);
        } else {
          $(`.package-pickup-time[data-service-id="${serviceId}"]`).val('');
        }

        // Update notes
        if (response.notes) {
          $(`.package-notes[data-service-id="${serviceId}"]`).val(response.notes);
        } else {
          $(`.package-notes[data-service-id="${serviceId}"]`).val('');
        }

        // Update staff selection
        if (response.staff_id) {
          $(`.package-staff-id[data-service-id="${serviceId}"]`).val(response.staff_id).trigger('change');
        }
      },
      error: function(xhr) {
        console.error('Error loading process data:', xhr);
        // Clear fields on error
        $(`.package-start-time[data-service-id="${serviceId}"]`).val('');
        $(`.package-pickup-time[data-service-id="${serviceId}"]`).val('');
        $(`.package-notes[data-service-id="${serviceId}"]`).val('');
      }
    });
  }

  function openConfirmModal() {
    const appointmentStatus = '{{ $appointment->status }}';
    let message = 'Are you sure to confirm this action?';
    
    if (appointmentStatus === 'checked_in') {
      message = 'Are you sure you want to confirm the check-in?';
    } else if (appointmentStatus === 'in_progress') {
      message = 'Are you sure you want to confirm the process?';
    } else if (appointmentStatus === 'completed') {
      message = 'Are you sure you want to confirm the checkout?<br>This appointment will be switched to "Finished" status automatically.';
    }
    
    $('#confirm_message').html(message);
    confirm_modal.showModal();

    $('#confirm_modal .btn-primary').off('click').on('click', function() {
      confirm_modal.close();
      if (appointmentStatus === 'checked_in') {
        return confirmCheckedIn();
      } else if (appointmentStatus === 'in_progress') {
        return confirmInProgress();
      } else if (appointmentStatus === 'completed') {
        return confirmCompleted();
      }
    });
  }

  function confirmCheckedIn() {
    const date = $('#checkin_date').val();
    const startTime = $('#start_time').val();
    const notes = $('#notes').val();

    if (!date || !startTime) {
      $('#alert_message').text('Please fill in all required fields (Date and Start Time).');
      alert_modal.showModal();
      return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("confirm-checked-in-appointment", $appointment->id) }}';
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    const appointmentInput = document.createElement('input');
    appointmentInput.type = 'hidden';
    appointmentInput.name = 'id';
    appointmentInput.value = '{{ $appointment->id }}';
    form.appendChild(appointmentInput);

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

    const notesInput = document.createElement('input');
    notesInput.type = 'hidden';
    notesInput.name = 'notes';
    notesInput.value = notes || '';
    form.appendChild(notesInput);

    document.body.appendChild(form);
    form.submit();
  }

  function confirmInProgress() {
    // For package appointments, we don't need to validate a single staff member
    // Each service has its own staff assignment
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("confirm-in-progress-appointment", $appointment->id) }}';
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    document.body.appendChild(form);
    form.submit();
  }

  function confirmCompleted() {
    const date = $('#checkout_date').val();
    const pickupTime = $('#checkout_pickup_time').val();
    const notes = $('#checkout_notes').val();
    const pictures = $('#checkout_pictures').length ? $('#checkout_pictures')[0].files : [];

    const ratingElement = $('input[name="rating"]:checked');
    const rating = ratingElement.length ? ratingElement.val() : null;
    const ratingYellowDetail = $('#rating_yellow_detail').length ? $('#rating_yellow_detail').val() : null;
    const ratingPurpleDetail = $('#rating_purple_detail').length ? $('#rating_purple_detail').val() : null;

    if (!date) {
      $('#alert_message').text('Please select a checkout date.');
      alert_modal.showModal();
      return;
    }

    if (!pickupTime) {
      $('#alert_message').text('Please select a pickup time.');
      alert_modal.showModal();
      return;
    }

    // Use FormData for submission
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('date', date);
    formData.append('pickup_time', pickupTime);
    formData.append('notes', notes || '');

    const flows = {};

    if (rating !== null) {
      flows.rating = rating;
      if (ratingYellowDetail) flows.rating_yellow_detail = ratingYellowDetail;
      if (ratingPurpleDetail) flows.rating_purple_detail = ratingPurpleDetail;
    }

    formData.append('flows', JSON.stringify(flows));

    if (pictures.length > 0) {
      for (let i = 0; i < pictures.length; i++) {
        formData.append('pictures[]', pictures[i]);
      }
    }

    // Send via AJAX
    $.ajax({
      url: '{{ route("confirm-completed-appointment", $appointment->id) }}',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        if (response.status === 'success') {
          // For package appointments, get service_id from service_id or first from additional_service_ids
          @php
            $serviceId = $appointment->service_id;
            if (!$serviceId && $appointment->additional_service_ids) {
              $serviceIds = explode(',', $appointment->additional_service_ids);
              $serviceId = $serviceIds[0] ?? 1;
            }
            $serviceId = $serviceId ?? 1;
          @endphp
          window.location.href = '{{ route("service-dashboard", $serviceId) }}';
        } else {
          $('#alert_message').text('Error: ' + (response.message || 'Unknown error'));
          alert_modal.showModal();
        }
      },
      error: function(xhr, status, error) {
        console.error('Error confirming checkout:', error);
        const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error confirming checkout. Please try again.';
        $('#alert_message').text(errorMessage);
        alert_modal.showModal();
      }
    });
  }

  function savePackageProcess(appointmentId, serviceId) {
    const $btn = $(`.save-package-process-btn[data-service-id="${serviceId}"]`);
    const $loading = $btn.find('.loading');
    const $btnText = $btn.find('.btn-text');
    const originalText = $btnText.text();

    const staffId = $(`.package-staff-id[data-service-id="${serviceId}"]`).val();
    const date = $(`.package-date[data-service-id="${serviceId}"]`).val();
    const startTime = $(`.package-start-time[data-service-id="${serviceId}"]`).val();
    const pickupTime = $(`.package-pickup-time[data-service-id="${serviceId}"]`).val();
    const notes = $(`.package-notes[data-service-id="${serviceId}"]`).val();

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

    // Show loading and disable button
    $loading.css('display', 'inline-block');
    $btnText.text('Saving...');
    $btn.prop('disabled', true);

    $.ajax({
      url: '{{ route("update-process-flows", ":id") }}'.replace(':id', appointmentId),
      method: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        service_id: serviceId,
        workflow_date: date,
        staff_id: staffId,
        start_time: startTime,
        pickup_time: pickupTime,
        notes: notes || '',
        flows: {
          service_id: serviceId,
          package_id: {{ $appointment->metadata && isset($appointment->metadata['package_id']) ? $appointment->metadata['package_id'] : 'null' }},
          package_name: '{{ $appointment->metadata && isset($appointment->metadata['package_name']) ? addslashes($appointment->metadata['package_name']) : '' }}'
        }
      },
      success: function(response) {
        // Silently save - no modal needed
        console.log('Process saved successfully');
        
        if (response.remaining_days !== undefined) {
          $(`.package-date[data-service-id="${serviceId}"]`).data('current-date', date);
          
          const oldRemainingDays = currentRemainingDays;
          currentRemainingDays = response.remaining_days;
          
          if (response.remaining_days !== oldRemainingDays) {
            console.log('Remaining days updated: ' + oldRemainingDays + ' -> ' + response.remaining_days);
          }
          
          if (response.remaining_days <= 0) {
            $('#alert_message').text('Package days exhausted (Remaining: ' + response.remaining_days + '). Process fields disabled.');
            alert_modal.showModal();
            toggleProcessFields(true);
          }
        }
        
        $loading.css('display', 'none');
        $btnText.text(originalText);
        if (currentRemainingDays === null || currentRemainingDays > 0) {
          $btn.prop('disabled', false);
        }
      },
      error: function(xhr) {
        const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error saving process.';
        $('#alert_message').text(errorMessage);
        alert_modal.showModal();
        // Reset button state
        $loading.css('display', 'none');
        $btnText.text(originalText);
        $btn.prop('disabled', false);
      }
    });
  }
</script>
@endsection

