<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Boarding Pre Check-In</title>
  <link rel="shortcut icon" href="{{ asset('images/favicon-dark.png') }}" media="(prefers-color-scheme: dark)" />
  <link rel="shortcut icon" href="{{ asset('images/favicon-light.png') }}" media="(prefers-color-scheme: light)" />
  <style>
    .card {
        width: 60%;
        margin: auto;
    }
    @media (max-width: 768px) {
        .card {
        width: 100%;
        }
    }
  </style>
  <script>
    try {
      const localStorageItem = localStorage.getItem("__NEXUS_CONFIG_v2.0__");
      if (localStorageItem) {
        const theme = JSON.parse(localStorageItem).theme;
        if (theme !== "system") {
          document.documentElement.setAttribute("data-theme", theme);
        }
      }
    } catch (err) {
      console.log(err);
    }
  </script>
  @if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="{{ asset('src/assets/app.css') }}" rel="stylesheet">
    <link href="{{ asset('src/assets/custom.css') }}" rel="stylesheet">
  @endif
</head>
<body class="bg-base-200 min-h-screen">
  @php
    $allFlows = is_array($flows ?? null) ? $flows : [];
    $petSpecificFlows = isset($allFlows['pet_specific']) && is_array($allFlows['pet_specific']) ? $allFlows['pet_specific'] : [];
    $isFamilyCheckin = $pets->count() > 1;
    $boardingPricing = isBoardingService($appointment->service) ? getBoardingPricingBreakdown($appointment) : null;
  @endphp
  <main class="max-w-7xl mx-auto p-4 md:p-6">
    <div class="card bg-base-100">
      <div class="card-body">
        <div class="flex flex-col md:flex-row items-center justify-between gap-2">
          <div>
            <h1 class="card-title text-2xl">Boarding Pre Check-In</h1>
            <p class="text-sm text-base-content/70">Appointment #{{ $appointment->id }} for {{ $appointment->pet?->name ?? 'Pet' }}</p>
          </div>
          @if($submitted)
            <span class="badge badge-success">Already Submitted</span>
          @endif
        </div>

        @if(session('status') === 'success')
          <div class="alert alert-success mt-4">{{ session('message') }}</div>
        @endif
        @if(session('status') === 'fail')
          <div class="alert alert-error mt-4">{{ session('message') }}</div>
        @endif
        @if($errors->any())
          <div class="alert alert-error mt-4">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form id="precheckin_form" method="POST" action="{{ route('pre-checkin.save', ['token' => $token]) }}" class="mt-4 space-y-6">
          @csrf

          <div class="text-sm mt-4 space-y-6">
            <div>
              <p class="font-semibold mb-2 text-base">Trip Information</p>
              <div class="space-y-3 ms-2">
                @php
                  $prefillPickup = old('pickup_datetime');
                  if ($prefillPickup === null) {
                    $prefillPickup = $allFlows['pickup_datetime'] ?? null;
                    if (!$prefillPickup && !empty($appointment->end_date) && !empty($appointment->end_time)) {
                      $prefillPickup = \Carbon\Carbon::parse($appointment->end_date . ' ' . $appointment->end_time)->format('Y-m-d\TH:i');
                    } elseif ($prefillPickup) {
                      try {
                        $prefillPickup = \Carbon\Carbon::parse($prefillPickup)->format('Y-m-d\TH:i');
                      } catch (\Throwable $e) {
                        $prefillPickup = null;
                      }
                    }
                  }
                @endphp
                <div>
                  <p class="font-medium mb-1">Confirm pickup date and time:</p>
                  <input type="datetime-local" id="boarding_pickup_datetime" name="pickup_datetime" class="input input-bordered w-full input-sm" value="{{ $prefillPickup }}" />
                </div>
                <div>
                  <p class="font-medium mb-1">Trip location:</p>
                  <input type="text" id="boarding_trip_location" name="trip_location" class="input input-bordered w-full input-sm" placeholder="Enter trip location" value="{{ old('trip_location', $allFlows['trip_location'] ?? '') }}" />
                </div>
                <div>
                  <p class="font-medium mb-1">Trip phone number (if different from current on file):</p>
                  <input type="tel" id="boarding_trip_phone" name="trip_phone" class="input input-bordered w-full input-sm" placeholder="Enter phone number" value="{{ old('trip_phone', $allFlows['trip_phone'] ?? '') }}" oninput="formatPhoneNumber(this)" />
                </div>
                <div>
                  <p class="font-medium mb-1">Alternate contact (name and phone):</p>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <input type="text" id="boarding_alternate_contact_name" name="alternate_contact_name" class="input input-bordered w-full input-sm" placeholder="Name" value="{{ old('alternate_contact_name', $allFlows['alternate_contact_name'] ?? '') }}" />
                    <input type="tel" id="boarding_alternate_contact_phone" name="alternate_contact_phone" class="input input-bordered w-full input-sm" placeholder="Phone" value="{{ old('alternate_contact_phone', $allFlows['alternate_contact_phone'] ?? '') }}" oninput="formatPhoneNumber(this)" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-1">Notes (authorized pickup & payment arrangement):</p>
                  <textarea id="boarding_trip_notes" name="trip_notes" class="textarea textarea-bordered w-full" rows="3" placeholder="Enter notes...">{{ old('trip_notes', $allFlows['trip_notes'] ?? '') }}</textarea>
                </div>
              </div>
            </div>

            <div class="{{ $isFamilyCheckin ? 'grid grid-cols-1 xl:grid-cols-2 gap-4' : '' }}">
              @foreach($pets as $pet)
                @php
                  $petIdKey = (string) $pet->id;
                  $petFlow = $petSpecificFlows[$petIdKey] ?? ($petSpecificFlows[$pet->id] ?? []);
                  $petFlow = is_array($petFlow) ? $petFlow : [];
                  $effectivePetFlow = array_merge($allFlows, $petFlow);
                  $petOtherItemsDescription = old('pet_specific.' . $petIdKey . '.other_items_description', $effectivePetFlow['other_items_description'] ?? '');
                  $legacyFleaTickValue = $effectivePetFlow['flea_tick'] ?? null;
                  $legacyFleaTickTruthy = in_array($legacyFleaTickValue, [true, 'true', 1, '1'], true);
                  $petFleaTickPrevention = old(
                    'pet_specific.' . $petIdKey . '.flea_tick_prevention',
                    $effectivePetFlow['flea_tick_prevention'] ?? ($legacyFleaTickValue !== null ? ($legacyFleaTickTruthy ? 'yes' : 'no') : '')
                  );
                  $petFleaTickPreventionType = old('pet_specific.' . $petIdKey . '.flea_tick_prevention_type', $effectivePetFlow['flea_tick_prevention_type'] ?? '');

                  $dryFoodRows = [];
                  if (isset($effectivePetFlow['dry_food_list']) && is_array($effectivePetFlow['dry_food_list']) && count($effectivePetFlow['dry_food_list']) > 0) {
                    $dryFoodRows = $effectivePetFlow['dry_food_list'];
                  } else {
                    $dryFoodRows[] = [
                      'brand' => $effectivePetFlow['dry_food']['brand'] ?? '',
                      'amount' => $effectivePetFlow['dry_food']['amount'] ?? '',
                      'dispense_am' => !empty($effectivePetFlow['dry_food']['dispense_am']),
                      'dispense_pm' => !empty($effectivePetFlow['dry_food']['dispense_pm']),
                      'dispense_lunch' => !empty($effectivePetFlow['dry_food']['dispense_lunch']),
                    ];
                  }

                  $wetFoodRows = [];
                  if (isset($effectivePetFlow['wet_food_list']) && is_array($effectivePetFlow['wet_food_list']) && count($effectivePetFlow['wet_food_list']) > 0) {
                    $wetFoodRows = $effectivePetFlow['wet_food_list'];
                  } else {
                    $wetFoodRows[] = [
                      'brand' => $effectivePetFlow['wet_food']['brand'] ?? '',
                      'amount' => $effectivePetFlow['wet_food']['amount'] ?? '',
                      'dispense_am' => !empty($effectivePetFlow['wet_food']['dispense_am']),
                      'dispense_pm' => !empty($effectivePetFlow['wet_food']['dispense_pm']),
                      'dispense_lunch' => !empty($effectivePetFlow['wet_food']['dispense_lunch']),
                    ];
                  }

                  $medicationRows = [];
                  if (isset($effectivePetFlow['meds_list']) && is_array($effectivePetFlow['meds_list']) && count($effectivePetFlow['meds_list']) > 0) {
                    $medicationRows = $effectivePetFlow['meds_list'];
                  } elseif (isset($effectivePetFlow['meds']) && is_array($effectivePetFlow['meds'])) {
                    $medicationRows[] = [
                      'name' => $effectivePetFlow['meds']['name'] ?? '',
                      'amount' => $effectivePetFlow['meds']['amount'] ?? '',
                      'dispense_am' => !empty($effectivePetFlow['meds']['dispense_am']),
                      'dispense_pm' => !empty($effectivePetFlow['meds']['dispense_pm']),
                      'dispense_rest' => !empty($effectivePetFlow['meds']['dispense_rest']),
                      'dispense_before_bed' => false,
                      'dispense_prn' => !empty($effectivePetFlow['meds']['dispense_prn']),
                      'dispense_custom_time' => false,
                      'meal_condition' => null,
                      'custom_time' => '',
                    ];
                  }

                  if (count($medicationRows) === 0) {
                    $medicationRows[] = [
                      'name' => '', 'amount' => '', 'dispense_am' => false, 'dispense_pm' => false,
                      'dispense_rest' => false, 'dispense_before_bed' => false, 'dispense_prn' => false, 'dispense_custom_time' => false,
                      'meal_condition' => null, 'custom_time' => ''
                    ];
                  }
                @endphp

                <div class="boarding-pet-section {{ $isFamilyCheckin ? 'rounded-box border border-base-300 bg-base-100 p-4 h-full' : '' }}" data-pet-id="{{ $pet->id }}" data-pet-name="{{ $pet->name }}">
                  @if($isFamilyCheckin)
                    <div class="mb-4 flex items-center gap-3 border-b border-base-300 pb-3">
                      <img src="{{ !empty($pet->pet_img) ? asset('storage/pets/' . $pet->pet_img) : asset('images/no_image.jpg') }}" alt="{{ $pet->name }}" class="mask mask-squircle bg-base-200 shrink-0" style="width: 48px; height: 48px; object-fit: cover;" />
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
                          <textarea class="textarea textarea-bordered w-full boarding-other-items-description" rows="2" data-pet-id="{{ $pet->id }}" name="pet_specific[{{ $petIdKey }}][other_items_description]" placeholder="Please describe items brought for boarding (e.g., Leash, Collar, toys, bedding, etc)">{{ $petOtherItemsDescription }}</textarea>
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
                          <button type="button" class="btn btn-primary btn-sm add-boarding-dry-food" data-pet-id="{{ $pet->id }}">Add Dry</button>
                        </div>
                        <div class="space-y-3 ms-2 boarding-dry-food-container" id="boarding_dry_food_container_{{ $pet->id }}">
                          @foreach($dryFoodRows as $index => $dryFoodRow)
                            @php
                              $rowDryAm = old('pet_specific.' . $petIdKey . '.dry_food_list.' . $index . '.dispense_am', $dryFoodRow['dispense_am'] ?? false);
                              $rowDryPm = old('pet_specific.' . $petIdKey . '.dry_food_list.' . $index . '.dispense_pm', $dryFoodRow['dispense_pm'] ?? false);
                              $rowDryLunch = old('pet_specific.' . $petIdKey . '.dry_food_list.' . $index . '.dispense_lunch', $dryFoodRow['dispense_lunch'] ?? false);
                            @endphp
                            <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-dry-food-row" data-row-index="{{ $index }}">
                              <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Dry Food #{{ $index + 1 }}</p>
                                <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-dry-food" title="Remove dry food">x</button>
                              </div>
                              <div>
                                <p class="text-sm mb-1">Brand:</p>
                                <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-brand" name="pet_specific[{{ $petIdKey }}][dry_food_list][{{ $index }}][brand]" placeholder="Enter dry food brand" value="{{ old('pet_specific.' . $petIdKey . '.dry_food_list.' . $index . '.brand', $dryFoodRow['brand'] ?? '') }}" />
                              </div>
                              <div class="flex items-end gap-4">
                                <div class="flex-1">
                                  <p class="text-sm mb-1">Amount:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-dry-food-amount" name="pet_specific[{{ $petIdKey }}][dry_food_list][{{ $index }}][amount]" placeholder="e.g., 1 cup, 1/2 cup" value="{{ old('pet_specific.' . $petIdKey . '.dry_food_list.' . $index . '.amount', $dryFoodRow['amount'] ?? '') }}" />
                                </div>
                                <div class="flex-1 pb-2">
                                  <p class="text-sm mb-1">Dispense:</p>
                                  <div class="flex items-center gap-3 flex-wrap">
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-am" name="pet_specific[{{ $petIdKey }}][dry_food_list][{{ $index }}][dispense_am]" value="1" {{ $rowDryAm ? 'checked' : '' }} /><span class="text-sm">AM</span></label>
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-pm" name="pet_specific[{{ $petIdKey }}][dry_food_list][{{ $index }}][dispense_pm]" value="1" {{ $rowDryPm ? 'checked' : '' }} /><span class="text-sm">PM</span></label>
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-dry-food-dispense-lunch" name="pet_specific[{{ $petIdKey }}][dry_food_list][{{ $index }}][dispense_lunch]" value="1" {{ $rowDryLunch ? 'checked' : '' }} /><span class="text-sm">Lunch</span></label>
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
                          <button type="button" class="btn btn-primary btn-sm add-boarding-wet-food" data-pet-id="{{ $pet->id }}">Add Wet</button>
                        </div>
                        <div class="space-y-3 ms-2 boarding-wet-food-container" id="boarding_wet_food_container_{{ $pet->id }}">
                          @foreach($wetFoodRows as $index => $wetFoodRow)
                            @php
                              $rowWetAm = old('pet_specific.' . $petIdKey . '.wet_food_list.' . $index . '.dispense_am', $wetFoodRow['dispense_am'] ?? false);
                              $rowWetPm = old('pet_specific.' . $petIdKey . '.wet_food_list.' . $index . '.dispense_pm', $wetFoodRow['dispense_pm'] ?? false);
                              $rowWetLunch = old('pet_specific.' . $petIdKey . '.wet_food_list.' . $index . '.dispense_lunch', $wetFoodRow['dispense_lunch'] ?? false);
                            @endphp
                            <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-wet-food-row" data-row-index="{{ $index }}">
                              <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Wet Food #{{ $index + 1 }}</p>
                                <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-wet-food" title="Remove wet food">x</button>
                              </div>
                              <div>
                                <p class="text-sm mb-1">Brand:</p>
                                <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-brand" name="pet_specific[{{ $petIdKey }}][wet_food_list][{{ $index }}][brand]" placeholder="Enter wet food brand" value="{{ old('pet_specific.' . $petIdKey . '.wet_food_list.' . $index . '.brand', $wetFoodRow['brand'] ?? '') }}" />
                              </div>
                              <div class="flex items-end gap-4">
                                <div class="flex-1">
                                  <p class="text-sm mb-1">Amount:</p>
                                  <input type="text" class="input input-bordered w-full input-sm boarding-wet-food-amount" name="pet_specific[{{ $petIdKey }}][wet_food_list][{{ $index }}][amount]" placeholder="e.g., 2 Tbsp, 1 container" value="{{ old('pet_specific.' . $petIdKey . '.wet_food_list.' . $index . '.amount', $wetFoodRow['amount'] ?? '') }}" />
                                </div>
                                <div class="flex-1 pb-2">
                                  <p class="text-sm mb-1">Dispense:</p>
                                  <div class="flex items-center gap-3 flex-wrap">
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-am" name="pet_specific[{{ $petIdKey }}][wet_food_list][{{ $index }}][dispense_am]" value="1" {{ $rowWetAm ? 'checked' : '' }} /><span class="text-sm">AM</span></label>
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-pm" name="pet_specific[{{ $petIdKey }}][wet_food_list][{{ $index }}][dispense_pm]" value="1" {{ $rowWetPm ? 'checked' : '' }} /><span class="text-sm">PM</span></label>
                                    <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-wet-food-dispense-lunch" name="pet_specific[{{ $petIdKey }}][wet_food_list][{{ $index }}][dispense_lunch]" value="1" {{ $rowWetLunch ? 'checked' : '' }} /><span class="text-sm">Lunch</span></label>
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
                          <button type="button" class="btn btn-primary btn-sm add-boarding-medication" data-pet-id="{{ $pet->id }}">Add Medication</button>
                        </div>
                        <div class="space-y-3 ms-2 boarding-meds-container" id="boarding_meds_container_{{ $pet->id }}">
                          @foreach($medicationRows as $index => $medicationRow)
                            @php
                              $rowMealCondition = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.meal_condition', $medicationRow['meal_condition'] ?? '');
                              $rowDispenseAm = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_am', $medicationRow['dispense_am'] ?? false);
                              $rowDispensePm = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_pm', $medicationRow['dispense_pm'] ?? false);
                              $rowDispenseRest = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_rest', $medicationRow['dispense_rest'] ?? false);
                              $rowDispenseBeforeBed = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_before_bed', $medicationRow['dispense_before_bed'] ?? false);
                              $rowDispensePrn = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_prn', $medicationRow['dispense_prn'] ?? false);
                              $rowDispenseCustomTime = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.dispense_custom_time', $medicationRow['dispense_custom_time'] ?? false);
                              $rowCustomTime = old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.custom_time', $medicationRow['custom_time'] ?? '');
                            @endphp
                            <div class="border border-base-300 rounded-box p-3 space-y-2 boarding-med-row" data-row-index="{{ $index }}">
                              <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Medication #{{ $index + 1 }}</p>
                                <button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-medication" title="Remove medication">x</button>
                              </div>
                              <div>
                                <p class="text-sm mb-1">Medication Name:</p>
                                <input type="text" class="input input-bordered w-full input-sm boarding-med-name" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][name]" placeholder="Enter medication name" value="{{ old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.name', $medicationRow['name'] ?? '') }}" />
                              </div>
                              <div>
                                <p class="text-sm mb-1">Dosage/Instruction:</p>
                                <input type="text" class="input input-bordered w-full input-sm boarding-med-amount" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][amount]" placeholder="e.g., 1 pill, 2 drops left ear" value="{{ old('pet_specific.' . $petIdKey . '.meds_list.' . $index . '.amount', $medicationRow['amount'] ?? '') }}" />
                              </div>
                              <div>
                                <p class="text-sm mb-1">Dispense:</p>
                                <div class="flex items-center gap-3 flex-wrap">
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-am" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_am]" value="1" {{ $rowDispenseAm ? 'checked' : '' }} /><span class="text-sm">AM</span></label>
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-pm" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_pm]" value="1" {{ $rowDispensePm ? 'checked' : '' }} /><span class="text-sm">PM</span></label>
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-rest" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_rest]" value="1" {{ $rowDispenseRest ? 'checked' : '' }} /><span class="text-sm">Rest</span></label>
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-before-bed" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_before_bed]" value="1" {{ $rowDispenseBeforeBed ? 'checked' : '' }} /><span class="text-sm">Before Bed</span></label>
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-prn" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_prn]" value="1" {{ $rowDispensePrn ? 'checked' : '' }} /><span class="text-sm">PRN</span></label>
                                  <label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-custom-time" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][dispense_custom_time]" value="1" {{ $rowDispenseCustomTime ? 'checked' : '' }} /><span class="text-sm">Custom Time</span></label>
                                </div>
                              </div>
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                  <p class="text-sm mb-1">Meal Condition:</p>
                                  <select class="select select-bordered w-full select-sm boarding-med-meal-condition" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][meal_condition]">
                                    <option value="">Select option</option>
                                    <option value="after_meal" {{ $rowMealCondition === 'after_meal' ? 'selected' : '' }}>After Meal</option>
                                    <option value="before_meal" {{ $rowMealCondition === 'before_meal' ? 'selected' : '' }}>Before Meal</option>
                                    <option value="empty_stomach" {{ $rowMealCondition === 'empty_stomach' ? 'selected' : '' }}>Empty Stomach</option>
                                  </select>
                                </div>
                                <div class="boarding-med-custom-time-wrap {{ $rowDispenseCustomTime ? '' : 'hidden' }}">
                                  <p class="text-sm mb-1">Custom Time:</p>
                                  <input type="time" class="input input-bordered w-full input-sm boarding-med-custom-time" name="pet_specific[{{ $petIdKey }}][meds_list][{{ $index }}][custom_time]" value="{{ $rowCustomTime }}" />
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

            <div>
              <p class="font-semibold mb-2 text-base">Assignment or location for visit</p>
              <div class="space-y-3 ms-2">
                <div>
                  <p class="font-medium mb-2">Location type:</p>
                  @php $locationType = old('location_type', $allFlows['location_type'] ?? ''); @endphp
                  <div class="flex items-center gap-2 mb-2 space-y-1 ms-1">
                    <label class="flex items-center gap-2"><input type="radio" class="radio radio-xs" name="location_type" value="suite" {{ $locationType === 'suite' ? 'checked' : '' }} /><span class="text-sm">Suite</span></label>
                    <label class="flex items-center gap-2"><input type="radio" class="radio radio-xs" name="location_type" value="run" {{ $locationType === 'run' ? 'checked' : '' }} /><span class="text-sm">Run</span></label>
                    <label class="flex items-center gap-2"><input type="radio" class="radio radio-xs" name="location_type" value="bedroom" {{ $locationType === 'bedroom' ? 'checked' : '' }} /><span class="text-sm">Bedroom</span></label>
                    <label class="flex items-center gap-2"><input type="radio" class="radio radio-xs" name="location_type" value="kennel" {{ $locationType === 'kennel' ? 'checked' : '' }} /><span class="text-sm">Kennel</span></label>
                  </div>
                  <div class="ms-1">
                    <p class="font-medium mb-1">Location details:</p>
                    <input type="text" id="boarding_location_details" name="location_details" class="input input-bordered w-full input-sm" placeholder="Enter details if needed..." value="{{ old('location_details', $allFlows['location_details'] ?? '') }}" />
                  </div>
                  @if(($appointment->pet?->age ?? 0) >= 16)
                    @php
                      $restRequired = old('rest_required', $allFlows['rest_required'] ?? false);
                      $restNote = old('rest_note', $allFlows['rest_note'] ?? '');
                    @endphp
                    <div class="flex items-center gap-2 mb-2 space-y-1 ms-1 mt-2">
                      <label class="flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs" id="boarding_rest_required" name="rest_required" value="1" {{ $restRequired ? 'checked' : '' }} />
                        <span class="text-sm">Rest Required (Senior Pet – 16 years old)</span>
                      </label>
                    </div>
                    <div class="ms-1">
                      <p class="font-medium mb-1">Rest Note:</p>
                      <textarea id="boarding_rest_note" name="rest_note" class="textarea textarea-bordered w-full" rows="2" placeholder="Enter rest note..." {{ $restRequired ? '' : 'disabled' }}>{{ $restNote }}</textarea>
                    </div>
                  @endif
                </div>
              </div>
            </div>

            <div>
              <div class="space-y-4 ms-2">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Assign Staff (optional)</legend>
                    <select name="staff_id" id="staff_id" class="select select-bordered w-full select-sm">
                      <option value="" hidden>Select Staff Member</option>
                      @foreach($staffs as $staff)
                        <option value="{{ $staff->id }}" {{ (string) old('staff_id', $appointment->staff_id) === (string) $staff->id ? 'selected' : '' }}>
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
                    <label class="input w-full input-sm">
                      <input class="grow" id="estimated_price" name="estimated_price" type="text" value="{{ old('estimated_price', number_format($estimatedPriceWithTax, 2, '.', '')) }}" placeholder="Enter estimated price" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" required />
                      <span class="badge badge-ghost badge-sm">USD</span>
                    </label>
                  </fieldset>
                </div>
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Date*</legend>
                    <input class="input input-sm w-full" id="checkin_date" name="date" type="date" value="{{ old('date', $checkin->date ?? ($appointment->date ?? '')) }}" />
                  </fieldset>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Start Time*</legend>
                    <input class="input input-sm w-full" id="start_time" name="start_time" type="time" value="{{ old('start_time', $appointment->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $appointment->start_time)->format('H:i') : '') }}" />
                  </fieldset>
                </div>
                <div>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">Notes</legend>
                    <textarea class="textarea textarea-bordered w-full" id="notes" name="notes" rows="3" placeholder="Add any notes about the check-in process...">{{ old('notes', $checkin->notes ?? '') }}</textarea>
                  </fieldset>
                </div>
                <div class="alert alert-soft alert-info">
                  <span>Estimated price{{ isBoardingService($appointment->service) ? ' (tax included)' : '' }} is required before continuing. Staff assignment can be added now or later.</span>
                </div>
                <div class="border border-base-300 rounded-box p-4 space-y-4">
                  <p class="font-semibold text-base">Boarding Agreement</p>
                  <div class="rounded-box border border-base-300 bg-base-100 p-3 text-sm text-base-content/80 space-y-2">
                    <p><span class="font-medium">Release and waiver:</span> I understand boarding activities carry inherent risks and I release the facility, its owners, and staff from liability except where prohibited by law.</p>
                    <p><span class="font-medium">Authorization to treat:</span> I authorize the facility to arrange reasonable care and treatment for my pet when needed during boarding.</p>
                    <p><span class="font-medium">Emergency care consent:</span> If I cannot be reached promptly, I consent to emergency veterinary care deemed necessary for my pet's welfare.</p>
                    <p><span class="font-medium">Facility policy acknowledgement:</span> I acknowledge and agree to follow the facility's boarding policies, pickup requirements, and payment terms.</p>
                  </div>
                  <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" id="boarding_agreement_accepted" name="boarding_agreement_accepted" value="1" class="checkbox checkbox-sm" required {{ old('boarding_agreement_accepted', $allFlows['boarding_agreement_accepted'] ?? false) ? 'checked' : '' }} />
                    <span class="label-text">I have read and agree to the boarding agreement</span>
                  </label>
                  <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" id="boarding_vet_authorized" name="boarding_vet_authorized" value="1" class="checkbox checkbox-sm" required {{ old('boarding_vet_authorized', $allFlows['boarding_vet_authorized'] ?? false) ? 'checked' : '' }} />
                    <span class="label-text">I authorize the facility to seek veterinary treatment if needed</span>
                  </label>

                  <p class="font-semibold text-base">Owner Signature</p>
                  <div>
                    <p class="text-sm mb-1">Owner full name:</p>
                    <input type="text" id="boarding_owner_full_name" name="boarding_owner_full_name" class="input input-bordered w-full input-sm" placeholder="Enter owner full name" required value="{{ old('boarding_owner_full_name', $allFlows['boarding_owner_full_name'] ?? trim((($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? '')))) }}" />
                  </div>
                  <div>
                    <p class="text-sm mb-1">Signature:</p>
                    <div class="rounded-box border border-base-300 bg-base-100 p-2">
                      <canvas id="boarding_signature_pad" width="900" height="180" class="w-full" style="height: 180px;"></canvas>
                    </div>
                    <input type="hidden" id="boarding_signature_data" name="boarding_signature_data" value="{{ old('boarding_signature_data', $allFlows['boarding_signature_data'] ?? '') }}" />
                    <p class="text-xs text-error mt-1 hidden" id="boarding_signature_error">Signature is required.</p>
                  </div>
                  <div>
                    <p class="text-sm mb-1">Date:</p>
                    <input type="date" id="boarding_signature_date" name="boarding_signature_date" class="input input-bordered w-full input-sm" value="{{ old('boarding_signature_date', $allFlows['boarding_signature_date'] ?? now()->format('Y-m-d')) }}" readonly />
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-outline btn-sm" id="boarding_clear_signature">Clear Signature</button>
                    <button type="button" class="btn btn-primary btn-sm" id="boarding_save_signature">Save Signature</button>
                  </div>
                  <p class="text-xs text-success hidden" id="boarding_signature_saved_note">Signature has been saved.</p>
                  <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Appointment Status</legend>
                      <select name="appointment_status_display" id="appointment_status" class="select select-bordered w-full select-sm" disabled>
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

          <div class="flex justify-end mt-4">
            <button type="submit" class="btn btn-primary">Save Pre Check-In</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    function formatPhoneNumber(input) {
      const digits = (input.value || '').replace(/\D/g, '').slice(0, 10);
      if (digits.length <= 3) {
        input.value = digits;
        return;
      }
      if (digits.length <= 6) {
        input.value = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
        return;
      }
      input.value = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
    }

    function refreshRowLabels(containerSelector, rowSelector, labelPrefix) {
      document.querySelectorAll(containerSelector + ' ' + rowSelector).forEach(function(row, index) {
        row.dataset.rowIndex = index;
        const label = row.querySelector('.text-sm.font-medium');
        if (label) {
          label.textContent = labelPrefix + ' #' + (index + 1);
        }
      });
    }

    function updateRemoveButtons(containerSelector, rowSelector, buttonSelector) {
      const rows = document.querySelectorAll(containerSelector + ' ' + rowSelector);
      const disable = rows.length <= 1;
      document.querySelectorAll(containerSelector + ' ' + buttonSelector).forEach(function(button) {
        button.disabled = disable;
      });
    }

    function toggleCustomTimeWrap(row) {
      const toggle = row.querySelector('.boarding-med-dispense-custom-time');
      const wrap = row.querySelector('.boarding-med-custom-time-wrap');
      if (!toggle || !wrap) return;
      wrap.classList.toggle('hidden', !toggle.checked);
    }

    function attachDynamicHandlers() {
      document.querySelectorAll('.remove-boarding-dry-food').forEach(function(button) {
        button.onclick = function() {
          const row = button.closest('.boarding-dry-food-row');
          const container = row.closest('.boarding-dry-food-container');
          if (!row || !container || container.querySelectorAll('.boarding-dry-food-row').length <= 1) return;
          row.remove();
          refreshRowLabels('#' + container.id, '.boarding-dry-food-row', 'Dry Food');
          updateRemoveButtons('#' + container.id, '.boarding-dry-food-row', '.remove-boarding-dry-food');
        };
      });

      document.querySelectorAll('.remove-boarding-wet-food').forEach(function(button) {
        button.onclick = function() {
          const row = button.closest('.boarding-wet-food-row');
          const container = row.closest('.boarding-wet-food-container');
          if (!row || !container || container.querySelectorAll('.boarding-wet-food-row').length <= 1) return;
          row.remove();
          refreshRowLabels('#' + container.id, '.boarding-wet-food-row', 'Wet Food');
          updateRemoveButtons('#' + container.id, '.boarding-wet-food-row', '.remove-boarding-wet-food');
        };
      });

      document.querySelectorAll('.remove-boarding-medication').forEach(function(button) {
        button.onclick = function() {
          const row = button.closest('.boarding-med-row');
          const container = row.closest('.boarding-meds-container');
          if (!row || !container || container.querySelectorAll('.boarding-med-row').length <= 1) return;
          row.remove();
          refreshRowLabels('#' + container.id, '.boarding-med-row', 'Medication');
          updateRemoveButtons('#' + container.id, '.boarding-med-row', '.remove-boarding-medication');
        };
      });

      document.querySelectorAll('.boarding-med-dispense-custom-time').forEach(function(checkbox) {
        checkbox.onchange = function() {
          toggleCustomTimeWrap(checkbox.closest('.boarding-med-row'));
        };
      });
    }

    function buildFoodRowHtml(type, petId, index) {
      const title = type === 'dry' ? 'Dry Food' : 'Wet Food';
      const prefix = type === 'dry' ? 'boarding-dry-food' : 'boarding-wet-food';
      const listName = type === 'dry' ? 'dry_food_list' : 'wet_food_list';
      return '<div class="border border-base-300 rounded-box p-3 space-y-2 ' + prefix + '-row" data-row-index="' + index + '">' +
        '<div class="flex items-center justify-between"><p class="text-sm font-medium">' + title + ' #' + (index + 1) + '</p><button type="button" class="btn btn-ghost btn-sm btn-circle remove-' + prefix + '" title="Remove ' + type + ' food">x</button></div>' +
        '<div><p class="text-sm mb-1">Brand:</p><input type="text" class="input input-bordered w-full input-sm ' + prefix + '-brand" name="pet_specific[' + petId + '][' + listName + '][' + index + '][brand]" placeholder="Enter ' + type + ' food brand" /></div>' +
        '<div class="flex items-end gap-4"><div class="flex-1"><p class="text-sm mb-1">Amount:</p><input type="text" class="input input-bordered w-full input-sm ' + prefix + '-amount" name="pet_specific[' + petId + '][' + listName + '][' + index + '][amount]" placeholder="e.g., 1 cup, 1/2 cup" /></div>' +
        '<div class="flex-1 pb-2"><p class="text-sm mb-1">Dispense:</p><div class="flex items-center gap-3 flex-wrap">' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs ' + prefix + '-dispense-am" name="pet_specific[' + petId + '][' + listName + '][' + index + '][dispense_am]" value="1" /><span class="text-sm">AM</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs ' + prefix + '-dispense-pm" name="pet_specific[' + petId + '][' + listName + '][' + index + '][dispense_pm]" value="1" /><span class="text-sm">PM</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs ' + prefix + '-dispense-lunch" name="pet_specific[' + petId + '][' + listName + '][' + index + '][dispense_lunch]" value="1" /><span class="text-sm">Lunch</span></label>' +
        '</div></div></div></div>';
    }

    function buildMedicationRowHtml(petId, index) {
      return '<div class="border border-base-300 rounded-box p-3 space-y-2 boarding-med-row" data-row-index="' + index + '">' +
        '<div class="flex items-center justify-between"><p class="text-sm font-medium">Medication #' + (index + 1) + '</p><button type="button" class="btn btn-ghost btn-sm btn-circle remove-boarding-medication" title="Remove medication">x</button></div>' +
        '<div><p class="text-sm mb-1">Medication Name:</p><input type="text" class="input input-bordered w-full input-sm boarding-med-name" name="pet_specific[' + petId + '][meds_list][' + index + '][name]" placeholder="Enter medication name" /></div>' +
        '<div><p class="text-sm mb-1">Dosage/Instruction:</p><input type="text" class="input input-bordered w-full input-sm boarding-med-amount" name="pet_specific[' + petId + '][meds_list][' + index + '][amount]" placeholder="e.g., 1 pill, 2 drops left ear" /></div>' +
        '<div><p class="text-sm mb-1">Dispense:</p><div class="flex items-center gap-3 flex-wrap">' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-am" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_am]" value="1" /><span class="text-sm">AM</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-pm" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_pm]" value="1" /><span class="text-sm">PM</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-rest" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_rest]" value="1" /><span class="text-sm">Rest</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-before-bed" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_before_bed]" value="1" /><span class="text-sm">Before Bed</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-prn" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_prn]" value="1" /><span class="text-sm">PRN</span></label>' +
        '<label class="flex items-center gap-2"><input type="checkbox" class="checkbox checkbox-xs boarding-med-dispense-custom-time" name="pet_specific[' + petId + '][meds_list][' + index + '][dispense_custom_time]" value="1" /><span class="text-sm">Custom Time</span></label>' +
        '</div></div>' +
        '<div class="grid grid-cols-1 md:grid-cols-2 gap-3"><div><p class="text-sm mb-1">Meal Condition:</p><select class="select select-bordered w-full select-sm boarding-med-meal-condition" name="pet_specific[' + petId + '][meds_list][' + index + '][meal_condition]"><option value="">Select option</option><option value="after_meal">After Meal</option><option value="before_meal">Before Meal</option><option value="empty_stomach">Empty Stomach</option></select></div>' +
        '<div class="boarding-med-custom-time-wrap hidden"><p class="text-sm mb-1">Custom Time:</p><input type="time" class="input input-bordered w-full input-sm boarding-med-custom-time" name="pet_specific[' + petId + '][meds_list][' + index + '][custom_time]" /></div></div>' +
        '</div>';
    }

    document.querySelectorAll('.add-boarding-dry-food').forEach(function(button) {
      button.addEventListener('click', function() {
        const petId = button.dataset.petId;
        const container = document.getElementById('boarding_dry_food_container_' + petId);
        const index = container.querySelectorAll('.boarding-dry-food-row').length;
        container.insertAdjacentHTML('beforeend', buildFoodRowHtml('dry', petId, index));
        attachDynamicHandlers();
        updateRemoveButtons('#' + container.id, '.boarding-dry-food-row', '.remove-boarding-dry-food');
      });
    });

    document.querySelectorAll('.add-boarding-wet-food').forEach(function(button) {
      button.addEventListener('click', function() {
        const petId = button.dataset.petId;
        const container = document.getElementById('boarding_wet_food_container_' + petId);
        const index = container.querySelectorAll('.boarding-wet-food-row').length;
        container.insertAdjacentHTML('beforeend', buildFoodRowHtml('wet', petId, index));
        attachDynamicHandlers();
        updateRemoveButtons('#' + container.id, '.boarding-wet-food-row', '.remove-boarding-wet-food');
      });
    });

    document.querySelectorAll('.add-boarding-medication').forEach(function(button) {
      button.addEventListener('click', function() {
        const petId = button.dataset.petId;
        const container = document.getElementById('boarding_meds_container_' + petId);
        const index = container.querySelectorAll('.boarding-med-row').length;
        container.insertAdjacentHTML('beforeend', buildMedicationRowHtml(petId, index));
        attachDynamicHandlers();
        updateRemoveButtons('#' + container.id, '.boarding-med-row', '.remove-boarding-medication');
      });
    });

    const restRequiredCheckbox = document.getElementById('boarding_rest_required');
    const restNoteField = document.getElementById('boarding_rest_note');
    if (restRequiredCheckbox && restNoteField) {
      restRequiredCheckbox.addEventListener('change', function() {
        restNoteField.disabled = !restRequiredCheckbox.checked;
        if (!restRequiredCheckbox.checked) {
          restNoteField.value = '';
        }
      });
    }

    function updateFleaPreventionTypeVisibility(petId, value) {
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
      updateFleaPreventionTypeVisibility(radio.dataset.petId, radio.value);
    });

    document.addEventListener('change', function(event) {
      if (event.target && event.target.classList && event.target.classList.contains('boarding-flea-prevention-radio')) {
        updateFleaPreventionTypeVisibility(event.target.dataset.petId, event.target.value);
      }
    });

    function initializeBoardingSignaturePad() {
      const canvas = document.getElementById('boarding_signature_pad');
      if (!canvas) return;
      const context = canvas.getContext('2d');
      context.lineCap = 'round';
      context.lineJoin = 'round';
      context.strokeStyle = '#111827';
      context.lineWidth = 2;
      let isDrawing = false;

      function getCanvasPoint(event) {
        const rect = canvas.getBoundingClientRect();
        const point = event.touches && event.touches[0] ? event.touches[0] : event;
        return {
          x: (point.clientX - rect.left) * (canvas.width / rect.width),
          y: (point.clientY - rect.top) * (canvas.height / rect.height)
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
        if (!isDrawing) return;
        const point = getCanvasPoint(event);
        context.lineTo(point.x, point.y);
        context.stroke();
        event.preventDefault();
      }

      function stopDrawing(event) {
        if (!isDrawing) return;
        isDrawing = false;
        context.closePath();
        if (event) event.preventDefault();
      }

      canvas.onpointerdown = startDrawing;
      canvas.onpointermove = draw;
      canvas.onpointerup = stopDrawing;
      canvas.onpointerleave = stopDrawing;
      canvas.onpointercancel = stopDrawing;
      canvas.addEventListener('touchstart', startDrawing, { passive: false });
      canvas.addEventListener('touchmove', draw, { passive: false });
      canvas.addEventListener('touchend', stopDrawing, { passive: false });

      const savedSignature = document.getElementById('boarding_signature_data').value;
      if (savedSignature) {
        const image = new Image();
        image.onload = function() {
          context.clearRect(0, 0, canvas.width, canvas.height);
          context.drawImage(image, 0, 0, canvas.width, canvas.height);
        };
        image.src = savedSignature;
      }

      document.getElementById('boarding_clear_signature').addEventListener('click', function() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('boarding_signature_data').value = '';
        document.getElementById('boarding_signature_error').classList.remove('hidden');
        document.getElementById('boarding_signature_saved_note').classList.add('hidden');
      });

      document.getElementById('boarding_save_signature').addEventListener('click', function() {
        const data = canvas.toDataURL('image/png');
        document.getElementById('boarding_signature_data').value = data;
        document.getElementById('boarding_signature_error').classList.add('hidden');
        document.getElementById('boarding_signature_saved_note').classList.remove('hidden');
      });
    }

    function isSignatureCanvasBlank(canvas) {
      const blank = document.createElement('canvas');
      blank.width = canvas.width;
      blank.height = canvas.height;
      return canvas.toDataURL() === blank.toDataURL();
    }

    const precheckinForm = document.getElementById('precheckin_form');
    if (precheckinForm) {
      precheckinForm.addEventListener('submit', function(event) {
        const signatureCanvas = document.getElementById('boarding_signature_pad');
        const signatureInput = document.getElementById('boarding_signature_data');
        const signatureError = document.getElementById('boarding_signature_error');

        if (signatureCanvas && signatureInput) {
          const blank = isSignatureCanvasBlank(signatureCanvas);
          if (blank) {
            event.preventDefault();
            if (signatureError) {
              signatureError.classList.remove('hidden');
            }
            signatureCanvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }

          // Auto-capture signature so users don't have to click "Save Signature" first.
          signatureInput.value = signatureCanvas.toDataURL('image/png');
          if (signatureError) {
            signatureError.classList.add('hidden');
          }
        }
      });
    }

    attachDynamicHandlers();
    document.querySelectorAll('.boarding-dry-food-container').forEach(function(container) {
      updateRemoveButtons('#' + container.id, '.boarding-dry-food-row', '.remove-boarding-dry-food');
    });
    document.querySelectorAll('.boarding-wet-food-container').forEach(function(container) {
      updateRemoveButtons('#' + container.id, '.boarding-wet-food-row', '.remove-boarding-wet-food');
    });
    document.querySelectorAll('.boarding-meds-container').forEach(function(container) {
      updateRemoveButtons('#' + container.id, '.boarding-med-row', '.remove-boarding-medication');
    });
    document.querySelectorAll('.boarding-med-row').forEach(toggleCustomTimeWrap);
    initializeBoardingSignaturePad();
  </script>
</body>
</html>
