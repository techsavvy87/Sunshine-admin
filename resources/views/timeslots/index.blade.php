@extends('layouts.main')
@section('title', 'Time Slots')

@section('page-css')
<style>
  .table th,
  .table td {
    padding-block: 0.5rem;
  }
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Time Slots</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li><a href="{{ route('services') }}">Service</a></li>
      <li>Time Slots</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-0">
      <div class="flex items-center justify-between px-5 pt-5">
        <div class="inline-flex items-center gap-3">
          <select class="select select-sm w-full" onchange="onChangeService(this)" value="{{ $serviceId }}">
            @foreach($services as $service)
            <option value="{{ $service->id }}" {{ $service->id == $serviceId ? 'selected' : '' }}>{{ $service->name }}</option>
            @endforeach
          </select>
          <input class="input w-full input-sm" type="date" value="{{ $date }}" onchange="onChangeDate(this)" onkeydown="if(event.key === 'Enter'){ onChangeDate(this); }" />
        </div>
        <div class="inline-flex items-center gap-2">
          @if (hasPermission(13, 'can_create'))
          <a aria-label="Create seller link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-timeslot') }}">
            <span class="iconify lucide--plus size-4"></span>
            <span class="hidden sm:inline">New</span>
          </a>
          <button type="button" class="btn btn-outline btn-primary btn-sm max-sm:btn-square" onclick="openGenerateModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-filter-plus-icon lucide-list-filter-plus gb-blur-svg"><path d="M12 5H2"/><path d="M6 12h12"/><path d="M9 19h6"/><path d="M16 5h6"/><path d="M19 8V2"/></svg>
            <span class="hidden sm:inline">Generate</span>
          </button>
          @endif
        </div>
      </div>
      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>No</th>
              <th>Start Time</th>
              <th>End Time</th>
              @php
                $firstSlot = $timeSlots->first();
                $isGroomingService = $firstSlot && $firstSlot->pet_size !== null;
                $isDaycareService = $firstSlot && $firstSlot->service && $firstSlot->service->category && str_contains(strtolower($firstSlot->service->category->name), 'daycare');
                $isTrainingService = $firstSlot && $firstSlot->service && $firstSlot->service->category && str_contains(strtolower($firstSlot->service->category->name), 'training');
              @endphp
              @if($isGroomingService)
              <th>Pet Size</th>
              @endif
              @if($isDaycareService)
              <th>Daycare Type</th>
              @endif
              @if($isTrainingService)
              <th>Training Type</th>
              @endif
              <th>Staff</th>
              <th style="text-align:center">Capacity</th>
              <th style="text-align:center">Booked Count</th>
              <th style="text-align:center">Status</th>
              <th style="text-align:center">Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($timeSlots as $slot)
            <tr class="hover:bg-base-200/40 cursor-pointer *:text-nowrap">
              <td>{{ $loop->iteration }}</td>
              <td>{{ $slot->start_time }}</td>
              <td>{{ $slot->end_time }}</td>
              @if($isGroomingService)
              <td>
                @if($slot->pet_size)
                  <span class="badge badge-sm
                    {{ $slot->pet_size === 'small' ? 'badge-info' : '' }}
                    {{ $slot->pet_size === 'medium' ? 'badge-warning' : '' }}
                    {{ $slot->pet_size === 'large' ? 'badge-error' : '' }}
                    {{ $slot->pet_size === 'xlarge' ? 'badge-secondary' : '' }}
                  ">
                    {{ $slot->pet_size === 'xlarge' ? 'X-Large' : ucfirst($slot->pet_size) }}
                  </span>
                @else
                  -
                @endif
              </td>
              @endif
              @if($isDaycareService)
              <td>
                @if($slot->daycare_type)
                  <span class="badge badge-sm
                    {{ $slot->daycare_type === 'half' ? 'badge-warning' : '' }}
                    {{ $slot->daycare_type === 'full' ? 'badge-success' : '' }}
                  ">
                    {{ $slot->daycare_type === 'half' ? 'Half Day' : 'Full Day' }}
                  </span>
                @else
                  -
                @endif
              </td>
              @endif
              @if($isTrainingService)
              <td>
                @if($slot->private_training_type)
                  <span class="badge badge-sm
                    {{ $slot->private_training_type === 'half' ? 'badge-warning' : '' }}
                    {{ $slot->private_training_type === 'one' ? 'badge-success' : '' }}
                  ">
                    {{ $slot->private_training_type === 'half' ? 'Half Hour' : 'One Hour' }}
                  </span>
                @else
                  -
                @endif
              </td>
              @endif
              <td>{{ $slot->staff ? $slot->staff->name : 'Unassigned' }}</td>
              <td style="text-align:center">{{ $slot->capacity }}</td>
              <td style="text-align:center">{{ $slot->booked_count }}</td>
              <td style="text-align:center">{{ ucfirst($slot->status) }}</td>
              <td style="text-align:center">
                <div class="inline-flex w-fit">
                  @if (hasPermission(13, 'can_update'))
                  <a class="btn btn-square btn-ghost btn-sm" href="{{ route('edit-timeslot', ['id' => $slot->id]) }}">
                    <span class="iconify lucide--pencil text-base-content/80 size-4"></span>
                  </a>
                  @endif
                  @if (hasPermission(13, 'can_delete'))
                  <button onclick="confirmDelete({{ $slot }})" class="btn btn-square btn-error btn-outline btn-sm border-transparent">
                    <span class="iconify lucide--trash size-4"></span>
                  </button>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
          </thead>
        </table>
      </div>
      {{ $timeSlots->appends(request()->query())->links('layouts.pagination', ['items' => $timeSlots]) }}
    </div>
  </div>
