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
          <div class="space-y-2" id="kennel_group">
            <label class="fieldset-label" for="kennel">Kennel*</label>
            <select class="select w-full" name="kennel" id="kennel">
              <option value="" hidden selected>Choose a kennel</option>
              @foreach($kennels as $kennel)
                <option value="{{ $kennel->id }}">{{ $kennel->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="space-y-2 hidden" id="room_group">
            <label class="fieldset-label" for="room">Room*</label>
            <select class="select w-full" name="room" id="room">
              <option value="" hidden selected>Choose a room</option>
              @foreach($rooms as $room)
                <option value="{{ $room->id }}">{{ $room->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="fieldset mt-3 grid grid-cols-1 gap-6 xl:grid-cols-3">
          <div id="additional_services_group">
            <div class="space-y-2">
              <label class="fieldset-label" for="additional_services">Additional Services</label>
              <select class="select w-full" name="additional_services[]" id="additional_services" multiple>
                @foreach($additionalServices as $service)
                  <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="space-y-2 hidden" id="time_slot_group">
            <label class="fieldset-label" for="time_slot">Start Time - End Time*</label>
            <select class="select w-full" name="time_slot" id="time_slot">
              <option value="" hidden selected>Choose a time slot</option>
            </select>
            <input type="hidden" name="time_slot_data" id="time_slot_data" />
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
              $('#pet').append('<option value="' + pet.id + '" data-pet-type="' + petType + '">' + pet.name + '</option>');
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
        updateBoardingLocationField();
        refreshAdditionalServiceTimeSlotsIfNeeded();
        refreshAvailableKennels();
      });

      $('#boarding_start_datetime, #boarding_end_datetime').on('change', function() {
        refreshAdditionalServiceTimeSlotsIfNeeded();
        refreshAvailableKennels();
      });

      window.originalAdditionalOptions = $('#additional_services').html();

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
        refreshAdditionalServiceTimeSlotsIfNeeded();
      });

      var selectedServiceId = $('#service').val();
      if (selectedServiceId) {
        checkServiceType(selectedServiceId);
        updateAdditionalServices(selectedServiceId);
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
        },
        success: function(kennels) {
          renderKennelOptions(kennels, currentKennel);
        },
        error: function() {
          renderKennelOptions(window.initialKennels || [], currentKennel);
        }
      });
    }

    function changeService(ele) {
      const serviceId = $(ele).val();

      $('#time_slot').empty();
      $('#time_slot').append('<option value="" hidden selected>Choose a time slot</option>');
      $('#time_slot_data').val('');
      $('#additional_services').empty();

      checkServiceType(serviceId);
      updateAdditionalServices(serviceId);
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
        refreshAdditionalServiceTimeSlotsIfNeeded();
      });

      if (currentValues.length > 0) {
        $('#additional_services').val(currentValues).trigger('change');
      } else {
        syncAdditionalServiceTimeSlotVisibility();
      }
    }

    function getSelectedAdditionalServiceForTimeSlot() {
      const selectedAdditionalServiceIds = $('#additional_services').val() || [];
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
      const petTypes = $('#pet option:selected').map(function() {
        return String($(this).data('pet-type') || '').trim().toLowerCase();
      }).get().filter(function(type) {
        return type !== '';
      });

      return petTypes.length > 0 && petTypes.every(function(type) {
        return type === 'cat';
      });
    }

    function updateBoardingLocationField() {
      const useRoom = shouldUseRoomForSelectedPets();

      if (useRoom) {
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

    function syncAdditionalServiceTimeSlotVisibility() {
      const selectedServiceId = $('#service').val();
      const selectedService = window.servicesData.find(function(s) {
        return String(s.id) === String(selectedServiceId);
      });
      const isBoarding = selectedService && selectedService.category_name && selectedService.category_name.toLowerCase().includes('boarding');

      if (!isBoarding) {
        $('#time_slot_group').removeClass('hidden');
        $('#time_slot_group label').text('Start Time - End Time*');
        return;
      }

      const additionalServiceId = getSelectedAdditionalServiceForTimeSlot();

      if (!additionalServiceId) {
        $('#time_slot_group').addClass('hidden');
        $('#time_slot').empty().append('<option value="" hidden selected>Choose a time slot</option>');
        $('#time_slot').val('').trigger('change');
        $('#time_slot_data').val('');
        return;
      }

      $('#time_slot_group').removeClass('hidden');
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

      const additionalServiceId = getSelectedAdditionalServiceForTimeSlot();
      if (!additionalServiceId) {
        return;
      }

      const petId = getPrimaryPetId();
      const boardingEndDateTime = $('#boarding_end_datetime').val();
      const pickupDate = boardingEndDateTime ? boardingEndDateTime.split('T')[0] : '';
      const pickupTime = boardingEndDateTime ? boardingEndDateTime.split('T')[1] : '';

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

      if (isBoarding && scheduledAdditionalServiceId && !timeSlot) {
        $('#alert_message').text('Please select a valid time slot for the additional service.');
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
      }

      const selectedAdditionalServices = $('#additional_services').val() || [];
      const chauffeurSelected = hasSelectedChauffeurAdditionalService(selectedAdditionalServices);

      setSaveButtonLoading(true);

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
          setSaveButtonLoading(false);

          let validationMessage = '';
          if (!response.owner_status) {
            validationMessage += `<li>Pet owner's profile is inactive.</li>`;
          }
          if (response.vaccine_status === 'expired') {
            validationMessage += '<li>Pet vaccination is expired.</li>';
          } else if (!response.vaccine_status) {
            validationMessage += '<li>Pet vaccination records is not approved.</li>';
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
    }

    function confirmAction() {
      confirm_modal.close();
    }

    // Initialize modals
    const confirm_modal = document.getElementById('confirm_modal');
  </script>
@endsection