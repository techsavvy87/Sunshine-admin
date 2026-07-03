@extends('layouts.public_portal')
@section('title', 'Customer Registration')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
  <style>
    .invite-pet-top-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 1.25rem;
      align-items: stretch;
    }

    .invite-pet-basic-row,
    .invite-pet-basic-row-secondary {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 1rem;
    }

    .invite-vaccination-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 0.75rem;
      align-items: stretch;
      width: 100%;
    }

    .invite-vaccination-action {
      display: flex;
      justify-content: flex-end;
      align-items: center;
    }

    @media (min-width: 768px) {
      .invite-pet-basic-row,
      .invite-pet-basic-row-secondary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (min-width: 1024px) {
      .invite-vaccination-row {
        grid-template-columns: minmax(220px, 2fr) minmax(180px, 1.5fr) minmax(130px, 1fr) 40px;
        gap: 1rem;
        align-items: center;
      }
    }

    @media (min-width: 1280px) {
      .invite-pet-top-grid {
        grid-template-columns: minmax(320px, 3fr) minmax(0, 9fr);
      }

      .invite-pet-basic-row {
        grid-template-columns: minmax(220px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) minmax(190px, 2fr) minmax(210px, 2fr) minmax(210px, 2fr);
      }

      .invite-pet-basic-row-secondary {
        grid-template-columns: minmax(220px, 2fr) minmax(120px, 1fr) minmax(120px, 1fr) minmax(220px, 2fr) minmax(220px, 2fr);
      }
    }
    @media (max-width: 639px) {
        #invite_registration_form button[type="submit"] {
            width: 100% !important;
        }
    }

    @media (min-width: 640px) {
        #invite_registration_form button[type="submit"] {
            width: auto !important;
        }
    }
  </style>
@endsection

