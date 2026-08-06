@extends('layouts.main')
@section('title', 'Facility Address')

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Facility Address</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li class="opacity-80">Facility Address</li>
    </ul>
  </div>
</div>

<div class="mt-3">
  @include('layouts.alerts')

  <form action="{{ route('update-facility-address') }}" method="POST" id="facility_address_form">
    @csrf

    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="card-title">Facility Information</div>
        <div class="fieldset mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
          <div class="space-y-2 lg:col-span-2">
            <label class="fieldset-label" for="address">Address*</label>
            <input
              class="input w-full"
              id="address"
              name="address"
              placeholder="Street address"
              type="text"
              value="{{ old('address', $facilityAddress->address ?? '') }}"
              required
            />
          </div>

          <div class="space-y-2">
            <label class="fieldset-label" for="city">City*</label>
            <input
              class="input w-full"
              id="city"
              name="city"
              placeholder="City"
              type="text"
              value="{{ old('city', $facilityAddress->city ?? '') }}"
              required
            />
          </div>

          <div class="space-y-2">
            <label class="fieldset-label" for="state">State*</label>
            <select class="select w-full" name="state" id="state" required>
              <option value="" {{ $selectedState ? '' : 'selected' }} disabled>Select a state</option>
              @foreach($states as $code => $name)
                <option value="{{ $code }}" {{ $selectedState === $code ? 'selected' : '' }}>{{ $name }}</option>
              @endforeach
            </select>
          </div>

          <div class="space-y-2">
            <label class="fieldset-label" for="zip">Zip*</label>
            <input
              class="input w-full"
              id="zip"
              name="zip"
              placeholder="Zip code"
              type="text"
              value="{{ old('zip', $facilityAddress->zip_code ?? '') }}"
              required
            />
          </div>

          <div class="space-y-2">
            <label class="fieldset-label" for="late_fees_enabled">Enable Late Fees</label>
            <label class="label w-fit cursor-pointer gap-3">
              <input type="hidden" name="late_fees_enabled" value="0" />
              <input id="late_fees_enabled" name="late_fees_enabled" type="checkbox" value="1" class="toggle toggle-primary" {{ old('late_fees_enabled', $facilityAddress->late_fees_enabled ?? true) ? 'checked' : '' }} />
              <span id="late_fees_enabled_label">{{ old('late_fees_enabled', $facilityAddress->late_fees_enabled ?? true) ? 'On' : 'Off' }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      <button class="btn btn-sm btn-primary" type="button" onclick="saveFacilityAddress()">
        <span class="iconify lucide--check size-4"></span>
        Save
      </button>
    </div>
  </form>
</div>
@endsection

@section('page-js')
<script>
$('#late_fees_enabled').on('change', function() {
  $('#late_fees_enabled_label').text(this.checked ? 'On' : 'Off');
});

function saveFacilityAddress() {
  const address = ($('#address').val() || '').trim();
  const city = ($('#city').val() || '').trim();
  const state = $('#state').val();
  const zip = ($('#zip').val() || '').trim();

  if (!address) {
    $('#alert_message').text('Please fill in address.');
    alert_modal.showModal();
    $('#address').focus();
    return false;
  }

  if (!city) {
    $('#alert_message').text('Please fill in city.');
    alert_modal.showModal();
    $('#city').focus();
    return false;
  }

  if (!/^[a-zA-Z\s\.-]+$/.test(city)) {
    $('#alert_message').text('City can only contain letters, spaces, dots, and hyphens.');
    alert_modal.showModal();
    $('#city').focus();
    return false;
  }

  if (!state) {
    $('#alert_message').text('Please select a state.');
    alert_modal.showModal();
    $('#state').focus();
    return false;
  }

  if (!zip) {
    $('#alert_message').text('Please fill in zip code.');
    alert_modal.showModal();
    $('#zip').focus();
    return false;
  }

  if (!/^\d{5}(?:-\d{4})?$/.test(zip)) {
    $('#alert_message').text('Zip code must be in 5-digit format or ZIP+4 format (e.g. 12345 or 12345-6789).');
    alert_modal.showModal();
    $('#zip').focus();
    return false;
  }

  $('#facility_address_form').submit();
}

@if ($errors->any())
  $(function() {
    $('#alert_message').text(@json($errors->first()));
    alert_modal.showModal();
  });
@endif
</script>
@endsection
