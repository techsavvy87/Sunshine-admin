@extends('layouts.main')
@section('title', 'Appointments')

@section('page-css')
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<style>
  .table th,
  .table td {
    padding-block: 0.5rem;
  }
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <div class="inline-flex items-center gap-3">
    <h3 class="text-lg font-medium">Appointments</h3>
    <a class="btn btn-primary btn-sm max-sm:btn-square w-36" href="{{ route('view-appointment-calendar') }}">
      <span class="iconify lucide--calendar-days size-4"></span>
      <span class="hidden sm:inline">View Calendar</span>
    </a>
  </div>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li>Appointments</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-4">
      <div class="flex items-center justify-between mt-3">
        <form id="search_form" class="w-full" method="GET" action="{{ route('appointments') }}">
          <div class="grow grid grid-cols-1 gap-2 xl:grid-cols-4">
            <div class="grid grid-cols-1 gap-2 xl:grid-cols-3 col-span-2">
              <input type="text" class="input input-sm w-full" placeholder="Customer/Pet" name="customer" value="{{ $customerPet }}"/>
              <select class="select select-sm w-full" name="service" value="{{ $serviceId }}">
                <option value="" hidden selected>Choose Service</option>
                @foreach($services as $service)
                <option value="{{ $service->id }}" {{ $serviceId == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                @endforeach
              </select>
              <select class="select select-sm w-full" name="staff" value="{{ $staffId }}">
                <option value="" hidden selected>Choose Staff</option>
                @foreach($staffs as $staff)
                <option value="{{ $staff->id }}" {{ $staffId == $staff->id ? 'selected' : '' }}>{{ $staff->profile ? $staff->profile->first_name . " " . $staff->profile->last_name : '' }}</option>
                @endforeach
              </select>
            </div>
            <input type="text" name="datetimes" class="input input-sm w-full" value="{{ $datetimes }}" autocomplete="off"/>
            <div class="flex justify-start">
              <button class="btn btn-soft btn-primary btn-sm max-sm:btn-square">
                <span class="iconify lucide--search size-4"></span>
                <span class="hidden sm:inline">Search</span>
              </button>
            </div>
          </div>
        </form>
        <div class="inline-flex items-center gap-2">
          @if (hasPermission(3, 'can_create'))
          <a aria-label="Create seller link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-appointment') }}">
            <span class="iconify lucide--plus size-4"></span>
            <span class="hidden sm:inline">New</span>
          </a>
          @endif
        </div>
      </div>
      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>No</th>
              <th>Customer</th>
              <th>Pet</th>
              <th>Room</th>
              <th>Kennel</th>
              <th>Service</th>
              <th>Staff</th>
              <th style="text-align:center">Check-in</th>
              <th style="text-align:center">Pickup</th>
              <th style="text-align:center">Stay</th>
              <th style="text-align:center">Meals</th>
              <th style="text-align:center">Status</th>
              <th style="padding-left: 30px">Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($appointments as $appointment)
            @php
              $checkInDate = \Carbon\Carbon::parse($appointment->date);
              $pickupDate = \Carbon\Carbon::parse($appointment->end_date);
              $stayDays = $checkInDate->copy()->startOfDay()->diffInDays($pickupDate->copy()->startOfDay()) + 1;
              $totalMeals = $stayDays * 2;
              $hasConflict = isAssignmentConflict($appointment);
              $assignmentDetails = $appointment->family_pet_assignment_details;
              $uniqueRoomNames = collect($assignmentDetails)
                ->pluck('room_name')
                ->map(fn($name) => trim((string) $name))
                ->filter(fn($name) => $name !== '')
                ->unique()
                ->values();
              $uniqueKennelNames = collect($assignmentDetails)
                ->pluck('kennel_name')
                ->map(fn($name) => trim((string) $name))
                ->filter(fn($name) => $name !== '')
                ->unique()
                ->values();
            @endphp
            <tr class="hover:bg-base-200/40 cursor-pointer *:text-nowrap {{ $hasConflict ? 'assignment-conflict-row' : '' }}">
              <td>{{ $loop->iteration }}</td>
              <td>{{ $appointment->customer->profile->first_name }} {{ $appointment->customer->profile->last_name }}</td>
              <td>
                @php
                  $displayPets = $appointment->family_pets->isNotEmpty() ? $appointment->family_pets : collect([$appointment->pet])->filter();
                @endphp
                <div class="flex flex-col gap-1">
                  @foreach ($displayPets as $displayPet)
                    <div>
                      <span>{{ $displayPet->name }}</span>
                      @if ($displayPet->rating === 'green')
                        <i class="fa-solid fa-star" style="color: lightseagreen"></i>
                      @elseif ($displayPet->rating === 'yellow')
                        <i class="fa-solid fa-star" style="color: gold"></i>
                      @elseif ($displayPet->rating === 'red')
                        <i class="fa-solid fa-star" style="color: tomato"></i>
                      @endif
                    </div>
                  @endforeach
                </div>
              </td>
              <td>
                <div class="flex items-center gap-2">
                  @if (!empty($assignmentDetails))
                    @if ($uniqueRoomNames->count() === 1)
                      <span>{{ $uniqueRoomNames->first() }}</span>
                    @else
                      <div class="flex flex-col gap-1">
                        @foreach ($assignmentDetails as $assignmentDetail)
                          <span>{{ $assignmentDetail['room_name'] ?: '—' }}</span>
                        @endforeach
                      </div>
                    @endif
                  @else
                    <span>{{ optional($appointment->catRoom)->name ?? $roomByKennel->get((string) $appointment->kennel_id, '—') }}</span>
                  @endif
                  @if ($hasConflict)
                    <span class="assignment-conflict-badge">
                      <span class="iconify lucide--alert-circle size-3"></span>
                      Conflict
                    </span>
                  @endif
                </div>
              </td>
              <td>
                <div class="flex items-center gap-2">
                  @if (!empty($assignmentDetails))
                    @if ($uniqueKennelNames->count() === 1)
                      <span>{{ $uniqueKennelNames->first() }}</span>
                    @elseif ($uniqueKennelNames->isEmpty())
                      <span>—</span>
                    @else
                      <div class="flex flex-col gap-1">
                        @foreach ($uniqueKennelNames as $kennelName)
                          <span>{{ $kennelName }}</span>
                        @endforeach
                      </div>
                    @endif
                  @else
                    <span>{{ optional($appointment->kennel)->name ?? '—' }}</span>
                  @endif
                  @if ($hasConflict)
                    <span class="assignment-conflict-badge">
                      <span class="iconify lucide--alert-circle size-3"></span>
                      {{ getAssignmentConflictLabel($appointment, 'Conflict') }}
                    </span>
                  @endif
                </div>
              </td>
              <td>{{ $appointment->service->name }}</td>
              <td>{{ $appointment->staff_id ? ($appointment->staff->profile ? $appointment->staff->profile->first_name : $appointment->staff->name) : 'Unassigned' }}</td>
              <td style="text-align:center">
                {{ $checkInDate->format('M j') }}
              </td>
              <td style="text-align:center">
                {{ $pickupDate->format('M j') }},{{ $appointment->end_time ? ' ' . \Carbon\Carbon::createFromFormat('H:i:s', $appointment->end_time)->format('g:i A') : '' }}
              </td>
              <td style="text-align:center">
                {{ $stayDays }} {{ $stayDays === 1 ? 'day' : 'days' }}
              </td>
              <td style="text-align:center">
                {{ $totalMeals }} meals
              </td>
              <td style="text-align:center">
                @if($appointment->status === 'checked_in')
                  Scheduled
                @elseif($appointment->status === 'in_progress')
                  {{ (isBoardingService($appointment->service) || isDaycareService($appointment->service)) ? 'On Property' : 'In Progress' }}
                @else
                  {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                @endif
              </td>
              <td>
                <div class="inline-flex w-fit">
                  @if (hasPermission(3, 'can_update'))
                  <a class="btn btn-square btn-ghost btn-sm" href="{{ route('edit-appointment', ['id' => $appointment->id]) }}">
                    <span class="iconify lucide--pencil text-base-content/80 size-4"></span>
                  </a>
                  @endif
                  @if (hasPermission(3, 'can_delete'))
                  <button onclick="confirmDelete({{ $appointment }})" class="btn btn-square btn-error btn-outline btn-sm border-transparent">
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
      {{ $appointments->links('layouts.pagination', ['items' => $appointments]) }}
    </div>
  </div>
</div>
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
      <form id="delete_form" method="POST" action="{{ route('delete-appointment') }}">
        @csrf
        <input type="hidden" name="appointment_id" value="" />
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
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
  $(function() {
    $('input[name="datetimes"]').daterangepicker({
      autoUpdateInput: false,
      timePicker: true,
      startDate: moment().startOf('hour'),
      endDate: moment().startOf('hour').add(32, 'hour'),
      locale: {
        cancelLabel: 'Clear'
      }
    });
    $('input[name="datetimes"]').on('apply.daterangepicker', function(ev, picker) {
      $(this).val(picker.startDate.format('MM/DD/YY hh:mm A') + ' - ' + picker.endDate.format('MM/DD/YY hh:mm A'));
    });
  });

  function confirmDelete(appointment) {
    const message = `You are about to delete the appointment on ${appointment.date} from ${appointment.start_time} to ${appointment.end_time}. Would you like to proceed?`;
    $('#delete_modal_message').text(message);
    $('#delete_form input[name=appointment_id]').val(appointment.id);
    delete_modal.showModal();
  }
</script>
@endsection