@section('content')
<div class="w-full max-w-none px-3">
  <h3 class="mt-6 text-xl font-semibold">Complete Your Registration</h3>
  <p class="text-base-content/70 mt-2 text-sm">Please complete your customer and pet information.</p>

  <div class="mt-4">
    @include('layouts.alerts')
  </div>

  <form action="{{ route('customer-invite.submit', ['token' => $token]) }}" method="POST" enctype="multipart/form-data" id="invite_registration_form" class="mt-4 space-y-4">
    @csrf

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12 mt-3">
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow h-full">
          <div class="card-body">
            <div class="card-title">Upload Avatar</div>
            <div class="mt-4">
              <input type="file" data-filepond-customer class="uploadFile" name="avatar_img" />
              <input type="hidden" id="temp_file_customer" name="temp_file_customer" value="{{ old('temp_file_customer') }}" />
            </div>
          </div>
        </div>
      </div>

      <div class="xl:col-span-6">
        <div class="card bg-base-100 shadow h-full">
          <div class="card-body">
            <div class="card-title">Account Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="username">Username*</label>
                <label class="input w-full focus:outline-0">
                  <span class="iconify lucide--user text-base-content/60 size-4"></span>
                  <input class="grow focus:outline-0" placeholder="User Name" id="username" name="username" type="text" value="{{ old('username') }}" required />
                </label>
              </div>

              <div class="space-y-2">
                <label class="fieldset-label" for="email">Email*</label>
                <label class="input w-full focus:outline-0">
                  <span class="iconify lucide--mail text-base-content/60 size-4"></span>
                  <input class="grow focus:outline-0" id="email" type="email" value="{{ $invite->email }}" readonly />
                </label>
              </div>

              <div class="space-y-2">
                <label class="fieldset-label" for="password">Password*</label>
                <label class="input w-full focus:outline-0">
                  <span class="iconify lucide--key-round text-base-content/60 size-4"></span>
                  <input class="grow focus:outline-0" placeholder="Password" id="password" name="password" type="password" autocomplete="new-password" required />
                  <label class="swap btn btn-xs btn-ghost btn-circle text-base-content/60">
                    <input type="checkbox" aria-label="Show password" data-password="password" />
                    <span class="iconify lucide--eye swap-off size-4"></span>
                    <span class="iconify lucide--eye-off swap-on size-4"></span>
                  </label>
                </label>
              </div>

              <div class="space-y-2">
                <label class="fieldset-label" for="password_confirmation">Confirm Password*</label>
                <label class="input w-full focus:outline-0">
                  <span class="iconify lucide--key-round text-base-content/60 size-4"></span>
                  <input class="grow focus:outline-0" id="password_confirmation" name="password_confirmation" placeholder="Confirm Password" type="password" autocomplete="new-password" required />
                  <label class="swap btn btn-xs btn-ghost btn-circle text-base-content/60">
                    <input type="checkbox" aria-label="Show password" data-password="password_confirmation" />
                    <span class="iconify lucide--eye swap-off size-4"></span>
                    <span class="iconify lucide--eye-off swap-on size-4"></span>
                  </label>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow h-full">
          <div class="card-body">
            <div class="flex items-center justify-between">
              <div class="card-title">Additional Owners</div>
              <button class="btn btn-primary btn-soft btn-sm" type="button" onclick="addAdditionalOwner()">
                <span class="iconify lucide--plus size-3.5"></span>
                Add
              </button>
            </div>
            <input type="hidden" name="owners" id="owners" value="" />
            <div class="fieldset mt-3 space-y-2" id="additional_owners"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
      <div class="xl:col-span-6">
        <div class="card bg-base-100 shadow h-full">
          <div class="card-body">
            <div class="card-title">Basic Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="first_name">First Name*</label>
                <input class="input w-full" placeholder="First Name" name="first_name" type="text" id="first_name" value="{{ old('first_name') }}" required />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="last_name">Last Name*</label>
                <input class="input w-full" placeholder="Last Name" name="last_name" type="text" id="last_name" value="{{ old('last_name') }}" required />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="phone_number_1">Phone Number*</label>
                <input class="input w-full" placeholder="(098) 765-4321" type="tel" name="phone_number_1" id="phone_number_1" value="{{ old('phone_number_1') }}" oninput="formatPhoneNumber(this)" required />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="phone_number_2">Phone Number2</label>
                <input class="input w-full" placeholder="(098) 765-4321" type="tel" name="phone_number_2" id="phone_number_2" value="{{ old('phone_number_2') }}" oninput="formatPhoneNumber(this)" />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="home_number">Home Number</label>
                <input class="input w-full" placeholder="(098) 765-4321" type="tel" name="home_number" id="home_number" value="{{ old('home_number') }}" oninput="formatPhoneNumber(this)" />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="work_number">Work Number</label>
                <input class="input w-full" placeholder="(098) 765-4321" type="tel" name="work_number" id="work_number" value="{{ old('work_number') }}" oninput="formatPhoneNumber(this)" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="xl:col-span-6">
        <div class="card bg-base-100 shadow h-full">
          <div class="card-body">
            <div class="card-title">Address</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="street_address">Street</label>
                <input class="input w-full" id="street_address" placeholder="Street" type="text" name="street_address" value="{{ old('street_address') }}" />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="city">City</label>
                <input class="input w-full" id="city" placeholder="City" type="text" name="city" value="{{ old('city') }}" />
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="state">State</label>
                <select class="select w-full" name="state" id="state">
                  <option value="">Select a state</option>
                  @foreach($states as $code => $name)
                    <option value="{{ $code }}" {{ old('state') === $code ? 'selected' : '' }}>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="zip_code">Zip Code</label>
                <input class="input w-full" id="zip_code" placeholder="564-879" type="text" name="zip_code" value="{{ old('zip_code') }}" />
              </div>
              <div class="lg:col-span-2 space-y-2">
                <label class="fieldset-label" for="emergency_contact_info">Emergency Contact Info</label>
                <textarea class="textarea w-full" placeholder="Emergency Contact Info" name="emergency_contact_info" id="emergency_contact_info">{{ old('emergency_contact_info') }}</textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow">
      <div class="card-body">
        <div class="flex items-center justify-between">
          <div class="card-title">Pet Information</div>
          <button class="btn btn-primary btn-soft btn-sm" type="button" onclick="addPet()">
            <span class="iconify lucide--plus size-3.5"></span>
            + Add Pet
          </button>
        </div>
        <div class="mt-3 space-y-4" id="pets_container"></div>
      </div>
    </div>

    <div class="mt-6 flex justify-end">
        <button type="submit" class="btn btn-primary btn-sm">
            Submit Registration
        </button>
    </div>
  </form>
</div>
@endsection

@section('page-js')
<script src="{{ asset('src/libs/filepond/filepond.min.js') }}"></script>
<script src="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.js') }}"></script>

