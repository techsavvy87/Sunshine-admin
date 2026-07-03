@extends('layouts.main')
@section('title', 'Customers')

@section('page-css')
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Customers Overview</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li>Customers</li>
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
            <input class="w-24 sm:w-36" placeholder="Search customers" aria-label="Search customers" type="search" onkeydown="handleSearch(event)" value="{{ $search }}"/>
          </label>
        </div>
        @if (hasPermission(1, 'can_create'))
        <div class="inline-flex items-center gap-2">
          <button class="btn btn-outline btn-sm max-sm:btn-square" type="button" onclick="invite_customer_modal.showModal()">
            <span class="iconify lucide--mail-plus size-4"></span>
            <span class="hidden sm:inline">Invite Customer</span>
          </button>
          <a aria-label="Create seller link" class="btn btn-primary btn-sm max-sm:btn-square" href="{{ route('add-customer') }}">
            <span class="iconify lucide--plus size-4"></span>
            <span class="hidden sm:inline">New Customer</span>
          </a>
        </div>
        @endif
      </div>
      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>No</th>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Phone 1</th>
              <th style="text-align: center;">Verified</th>
              <th>Block</th>
              <th>Pets</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($customers as $customer)
            <tr class="hover:bg-base-200/40 *:text-nowrap">
              <td class="font-medium">{{ $loop->iteration }}</td>
              <td>
                <div class="flex items-center space-x-3 truncate">
                  @if (empty($customer->profile) || empty($customer->profile->avatar_img))
                  <img src="{{ asset('images/default-user-avatar.png') }}" alt="Seller Image" class="mask mask-squircle bg-base-200 size-10">
                  @else
                  <img src="{{ asset('storage/profiles/'. $customer->profile->avatar_img) }}" alt="Seller Image" class="mask mask-squircle bg-base-200 size-10">
                  @endif
                  <div>
                    <p class="font-medium">{{ $customer->profile ? $customer->profile->first_name . ' ' . $customer->profile->last_name : ''   }}</p>
                  </div>
                </div>
              </td>
              <td class="font-medium">{{ $customer->name }}</td>
              <td>{{ $customer->email }}</td>
              <td>{{ $customer->profile ? $customer->profile->phone_number_1 : '' }}</td>
              <td style="text-align: center;">
                @if ($customer->email_verified_at)
                <span class="iconify lucide--badge-check text-success size-4.5"></span>
                @else
                <span class="iconify lucide--badge-x text-error size-4.5"></span>
                @endif
              </td>
              <td style="max-width: 180px;">
                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                  @if ($customer->block_reservations)
                    <div class="badge badge-soft badge-warning badge-sm">Reservations</div>
                  @endif

                  @if ($customer->block_messages)
                    <div class="badge badge-soft badge-secondary badge-sm">Messages</div>
                  @endif
                </div>
              </td>
              <td style="max-width: 180px;">
                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                  @foreach($customer->pets as $pet)
                  <a href="{{ route('edit-pet', ['id' => $pet->id]) }}">
                    <div class="badge badge-soft badge-primary badge-sm">{{ $pet->name }}</div>
                  </a>
                  @endforeach
                </div>
              </td>
              <td>
                <div class="inline-flex w-fit">
                  @if (hasPermission(1, 'can_update'))
                  <a aria-label="Edit seller link" class="btn btn-square btn-ghost btn-sm" href="{{ route('edit-customer', ['id' => $customer->id]) }}">
                    <span class="iconify lucide--pencil text-base-content/80 size-4"></span>
                  </a>
                  @endif
                  @if (hasPermission(1, 'can_delete'))
                  <button aria-label="Dummy delete seller" onclick="confirmDelete({{ $customer }})" class="btn btn-square btn-error btn-outline btn-sm border-transparent">
                    <span class="iconify lucide--trash size-4"></span>
                  </button>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      {{ $customers->links('layouts.pagination', ['items' => $customers]) }}
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
        <button class="btn btn-ghost">No</button>
      </form>
      <form id="delete_form" method="POST" action="{{ route('delete-customer') }}">
        @csrf
        <input type="hidden" name="user_id" value="" />
        <button class="btn btn-error">Delete</button>
      </form>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<dialog id="invite_customer_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Invite Customer
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <form action="{{ route('send-customer-invite') }}" method="POST" class="mt-3">
      @csrf
      <div class="space-y-2">
        <label class="fieldset-label" for="invite_email">Customer Email</label>
        <label class="input w-full focus:outline-0">
          <span class="iconify lucide--mail text-base-content/60 size-4"></span>
          <input class="grow focus:outline-0" id="invite_email" name="email" placeholder="customer@email.com" type="email" required />
        </label>
      </div>
      <div class="modal-action">
        <button class="btn btn-ghost" type="button" onclick="invite_customer_modal.close()">Cancel</button>
        <button class="btn btn-primary" type="submit">Send Invite</button>
      </div>
    </form>
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
      const url = `/customers?search=${encodeURIComponent(searchValue)}`;
      window.location.href = url;
    }
  }
  function confirmDelete(customer) {
    const message = `You are about to delete the customer ${customer.profile ? customer.profile.first_name + ' ' + customer.profile.last_name : ''}. Would you like to proceed?`;
    $('#delete_modal_message').text(message);
    $('#delete_form input[name=user_id]').val(customer.id);
    delete_modal.showModal();
  }
</script>
@endsection