@extends('layouts.main')
@section('title', 'Update Pet')

@section('page-css')
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
  <style>
    #questionnaire_modal .modal-box,
    #initial_temperament_modal .modal-box,
    #previous_note_modal .modal-box {
      max-height: 100vh;           /* limit height relative to viewport */
      overflow-y: auto;          /* enable vertical scroll */
      -webkit-overflow-scrolling: touch;
    }

    /* optional: make the tab content scroll independently (keeps header visible) */
    #questionnaire_modal .tab-content {
      max-height: calc(80vh - 6rem); /* adjust to leave space for modal header/footer */
      overflow-y: auto;
      padding-right: 0.5rem;          /* avoid layout jump when scrollbar appears */
    }
    #previous_note_modal .previous-note-report-content {
      max-height: calc(80vh - 6rem);
      overflow-y: auto;
      overflow-x: auto;
      padding-right: 0.5rem;
    }
    #previous_note_modal .previous-note-report-content table {
      min-width: 100%;
    }
    #previous_note_modal .previous-note-report-content .table th,
    #previous_note_modal .previous-note-report-content .table td {
      padding-block: 0.4rem;
    }
    .fa-star {
      cursor: pointer;
      font-size: 20px;
    }

    .behavior-option {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .behavior-option .behavior-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
      color: var(--color-base-content);
    }

    .behavior-option .behavior-icon svg,
    .behavior-selection-icon svg {
      width: 1rem;
      height: 1rem;
      display: block;
    }

    .behavior-option .behavior-icon .iconify,
    .behavior-selection-icon .iconify {
      width: 1rem;
      height: 1rem;
      display: inline-block;
    }

    .behavior-selection-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.7rem;
      height: 1.7rem;
    }

    .behavior-selection-chip .behavior-selection-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.05rem;
      height: 1.05rem;
      line-height: 1;
    }

    #pet_behavior_id + .select2-container--default .select2-selection--multiple {
      min-height: 2.75rem;
      padding: 0.25rem;
    }

    /* Fix: Select2/FilePond should not lock the page scroll after adding vaccination rows */
    html,
    body {
      overflow-y: auto;
    }

    .select2-container {
      width: 100% !important;
    }

    .select2-dropdown {
      z-index: 9999 !important;
    }

    .select2-results__options {
      max-height: 250px !important;
      overflow-y: auto !important;
    }
  </style>
@endsection

@section('content')
<div class="flex items-center justify-between">
  <h3 class="text-lg font-medium">Update Pet</h3>
  <div class="breadcrumbs hidden p-0 text-sm sm:inline">
    <ul>
      <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
      <li><a href="{{ route('pets') }}">Pets</a></li>
      <li class="opacity-80">Update</li>
    </ul>
  </div>