<script>
  const breeds = @json($breedOptions);
  const colors = @json($colorOptions);
  const coatTypes = @json($coatTypeOptions);
  const weightRanges = @json($weightRangeOptions);
  const vaccinationTypeOptions = @json($vaccinationTypeOptions);
  const oldPets = @json(old('pets', []));
  const csrfToken = '{{ csrf_token() }}';

  let ownerIdx = 0;
  function getUsedPetIndices() {
    return $('#pets_container .pet-form').map(function() {
      return Number($(this).data('pet-index'));
    }).get().filter((value) => !Number.isNaN(value));
  }

  function getNextPetIndex() {
    const used = getUsedPetIndices();
    let next = 0;
    while (used.includes(next)) {
      next += 1;
    }
    return next;
  }

  function optionHtml(items, selectedValue) {
    return items.map((item) => {
      const selected = String(selectedValue || '') === String(item.id) ? 'selected' : '';
      return `<option value="${item.id}" ${selected}>${item.name}</option>`;
    }).join('');
  }

  function addAdditionalOwner(prefill = { name: '', phone: '' }) {
    const ownerContainer = $('#additional_owners');
    const ownerCount = ownerContainer.children().length;

    if (ownerCount >= 4) {
      return;
    }

    const newOwner = $(`
      <div class="flex gap-2" id="owner_${ownerIdx}">
        <input class="input w-full input-sm" placeholder="Name" id="owner_name_${ownerIdx}" type="text" value="${prefill.name || ''}" />
        <input class="input w-full input-sm" placeholder="Phone Number" id="owner_phone_${ownerIdx}" type="text" value="${prefill.phone || ''}" oninput="formatPhoneNumber(this)" />
        <button type="button" class="btn btn-sm btn-ghost btn-square" aria-label="remove" onclick="removeOwner(${ownerIdx})">
          <span class="iconify lucide--x size-3"></span>
        </button>
      </div>
    `);

    ownerContainer.append(newOwner);
    ownerIdx++;
  }

  function removeOwner(ownerId) {
    $(`#owner_${ownerId}`).remove();
  }

  function vaccinationOptionsHtml(selectedValue = '') {
    const normalized = String(selectedValue || '');
    return vaccinationTypeOptions.map((option) => {
      const selected = normalized === String(option) ? 'selected' : '';
      return `<option value="${option}" ${selected}>${option}</option>`;
    }).join('');
  }

  function normalizeVaccinations(vaccinations) {
    if (!vaccinations) {
      return [{}];
    }

    const rows = Array.isArray(vaccinations)
      ? vaccinations
      : Object.values(vaccinations);

    if (!rows.length) {
      return [{}];
    }

    return rows;
  }

  function vaccinationRowsTemplate(petIndex, vaccinations) {
    const rows = normalizeVaccinations(vaccinations);
    return rows.map((vaccination, rowIndex) => vaccinationRowTemplate(petIndex, rowIndex, vaccination)).join('');
  }

  function vaccinationRowTemplate(petIndex, rowIndex, vaccination = {}) {
    return `
      <div class="vaccination-row invite-vaccination-row" data-row-index="${rowIndex}">
        <div class="min-w-0">
          <select class="select w-full" name="pets[${petIndex}][vaccinations][${rowIndex}][type]">
            <option value="">Select vaccination</option>
            ${vaccinationOptionsHtml(vaccination.type || '')}
          </select>
        </div>
        <div class="min-w-0">
          <input class="input w-full" type="date" name="pets[${petIndex}][vaccinations][${rowIndex}][date]" value="${vaccination.date || ''}" />
        </div>
        <div class="min-w-0">
          <input class="input w-full" type="text" placeholder="Months" name="pets[${petIndex}][vaccinations][${rowIndex}][months]" value="${vaccination.months || ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
        </div>
        <div class="invite-vaccination-action lg:w-10">
          <button type="button" class="btn btn-ghost btn-sm" onclick="removeVaccinationRow(this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f31260" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-minus-icon lucide-minus"><path d="M5 12h14"></path></svg>
          </button>
        </div>
      </div>
    `;
  }

  function petTemplate(index, pet = {}) {
    return `
      <div class="pet-form space-y-5" id="pet_form_${index}" data-pet-index="${index}">
        <div class="flex items-center justify-between rounded-box border border-base-300 bg-base-100 px-4 py-3">
          <div class="font-medium">Pet ${index + 1}</div>
          <button class="btn btn-ghost btn-sm" type="button" onclick="removePet(${index})">Remove</button>
        </div>

        <div class="invite-pet-top-grid">
          <div class="min-w-0">
            <div class="card bg-base-100 shadow h-full">
              <div class="card-body">
                <div class="card-title">Upload Pet Image</div>
                <div class="mt-4">
                  <input type="file" data-filepond-pet data-pet-index="${index}" class="uploadFile" name="pet_img" />
                  <input type="hidden" name="pets[${index}][temp_file_pet]" id="temp_file_pet_${index}" value="${pet.temp_file_pet || ''}" />
                </div>
              </div>
            </div>
          </div>

          <div class="min-w-0">
            <div class="card bg-base-100 shadow h-full">
              <div class="card-body">
                <div class="card-title">Basic Information</div>

                <div class="mt-2 space-y-4">
                  <div class="invite-pet-basic-row">
                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Pet Name*</label>
                      <label class="input w-full focus:outline-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dog-icon lucide-dog text-base-content/80"><path d="M11.25 16.25h1.5L12 17z"/><path d="M16 14v.5"/><path d="M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309"/><path d="M8 14v.5"/><path d="M8.5 8.5c-.384 1.05-1.083 2.028-2.344 2.5-1.931.722-3.576-.297-3.656-1-.113-.994 1.177-6.53 4-7 1.923-.321 3.651.845 3.651 2.235A7.497 7.497 0 0 1 14 5.277c0-1.39 1.844-2.598 3.767-2.277 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5"/></svg>
                        <input class="grow focus:outline-0" placeholder="e.g. Fluffy" name="pets[${index}][pet_name]" type="text" value="${pet.pet_name || ''}" required />
                      </label>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Sex*</label>
                      <select class="select w-full" name="pets[${index}][sex]" required>
                        <option value="male" ${pet.sex === 'male' ? 'selected' : ''}>Male</option>
                        <option value="female" ${pet.sex === 'female' ? 'selected' : ''}>Female</option>
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Type*</label>
                      <select class="select w-full" name="pets[${index}][type]" required>
                        <option value="Dog" ${pet.type === 'Dog' ? 'selected' : ''}>Dog</option>
                        <option value="Cat" ${pet.type === 'Cat' ? 'selected' : ''}>Cat</option>
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Spay/Neuter</label>
                      <select class="select w-full" name="pets[${index}][spay_neuter]">
                        <option value="">Select status</option>
                        <option value="spayed" ${pet.spay_neuter === 'spayed' ? 'selected' : ''}>Spayed</option>
                        <option value="neutered" ${pet.spay_neuter === 'neutered' ? 'selected' : ''}>Neutered</option>
                        <option value="intact" ${pet.spay_neuter === 'intact' ? 'selected' : ''}>Intact</option>
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Birth Date</label>
                      <input class="input w-full pet-birth-date" data-pet-index="${index}" type="date" name="pets[${index}][birth_date]" value="${pet.birth_date || ''}" />
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Age</label>
                      <label class="input w-full focus:outline-0">
                        <input class="grow focus:outline-0" id="pet_age_${index}" placeholder="e.g. 2" type="text" name="pets[${index}][age]" value="${pet.age || ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                        <span class="badge badge-ghost badge-sm">years</span>
                      </label>
                    </div>
                  </div>

                  <div class="invite-pet-basic-row-secondary">
                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Breed*</label>
                      <select class="select w-full" name="pets[${index}][breed]" required>
                        <option value="" hidden>Choose breed</option>
                        ${optionHtml(breeds, pet.breed)}
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Weight*</label>
                      <label class="input w-full focus:outline-0">
                        <input class="grow focus:outline-0 pet-weight" data-pet-index="${index}" placeholder="e.g. 10" type="text" name="pets[${index}][weight]" value="${pet.weight || ''}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1');" required />
                        <span class="badge badge-ghost badge-sm">lbs</span>
                      </label>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Size*</label>
                      <select class="select w-full" id="pet_size_${index}" name="pets[${index}][size]" required>
                        <option value="" hidden>Choose size</option>
                        ${optionHtml(weightRanges, pet.size)}
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Color*</label>
                      <select class="select w-full" name="pets[${index}][color]" required>
                        <option value="" hidden>Choose color</option>
                        ${optionHtml(colors, pet.color)}
                      </select>
                    </div>

                    <div class="min-w-0 space-y-2">
                      <label class="fieldset-label">Coat Type*</label>
                      <select class="select w-full" name="pets[${index}][coat_type]" required>
                        <option value="" hidden>Choose coat type</option>
                        ${optionHtml(coatTypes, pet.coat_type)}
                      </select>
                    </div>
                  </div>
                </div>

                <div class="mt-4 space-y-2">
                  <label class="fieldset-label">Notes</label>
                  <textarea class="textarea w-full min-h-20 block" style="display:block;width:100% !important;min-height:80px;" placeholder="Type here" name="pets[${index}][notes]">${pet.notes || ''}</textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
          <div class="xl:col-span-7">
            <div class="card bg-base-100 shadow h-full">
              <div class="card-body">
                <div class="flex items-center justify-between">
                  <div class="card-title">Vaccinations</div>
                  <button type="button" class="btn btn-primary btn-sm" onclick="addVaccinationRow(${index})">
                    <span class="iconify lucide--plus size-3"></span>
                    Add
                  </button>
                </div>
                <div id="vaccinations_container_${index}" class="mt-2 space-y-2 rounded-box bg-base-200/80 p-3">
                  ${vaccinationRowsTemplate(index, pet.vaccinations)}
                </div>
              </div>
            </div>
          </div>

          <div class="xl:col-span-5">
            <div class="space-y-4">
              <div class="card bg-base-100 shadow">
                <div class="card-body">
                  <div class="card-title">Health Certificate</div>
                  <input aria-label="File" class="file-input w-full" type="file" name="pets[${index}][certificate_files][]" multiple />
                </div>
              </div>

              <div class="card bg-base-100 shadow">
                <div class="card-body">
                  <div class="card-title">Veterinarian Information</div>
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                      <label class="fieldset-label">Name/Facility*</label>
                      <input class="input w-full" placeholder="e.g. Animal Hospital" type="text" name="pets[${index}][veterinarian_name]" value="${pet.veterinarian_name || ''}" required />
                    </div>
                    <div class="space-y-2">
                      <label class="fieldset-label">Phone*</label>
                      <input class="input w-full" placeholder="e.g. (123) 456-7890" type="text" name="pets[${index}][veterinarian_phone]" value="${pet.veterinarian_phone || ''}" oninput="formatPhoneNumber(this)" required />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function addPet(prefill = {}, forcedIndex = null) {
    const nextIndex = Number.isInteger(forcedIndex) ? forcedIndex : getNextPetIndex();
    $('#pets_container').append(petTemplate(nextIndex, prefill));
    initializePetFilePond(nextIndex);
  }

  function removePet(index) {
    $(`#pet_form_${index}`).remove();
    if ($('#pets_container').children().length === 0) {
      addPet();
    }
  }

  function getNextVaccinationRowIndex(petIndex) {
    const used = $(`#vaccinations_container_${petIndex} .vaccination-row`).map(function() {
      return Number($(this).data('row-index'));
    }).get().filter((value) => !Number.isNaN(value));

    let next = 0;
    while (used.includes(next)) {
      next += 1;
    }
    return next;
  }

  function addVaccinationRow(petIndex) {
    const rowIndex = getNextVaccinationRowIndex(petIndex);
    $(`#vaccinations_container_${petIndex}`).append(vaccinationRowTemplate(petIndex, rowIndex));
  }

  function removeVaccinationRow(button) {
    const rows = $(button).closest('[id^="vaccinations_container_"]').find('.vaccination-row');
    if (rows.length <= 1) {
      $(button).closest('.vaccination-row').find('input, select').val('');
      return;
    }
    $(button).closest('.vaccination-row').remove();
  }

  function initializeCustomerFilePond() {
    const inputElement = document.querySelector('input[type="file"][data-filepond-customer]');
    if (!inputElement) {
      return;
    }

    FilePond.create(inputElement, {
      acceptedFileTypes: ['image/*'],
      allowImagePreview: true,
      allowImageFilter: false,
      allowImageExifOrientation: false,
      allowImageCrop: false,
      imagePreviewHeight: 170,
      imageCropAspectRatio: '1:1',
      imageResizeTargetWidth: 200,
      imageResizeTargetHeight: 200,
      stylePanelLayout: 'compact',
      styleLoadIndicatorPosition: 'center bottom',
      styleProgressIndicatorPosition: 'right bottom',
      styleButtonRemoveItemPosition: 'left bottom',
      styleButtonProcessItemPosition: 'right bottom',
      server: {
        process: {
          url: '{{ route("process-file-customer-invite") }}',
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          },
          onload: (response) => {
            const result = JSON.parse(response);
            $('#temp_file_customer').val(result.temp_file);
            return result.temp_file;
          }
        },
        revert: {
          url: '{{ route("revert-file-customer-invite") }}',
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        }
      },
      onremovefile: () => {
        $('#temp_file_customer').val('');
      }
    });
  }

  function initializePetFilePond(petIndex) {
    const inputElement = document.querySelector(`#pet_form_${petIndex} input[type="file"][data-filepond-pet]`);
    if (!inputElement) {
      return;
    }

    FilePond.create(inputElement, {
      acceptedFileTypes: ['image/*'],
      allowImagePreview: true,
      allowImageFilter: false,
      allowImageExifOrientation: false,
      allowImageCrop: false,
      imagePreviewHeight: 170,
      imageCropAspectRatio: '1:1',
      imageResizeTargetWidth: 200,
      imageResizeTargetHeight: 200,
      stylePanelLayout: 'compact',
      styleLoadIndicatorPosition: 'center bottom',
      styleProgressIndicatorPosition: 'right bottom',
      styleButtonRemoveItemPosition: 'left bottom',
      styleButtonProcessItemPosition: 'right bottom',
      server: {
        process: {
          url: '{{ route("process-file-pet-invite") }}',
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          },
          onload: (response) => {
            const result = JSON.parse(response);
            $(`#temp_file_pet_${petIndex}`).val(result.temp_file);
            return result.temp_file;
          }
        },
        revert: {
          url: '{{ route("revert-file-pet-invite") }}',
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': csrfToken
          }
        }
      },
      onremovefile: () => {
        $(`#temp_file_pet_${petIndex}`).val('');
      }
    });
  }

  $(document).on('blur', '.pet-weight', function() {
    const petIndex = $(this).data('pet-index');
    const weight = parseFloat($(this).val());

    if (isNaN(weight)) {
      return;
    }

    let selectedSize = '';
    weightRanges.forEach((weightRange) => {
      if (weight > parseFloat(weightRange.min_weight) && weight <= parseFloat(weightRange.max_weight)) {
        selectedSize = String(weightRange.id);
      }
    });

    if (selectedSize) {
      $(`#pet_size_${petIndex}`).val(selectedSize);
    }
  });

  $(document).on('change', '.pet-birth-date', function() {
    const petIndex = $(this).data('pet-index');
    const birthDateStr = $(this).val();

    if (!birthDateStr) {
      $(`#pet_age_${petIndex}`).val('');
      return;
    }

    const birthDate = new Date(birthDateStr);
    if (Number.isNaN(birthDate.getTime())) {
      $(`#pet_age_${petIndex}`).val('');
      return;
    }

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age -= 1;
    }

    $(`#pet_age_${petIndex}`).val(age >= 0 ? age : '');
  });

  $('#invite_registration_form').submit(function() {
    const ownerDatas = [];
    let hasInvalidOwner = false;

    $('#additional_owners').children('div').each(function() {
      const name = ($(this).find('input[id^="owner_name_"]').val() || '').trim();
      const phone = ($(this).find('input[id^="owner_phone_"]').val() || '').trim();

      if ((name && !phone) || (!name && phone)) {
        hasInvalidOwner = true;
        return false;
      }

      if (name && phone) {
        ownerDatas.push({ name: name, phone: phone });
      }
    });

    if (hasInvalidOwner) {
      alert('Please complete both name and phone for each additional owner, or remove the row.');
      return false;
    }

    $('#owners').val(JSON.stringify(ownerDatas));
    return true;
  });

  $(document).ready(function() {
    FilePond.registerPlugin(FilePondPluginImagePreview);
    initializeCustomerFilePond();

    if (Array.isArray(oldPets) && oldPets.length > 0) {
      oldPets.forEach((pet, idx) => addPet(pet, idx));
    } else {
      addPet();
    }
  });
</script>
@endsection
