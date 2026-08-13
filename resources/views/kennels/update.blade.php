@extends('layouts.main')
@section('title', 'Update Kennel')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Update Kennel</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">Sunshine</a></li>
      <li><a href="{{ route('kennels') }}">Kennels</a></li>
      <li class="opacity-80">Update</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('update-kennel') }}" method="POST" enctype="multipart/form-data" id="update_form">
    @csrf
    <input type="hidden" name="id" value="{{ $kennel->id }}" />
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Kennel Image</div>
            <div class="mt-4">
              <input type="file" data-filepond class="uploadFile" name="img"/>
              <input type="hidden" id="temp_file" name="temp_file" />
              <input type="hidden" id="img_action" name="img_action" value="keep" />
              <input type="hidden" id="current_img" name="current_img" value="{{ $kennel->img ?? '' }}" />
            </div>
          </div>
        </div>
      </div>
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Basic Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 xl:grid-cols-3">
              <div class="space-y-2">
                <label class="fieldset-label" for="name">Kennel Name*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. Room A" id="name" name="name" type="text" value="{{ old('name', $kennel->name) }}" />
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="capacity">Capacity*</label>
                <label class="input w-full focus:outline-0">
                  <input class="grow focus:outline-0" placeholder="e.g. 1" id="capacity" name="capacity" type="number" min="1" step="1" value="{{ old('capacity', $kennel->capacity ?? 1) }}" />
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="status">Status*</label>
                <select class="select w-full" name="status" id="status">
                  <option value="In Service" {{ old('status', $kennel->status) === 'In Service' ? 'selected' : '' }}>In Service</option>
                  <option value="Out of Service" {{ old('status', $kennel->status) === 'Out of Service' ? 'selected' : '' }}>Out of Service</option>
                  <option value="Cleaning" {{ old('status', $kennel->status) === 'Cleaning' ? 'selected' : '' }}>Cleaning</option>
                </select>
              </div>
              <div class="space-y-2 xl:col-span-3">
                <label class="fieldset-label" for="description">Description</label>
                <textarea class="textarea w-full min-h-24" placeholder="Description" name="description" id="description">{{ old('description', $kennel->description) }}</textarea>
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

  <div class="card bg-base-100 shadow mt-5">
    <div class="card-body">
      <div class="card-title">Blocked for Reservations</div>
      <p class="text-sm text-base-content/60">
        Temporarily prevent this kennel from being selected for new reservations during a date range,
        without changing its permanent status. Availability returns automatically once the range ends.
      </p>

      <form action="{{ route('create-kennel-block') }}" method="POST" class="mt-3">
        @csrf
        <input type="hidden" name="kennel_id" value="{{ $kennel->id }}" />
        <div class="fieldset grid grid-cols-1 gap-4 xl:grid-cols-4">
          <div class="space-y-2">
            <label class="fieldset-label" for="blocked_from">From*</label>
            <label class="input w-full focus:outline-0">
              <input class="grow focus:outline-0" id="blocked_from" name="blocked_from" type="date" required />
            </label>
          </div>
          <div class="space-y-2">
            <label class="fieldset-label" for="blocked_to">To*</label>
            <label class="input w-full focus:outline-0">
              <input class="grow focus:outline-0" id="blocked_to" name="blocked_to" type="date" required />
            </label>
          </div>
          <div class="space-y-2 xl:col-span-2">
            <label class="fieldset-label" for="reason">Reason</label>
            <label class="input w-full focus:outline-0">
              <input class="grow focus:outline-0" id="reason" name="reason" type="text" placeholder="e.g. Keep empty during Zak & Keno's stay" maxlength="255" />
            </label>
          </div>
        </div>
        <div class="mt-3 flex justify-end">
          <button class="btn btn-sm btn-primary" type="submit">
            <span class="iconify lucide--ban size-4"></span>
            Block Kennel
          </button>
        </div>
      </form>

      <div class="mt-4 overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>From</th>
              <th>To</th>
              <th>Reason</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($kennelBlocks as $block)
              <tr>
                <td>{{ \Carbon\Carbon::parse($block->blocked_from)->format('M j, Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($block->blocked_to)->format('M j, Y') }}</td>
                <td>{{ $block->reason ?: 'N/A' }}</td>
                <td>
                  <button type="button" class="btn btn-square btn-error btn-outline btn-xs border-transparent btn-delete-kennel-block" data-id="{{ $block->id }}" aria-label="Remove block">
                    <span class="iconify lucide--trash" style="font-size: 0.875rem;"></span>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-base-content/60">No active or upcoming blocks.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<dialog id="delete_block_modal" class="modal">
  <div class="modal-box">
    <div class="flex items-center justify-between text-lg font-medium">
      Confirm Delete
      <form method="dialog">
        <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
          <span class="iconify lucide--x size-4"></span>
        </button>
      </form>
    </div>
    <p class="py-4">You are about to remove this block. Would you like to proceed?</p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-ghost">No</button>
      </form>
      <form id="delete_block_form" method="POST" action="{{ route('delete-kennel-block') }}">
        @csrf
        <input type="hidden" name="id" id="delete_kennel_block_id" value="" />
        <button class="btn btn-error">Delete</button>
      </form>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>
@endsection

@section('page-js')
  <script src="{{ asset('src/libs/filepond/filepond.min.js') }}"></script>
  <script src="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.js') }}"></script>

  <script>
    FilePond.registerPlugin(FilePondPluginImagePreview);

    $(document).on('click', '.btn-delete-kennel-block', function() {
      $('#delete_kennel_block_id').val($(this).data('id'));
      document.getElementById('delete_block_modal').showModal();
    });

    const alert_modal = document.getElementById('alert_modal') || null;
    const pageAlertMessage = @json(session('status') === 'fail' ? session('message') : ($errors->any() ? $errors->first() : ''));

    if (pageAlertMessage && alert_modal) {
      $('#alert_message').text(pageAlertMessage);
      alert_modal.showModal();
    }

    let initialFiles = [];
    @if($kennel->img)
      initialFiles = [{
        source: '{{ $kennel->img }}',
        options: {
          type: 'local'
        }
      }];
    @endif

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
        files: initialFiles,
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
              $('#img_action').val('change');
              return result.temp_file;
            },
            onerror: () => {
              $('#alert_message').text('Error uploading image.');
              alert_modal.showModal();
              return '';
            }
          },
          load: (source, load, error) => {
            const imageUrl = '{{ asset("storage/kennels") }}/' + source;

            fetch(imageUrl)
              .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.blob();
              })
              .then(blob => {
                load(blob);
              })
              .catch(() => {
                error('Could not load existing image');
              });
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
            if ($('#current_img').val()) {
              $('#img_action').val('delete');
            }
          }
        }
      });
    }
  </script>
@endsection
