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
    <input type="hidden" name="allow_assignment_conflict" id="allow_assignment_conflict" value="0" />
    <input type="hidden" name="assignment_conflict_info" id="assignment_conflict_info" value="" />
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
          <div class="space-y-2" id="room_group">
            <label class="fieldset-label" for="room">Assignment*</label>
            <select class="select w-full" name="room" id="room">
              <option value="" hidden selected>Choose a room</option>
              @foreach($rooms as $room)
                <option
                  value="{{ $room->id }}"
                  data-room-type="{{ implode(',', $room->room_type_array) }}"
                  data-kennel-ids="{{ implode(',', $room->kennel_id_array) }}"
                  data-restrict-count="{{ $room->restrict_count }}"
                  {{ (string)($selectedAssignmentRoomId ?? '') === (string)$room->id ? 'selected' : '' }}
                >{{ $room->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 {{ $selectedAssignmentRoomId ? 'hidden' : '' }}" id="kennel_group">
            <label class="fieldset-label" for="kennel">Kennel*</label>
            <select class="select w-full" name="kennel" id="kennel">
              <option value="" hidden selected>Choose a kennel</option>
              @foreach($kennels as $kennel)
                <option value="{{ $kennel->id }}" {{ (string)($appointment->kennel_id ?? '') === (string)$kennel->id ? 'selected' : '' }}>{{ $kennel->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 hidden xl:col-span-4" id="family_kennel_assignments_group">
            <label class="fieldset-label">Kennel Assignments*</label>
            <div id="family_kennel_assignments_container" class="grid grid-cols-1 gap-3 xl:grid-cols-2"></div>
          </div>
          <div class="space-y-2" id="additional_services_group">
            <label class="fieldset-label" for="additional_services">Additional Services</label>
            <div id="additional_services_single_wrapper">
              <select class="select w-full" name="additional_services[]" id="additional_services" multiple data-selected="{{ $appointment->additional_service_ids }}">
                @foreach($additionalServices as $service)
                  <option value="{{ $service->id }}" {{ in_array($service->id, $selectedAdditionalServiceIdsFlat ?? []) ? 'selected' : '' }}>{{ $service->name }}</option>
                @endforeach
              </select>
            </div>
            <div id="additional_services_by_pet_container" class="hidden space-y-3"></div>
          </div>
          <div class="space-y-2 {{ !empty($selectedAdditionalServiceIdsFlat) ? '' : 'hidden' }}" id="time_slot_group">
            <label class="fieldset-label" for="time_slot">Start Time - End Time*</label>
            @php
              $selectedTimeSlotId = $appointment->metadata['additional_service_time_slot_id'] ?? null;
              $selectedTimeSlotStart = $appointment->metadata['additional_service_time_slot_start_time'] ?? $appointment->start_time;
            @endphp
            <div id="single_time_slot_wrapper">
              <select class="select w-full" name="time_slot" id="time_slot">
                <option value="" hidden selected>Choose a time slot</option>
                @foreach ($timeSlots as $slot)
                  <option value="{{ $slot->id }}" {{ (string) $slot->id === (string) $selectedTimeSlotId || $slot->start_time == $selectedTimeSlotStart ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}</option>
                @endforeach
              </select>
              <input type="hidden" name="time_slot_data" id="time_slot_data" />
            </div>
            <div id="additional_service_time_slots_container" class="hidden space-y-3"></div>
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

<dialog id="assignment_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Assignment Warning
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <p class="py-4" id="assignment_message"></p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost btn-sm" onclick="changeAssignmentRoom()">Change Room</button>
      </form>
      <button id="continue_anyway_btn" class="btn btn-primary btn-sm btn-soft" onclick="continueWithAssignmentConflict()">Continue Anyway</button>
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
    const assignment_modal = document.getElementById('assignment_modal');
    const alert_modal = document.getElementById('alert_modal') || null;
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
      window.initialKennels = [
        @foreach($kennels as $kennel)
          { id: '{{ $kennel->id }}', name: '{{ addslashes($kennel->name) }}' },
        @endforeach
      ];
      window.initialRooms = [
        @foreach($rooms as $room)
          {
            id: '{{ $room->id }}',
            name: '{{ addslashes($room->name) }}',
            room_types: '{{ addslashes(implode(',', $room->room_type_array)) }}',
            kennel_ids: '{{ addslashes(implode(',', $room->kennel_id_array)) }}',
            restrict_count: '{{ $room->restrict_count }}'
          },
        @endforeach
      ];
      window.initialFamilyPetAssignments = @json($appointment->family_pet_assignments ?? []);

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

      window.initialAdditionalServicesByPet = @json($appointmentAdditionalServicesByPet ?? []);
      window.initialAdditionalServiceTimeSlotsByPet = @json($appointment->metadata['additional_service_time_slots_by_pet'] ?? []);
      window.initialAdditionalServiceTimeSlots = @json($appointment->metadata['additional_service_time_slots'] ?? []);
      window.additionalServicesByPetState = {};
      window.selectedAdditionalServiceTimeslotsByPair = {};
      window.initialAdditionalServiceTimeslotDetailsByPair = {};

      if (window.initialAdditionalServicesByPet && typeof window.initialAdditionalServicesByPet === 'object') {
        Object.keys(window.initialAdditionalServicesByPet).forEach(function(petId) {
          window.initialAdditionalServicesByPet[String(petId)] = normalizeServiceIdList(window.initialAdditionalServicesByPet[petId]);
          window.additionalServicesByPetState[String(petId)] = normalizeServiceIdList(window.initialAdditionalServicesByPet[petId]);
        });
      }

      if ((!window.initialAdditionalServiceTimeSlots || Object.keys(window.initialAdditionalServiceTimeSlots).length === 0)
          && "{{ $appointment->metadata['additional_service_time_slot_id'] ?? '' }}"
          && "{{ $appointment->metadata['additional_service_time_slot_service_id'] ?? '' }}") {
        window.initialAdditionalServiceTimeSlots = {
          "{{ $appointment->metadata['additional_service_time_slot_service_id'] ?? '' }}": {
            time_slot_id: "{{ $appointment->metadata['additional_service_time_slot_id'] ?? '' }}"
          }
        };
      }

      if (window.initialAdditionalServiceTimeSlots && typeof window.initialAdditionalServiceTimeSlots === 'object') {
        const normalizedTimeSlotMap = {};

        Object.keys(window.initialAdditionalServiceTimeSlots).forEach(function(serviceId) {
          const rawDetails = window.initialAdditionalServiceTimeSlots[serviceId];
          const normalizedSlotId = (rawDetails && typeof rawDetails === 'object')
            ? String(rawDetails.time_slot_id || '')
            : String(rawDetails || '');

          if (!normalizedSlotId) {
            return;
          }

          normalizedTimeSlotMap[String(serviceId)] = {
            time_slot_id: normalizedSlotId,
            service_id: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.service_id || '') : '',
            date: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.date || '') : '',
            start_time: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.start_time || '') : '',
            end_time: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.end_time || '') : ''
          };
        });

        window.initialAdditionalServiceTimeSlots = normalizedTimeSlotMap;
      }

      if (window.initialAdditionalServiceTimeSlotsByPet && typeof window.initialAdditionalServiceTimeSlotsByPet === 'object') {
        Object.keys(window.initialAdditionalServiceTimeSlotsByPet).forEach(function(petId) {
          const serviceSlots = window.initialAdditionalServiceTimeSlotsByPet[petId];
          if (!serviceSlots || typeof serviceSlots !== 'object') {
            return;
          }

          Object.keys(serviceSlots).forEach(function(serviceId) {
            const rawDetails = serviceSlots[serviceId];
            const normalizedSlotId = (rawDetails && typeof rawDetails === 'object')
              ? String(rawDetails.time_slot_id || '')
              : String(rawDetails || '');

            if (!normalizedSlotId) {
              return;
            }

            const pairKey = String(petId) + '_' + String(serviceId);
            window.initialAdditionalServiceTimeslotDetailsByPair[pairKey] = {
              time_slot_id: normalizedSlotId,
              service_id: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.service_id || serviceId || '') : String(serviceId),
              date: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.date || '') : '',
              start_time: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.start_time || '') : '',
              end_time: rawDetails && typeof rawDetails === 'object' ? String(rawDetails.end_time || '') : ''
            };
            window.selectedAdditionalServiceTimeslotsByPair[pairKey] = normalizedSlotId;
          });
        });
      }

      if (Object.keys(window.initialAdditionalServiceTimeslotDetailsByPair).length === 0 && window.initialAdditionalServiceTimeSlots && typeof window.initialAdditionalServiceTimeSlots === 'object') {
        Object.keys(window.initialAdditionalServicesByPet || {}).forEach(function(petId) {
          const serviceIds = normalizeServiceIdList(window.initialAdditionalServicesByPet[petId] || []);
          serviceIds.forEach(function(serviceId) {
            const rawDetails = window.initialAdditionalServiceTimeSlots[String(serviceId)];
            if (!rawDetails) {
              return;
            }

            const normalizedSlotId = String(rawDetails.time_slot_id || '');
            if (!normalizedSlotId) {
              return;
            }

            const pairKey = String(petId) + '_' + String(serviceId);
            window.initialAdditionalServiceTimeslotDetailsByPair[pairKey] = {
              time_slot_id: normalizedSlotId,
              service_id: String(rawDetails.service_id || serviceId || ''),
              date: String(rawDetails.date || ''),
              start_time: String(rawDetails.start_time || ''),
              end_time: String(rawDetails.end_time || '')
            };
            window.selectedAdditionalServiceTimeslotsByPair[pairKey] = normalizedSlotId;
          });
        });
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

      $('#boarding_end_datetime').on('change', function() {
        handleAdditionalServiceTimeSlotState();
        refreshAvailableKennels();
      });

      $('#boarding_start_datetime').on('change', function() {
        refreshAvailableKennels();
      });

      $('#pet').on('change', function() {
        renderAdditionalServicesByPetSelectors();
        updateBoardingLocationField();
        if (isBoardingSelectedService($('#service').val())) {
          handleAdditionalServiceTimeSlotState();
        }
        refreshAvailableKennels();
      });

      $('#room').on('change', function() {
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
        renderAdditionalServicesByPetSelectors();
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

    function normalizeServiceIdList(serviceIds = []) {
      if (!Array.isArray(serviceIds)) {
        return [];
      }

      return Array.from(new Set(serviceIds.map(function(serviceId) {
        return String(serviceId);
      }).filter(function(serviceId) {
        return serviceId.trim() !== '';
      })));
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
            const petSize = String(pet.size || '');
            $('#pet').append('<option value="' + pet.id + '" data-pet-type="' + petType + '" data-pet-size="' + petSize + '"' + selected + '>' + pet.name + '</option>');
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
      return getSelectedRoomType() === 'space';
    }

    function getSelectedRoomOption() {
      return $('#room option:selected');
    }

    function getSelectedRoomType() {
      const roomType = String(getSelectedRoomOption().data('room-type') || '').trim().toLowerCase();
      return roomType.includes('space') ? 'space' : 'standard';
    }

    function getSelectedRoomKennelIds() {
      const kennelIds = String(getSelectedRoomOption().data('kennel-ids') || '').trim();

      if (!kennelIds) {
        return [];
      }

      return kennelIds.split(',').map(function(id) {
        return String(id).trim();
      }).filter(function(id) {
        return id !== '';
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
      if (!isBoardingSelectedService($('#service').val())) {
        $('#room_group').removeClass('hidden');
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('.family-pet-room-select').prop('disabled', true);
        $('.family-pet-kennel-select').prop('disabled', true);
        return;
      }

      const familyMode = getSelectedPetAssignmentMode();

      if (familyMode === 'individual') {
        $('#room_group').addClass('hidden');
        $('#kennel_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        renderFamilyPetAssignmentFields(Object.assign({}, window.initialFamilyPetAssignments || {}, getSelectedFamilyPetAssignments()));
        return;
      }

      $('#room_group').removeClass('hidden');

      if (!$('#room').val()) {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('.family-pet-room-select').prop('disabled', true);
        $('.family-pet-kennel-select').prop('disabled', true);
        renderKennelOptions(window.initialKennels || [], $('#kennel').val());
        return;
      }

      if (getSelectedRoomType() === 'space') {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('.family-pet-room-select').prop('disabled', true);
        $('.family-pet-kennel-select').prop('disabled', true);
        $('#kennel').val('').trigger('change');
        return;
      }

      const currentKennel = $('#kennel').val();

      const roomKennelIds = getSelectedRoomKennelIds();
      const roomKennels = (window.initialKennels || []).filter(function(kennel) {
        return roomKennelIds.includes(String(kennel.id));
      });

      $('#family_kennel_assignments_group').addClass('hidden');
      $('.family-pet-room-select').prop('disabled', true);
      $('.family-pet-kennel-select').prop('disabled', true);
      $('#kennel_group').removeClass('hidden');
      $('#kennel').prop('disabled', false);
      renderKennelOptions(roomKennels, currentKennel || '{{ $appointment->kennel_id }}');
    }

    function updateBoardingLocationField() {
      if (!isBoardingSelectedService($('#service').val())) {
        $('#room_group').removeClass('hidden');
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('.family-pet-room-select').prop('disabled', true);
        $('.family-pet-kennel-select').prop('disabled', true);
        return;
      }

      if (getSelectedPetAssignmentMode() === 'individual') {
        $('#room_group').addClass('hidden');
        $('#kennel_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        renderFamilyPetAssignmentFields(Object.assign({}, window.initialFamilyPetAssignments || {}, getSelectedFamilyPetAssignments()));
        return;
      }

      $('#room_group').removeClass('hidden');

      if (shouldUseRoomForSelectedPets()) {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('#kennel').val('').trigger('change');
      } else if ($('#room').val()) {
        $('#kennel_group').removeClass('hidden');
        refreshAvailableKennels();
      } else {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
      }
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

      renderAdditionalServicesByPetSelectors();
    }

    function getSelectedPetDetails() {
      return ($('#pet option:selected') || []).map(function(_, option) {
        return {
          id: String(option.value),
          name: $(option).text().trim(),
          size: String($(option).data('pet-size') || '').trim().toLowerCase()
        };
      }).get();
    }

    function getSelectedPetAssignmentMode() {
      const selectedPets = getSelectedPetDetails();

      if (selectedPets.length <= 1) {
        return 'shared';
      }

      return selectedPets.some(function(pet) {
        return pet.size !== 'small';
      }) ? 'individual' : 'shared';
    }

    function getRoomById(roomId) {
      return (window.initialRooms || []).find(function(room) {
        return String(room.id) === String(roomId);
      }) || null;
    }

    function getRoomTypeById(roomId) {
      const room = getRoomById(roomId);
      if (!room) {
        return '';
      }

      const roomTypes = String(room.room_types || '').toLowerCase();
      return roomTypes.includes('space') ? 'space' : 'standard';
    }

    function getKennelsForRoom(roomId) {
      const room = getRoomById(roomId);
      if (!room) {
        return [];
      }

      const roomKennelIds = String(room.kennel_ids || '')
        .split(',')
        .map(function(id) {
          return String(id).trim();
        })
        .filter(function(id) {
          return id !== '';
        });

      return (window.initialKennels || []).filter(function(kennel) {
        return roomKennelIds.includes(String(kennel.id));
      });
    }

    function getSelectedFamilyPetAssignments() {
      const assignments = {};

      $('.family-pet-room-select').each(function() {
        const petId = String($(this).data('pet-id') || '');
        const roomId = String($(this).val() || '');

        if (!petId || !roomId) {
          return;
        }

        const kennelId = String($('#family_pet_kennel_' + petId).val() || '');

        assignments[petId] = {
          room_id: roomId,
          kennel_id: kennelId || null,
        };
      });

      return assignments;
    }

    function hasMissingFamilyPetAssignments() {
      if (getSelectedPetAssignmentMode() !== 'individual') {
        return false;
      }

      const assignments = getSelectedFamilyPetAssignments();

      return getSelectedPetDetails().some(function(pet) {
        const assignment = assignments[String(pet.id)] || null;
        if (!assignment || !assignment.room_id) {
          return true;
        }

        if (getRoomTypeById(assignment.room_id) === 'standard' && !assignment.kennel_id) {
          return true;
        }

        return false;
      });
    }

    function renderFamilyPetKennelOptions($kennelSelect, roomId, selectedKennelId = '') {
      const kennels = getKennelsForRoom(roomId);
      $kennelSelect.empty();
      $kennelSelect.append('<option value="" hidden selected>Choose a kennel</option>');

      if (!kennels.length) {
        $kennelSelect.append('<option value="" disabled>No available kennels</option>');
        $kennelSelect.val('').trigger('change');
        return;
      }

      kennels.forEach(function(kennel) {
        $kennelSelect.append('<option value="' + kennel.id + '">' + kennel.name + '</option>');
      });

      const selectedExists = selectedKennelId && kennels.some(function(kennel) {
        return String(kennel.id) === String(selectedKennelId);
      });

      $kennelSelect.val(selectedExists ? String(selectedKennelId) : '').trigger('change');
    }

    function renderFamilyPetAssignmentFields(currentAssignments = {}) {
      const selectedPets = getSelectedPetDetails();
      const $container = $('#family_kennel_assignments_container');
      $container.empty();

      if (selectedPets.length <= 1) {
        $('#family_kennel_assignments_group').addClass('hidden');
        return;
      }

      let roomOptions = '<option value="" hidden selected>Choose a room</option>';
      (window.initialRooms || []).forEach(function(room) {
        roomOptions += '<option value="' + room.id + '">' + room.name + '</option>';
      });

      selectedPets.forEach(function(pet) {
        const petId = String(pet.id);
        const assignment = currentAssignments[petId] || {};
        const selectedRoomId = String(assignment.room_id || '');

        $container.append(`
          <div class="space-y-2 rounded-box border border-base-300 p-3">
            <label class="fieldset-label">${pet.name}</label>
            <select class="select w-full family-pet-room-select" id="family_pet_room_${petId}" name="family_pet_assignments[${petId}][room_id]" data-pet-id="${petId}">
              ${roomOptions}
            </select>
            <div class="space-y-2" id="family_pet_kennel_group_${petId}">
              <label class="fieldset-label" for="family_pet_kennel_${petId}">Kennel*</label>
              <select class="select w-full family-pet-kennel-select" id="family_pet_kennel_${petId}" name="family_pet_assignments[${petId}][kennel_id]" data-pet-id="${petId}">
                <option value="" hidden selected>Choose a kennel</option>
              </select>
            </div>
          </div>
        `);

        const $roomSelect = $('#family_pet_room_' + petId);
        const $kennelSelect = $('#family_pet_kennel_' + petId);
        const $kennelGroup = $('#family_pet_kennel_group_' + petId);
        $roomSelect.val(selectedRoomId || '').trigger('change');

        const toggleKennelForRoom = function(roomId, initialKennelId = '') {
          if (!roomId || getRoomTypeById(roomId) === 'space') {
            $kennelGroup.addClass('hidden');
            $kennelSelect.prop('disabled', true);
            $kennelSelect.val('').trigger('change');
            return;
          }

          $kennelGroup.removeClass('hidden');
          $kennelSelect.prop('disabled', false);
          renderFamilyPetKennelOptions($kennelSelect, roomId, initialKennelId);
        };

        toggleKennelForRoom(selectedRoomId, assignment.kennel_id || '');

        $roomSelect.off('change').on('change', function() {
          const nextRoomId = String($(this).val() || '');
          toggleKennelForRoom(nextRoomId, '');
        });
      });

      $('.family-pet-room-select').select2({
        placeholder: 'Choose a room',
        width: '100%',
        allowClear: true
      });

      $('.family-pet-kennel-select').select2({
        placeholder: 'Choose a kennel',
        width: '100%',
        allowClear: true
      });

      $('#family_kennel_assignments_group').removeClass('hidden');
    }

    function getPerPetAdditionalServicesPayload() {
      return Object.keys(window.additionalServicesByPetState || {}).reduce(function(payload, petId) {
        payload[String(petId)] = (window.additionalServicesByPetState[petId] || []).map(function(serviceId) {
          return String(serviceId);
        });
        return payload;
      }, {});
    }

    function syncAdditionalServicesByPetStateFromDom() {
      if (!window.additionalServicesByPetState) {
        window.additionalServicesByPetState = {};
      }

      const selectedPetIds = getSelectedPetIds().map(function(petId) {
        return String(petId);
      });

      Object.keys(window.additionalServicesByPetState).forEach(function(petId) {
        if (!selectedPetIds.includes(String(petId))) {
          delete window.additionalServicesByPetState[petId];
        }
      });

      $('.pet-additional-services').each(function() {
        const petId = String($(this).data('pet-id') || '');
        if (!petId) {
          return;
        }

        window.additionalServicesByPetState[petId] = ($(this).val() || []).map(function(serviceId) {
          return String(serviceId);
        });
      });

      pruneAdditionalServiceTimeSlotState();
    }

    function getSelectedAdditionalServicePairsByPet() {
      const selectedPets = getSelectedPetDetails();
      const pairs = [];

      selectedPets.forEach(function(pet) {
        const petId = String(pet.id);
        const serviceIds = (window.additionalServicesByPetState[petId] || []).map(function(serviceId) {
          return String(serviceId);
        });

        serviceIds.forEach(function(serviceId) {
          if (!serviceId) {
            return;
          }

          pairs.push({
            petId: petId,
            petName: pet.name,
            serviceId: serviceId,
            pairKey: petId + '_' + serviceId,
          });
        });
      });

      return pairs;
    }

    function pruneAdditionalServiceTimeSlotState() {
      if (!window.selectedAdditionalServiceTimeslotsByPair) {
        window.selectedAdditionalServiceTimeslotsByPair = {};
      }

      const validPairKeys = getSelectedAdditionalServicePairsByPet().map(function(pair) {
        return pair.pairKey;
      });

      Object.keys(window.selectedAdditionalServiceTimeslotsByPair).forEach(function(pairKey) {
        if (!validPairKeys.includes(pairKey)) {
          delete window.selectedAdditionalServiceTimeslotsByPair[pairKey];
        }
      });
    }

    function collectSelectedAdditionalServiceIds() {
      const perPetPayload = getPerPetAdditionalServicesPayload();
      const flattenedPerPetIds = Object.keys(perPetPayload).reduce(function(carry, petId) {
        return carry.concat(perPetPayload[petId] || []);
      }, []);

      const singleIds = $('#additional_services').prop('disabled') ? [] : ($('#additional_services').val() || []);

      return Array.from(new Set(singleIds.concat(flattenedPerPetIds).filter(function(id) {
        return String(id).trim() !== '';
      })));
    }

    function renderAdditionalServicesByPetSelectors() {
      const selectedPets = getSelectedPetDetails();
      const usePerPetSelectors = selectedPets.length > 1;
      const serviceId = $('#service').val();

      syncAdditionalServicesByPetStateFromDom();

      if (!usePerPetSelectors) {
        const singlePetId = selectedPets.length === 1 ? selectedPets[0].id : null;
        const initialSinglePetSelection = singlePetId && window.initialAdditionalServicesByPet && window.initialAdditionalServicesByPet[singlePetId]
          ? normalizeServiceIdList(window.initialAdditionalServicesByPet[singlePetId])
          : [];
        $('#additional_services_by_pet_container').addClass('hidden').empty();
        $('#additional_services_single_wrapper').removeClass('hidden');
        $('#additional_services').prop('disabled', false);
        const singlePetSelection = singlePetId && Array.isArray(window.additionalServicesByPetState[singlePetId]) && window.additionalServicesByPetState[singlePetId].length > 0
          ? normalizeServiceIdList(window.additionalServicesByPetState[singlePetId])
          : initialSinglePetSelection;
        if (singlePetSelection.length > 0) {
          $('#additional_services').val(singlePetSelection).trigger('change');
        }
        return;
      }

      const $container = $('#additional_services_by_pet_container');
      $container.empty();

      const selectedPetIds = selectedPets.map(function(pet) {
        return String(pet.id);
      });

      Object.keys(window.additionalServicesByPetState || {}).forEach(function(petId) {
        if (!selectedPetIds.includes(String(petId))) {
          delete window.additionalServicesByPetState[petId];
        }
      });

      selectedPets.forEach(function(pet) {
        const initialSelections = window.initialAdditionalServicesByPet && window.initialAdditionalServicesByPet[pet.id]
          ? normalizeServiceIdList(window.initialAdditionalServicesByPet[pet.id])
          : [];
        const currentSelections = normalizeServiceIdList(window.additionalServicesByPetState[pet.id] || initialSelections);
        window.additionalServicesByPetState[pet.id] = currentSelections;
        let optionsHtml = '';

        window.additionalServicesData.forEach(function(additionalService) {
          if (String(additionalService.id) === String(serviceId)) {
            return;
          }

          const isSelected = currentSelections.includes(String(additionalService.id));
          optionsHtml += '<option value="' + additionalService.id + '"' + (isSelected ? ' selected' : '') + '>' + additionalService.name + '</option>';
        });

        const petBlockHtml = `
          <div class="space-y-2 rounded-box border border-base-300 p-3">
            <label class="fieldset-label">${pet.name}</label>
            <select class="select w-full pet-additional-services" name="additional_services_by_pet[${pet.id}][]" data-pet-id="${pet.id}" multiple>
              ${optionsHtml}
            </select>
          </div>
        `;

        $container.append(petBlockHtml);
      });

      $('.pet-additional-services').select2({
        placeholder: 'Choose additional services (optional)',
        allowClear: true,
        multiple: true,
        width: '100%',
        closeOnSelect: false
      }).on('change', function() {
        const petId = String($(this).data('pet-id') || '');
        if (petId) {
          window.additionalServicesByPetState[petId] = ($(this).val() || []).map(function(serviceId) {
            return String(serviceId);
          });
        }

        pruneAdditionalServiceTimeSlotState();
        handleAdditionalServiceTimeSlotState();
      });

      $('#additional_services_single_wrapper').addClass('hidden');
      $('#additional_services').prop('disabled', true).val([]).trigger('change');
      $('#additional_services_by_pet_container').removeClass('hidden');
    }

    function getSelectedAdditionalServiceForTimeSlot() {
      const selectedAdditionalServiceIds = collectSelectedAdditionalServiceIds();
      return selectedAdditionalServiceIds.length > 0 ? selectedAdditionalServiceIds[0] : null;
    }

    function getAdditionalServiceTimeSlotSelections() {
      return Object.assign({}, window.selectedAdditionalServiceTimeslotsByPair || {});
    }

    function hasMissingAdditionalServiceTimeSlots() {
      const selectedPairs = getSelectedAdditionalServicePairsByPet();
      if (selectedPairs.length === 0) {
        return false;
      }

      if (getSelectedPetIds().length <= 1) {
        return !$('#time_slot').val();
      }

      const slotSelections = getAdditionalServiceTimeSlotSelections();
      return selectedPairs.some(function(pair) {
        return !slotSelections[pair.pairKey];
      });
    }

    function handleAdditionalServiceTimeSlotState() {
      syncAdditionalServicesByPetStateFromDom();

      const serviceId = $('#service').val();

      if (!isBoardingSelectedService(serviceId)) {
        $('#single_time_slot_wrapper').removeClass('hidden');
        $('#additional_service_time_slots_container').addClass('hidden').empty();
        $('#time_slot_group label').text('Start Time - End Time*');
        $('#time_slot_group').removeClass('hidden');

        const primaryPetId = getPrimaryPetId();
        if (appointmentDate && primaryPetId) {
          populateTimeSlots(serviceId, appointmentDate, primaryPetId);
        }
        return;
      }

      const selectedAdditionalServiceIds = collectSelectedAdditionalServiceIds();
      const petId = getPrimaryPetId();
      const boardingEndDateTime = $('#boarding_end_datetime').val();
      const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
      const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';

      if (selectedAdditionalServiceIds.length === 0) {
        $('#single_time_slot_wrapper').addClass('hidden');
        $('#additional_service_time_slots_container').addClass('hidden').empty();
        $('#time_slot_group').addClass('hidden');
        $('#time_slot').empty().append('<option value="" hidden selected>Choose a time slot</option>');
        $('#time_slot').val('').trigger('change');
        $('#time_slot_data').val('');
        return;
      }

      const shouldUsePerServiceTimeSlots = getSelectedPetIds().length > 1;
      if (!shouldUsePerServiceTimeSlots) {
        const additionalServiceId = getSelectedAdditionalServiceForTimeSlot();

        $('#single_time_slot_wrapper').removeClass('hidden');
        $('#additional_service_time_slots_container').addClass('hidden').empty();
        $('#time_slot_group').removeClass('hidden');
        $('#time_slot_group label').text('Start Time - End Time*');

        if (!additionalServiceId || !petId || !pickupDate || !pickupTime) {
          $('#time_slot').empty().append('<option value="" hidden selected>Select pet and pick up time first</option>');
          $('#time_slot').val('').trigger('change');
          $('#time_slot_data').val('');
          return;
        }

        populateTimeSlots(additionalServiceId, pickupDate, petId, pickupTime, true);
        return;
      }

      $('#single_time_slot_wrapper').addClass('hidden');
      $('#time_slot_group').removeClass('hidden');
      $('#time_slot_group label').text('Additional Service Time Slots*');

      const selectedPairs = getSelectedAdditionalServicePairsByPet();
      const previousSelections = getAdditionalServiceTimeSlotSelections();
      const $container = $('#additional_service_time_slots_container');
      $container.empty();

      pruneAdditionalServiceTimeSlotState();

      selectedPairs.forEach(function(pair) {
        const service = window.additionalServicesData.find(function(item) {
          return String(item.id) === String(pair.serviceId);
        });
        const serviceName = service ? service.name : 'Additional Service';
        const selectId = 'additional_service_time_slot_' + pair.petId + '_' + pair.serviceId;
        const initialSlotDetails = window.initialAdditionalServiceTimeslotDetailsByPair && window.initialAdditionalServiceTimeslotDetailsByPair[pair.pairKey]
          ? window.initialAdditionalServiceTimeslotDetailsByPair[pair.pairKey]
          : null;
        const initialSlot = initialSlotDetails
          ? String(initialSlotDetails.time_slot_id || '')
          : '';
        const selectedSlotId = previousSelections[pair.pairKey] || initialSlot;

        $container.append(`
          <div class="space-y-2 rounded-box border border-base-300 p-3">
            <label class="fieldset-label" for="${selectId}">${pair.petName} - ${serviceName}*</label>
            <select class="select w-full additional-service-time-slot-select" id="${selectId}" name="additional_service_time_slots_by_pet[${pair.petId}][${pair.serviceId}]" data-service-id="${pair.serviceId}" data-pair-key="${pair.pairKey}" data-pet-id="${pair.petId}">
              <option value="" hidden selected>Choose a time slot</option>
            </select>
          </div>
        `);

        if (!petId || !pickupDate || !pickupTime) {
          $('#' + selectId).empty().append('<option value="" hidden selected>Select pet and pick up time first</option>');
          return;
        }

        populateAdditionalServiceTimeSlotOptions($('#' + selectId), pair.serviceId, pickupDate, pair.petId, pickupTime, selectedSlotId);
      });

      $container.removeClass('hidden');

      // Keep initial values available so rerenders can still restore saved selections.

      $('.additional-service-time-slot-select').off('change').on('change', function() {
        const pairKey = String($(this).data('pair-key') || '');
        if (!pairKey) {
          return;
        }

        window.selectedAdditionalServiceTimeslotsByPair[pairKey] = String($(this).val() || '');
      });
    }

    function populateAdditionalServiceTimeSlotOptions($select, serviceId, date, petId, pickupTime, selectedSlotId = '') {
      $select.empty().append('<option value="" hidden selected>Choose a time slot</option>');

      $.ajax({
        url: '{{ route("get-appointment-timeslots") }}',
        method: 'POST',
        data: {
          service_id: serviceId,
          date: date,
          pet_id: petId,
          pickup_time: pickupTime,
          is_boarding_additional_service: 1
        },
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        dataType: 'json',
        success: function(timeSlots) {
          $select.empty().append('<option value="" hidden selected>Choose a time slot</option>');

          if (!timeSlots || timeSlots.length === 0) {
            $select.append('<option value="" disabled>No available time slots</option>');
            return;
          }

          let selectedOptionExists = false;

          timeSlots.forEach(function(slot) {
            const slotValue = slot.is_virtual ? slot.start_time : (slot.id || slot.start_time);
            const isSelected = selectedSlotId && String(slotValue) === String(selectedSlotId);
            if (isSelected) {
              selectedOptionExists = true;
            }

            const disabled = slot.status !== 'available' ? 'disabled' : '';
            const displayText = formatTimeToAMPM(slot.start_time) + ' - ' + formatTimeToAMPM(slot.end_time);
            $select.append('<option value="' + slotValue + '" ' + disabled + (isSelected ? ' selected' : '') + '>' + displayText + '</option>');
          });

          if (!selectedOptionExists && selectedSlotId) {
            const pairKey = String($select.data('pair-key') || '');
            const initialSlotDetails = window.initialAdditionalServiceTimeslotDetailsByPair && window.initialAdditionalServiceTimeslotDetailsByPair[pairKey]
              ? window.initialAdditionalServiceTimeslotDetailsByPair[pairKey]
              : null;

            let fallbackLabel = 'Previously selected time slot';
            if (initialSlotDetails && initialSlotDetails.start_time && initialSlotDetails.end_time) {
              fallbackLabel = formatTimeToAMPM(initialSlotDetails.start_time) + ' - ' + formatTimeToAMPM(initialSlotDetails.end_time);
            }

            $select.append('<option value="' + selectedSlotId + '" selected>' + fallbackLabel + '</option>');
          }

          const pairKey = String($select.data('pair-key') || '');
          if (pairKey) {
            window.selectedAdditionalServiceTimeslotsByPair[pairKey] = String($select.val() || '');
          }
        },
        error: function() {
          $select.empty().append('<option value="" disabled>Failed to load time slots</option>');
        }
      });
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

    function isWithinBusinessHours(dateTimeValue) {
      if (!dateTimeValue) {
        return false;
      }

      const parts = dateTimeValue.split('T');
      if (parts.length !== 2) {
        return false;
      }

      const timeParts = parts[1].split(':');
      if (timeParts.length < 2) {
        return false;
      }

      const hours = parseInt(timeParts[0], 10);
      const minutes = parseInt(timeParts[1], 10);

      if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return false;
      }

      const totalMinutes = (hours * 60) + minutes;
      const businessStart = 9 * 60;
      const businessEnd = 16 * 60;

      return totalMinutes >= businessStart && totalMinutes <= businessEnd;
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
      
      if (selectedStatus === 'cancelled') {
        const requiresModal = requiresLateCancellationModal(appointmentDate, appointmentStartTime);

        if (!requiresModal) {
          $('#form_status').val(selectedStatus);
          proceedWithFormSubmission();
          return;
        }

        $('#confirm_message').text(LATE_CANCELLATION_MESSAGE);

        $('#confirm_modal .btn-primary').off('click').on('click', function() {
          confirm_modal.close();
          $('#form_status').val(selectedStatus);
          proceedWithFormSubmission();
        });

        confirm_modal.showModal();
        return;
      }

      if (selectedStatus === 'no_show') {
        $('#confirm_message').text('Are you sure you want to mark as no show this appointment?');

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
      const selectedAdditionalServices = collectSelectedAdditionalServiceIds();
      const additionalServicesByPet = getPerPetAdditionalServicesPayload();
      const chauffeurSelected = hasSelectedChauffeurAdditionalService(selectedAdditionalServices);
      const isBoarding = $('#boarding_start_group').is(':visible');
      const boardingStart = $('#boarding_start_datetime').val();
      const boardingEnd = $('#boarding_end_datetime').val();
      const kennel = $('#kennel').val();
      const room = $('#room').val();
      const selectedRoomType = getSelectedRoomType();
      const familyKennelMode = getSelectedPetAssignmentMode();
      const familyPetAssignments = familyKennelMode === 'individual' ? getSelectedFamilyPetAssignments() : {};

      if (!customer || pet.length === 0 || !service) {
        $('#alert_message').text('Please fill in all required fields.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && familyKennelMode !== 'individual' && !room) {
        $('#alert_message').text('Please select a room for the boarding appointment.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && familyKennelMode === 'individual' && hasMissingFamilyPetAssignments()) {
        $('#alert_message').text('Please assign a room and kennel (for standard rooms) to each selected pet.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && selectedRoomType === 'standard' && familyKennelMode === 'shared' && !kennel) {
        $('#alert_message').text('Please select a kennel for the boarding appointment.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding && familyKennelMode !== 'individual' && selectedRoomType === 'standard') {
        const roomKennelIds = getSelectedRoomKennelIds();
        if (kennel && roomKennelIds.length > 0 && !roomKennelIds.includes(String(kennel))) {
          $('#alert_message').text('The selected kennel does not belong to the selected room.');
          alert_modal.showModal();
          return;
        }
      }

      if (isBoarding && selectedAdditionalServices.length > 0 && hasMissingAdditionalServiceTimeSlots()) {
        $('#alert_message').text('Please select a valid time slot for each additional service.');
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

        if (!isWithinBusinessHours(boardingStart)) {
          $('#alert_message').text('Drop-off time must be between 9:00 AM and 4:00 PM.');
          alert_modal.showModal();
          return;
        }

        if (!isWithinBusinessHours(boardingEnd)) {
          $('#alert_message').text('Pick-up time must be between 9:00 AM and 4:00 PM.');
          alert_modal.showModal();
          return;
        }

        if ($('#allow_assignment_conflict').val() !== '1') {
          $.ajax({
            url: '{{ route("validate-assignment") }}',
            method: 'POST',
            dataType: 'json',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            data: {
              room_id: familyKennelMode === 'shared' ? room : null,
              kennel_id: selectedRoomType === 'standard' && familyKennelMode === 'shared' ? kennel : null,
              pet_ids: pet,
              family_pet_assignments: familyPetAssignments,
              boarding_start_datetime: boardingStart,
              boarding_end_datetime: boardingEnd,
              appointment_id: '{{ $appointment->id }}'
            },
            success: function(response) {
              if (response.conflict) {
                var messageText = response.message || 'The selected assignment is already in use during this time period.';
                messageText += '<br><br>Do you want to continue anyway?';
                $('#assignment_message').html(messageText);
                $('#assignment_conflict_info').val(JSON.stringify(response));
                $('#continue_anyway_btn').show();
                assignment_modal.showModal();
                return;
              }

              submitAppointmentDetails(customer, pet, primaryPetId, service, timeSlot, selectedAdditionalServices, additionalServicesByPet, chauffeurSelected, isBoarding, boardingStart, boardingEnd, kennel, room, selectedRoomType);
            },
            error: function() {
              console.error('Failed to validate assignment.');
              $('#alert_message').text('An error occurred while validating the assignment. Please try again.');
              alert_modal.showModal();
            }
          });

          return;
        }
      }

      submitAppointmentDetails(customer, pet, primaryPetId, service, timeSlot, selectedAdditionalServices, additionalServicesByPet, chauffeurSelected, isBoarding, boardingStart, boardingEnd, kennel, room, selectedRoomType);
    }

    function submitAppointmentDetails(customer, pet, primaryPetId, service, timeSlot, selectedAdditionalServices, additionalServicesByPet, chauffeurSelected, isBoarding, boardingStart, boardingEnd, kennel, room, selectedRoomType) {

      $.ajax({
        url: '{{ route("get-validation-info") }}',
        method: 'POST',
        data: {
          pet_id: primaryPetId || null,
          pet_ids: pet,
          service_id: service,
          additional_services: selectedAdditionalServices,
          additional_services_by_pet: additionalServicesByPet,
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
          if (response.vaccine_status === 'expired' || !response.vaccine_status) {
            const vaccineMessages = Array.isArray(response.vaccine_messages) && response.vaccine_messages.length
              ? response.vaccine_messages
              : [response.vaccine_message || (response.vaccine_status === 'expired' ? 'Pet vaccination is expired.' : 'Pet vaccination records is not approved.')];
            vaccineMessages.forEach(function(message) {
              validationMessage += '<li>' + message + '</li>';
            });
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

    function changeAssignmentRoom() {
      $('#allow_assignment_conflict').val('0');
      $('#assignment_conflict_info').val('');
      assignment_modal.close();
    }

    function continueWithAssignmentConflict() {
      $('#allow_assignment_conflict').val('1');
      assignment_modal.close();
      proceedWithFormSubmission();
    }

  </script>
@endsection