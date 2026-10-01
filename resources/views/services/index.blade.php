@extends('layouts.main')
@section('title', 'Services')

@section('page-css')
<style>
.price-breakdown {
  min-width: 120px;
}
.price-breakdown .size-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 2px 0;
  border-bottom: 1px solid #f0f0f0;
}
.price-breakdown .size-item:last-child {
  border-bottom: none;
}
.duration-breakdown {
  min-width: 120px;
}
.duration-breakdown .size-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 2px 0;
  border-bottom: 1px solid #f0f0f0;
}
.duration-breakdown .size-item:last-child {
  border-bottom: none;
}
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Services Overview</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Services</li>
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
            <input class="w-24 sm:w-36" placeholder="Search services" aria-label="Search services" type="search" onkeydown="handleSearch(event)" value="{{ $search }}"/>
          </label>
        </div>
        @if (hasPermission(12, 'can_create'))
        <a aria-label="Create seller link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-service') }}">
          <span class="iconify lucide--plus size-4"></span>
          <span class="hidden sm:inline">New Service</span>
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
              <th>Price</th>
              <th>Duration</th>
              <th>Category</th>
              <th style="text-align:center">Level</th>
              <th style="text-align:center">Status</th>
              <th>Future Price</th>
              <th>Effective Date</th>
              <th style="text-align:center">Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($services as $service)
            <tr class="hover:bg-base-200/40 cursor-pointer *:text-nowrap">
              <td>{{ $loop->iteration }}</td>
              <td>
                <div class="flex items-center space-x-3 truncate">
                  @if (empty($service->avatar_img))
                  <img src="{{ asset('images/no_image.jpg') }}" alt="Seller Image" class="rounded-box bg-base-200 size-10">
                  @else
                  <img src="{{ asset('storage/services/'. $service->avatar_img) }}" alt="Seller Image" class="rounded-box bg-base-200 size-10">
                  @endif
                </div>
              </td>
              <td>{{ $service->name }}</td>
              <td>
                @if(isGroomingService($service) && ($service->price_small || $service->price_medium || $service->price_large || $service->price_xlarge))
                  —
                @else
                  @if($service->price)
                    ${{ number_format($service->price, 2) }}
                  @else
                    <span class="text-gray-500">N/A</span>
                  @endif
                @endif
              </td>
              <td>
                @if(isGroomingService($service) && ($service->duration_small || $service->duration_medium || $service->duration_large || $service->duration_xlarge))
                  —
                @else
                  @if($service->duration)
                    {{ rtrim(rtrim(number_format($service->duration, 2), '0'), '.') }} hrs
                  @else
                    <span class="text-gray-500">N/A</span>
                  @endif
                @endif
              </td>
              <td>{{ $service->category->name }}</td>
              <td style="text-align:center">
                @if ($service->level === 'primary')
                  <span class="badge badge-outline badge-primary">Primary</span>
                @else
                  <span class="badge badge-outline badge-secondary">Secondary</span>
                @endif
              </td>
              <td style="text-align:center">
                @if ($service->status === 'active')
                  <span class="badge badge-soft badge-success">Active</span>
                @else
                  <span class="badge badge-soft badge-error">Inactive</span>
                @endif
              </td>
              <td>
                @if($service->future_price)
                  ${{ number_format($service->future_price, 2) }}
                @else
                  <span class="text-gray-500">—</span>
                @endif
              </td>
              <td>
                @if($service->future_price_effective_date)
                  {{ $service->future_price_effective_date->format('m/d/Y') }}
                @else
                  <span class="text-gray-500">—</span>
                @endif
              </td>
              <td style="text-align:center">
                <div class="inline-flex w-fit">
                  @if (hasPermission(12, 'can_update'))
                  <a class="btn btn-square btn-ghost btn-sm" href="{{ route('edit-service', ['id' => $service->id]) }}">
                    <span class="iconify lucide--pencil text-base-content/80 size-4"></span>
                  </a>
                  @endif
                  @if (hasPermission(12, 'can_delete'))
                  <button onclick="confirmDelete({{ $service }})" class="btn btn-square btn-error btn-outline btn-sm border-transparent">
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
      <form id="delete_form" method="POST" action="{{ route('delete-service') }}">
        @csrf
        <input type="hidden" name="service_id" value="" />
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
      const url = `/services?search=${encodeURIComponent(searchValue)}`;
      window.location.href = url;
    }
  }
  function confirmDelete(service) {
    const message = `You are about to delete the service ${service.name}. Would you like to proceed?`;
    $('#delete_modal_message').text(message);
    $('#delete_form input[name=service_id]').val(service.id);
    delete_modal.showModal();
  }
</script>
@endsection