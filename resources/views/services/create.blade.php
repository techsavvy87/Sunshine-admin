@extends('layouts.main')
@section('title', 'Create Service')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Create Service</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('services') }}">Services</a></li>
      <li class="opacity-80">Create</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('create-service') }}" method="POST" enctype="multipart/form-data" id="create_form">
    @csrf
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Service Image</div>
              <div class="mt-4">
                <input type="file" data-filepond class="uploadFile" name="avatar_img"/>
                <input type="hidden" id="temp_file" name="temp_file" />
              </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-3">
          <div class="card-body">
            <div class="card-title">Icon</div>
              <div class="mt-2">
                <input aria-label="File" class="file-input w-full" type="file" name="service_icon" id="service_icon" accept="image/*"/>
              </div>
          </div>
        </div>
      </div>
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Basic Information</div>
            @php
              $selectedCategoryId = old('category', '');
            @endphp
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 xl:grid-cols-4">
              <div class="xl:col-span-2 space-y-2">
                <label class="fieldset-label" for="service_name">Service Name*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. Groom" id="service_name" name="service_name" type="text" />
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="category">Category*</label>
                <select class="select w-full" name="category" id="category">
                  <option value="" hidden selected>Select a category</option>
                  @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="level">Level*</label>
                <select class="select w-full" name="level" id="level">
                  <option value="" hidden selected>Select a level</option>
                  <option value="primary">Primary</option>
                  <option value="secondary">Secondary</option>
                </select>
              </div>

              <div class="xl:col-span-4 hidden" id="is_multiple_prices_durations">
                <div class="space-y-2">
                  <label class="fieldset-label">Multiple Prices and Durations</label>
                  <div class="flex items-center gap-3">
                    <input class="toggle toggle-sm" id="multi_price_toggle" type="checkbox" name="multi_price_toggle"/>
                    <label class="label" for="multi_price_toggle">Is Multiple?</label>
                  </div>
                </div>
              </div>

              <div class="space-y-2" id="price_standard_group">
                <label class="fieldset-label" for="price" id="base_price_lbl">Base Price*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 100" id="price" name="price" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                  <span class="badge badge-ghost badge-sm">USD</span>
                </label>
              </div>
              <div class="space-y-2 hidden" id="price_mile_group">
                <label class="fieldset-label" for="price_per_mile">Price per Mile*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 100" id="price_per_mile" name="price_per_mile" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                  <span class="badge badge-ghost badge-sm">USD</span>
                </label>
              </div>
              <div id="price_multi_group" class="xl:col-span-4 hidden space-y-2">
                <label class="fieldset-label" for="price">Price - based on pet size*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-4">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Small</span>
                    <input data-multi-price class="grow focus:outline-0" placeholder="e.g. 50" id="price_small" name="price_small" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Medium</span>
                    <input data-multi-price class="grow focus:outline-0" placeholder="e.g. 75" id="price_medium" name="price_medium" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Large</span>
                    <input data-multi-price class="grow focus:outline-0" placeholder="e.g. 100" id="price_large" name="price_large" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">X-Large</span>
                    <input data-multi-price class="grow focus:outline-0" placeholder="e.g. 125" id="price_xlarge" name="price_xlarge" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div id="price_daycare_group" class="xl:col-span-2 hidden space-y-2">
                <label class="fieldset-label" for="duration">Daycare Price*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-1">
                  <label class="input w-full focus:outline-0">
                    <input data-daycare-price class="grow focus:outline-0" placeholder="e.g. 40" id="price_daycare" name="price_daycare" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div id="price_training_group" class="xl:col-span-4 hidden space-y-2">
                <label class="fieldset-label">Price*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-3">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Half Hour</span>
                    <input class="grow focus :outline-0" placeholder="e.g. 50" id="price_half_training" name="price_half_training" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">One Hour</span>
                    <input class="grow focus :outline-0" placeholder="e.g. 90" id="price_one_training" name="price_one_training" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Travel Charge</span>
                    <input class="grow focus :outline-0" placeholder="e.g. 90" id="price_travel_training" name="price_travel_training" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div class="space-y-2" id="duration_standard_group">
                <label class="fieldset-label" for="duration" id="default_duration_lbl">Default Duration*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 1.5" id="duration" name="duration" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                  <span class="badge badge-ghost badge-sm">hrs</span>
                </label>
              </div>
              <div id="duration_multi_group" class="xl:col-span-4 hidden space-y-2">
                <label class="fieldset-label" for="duration">Duration - based on pet size*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-4">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Small</span>
                    <input data-multi-duration class="grow focus:outline-0" placeholder="e.g. 1.0" id="duration_small" name="duration_small" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Medium</span>
                    <input data-multi-duration class="grow focus:outline-0" placeholder="e.g. 1.5" id="duration_medium" name="duration_medium" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Large</span>
                    <input data-multi-duration class="grow focus:outline-0" placeholder="e.g. 2.0" id="duration_large" name="duration_large" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">X-Large</span>
                    <input data-multi-duration class="grow focus:outline-0" placeholder="e.g. 2.5" id="duration_xlarge" name="duration_xlarge" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                </div>
              </div>
              <div id="duration_daycare_group" class="xl:col-span-2 hidden space-y-2">
                <label class="fieldset-label" for="duration">Daycare Duration*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-1">
                  <label class="input w-full focus:outline-0">
                    <input data-daycare-duration class="grow focus:outline-0" placeholder="e.g. 8" id="duration_daycare" name="duration_daycare" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                </div>
              </div>
              <div class="xl:col-span-4 hidden mt-2" id="coat_type_price_group">
                <div class="space-y-2">
                  <label class="fieldset-label">Coat Type</label>
                  <div class="flex items-end gap-4 flex-wrap">
                    <div class="flex items-center gap-3 pb-2">
                      <input class="toggle toggle-sm" id="coat_type_price" type="checkbox" name="coat_type_price"/>
                      <label class="label" for="coat_type_price">Is Active</label>
                    </div>
                    <div class="w-full max-w-xs space-y-2">
                      <label class="input w-full focus:outline-0">
                        <input class="grow focus:outline-0" placeholder="e.g. 15" id="coat_type_price_value" name="coat_type_price_value" type="text" disabled oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                        <span class="badge badge-ghost badge-sm">USD</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="xl:col-span-4">
                <div class="space-y-2">
                  <label class="fieldset-label" for="status">Status</label>
                  <div class="flex items-center">
                    <div class="flex items-center gap-3">
                      <input class="toggle toggle-sm" id="status" type="checkbox" name="status"/>
                      <label class="label" for="status">Is Active</label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="xl:col-span-4">
                <div class="space-y-2">
                  <label class="fieldset-label" for="notes">Description</label>
                  <textarea placeholder="Type here" class="textarea w-full" name="description"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ route('services') }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      <button class="btn btn-sm btn-primary" type="button" onclick="saveService()">
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

  <script src="https://cdn.tiny.cloud/1/rhlw8jgc0ksvm4s3f68bs8wm8pwd45zggs1jjp9x5coyapfy/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>

  <script>

    try {
      const localStorageItem = localStorage.getItem("__NEXUS_CONFIG_v2.0__")
      if (localStorageItem) {
        const theme = JSON.parse(localStorageItem).theme
        initTinyMCE(theme);
      }
    } catch (err) {
      console.log(err)
    }

    $(document).on('click', '[data-theme-control]', function() {
      const selectedTheme = $(this).attr('data-theme-control');
      document.documentElement.setAttribute('data-theme', selectedTheme);
      localStorage.setItem("__NEXUS_CONFIG_v2.0__", JSON.stringify({ theme: selectedTheme }));

      initTinyMCE(selectedTheme); // Re-initialize TinyMCE with new theme
    });

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
          url: '{{ route("process-file-service") }}',
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
          url: '{{ route("revert-file-service") }}',
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

    // Tiny MCE
    function initTinyMCE(theme) {
      tinymce.remove();
      tinymce.init({
        selector: 'textarea',
        height: 280,
        skin: theme === 'dark' ? 'oxide-dark' : 'oxide',
        content_css: theme === 'dark' ? 'dark' : 'default',
        mobile: {
          menubar: true
        },
        plugins: [
          // Core editing features
          'anchor', 'autolink', 'charmap', 'codesample', 'emoticons', 'link', 'lists', 'media', 'searchreplace', 'table', 'visualblocks', 'wordcount',
          // Your account includes a free trial of TinyMCE premium features
          // Try the most popular premium features until Sep 10, 2025:
          // 'checklist', 'mediaembed', 'casechange', 'formatpainter', 'pageembed', 'a11ychecker', 'tinymcespellchecker', 'permanentpen', 'powerpaste', 'advtable', 'advcode', 'advtemplate', 'ai', 'uploadcare', 'mentions', 'tinycomments', 'tableofcontents', 'footnotes', 'mergetags', 'autocorrect', 'typography', 'inlinecss', 'markdown','importword', 'exportword', 'exportpdf'
        ],
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography uploadcare | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
        tinycomments_mode: 'embedded',
        tinycomments_author: 'Author name',
        mergetags_list: [
          { value: 'First.Name', title: 'First Name' },
          { value: 'Email', title: 'Email' },
        ],
        ai_request: (request, respondWith) => respondWith.string(() => Promise.reject('See docs to implement AI Assistant')),
        uploadcare_public_key: '81113b3e8385e17a0d76',
      });
    }

    function initializeFields() {
      $('#is_multiple_prices_durations').addClass('hidden');
      $('#multi_price_toggle').prop('checked', false);

      $('#duration_standard_group').addClass('hidden');
      $('#price_standard_group').addClass('hidden');

      $('#price_multi_group').addClass('hidden');
      $('#duration_multi_group').addClass('hidden');

      $('#duration_daycare_group').addClass('hidden');
      $('#price_daycare_group').addClass('hidden');

      $('#price_training_group').addClass('hidden');

      $('#base_price_lbl').text('Base Price*');
      $('#default_duration_lbl').text('Default Duration*');

      $('#price_mile_group').addClass('hidden');

      $('#coat_type_price_group').addClass('hidden');
      $('#coat_type_price').prop('checked', false);
      $('#coat_type_price_value').prop('disabled', true).val('');
    }

    $(document).ready(function() {

      $('#coat_type_price').on('change', function() {
        const isCoatTypePriceEnabled = $('#coat_type_price').is(':checked');
        $('#coat_type_price_value').prop('disabled', !isCoatTypePriceEnabled);

        if (!isCoatTypePriceEnabled) {
          $('#coat_type_price_value').val('');
        }
      });

      $('#multi_price_toggle').on('change', function() {
        const isMultiPrice = $('#multi_price_toggle').is(':checked');

        if (isMultiPrice) {
          $('#price_standard_group').addClass('hidden');
          $('#price_multi_group').removeClass('hidden');
          $('#duration_standard_group').addClass('hidden');
          $('#duration_multi_group').removeClass('hidden');
        } else {
          $('#price_standard_group').removeClass('hidden');
          $('#price_multi_group').addClass('hidden');
          $('#duration_standard_group').removeClass('hidden');
          $('#duration_multi_group').addClass('hidden');
        }
      });

      $('#category').on('change', function() {
        const selectedCategory = $(this).find('option:selected').text();
        initializeFields();

        if (selectedCategory.toLowerCase().includes('grooming')) {
          $('#is_multiple_prices_durations').removeClass('hidden');
          $('#duration_standard_group').removeClass('hidden');
          $('#price_standard_group').removeClass('hidden');
          $('#coat_type_price_group').removeClass('hidden');
        } else if (selectedCategory.toLowerCase().includes('daycare')) {
          $('#duration_daycare_group').removeClass('hidden');
          $('#price_daycare_group').removeClass('hidden');
        } else if (selectedCategory.toLowerCase().includes('training')) {
          $('#price_training_group').removeClass('hidden');
        } else if (selectedCategory.toLowerCase().includes('group') || selectedCategory.toLowerCase().includes('carte') || selectedCategory.toLowerCase().includes('package')) {
          $('#duration_standard_group').removeClass('hidden');
          $('#price_standard_group').removeClass('hidden');
          $('#base_price_lbl').text('Base Price');
          $('#default_duration_lbl').text('Default Duration');
        } else if (selectedCategory.toLowerCase().includes('boarding')) {
          $('#duration_standard_group').removeClass('hidden');
          $('#price_standard_group').removeClass('hidden');
          $('#base_price_lbl').text('Base Price*');
          $('#default_duration_lbl').text('Default Duration*');
        } else if (selectedCategory.toLowerCase().includes('chauffeur')) {
          $('#price_mile_group').removeClass('hidden');
        }
      });
    });

    function saveService() {
      const serviceName = $('#service_name').val();
      const category = $('#category').val();
      const level = $('#level').val();

      if (!serviceName || !category || !level) {
        $('#alert_message').text('Please fill in all required fields.');
        alert_modal.showModal();
        return;
      }

      const categoryName = $('#category').find('option:selected').text().toLowerCase();

      if (categoryName.includes('grooming')) {
        const isMultiPrice = $('#multi_price_toggle').is(':checked');
        if (isMultiPrice) {
          const priceSmall = $('#price_small').val();
          const priceMedium = $('#price_medium').val();
          const priceLarge = $('#price_large').val();
          const priceXLarge = $('#price_xlarge').val();
          const durationSmall = $('#duration_small').val();
          const durationMedium = $('#duration_medium').val();
          const durationLarge = $('#duration_large').val();
          const durationXLarge = $('#duration_xlarge').val();

          if (!priceSmall || !priceMedium || !priceLarge || !priceXLarge || !durationSmall || !durationMedium || !durationLarge || !durationXLarge) {
            $('#alert_message').text('Please fill in all price and duration fields for pet sizes.');
            alert_modal.showModal();
            return;
          }
        } else {
          const price = $('#price').val();
          const duration = $('#duration').val();
          if (!price || !duration) {
            $('#alert_message').text('Please fill in the base price and duration.');
            alert_modal.showModal();
            return;
          }
        }
      } else if (categoryName.includes('daycare')) {
        const priceDaycare = $('#price_daycare').val();
        const durationDaycare = $('#duration_daycare').val();

        if (!priceDaycare || !durationDaycare) {
          $('#alert_message').text('Please fill in daycare price and duration fields.');
          alert_modal.showModal();
          return;
        }
      } else if (categoryName.includes('training')) {
        const priceHalfTraining = $('#price_half_training').val();
        const priceOneTraining = $('#price_one_training').val();
        const priceTravelTraining = $('#price_travel_training').val();

        if (!priceHalfTraining || !priceOneTraining || !priceTravelTraining) {
          $('#alert_message').text('Please fill in all private training price fields.');
          alert_modal.showModal();
          return;
        }
      } else if (categoryName.includes('boarding')) {
        const price = $('#price').val();
        const duration = $('#duration').val();
        if (!price || !duration) {
          $('#alert_message').text('Please fill in the base price and duration.');
          alert_modal.showModal();
          return;
        }
      } else if (categoryName.includes('chauffeur')) {
        const pricePerMile = $('#price_per_mile').val();
        if (!pricePerMile) {
          $('#alert_message').text('Please fill in the price per mile.');
          alert_modal.showModal();
          return;
        }
      }

      const isCoatTypePriceEnabled = $('#coat_type_price').is(':checked');
      const coatTypePriceValue = $('#coat_type_price_value').val();

      if (isCoatTypePriceEnabled && !coatTypePriceValue) {
        $('#alert_message').text('Please enter the coat type price.');
        alert_modal.showModal();
        return;
      }

      $('#create_form').submit();
    }
  </script>
@endsection