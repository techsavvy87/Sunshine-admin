@extends('layouts.main')
@section('title', 'Update Service')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Update Service</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('services') }}">Services</a></li>
      <li class="opacity-80">Update</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('update-service') }}" method="POST" enctype="multipart/form-data" id="update_form">
    @csrf
    <input type="hidden" name="service_id" value="{{ $service->id }}">
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Service Image</div>
            <div class="mt-4">
              <input type="file" data-filepond class="uploadFile" name="avatar_img"/>
              <input type="hidden" id="temp_file" name="temp_file" />
              <input type="hidden" id="avatar_action" name="avatar_action" value="keep" />
              <input type="hidden" id="current_avatar" name="current_avatar" value="{{ $service->avatar_img ?? '' }}" />
            </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-3">
          <div class="card-body">
            <div class="card-title">Icon</div>
            <div class="mt-2">
              <input aria-label="File" class="file-input w-full" type="file" name="service_icon" id="service_icon" accept="image/*"/>
            </div>
            <input type="hidden" name="icon_action" id="icon_action" value="keep">
            @if ($service->icon)
            <div class="flex items-end justify-center gap-1 pt-3" id="icon_area">
              <img src="{{ asset('storage/services/' . $service->icon) }}" alt="Service Icon" class="w-12 h-12 object-cover"/>
              <button class="btn btn-ghost gb-blur-background-image btn-sm ml-auto" title="Delete" type="button" onclick="deleteIcon()">
                <span class="text-error">Remove</span>
              </button>
            </div>
            @endif
          </div>
        </div>
      </div>
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Basic Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 xl:grid-cols-4">
              <div class="xl:col-span-2 space-y-2">
                <label class="fieldset-label" for="service_name">Service Name*</label>
                <input class="input w-full" placeholder="e.g. Groom" id="service_name" name="service_name" type="text" value="{{ $service->name }}"/>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="category">Category*</label>
                <select class="select w-full" name="category" id="category" value="{{ $service->category_id }}">
                  <option value="" hidden selected>Select a category</option>
                  @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ $category->id == $service->category_id ? 'selected' : '' }}>{{ $category->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="level">Level*</label>
                <select class="select w-full" name="level" id="level" value="{{ $service->level }}">
                  <option value="" hidden selected>Select a level</option>
                  <option value="primary" {{ $service->level == 'primary' ? 'selected' : '' }}>Primary</option>
                  <option value="secondary" {{ $service->level == 'secondary' ? 'selected' : '' }}>Secondary</option>
                </select>
              </div>
              <div class="xl:col-span-4 {{ isGroomingService($service) ? '' : 'hidden' }}" id="is_multiple_prices_durations">
                <div class="space-y-2">
                  <label class="fieldset-label">Multiple Prices and Durations</label>
                  <div class="flex items-center gap-3">
                    <input class="toggle toggle-sm" id="multi_price_toggle" type="checkbox" name="multi_price_toggle" {{ $service->price ? '' : 'checked' }}/>
                    <label class="label" for="multi_price_toggle">Is Multiple?</label>
                  </div>
                </div>
              </div>

              <div id="price_standard_group" class="space-y-2 {{ ((isGroomingService($service) && $service->price) || isGroupClassService($service) || isAlaCarteService($service) || isBoardingService($service) || isPackageService($service)) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="price" id="base_price_lbl">{{ (isGroupClassService($service) || isAlaCarteService($service) || isPackageService($service)) ? 'Base Price' : 'Base Price*' }}</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 100" id="price" name="price" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" value="{{ $service->price }}"/>
                  <span class="badge badge-ghost badge-sm">USD</span>
                </label>
              </div>
              <div id="price_mile_group" class="space-y-2 {{ isChauffeurService($service) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="price_per_mile">Price per Mile*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 100" id="price_per_mile" name="price_per_mile" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" value="{{ $service->price_per_mile }}"/>
                  <span class="badge badge-ghost badge-sm">USD</span>
                </label>
              </div>
              <div id="price_multi_group" class="xl:col-span-4 space-y-2 {{ (isGroomingService($service) && !$service->price) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="price">Price - based on pet size*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-4">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Small</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 50" id="price_small" name="price_small" type="text" value="{{ $service->price_small }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Medium</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 75" id="price_medium" name="price_medium" type="text" value="{{ $service->price_medium }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Large</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 100" id="price_large" name="price_large" type="text" value="{{ $service->price_large }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">X-Large</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 125" id="price_xlarge" name="price_xlarge" type="text" value="{{$service->price_xlarge }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div id="price_daycare_group" class="xl:col-span-2 space-y-2 {{ isDaycareService($service) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="duration">Daycare Price*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-1">
                  <label class="input w-full focus:outline-0">
                    <input class="grow focus:outline-0" placeholder="e.g. 40" id="price_daycare" name="price_daycare" type="text" value="{{ $service->price ?? $service->price_medium ?? $service->price_small }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div id="price_training_group" class="xl:col-span-4 space-y-2 {{ isPrivateTrainingService($service) ? '' : 'hidden' }}">
                <label class="fieldset-label">Price*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-3">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Half Hour</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 50" id="price_half_training" name="price_half_training" type="text" value="{{ $service->price_small }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">One Hour</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 90" id="price_one_training" name="price_one_training" type="text" value="{{ $service->price_medium }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Travel Charge</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 90" id="price_travel_training" name="price_travel_training" type="text" value="{{ $service->price_large }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
              </div>
              <div id="duration_standard_group" class="space-y-2 {{ ((isGroomingService($service) && $service->duration) || isGroupClassService($service) || isAlaCarteService($service) || isBoardingService($service) || isPackageService($service)) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="duration" id="default_duration_lbl">{{ (isGroupClassService($service) || isAlaCarteService($service) || isPackageService($service)) ? 'Default Duration' : 'Default Duration*' }}</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 1.5" id="duration" name="duration" type="text" value="{{ $service->duration }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                  <span class="badge badge-ghost badge-sm">hrs</span>
                </label>
              </div>
              <div id="duration_multi_group" class="xl:col-span-4 space-y-2 {{ (isGroomingService($service) && !$service->duration) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="duration">Duration - based on pet size*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-4">
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Small</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 1.0" id="duration_small" name="duration_small" type="text" value="{{ $service->duration_small }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Medium</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 1.5" id="duration_medium" name="duration_medium" type="text" value="{{ $service->duration_medium }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">Large</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 2.0" id="duration_large" name="duration_large" type="text" value="{{ $service->duration_large }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                  <label class="input w-full focus:outline-0">
                    <span class="badge badge-ghost badge-sm mr-2">X-Large</span>
                    <input class="grow focus:outline-0" placeholder="e.g. 2.5" id="duration_xlarge" name="duration_xlarge" type="text" value="{{ $service->duration_xlarge }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                </div>
              </div>
              <div id="duration_daycare_group" class="xl:col-span-2 space-y-2 {{ isDaycareService($service) ? '' : 'hidden' }}">
                <label class="fieldset-label" for="duration">Daycare Duration*</label>
                <div class="grid grid-cols-1 gap-2 xl:grid-cols-1">
                  <label class="input w-full focus:outline-0">
                    <input class="grow focus:outline-0" placeholder="e.g. 8" id="duration_daycare" name="duration_daycare" type="text" value="{{ $service->duration ?? $service->duration_medium ?? $service->duration_small }}" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
                    <span class="badge badge-ghost badge-sm">hrs</span>
                  </label>
                </div>
              </div>
              <div class="xl:col-span-4 mt-2 {{ isGroomingService($service) ? '' : 'hidden' }}" id="coat_type_price_group">
                <div class="space-y-2">
                  <label class="fieldset-label">Coat Type</label>
                  <div class="flex items-end gap-4 flex-wrap">
                    <div class="flex items-center gap-3 pb-2">
                      <input class="toggle toggle-sm" id="coat_type_price" type="checkbox" name="coat_type_price" {{ $service->is_double_coated ? 'checked' : '' }}/>
                      <label class="label" for="coat_type_price">Is Active</label>
                    </div>
                    <div class="w-full max-w-xs space-y-2">
                      <label class="input w-full focus:outline-0">
                        <input class="grow focus:outline-0" placeholder="e.g. 15" id="coat_type_price_value" name="coat_type_price_value" type="text" value="{{ $service->coat_type_price }}" {{ $service->is_double_coated ? '' : 'disabled' }} oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1')" />
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
                      <input class="toggle toggle-sm" id="status" type="checkbox" name="status" {{ $service->status === 'active' ? 'checked' : '' }}/>
                      <label class="label" for="status">Is Active</label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="xl:col-span-4">
                <div class="space-y-2">
                  <label class="fieldset-label">Future Pricing</label>
                  <div class="flex items-center gap-3">
                    <input class="toggle toggle-sm" id="enable_future_price" type="checkbox" name="enable_future_price" value="1" {{ $service->future_price ? 'checked' : '' }} onchange="toggleFuturePriceFields()" />
                    <label class="label" for="enable_future_price">Enable future price schedule</label>
                  </div>
                </div>
              </div>
              <div id="future_price_fields" class="xl:col-span-4 grid grid-cols-1 gap-4 xl:grid-cols-2 {{ $service->future_price ? '' : 'hidden' }}">
                <div class="space-y-2">
                  <label class="fieldset-label" for="future_price">New Price (USD)*</label>
                  <label class="input w-full focus:outline-0">
                    <input class="grow focus:outline-0" placeholder="e.g. 55" id="future_price" name="future_price" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" value="{{ $service->future_price }}"/>
                    <span class="badge badge-ghost badge-sm">USD</span>
                  </label>
                </div>
                <div class="space-y-2">
                  <label class="fieldset-label" for="future_price_effective_date">Effective Date*</label>
                  <input class="input w-full" id="future_price_effective_date" name="future_price_effective_date" type="date" value="{{ $service->future_price_effective_date ? $service->future_price_effective_date->format('Y-m-d') : '' }}" />
                </div>
              </div>
              <div class="xl:col-span-4">
                <div class="space-y-2">
                  <label class="fieldset-label" for="notes">Description</label>
                  <textarea placeholder="Type here" class="textarea w-full" name="description" value="{{ $service->description }}">{{ $service->description }}</textarea>
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

    let initialFiles = [];
    @if($service->avatar_img)
      initialFiles = [{
        source: '{{ $service->avatar_img }}',
        options: {
          type: 'local'
        }
      }];
    @endif

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
      files: initialFiles,
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
            $('#avatar_action').val('change');
            return result.temp_file; // Return the file path to FilePond
          },
          onerror: (response) => {
            $('#alert_message').text('Error uploading image.');
            alert_modal.showModal();
            return '';
          }
        },
        // Add this load configuration for existing files
        load: (source, load, error, progress, abort, headers) => {
          const imageUrl = '{{ asset("storage/services") }}/' + source;

          fetch(imageUrl)
            .then(response => {
              if (!response.ok) throw new Error('Network response was not ok');
              return response.blob();
            })
            .then(blob => {
              load(blob);
            })
            .catch(() => {
              error('Could not load existing avatar');
            });
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
          $('#avatar_action').val('change');
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

          // Check if user had an original avatar
          const currentAvatar = $('#current_avatar').val();
          if (currentAvatar) {
            $('#avatar_action').val('delete'); // Set action to delete if original existed
          } else {
            $('#avatar_action').val('keep'); // Set action to keep if no original
          }
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

    function deleteIcon(icon) {
      $('#icon_area').remove();
      $('#icon_action').val('delete');
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

      $('#coat_type_price').trigger('change');

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

      $('#update_form').submit();
    }

    function toggleFuturePriceFields() {
      const isEnabled = $('#enable_future_price').is(':checked');
      const fieldsDiv = $('#future_price_fields');
      const futurePrice = $('#future_price');
      const effectiveDate = $('#future_price_effective_date');

      if (isEnabled) {
        fieldsDiv.removeClass('hidden');
        futurePrice.prop('required', true);
        effectiveDate.prop('required', true);
      } else {
        fieldsDiv.addClass('hidden');
        futurePrice.prop('required', false).val('');
        effectiveDate.prop('required', false).val('');
      }
    }
  </script>
@endsection