</div>
<div class="mt-3">
  @include('layouts.alerts')
  <form action="{{ route('update-pet') }}" method="POST" enctype="multipart/form-data" id="update_form">
    @csrf
    <input type="hidden" name="pet_profile_id" id="pet_profile_id" value="{{ $pet->id }}" />
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4 mt-3">
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">Upload Pet Image</div>
              <div class="mt-4">
                <input type="file" data-filepond class="uploadFile" name="pet_img"/>
                <input type="hidden" id="temp_file" name="temp_file" />
                <input type="hidden" id="img_action" name="img_action" value="keep" />
                <input type="hidden" id="current_img" name="current_img" value="{{ $pet->pet_img ?? '' }}" />
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
        <div class="mt-4 flex gap-2">
          <button class="btn btn-primary btn-sm" type="button" onclick="questionnaire_modal.showModal()">
            Questionnaire
          </button>
          <button class="btn btn-info btn-sm" type="button" onclick="initial_temperament_modal.showModal()">
            Initial Temperament
          </button>
          @if(count($previousNoteTabs) > 0)
          <button class="btn btn-accent btn-sm" type="button" onclick="if(previous_note_modal) { previous_note_modal.showModal(); loadPreviousNoteVisiblePanel(); }">
            Previous note
          </button>
          @endif
        </div>
      </div>
      <div class="xl:col-span-1">
        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <div class="card-title">
              <span>Rating: </span>
              <div class="flex items-center gap-3">
                <i class="{{ $pet->rating === 'green' ? 'fa-solid' : 'fa-regular' }} fa-star rating-green" style="color: lightseagreen"></i>
                <i class="{{ $pet->rating === 'yellow' ? 'fa-solid' : 'fa-regular' }} fa-star rating-yellow" style="color: darkorange"></i>
                <i class="{{ $pet->rating === 'red' ? 'fa-solid' : 'fa-regular' }} fa-star rating-red" style="color: red"></i>
              </div>
            </div>
            <input type="hidden" id="rating" name="rating" value="{{ $pet->rating ?? '' }}"/>
            <div class="fieldset mt-1 mb-2">
              <textarea placeholder="Rating Notes Here" class="textarea w-full" name="rating_notes">{{ $pet->rating_notes ?? '' }}</textarea>
            </div>
            <div class="card-title">
              <span>Behavior: </span>
            </div>
            <div class="fieldset mt-1 pet-behavior">
              @php
                $selectedBehaviorIds = [];
                if (is_array($pet->pet_behavior_id ?? null)) {
                  $selectedBehaviorIds = collect($pet->pet_behavior_id)->map(fn ($id) => (string)$id)->toArray();
                } elseif (!empty($pet->pet_behavior_id)) {
                  $selectedBehaviorIds = [(string)$pet->pet_behavior_id];
                }
              @endphp
              <select class="select w-full" name="pet_behavior_id[]" id="pet_behavior_id" multiple>
                <option value="">Select behavior</option>
                @foreach($petBehaviors as $behavior)
                  <option
                    value="{{ $behavior->id }}"
                    data-icon-b64="{{ base64_encode($behavior->icon?->icon ?? '') }}"
                    {{ in_array((string)$behavior->id, $selectedBehaviorIds, true) ? 'selected' : '' }}
                  >
                    {{ $behavior->description }}
                  </option>
                @endforeach
              </select>
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
                <input class="grow focus:outline-0" placeholder="e.g. Fluffy" id="pet_name" name="pet_name" type="text" value="{{ $pet->name }}"/>
              </label>
            </div>
            <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="sex">Sex*</label>
                <select class="select w-full" name="sex" id="sex" value="{{ $pet->sex }}">
                  <option value="male" {{ $pet->sex === 'male' ? 'selected' : '' }}>Male</option>
                  <option value="female" {{ $pet->sex === 'female' ? 'selected' : '' }}>Female</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="type">Type*</label>
                <select class="select w-full" name="type" id="type">
                  <option value="Dog" {{ $pet->type === 'Dog' ? 'selected' : '' }}>Dog</option>
                  <option value="Cat" {{ $pet->type === 'Cat' ? 'selected' : '' }}>Cat</option>
                </select>
              </div>
            </div>
            <div class="space-y-2">
              <label class="fieldset-label" for="spay_neuter">Spay/Neuter</label>
              <select class="select w-full" name="spay_neuter" id="spay_neuter" value="{{ $pet->spay_neuter }}">
                <option value="" {{ empty($pet->spay_neuter) ? 'selected' : '' }} disabled hidden>Select status</option>
                <option value="spayed" {{ $pet->spay_neuter === 'spayed' ? 'selected' : '' }}>Spayed</option>
                <option value="neutered" {{ $pet->spay_neuter === 'neutered' ? 'selected' : '' }}>Neutered</option>
              </select>
            </div>
            <div class="space-y-2">
              <input type="hidden" id="birth_date" name="birth_date" />
              <label class="fieldset-label" for="birthdate">Birth Date</label>
              <div class="dropdown w-full">
                <div role="button" class="btn btn-outline border-base-300 flex items-center gap-2" tabindex="0">
                  <span class="iconify lucide--calendar text-base-content/80 size-3.5"></span>
                    <p class="text-start" id="button_cally_target">{{ $pet->birthdate ? Carbon\Carbon::parse($pet->birthdate)->format('Y-m-d') : '-' }}</p>
                  <span class="iconify lucide--chevron-down text-base-content/70 size-4"></span>
                </div>
                <div class="dropdown-content mt-2" tabindex="0">
                  <calendar-date class="cally bg-base-100 rounded-box shadow-md transition-all hover:shadow-lg" id="button_cally_element" value="{{ $pet->birthdate ? Carbon\Carbon::parse($pet->birthdate)->format('Y-m-d') : '-' }}" >
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
                <input class="grow focus:outline-0" placeholder="e.g. 2" id="age" name="age" type="text" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="{{ $pet->age }}"/>
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
                  <input class="grow focus:outline-0" placeholder="e.g. 10" id="weight" name="weight" type="text" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="{{ $pet->weight }}"/>
                  <span class="badge badge-ghost badge-sm">lbs</span>
                </label>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="size">Size*</label>
                <select class="select w-full" name="size" id="size" value="{{ $pet->sizeId }}">
                  <option value="" hidden>Choose size</option>
                  @foreach($weightRanges as $weightRange)
                    <option value="{{ $weightRange->id }}" {{ $pet->sizeId === $weightRange->id ? 'selected' : '' }}>{{ $weightRange->name }}</option>
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
                <textarea placeholder="Type here" class="textarea w-full" name="notes">{{ $pet->notes }}</textarea>
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

      $existingVaccinations = $pet->vaccinations->map(function ($vaccination) {
        return [
          'id' => $vaccination->id,
          'type' => $vaccination->type ?? '',
          'date' => $vaccination->date ? \Carbon\Carbon::parse($vaccination->date)->format('Y-m-d') : '',
          'months' => $vaccination->months,
        ];
      })->values();
    @endphp
    <input type="hidden" id="vaccinations" name="vaccinations" />
    <div class="grid grid-cols-1 mt-5 gap-5 xl:grid-cols-5">
      <div class="xl:col-span-3">
        <div class="card bg-base-100 shadow" id="vaccinations_section">
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
            <input type="hidden" name="certificate_ids" id="certificate_ids" />
            <div class="mt-2" id="certificates_container">
              @foreach($pet->certificates as $certificate)
              <div class="flex items-center gap-1" id="certificate_{{ $certificate->id }}">
                <input type="hidden" id="certificate_id_{{ $certificate->id }}" value="{{ $certificate->id }}" />
                <span class="iconify lucide--file size-3.5"></span>
                <span style="font-size: 13px" class="font-medium">{{ $certificate->file_name }}</span>
                <button class="btn btn-ghost btn-sm ml-auto p-1" title="Delete" type="button" onclick="deleteCertificate({{ $certificate->id }})">
                  <span class="iconify lucide--x size-3 text-error"></span>
                </button>
                <a href="{{ route('download-certificate-pet', $certificate->id) }}" class="btn btn-ghost btn-sm ml-auto p-1">
                  <span class="iconify lucide--download size-3 text-primary"></span>
                </a>
                @if ($certificate->file_type === 'application/pdf' || str_contains($certificate->file_type, 'image/'))
                <a href="{{ asset('storage/pets/' . $certificate->file_path) }}" class="btn btn-ghost btn-sm ml-auto p-1" target="_blank" rel="noopener">
                  <span class="iconify lucide--eye size-3 text-info"></span>
                </a>
                @endif
              </div>
              @endforeach
            </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-2">
          <div class="card-body">
            <div class="card-title">Vaccine Status*</div>
            <div class="mt-2">
              <select class="select w-full" name="vaccine_status" id="vaccine_status" value="{{ $pet->vaccine_status }}">
                <option value="" hidden selected>Choose Vaccine Status</option>
                <option value="missing" {{ $pet->vaccine_status === 'missing' ? 'selected' : '' }}>Missing</option>
                <option value="submitted" {{ $pet->vaccine_status === 'submitted' ? 'selected' : '' }}>Submitted</option>
                <option value="approved" {{ $pet->vaccine_status === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="declined" {{ $pet->vaccine_status === 'declined' ? 'selected' : '' }}>Declined</option>
                <option value="expired" {{ $pet->vaccine_status === 'expired' ? 'selected' : '' }}>Expired</option>
              </select>
            </div>
          </div>
        </div>
        <div class="card bg-base-100 shadow mt-5">
          <div class="card-body">
            <div class="card-title">Veterinarian Information</div>
            <div class="fieldset mt-2 grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div class="space-y-2">
                <label class="fieldset-label" for="veterinarian_name">Name/Facility*</label>
                <input class="input w-full" id="veterinarian_name" placeholder="e.g. Animal Hospital" type="text" name="veterinarian_name" value="{{ $pet->veterinarian_name }}"/>
              </div>
              <div class="space-y-2">
                <label class="fieldset-label" for="veterinarian_phone">Phone*</label>
                <input class="input w-full" id="veterinarian_phone" placeholder="e.g. (123) 456-7890" type="text" name="veterinarian_phone" oninput="formatPhoneNumber(this)" value="{{ $pet->veterinarian_phone }}"/>
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
<dialog id="questionnaire_modal" class="modal">
  <div class="modal-box w-11/12 max-w-5xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium">Questionnaire</h3>
    <div class="p-2">
      <div role="tablist" class="tabs tabs-lift">
        @foreach($serviceCategories as $category)
          @if (stripos($category->name, 'group') !== false || stripos($category->name, 'carte') !== false || stripos($category->name, 'package') !== false || stripos($category->name, 'chauffeur') !== false)
            @continue
          @endif
        <input role="tab" class="tab" aria-label="{{ $category->name }}" type="radio" name="demo-tabs-radio" @if($loop->first) checked @endif/>
        <div class="tab-content border-base-200 bg-base-100 px-4 py-6">
          @php
            $questionnaire = $questionnaires->firstWhere('service_category_id', $category->id) ?? null;
            $questions_answers = $questionnaire ? json_decode($questionnaire->questions_answers, true) : [];
          @endphp
          @if (stripos($category->name, 'groom') !== false)
            <div class="text-sm space-y-4">
              <div>
                <p class="font-medium mb-2">1. Has your pet been groomed before?*</p>
                <div class="mb-2 space-y-1 ms-4">
                  <label class="flex items-center gap-2">
                    <input type="radio" class="radio radio-xs" name="groomed_before" value="yes"
                      {{ $questionnaire && $questions_answers['groomed_before'] === 'yes' ? 'checked' : '' }} />
                    <span class="text-sm">Yes</span>
                  </label>
                  <label class="flex items-center gap-2">
                    <input type="radio" class="radio radio-xs" name="groomed_before" value="no"
                      {{ $questionnaire && $questions_answers['groomed_before'] === 'no' ? 'checked' : '' }} />
                    <span class="text-sm">No</span>
                  </label>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">If yes, tell us where you have groomed previously</legend>
                    <textarea placeholder="Type here" id="groomed_before_detail" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['groomed_before_detail'] : '' }}">{{ $questionnaire ? $questions_answers['groomed_before_detail'] : '' }}</textarea>
                  </fieldset>
                </div>
              </div>
              <div>
                <p class="font-medium">2. Tell us more about your dog:</p>
                <div class="mb-2 ms-4 grid grid-cols-1 gap-4 xl:grid-cols-4">
                  <div class="xl:col-span-3">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Around people*</legend>
                        <div class="space-y-1 ms-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_people"
                              value="My dog loves people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog loves people' ? 'checked' : '' }} />
                            <span class="text-sm">My dog loves people</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_people"
                              value="My dog can be shy around new people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog can be shy around new people' ? 'checked' : '' }} />
                            <span class="text-sm">My dog can be shy around new people</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_people"
                              value="My dog prefers not to meet people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog prefers not to meet people' ? 'checked' : '' }} />
                            <span class="text-sm">My dog prefers not to meet people</span>
                          </label>
                        </div>
                      </fieldset>
                      <fieldset class="fieldset">
                        <legend class="fieldset-legend">Around other pets*</legend>
                        <div class="space-y-1 ms-4">
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_pets"
                              value="My dog loves other pets" {{ $questionnaire && $questions_answers['social_pets'] === 'My dog loves other pets' ? 'checked' : '' }} />
                            <span class="text-sm">My dog loves other pets</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_pets"
                              value="Can be selective about making new friends" {{ $questionnaire && $questions_answers['social_pets'] === 'Can be selective about making new friends' ? 'checked' : '' }} />
                            <span class="text-sm">Can be selective about making new friends</span>
                          </label>
                          <label class="flex items-center gap-2">
                            <input type="radio" class="radio radio-xs" name="social_pets"
                              value="Prefers to be alone" {{ $questionnaire && $questions_answers['social_pets'] === 'Prefers to be alone' ? 'checked' : '' }} />
                            <span class="text-sm">Prefers to be alone</span>
                          </label>
                        </div>
                      </fieldset>
                    </div>
                  </div>
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend">My dog is crate trained*</legend>
                    <div class="space-y-1 ms-4">
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="crate_trained" value="yes" {{ $questionnaire && $questions_answers['crate_trained'] === 'yes' ? 'checked' : '' }} />
                        <span class="text-sm">Yes</span>
                      </label>
                      <label class="flex items-center gap-2">
                        <input type="radio" class="radio radio-xs" name="crate_trained" value="no" {{ $questionnaire && $questions_answers['crate_trained'] === 'no' ? 'checked' : '' }} />
                        <span class="text-sm">No</span>
                      </label>
                    </div>
                  </fieldset>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 mt-3">
                <div>
                  <p class="font-medium mb-2">3. Are there any physical issues or concerns else?*</p>
                  <div class="mb-2 space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="physical_issues" value="yes" {{ $questionnaire && $questions_answers['physical_issues'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="physical_issues" value="no" {{ $questionnaire && $questions_answers['physical_issues'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">If yes, please tell us about them</legend>
                      <textarea placeholder="Type here" id="physical_issues_detail" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['physical_issues_detail'] : '' }}">{{ $questionnaire ? $questions_answers['physical_issues_detail'] : '' }}</textarea>
                    </fieldset>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">4. Is your dog taking any medications?*</p>
                  <div class="mb-2 space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="medications" value="yes" {{ $questionnaire && $questions_answers['medications'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="medications" value="no" {{ $questionnaire && $questions_answers['medications'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">If yes, tell us what medications your dog is taking</legend>
                      <textarea placeholder="Type here" id="medications_detail" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['medications_detail'] : '' }}">{{ $questionnaire ? $questions_answers['medications_detail'] : '' }}</textarea>
                    </fieldset>
                  </div>
                </div>
              </div>
              <div>
                <p class="font-medium mb-2">5. Is there anything else you want us to know about your dog?</p>
                <fieldset class="fieldset ms-4">
                  <textarea placeholder="Type here" id="additional_note" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['additional_note'] : '' }}">{{ $questionnaire ? $questions_answers['additional_note'] : '' }}</textarea>
                </fieldset>
              </div>
              <div>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend text-dark">
                    ※Questionnaire Status
                  </legend>
                  <select aria-label="Select" class="select" name="grooming_questionnaire_status">
                    <option value="pending" {{ $questionnaire && $questionnaire->status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $questionnaire && $questionnaire->status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $questionnaire && $questionnaire->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                  </select>
                </fieldset>
              </div>
              <div class="mt-4 flex justify-end">
                <button class="btn btn-sm btn-primary" type="button"
                  onclick="saveQuestionnaire(this, {{ $category }}, {{ $pet->id }}, {{ $questionnaire ? $questionnaire->id : null }})">
                  <span class="iconify lucide--check size-4"></span>
                  <span class="loading loading-spinner size-3.5 hidden"></span>
                  Save
                </button>
              </div>
            </div>
          @elseif (stripos($category->name, 'daycare') !== false)
            <div class="text-sm space-y-4">
              <p class="font-medium">Tell us more about your dog:</p>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 px-4">
                <div>
                  <p class="font-medium mb-2">Around People</p>
                  <div class="space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_people"
                        value="My dog loves people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog loves people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog loves people</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_people"
                        value="My dog can be shy around new people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog can be shy around new people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog can be shy around new people</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_people"
                        value="My dog prefers not to meet people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog prefers not to meet people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog prefers not to meet people</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Around Other Dogs</p>
                  <div class="space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_pets"
                        value="My dog loves other pets" {{ $questionnaire && $questions_answers['social_pets'] === 'My dog loves other pets' ? 'checked' : '' }} />
                      <span class="text-sm">My dog loves other pets</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_pets"
                        value="Can be selective about making new friends" {{ $questionnaire && $questions_answers['social_pets'] === 'Can be selective about making new friends' ? 'checked' : '' }} />
                      <span class="text-sm">Can be selective about making new friends</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_around_pets"
                        value="Prefers to be alone" {{ $questionnaire && $questions_answers['social_pets'] === 'Prefers to be alone' ? 'checked' : '' }} />
                      <span class="text-sm">Prefers to be alone</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog is crate trained*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_is_crate_trained" value="yes" {{ $questionnaire && $questions_answers['crate_trained'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_is_crate_trained" value="no" {{ $questionnaire && $questions_answers['crate_trained'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog visits dog parks*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_visit_parks" value="yes" {{ $questionnaire && $questions_answers['visit_parks'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_visit_parks" value="no" {{ $questionnaire && $questions_answers['visit_parks'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog has boarded at another facility*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_boarded" value="yes" {{ $questionnaire && $questions_answers['boarded'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_boarded" value="no" {{ $questionnaire && $questions_answers['boarded'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog has attended daycare at another facility*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_attended" value="yes" {{ $questionnaire && $questions_answers['attended'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="daycare_attended" value="no" {{ $questionnaire && $questions_answers['attended'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
              </div>
              <div>
                <p class="font-medium mb-2">Additional Comments(optional)</p>
                <fieldset class="fieldset ms-4">
                  <textarea placeholder="Type here" id="daycare_additional_comments" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['additional_comments'] : '' }}">{{ $questionnaire ? $questions_answers['additional_comments'] : '' }}</textarea>
                </fieldset>
              </div>
              <div>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend text-dark">
                    ※Questionnaire Status
                  </legend>
                  <select aria-label="Select" class="select" name="daycare_questionnaire_status">
                    <option value="pending" {{ $questionnaire && $questionnaire->status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $questionnaire && $questionnaire->status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $questionnaire && $questionnaire->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                  </select>
                </fieldset>
              </div>
              <div class="mt-4 flex justify-end">
                <button class="btn btn-sm btn-primary" type="button"
                  onclick="saveQuestionnaire(this, {{ $category }}, {{ $pet->id }}, {{ $questionnaire ? $questionnaire->id : null }})">
                  <span class="iconify lucide--check size-4"></span>
                  <span class="loading loading-spinner size-3.5 hidden"></span>
                  Save
                </button>
              </div>
            </div>
          @elseif (stripos($category->name, 'boarding') !== false)
            <div class="text-sm space-y-4">
              <p class="font-medium">Tell us more about your dog:</p>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 px-4">
                <div>
                  <p class="font-medium mb-2">Around People</p>
                  <div class="space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_people"
                        value="My dog loves people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog loves people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog loves people</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_people"
                        value="My dog can be shy around new people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog can be shy around new people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog can be shy around new people</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_people"
                        value="My dog prefers not to meet people" {{ $questionnaire && $questions_answers['social_people'] === 'My dog prefers not to meet people' ? 'checked' : '' }} />
                      <span class="text-sm">My dog prefers not to meet people</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Around Other Dogs</p>
                  <div class="space-y-1 ms-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_pets"
                        value="My dog loves other pets" {{ $questionnaire && $questions_answers['social_pets'] === 'My dog loves other pets' ? 'checked' : '' }} />
                      <span class="text-sm">My dog loves other pets</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_pets"
                        value="Can be selective about making new friends" {{ $questionnaire && $questions_answers['social_pets'] === 'Can be selective about making new friends' ? 'checked' : '' }} />
                      <span class="text-sm">Can be selective about making new friends</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_around_pets"
                        value="Prefers to be alone" {{ $questionnaire && $questions_answers['social_pets'] === 'Prefers to be alone' ? 'checked' : '' }} />
                      <span class="text-sm">Prefers to be alone</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog is crate trained*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_is_crate_trained" value="yes" {{ $questionnaire && $questions_answers['crate_trained'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_is_crate_trained" value="no" {{ $questionnaire && $questions_answers['crate_trained'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog visits dog parks*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_visit_parks" value="yes" {{ $questionnaire && $questions_answers['visit_parks'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_visit_parks" value="no" {{ $questionnaire && $questions_answers['visit_parks'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog has boarded at another facility*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_boarded" value="yes" {{ $questionnaire && $questions_answers['boarded'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_boarded" value="no" {{ $questionnaire && $questions_answers['boarded'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">My dog has attended daycare at another facility*</p>
                  <div class="ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_attended" value="yes" {{ $questionnaire && $questions_answers['attended'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="boarding_attended" value="no" {{ $questionnaire && $questions_answers['attended'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
              </div>
              <div>
                <p class="font-medium mb-2">Additional Comments(optional)</p>
                <fieldset class="fieldset ms-4">
                  <textarea placeholder="Type here" id="boarding_additional_comments" class="textarea w-full questionnaire-detail" value="{{ $questionnaire ? $questions_answers['additional_comments'] : '' }}">{{ $questionnaire ? $questions_answers['additional_comments'] : '' }}</textarea>
                </fieldset>
              </div>
              <div>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend text-dark">
                    ※Questionnaire Status
                  </legend>
                  <select aria-label="Select" class="select" name="boarding_questionnaire_status">
                    <option value="pending" {{ $questionnaire && $questionnaire->status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $questionnaire && $questionnaire->status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $questionnaire && $questionnaire->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                  </select>
                </fieldset>
              </div>
              <div class="mt-4 flex justify-end">
                <button class="btn btn-sm btn-primary" type="button"
                  onclick="saveQuestionnaire(this, {{ $category }}, {{ $pet->id }}, {{ $questionnaire ? $questionnaire->id : null }})">
                  <span class="iconify lucide--check size-4"></span>
                  <span class="loading loading-spinner size-3.5 hidden"></span>
                  Save
                </button>
              </div>
            </div>
          @elseif (stripos($category->name, 'training') !== false)
            <div class="text-sm space-y-4">
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">What is the primary issue?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="primary_issue" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['primary_issue'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">When does it occur?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="when_occurs" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['when_occurs'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
                <div>
                  <p class="font-medium mb-2">Does it happen consistently?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="consistent" value="yes" {{ $questionnaire && $questions_answers['consistent'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="consistent" value="no" {{ $questionnaire && $questions_answers['consistent'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div class="xl:col-span-3">
                  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div>
                      <p class="font-medium mb-2">Has your dog received any training?</p>
                      <div class="mb-2 ms-4 flex items-center gap-4">
                        @php
                          $receivedTraining = $questionnaire ? $questions_answers['received_training'] : '';
                          $receivedTrainingArray = $receivedTraining ? (is_array($receivedTraining) ? $receivedTraining : explode(',', $receivedTraining)) : [];
                          $receivedTrainingArray = array_map('trim', $receivedTrainingArray);
                        @endphp
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="received_training[]" value="private" {{ in_array('private', $receivedTrainingArray) ? 'checked' : '' }} />
                          <span class="text-sm">Private</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="received_training[]" value="group classes" {{ in_array('group classes', $receivedTrainingArray) ? 'checked' : '' }} />
                          <span class="text-sm">Group</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="received_training[]" value="board" {{ in_array('board', $receivedTrainingArray) ? 'checked' : '' }} />
                          <span class="text-sm">Board</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="checkbox" class="checkbox checkbox-xs" name="received_training[]" value="train" {{ in_array('train', $receivedTrainingArray) ? 'checked' : '' }} />
                          <span class="text-sm">Train</span>
                        </label>
                      </div>
                    </div>
                    <div>
                      <p class="font-medium mb-2">Did you see improvement after training?*</p>
                      <div class="mb-2 ms-4 flex items-center gap-4">
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="improvement_after_training" value="yes" {{ $questionnaire && $questions_answers['improvement_after_training'] === 'yes' ? 'checked' : '' }} />
                          <span class="text-sm">Yes</span>
                        </label>
                        <label class="flex items-center gap-2">
                          <input type="radio" class="radio radio-xs" name="improvement_after_training" value="no" {{ $questionnaire && $questions_answers['improvement_after_training'] === 'no' ? 'checked' : '' }} />
                          <span class="text-sm">No</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">How old was your pet when you received them?*</p>
                  <div class="mb-2 ms-4">
                    <input name="age_when_received" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['age_when_received'] : '' }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">How were they acquired?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="how_acquired" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['how_acquired'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="divider my-6"><span class="font-semibold text-base-content/70">Daily schedule</span></div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">Where does your pet sleep?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="where_sleeps" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['where_sleeps'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">What time does your pet wake up in the morning?*</p>
                  <div class="mb-2 ms-4">
                    <input type="time" name="wake_up_time" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['wake_up_time'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">When do they go out to the bathroom?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="bathroom_time" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['bathroom_time'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div>
                  <p class="font-medium mb-2">Do you take your pet for walks?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="takes_walks" value="yes" {{ $questionnaire && $questions_answers['takes_walks'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="takes_walks" value="no" {{ $questionnaire && $questions_answers['takes_walks'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Does your pet have a fenced yard?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="fenced_yard" value="yes" {{ $questionnaire && $questions_answers['fenced_yard'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="fenced_yard" value="no" {{ $questionnaire && $questions_answers['fenced_yard'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">What other type of exercise does your pet receive?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="exercise_type" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['exercise_type'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div>
                  <p class="font-medium mb-2">When do they eat (AM, PM, both)?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    @php
                      $eatingTimes = $questionnaire ? $questions_answers['eating_times'] : '';
                      $eatingTimesArray = $eatingTimes ? (is_array($eatingTimes) ? $eatingTimes : explode(',', $eatingTimes)) : [];
                      $eatingTimesArray = array_map('trim', $eatingTimesArray);
                      // Handle "both" value by checking both AM and PM
                      if (in_array('both', $eatingTimesArray)) {
                        $eatingTimesArray = array_diff($eatingTimesArray, ['both']);
                        $eatingTimesArray[] = 'AM';
                        $eatingTimesArray[] = 'PM';
                      }
                      $eatingTimesArray = array_unique($eatingTimesArray);
                    @endphp
                    <label class="flex items-center gap-2">
                      <input type="checkbox" class="checkbox checkbox-xs" name="eating_times[]" value="AM" {{ in_array('AM', $eatingTimesArray) ? 'checked' : '' }} />
                      <span class="text-sm">AM</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="checkbox" class="checkbox checkbox-xs" name="eating_times[]" value="PM" {{ in_array('PM', $eatingTimesArray) ? 'checked' : '' }} />
                      <span class="text-sm">PM</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">How do they eat?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="eating_style" value="all at once" {{ $questionnaire && $questions_answers['eating_style'] === 'all at once' ? 'checked' : '' }} />
                      <span class="text-sm">All at once</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="eating_style" value="graze" {{ $questionnaire && $questions_answers['eating_style'] === 'graze' ? 'checked' : '' }} />
                      <span class="text-sm">Graze</span>
                    </label>
                  </div>
                </div>
              </div>
              <div class="divider my-6"><span class="font-semibold text-base-content/70">Household</span></div>
              <div>
                <p class="font-medium mb-2">Who else lives in the house?</p>
                <div class="mb-2 ms-4 flex items-center gap-4">
                  @php
                    $householdMembers = $questionnaire ? $questions_answers['household_members'] : '';
                    $householdMembersArray = $householdMembers ? (is_array($householdMembers) ? $householdMembers : explode(',', $householdMembers)) : [];
                    $householdMembersArray = array_map('trim', $householdMembersArray);
                  @endphp
                  <label class="flex items-center gap-2">
                    <input type="checkbox" class="checkbox checkbox-xs" name="household_members[]" value="adults" {{ in_array('adults', $householdMembersArray) ? 'checked' : '' }} />
                    <span class="text-sm">Adults</span>
                  </label>
                  <label class="flex items-center gap-2">
                    <input type="checkbox" class="checkbox checkbox-xs" name="household_members[]" value="children" {{ in_array('children', $householdMembersArray) ? 'checked' : '' }} />
                    <span class="text-sm">Children</span>
                  </label>
                  <label class="flex items-center gap-2">
                    <input type="checkbox" class="checkbox checkbox-xs" name="household_members[]" value="other pets" {{ in_array('other pets', $householdMembersArray) ? 'checked' : '' }} />
                    <span class="text-sm">Other Pets</span>
                  </label>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">How do they get along?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="getting_along" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['getting_along'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Who else cares for the pet?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="who_cares_for_pet" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['who_cares_for_pet'] : '' }}" />
                  </div>
                </div>
              </div>
              <div>
                <p class="font-medium mb-2">Do you work?*</p>
                <div class="mb-2 ms-4 flex items-center gap-4">
                  <label class="flex items-center gap-2">
                    <input type="radio" class="radio radio-xs" name="do_you_work" value="yes" {{ $questionnaire && $questions_answers['do_you_work'] === 'yes' ? 'checked' : '' }} />
                    <span class="text-sm">Yes</span>
                  </label>
                  <label class="flex items-center gap-2">
                    <input type="radio" class="radio radio-xs" name="do_you_work" value="no" {{ $questionnaire && $questions_answers['do_you_work'] === 'no' ? 'checked' : '' }} />
                    <span class="text-sm">No</span>
                  </label>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">What does your pet do throughout the day?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="pet_daily_activities" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['pet_daily_activities'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Who lets your pet out during the day?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="who_lets_out" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['who_lets_out'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="divider my-6"><span class="font-semibold text-base-content/70">Sociability/behavior</span></div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div>
                  <p class="font-medium mb-2">How is your pet with people they don't know?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="with_unknown_people" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['with_unknown_people'] : '' }}" />
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">How is your pet with dogs they don't know?*</p>
                  <div class="mb-2 ms-4">
                    <input type="text" name="with_unknown_dogs" class="input w-full input-sm" value="{{ $questionnaire ? $questions_answers['with_unknown_dogs'] : '' }}" />
                  </div>
                </div>
              </div>
              <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div>
                  <p class="font-medium mb-2">Has your pet ever bit someone?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="ever_bitten_someone" value="yes" {{ $questionnaire && $questions_answers['ever_bitten_someone'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="ever_bitten_someone" value="no" {{ $questionnaire && $questions_answers['ever_bitten_someone'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Has your dog tried to bite someone?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="tried_to_bite" value="yes" {{ $questionnaire && $questions_answers['tried_to_bite'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="tried_to_bite" value="no" {{ $questionnaire && $questions_answers['tried_to_bite'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
                <div>
                  <p class="font-medium mb-2">Has your pet ever fought with another dog?*</p>
                  <div class="mb-2 ms-4 flex items-center gap-4">
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="fought_with_dogs" value="yes" {{ $questionnaire && $questions_answers['fought_with_dogs'] === 'yes' ? 'checked' : '' }} />
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input type="radio" class="radio radio-xs" name="fought_with_dogs" value="no" {{ $questionnaire && $questions_answers['fought_with_dogs'] === 'no' ? 'checked' : '' }} />
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
              </div>
              <div>
                <fieldset class="fieldset">
                  <legend class="fieldset-legend text-dark">
                    ※Questionnaire Status
                  </legend>
                  <select aria-label="Select" class="select" name="training_questionnaire_status">
                    <option value="pending" {{ $questionnaire && $questionnaire->status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $questionnaire && $questionnaire->status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $questionnaire && $questionnaire->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                  </select>
                </fieldset>
              </div>
              <div class="mt-4 flex justify-end">
                <button class="btn btn-sm btn-primary" type="button"
                  onclick="saveQuestionnaire(this, {{ $category }}, {{ $pet->id }}, {{ $questionnaire ? $questionnaire->id : null }})">
                  <span class="iconify lucide--check size-4"></span>
                  <span class="loading loading-spinner size-3.5 hidden"></span>
                  Save
                </button>
              </div>
            </div>
          @endif
        </div>
        @endforeach
      </div>
    </div>
  </div>
</dialog>

<dialog id="initial_temperament_modal" class="modal">
  <div class="modal-box w-11/12 max-w-xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium">Initial Temperament Assessment</h3>
    <div class="p-2">
      @php
        $temperamentData = $initialTemperament && $initialTemperament->temperament_data ? $initialTemperament->temperament_data : [];
      @endphp
      <div class="text-sm space-y-4">
        <div>
          <p class="font-medium mb-2">Initial Greeting:</p>
          <div class="mb-2 space-y-1 ms-1">
            <label class="flex items-center gap-2">
              <input type="radio" class="radio radio-xs" name="temperament_initial_greeting" value="approachable"
                {{ isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'approachable' ? 'checked' : '' }} />
              <span class="text-sm">Approachable<span class="text-base-content/70">(allows contact, loose body posture, will accept treats)</span></span>
            </label>
            <label class="flex items-center gap-2">
              <input type="radio" class="radio radio-xs" name="temperament_initial_greeting" value="shy"
                {{ isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'shy' ? 'checked' : '' }} />
              <span class="text-sm">Shy<span class="text-base-content/70">(cautious, tail tucked, whale eye, does not want to be petted)</span></span>
            </label>
            <label class="flex items-center gap-2">
              <input type="radio" class="radio radio-xs" name="temperament_initial_greeting" value="uncomfortable"
                {{ isset($temperamentData['initial_greeting']) && $temperamentData['initial_greeting'] === 'uncomfortable' ? 'checked' : '' }} />
              <span class="text-sm">Uncomfortable<span class="text-base-content/70">(moves away, shows teeth, barks or snaps)</span></span>
            </label>
          </div>
        </div>
        <div>
          <p class="font-medium">Can you touch the following areas:</p>
          <div class="mb-2 space-y-2 ms-4">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Body</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_body"
                    value="accept" {{ isset($temperamentData['touch_body']) && $temperamentData['touch_body'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_body"
                    value="react" {{ isset($temperamentData['touch_body']) && $temperamentData['touch_body'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Legs</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_legs"
                    value="accept" {{ isset($temperamentData['touch_legs']) && $temperamentData['touch_legs'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_legs"
                    value="react" {{ isset($temperamentData['touch_legs']) && $temperamentData['touch_legs'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Feet</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_feet"
                    value="accept" {{ isset($temperamentData['touch_feet']) && $temperamentData['touch_feet'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_feet"
                    value="react" {{ isset($temperamentData['touch_feet']) && $temperamentData['touch_feet'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Tail</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_tail"
                    value="accept" {{ isset($temperamentData['touch_tail']) && $temperamentData['touch_tail'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_tail"
                    value="react" {{ isset($temperamentData['touch_tail']) && $temperamentData['touch_tail'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Face</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_face"
                    value="accept" {{ isset($temperamentData['touch_face']) && $temperamentData['touch_face'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_face"
                    value="react" {{ isset($temperamentData['touch_face']) && $temperamentData['touch_face'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Nails</legend>
              <div class="ms-4 flex items-center gap-5">
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_nails"
                    value="accept" {{ isset($temperamentData['touch_nails']) && $temperamentData['touch_nails'] === 'accept' ? 'checked' : '' }} />
                  <span class="text-sm">Accepts</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" class="radio radio-xs" name="temperament_touch_nails"
                    value="react" {{ isset($temperamentData['touch_nails']) && $temperamentData['touch_nails'] === 'react' ? 'checked' : '' }} />
                  <span class="text-sm">Reacts</span>
                </label>
              </div>
            </fieldset>
          </div>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button class="btn btn-sm btn-primary" type="button" onclick="saveInitialTemperament({{ $pet->id }}, {{ $initialTemperament ? $initialTemperament->id : 'null' }})">
          <span class="iconify lucide--check size-4"></span>
          <span class="loading loading-spinner size-3.5 hidden"></span>
          Save
        </button>
      </div>
    </div>
  </div>
</dialog>

@if(count($previousNoteTabs) > 0)
<dialog id="previous_note_modal" class="modal">
  <div class="modal-box w-11/12 max-w-4xl">
    <form method="dialog">
      <button class="btn btn-sm btn-circle btn-ghost absolute top-2 right-2" type="submit" aria-label="Close">
        <span class="iconify lucide--x size-4"></span>
      </button>
    </form>
    <h3 class="text-lg font-medium">Previous note</h3>
    <div class="p-2">
      <div class="tabs tabs-boxed pb-2 shrink-0">
        @foreach($previousNoteTabs as $idx => $tab)
        <button type="button" class="tab previous-note-tab {{ $idx === 0 ? 'tab-active' : '' }}"
          data-tab-id="{{ $tab['id'] }}"
          @if(!empty($tab['appointment_id'])) data-appointment-id="{{ $tab['appointment_id'] }}" @endif>
          {{ $tab['label'] }}
        </button>
        @endforeach
      </div>
      @foreach($previousNoteTabs as $idx => $tab)
      <div class="previous-note-panel {{ $idx === 0 ? '' : 'hidden' }}" id="panel-{{ $tab['id'] }}" @if(!empty($tab['appointment_id'])) data-appointment-id="{{ $tab['appointment_id'] }}" data-fetch-url="{{ route('archive-report-fragment', $tab['appointment_id']) }}" @endif>
        @if(!empty($tab['appointment_id']))
        <div class="previous-note-report-content w-full bg-base-100 border border-base-200 rounded-box px-4 py-6 text-sm" data-fetch-url="{{ route('archive-report-fragment', $tab['appointment_id']) }}">
          <p class="text-base-content/70 flex items-center gap-2"><span class="loading loading-spinner loading-sm"></span>Loading report…</p>
        </div>
        @else
        <div class="flex items-center justify-center min-h-[200px] rounded-box border border-base-200 bg-base-200/30 px-4 py-6">
          <p class="text-base-content/70">No previous appointment for this service.</p>
        </div>
        @endif
      </div>
      @endforeach
    </div>
    <div class="mt-6 flex justify-end">
      <button class="btn btn-sm" type="button" onclick="previous_note_modal.close()">Close</button>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button type="submit">close</button>
  </form>
</dialog>
@endif
@endsection

@section('page-js')
  <script src="{{ asset('src/libs/filepond/filepond.min.js') }}"></script>
  <script src="{{ asset('src/libs/filepond/filepond-plugin-image-preview.min.js') }}"></script>
  <script src="{{ asset('src/assets/ui-components-calendar.js') }}"></script>
  <script type="module" src="https://unpkg.com/cally"></script>

  <script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>

  <script>
    const questionnaire_modal = document.getElementById('questionnaire_modal');
    const initial_temperament_modal = document.getElementById('initial_temperament_modal');
    const previous_note_modal = document.getElementById('previous_note_modal');

    function loadPreviousNotePanelContent(contentEl) {
      if (!contentEl || !contentEl.getAttribute('data-fetch-url')) return;
      if (contentEl.hasAttribute('data-loaded')) return;
      contentEl.setAttribute('data-loaded', '1');
      var url = contentEl.getAttribute('data-fetch-url');
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data && data.html) {
            contentEl.innerHTML = data.html;
          } else {
            contentEl.innerHTML = '<p class="text-base-content/70">Unable to load report.</p>';
          }
        })
        .catch(function() {
          contentEl.setAttribute('data-loaded', '');
          contentEl.innerHTML = '<p class="text-base-content/70">Unable to load report.</p>';
        });
    }
    function loadPreviousNoteVisiblePanel() {
      var panel = document.querySelector('.previous-note-panel:not(.hidden)');
      if (panel) {
        var contentEl = panel.querySelector('.previous-note-report-content');
        if (contentEl) loadPreviousNotePanelContent(contentEl);
      }
    }
    if (previous_note_modal) {
      document.querySelectorAll('.previous-note-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
          var tabId = this.getAttribute('data-tab-id');
          document.querySelectorAll('.previous-note-tab').forEach(function(t) { t.classList.remove('tab-active'); });
          this.classList.add('tab-active');
          document.querySelectorAll('.previous-note-panel').forEach(function(panel) {
            panel.classList.add('hidden');
            if (panel.id === 'panel-' + tabId) {
              panel.classList.remove('hidden');
              var contentEl = panel.querySelector('.previous-note-report-content');
              loadPreviousNotePanelContent(contentEl);
            }
          });
        });
      });
    }
  </script>
  <script>
    const vaccinationTypeOptions = @json($vaccinationTypeOptions);
    const existingVaccinations = @json($existingVaccinations);
    const vaccinationRemoveActiveColor = '#f31260';
    const vaccinationRemoveDisabledColor = '#b3b8c3';
    let vaccinationRowCounter = 0;


    function unlockPageScroll() {
      document.documentElement.style.overflow = 'auto';
      document.documentElement.style.overflowY = 'auto';
      document.body.style.overflow = 'auto';
      document.body.style.overflowY = 'auto';
      document.body.style.position = 'relative';
    }

    function findVaccinationOption(value) {
      const normalizedValue = (value || '').toString().trim().toLowerCase();
      if (!normalizedValue) {
        return '';
      }

      const exact = vaccinationTypeOptions.find((option) => option.toLowerCase() === normalizedValue);
      return exact || value;
    }

    function buildVaccinationOptions(selectedType = '', excludedTypes = []) {
      const resolvedSelectedType = findVaccinationOption(selectedType);
      const options = [...vaccinationTypeOptions];
      const excludedSet = new Set(
        excludedTypes
          .map((type) => findVaccinationOption(type))
          .map((type) => (type || '').toString().trim().toLowerCase())
          .filter((type) => type !== '' && type !== resolvedSelectedType.toLowerCase())
      );

      if (resolvedSelectedType && !options.some((option) => option.toLowerCase() === resolvedSelectedType.toLowerCase())) {
        options.push(resolvedSelectedType);
      }

      return options
        .filter((option) => !excludedSet.has(option.toLowerCase()) || option === resolvedSelectedType)
        .map((option) => {
          const selected = option === resolvedSelectedType ? 'selected' : '';
          return `<option value="${option}" ${selected}>${option}</option>`;
        })
        .join('');
    }

    function bindVaccinationSelectChangeHandler() {
      $('#vaccinations_container .vaccination-type-select')
        .off('change.uniqueVaccination')
        .on('change.uniqueVaccination', function() {
          unlockPageScroll();
          setTimeout(function() {
            refreshVaccinationDropdowns();
            unlockPageScroll();
          }, 0);
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
        const resolvedSelectedType = findVaccinationOption(row.selectedType);

        if ($select.hasClass('select2-hidden-accessible')) {
          $select.select2('destroy');
        }

        $select.html(`<option value=""></option>${buildVaccinationOptions(resolvedSelectedType, excludedTypes)}`);
        $select.val(resolvedSelectedType);
        initVaccinationRowSelect2($row);
      });

      bindVaccinationSelectChangeHandler();
    }

    function initVaccinationRowSelect2(row) {
      row.find('.vaccination-type-select').each(function() {
        const $select = $(this);

        if ($select.hasClass('select2-hidden-accessible')) {
          $select.select2('destroy');
        }

        $select.select2({
          placeholder: 'Select vaccination',
          allowClear: true,
          width: '100%',
          dropdownParent: $('#vaccinations_section')
        });

        $select
          .off('select2:open.scrollFix select2:close.scrollFix select2:select.scrollFix select2:unselect.scrollFix')
          .on('select2:open.scrollFix select2:close.scrollFix select2:select.scrollFix select2:unselect.scrollFix', function() {
            setTimeout(unlockPageScroll, 0);
          });
      });
    }

    function addVaccinationRow(vaccination = {}) {
      vaccinationRowCounter += 1;
      const rowId = `vaccination_row_${vaccinationRowCounter}`;
      const id = vaccination.id || '';
      const type = vaccination.type || '';
      const date = vaccination.date || '';
      const months = vaccination.months || '';

      const rowHtml = `
        <div class="grid grid-cols-1 gap-3 xl:grid-cols-12 vaccination-row" id="${rowId}">
          <input type="hidden" class="vaccination-id" value="${id}">
          <div class="xl:col-span-5">
            <select class="select w-full vaccination-type-select">
              <option value=""></option>
              ${buildVaccinationOptions(type)}
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
      document.getElementById("button_cally_target").innerText = e.target.value

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

    // Prepare initial files array for existing avatar
    let initialFiles = [];
    @if($pet->pet_img)
      initialFiles = [{
        source: '{{ $pet->pet_img }}',
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
          url: '{{ route("process-file-pet") }}',
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          onload: (response) => {
            // Handle successful upload response
            const result = JSON.parse(response);
            $('#temp_file').val(result.temp_file); // Store the file path in a hidden input
            $('#img_action').val('change'); // Set action to change when new file is uploaded
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
          const imageUrl = '{{ asset("storage/pets") }}/' + source;

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
          $('#img_action').val('change');
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
          const currentImg = $('#current_img').val();
          if (currentImg) {
            $('#img_action').val('delete'); // Set action to delete if original existed
          } else {
            $('#img_action').val('keep'); // Set action to keep if no original
          }
        }
      }
    });

    $(document).ready(function() {

      $('#spay_neuter').select2({
        placeholder: "Select status",
        allowClear: true,
        minimumResultsForSearch: Infinity
      });

      function decodeBase64(value) {
        if (!value) {
          return '';
        }

        try {
          return atob(value);
        } catch (err) {
          return '';
        }
      }

      function normalizeIconMarkup(markup) {
        if (!markup) {
          return '';
        }

        let normalized = String(markup).trim();
        if (!normalized) {
          return '';
        }

        if (normalized.includes('&lt;')) {
          normalized = $('<textarea/>').html(normalized).text().trim();
        }

        if (normalized.startsWith('<svg') || normalized.startsWith('<span') || normalized.startsWith('<i')) {
          return normalized;
        }

        if (normalized.includes('iconify')) {
          return '<span class="' + normalized + '"></span>';
        }

        return '';
      }

      function getBehaviorIconMarkup(item) {
        if (!item || !item.id) {
          return '';
        }

        let iconB64 = item.element ? item.element.getAttribute('data-icon-b64') : '';

        if (!iconB64) {
          const optionEl = $('#pet_behavior_id').find('option[value="' + item.id + '"]');
          if (optionEl.length) {
            iconB64 = optionEl.attr('data-icon-b64') || '';
          }
        }

        return normalizeIconMarkup(decodeBase64(iconB64));
      }

      function renderBehaviorOption(item) {
        if (!item.id) {
          return item.text;
        }

        const svg = getBehaviorIconMarkup(item);
        if (!svg) {
          return item.text;
        }

        return $(
          '<span class="behavior-option">' +
            '<span class="behavior-icon">' + svg + '</span>' +
            '<span>' + item.text + '</span>' +
          '</span>'
        );
      }

      function renderBehaviorSelection(item) {
        if (!item.id) {
          return item.text;
        }

        const svg = getBehaviorIconMarkup(item);
        if (!svg) {
          return item.text;
        }

        const safeTitle = $('<div>').text(item.text || '').html();
        return $(
          '<span class="behavior-selection-chip" title="' + safeTitle + '" aria-label="' + safeTitle + '">' +
            '<span class="behavior-selection-icon">' + svg + '</span>' +
          '</span>'
        );
      }

      $('#pet_behavior_id').select2({
        width: '100%',
        placeholder: 'Select behavior',
        closeOnSelect: false,
        templateResult: renderBehaviorOption,
        templateSelection: renderBehaviorSelection,
        escapeMarkup: function(markup) {
          return markup;
        }
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
              q: params.term // Send the search term as 'q'
            };
          },
          processResults: function (data) {
            return {
              results: data.items.map(function (owner) {
                return {
                  id: owner.id,
                  text: `${owner.profile.first_name} ${owner.profile.last_name} (${owner.email} | ${owner.profile.phone_number_1})`,
                  first_name: owner.profile.first_name,
                  last_name: owner.profile.last_name,
                  email: owner.email,
                  phone_number: owner.profile.phone_number_1
                };
              })
            };
          }
        },
        templateResult: function (owner) {
          if (!owner.id) {
            return owner.text;
          }
          if (!owner.first_name) {
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
          if (!owner.first_name) {
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

      // Add the owner option if not present
      var ownerText = "{{ $pet->owner->profile->first_name ?? '' }} {{ $pet->owner->profile->last_name ?? '' }} ({{ $pet->owner->email ?? '' }} | {{ $pet->owner->profile->phone_number_1 ?? '' }})";
      var ownerOption = new Option(ownerText, "{{ $pet->user_id }}", true, true);
      $('#owner').append(ownerOption).trigger('change');

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
      var breedOption = new Option("{{ $pet->breed->name ?? '' }}", "{{ $pet->breed_id ?? '' }}", true, true);
      $('#breed').append(breedOption).trigger('change');

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
      var colorOption = new Option("{{ $pet->color->name ?? '' }}", "{{ $pet->color_id ?? '' }}", true, true);
      $('#color').append(colorOption).trigger('change');

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
      var coatTypeOption = new Option("{{ $pet->coatType->name ?? '' }}", "{{ $pet->coat_type_id ?? '' }}", true, true);
      $('#coat_type').append(coatTypeOption).trigger('change');

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

      // If questionnaireId is provided, open the modal and select the corresponding tab(when being navigated from notifiction item)
      const questionnaireId = {{ $questionnaireId ?? 'null' }};
      if (questionnaireId) {
        @php
          $targetIndex = null;
          foreach ($serviceCategories as $idx => $cat) {
            $q = $questionnaires->firstWhere('service_category_id', $cat->id);
            if ($q && $q->id == $questionnaireId) {
              $targetIndex = $idx;
              break;
            }
          }
        @endphp

        // Open the questionnaire modal and select the corresponding tab (if found)
        const modal = document.getElementById('questionnaire_modal');
        const tabs = modal ? modal.querySelectorAll('[role="tab"]') : [];
        const targetIndex = {{ $targetIndex !== null ? $targetIndex : 'null' }};

        if (modal) {
          if (targetIndex !== null && tabs[targetIndex]) {
            tabs[targetIndex].checked = true;
            tabs[targetIndex].dispatchEvent(new Event('change', { bubbles: true }));
          }
          questionnaire_modal.showModal();
        }
      }

      // If target variable is provided, scroll to the bottom of the page(when being navigated from notifiction item)
      const target = "{{ $target ?? '' }}";
      if (target === 'vaccinations') {
        const $targetEl = $('#vaccinations_section');
        if ($targetEl.length) {
          const top = $targetEl.offset().top;
          const windowHeight = $(window).height();
          const elHeight = $targetEl.outerHeight();

          // Find nearest scrollable ancestor (common class in your layout is "overflow-auto")
          let $scrollContainer = $targetEl.closest('.overflow-auto');
          // fallback to window/document if no scrollable ancestor found
          const scrollTo = elHeight > windowHeight ? top : top - (windowHeight - elHeight) + 20;

          if ($scrollContainer.length) {
            // compute scrollTop within the container
            const containerTop = $scrollContainer.offset().top;
            const currentScroll = $scrollContainer.scrollTop();
            const scrollTarget = Math.max(0, currentScroll + (top - containerTop) - 20);
            $scrollContainer.animate({ scrollTop: scrollTarget }, 600);
          } else {
            $('html, body').animate({ scrollTop: Math.max(scrollTo, 0) }, 600);
          }
        }
      }

      if (existingVaccinations.length > 0) {
        existingVaccinations.forEach((vaccination) => addVaccinationRow(vaccination));
      } else {
        addVaccinationRow();
      }

      unlockPageScroll();

      $(document)
        .off('select2:open.pageScrollFix select2:close.pageScrollFix')
        .on('select2:open.pageScrollFix select2:close.pageScrollFix', function() {
          setTimeout(unlockPageScroll, 0);
        });

      $('dialog')
        .off('close.pageScrollFix')
        .on('close.pageScrollFix', function() {
          setTimeout(unlockPageScroll, 0);
        });
    });

    function saveQuestionnaire(ele, category, petId, questionnaireId) {
      let questionsAnswers = {};
      let status = 'pending';
      if (category.name.toLowerCase().includes('grooming')) {
        const questionnaireFields = [
          'groomed_before',
          'social_people',
          'social_pets',
          'crate_trained',
          'physical_issues',
          'medications'
        ];

        for (const field of questionnaireFields) {
          if (!$(`input[name="${field}"]:checked`).val()) {
            $('#alert_message').text('Please fill in all required fields in the questionnaire form.');
            alert_modal.showModal();
            return;
          }
        }

        questionsAnswers = {
          groomed_before: $('input[name="groomed_before"]:checked').val(),
          groomed_before_detail: $('#groomed_before_detail').val() || null,
          social_people: $('input[name="social_people"]:checked').val(),
          social_pets: $('input[name="social_pets"]:checked').val(),
          crate_trained: $('input[name="crate_trained"]:checked').val(),
          physical_issues: $('input[name="physical_issues"]:checked').val(),
          physical_issues_detail: $('#physical_issues_detail').val() || null,
          medications: $('input[name="medications"]:checked').val(),
          medications_detail: $('#medications_detail').val() || null,
          additional_note: $('#additional_note').val() || null
        };

        status = $('select[name="grooming_questionnaire_status"]').val();
      } else if (category.name.toLowerCase().includes('daycare')) {
        const questionnaireFields = [
          'daycare_around_people',
          'daycare_around_pets',
          'daycare_is_crate_trained',
          'daycare_visit_parks',
          'daycare_boarded',
          'daycare_attended'
        ];

        for (const field of questionnaireFields) {
          if (!$(`input[name="${field}"]:checked`).val()) {
            $('#alert_message').text('Please fill in all required fields in the questionnaire form.');
            alert_modal.showModal();
            return;
          }
        }

        questionsAnswers = {
          social_people: $('input[name="daycare_around_people"]:checked').val(),
          social_pets: $('input[name="daycare_around_pets"]:checked').val(),
          crate_trained: $('input[name="daycare_is_crate_trained"]:checked').val(),
          visit_parks: $('input[name="daycare_visit_parks"]:checked').val(),
          boarded: $('input[name="daycare_boarded"]:checked').val(),
          attended: $('input[name="daycare_attended"]:checked').val(),
          additional_comments: $('#daycare_additional_comments').val() || null
        };

        status = $('select[name="daycare_questionnaire_status"]').val();
      } else if (category.name.toLowerCase().includes('boarding')) {
        const questionnaireFields = [
          'boarding_around_people',
          'boarding_around_pets',
          'boarding_is_crate_trained',
          'boarding_visit_parks',
          'boarding_boarded',
          'boarding_attended'
        ];

        for (const field of questionnaireFields) {
          if (!$(`input[name="${field}"]:checked`).val()) {
            $('#alert_message').text('Please fill in all required fields in the questionnaire form.');
            alert_modal.showModal();
            return;
          }
        }

        questionsAnswers = {
          social_people: $('input[name="boarding_around_people"]:checked').val(),
          social_pets: $('input[name="boarding_around_pets"]:checked').val(),
          crate_trained: $('input[name="boarding_is_crate_trained"]:checked').val(),
          visit_parks: $('input[name="boarding_visit_parks"]:checked').val(),
          boarded: $('input[name="boarding_boarded"]:checked').val(),
          attended: $('input[name="boarding_attended"]:checked').val(),
          additional_comments: $('#boarding_additional_comments').val() || null
        };

        status = $('select[name="boarding_questionnaire_status"]').val();
      } else if (category.name.toLowerCase().includes('training')) {
        const questionnaireFields = [
          'primary_issue',
          'when_occurs',
          'consistent',
          'improvement_after_training',
          'age_when_received',
          'how_acquired',
          'where_sleeps',
          'wake_up_time',
          'bathroom_time',
          'takes_walks',
          'fenced_yard',
          'exercise_type',
          'eating_times',
          'eating_style',
          'getting_along',
          'who_cares_for_pet',
          'do_you_work',
          'pet_daily_activities',
          'who_lets_out',
          'with_unknown_people',
          'ever_bitten_someone',
          'tried_to_bite',
          'with_unknown_dogs',
          'fought_with_dogs'
        ];

        for (const field of questionnaireFields) {
          if ($(`[name="${field}"]`).length > 0 && !$(`[name="${field}"]`).val() && !$(`[name="${field}"]:checked`).val()) {
            $('#alert_message').text('Please fill in all required fields in the questionnaire form.');
            alert_modal.showModal();
            return;
          }
        }

        const receivedTrainings = [];
        $('input[name="received_training[]"]:checked').each(function() {
          receivedTrainings.push($(this).val());
        });

        const eatingTimes = [];
        $('input[name="eating_times[]"]:checked').each(function() {
          eatingTimes.push($(this).val());
        });

        const householdMembers = [];
        $('input[name="household_members[]"]:checked').each(function() {
          householdMembers.push($(this).val());
        });

        questionsAnswers = {
          primary_issue: $('input[name="primary_issue"]').val(),
          when_occurs: $('input[name="when_occurs"]').val(),
          consistent: $('input[name="consistent"]:checked').val(),
          received_training: receivedTrainings,
          improvement_after_training: $('input[name="improvement_after_training"]:checked').val(),
          age_when_received: $('input[name="age_when_received"]').val(),
          how_acquired: $('input[name="how_acquired"]').val(),
          where_sleeps: $('input[name="where_sleeps"]').val(),
          wake_up_time: $('input[name="wake_up_time"]').val(),
          bathroom_time: $('input[name="bathroom_time"]').val(),
          takes_walks: $('input[name="takes_walks"]:checked').val(),
          fenced_yard: $('input[name="fenced_yard"]:checked').val(),
          exercise_type: $('input[name="exercise_type"]').val(),
          eating_times: eatingTimes,
          eating_style: $('input[name="eating_style"]:checked').val(),
          household_members: householdMembers,
          getting_along: $('input[name="getting_along"]').val(),
          who_cares_for_pet: $('input[name="who_cares_for_pet"]').val(),
          do_you_work: $('input[name="do_you_work"]:checked').val(),
          pet_daily_activities: $('input[name="pet_daily_activities"]').val(),
          who_lets_out: $('input[name="who_lets_out"]').val(),
          with_unknown_people: $('input[name="with_unknown_people"]').val(),
          ever_bitten_someone: $('input[name="ever_bitten_someone"]:checked').val(),
          tried_to_bite: $('input[name="tried_to_bite"]:checked').val(),
          with_unknown_dogs: $('input[name="with_unknown_dogs"]').val(),
          fought_with_dogs: $('input[name="fought_with_dogs"]:checked').val(),
        }

        status = $('select[name="training_questionnaire_status"]').val();
      }

      $(ele).find('span.loading').removeClass('hidden');
      $(ele).find('span.iconify').addClass('hidden');
      $(ele).prop('disabled', true);

      $.ajax({
        url: '{{ route("save-pet-questionnaire") }}',
        method: 'POST',
        data: {
          questionnaire_id: questionnaireId,
          pet_id: petId,
          service_category_id: category.id,
          questions_answers: JSON.stringify(questionsAnswers),
          status: status,
          _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          $(ele).find('span.loading').addClass('hidden');
          $(ele).find('span.iconify').removeClass('hidden');
          $(ele).prop('disabled', false);
          questionnaire_modal.close();
          $('#success_message').text('Questionnaire saved successfully.');
          success_modal.showModal();
        },
        error: function(xhr) {
          $(ele).find('span.loading').addClass('hidden');
          $(ele).find('span.iconify').removeClass('hidden');
          $(ele).prop('disabled', false);
          $('#alert_message').text('Error saving questionnaire.');
          alert_modal.showModal();
        }
      });
    }

    function saveInitialTemperament(petId, temperamentId) {
      const temperamentData = {
        initial_greeting: $('input[name="temperament_initial_greeting"]:checked').val(),
        touch_body: $('input[name="temperament_touch_body"]:checked').val(),
        touch_legs: $('input[name="temperament_touch_legs"]:checked').val(),
        touch_feet: $('input[name="temperament_touch_feet"]:checked').val(),
        touch_tail: $('input[name="temperament_touch_tail"]:checked').val(),
        touch_face: $('input[name="temperament_touch_face"]:checked').val(),
        touch_nails: $('input[name="temperament_touch_nails"]:checked').val()
      };

      const requiredFields = ['initial_greeting', 'touch_body', 'touch_legs', 'touch_feet', 'touch_tail', 'touch_face', 'touch_nails'];
      for (const field of requiredFields) {
        if (!temperamentData[field]) {
          $('#alert_message').text('Please fill in all fields in the Initial Temperament Assessment.');
          alert_modal.showModal();
          return;
        }
      }

      const saveButton = $('button[onclick*="saveInitialTemperament"]');
      saveButton.find('span.loading').removeClass('hidden');
      saveButton.find('span.iconify').addClass('hidden');
      saveButton.prop('disabled', true);

      $.ajax({
        url: '{{ route("save-pet-initial-temperament") }}',
        method: 'POST',
        data: {
          temperament_id: temperamentId,
          pet_id: petId,
          temperament_data: JSON.stringify(temperamentData),
          _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          saveButton.find('span.loading').addClass('hidden');
          saveButton.find('span.iconify').removeClass('hidden');
          saveButton.prop('disabled', false);
          initial_temperament_modal.close();
          $('#success_message').text('Initial Temperament Assessment saved successfully.');
          success_modal.showModal();
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        },
        error: function(xhr) {
          saveButton.find('span.loading').addClass('hidden');
          saveButton.find('span.iconify').removeClass('hidden');
          saveButton.prop('disabled', false);
          $('#alert_message').text('Error saving Initial Temperament Assessment.');
          alert_modal.showModal();
        }
      });
    }

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
      if (!birthDate && !age) {
        $('#alert_message').text('Please fill in either Birth Date or Age.');
        alert_modal.showModal();
        return;
      }

      let hasIncompleteVaccination = false;
      const vaccinationData = [];
      $('#vaccinations_container .vaccination-row').each(function() {
        const id = ($(this).find('.vaccination-id').val() || '').trim();
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

        vaccinationData.push({ id: id, type: type, date: date, months: months });
      });

      if (hasIncompleteVaccination) {
        $('#alert_message').text('Please complete vaccination name, date, and month for each row or remove it.');
        alert_modal.showModal();
        return;
      }

      $('#vaccinations').val(JSON.stringify(vaccinationData));

      // collecting the remaining certificate ids
      var certificateIds = [];
      $('#certificates_container').children('div').each(function() {
        const id = $(this).find('input[id^="certificate_id_"]').val();
        if (id) {
          certificateIds.push(id);
        }
      });
      $('#certificate_ids').val(certificateIds.join(','));

      if (birthDate) {
        $('#birth_date').val(birthDate);
      }

      $('#update_form').submit();
    }

  </script>
@endsection