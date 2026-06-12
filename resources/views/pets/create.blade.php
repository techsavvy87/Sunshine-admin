@extends('layouts.main')
@section('title', 'Create Pet')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
  <style>
    .fa-star {
      cursor: pointer;
      font-size: 20px;
    }
    .select2-container {
      width: 100% !important;
    }

    .select2-dropdown {
      z-index: 9999;
    }
  </style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Create Pet</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('pets') }}">Pets</a></li>
      <li class="opacity-80">Create</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('create-pet') }}" method="POST" enctype="multipart/form-data" id="create_form">
    @csrf
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Pet Image</div>
              <div class="mt-4">
                <input type="file" data-filepond class="uploadFile" name="pet_img"/>
                <input type="hidden" id="temp_file" name="temp_file" />
              </div>
          </div>
        </div>
      </div>
      <div class="xl:col-span-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Owner Information</div>
            <div class="fieldset mt-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="owner_name">Primary Owner*</label>
                <select class="select w-full" name="owner" id="owner" style="height: 60px">
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">
              <span>Rating: </span>
              <div class="flex items-center gap-3">
                <i class="fa-regular fa-star rating-green" style="color: lightseagreen"></i>
                <i class="fa-regular fa-star rating-yellow" style="color: darkorange"></i>
                <i class="fa-regular fa-star rating-red" style="color: red"></i>
              </div>
            </div>
            <input type="hidden" id="rating" name="rating" />
            <div class="fieldset mt-1">
              <textarea placeholder="Rating Notes Here" class="textarea w-full" name="rating_notes"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="grid grid-cols-1 mt-5">
      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="card-title">Basic Information</div>
          <div class="fieldset mt-2 grid grid-cols-1 gap-4 xl:grid-cols-5">
            <div class="space-y-2">
              <label class="fieldset-label" for="pet_name">Pet Name*</label>
              <label class="input w-full focus:outline-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dog-icon lucide-dog text-base-content/80"><path d="M11.25 16.25h1.5L12 17z"/><path d="M16 14v.5"/><path d="M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309"/><path d="M8 14v.5"/><path d="M8.5 8.5c-.384 1.05-1.083 2.028-2.344 2.5-1.931.722-3.576-.297-3.656-1-.113-.994 1.177-6.53 4-7 1.923-.321 3.651.845 3.651 2.235A7.497 7.497 0 0 1 14 5.277c0-1.39 1.844-2.598 3.767-2.277 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5"/></svg>
                <input class="grow focus:outline-0" placeholder="e.g. Fluffy" id="pet_name" name="pet_name" type="text" />
              </label>
            </div>
            <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="sex">Sex*</label>
                <select class="select w-full" name="sex" id="sex">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="type">Type*</label>
                <select class="select w-full" name="type" id="type">
                  <option value="Dog">Dog</option>
                  <option value="Cat">Cat</option>
                </select>
              </div>
            </div>
            <div class="space-y-2">
              <label class="fieldset-label" for="spay_neuter">Spay/Neuter</label>
              <select class="select w-full" name="spay_neuter" id="spay_neuter">
                <option value="" selected disabled hidden></option>
                <option value="spayed">Spayed</option>
                <option value="neutered">Neutered</option>
                <option value="intact">Intact</option>
              </select>
            </div>
            <div class="space-y-2">
              <input type="hidden" id="birth_date" name="birth_date" />
              <label class="fieldset-label" for="birthdate">Birth Date*</label>
              <div class="dropdown w-full">
                <div role="button" class="btn btn-outline border-base-300 flex items-center gap-2" tabindex="0">
                  <span class="iconify lucide--calendar text-base-content/80 size-3.5"></span>
                  <p class="text-start" id="button_cally_target">-</p>
                  <span class="iconify lucide--chevron-down text-base-content/70 size-4"></span>
                </div>
                <div class="dropdown-content mt-2" tabindex="0">
                  <calendar-date class="cally bg-base-100 rounded-box shadow-md transition-all hover:shadow-lg" id="button_cally_element" value="-" >
                    <span class="iconify lucide--chevron-left" slot="previous"></span>
                    <span class="iconify lucide--chevron-right" slot="next"></span>
                    <calendar-month></calendar-month>
                  </calendar-date>
                </div>
              </div>
            </div>
            <div class="space-y-2">
              <label class="fieldset-label" for="age">Age</label>
              <label class="input w-full focus:outline-0">
                <input class="grow focus:outline-0" placeholder="e.g. 2" id="age" name="age" type="text" oninput="this.value = this.value.replace(/[^0-9]/g, '')"/>
                <span class="badge badge-ghost badge-sm">years</span>
              </label>
            </div>
          </div>
          <div class="fieldset mt-4 grid grid-cols-1 gap-4 xl:grid-cols-4">
            <div class="space-y-2">
              <label class="fieldset-label" for="breed">Breed*</label>
              <select class="select w-full" name="breed" id="breed" style="height: 60px">
              </select>
            </div>
            <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="weight">Weight*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 10" id="weight" name="weight" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                  <span class="badge badge-ghost badge-sm">lbs</span>
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="size">Size*</label>
                <select class="select w-full" name="size" id="size">
                  <option value="" hidden>Choose size</option>
                  @foreach($weightRanges as $weightRange)
                    <option value="{{ $weightRange->id }}">{{ $weightRange->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="space-y-2">
              <label class="fieldset-label" for="color">Color*</label>
              <select class="select w-full" name="color" id="color" style="height: 60px">
              </select>
            </div>
            <div class="space-y-2">
              <label class="fieldset-label" for="coat_type">Coat Type*</label>
              <select class="select w-full" name="coat_type" id="coat_type" style="height: 60px">
              </select>
            </div>
            <div class="xl:col-span-4">
              <div class="space-y-2">
                <label class="fieldset-label" for="notes">Notes</label>
                <textarea placeholder="Type here" class="textarea w-full" name="notes"></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    @php
      $vaccinationTypeOptions = [
          'Leptospirosis',
          'Rabies',
          'FVRCP',
          'Bordetella',
          'DHPP',
          'Annual Exam',
          'Annual Heartworm',
          'C5 Canine Vaccine',
          'Canine Coronavirus (CCoV)',
          'Canine Distemper',
          'Canine Hepatitis',
          'Canine Influenza',
          'Canine Parvovirus',
          'Crotalid',
          'Fecal Test',
          'Flea Prevention Medication',
          'Lyme',
          'Monthly Parasite Prevention',
      ];
    @endphp
    <input type="hidden" id="vaccinations" name="vaccinations" />
    <div class="grid grid-cols-1 mt-5 gap-5 xl:grid-cols-5">
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="flex items-center justify-between mb-2">
              <span class="text-md font-bold">Vaccinations</span>
              <button type="button" class="btn btn-primary btn-sm" onclick="addVaccinationRow()">
                <span class="iconify lucide--plus size-4"></span>
                Add
              </button>
            </div>
            <fieldset class="fieldset bg-base-300 border-base-300 rounded-box border p-4">
              <div class="fieldset space-y-2" id="vaccinations_container"></div>
            </fieldset>
          </div>
        </div>
      </div>
      <div class="xl:col-span-2">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Health Certificate</div>
            <div class="mt-2">
              <input aria-label="File" class="file-input w-full" type="file" name="certificate_files[]" multiple id="certificate_files"/>
            </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-2">
          <div class="card-body">
            <div class="card-title">Vaccine Status*</div>
            <div class="mt-2">
              <select class="select w-full" name="vaccine_status" id="vaccine_status">
                <option value="" hidden selected>Choose Vaccine Status</option>
                <option value="missing">Missing</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="declined">Declined</option>
              </select>
            </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-5">
          <div class="card-body">
            <div class="card-title">Veterinarian Information</div>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="veterinarian_name">Name/Facility*</label>
                <input class="input w-full" id="veterinarian_name" placeholder="e.g. Animal Hospital" type="text" name="veterinarian_name"/>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="veterinarian_phone">Phone*</label>
                <input class="input w-full" id="veterinarian_phone" placeholder="e.g. (123) 456-7890" type="text" name="veterinarian_phone" oninput="formatPhoneNumber(this)"/>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ url()->previous() }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      <button class="btn btn-sm btn-primary" type="button" onclick="savePet()">
        <span class="iconify lucide--check size-4"></span>
        Save
      </button>
    </div>
  </form>
</div>
@endsection

@section('page-js')
  <script src="{{ asset('src/libs/filepond/filepond.min.js') }}"></script>
  <script src="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.js') }}"></script>
  <script src="{{ asset('src/assets/ui-components-calendar.js') }}"></script>
  <script type="module" src="https://unpkg.com/cally"></script>

  <script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>

  <script>
    const vaccinationTypeOptions = @json($vaccinationTypeOptions);
    const vaccinationRemoveActiveColor = '#f31260';
    const vaccinationRemoveDisabledColor = '#b3b8c3';
    let vaccinationRowCounter = 0;

    function buildVaccinationOptions(selectedType = '', excludedTypes = []) {
      const excludedSet = new Set(
        excludedTypes
          .map((type) => (type || '').trim())
          .filter((type) => type !== '' && type !== selectedType)
      );

      return vaccinationTypeOptions
        .filter((option) => !excludedSet.has(option) || option === selectedType)
        .map((option) => {
          const selected = option === selectedType ? 'selected' : '';
          return `<option value="${option}" ${selected}>${option}</option>`;
        })
        .join('');
    }

    function bindVaccinationSelectChangeHandler() {
      $('#vaccinations_container .vaccination-type-select')
        .off('change.uniqueVaccination')
        .on('change.uniqueVaccination', function() {
          $(this).select2('close');
          refreshVaccinationDropdowns();
        });
    }

    function refreshVaccinationDropdowns() {
      const rows = [];
      $('#vaccinations_container .vaccination-row').each(function() {
        rows.push({
          rowId: $(this).attr('id'),
          selectedType: (($(this).find('.vaccination-type-select').val() || '') + '').trim(),
        });
      });

      rows.forEach((row) => {
        const excludedTypes = rows
          .filter((item) => item.rowId !== row.rowId)
          .map((item) => item.selectedType)
          .filter((type) => type !== '');

        const $row = $('#' + row.rowId);
        const $select = $row.find('.vaccination-type-select');

        if ($select.hasClass('select2-hidden-accessible')) {
          $select.select2('destroy');
        }

        $select.html(`<option value=""></option>${buildVaccinationOptions(row.selectedType, excludedTypes)}`);
        $select.val(row.selectedType);
        initVaccinationRowSelect2($row);
      });

      bindVaccinationSelectChangeHandler();
    }

    function initVaccinationRowSelect2(row) {
      row.find('.vaccination-type-select').select2({
        placeholder: 'Select vaccination',
        allowClear: true,
        width: '100%',
        dropdownParent: row.closest('.card-body')
      });
    }

    function addVaccinationRow(vaccination = {}) {
      vaccinationRowCounter += 1;
      const rowId = `vaccination_row_${vaccinationRowCounter}`;
      const selectedType = vaccination.type || '';
      const date = vaccination.date || '';
      const months = vaccination.months || '';

      const rowHtml = `
        <div class="grid grid-cols-1 gap-3 xl:grid-cols-12 vaccination-row" id="${rowId}">
          <input type="hidden" class="vaccination-id" value="">
          <div class="xl:col-span-5">
            <select class="select w-full vaccination-type-select">
              <option value=""></option>
              ${buildVaccinationOptions(selectedType)}
            </select>
          </div>
          <div class="xl:col-span-4">
            <input class="input w-full vaccination-date" placeholder="e.g. 2023-01-01" type="date" value="${date}" />
          </div>
          <div class="xl:col-span-2">
            <input class="input w-full vaccination-months" placeholder="Months" type="text" value="${months}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
          </div>
          <div class="xl:col-span-1 flex items-center justify-end gap-1">
            <button type="button" class="btn btn-ghost btn-sm p-1 btn-remove-vaccination" title="Remove vaccination" onclick="removeVaccinationRow(this)">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f31260" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-minus-icon lucide-minus"><path d="M5 12h14"/></svg>
            </button>
          </div>
        </div>
      `;

      const $row = $(rowHtml);
      $('#vaccinations_container').append($row);
      initVaccinationRowSelect2($row);
      updateVaccinationRemoveButtons();
      refreshVaccinationDropdowns();
    }

    function removeVaccinationRow(button) {
      const $row = $(button).closest('.vaccination-row');
      const $select = $row.find('.vaccination-type-select');
      if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
      }
      $row.remove();
      updateVaccinationRemoveButtons();
      refreshVaccinationDropdowns();
    }

    function updateVaccinationRemoveButtons() {
      $('#vaccinations_container .btn-remove-vaccination').each(function() {
        $(this)
          .find('svg')
          .attr('stroke', vaccinationRemoveActiveColor);
      });
    }

    document.getElementById("button_cally_element")?.addEventListener("change", (e) => {
      document.getElementById("button_cally_target").innerText = e.target.value;

      // Calculate age in years
      const birthDateStr = e.target.value;
      if (birthDateStr && birthDateStr !== '-') {
        const birthDate = new Date(birthDateStr);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
          age--;
        }
        document.getElementById("age").value = age >= 0 ? age : '';
      } else {
        document.getElementById("age").value = '';
      }
    })

    // Register FilePond plugins
    FilePond.registerPlugin(FilePondPluginImagePreview);

    // Create a FilePond instance
    const inputElement = document.querySelector('input[type="file"][data-filepond]');
    const pond = FilePond.create(inputElement, {
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
      beforeAddFile: (item) => {
        // Additional validation if needed
        const file = item.file;

        if (file.size > 1024 * 1024 * 2) { // 2MB
          $('#alert_message').text('The size of image should be smaller than 2M.');
          alert_modal.showModal();
          return false; // Prevent file from being added
        }

        if (!file.type.startsWith('image/')) {
          $('#alert_message').text('Uploaded file must be an image.');
          alert_modal.showModal();
          return false; // Prevent file from being added
        }

        return true; // Allow file to be added
      },
      server: {
        process: {
          url: '{{ route("process-file-pet") }}',
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          onload: (response) => {
            // Handle successful upload response
            const result = JSON.parse(response);
            $('#temp_file').val(result.temp_file); // Store the file path in a hidden input
            return result.temp_file; // Return the file path to FilePond
          },
          onerror: (response) => {
            $('#alert_message').text('Error uploading image.');
            alert_modal.showModal();
            return '';
          }
        },
        revert: {
          url: '{{ route("revert-file-pet") }}',
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          }
        }
      },

      onprocessfile: (error, file) => {
        if (error) {
          console.error('Error processing file:', error);
          $('#alert_message').text('Error processing image.');
          alert_modal.showModal();
        } else {
          console.log('File processed successfully:', file);
        }
      },

      onremovefile: (error, file) => {
        if (error) {
          console.error('Error removing file:', error);
          $('#alert_message').text('Error removing image.');
          alert_modal.showModal();
        } else {
          console.log('File removed successfully:', file);
          $('#temp_file').val(''); // Clear the hidden input when file is removed
        }
      }
    });

    $(document).ready(function() {

      $('#spay_neuter').select2({
        placeholder: "Select status",
        allowClear: true,
        minimumResultsForSearch: Infinity
      });
      
      $('.fa-star').click(function() {
        const isSolid = $(this).hasClass('fa-solid');
        const ratingType = $(this).hasClass('rating-green') ? 'green' : $(this).hasClass('rating-yellow') ? 'yellow' : 'red';
        // Reset all stars of the same type
        $('.fa-star').removeClass('fa-solid').addClass('fa-regular');
        // Toggle the clicked star
        if (isSolid) {
          $(this).removeClass('fa-solid').addClass('fa-regular');
          $('#rating').val('');
        } else {
          $(this).removeClass('fa-regular').addClass('fa-solid');
          $('#rating').val(ratingType);
        }
      });

      $('#owner').select2({
        placeholder: "Select an owner",
        ajax: {
          url: '{{ route("get-pet-owners") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term, // Send the search term as 'q'
              page: params.page || 1
            };
          },
          processResults: function (data) {
            return {
              results: data.items.map(function (owner) {
                return {
                  id: owner.id,
                  first_name: owner.profile.first_name,
                  last_name: owner.profile.last_name,
                  email: owner.email,
                  phone_number: owner.profile.phone_number_1
                };
              }),
              pagination: {
                more: data.has_more // true if more pages are available
              }
            };
          }
        },
        templateResult: function (owner) {
          if (!owner.id) {
            return owner.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${owner.first_name} ${owner.last_name}</span>
              <span class="text-sm text-base-content/70">(${owner.email} | ${owner.phone_number})</span>
            </div>
          `);
          return $container;
        },
        templateSelection: function (owner) {
          if (!owner.id) {
            return owner.text;
          }
          var $container = $(`
            <div class="flex items-center gap-2">
              <span class="font-medium">${owner.first_name} ${owner.last_name}</span>
              <span class="text-sm text-base-content/70">(${owner.email} | ${owner.phone_number})</span>
            </div>
          `);
          return $container;
        }
      });

      $('#breed').select2({
        ajax: {
          url: '{{ route("get-pet-breeds") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term, // Send the search term as 'q'
              page: params.page || 1
            };
          },
          processResults: function (data, params) {
            return {
              results: data.items.map(function (breed) {
                return {
                  id: breed.id,
                  text: breed.name
                };
              }),
              pagination: {
                more: data.has_more // true if more pages are available
              }
            };
          }
        },
      });

      $('#color').select2({
        ajax: {
          url: '{{ route("get-pet-colors") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term, // Send the search term as 'q'
              page: params.page || 1
            };
          },
          processResults: function (data, params) {
            return {
              results: data.items.map(function (color) {
                return {
                  id: color.id,
                  text: color.name
                };
              }),
              pagination: {
                more: data.has_more // true if more pages are available
              }
            };
          }
        },
      });

      $('#coat_type').select2({
        ajax: {
          url: '{{ route("get-pet-coat-types") }}',
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return {
              q: params.term, // Send the search term as 'q'
              page: params.page || 1
            };
          },
          processResults: function (data, params) {
            return {
              results: data.items.map(function (coatType) {
                return {
                  id: coatType.id,
                  text: coatType.name
                };
              }),
              pagination: {
                more: data.has_more // true if more pages are available
              }
            };
          }
        },
      });

      $('#weight').blur(function() {
        const weight = parseFloat($(this).val());
        if (!isNaN(weight)) {
          let selectedSize = '';
          @foreach($weightRanges as $weightRange)
            if (weight > {{ $weightRange->min_weight }} && weight <= {{ $weightRange->max_weight }}) {
              selectedSize = '{{ $weightRange->id }}';
            }
          @endforeach
          $('#size').val(selectedSize).trigger('change');
        }
      });

      addVaccinationRow();
    });

    function savePet() {
      const petName = $('#pet_name').val();
      const sex = $('#sex').val();
      const birthDate = $('#button_cally_target').text();
      const age = $('#age').val();
      const breed = $('#breed').val();
      const size = $('#size').val();
      const weight = $('#weight').val();
      const color = $('#color').val();
      const coatType = $('#coat_type').val();
      const owner = $('#owner').val();
      const veterinarianName = $('#veterinarian_name').val();
      const veterinarianPhone = $('#veterinarian_phone').val();

      if (!petName || !sex || !breed || !weight || !color || !coatType || !owner || !veterinarianName || !veterinarianPhone) {
        $('#alert_message').text('Please fill in all required fields.');
        alert_modal.showModal();
        return;
      }

      // validate if birthDate and age is empty at the same time
      if (birthDate === '-') {
        $('#alert_message').text('Please fill in Birth Date field.');
        alert_modal.showModal();
        return;
      }

      let hasIncompleteVaccination = false;
      const vaccinationData = [];
      $('#vaccinations_container .vaccination-row').each(function() {
        const type = ($(this).find('.vaccination-type-select').val() || '').trim();
        const date = ($(this).find('.vaccination-date').val() || '').trim();
        const months = ($(this).find('.vaccination-months').val() || '').trim();
        const hasAnyField = Boolean(type || date || months);

        if (!hasAnyField) {
          return;
        }

        if (!type || !date || !months) {
          hasIncompleteVaccination = true;
          return false;
        }

        vaccinationData.push({ type: type, date: date, months: months });
      });

      if (hasIncompleteVaccination) {
        $('#alert_message').text('Please complete vaccination name, date, and month for each row or remove it.');
        alert_modal.showModal();
        return;
      }

      $('#vaccinations').val(JSON.stringify(vaccinationData));

      if (birthDate) {
        $('#birth_date').val(birthDate);
      }

      $('#create_form').submit();
    }
  </script>
@endsection