</div>
<dialog id="generate_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Generate Time Slots
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <div class="mt-4 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-1">
          <label class="fieldset-label" for="start_date">Start Date*</label>
          <input class="input w-full" id="start_date" type="date" required onchange="validateDateRange()" min="{{ date('Y-m-d') }}" />
        </div>
        <div class="space-y-1">
          <label class="fieldset-label" for="end_date">End Date*</label>
          <input class="input w-full" id="end_date" type="date" required onchange="validateDateRange()" />
        </div>
      </div>
      <div id="holidays_list" class="hidden">
        <label class="fieldset-label mb-2">Holidays in Selected Period</label>
        <div class="max-h-64 overflow-y-auto border border-base-300 rounded-box p-3">
          <div id="holidays_content" class="space-y-2">
            <!-- Holidays will be displayed here -->
          </div>
        </div>
      </div>
    </div>
    <div class="modal-action">
      <form method="dialog">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeGenerateModal()">Cancel</button>
      </form>
      <button type="button" class="btn btn-primary btn-sm" onclick="confirmGenerate()" id="confirm_generate_btn">
        <span class="loading loading-spinner loading-sm" style="display: none;"></span>
        <span class="btn-text">Generate</span>
      </button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
<dialog id="delete_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Confirm Delete
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <p class="py-4" id="delete_modal_message"></p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost btn-sm">No</button>
      </form>
      <form id="delete_form" method="POST" action="{{ route('delete-timeslot') }}">
        @csrf
        <input type="hidden" name="timeslot_id" value="" />
        <button class="btn btn-error">Delete</button>
      </form>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
@endsection

