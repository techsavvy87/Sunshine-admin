@extends('layouts.main')
@section('title', 'Rooms')

@section('page-css')
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Rooms Overview</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Rooms</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <div class="card bg-base-100 shadow mt-3">
    <div class="card-body p-0">
      <div class="flex items-center justify-between px-5 pt-5">
        <div class="inline-flex items-center gap-3">
          <label class="input input-sm">
            <span class="iconify lucide--search text-base-content/80 size-3.5"></span>
            <input class="w-24 sm:w-36" id="search" placeholder="Search rooms" aria-label="Search rooms" type="search" onkeydown="handleRoomFilter(event)" value="{{ $search }}"/>
          </label>
        </div>

        @if (hasPermission(28, 'can_create'))
        <a aria-label="Create room link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-room') }}">
          <span class="iconify lucide--plus size-4"></span>
          <span class="hidden sm:inline">New Room</span>
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
              <th>Assigned Kennels / Pets</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rooms as $room)
            <tr class="hover:bg-base-200/40 cursor-pointer align-top">
              <td>{{ ($rooms->currentPage() - 1) * $rooms->perPage() + $loop->iteration }}</td>
              <td>
                <div class="flex items-center space-x-3 truncate">
                  @if (empty($room->img))
                  <img src="{{ asset('images/no_image.jpg') }}" alt="Room Image" class="rounded-box bg-base-200 size-10">
                  @else
                  <img src="{{ asset('storage/rooms/' . $room->img) }}" alt="Room Image" class="rounded-box bg-base-200 size-10">
                  @endif
                </div>
              </td>
              <td class="font-medium">{{ $room->name }}</td>
              <td>
                @if (isset($room->current_room_pets) && $room->current_room_pets->isNotEmpty())
                  <div class="mb-2 rounded-box bg-base-200/40 px-2 py-2 min-w-[260px] max-w-md">
                    <div class="flex flex-col gap-2">
                      @foreach ($room->current_room_pets as $pet)
                        <div class="flex items-center gap-2">
                          <img src="{{ empty($pet->pet_img) ? asset('images/no_image.jpg') : asset('storage/pets/' . $pet->pet_img) }}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-8" />
                          <span class="text-sm font-medium truncate">{{ $pet->name }}</span>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if ($room->assigned_kennels->isNotEmpty())
                <div class="space-y-2 min-w-[260px] max-w-md">
                  @foreach ($room->kennel_pet_assignments as $assignment)
                  @php
                    $hasConflict = isset($assignment->appointments) && $assignment->appointments->contains(fn($apt) => isAssignmentConflict($apt));
                    $kennelType = $assignment->kennel->kennel_type ?? 'Canine';
                    $kennelTypeClass = $kennelType === 'Feline' ? 'badge-secondary' : 'badge-info';
                  @endphp
                  <div class="flex items-center gap-4 rounded-box {{ $hasConflict ? 'assignment-conflict-bg border-l-4 border-yellow-500' : 'bg-base-200/40' }} px-2 py-1.5">
                    <div class="flex items-center gap-2">
                      <span class="badge badge-soft badge-sm {{ $kennelTypeClass }} shrink-0">{{ $assignment->kennel->name }} ({{ $kennelType }})</span>
                      @if ($hasConflict)
                        <span class="badge badge-warning badge-sm">
                          <span class="iconify lucide--alert-circle size-3"></span>
                          Conflict
                        </span>
                      @endif
                    </div>

                    @if (isset($assignment->pets) && $assignment->pets->isNotEmpty())
                      <div class="flex flex-col gap-2">
                        @foreach ($assignment->pets as $pet)
                          <div class="flex items-center gap-2">
                            <img src="{{ empty($pet->pet_img) ? asset('images/no_image.jpg') : asset('storage/pets/' . $pet->pet_img) }}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-8" />
                            <span class="text-sm font-medium truncate">{{ $pet->name }}</span>
                          </div>
                        @endforeach
                      </div>
                    @endif
                  </div>
                  @endforeach
                </div>
                @elseif (($room->room_types ?? '') !== 'space' && (!isset($room->current_room_pets) || $room->current_room_pets->isEmpty()))
                <span class="text-sm text-base-content/60">No kennels assigned</span>
                @endif
              </td>
              <td>
                @php
                  $statusClass = match($room->status) {
                    'Available' => 'badge-success',
                    'Blocked' => 'badge-error',
                    default => 'badge-warning',
                  };
                @endphp
                <span class="badge badge-soft badge-sm {{ $statusClass }}">{{ $room->status }}</span>
              </td>
              <td>
                <div class="inline-flex w-fit gap-1">
                  @if (hasPermission(28, 'can_update'))
                  <a aria-label="Edit room" class="btn btn-square btn-primary btn-outline btn-xs border-transparent" href="{{ route('edit-room', ['id' => $room->id]) }}">
                    <span class="iconify lucide--pencil" style="font-size: 0.875rem;"></span>
                  </a>
                  @endif
                  @if (hasPermission(28, 'can_delete'))
                  <button type="button" class="btn btn-square btn-error btn-outline btn-xs border-transparent btn-delete-room" data-id="{{ $room->id }}" data-name="{{ e($room->name) }}" aria-label="Delete room">
                    <span class="iconify lucide--trash" style="font-size: 0.875rem;"></span>
                  </button>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
            @if ($rooms->isEmpty())
            <tr>
              <td colspan="8" class="text-center text-base-content/60">No rooms found.</td>
            </tr>
            @endif
          </tbody>
        </table>
      </div>

      {{ $rooms->links('layouts.pagination', ['items' => $rooms]) }}
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
    <p class="py-4" id="delete_modal_message">You are about to delete this room. Would you like to proceed?</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost">No</button>
      </form>
      <form id="delete_form" method="POST" action="{{ route('delete-room') }}">
        @csrf
        <input type="hidden" name="id" id="delete_room_id" value="" />
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
  function applyRoomFilters() {
    const params = new URLSearchParams();
    const search = $('#search').val();

    if (search) params.set('search', search);

    const queryString = params.toString();
    window.location.href = '{{ route('rooms') }}' + (queryString ? '?' + queryString : '');
  }

  function handleRoomFilter(event) {
    if (event.key === 'Enter') {
      applyRoomFilters();
    }
  }

  $(function() {
    $(document).on('click', '.btn-delete-room', function() {
      const id = $(this).data('id');
      const name = $(this).data('name') || 'this room';
      $('#delete_room_id').val(id);
      $('#delete_modal_message').text('You are about to delete the room ' + name + '. Would you like to proceed?');
      $('#delete_modal')[0].showModal();
    });
  });
</script>
@endsection
