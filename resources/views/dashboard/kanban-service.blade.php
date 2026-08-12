@extends('layouts.main')
@section('title', 'Service Dashboard')

@section('page-css')
<style>
  .kanban-shell {
    --kanban-offset: 152px;
    height: calc(100vh - var(--kanban-offset));
  }

  .kanban-shell .card-body {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .kanban-toolbar {
    flex-shrink: 0;
  }

  .kanban-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }

  .kanban-toolbar-main {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
  }

  .kanban-toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-left: auto;
    min-width: 0;
  }

  .kanban-toolbar-scheduled {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.5rem;
    width: 100%;
  }

  @media (min-width: 1280px) {
    .kanban-toolbar-row {
      flex-wrap: nowrap;
      overflow-x: auto;
      padding-bottom: 0.25rem;
    }

    .kanban-toolbar-main,
    .kanban-toolbar-scheduled,
    .kanban-toolbar-actions {
      flex-wrap: nowrap;
      width: auto;
    }
  }

  .kanban-board-wrap {
    margin-top: 0.875rem;
    flex: 1;
    min-height: 0;
    overflow-x: auto;
    overflow-y: hidden;
  }

  .kanban-board {
    height: 100%;
    min-height: 0;
    align-items: stretch;
  }

  .kanban-col {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }

  .kanban-col .card-body {
    display: flex;
    flex-direction: column;
    padding: 0;
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .kanban-col-head {
    padding: 0.55rem 0.75rem;
    position: sticky;
    top: 0;
    z-index: 2;
  }

  .kanban-col-list {
    flex: 1 1 auto;
    min-height: 0;
    max-height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0.5rem;
  }

  .kanban-card {
    border: 1px solid hsl(var(--bc) / 0.1);
    transition: box-shadow 0.15s ease, transform 0.15s ease;
  }

  .kanban-card:hover {
    box-shadow: 0 6px 18px hsl(var(--bc) / 0.12);
    transform: translateY(-1px);
  }

  .kanban-pet-name {
    font-size: 1rem;
    line-height: 1.25rem;
  }

  .kanban-muted {
    color: hsl(var(--bc) / 0.66);
  }

  @media (max-width: 1279px) {
    .kanban-shell {
      height: auto;
    }

    .kanban-board-wrap {
      overflow-x: auto;
      overflow-y: visible;
    }

    .kanban-board {
      height: auto;
    }

    .kanban-col {
      height: 440px;
    }
  }
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <div class="inline-flex items-center gap-3">
    <h3 class="text-lg font-medium">{{ $service->name }} Dashboard</h3>
    @if($service->name === 'Groom')
      <a class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('groomer-calendar', $id) }}">
        <span class="iconify lucide--calendar-days size-4"></span>
        <span class="hidden sm:inline">View Calendar</span>
      </a>
    @endif
  </div>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Service Dashboard</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @if($infoMessage)
    <div class="alert alert-soft alert-warning" role="alert">
      <span class="iconify lucide--info size-4"></span>
      <span>{{ $infoMessage }}</span>
      <button class="btn btn-ghost" style="height: 1px; padding: 0px"><span class="iconify lucide--x size-4"></span></button>
  </div>
  @endif
  <div class="card bg-base-100 shadow mt-3 kanban-shell">
    <div class="card-body p-4">
      <form id="search_form" class="w-full kanban-toolbar" method="GET" action="{{ route('service-dashboard', $id) }}">
        <div class="kanban-toolbar-row">
          <div class="kanban-toolbar-main">
            <input type="text" class="input input-sm w-full sm:w-44 xl:w-52 xl:shrink-0" placeholder="Customer/Pet" name="customer" value="{{ $customerPet }}"/>
            <select class="select select-sm w-full sm:w-44 xl:w-48 xl:shrink-0" name="staff" value="{{ $staffId }}">
              <option value="" hidden selected>Choose Staff</option>
              <option value="0">All Staffs</option>
              @foreach($staffs as $staff)
              <option value="{{ $staff->id }}" {{ $staffId == $staff->id ? 'selected' : '' }}>{{ $staff->profile ? $staff->profile->first_name . " " . $staff->profile->last_name : '' }}</option>
              @endforeach
            </select>
            <button type="submit" class="btn btn-soft btn-primary btn-sm shrink-0 max-sm:btn-square">
              <span class="iconify lucide--search size-4"></span>
              <span class="hidden sm:inline">Search</span>
            </button>
          </div>
          @if($showScheduledFilters)
          <div class="kanban-toolbar-scheduled">
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-xs font-medium text-base-content/70 whitespace-nowrap">Scheduled</span>
              <input type="date" name="scheduled_date" value="{{ $scheduledDate }}" class="input input-bordered input-sm w-full sm:w-40 xl:w-40" onchange="clearScheduledRangeAndSubmit(this)" />
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-xs font-medium text-base-content/70 whitespace-nowrap">Start</span>
              <input type="date" name="scheduled_start_date" value="{{ $scheduledStartDate }}" class="input input-bordered input-sm w-full sm:w-40 xl:w-40" />
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="text-xs font-medium text-base-content/70 whitespace-nowrap">End</span>
              <input type="date" name="scheduled_end_date" value="{{ $scheduledEndDate }}" class="input input-bordered input-sm w-full sm:w-40 xl:w-40" />
            </div>
            <button type="submit" class="btn btn-outline btn-primary btn-sm shrink-0">Apply Range</button>
          </div>
          @endif
          <div class="kanban-toolbar-actions">
            <a href="{{ route('list-incident-reports', ['serviceId' => $id]) }}" class="btn btn-outline btn-warning btn-sm max-sm:btn-square">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-scroll-text-icon lucide-scroll-text"><path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/></svg>
              <span class="hidden sm:inline">Incident Reports</span>
            </a>
            <a class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('list-dashboard', $id) }}">
              <span class="iconify lucide--list size-4"></span>
              <span class="hidden sm:inline">View List</span>
            </a>
          </div>
        </div>
      </form>
      <div class="kanban-board-wrap">
        @php
          $isBoardingOrDaycare = $service->category && (str_contains(strtolower($service->category->name), 'boarding') || str_contains(strtolower($service->category->name), 'daycare'));
          $showWaitListed = isBoardingService($service);
          $boardGridClass = $showWaitListed ? 'xl:grid-cols-5' : 'xl:grid-cols-4';
          $boardSections = [
            [
              'key' => 'checked_in',
              'label' => 'Scheduled',
              'color' => '#e0e7ff',
              'items' => $scheduledAppointments,
              'route' => 'appointment-dashboard',
            ],
          ];

          if ($showWaitListed) {
            array_unshift($boardSections, [
              'key' => 'wait listed',
              'label' => 'Wait Listed',
              'color' => '#fef3c7',
              'items' => $waitListedAppointments ?? collect(),
              'route' => 'edit-appointment',
            ]);
          }

          $boardSections = array_merge($boardSections, [
            [
              'key' => 'in_progress',
              'label' => $isBoardingOrDaycare ? 'On Property' : 'In Progress',
              'color' => '#ede9fe',
              'items' => $appointments->where('status', 'in_progress')->values(),
              'route' => 'appointment-dashboard',
            ],
            [
              'key' => 'completed',
              'label' => 'Completed',
              'color' => '#bbf7d0',
              'items' => $appointments->where('status', 'completed')->values(),
              'route' => 'appointment-dashboard',
            ],
            [
              'key' => 'issue',
              'label' => 'Issue',
              'color' => '#fecaca',
              'items' => $appointments->where('status', 'issue')->values(),
              'route' => 'appointment-dashboard',
            ],
          ]);
        @endphp
        <div class="grid grid-cols-1 {{ $boardGridClass }} gap-4 kanban-board">
          @foreach($boardSections as $section)
            <div class="card shadow kanban-col" style="background-color: {{ $section['color'] ?? '#f3f4f6' }};">
              <div class="card-body">
                <div class="kanban-col-head flex items-center justify-between" style="background-color: {{ $section['color'] ?? '#f3f4f6' }};">
                  <h4 class="font-semibold text-sm xl:text-base" style="color: black">{{ $section['label'] }}</h4>
                  <span class="badge badge-sm badge-outline">{{ $section['items']->count() }}</span>
                  @if ($section['key'] == 'checked_in')
                  <a class="btn btn-square btn-ghost btn-xs" href="{{ route('add-appointment', ['service_id' => $id]) }}" title="Add Appointment">
                    <span class="iconify lucide--plus size-3 font-medium"></span>
                  </a>
                  @endif
                </div>
                <div class="space-y-2 kanban-col-list">
                  @forelse($section['items'] as $appointment)
                    @php
                      $cardPets = $appointment->family_pets;
                      if ($cardPets->isEmpty() && $appointment->pet) {
                        $cardPets = collect([$appointment->pet]);
                      }

                      $checkInDateTime = 'Not set';
                      if (!empty($appointment->date)) {
                        $checkInBase = $appointment->date . ' ' . ($appointment->start_time ?? '00:00:00');
                        try {
                          $checkInDateTime = \Carbon\Carbon::parse($checkInBase)->format('M j, Y h:i A');
                        } catch (\Exception $e) {
                          $checkInDateTime = \Carbon\Carbon::parse($appointment->date)->format('M j, Y');
                        }
                      }

                      $pickupDateTime = 'Not set';
                      if (!empty($appointment->end_date)) {
                        $pickupBase = $appointment->end_date . ' ' . ($appointment->end_time ?? '00:00:00');
                        try {
                          $pickupDateTime = \Carbon\Carbon::parse($pickupBase)->format('M j, Y h:i A');
                        } catch (\Exception $e) {
                          $pickupDateTime = \Carbon\Carbon::parse($appointment->end_date)->format('M j, Y');
                        }
                      } elseif (!empty($appointment->end_time) && !empty($appointment->date)) {
                        try {
                          $pickupDateTime = \Carbon\Carbon::parse($appointment->date . ' ' . $appointment->end_time)->format('M j, Y h:i A');
                        } catch (\Exception $e) {
                          $pickupDateTime = 'Not set';
                        }
                      }

                      $assignmentLabel = trim((string) ($appointment->assignment_label ?? ''));
                    @endphp
                    <div class="card bg-base-100 shadow-sm p-2 relative kanban-card" style="cursor: pointer;" onclick="window.location='{{ route($section['route'] ?? 'appointment-dashboard', $appointment->id) }}'">
                      <div class="flex gap-3 items-start">
                        <div class="flex w-14 shrink-0 flex-col gap-1">
                          @foreach($cardPets->take(3) as $pet)
                            <img src="{{ empty($pet->pet_img) ? asset('images/no_image.jpg') : asset('storage/pets/'. $pet->pet_img) }}" alt="Pet Image" class="mask mask-squircle bg-base-200 block" style="width: 3.5rem; height: 3.5rem; object-fit: cover;">
                          @endforeach
                        </div>
                        <div class="min-w-0 flex-1">
                          <div class="space-y-0.5 flex items-center gap-1 flex-wrap">
                            @foreach($cardPets as $pet)
                              <p class="font-semibold leading-tight wrap-break-word kanban-pet-name">
                                <span>{{ $pet->name }}</span>
                                @if ($pet->rating === 'green')
                                  <i class="fa-solid fa-star" style="color: lightseagreen; font-size: 14px"></i>
                                @elseif ($pet->rating === 'yellow')
                                  <i class="fa-solid fa-star" style="color: gold; font-size: 14px"></i>
                                @elseif ($pet->rating === 'red')
                                  <i class="fa-solid fa-star" style="color: tomato; font-size: 14px"></i>
                                @endif
                              </p>
                            @endforeach
                          </div>
                          <p class="text-xs kanban-muted">
                            <span class="text-base-content/80">Customer: </span>
                            {{ $appointment->customer->profile ? $appointment->customer->profile->first_name . " " . $appointment->customer->profile->last_name : $appointment->customer->name }}
                          </p>
                          <p class="text-xs kanban-muted">
                            <span class="text-base-content/80">Check-in: </span>
                            {{ $checkInDateTime }}
                          </p>
                          <p class="text-xs kanban-muted">
                            <span class="text-base-content/80">Pickup: </span>
                            {{ $pickupDateTime }}
                          </p>
                          <p class="text-xs kanban-muted">
                            <span class="text-base-content/80">Assignment: </span>
                            {{ $assignmentLabel !== '' ? $assignmentLabel : 'Not Assigned' }}
                          </p>
                          @if($section['key'] === 'issue')
                            <div class="absolute top-2 right-2">
                              <span class="iconify lucide--triangle-alert size-4 text-red-600"></span>
                            </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  @empty
                    <p class="text-xs text-center text-base-content/40">No records</p>
                  @endforelse
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-js')
<script>
  function clearScheduledRangeAndSubmit(input) {
    const form = input.form;
    if (!form) {
      return;
    }

    const rangeStart = form.querySelector('input[name="scheduled_start_date"]');
    const rangeEnd = form.querySelector('input[name="scheduled_end_date"]');

    if (rangeStart) {
      rangeStart.value = '';
    }

    if (rangeEnd) {
      rangeEnd.value = '';
    }

    form.submit();
  }
</script>
@endsection