@extends('layouts.main')
@section('title', 'Create Kennel')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Create Kennel</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('kennels') }}">Kennels</a></li>
      <li class="opacity-80">Create</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('create-kennel') }}" method="POST" enctype="multipart/form-data" id="create_form">
    @csrf
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Kennel Image</div>
              <div class="mt-4">
                <input type="file" data-filepond class="uploadFile" name="img"/>
                <input type="hidden" id="temp_file" name="temp_file" />
              </div>
          </div>
        </div>
      </div>
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Basic Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 xl:grid-cols-4">
              <div class="xl:col-span-2 space-y-2">
                <label class="fieldset-label" for="name">Kennel Name*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. Kennel A" id="name" name="name" type="text" value="{{ old('name') }}" />
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="type">Type*</label>
                <select class="select w-full" name="type" id="type">
                  <option value="dog" {{ old('type', 'dog') === 'dog' ? 'selected' : '' }}>Dog</option>
                  <option value="cat" {{ old('type') === 'cat' ? 'selected' : '' }}>Cat</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="status">Status*</label>
                <select class="select w-full" name="status" id="status">
                  <option value="In Service" {{ old('status', 'In Service') === 'In Service' ? 'selected' : '' }}>In Service</option>
                  <option value="Out of Service" {{ old('status') === 'Out of Service' ? 'selected' : '' }}>Out of Service</option>
                  <option value="Cleaning" {{ old('status') === 'Cleaning' ? 'selected' : '' }}>Cleaning</option>
                </select>
              </div>
              <div class="space-y-2 xl:col-span-2">
                <label class="fieldset-label" for="description">Description</label>
                <textarea class="textarea w-full min-h-24" placeholder="Description" name="description" id="description">{{ old('description') }}</textarea>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
      <a class="btn btn-sm btn-ghost" href="{{ route('kennels') }}">
        <span class="iconify lucide--x size-4"></span>
        Cancel
      </a>
      <button class="btn btn-sm btn-primary" type="submit">
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

  <script>
    FilePond.registerPlugin(FilePondPluginImagePreview);

    const alert_modal = document.getElementById('alert_modal') || null;
    const pageAlertMessage = @json(session('status') === 'fail' ? session('message') : ($errors->any() ? $errors->first() : ''));

    if (pageAlertMessage && alert_modal) {
      $('#alert_message').text(pageAlertMessage);
      alert_modal.showModal();
    }

    const inputElement = document.querySelector('input[type="file"][data-filepond]');
    if (inputElement) {
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
        beforeAddFile: (item) => {
          const file = item.file;

          if (file.size > 1024 * 1024 * 2) {
            $('#alert_message').text('The size of image should be smaller than 2M.');
            alert_modal.showModal();
            return false;
          }

          if (!file.type.startsWith('image/')) {
            $('#alert_message').text('Uploaded file must be an image.');
            alert_modal.showModal();
            return false;
          }

          return true;
        },
        server: {
          process: {
            url: '{{ route("process-file-kennel") }}',
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            onload: (response) => {
              const result = JSON.parse(response);
              $('#temp_file').val(result.temp_file);
              return result.temp_file;
            },
            onerror: () => {
              $('#alert_message').text('Error uploading image.');
              alert_modal.showModal();
              return '';
            }
          },
          revert: {
            url: '{{ route("revert-file-kennel") }}',
            method: 'DELETE',
            headers: {
              'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
          }
        },
        onprocessfile: (error) => {
          if (error) {
            $('#alert_message').text('Error processing image.');
            alert_modal.showModal();
          }
        },
        onremovefile: (error) => {
          if (!error) {
            $('#temp_file').val('');
          }
        }
      });
    }
  </script>
@endsection
