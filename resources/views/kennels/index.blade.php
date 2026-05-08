@extends('layouts.main')
@section('title', 'Kennels')

@section('page-css')
@endsection

@section('content')
@php
  $activeView = request('view', 'list');
@endphp
<div class="flex items-center justify-between">
  <div class="inline-flex items-center gap-3">
    <h3 class="text-lg font-medium">Kennels</h3>
    <a
      class="btn btn-sm max-sm:btn-square w-28 {{ $activeView === 'list' ? 'btn-primary' : 'btn-soft btn-primary' }}"
      href="{{ route('kennels', array_merge(request()->query(), ['view' => 'list'])) }}"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-grid2x2-icon lucide-grid-2x2"><path d="M12 3v18"/><path d="M3 12h18"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
      <span class="hidden sm:inline">View List</span>
    </a>
    <a
      class="btn btn-sm max-sm:btn-square w-36 {{ $activeView === 'calendar' ? 'btn-primary' : 'btn-soft btn-primary' }}"
      href="{{ route('kennels', array_merge(request()->query(), ['view' => 'calendar'])) }}"
    >
      <span class="iconify lucide--calendar-days size-4"></span>
      <span class="hidden sm:inline">View Calendar</span>
    </a>
  </div>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li>Kennels</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')

  @if ($activeView === 'calendar')
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-0">
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#34d399;"></span>Single Occupied</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#ff00ff;"></span>Family Occupied</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#3b82f6;"></span>Check-in</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#f97316;"></span>Check-out</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#8b5cf6;"></span>Turnover</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#cbd5e1;"></span>Empty</span>
          <span class="inline-flex items-center gap-1.5 text-xs text-base-content/70"><span class="inline-block shrink-0" style="width:0.65rem;height:0.65rem;border-radius:2px;background:#b91c1c;"></span>Out of Service</span>
        </div>
        <form method="GET" action="{{ route('kennels') }}" class="flex items-center gap-2">
          <input type="hidden" name="view" value="calendar">
          <input type="hidden" name="search" value="{{ $search }}">
          <input type="hidden" name="type" value="{{ $type }}">
          <input type="hidden" name="status" value="{{ $status }}">
          <input type="hidden" name="per_page" value="{{ request('per_page', 20) }}">
          <label class="input input-sm">
            <span class="text-base-content/70">Start</span>
            <input type="date" name="start_date" value="{{ old('start_date', optional($startDate)->toDateString()) }}" />
          </label>
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </form>
      </div>

      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Kennel</th>
              @foreach($dateColumns as $columnDate)
                <th>{{ $columnDate->format('M j') }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @forelse ($kennels as $kennel)
              <tr class="hover:bg-base-200/40 cursor-pointer *:text-nowrap h-16">
                <td>{{ $kennel->name }}</td>
                @foreach($dateColumns as $columnDate)
                  @php
                    $cell = $availabilityMatrix[$kennel->id][$columnDate->toDateString()] ?? ['state' => 'empty', 'text' => 'Empty'];
                    $markerColor = match($cell['state']) {
                      'occupied' => '#34d399',
                      'occupied_family' => '#ff00ff',
                      'checkin' => '#3b82f6',
                      'checkout' => '#f97316',
                      'turnover' => '#8b5cf6',
                      'empty' => '#cbd5e1',
                      'out_of_service' => '#b91c1c',
                      default => '',
                    };
                    $textClass = $cell['state'] === 'out_of_service' ? 'text-base-content' : 'text-base-content';
                  @endphp
                  <td>
                    <div class="min-w-36 text-sm {{ $textClass }}">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        @if($markerColor)
                          <span class="inline-block shrink-0" style="width: 0.7rem; height: 0.7rem; border-radius: 2px; background-color: {{ $markerColor }};"></span>
                        @endif
                        @if(!empty($cell['pet_imgs']))
                          @foreach($cell['pet_imgs'] as $petImg)
                            <img src="{{ $petImg ? asset('storage/pets/' . $petImg) : asset('images/no_image.jpg') }}" alt="Pet" class="rounded-box bg-base-200 size-10">
                          @endforeach
                        @endif
                        @if($cell['state'] !== 'empty')
                          <span>{{ $cell['text'] }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                @endforeach
              </tr>
            @empty
              <tr>
                <td colspan="{{ 1 + $dateColumns->count() }}" class="text-center text-base-content/60">No kennels found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $kennels->links('layouts.pagination', ['items' => $kennels]) }}    </div>
  </div>

  @endif

  @if ($activeView === 'list')
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-0">
      <div class="flex items-center justify-between px-5 pt-5">
        <div class="inline-flex items-center gap-3">
          <label class="input input-sm">
            <span class="iconify lucide--search text-base-content/80 size-3.5"></span>
            <input class="w-24 sm:w-36" placeholder="Search kennels" aria-label="Search kennels" type="search" onkeydown="handleSearch(event)" value="{{ $search }}"/>
          </label>
        </div>
        @if (hasPermission(27, 'can_create'))
        <a aria-label="Create kennel link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-kennel') }}">
          <span class="iconify lucide--plus size-4"></span>
          <span class="hidden sm:inline">New Kennel</span>
        </a>
        @endif
      </div>

      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>No</th>
              <th>Image</th>
              <th>Name</th>
              <th>Assigned Pet</th>
              <th>Type</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($kennels as $kennel)
            <tr class="hover:bg-base-200/40 cursor-pointer *:text-nowrap">
              <td>{{ $loop->iteration }}</td>
              <td>
                <div class="flex items-center space-x-3 truncate">
                  @if (empty($kennel->img))
                  <img src="{{ asset('images/no_image.jpg') }}" alt="Seller Image" class="rounded-box bg-base-200 size-10">
                  @else
                  <img src="{{ asset('storage/kennels/'. $kennel->img) }}" alt="Seller Image" class="rounded-box bg-base-200 size-10">
                  @endif
                </div>
              </td>
              <td>{{ $kennel->name }}</td>
              <td>
                @if (isset($kennel->assigned_pet_bookings) && $kennel->assigned_pet_bookings->isNotEmpty())
                  <div class="flex flex-col gap-2">
                    @foreach ($kennel->assigned_pet_bookings as $booking)
                      <div class="rounded-box bg-base-200/40 px-2 py-1.5">
                        <p class="text-xs text-base-content/60 mb-1">
                          {{ \Carbon\Carbon::parse($booking->start_date)->format('M j, Y') }}
                          -
                          {{ \Carbon\Carbon::parse($booking->end_date)->format('M j, Y') }}
                        </p>
                        <div class="flex flex-col gap-1.5">
                          @foreach ($booking->pets as $pet)
                            <div class="flex items-center gap-2">
                              <img src="{{ empty($pet->pet_img) ? asset('images/no_image.jpg') : asset('storage/pets/' . $pet->pet_img) }}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-8">
                              <span>{{ $pet->name }}</span>
                            </div>
                          @endforeach
                        </div>
                      </div>
                    @endforeach
                  </div>
                @else
                  <span class="text-base-content/60">No assigned pets</span>
                @endif
              </td>
              <td>
                @php
                  $typeClass = $kennel->type === 'dog' ? 'badge-info' : 'badge-secondary';
                @endphp
                <span class="badge badge-soft badge-sm {{ $typeClass }}">{{ ucfirst($kennel->type) }}</span>
              </td>
              <td>
                @php
                  $statusClass = match($kennel->status) {
                    'In Service' => 'badge-success',
                    'Out of Service' => 'badge-error',
                    default => 'badge-warning',
                  };
                @endphp
                <span class="badge badge-soft badge-sm {{ $statusClass }}">{{ $kennel->status }}</span>
              </td>
              <td>
                <div class="inline-flex w-fit gap-1">
                  @if (hasPermission(27, 'can_update'))
                  <a aria-label="Edit kennel" class="btn btn-square btn-primary btn-outline btn-xs border-transparent" href="{{ route('edit-kennel', ['id' => $kennel->id]) }}">
                    <span class="iconify lucide--pencil" style="font-size: 0.875rem;"></span>
                  </a>
                  @endif
                  @if (hasPermission(27, 'can_delete'))
                  <button type="button" class="btn btn-square btn-error btn-outline btn-xs border-transparent btn-delete-kennel" data-id="{{ $kennel->id }}" data-name="{{ e($kennel->name) }}" aria-label="Delete kennel">
                    <span class="iconify lucide--trash" style="font-size: 0.875rem;"></span>
                  </button>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
            @if ($kennels->isEmpty())
            <tr>
              <td colspan="8" class="text-center text-base-content/60">No kennels found.</td>
            </tr>
            @endif
          </tbody>
        </table>
      </div>

      {{ $kennels->links('layouts.pagination', ['items' => $kennels]) }}
    </div>
  </div>
  @endif
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
    <p class="py-4" id="delete_modal_message">You are about to delete this kennel. Would you like to proceed?</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost">No</button>
      </form>
      <form id="delete_form" method="POST" action="{{ route('delete-kennel') }}">
        @csrf
        <input type="hidden" name="id" id="delete_kennel_id" value="" />
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
  function handleSearch(event) {
    if (event.key === 'Enter') {
      const searchValue = event.target.value;
      const params = new URLSearchParams(window.location.search);
      params.set('search', searchValue);
      params.set('view', 'list');
      params.delete('page');
      window.location.href = `/kennels?${params.toString()}`;
    }
  }

  $(function() {
    $(document).on('click', '.btn-delete-kennel', function() {
      var id = $(this).data('id');
      var name = $(this).data('name') || 'this kennel';
      $('#delete_kennel_id').val(id);
      $('#delete_modal_message').text('You are about to delete the kennel ' + name + '. Would you like to proceed?');
      $('#delete_modal')[0].showModal();
    });
  });
</script>
@endsection
