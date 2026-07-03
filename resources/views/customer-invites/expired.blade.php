@extends('layouts.public_portal')
@section('title', 'Invitation Expired')

@section('content')
<div class="mx-auto mt-8 max-w-3xl">
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body items-center text-center">

      <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-warning/10 text-warning">
        <span class="iconify lucide--clock size-9"></span>
      </div>

      <h3 class="text-2xl font-semibold">
        Invitation Expired
      </h3>

      <p class="text-base-content/70 mt-2 max-w-xl text-sm">
        This invitation link has expired or has already been used.
      </p>

      <div class="divider my-4"></div>

      <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3">
        <div class="rounded-box bg-base-200 p-4">
          <div class="flex items-center justify-center mb-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f5a524" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link2-off-icon lucide-link-2-off"><path d="M9 17H7A5 5 0 0 1 7 7"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" x2="12" y1="12" y2="12"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
          </div>
          <p class="font-medium">Expired Link</p>
          <p class="text-base-content/60 mt-1 text-xs">
            The invitation is no longer valid.
          </p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--check size-6 text-success mx-auto mb-2"></span>
          <p class="font-medium">Already Registered</p>
          <p class="text-base-content/60 mt-1 text-xs">
            This invitation may have already been completed.
          </p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--mail-plus size-6 text-primary mx-auto mb-2"></span>
          <p class="font-medium">Request a New Invite</p>
          <p class="text-base-content/60 mt-1 text-xs">
            Contact our staff if you still need to complete your registration.
          </p>
        </div>
      </div>

      <div class="alert alert-warning mt-6 max-w-xl text-left">
        <span class="iconify lucide--info size-5"></span>
        <span>
          If you believe this invitation should still be active, please contact our staff and request a new invitation link.
        </span>
      </div>

    </div>
  </div>
</div>
@endsection