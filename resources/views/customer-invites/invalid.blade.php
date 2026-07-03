@extends('layouts.public_portal')
@section('title', 'Invalid Invitation')

@section('content')
<div class="mx-auto mt-8 max-w-3xl">
  <div class="card bg-base-100 shadow-lg">
    <div class="card-body items-center text-center">

      <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-error/10 text-error">
        <span class="iconify lucide--triangle-alert size-9"></span>
      </div>

      <h3 class="text-2xl font-semibold">
        Invalid or Expired Invitation
      </h3>

      <p class="text-base-content/70 mt-2 max-w-xl text-sm">
        This invitation link is no longer valid. It may have expired, already been used,
        or the link is incorrect.
      </p>

      <div class="divider my-4"></div>

      <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3">
        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--clock size-6 text-warning mx-auto mb-2"></span>
          <p class="font-medium">Invitation Expired</p>
          <p class="text-base-content/60 mt-1 text-xs">
            The invitation has passed its valid period.
          </p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--link size-6 text-error mx-auto mb-2"></span>
          <p class="font-medium">Invalid Link</p>
          <p class="text-base-content/60 mt-1 text-xs">
            The invitation link is incorrect or incomplete.
          </p>
        </div>

        <div class="rounded-box bg-base-200 p-4">
          <span class="iconify lucide--mail-plus size-6 text-primary mx-auto mb-2"></span>
          <p class="font-medium">Need a New Invite?</p>
          <p class="text-base-content/60 mt-1 text-xs">
            Please contact our staff to request a new invitation.
          </p>
        </div>
      </div>

      <div class="alert alert-warning mt-6 max-w-xl text-left">
        <span class="iconify lucide--info size-5"></span>
        <span>
          If you believe this invitation should still be valid, please contact the facility staff for assistance.
        </span>
      </div>

    </div>
  </div>
</div>
@endsection