@section('page-js')
<script>
  const generate_modal = document.getElementById('generate_modal');
  const delete_modal = document.getElementById('delete_modal');

  function onChangeService(ele) {
    const serviceId = $(ele).val();
    handleSearch(serviceId, "{{ $date }}");
  }

  function onChangeDate(ele) {
    const date = $(ele).val();
    console.log("date:::", date);

    // validate the date
    const regex = /^\d{4}-\d{2}-\d{2}$/;
    if (!regex.test(date)) {
      console.log("Invalid date format.");
      return;
    }

    // Check if it's a real date
    const parts = date.split('-');
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1; // JS months are 0-based
    const day = parseInt(parts[2], 10);
    const d = new Date(year, month, day);

    if (
      d.getFullYear() !== year ||
      d.getMonth() !== month ||
      d.getDate() !== day ||
      year < 1000
    ) {
      console.log("Invalid date value.");
      return;
    }

    handleSearch("{{ $serviceId }}", date);
  }

  function handleSearch(serviceId, date) {
    const url = `/timeslots?serviceId=${serviceId}&date=${encodeURIComponent(date)}`;
    window.location.href = url;
  }

  function openGenerateModal() {
    loadExistingTimeSlotDates(function() {
      const today = new Date();
      let startDate = new Date(today);
      
      const todayStr = today.toISOString().split('T')[0];
      if (existingTimeSlotDates.includes(todayStr)) {
        startDate.setDate(startDate.getDate() + 1);
        while (existingTimeSlotDates.includes(startDate.toISOString().split('T')[0])) {
          startDate.setDate(startDate.getDate() + 1);
        }
      }
      
      const endDate = new Date(startDate);
      endDate.setDate(endDate.getDate() + 364);
      
      const startDateStr = startDate.toISOString().split('T')[0];
      const endDateStr = endDate.toISOString().split('T')[0];
      
      $('#start_date').attr('min', todayStr);
      
      updateEndDateConstraints(startDateStr);
      
      $('#start_date').val(startDateStr);
      $('#end_date').val(endDateStr);
      $('#holidays_list').addClass('hidden');
      $('#holidays_content').empty();
      generate_modal.showModal();
      
      setTimeout(() => {
        loadHolidaysInRange(startDateStr, endDateStr);
      }, 100);
    });
  }

  function closeGenerateModal() {
    generate_modal.close();
    $('#start_date').val('');
    $('#end_date').val('');
    $('#holidays_list').addClass('hidden');
    $('#holidays_content').empty();
  }

  let existingTimeSlotDates = [];
  let latestTimeSlotEndDate = null;

  function loadExistingTimeSlotDates(callback) {
    $.ajax({
      url: '{{ route("get-existing-timeslot-dates") }}',
      method: 'GET',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      success: function(response) {
        if (response.existing_dates) {
          existingTimeSlotDates = response.existing_dates;
        }
        latestTimeSlotEndDate = response.latest_timeslot_end_date || null;
        if (callback) callback();
      },
      error: function() {
        console.error('Error loading existing time slot dates');
        if (callback) callback();
      }
    });
  }

  $(document).ready(function() {
    loadExistingTimeSlotDates();
  });

  function formatDateLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function getNextTwelveMonthsRangeFromLatestEndDate() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let startDate = new Date(today);

    if (latestTimeSlotEndDate) {
      const [year, month, day] = latestTimeSlotEndDate.split('-').map(Number);
      const latest = new Date(year, month - 1, day);
      latest.setHours(0, 0, 0, 0);
      startDate = new Date(latest);
      startDate.setDate(startDate.getDate() + 1);
    } else {
      const todayStr = formatDateLocal(today);
      if (existingTimeSlotDates.includes(todayStr)) {
        startDate.setDate(startDate.getDate() + 1);
        while (existingTimeSlotDates.includes(formatDateLocal(startDate))) {
          startDate.setDate(startDate.getDate() + 1);
        }
      }
    }

    if (startDate < today) {
      startDate = new Date(today);
    }

    const endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 364);

    return {
      startDateStr: formatDateLocal(startDate),
      endDateStr: formatDateLocal(endDate),
      todayStr: formatDateLocal(today),
    };
  }

  function showAlert(message) {
    const alert_modal = document.getElementById('alert_modal');
    const alert_message = document.getElementById('alert_message');
    if (alert_modal && alert_message) {
      alert_message.textContent = message;
      alert_modal.showModal();
    } else {
      console.error(message);
    }
  }

  function validateDateRange() {
    const startDate = $('#start_date').val();
    const endDate = $('#end_date').val();
    
    if (startDate && endDate) {
      if (existingTimeSlotDates.includes(startDate)) {
        $.ajax({
          url: '{{ route("get-holidays-in-range") }}',
          method: 'GET',
          async: false,
          data: {
            start_date: startDate,
            end_date: startDate
          },
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.holidays && response.holidays.length > 0) {
              const holiday = response.holidays[0];
              if (holiday.restrict_bookings === 'yes') {
                showAlert('This date has a holiday with restricted bookings. Time slots cannot be generated for this date. Please select a different start date.');
              } else {
                showAlert('Time slots already exist in the selected date range. Please choose a different date range.');
              }
            } else {
              showAlert('Time slots already exist in the selected date range. Please choose a different date range.');
            }
          }
        });
        const today = new Date().toISOString().split('T')[0];
        if (!existingTimeSlotDates.includes(today)) {
          $('#start_date').val(today);
          updateEndDateConstraints(today);
        } else {
          let nextDate = new Date(today);
          nextDate.setDate(nextDate.getDate() + 1);
          while (existingTimeSlotDates.includes(nextDate.toISOString().split('T')[0])) {
            nextDate.setDate(nextDate.getDate() + 1);
          }
          $('#start_date').val(nextDate.toISOString().split('T')[0]);
          updateEndDateConstraints(nextDate.toISOString().split('T')[0]);
        }
        return false;
      }

      const start = new Date(startDate);
      const end = new Date(endDate);
      
      if (start > end) {
        showAlert('End date cannot be earlier than start date.');
        const sameDay = new Date(start);
        $('#end_date').val(sameDay.toISOString().split('T')[0]);
        updateEndDateConstraints(startDate);
        return false;
      }

      // Calculate days including both start and end dates (add 1 for inclusive range)
      const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
      if (daysDiff > 365) {
        showAlert('The date range cannot exceed 12 months (365 days).');
        const maxEndDate = new Date(start);
        maxEndDate.setDate(maxEndDate.getDate() + 364); // 365 days total (start + 364 more)
        $('#end_date').val(maxEndDate.toISOString().split('T')[0]);
        updateEndDateConstraints(startDate);
        return false;
      }
      
      updateEndDateConstraints(startDate);
      loadHolidaysInRange(startDate, $('#end_date').val());
      return true;
    }
    return false;
  }

  function updateEndDateConstraints(startDate) {
    if (!startDate) return;
    
    const start = new Date(startDate);
    
    // Allow same day (start date = end date for single day generation)
    const minEndDate = new Date(start);
    $('#end_date').attr('min', minEndDate.toISOString().split('T')[0]);
    
    const maxEndDate = new Date(start);
    maxEndDate.setDate(maxEndDate.getDate() + 364); // 365 days total (start + 364 more)
    $('#end_date').attr('max', maxEndDate.toISOString().split('T')[0]);
    
    const currentEndDate = $('#end_date').val();
    if (currentEndDate && new Date(currentEndDate) > maxEndDate) {
      $('#end_date').val(maxEndDate.toISOString().split('T')[0]);
    }
  }

  $('#start_date').on('change', function() {
    const startDate = $(this).val();
    if (startDate) {
      if (existingTimeSlotDates.includes(startDate)) {
        $.ajax({
          url: '{{ route("get-holidays-in-range") }}',
          method: 'GET',
          async: false,
          data: {
            start_date: startDate,
            end_date: startDate
          },
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.holidays && response.holidays.length > 0) {
              const holiday = response.holidays[0];
              if (holiday.restrict_bookings === 'yes') {
                showAlert('This date has a holiday with restricted bookings. Time slots cannot be generated for this date. Please select a different start date.');
              } else {
                showAlert('Time slots already exist in the selected date range. Please choose a different date range.');
              }
            } else {
              showAlert('Time slots already exist in the selected date range. Please choose a different date range.');
            }
          }
        });
        const today = new Date().toISOString().split('T')[0];
        if (!existingTimeSlotDates.includes(today)) {
          $(this).val(today);
          updateEndDateConstraints(today);
        } else {
          let nextDate = new Date(today);
          nextDate.setDate(nextDate.getDate() + 1);
          while (existingTimeSlotDates.includes(nextDate.toISOString().split('T')[0])) {
            nextDate.setDate(nextDate.getDate() + 1);
          }
          $(this).val(nextDate.toISOString().split('T')[0]);
          updateEndDateConstraints(nextDate.toISOString().split('T')[0]);
        }
        return;
      }

      updateEndDateConstraints(startDate);
      
      const endDate = $('#end_date').val();
      if (endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        if (start > end) {
          // If end date is before start date, set it to start date (allow single day)
          $('#end_date').val(startDate);
        }
        
        // Calculate days including both start and end dates (add 1 for inclusive range)
        const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        if (daysDiff > 365) {
          const maxEndDate = new Date(start);
          maxEndDate.setDate(maxEndDate.getDate() + 364); // 365 days total (start + 364 more)
          $('#end_date').val(maxEndDate.toISOString().split('T')[0]);
        }
      }
    }
    validateDateRange();
  });

  $('#end_date').on('change', function() {
    const startDate = $('#start_date').val();
    const endDate = $(this).val();
    
    if (startDate && endDate) {
      const start = new Date(startDate);
      const end = new Date(endDate);
      
      // Calculate days including both start and end dates (add 1 for inclusive range)
      const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
      if (daysDiff > 365) {
        showAlert('The date range cannot exceed 12 months (365 days).');
        const maxEndDate = new Date(start);
        maxEndDate.setDate(maxEndDate.getDate() + 364); // 365 days total (start + 364 more)
        $(this).val(maxEndDate.toISOString().split('T')[0]);
      }
    }
    
    validateDateRange();
  });

  function loadHolidaysInRange(startDate, endDate) {
    $.ajax({
      url: '{{ route("get-holidays-in-range") }}',
      method: 'GET',
      data: {
        start_date: startDate,
        end_date: endDate
      },
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      success: function(response) {
        if (response.holidays && response.holidays.length > 0) {
          let html = '';
          let hasRestrictedBookings = false;
          response.holidays.forEach(holiday => {
            const isRestricted = holiday.restrict_bookings === 'yes';
            if (isRestricted) {
              hasRestrictedBookings = true;
            }
            const holidayDate = holiday.date ? new Date(holiday.date).toISOString().split('T')[0] : holiday.date;
            const restrictedBadge = isRestricted 
              ? '<span class="badge badge-error badge-sm ml-2">Restricted Bookings</span>' 
              : '';
            html += `
              <div class="p-2 ${isRestricted ? 'bg-error/10 border border-error/30' : 'bg-base-200'} rounded-box mb-2">
                <div>
                  <div class="flex items-center">
                    <p class="font-medium text-sm">${holiday.name}</p>
                    ${restrictedBadge}
                  </div>
                  <p class="text-xs text-base-content/70">${holidayDate} - ${holiday.percent_increase}% increase</p>
                  ${isRestricted ? '<p class="text-xs text-error mt-1 font-medium">⚠️ Time slots will not be generated for this date</p>' : ''}
                </div>
              </div>
            `;
          });
          if (hasRestrictedBookings) {
            html = '<div class="alert alert-warning mb-3"><p class="text-sm">⚠️ Some holidays have restricted bookings. Time slots will not be generated for those dates.</p></div>' + html;
          }
          $('#holidays_content').html(html);
          $('#holidays_list').removeClass('hidden');
        } else {
          $('#holidays_content').html('<p class="text-sm text-base-content/70">No holidays in selected period.</p>');
          $('#holidays_list').removeClass('hidden');
        }
      },
      error: function() {
        console.error('Error loading holidays');
      }
    });
  }

  function confirmGenerate() {
    const startDate = $('#start_date').val();
    const endDate = $('#end_date').val();

    if (!startDate || !endDate) {
      showAlert('Please select both start and end dates.');
      return;
    }

    if (new Date(startDate) > new Date(endDate)) {
      showAlert('End date cannot be earlier than start date.');
      return;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    if (daysDiff > 365) {
      showAlert('The date range cannot exceed 12 months (365 days).');
      return;
    }

    if (!validateDateRange()) {
      return;
    }

    const $btn = $('#confirm_generate_btn');
    const $loading = $btn.find('.loading');
    const $btnText = $btn.find('.btn-text');
    const originalText = $btnText.text();

    $loading.css('display', 'inline-block');
    $btnText.text('Generating...');
    $btn.prop('disabled', true);

    $.ajax({
      url: '{{ route("generate-timeslot") }}',
      method: 'POST',
      data: {
        start_date: startDate,
        end_date: endDate
      },
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      success: function(response) {
        closeGenerateModal();
        if (response.status === 'success') {
          window.location.href = '{{ route("timeslots") }}';
        } else {
          showAlert(response.message || 'Time slots generated with some issues.');
          window.location.href = '{{ route("timeslots") }}';
        }
      },
      error: function(xhr) {
        let msg = 'An error occurred while generating time slots.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        showAlert(msg);
        $loading.css('display', 'none');
        $btnText.text(originalText);
        $btn.prop('disabled', false);
      }
    });
  }

  function confirmDelete(timeslot) {
    const message = `You are about to delete the timeslot ${timeslot.start_time} - ${timeslot.end_time}. Would you like to proceed?`;
    $('#delete_modal_message').text(message);
    $('#delete_form input[name=timeslot_id]').val(timeslot.id);
    delete_modal.showModal();
  }
</script>
@endsection
