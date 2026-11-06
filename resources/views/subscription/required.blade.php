@extends('layouts.main')
@section('title', 'Subscription Required')

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Subscription Required</h3>
</div>

<div class="mt-3">
  <div class="card bg-base-100 shadow">
    <div class="card-body items-center text-center">
      <span class="iconify lucide--key-round size-8 text-base-content/50"></span>
      <p class="text-base-content/70">Portal features are unavailable until the facility subscription is active.</p>
    </div>
  </div>
</div>

<dialog id="subscription_required_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-center">
      <span class="iconify lucide--credit-card size-10 text-primary"></span>
    </div>
    <h3 class="mt-3 text-center text-lg font-semibold">Subscription Required</h3>
    @if ($isOwner)
      <p class="py-4 text-center text-base-content/80">
        An active monthly subscription is required to continue using the platform.
        Subscribe now to restore access to your dashboard and all management features.
      </p>
      <div class="flex items-center justify-center gap-2 mt-2">
        <a href="{{ route('logout') }}" class="btn btn-ghost">Log out</a>
        <a href="{{ route('subscription.index') }}" class="btn btn-primary">Go to Subscription</a>
      </div>
    @else
      <p class="py-4 text-center text-base-content/80">
        This facility does not have an active subscription, so portal features are unavailable.
        Please contact the facility owner to renew the subscription.
      </p>
      <div class="flex items-center justify-center mt-2">
        <a href="{{ route('logout') }}" class="btn btn-primary">Log out</a>
      </div>
    @endif
  </div>
</dialog>
@endsection

@section('page-js')
<script>
  (function () {
    var modal = document.getElementById('subscription_required_modal');
    // Keep the modal open: access stays restricted until a subscription is active.
    modal.addEventListener('cancel', function (e) { e.preventDefault(); });
    modal.showModal();
  })();
</script>
@endsection
