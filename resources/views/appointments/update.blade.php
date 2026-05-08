@extends('layouts.main')
@section('title', 'Update Appointment')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
  <style>
    .select2-container--default .select2-selection--multiple {
      min-height: 40px;
      height: 40px;
      overflow-y: auto;
      overflow-x: hidden !important;
      white-space: normal !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      margin-top: 10px !important;
      margin-left: 10px !important;
    }

    .select2-container .select2-search--inline .select2-search__field {
      margin-top: 10px !important;
      margin-left: 10px !important;
    }
    /* Also ensure the dropdown fits the parent */
    .select2-container {
      width: 100% !important;
      min-width: 0 !important;
    }
    .select2-container--default.select2-container--disabled .select2-selection--multiple {
      background-color: unset !important;
    }
  </style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Update Appointment</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('appointments') }}">Appointments</a></li>
      <li class="opacity-80">Update</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('update-appointment') }}" method="POST" id="update_form">
    @csrf
    <input type="hidden" name="appointment_id" id="appointment_id" value="{{ $appointment->id }}" />
    <input type="hidden" name="status" id="form_status" value="" />
    @if(isPackageService($appointment->service))
      <input type="hidden" name="customer" value="{{ $appointment->customer_id }}" />
      <input type="hidden" name="service" value="{{ $appointment->service_id }}" />
      @php
        $selectedPackageId = $appointment->metadata && isset($appointment->metadata['package_id']) ? $appointment->metadata['package_id'] : null;
      @endphp
      @if($selectedPackageId)
        <input type="hidden" name="package_id" value="{{ $selectedPackageId }}" />
      @endif
    @endif
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="fieldset mt-2 grid grid-cols-1 gap-6 xl:grid-cols-2">
          <div class="space-y-2">
            <label class="fieldset-label" for="customer">Customer*</label>
            <select class="select w-full" name="customer" id="customer" {{ isPackageService($appointment->service) ? 'disabled' : '' }}>
              <option value="" hidden selected>Choose a customer</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="fieldset-label" for="pet">Pet(s)*</label>
            <select class="select w-full" name="pet[]" id="pet" multiple>
            </select>
          </div>
        </div>
        <div class="fieldset mt-2 grid grid-cols-1 gap-6 xl:grid-cols-4">
          <div class="space-y-2">
            <label class="fieldset-label" for="service">Service*</label>
            <select class="select w-full" name="service" id="service" onchange="changeService(this)" value="{{ $appointment->service_id }}" {{ isPackageService($appointment->service) ? 'disabled' : '' }}>
              <option value="" hidden selected>Choose a service</option>
              @foreach($services as $service)
                <option value="{{ $service->id }}" {{ $service->id == $appointment->service_id ? 'selected' : '' }}>{{ $service->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2" id="boarding_start_group">
            <label class="fieldset-label">Drop Off Date/Time*</label>
            <input
              type="datetime-local"
              class="input w-full"
              id="boarding_start_datetime"
              name="boarding_start_datetime"
              format="YYYY-MM-DD HH:mm" placeholder="Select drop off date/time"
              value="{{ $appointment->date ? $appointment->date . 'T' . \Carbon\Carbon::parse($appointment->start_time)->format('H:i') : '' }}"
            />
          </div>
          <div class="space-y-2" id="boarding_end_group">
            <label class="fieldset-label">Pick Up Date/Time*</label>
            <input
              type="datetime-local"
              class="input w-full"
              id="boarding_end_datetime"
              name="boarding_end_datetime"
              format="YYYY-MM-DD HH:mm" placeholder="Select drop off date/time"
              value="{{ $appointment->end_date ? $appointment->end_date . 'T' . \Carbon\Carbon::parse($appointment->end_time)->format('H:i') : '' }}"
            />
          </div>
          <div class="space-y-2 {{ $appointment->cat_room_id ? 'hidden' : '' }}" id="kennel_group">
            <label class="fieldset-label" for="kennel">Kennel*</label>
            <select class="select w-full" name="kennel" id="kennel">
              <option value="" hidden selected>Choose a kennel</option>
              @foreach($kennels as $kennel)
                <option value="{{ $kennel->id }}" {{ (string)($appointment->kennel_id ?? '') === (string)$kennel->id ? 'selected' : '' }}>{{ $kennel->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 {{ $appointment->cat_room_id ? '' : 'hidden' }}" id="room_group">
            <label class="fieldset-label" for="room">Room*</label>
            <select class="select w-full" name="room" id="room">
              <option value="" hidden selected>Choose a room</option>
              @foreach($rooms as $room)
                <option value="{{ $room->id }}" {{ (string)($appointment->cat_room_id ?? '') === (string)$room->id ? 'selected' : '' }}>{{ $room->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="fieldset mt-3 grid grid-cols-1 gap-6 xl:grid-cols-4">
          <div class="space-y-2" id="additional_services_group">
            <label class="fieldset-label" for="additional_services">Additional Services</label>
            <select class="select w-full" name="additional_services[]" id="additional_services" multiple data-selected="{{ $appointment->additional_service_ids }}">
              @foreach($additionalServices as $service)
                @php
                  $selectedAdditionalServices = $appointment->additional_service_ids ? explode(',', $appointment->additional_service_ids) : [];
                @endphp
                <option value="{{ $service->id }}" {{ in_array($service->id, $selectedAdditionalServices) ? 'selected' : '' }}>{{ $service->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 {{ !empty($selectedAdditionalServices) ? '' : 'hidden' }}" id="time_slot_group">
            <label class="fieldset-label" for="time_slot">Start Time - End Time*</label>
            @php
              $selectedTimeSlotId = $appointment->metadata['additional_service_time_slot_id'] ?? null;
              $selectedTimeSlotStart = $appointment->metadata['additional_service_time_slot_start_time'] ?? $appointment->start_time;
            @endphp
            <select class="select w-full" name="time_slot" id="time_slot">
              <option value="" hidden selected>Choose a time slot</option>
              @foreach ($timeSlots as $slot)
                <option value="{{ $slot->id }}" {{ (string) $slot->id === (string) $selectedTimeSlotId || $slot->start_time == $selectedTimeSlotStart ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}</option>
              @endforeach
            </select>
            <input type="hidden" name="time_slot_data" id="time_slot_data" />
          </div>
          <div class="space-y-2 {{ isPackageService($appointment->service) ? 'hidden' : '' }}" id="staff_group">
            <label class="fieldset-label" for="staff">Staff</label>
            <select class="select w-full" name="staff" id="staff">
              <option value="" hidden selected>Choose a staff</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="fieldset-label" for="appointment_status">Appointment Status</label>
            <select class="select w-full" name="appointment_status" id="appointment_status">
              <option value="">-- Select Status --</option>
              <option value="cancelled" {{ $appointment->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
              <option value="no_show" {{ $appointment->status === 'no_show' ? 'selected' : '' }}>No Show</option>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ url()->previous() }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      <button class="btn btn-sm btn-primary" type="button" onclick="saveAppointment()">
        <span class="iconify lucide--check size-4"></span>
        Save
      </button>
    </div>
  </form>
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
    <p class="py-4" id="confirm_message"></p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost btn-sm">No</button>
      </form>
      <button class="btn btn-primary btn-sm btn-soft" id="confirm_status_button" onclick="confirmAction()">Yes</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
@endsection

@section('page-js')
  <script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>
  <script src="{{ asset('src/assets/ui-components-calendar.js') }}"></script>
  <script type="module" src="https://unpkg.com/cally"></script>

  <script>
    const confirm_modal = document.getElementById('confirm_modal');
    const alert_modal = document.getElementById('alert_modal') || null;
    const appointmentDate = "{{ $appointment->date ? \Carbon\Carbon::parse($appointment->date)->format('Y-m-d') : '' }}";

    $(document).ready(function() {
      window.initialKennels = [
        @foreach($kennels as $kennel)
          { id: '{{ $kennel->id }}', name: '{{ addslashes($kennel->name) }}' },
        @endforeach
      ];

      // customer select2 with ajax
      $('#customer').select2({
        placeholder: "Choose a customer",
        ajax: {
          url: '{{ route("get-appointment-customers") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term // Send the search term as 'q'
            };
          },
          processResults: function (data) {
            return {
              results: data.map(function (customer) {
                return {
                  id: customer.id,
                  first_name: customer.profile.first_name,
                  last_name: customer.profile.last_name,
                  email: customer.email,
                  phone_number: customer.profile.phone_number_1
                };
              })
            };
          }
        },
        templateResult: function (customer) {
          if (!customer.id) {
            return customer.text;
          }
          if (!customer.first_name) {
            return customer.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${customer.first_name} ${customer.last_name}</span>
              <span class="text-sm text-base-content/70">(${customer.email} | ${customer.phone_number})</span>
            </div>
          `);
          return $container;
        },
        templateSelection: function (customer) {
          if (!customer.id) {
            return customer.text;
          }
          if (!customer.first_name) {
            return customer.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${customer.first_name} ${customer.last_name}</span>
              <span class="text-sm text-base-content/70">(${customer.email} | ${customer.phone_number})</span>
            </div>
          `);
          return $container;
        }
      });

      $('#pet').select2({
        placeholder: "Choose pet(s)",
        allowClear: true,
        multiple: true,
        width: '100%',
        closeOnSelect: false
      });

      // Add the customer option if not present
      var customerText = "{{ $appointment->customer->profile->first_name ?? '' }} {{ $appointment->customer->profile->last_name ?? '' }} ({{ $appointment->customer->email ?? '' }} | {{ $appointment->customer->profile->phone_number_1 ?? '' }})";
      var customerOption = new Option(customerText, "{{ $appointment->customer_id }}", true, true);
      $('#customer').append(customerOption).trigger('change');

      $('#customer').on('select2:select', function (e) {
        const customerId = e.params.data.id;
        loadPets(customerId);
      });

      const currentCustomerId = "{{ $appointment->customer_id }}";
      const currentPetIds = @json($appointment->family_pet_ids);
      loadPets(currentCustomerId, currentPetIds);

      @if(!isPackageService($appointment->service))
      $('#staff').select2({
        placeholder: "Choose a staff",
        ajax: {
          url: '{{ route("get-appointment-staffs") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term // Send the search term as 'q'
            };
          },
          processResults: function (data) {
            return {
              results: data.map(function (staff) {
                return {
                  id: staff.id,
                  first_name: staff.profile.first_name,
                  last_name: staff.profile.last_name,
                  email: staff.email,
                  phone_number: staff.profile.phone_number_1
                };
              })
            };
          }
        },
        templateResult: function (staff) {
          if (!staff.id) {
            return staff.text;
          }
          if (!staff.first_name) {
            return staff.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${staff.first_name} ${staff.last_name}</span>
              <span class="text-sm text-base-content/70">(${staff.email} | ${staff.phone_number})</span>
            </div>
          `);
          return $container;
        },
        templateSelection: function (staff) {
          if (!staff.id) {
            return staff.text;
          }
          if (!staff.first_name) {
            return staff.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${staff.first_name} ${staff.last_name}</span>
              <span class="text-sm text-base-content/70">(${staff.email} | ${staff.phone_number})</span>
            </div>
          `);
          return $container;
        }
      });
      // Add the staff option if not present
      var staffText = "{{ $appointment->staff->profile->first_name ?? '' }} {{ $appointment->staff->profile->last_name ?? '' }} ({{ $appointment->staff->email ?? '' }} | {{ $appointment->staff->profile->phone_number_1 ?? '' }})";
      var staffOption = new Option(staffText, "{{ $appointment->staff_id }}", true, true);
      $('#staff').append(staffOption).trigger('change');
      @endif

      $('#kennel').select2({
        placeholder: "Choose a kennel",
        width: '100%',
        allowClear: true
      });

      $('#room').select2({
        placeholder: "Choose a room",
        width: '100%',
        allowClear: true
      });

      window.originalAdditionalOptions = $('#additional_services').html();

      // Define servicesData globally so it's accessible to all functions
      window.servicesData = [];
      @foreach($services as $s)
        window.servicesData.push({
          id: {{ $s->id }},
          name: '{{ addslashes($s->name) }}',
          category_name: '{{ $s->category ? addslashes($s->category->name) : '' }}'
        });
      @endforeach

      // Define additionalServicesData with category and level info
      window.additionalServicesData = [];
      @foreach($additionalServices as $s)
        window.additionalServicesData.push({
          id: {{ $s->id }},
          name: '{{ addslashes($s->name) }}',
          category_name: '{{ $s->category ? addslashes($s->category->name) : '' }}',
          level: '{{ $s->level }}',
        });
      @endforeach

      $('#additional_services').select2({
        placeholder: "Choose additional services (optional)",
        allowClear: true,
        multiple: true,
        width: '100%',
        closeOnSelect: false
      }).on('change', function() {
        handleAdditionalServiceTimeSlotState();
      });

      $('#boarding_end_datetime').on('change', function() {
        handleAdditionalServiceTimeSlotState();
        refreshAvailableKennels();
      });

      $('#boarding_start_datetime').on('change', function() {
        refreshAvailableKennels();
      });

      $('#pet').on('change', function() {
        updateBoardingLocationField();
        if (isBoardingSelectedService($('#service').val())) {
          handleAdditionalServiceTimeSlotState();
        }
        refreshAvailableKennels();
      });

      $('#time_slot').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const slotDataAttr = selectedOption.attr('data-slot-data');
        if (slotDataAttr) {
          const slotData = JSON.parse(decodeURIComponent(slotDataAttr));
          $('#time_slot_data').val(JSON.stringify(slotData));
        } else {
          $('#time_slot_data').val('');
        }
      });

      const selectedServiceId = $('#service').val();
      if (selectedServiceId) {
        updateAdditionalServices(selectedServiceId, true);
        updateBoardingLocationField();
        handleAdditionalServiceTimeSlotState();
        refreshAvailableKennels();
      }
    });

    function normalizeSelectedPetIds(selectedPetIds = []) {
      if (Array.isArray(selectedPetIds)) {
        return selectedPetIds.map(function(id) {
          return String(id);
        });
      }

      if (selectedPetIds) {
        return [String(selectedPetIds)];
      }

      return [];
    }

    function loadPets(customerId, selectedPetIds = []) {
      if (!customerId) {
        return;
      }

      const normalizedPetIds = normalizeSelectedPetIds(selectedPetIds);

      $.ajax({
        url: '{{ url("/appointment/pets") }}/' + customerId,
        type: 'GET',
        dataType: 'json',
        success: function(pets) {
          $('#pet').empty();

          $.each(pets, function(index, pet) {
            const selected = normalizedPetIds.includes(String(pet.id)) ? ' selected' : '';
            const petType = String(pet.type || '');
            $('#pet').append('<option value="' + pet.id + '" data-pet-type="' + petType + '"' + selected + '>' + pet.name + '</option>');
          });

          $('#pet').val(normalizedPetIds).trigger('change');
          updateBoardingLocationField();

          const primaryPetId = getPrimaryPetId();
          if (isBoardingSelectedService($('#service').val())) {
            handleAdditionalServiceTimeSlotState();
          } else if (appointmentDate && primaryPetId) {
            populateTimeSlots($('#service').val(), appointmentDate, primaryPetId);
          }
        },
        error: function() {
          console.error('Failed to fetch pets for the selected customer.');
        }
      });
    }

    function isBoardingSelectedService(serviceId) {
      const service = window.servicesData.find(function(s) {
        return String(s.id) === String(serviceId);
      });

      return !!(service && service.category_name && service.category_name.toLowerCase().includes('boarding'));
    }

    function getSelectedPetIds() {
      return $('#pet').val() || [];
    }

    function getPrimaryPetId() {
      const petIds = getSelectedPetIds();
      return petIds.length > 0 ? petIds[0] : '';
    }

    function shouldUseRoomForSelectedPets() {
      const petTypes = $('#pet option:selected').map(function() {
        return String($(this).data('pet-type') || '').trim().toLowerCase();
      }).get().filter(function(type) {
        return type !== '';
      });

      return petTypes.length > 0 && petTypes.every(function(type) {
        return type === 'cat';
      });
    }

    function renderKennelOptions(kennels, selectedKennelId = '') {
      const $kennel = $('#kennel');
      $kennel.empty();
      $kennel.append('<option value="" hidden selected>Choose a kennel</option>');

      if (!kennels || kennels.length === 0) {
        $kennel.append('<option value="" disabled>No available kennels</option>');
        $kennel.val('').trigger('change');
        return;
      }

      kennels.forEach(function(kennel) {
        $kennel.append('<option value="' + kennel.id + '">' + kennel.name + '</option>');
      });

      const selectedExists = selectedKennelId && kennels.some(function(kennel) {
        return String(kennel.id) === String(selectedKennelId);
      });

      $kennel.val(selectedExists ? String(selectedKennelId) : '').trigger('change');
    }

    function refreshAvailableKennels() {
      if (!isBoardingSelectedService($('#service').val()) || shouldUseRoomForSelectedPets()) {
        renderKennelOptions(window.initialKennels || [], $('#kennel').val());
        return;
      }

      const boardingStart = $('#boarding_start_datetime').val();
      const boardingEnd = $('#boarding_end_datetime').val();
      const currentKennel = $('#kennel').val();

      if (!boardingStart || !boardingEnd) {
        renderKennelOptions(window.initialKennels || [], currentKennel);
        return;
      }

      $.ajax({
        url: '{{ route("get-appointment-available-kennels") }}',
        method: 'GET',
        dataType: 'json',
        data: {
          boarding_start_datetime: boardingStart,
          boarding_end_datetime: boardingEnd,
          appointment_id: '{{ $appointment->id }}',
          selected_kennel_id: '{{ $appointment->kennel_id }}'
        },
        success: function(kennels) {
          renderKennelOptions(kennels, currentKennel || '{{ $appointment->kennel_id }}');
        },
        error: function() {
          renderKennelOptions(window.initialKennels || [], currentKennel);
        }
      });
    }

    function updateBoardingLocationField() {
      if (!isBoardingSelectedService($('#service').val())) {
        $('#kennel_group').removeClass('hidden');
        $('#room_group').addClass('hidden');
        return;
      }

      const selectedPetIds = getSelectedPetIds();
      if (selectedPetIds.length === 0) {
        if ($('#room').val()) {
          $('#kennel_group').addClass('hidden');
          $('#room_group').removeClass('hidden');
        } else {
          $('#kennel_group').removeClass('hidden');
          $('#room_group').addClass('hidden');
        }
        return;
      }

      if (shouldUseRoomForSelectedPets()) {
        $('#kennel_group').addClass('hidden');
        $('#room_group').removeClass('hidden');
        $('#kennel').val('').trigger('change');
      } else {
        $('#kennel_group').removeClass('hidden');
        $('#room_group').addClass('hidden');
        $('#room').val('').trigger('change');
      }

      refreshAvailableKennels();
    }

    function changeService(ele) {
      const serviceId = $(ele).val();
      const petId = getPrimaryPetId();

      $('#time_slot').empty();
      $('#time_slot').append('<option value="" hidden selected>Choose a time slot</option>');
      $('#time_slot_data').val('');

      updateAdditionalServices(serviceId, false);
      updateBoardingLocationField();

      if (!isBoardingSelectedService(serviceId) && appointmentDate && petId) {
        populateTimeSlots(serviceId, appointmentDate, petId);
      } else {
        handleAdditionalServiceTimeSlotState();
      }

      refreshAvailableKennels();
    }

    function updateAdditionalServices(selectedServiceId, preserveSelection = true) {
      let currentValues = $('#additional_services').val() || [];

      try {
        $('#additional_services').select2('destroy');
      } catch (e) {
        console.error('Failed to destroy select2 for additional services.');
      }

      $('#additional_services').html(window.originalAdditionalOptions);

      if (selectedServiceId) {
        $('#additional_services option[value="' + selectedServiceId + '"]').remove();
      }

      $('#additional_services').select2({
        placeholder: "Choose additional services (optional)",
        allowClear: true,
        multiple: true,
        width: '100%',
        closeOnSelect: false
      }).on('change', function() {
        handleAdditionalServiceTimeSlotState();
      });

      if (preserveSelection && currentValues.length > 0) {
        const validValues = currentValues.filter(function(value) {
          return $('#additional_services option[value="' + value + '"]').length > 0;
        });
        $('#additional_services').val(validValues).trigger('change');
      }
    }

    function getSelectedAdditionalServiceForTimeSlot() {
      const selectedAdditionalServiceIds = $('#additional_services').val() || [];
      return selectedAdditionalServiceIds.length > 0 ? selectedAdditionalServiceIds[0] : null;
    }

    function handleAdditionalServiceTimeSlotState() {
      const serviceId = $('#service').val();

      if (!isBoardingSelectedService(serviceId)) {
        $('#time_slot_group').removeClass('hidden');

        const primaryPetId = getPrimaryPetId();
        if (appointmentDate && primaryPetId) {
          populateTimeSlots(serviceId, appointmentDate, primaryPetId);
        }
        return;
      }

      const additionalServiceId = getSelectedAdditionalServiceForTimeSlot();
      const petId = getPrimaryPetId();
      const boardingEndDateTime = $('#boarding_end_datetime').val();
      const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
      const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';

      if (!additionalServiceId) {
        $('#time_slot_group').addClass('hidden');
        $('#time_slot').empty().append('<option value="" hidden selected>Choose a time slot</option>');
        $('#time_slot').val('').trigger('change');
        $('#time_slot_data').val('');
        return;
      }

      $('#time_slot_group').removeClass('hidden');

      if (!petId || !pickupDate || !pickupTime) {
        $('#time_slot').empty().append('<option value="" hidden selected>Select pet and pick up time first</option>');
        $('#time_slot').val('').trigger('change');
        $('#time_slot_data').val('');
        return;
      }

      populateTimeSlots(additionalServiceId, pickupDate, petId, pickupTime, true);
    }

    function populateTimeSlots(serviceId, date, petId, pickupTime = '', isBoardingAdditionalService = false) {
      if (!serviceId || !date || date === '-' || !petId) {
        $('#time_slot').empty();
        $('#time_slot').append('<option value="" hidden selected>Choose a time slot</option>');
        return;
      }

      $.ajax({
        url: '{{ route("get-appointment-timeslots") }}',
        method: 'POST',
        data: {
          service_id: serviceId,
          date: date,
          pet_id: petId,
          pickup_time: pickupTime,
          is_boarding_additional_service: isBoardingAdditionalService ? 1 : 0
        },
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        dataType: 'json',
        success: function(timeSlots) {
          $('#time_slot').empty();
          $('#time_slot').append('<option value="" hidden selected>Choose a time slot</option>');

          @php
            $selectedAdditionalSlotId = $appointment->metadata['additional_service_time_slot_id'] ?? null;
            $selectedAdditionalSlotStart = $appointment->metadata['additional_service_time_slot_start_time'] ?? null;
            $selectedAdditionalSlotEnd = $appointment->metadata['additional_service_time_slot_end_time'] ?? null;
          @endphp
          const selectedAdditionalSlotId = "{{ $selectedAdditionalSlotId ?? '' }}";
          const selectedAdditionalSlotStart = "{{ $selectedAdditionalSlotStart ?? '' }}";
          const selectedAdditionalSlotEnd = "{{ $selectedAdditionalSlotEnd ?? '' }}";
          const appointmentStartTime = "{{ $appointment->start_time ?? '' }}";
          let selectedOptionExists = false;

          if (timeSlots.length === 0) {
            if (selectedAdditionalSlotId && selectedAdditionalSlotStart && selectedAdditionalSlotEnd) {
              const selectedLabel = formatTimeToAMPM(selectedAdditionalSlotStart) + ' - ' + formatTimeToAMPM(selectedAdditionalSlotEnd);
              $('#time_slot').append('<option value="' + selectedAdditionalSlotId + '" selected data-slot-data="">' + selectedLabel + '</option>');
            } else {
              $('#time_slot').append('<option value="" disabled>No available time slots</option>');
            }
            return;
          }

          $.each(timeSlots, function(index, slot) {
            const start = formatTimeToAMPM(slot.start_time);
            const end = formatTimeToAMPM(slot.end_time);
            const displayText = start + ' - ' + end;
            const disabled = slot.status !== 'available' ? 'disabled' : '';
            const slotValue = slot.is_virtual ? slot.start_time : (slot.id || slot.start_time);

            const isSelected = selectedAdditionalSlotId
              ? String(slot.id || '') === String(selectedAdditionalSlotId) || slot.start_time === selectedAdditionalSlotStart
              : slot.start_time === appointmentStartTime;

            if (isSelected) {
              selectedOptionExists = true;
            }

            $('#time_slot').append('<option value="' + slotValue + '" ' + disabled + (isSelected ? ' selected' : '') + ' data-slot-data="' + encodeURIComponent(JSON.stringify(slot)) + '">' + displayText + '</option>');

            if (isSelected) {
              $('#time_slot_data').val(JSON.stringify(slot));
            }
          });

          if (!selectedOptionExists && selectedAdditionalSlotId && selectedAdditionalSlotStart && selectedAdditionalSlotEnd) {
            const selectedLabel = formatTimeToAMPM(selectedAdditionalSlotStart) + ' - ' + formatTimeToAMPM(selectedAdditionalSlotEnd);
            $('#time_slot').append('<option value="' + selectedAdditionalSlotId + '" selected data-slot-data="">' + selectedLabel + '</option>');
          }
        },
        error: function() {
          console.error('Failed to fetch time slots for the selected service and date.');
        }
      });
    }

    function formatTimeToAMPM(timeStr) {
      // timeStr is '09:00:00'
      const [hours, minutes, seconds] = timeStr.split(':');
      const date = new Date();
      date.setHours(hours, minutes, seconds || 0);
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    function hasSelectedChauffeurAdditionalService(selectedAdditionalServiceIds) {
      if (!selectedAdditionalServiceIds || selectedAdditionalServiceIds.length === 0) {
        return false;
      }

      return selectedAdditionalServiceIds.some(function(serviceId) {
        const additionalService = window.additionalServicesData.find(function(s) {
          return String(s.id) === String(serviceId);
        });
        const categoryName = additionalService && additionalService.category_name
          ? additionalService.category_name.toLowerCase()
          : '';

        return categoryName.includes('chauffeur');
      });
    }

    function showAddressValidationErrors(ownerAddressValid, facilityAddressValid) {
      const messages = [];

      if (!ownerAddressValid) {
        messages.push('<li>Owner address is invalid</li>');
      }

      if (!facilityAddressValid) {
        messages.push('<li>Facility address is invalid</li>');
      }

      if (messages.length === 0) {
        return;
      }

      const html = `
        <div class="text-left">
          <p>Please address the following issues before updating the appointment:</p>
          <ul style="list-style: none; font-size: 14px; padding-top: 6px;">${messages.join('')}</ul>
        </div>
      `;

      $('#alert_message').html(html);
      alert_modal.showModal();
    }

    function saveAppointment() {
      const selectedStatus = $('#appointment_status').val();
      const currentStatus = '{{ $appointment->status }}';
      
      if (selectedStatus === 'cancelled' || selectedStatus === 'no_show') {
        const statusText = selectedStatus === 'cancelled' ? 'cancel' : 'mark as no show';
        $('#confirm_message').text(`Are you sure you want to ${statusText} this appointment?`);
        
        $('#confirm_modal .btn-primary').off('click').on('click', function() {
          confirm_modal.close();
          $('#form_status').val(selectedStatus);
          proceedWithFormSubmission();
        });
        
        confirm_modal.showModal();
        return;
      } else if (selectedStatus === '' && (currentStatus === 'cancelled' || currentStatus === 'no_show')) {
        $('#form_status').val('checked_in');
        proceedWithFormSubmission();
        return;
      }

      if (selectedStatus) {
        $('#form_status').val(selectedStatus === '' ? 'checked_in' : selectedStatus);
      }
      proceedWithFormSubmission();
    }

    function proceedWithFormSubmission() {
      const customer = $('#customer').val();
      const pet = getSelectedPetIds();
      const primaryPetId = getPrimaryPetId();
      const service = $('#service').val();
      const timeSlot = $('#time_slot').val();
      const selectedAdditionalServices = $('#additional_services').val() || [];
      const chauffeurSelected = hasSelectedChauffeurAdditionalService(selectedAdditionalServices);
      const isBoarding = $('#boarding_start_group').is(':visible');
      const boardingStart = $('#boarding_start_datetime').val();
      const boardingEnd = $('#boarding_end_datetime').val();
      const kennel = $('#kennel').val();
      const room = $('#room').val();
      const useRoom = shouldUseRoomForSelectedPets();

      if (!customer || pet.length === 0 || !service) {
        $('#alert_message').text('Please fill in all required fields.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && useRoom && !room) {
        $('#alert_message').text('Please select a cat room for the boarding appointment.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && !useRoom && !kennel) {
        $('#alert_message').text('Please select a kennel for the boarding appointment.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && selectedAdditionalServices.length > 0 && !timeSlot) {
        $('#alert_message').text('Please select a valid time slot for the additional service.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding) {
        if (!boardingStart || !boardingEnd) {
          $('#alert_message').text('Please select both drop off and pick up date/time for boarding service.');
          alert_modal.showModal();
          return;
        }

        if (new Date(boardingStart) >= new Date(boardingEnd)) {
          $('#alert_message').text('Pick up date/time must be after drop off date/time for boarding service.');
          alert_modal.showModal();
          return;
        }
      }

      $.ajax({
        url: '{{ route("get-validation-info") }}',
        method: 'POST',
        data: {
          pet_id: primaryPetId || null,
          service_id: service,
          additional_services: selectedAdditionalServices,
        },
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        dataType: 'json',
        success: function(response) {
          if (chauffeurSelected && (!response.owner_address_valid || !response.facility_address_valid)) {
            showAddressValidationErrors(response.owner_address_valid, response.facility_address_valid);
            return;
          }

          let validationMessage = '';
          if (!response.owner_status) {
            validationMessage += '<li>Pet owner\'s profile is inactive.</li>';
          }
          if (response.vaccine_status === 'expired') {
            validationMessage += '<li>Pet vaccination is expired.</li>';
          } else if (!response.vaccine_status) {
            validationMessage += '<li>Pet vaccination records is not approved.</li>';
          }
          if (!response.questionnaire_status) {
            validationMessage += '<li>Pet questionnaire is not approved.</li>';
          }

          if (validationMessage) {
            $('#confirm_message').html(
              'Please address the following issues before updating the appointment:<br>' +
              '<ul style="list-style: disc; font-size: 14px; padding-left: 24px; padding-top: 6px;">' + validationMessage + '</ul>'
            );
            confirm_modal.showModal();
            return;
          }

          $('#update_form').submit();
        },
        error: function() {
          console.error('Failed to validate appointment details.');
          $('#alert_message').text('An error occurred while validating the appointment. Please try again.');
          alert_modal.showModal();
        }
      });
    }

    function confirmAction() {
      const selectedStatus = $('#appointment_status').val();
      if (selectedStatus) {
        $('#form_status').val(selectedStatus === '' ? 'checked_in' : selectedStatus);
      }
      $('#update_form').submit();
    }

  </script>
@endsection