@extends('layouts.main')
@section('title', 'Create Appointment')

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
  </style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Create Appointment</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('appointments') }}">Appointments</a></li>
      <li class="opacity-80">Create</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('create-appointment') }}" method="POST" id="create_form">
    @csrf
    <input type="hidden" name="allow_assignment_conflict" id="allow_assignment_conflict" value="0" />
    <input type="hidden" name="assignment_conflict_info" id="assignment_conflict_info" value="" />
    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="fieldset mt-2 grid grid-cols-1 gap-6 xl:grid-cols-2">
          <div class="space-y-2">
            <label class="fieldset-label" for="customer">Customer*</label>
            <select class="select w-full" name="customer" id="customer">
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
            <select class="select w-full" name="service" id="service" onchange="changeService(this)" @if ($serviceId) disabled @endif>
              <option value="" hidden selected>Choose a service</option>
              @foreach($services as $service)
                <option value="{{ $service->id }}" @if ($service->id == $serviceId) selected @endif>{{ $service->name }}</option>
              @endforeach
            </select>
            @if ($serviceId)
              <input type="hidden" name="service" value="{{ $serviceId }}" />
            @endif
          </div>
          
          <div class="space-y-2" id="boarding_start_group">
            <label class="fieldset-label">Drop Off Date/Time*</label>
            <input type="datetime-local" class="input w-full" id="boarding_start_datetime" name="boarding_start_datetime" format="YYYY-MM-DD HH:mm" placeholder="Select drop off date/time" />
          </div>
          <div class="space-y-2" id="boarding_end_group">
            <label class="fieldset-label">Pick Up Date/Time*</label>
            <input type="datetime-local" class="input w-full" id="boarding_end_datetime" name="boarding_end_datetime" format="YYYY-MM-DD HH:mm" placeholder="Select drop off date/time" />
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
                >{{ $room->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 hidden" id="kennel_group">
            <label class="fieldset-label" for="kennel">Kennel*</label>
            <select class="select w-full" name="kennel" id="kennel">
              <option value="" hidden selected>Choose a kennel</option>
              @foreach($kennels as $kennel)
                <option value="{{ $kennel->id }}">{{ $kennel->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 hidden xl:col-span-4" id="family_kennel_assignments_group">
            <label class="fieldset-label">Kennel Assignments*</label>
            <div id="family_kennel_assignments_container" class="grid grid-cols-1 gap-3 xl:grid-cols-2"></div>
          </div>
          <div id="additional_services_group">
            <div class="space-y-2">
              <label class="fieldset-label" for="additional_services">Additional Services</label>
              <div id="additional_services_single_wrapper">
                <select class="select w-full" name="additional_services[]" id="additional_services" multiple>
                  @foreach($additionalServices as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                  @endforeach
                </select>
              </div>
              <div id="additional_services_by_pet_container" class="hidden space-y-3"></div>
            </div>
          </div>
          <div class="space-y-2 hidden" id="time_slot_group">
            <label class="fieldset-label" for="time_slot">Start Time - End Time*</label>
            <div id="single_time_slot_wrapper">
              <select class="select w-full" name="time_slot" id="time_slot">
                <option value="" hidden selected>Choose a time slot</option>
              </select>
              <input type="hidden" name="time_slot_data" id="time_slot_data" />
            </div>
            <div id="additional_service_time_slots_container" class="hidden space-y-3"></div>
          </div>
          <div class="space-y-2" id="staff_group">
            <label class="fieldset-label" for="staff">Staff</label>
            <select class="select w-full" name="staff" id="staff">
              <option value="" hidden selected>Choose a staff</option>
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
      <button class="btn btn-sm btn-primary" type="button" id="save_appointment_btn" onclick="saveAppointment()">
        <span class="loading loading-spinner size-3.5" style="display:none;"></span>
        <span class="iconify lucide--check size-4 save-icon"></span>
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
      <button class="btn btn-primary btn-sm btn-soft" onclick="confirmAction()">Yes</button>
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
      window.initialFamilyPetAssignments = {};

      $('#customer').select2({
        placeholder: "Choose a customer",
        ajax: {
          url: '{{ route("get-appointment-customers") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term
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

      $('#customer').on('select2:select', function (e) {
        const selectedData = e.params.data;
        const customerId = selectedData.id;

        $.ajax({
          url: '{{ url("/appointment/pets") }}/' + customerId,
          type: 'GET',
          dataType: 'json',
          success: function(pets) {
            $('#pet').empty();

            $.each(pets, function(index, pet) {
              const petType = pet.type || '';
              const petSize = pet.size || '';
              $('#pet').append('<option value="' + pet.id + '" data-pet-type="' + petType + '" data-pet-size="' + petSize + '">' + pet.name + '</option>');
            });

            $('#pet').val([]).trigger('change');
          },
          error: function() {
            console.error('Failed to fetch pets for the selected customer.');
          }
        });
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
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${staff.first_name} ${staff.last_name}</span>
              <span class="text-sm text-base-content/70">(${staff.email} | ${staff.phone_number})</span>
            </div>
          `);
          return $container;
        }
      });

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

      $('#pet').on('change', function() {
        renderAdditionalServicesByPetSelectors();
        updateBoardingLocationField();
        refreshAdditionalServiceTimeSlotsIfNeeded();
        refreshAvailableKennels();
      });

      $('#room').on('change', function() {
        refreshAvailableKennels();
      });

      $('#boarding_start_datetime, #boarding_end_datetime').on('change', function() {
        refreshAdditionalServiceTimeSlotsIfNeeded();
        refreshAvailableKennels();
      });

      window.originalAdditionalOptions = $('#additional_services').html();
      window.additionalServicesByPetState = {};
      window.selectedAdditionalServiceTimeslotsByPair = {};

      // Define servicesData globally so it's accessible to all functions
      window.servicesData = [];
      @foreach($services->merge($secondaryServices) as $s)
        window.servicesData.push({
          id: {{ $s->id }},
          name: '{{ addslashes($s->name) }}',
          category_name: '{{ $s->category ? addslashes($s->category->name) : '' }}',
          price_small: {{ $s->price_small !== null ? $s->price_small : 'null' }},
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
        syncAdditionalServiceTimeSlotVisibility();
      });

      var selectedServiceId = $('#service').val();
      if (selectedServiceId) {
        checkServiceType(selectedServiceId);
        updateAdditionalServices(selectedServiceId);
        renderAdditionalServicesByPetSelectors();
        updateBoardingLocationField();
        syncAdditionalServiceTimeSlotVisibility();
        refreshAdditionalServiceTimeSlotsIfNeeded();
        refreshAvailableKennels();
      }
    });

    function isBoardingSelectedService(serviceId) {
      const service = window.servicesData.find(function(s) {
        return String(s.id) === String(serviceId);
      });

      return !!(service && service.category_name && service.category_name.toLowerCase().includes('boarding'));
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
        renderFamilyPetAssignmentFields(getSelectedFamilyPetAssignments());
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
      renderKennelOptions(roomKennels, currentKennel);
    }

    function changeService(ele) {
      const serviceId = $(ele).val();

      $('#time_slot').empty();
      $('#time_slot').append('<option value="" hidden selected>Choose a time slot</option>');
      $('#time_slot_data').val('');
      $('#additional_services').empty();

      checkServiceType(serviceId);
      updateAdditionalServices(serviceId);
      renderAdditionalServicesByPetSelectors();
      syncAdditionalServiceTimeSlotVisibility();
      refreshAdditionalServiceTimeSlotsIfNeeded();
      refreshAvailableKennels();
    }

    function checkServiceType(serviceId) {
      $('#additional_services_group').addClass('hidden');
      $('#boarding_start_group').addClass('hidden');
      $('#boarding_end_group').addClass('hidden');
      $('#time_slot_group').addClass('hidden');
      $('#staff_group').removeClass('hidden');

      if (!serviceId) {
        updateBoardingLocationField();
        return;
      }

      const service = window.servicesData.find(function(s) { return s.id == serviceId; });

      if (service && service.category_name && service.category_name.toLowerCase().includes('boarding')) {
        $('#additional_services_group').removeClass('hidden');
        $('#boarding_start_group').removeClass('hidden');
        $('#boarding_end_group').removeClass('hidden');
      } else {
        $('#time_slot_group').removeClass('hidden');
      }

      updateBoardingLocationField();
    }

    function updateAdditionalServices(selectedServiceId) {
      var currentValues = $('#additional_services').val() || [];

      try {
        $('#additional_services').select2('destroy');
      } catch (e) {
        console.error('Failed to destroy select2 for additional services.');
      }

      $('#additional_services').html(window.originalAdditionalOptions);

      var service = window.servicesData.find(function(s) { return s.id == selectedServiceId; });
      const isBoarding = service && service.category_name && service.category_name.toLowerCase().includes('boarding');

      if (isBoarding) {
        $('#additional_services option').each(function() {
          var optionVal = $(this).val();
          var additionalService = window.additionalServicesData.find(function(s) {
            return String(s.id) === String(optionVal);
          });
        });
      }

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
        syncAdditionalServiceTimeSlotVisibility();
      });

      if (currentValues.length > 0) {
        $('#additional_services').val(currentValues).trigger('change');
      } else {
        syncAdditionalServiceTimeSlotVisibility();
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
        $('#additional_services_by_pet_container').addClass('hidden').empty();
        $('#additional_services_single_wrapper').removeClass('hidden');
        $('#additional_services').prop('disabled', false);
        if (singlePetId && Array.isArray(window.additionalServicesByPetState[singlePetId])) {
          $('#additional_services').val(window.additionalServicesByPetState[singlePetId]).trigger('change');
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
        const currentSelections = (window.additionalServicesByPetState[pet.id] || []).map(function(serviceId) {
          return String(serviceId);
        });
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
        syncAdditionalServiceTimeSlotVisibility();
      });

      $('#additional_services_single_wrapper').addClass('hidden');
      $('#additional_services').prop('disabled', true).val([]).trigger('change');
      $('#additional_services_by_pet_container').removeClass('hidden');
    }

    function getSelectedAdditionalServiceForTimeSlot() {
      const selectedAdditionalServiceIds = collectSelectedAdditionalServiceIds();
      return selectedAdditionalServiceIds.length > 0 ? selectedAdditionalServiceIds[0] : null;
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
        renderFamilyPetAssignmentFields(getSelectedFamilyPetAssignments());
        return;
      }

      $('#room_group').removeClass('hidden');

      if (shouldUseRoomForSelectedPets()) {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
        $('#kennel').val('').trigger('change');
      } else if ($('#room').val()) {
        refreshAvailableKennels();
      } else {
        $('#kennel_group').addClass('hidden');
        $('#family_kennel_assignments_group').addClass('hidden');
        $('#kennel').prop('disabled', true);
      }
    }

    function syncAdditionalServiceTimeSlotVisibility() {
      const selectedServiceId = $('#service').val();
      const selectedService = window.servicesData.find(function(s) {
        return String(s.id) === String(selectedServiceId);
      });
      const isBoarding = selectedService && selectedService.category_name && selectedService.category_name.toLowerCase().includes('boarding');

      if (!isBoarding) {
        $('#single_time_slot_wrapper').removeClass('hidden');
        $('#additional_service_time_slots_container').addClass('hidden').empty();
        $('#time_slot_group').removeClass('hidden');
        $('#time_slot_group label').text('Start Time - End Time*');
        return;
      }

      const selectedAdditionalServiceIds = collectSelectedAdditionalServiceIds();

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
        const petId = getPrimaryPetId();
        const boardingEndDateTime = $('#boarding_end_datetime').val();
        const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
        const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';

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
      renderAdditionalServiceTimeSlotSelectors();
    }

    function refreshAdditionalServiceTimeSlotsIfNeeded() {
      const selectedServiceId = $('#service').val();
      const selectedService = window.servicesData.find(function(s) {
        return String(s.id) === String(selectedServiceId);
      });
      const isBoarding = selectedService && selectedService.category_name && selectedService.category_name.toLowerCase().includes('boarding');

      if (!isBoarding) {
        return;
      }

      if (getSelectedPetIds().length <= 1) {
        const additionalServiceId = getSelectedAdditionalServiceForTimeSlot();
        const petId = getPrimaryPetId();
        const boardingEndDateTime = $('#boarding_end_datetime').val();
        const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
        const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';

        if (!additionalServiceId || !petId || !pickupDate || !pickupTime) {
          return;
        }

        populateTimeSlots(additionalServiceId, pickupDate, petId, pickupTime, true);
        return;
      }

      renderAdditionalServiceTimeSlotSelectors();
    }

    function getAdditionalServiceTimeSlotSelections() {
      return Object.assign({}, window.selectedAdditionalServiceTimeslotsByPair || {});
    }

    function getSelectedAdditionalServicePairsByPet() {
      const selectedPets = getSelectedPetDetails();
      const perPetPayload = getPerPetAdditionalServicesPayload();
      const pairs = [];

      selectedPets.forEach(function(pet) {
        const petId = String(pet.id);
        const serviceIds = (perPetPayload[petId] || []).map(function(serviceId) {
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

    function hasMissingAdditionalServiceTimeSlots() {
      const selectedAdditionalServiceIds = collectSelectedAdditionalServiceIds();
      if (selectedAdditionalServiceIds.length === 0) {
        return false;
      }

      if (getSelectedPetIds().length <= 1) {
        return !$('#time_slot').val();
      }

      const selectedPairs = getSelectedAdditionalServicePairsByPet();
      if (selectedPairs.length === 0) {
        return false;
      }

      const slotSelections = getAdditionalServiceTimeSlotSelections();
      return selectedPairs.some(function(pair) {
        return !slotSelections[pair.pairKey];
      });
    }

    function renderAdditionalServiceTimeSlotSelectors() {
      const selectedPairs = getSelectedAdditionalServicePairsByPet();
      const boardingEndDateTime = $('#boarding_end_datetime').val();
      const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
      const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';
      const previousSelections = getAdditionalServiceTimeSlotSelections();

      const $container = $('#additional_service_time_slots_container');
      $container.empty();

      pruneAdditionalServiceTimeSlotState();

      if (selectedPairs.length === 0) {
        $container.addClass('hidden');
        return;
      }

      selectedPairs.forEach(function(pair) {
        const service = window.additionalServicesData.find(function(item) {
          return String(item.id) === String(pair.serviceId);
        });

        const serviceName = service ? service.name : 'Additional Service';
        const selectId = 'additional_service_time_slot_' + pair.petId + '_' + pair.serviceId;

        $container.append(`
          <div class="space-y-2 rounded-box border border-base-300 p-3">
            <label class="fieldset-label" for="${selectId}">${pair.petName} - ${serviceName}*</label>
            <select class="select w-full additional-service-time-slot-select" id="${selectId}" name="additional_service_time_slots_by_pet[${pair.petId}][${pair.serviceId}]" data-service-id="${pair.serviceId}" data-pair-key="${pair.pairKey}" data-pet-id="${pair.petId}">
              <option value="" hidden selected>Choose a time slot</option>
            </select>
          </div>
        `);
      });

      $container.removeClass('hidden');

      if (!pickupDate || !pickupTime) {
        $('.additional-service-time-slot-select').each(function() {
          $(this).empty().append('<option value="" hidden selected>Select pet and pick up time first</option>');
        });
        return;
      }

      $('.additional-service-time-slot-select').each(function() {
        const $select = $(this);
        const serviceId = String($select.data('service-id'));
        const petId = String($select.data('pet-id'));
        const pairKey = String($select.data('pair-key'));
        populateAdditionalServiceTimeSlotOptions($select, serviceId, pickupDate, petId, pickupTime, previousSelections[pairKey] || '');
      });

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

          timeSlots.forEach(function(slot) {
            const slotValue = slot.is_virtual ? slot.start_time : (slot.id || slot.start_time);
            const isSelected = selectedSlotId && String(slotValue) === String(selectedSlotId);
            const disabled = slot.status !== 'available' ? 'disabled' : '';
            const displayText = formatTimeToAMPM(slot.start_time) + ' - ' + formatTimeToAMPM(slot.end_time);
            $select.append('<option value="' + slotValue + '" ' + disabled + (isSelected ? ' selected' : '') + '>' + displayText + '</option>');
          });

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

          if (timeSlots.length === 0) {
            $('#time_slot').append('<option value="" disabled>No available time slots</option>');
          } else {
            $.each(timeSlots, function(index, slot) {
              let displayText = '';
              if (slot.is_virtual && slot.optimized_service_order) {
                // For ala carte, show the optimized service order
                const services = slot.optimized_service_order.map(function(s) {
                  return s.service_name + ' (' + formatTimeToAMPM(s.start_time) + ' - ' + formatTimeToAMPM(s.end_time) + ')';
                }).join(', ');
                displayText = formatTimeToAMPM(slot.start_time) + ' - ' + formatTimeToAMPM(slot.end_time);
              } else {
                const start = formatTimeToAMPM(slot.start_time);
                const end = formatTimeToAMPM(slot.end_time);
                displayText = start + ' - ' + end;
              }
              const disabled = slot.status !== 'available' ? 'disabled' : '';
              const slotValue = slot.is_virtual ? slot.start_time : (slot.id || slot.start_time);
              $('#time_slot').append('<option value="' + slotValue + '" ' + disabled + ' data-slot-data="' + encodeURIComponent(JSON.stringify(slot)) + '">' + displayText + '</option>');
            });
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

    function setSaveButtonLoading(isLoading) {
      const $saveButton = $('#save_appointment_btn');
      if ($saveButton.length === 0) {
        return;
      }

      if (isLoading) {
        $saveButton.find('.loading').css('display', 'inline-block');
        $saveButton.find('.save-icon').css('display', 'none');
        $saveButton.prop('disabled', true);
        $saveButton.contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Save';
        }).remove();
        if ($saveButton.contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Loading';
        }).length === 0) {
          $saveButton.append('Loading');
        }
      } else {
        $saveButton.find('.loading').css('display', 'none');
        $saveButton.find('.save-icon').css('display', 'inline-block');
        $saveButton.prop('disabled', false);
        $saveButton.contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Loading';
        }).remove();
        if ($saveButton.contents().filter(function() {
          return this.nodeType === 3 && this.nodeValue.trim() === 'Save';
        }).length === 0) {
          $saveButton.append('Save');
        }
      }
    }

    function saveAppointment() {
      if ($('#save_appointment_btn').prop('disabled')) {
        return;
      }

      const customer = $('#customer').val();
      const pet = getSelectedPetIds();
      const primaryPetId = getPrimaryPetId();
      const service = $('#service').val();
      const date = $('#button_cally_target').text();
      const timeSlot = $('#time_slot').val();

      const isBoarding = $('#boarding_start_group').is(':visible');
      const boardingStart = $('#boarding_start_datetime').val();
      const boardingEnd = $('#boarding_end_datetime').val();
      const scheduledAdditionalServiceId = getSelectedAdditionalServiceForTimeSlot();
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

      if (isBoarding && scheduledAdditionalServiceId && hasMissingAdditionalServiceTimeSlots()) {
        $('#alert_message').text('Please select a valid time slot for each additional service.');
        alert_modal.showModal();
        return;
      }

      if (isBoarding) {
        $('#date').val('');
        if (!scheduledAdditionalServiceId) {
          $('#time_slot_group').addClass('hidden');
          $('#time_slot').val('').trigger('change');
          $('#time_slot_data').val('');
        }
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
      }

      const selectedAdditionalServices = collectSelectedAdditionalServiceIds();
      const additionalServicesByPet = getPerPetAdditionalServicesPayload();
      const chauffeurSelected = hasSelectedChauffeurAdditionalService(selectedAdditionalServices);

      const submitAppointment = function() {
        setSaveButtonLoading(true);

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
            setSaveButtonLoading(false);

            let validationMessage = '';
            if (!response.owner_status) {
              validationMessage += `<li>Pet owner's profile is inactive.</li>`;
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
            if (!response.available_status) {
              validationMessage += '<li>Online booking is currently limited due to high demand.</li>';
            }
            if (validationMessage) {
              validationMessage = `Please address the following issues before creating the appointment:<br>
                <ul style="list-style: disc; font-size: 14px; padding-left: 24px; padding-top: 6px;">${validationMessage}</ul>`;
              $('#confirm_message').html(validationMessage);
              confirm_modal.showModal();
            } else {
              $('#create_form').attr('action', '{{ route("create-appointment") }}');
              $('#create_form').submit();
            }
          },
          error: function() {
            setSaveButtonLoading(false);
            console.error('Failed to validate appointment details.');
            $('#alert_message').text('An error occurred while validating the appointment. Please try again.');
            alert_modal.showModal();
          }
        });
      };

      if (!isBoarding) {
        submitAppointment();
        return;
      }

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
        },
        success: function(response) {
          if (response.conflict && $('#allow_assignment_conflict').val() !== '1') {
            var messageText = response.message || 'The selected assignment is already in use during this time period.';
            messageText += '<br><br>Do you want to continue anyway?';
            $('#assignment_message').html(messageText);
            $('#assignment_conflict_info').val(JSON.stringify(response));
            $('#continue_anyway_btn').show();
            assignment_modal.showModal();
            return;
          }

          submitAppointment();
        },
        error: function() {
          setSaveButtonLoading(false);
          $('#alert_message').text('An error occurred while validating the assignment. Please try again.');
          alert_modal.showModal();
        }
      });
    }

    function changeAssignmentRoom() {
      $('#allow_assignment_conflict').val('0');
      $('#assignment_conflict_info').val('');
      assignment_modal.close();
    }

    function continueWithAssignmentConflict() {
      $('#allow_assignment_conflict').val('1');
      assignment_modal.close();
      saveAppointment();
    }

    function confirmAction() {
      confirm_modal.close();
    }

    // Initialize modals
    const confirm_modal = document.getElementById('confirm_modal');
    const assignment_modal = document.getElementById('assignment_modal');
  </script>
@endsection