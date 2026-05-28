@extends('layouts.main')
@section('title', 'Edit Boarding Daily Workflow')

@section('page-css')
<link rel="stylesheet" href="{{ asset('src/libs/select2/select2.min.css') }}" />
<style>
.select2-container--default .select2-selection--multiple {
    min-height: 40px;
    height: auto;
    overflow-y: auto;
    overflow-x: hidden !important;
    white-space: normal !important;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    margin-top: 10px !important;
    margin-left: 10px !important;
}

.select2-container .select2-search--inline .select2-search__field {
    margin-top: 10px !important;
    margin-left: 10px !important;
}

.select2-container {
    width: 100% !important;
    min-width: 0 !important;
}

.workflow-tab {
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.workflow-tabs {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.75rem;
    overflow-x: auto;
}

.workflow-tabs .workflow-tab {
    flex: 0 0 calc((100% - 3.75rem) / 6);
    min-width: 10.5rem;
}

.workflow-tabs .workflow-tab .card-body>div:last-child {
    width: 100%;
    justify-content: center;
}

@media (min-width: 1280px) {
    .workflow-tabs {
        overflow-x: visible;
    }

    .workflow-tabs .workflow-tab {
        min-width: 0;
    }
}

.workflow-tab.active {
    background-color: color-mix(in oklab, var(--color-primary) 5%, transparent);
    border: 1px solid hsl(var(--p) / 0.1);
    box-shadow: 0 2px 4px -1px hsl(var(--p) / 0.1), 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.workflow-tab.active .card-body {
    background-color: transparent;
}

.workflow-tab.active .bg-base-200 {
    background-color: hsl(var(--p) / 0.1);
}

.process-item {
    cursor: pointer;
    transition: all 0.2s;
}

.process-item:hover .timeline-end {
    background-color: hsl(var(--b2) / 0.3);
    border-radius: 0.5rem;
}

.process-item.active .timeline-end {
    background-color: hsl(var(--p) / 0.1);
    border-radius: 0.5rem;
}

.process-item.active .timeline-middle>div {
    background-color: hsl(var(--p) / 0.2) !important;
    color: hsl(var(--p)) !important;
}

#file_activity_content li:last-child hr:last-of-type {
    display: none;
}
</style>
@endsection

@section('content')
<div class="flex items-center justify-between">
    <h3 class="text-lg font-medium">Edit Boarding Daily Workflow</h3>
    <div class="breadcrumbs hidden p-0 text-sm sm:inline">
        <ul>
            <li><a href="{{ route('dashboard') }}">PawPrints</a></li>
            <li><a href="{{ route('boarding-process-log') }}">Boarding Daily Workflow</a></li>
            <li>Edit</li>
        </ul>
    </div>
</div>
<div class="mt-3">
    @include('layouts.alerts')

    <form id="bulk_process_log_form">
        <div class="mt-3">
            <div class="p-4">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 mb-4">
                    <fieldset class="fieldset xl:col-span-6">
                        <legend class="fieldset-legend">Date</legend>
                        <input id="workflow_date" class="input input-bordered w-full"
                            value="{{ \Carbon\Carbon::parse($process->date)->format('Y-m-d') }}" disabled />
                    </fieldset>
                </div>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-3 2xl:grid-cols-4 pt-5">
                    <div class="col-span-1 xl:col-span-2 2xl:col-span-3">

                        {{-- Tabs Section (replacing cloud storage cards) --}}
                        <div class="workflow-tabs mb-6">
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md active"
                                data-tab="am-feeding-meds">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <span class="iconify lucide--sun text-primary size-6"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">AM Feeding Meds</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md"
                                data-tab="nose-to-tail">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <span class="iconify lucide--search text-success size-6"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">Nose to Tail Check</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md"
                                data-tab="treatment-lunch-rest">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <span class="iconify lucide--heart text-warning size-6"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">Treatment Lunch Rest</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md"
                                data-tab="pm-feeding-meds">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <span class="iconify lucide--moon text-info size-6"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">PM Feeding Meds</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md"
                                data-tab="prn">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            width="24"
                                            height="24"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="#f31260"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="lucide lucide-pill-icon lucide-pill">
                                            <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/>
                                            <path d="m8.5 8.5 7 7"/>
                                        </svg>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">PRN Meds</p>
                                    </div>
                                </div>
                            </div>
                            <div class="workflow-tab card bg-base-100 cursor-pointer shadow transition-all hover:shadow-md"
                                data-tab="reports">
                                <div class="card-body p-4 items-center text-center">
                                    <div class="bg-base-200 rounded-box size-12 flex items-center justify-center mb-2"
                                        style="width: 2.5rem; height: 2.5rem;">
                                        <span class="iconify lucide--file-text text-secondary size-6"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium">Reports</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Process Detail Section (Details Table) --}}
                        <h3 id="process_detail_title" class="mt-6 font-medium">Process Detail</h3>
                        <div class="mt-3">
                            <div class="card card-border bg-base-100">
                                <div class="card-body p-0">
                                    <div id="process_detail_search_bar"
                                        class="p-4 border-b border-base-300 flex flex-wrap items-center gap-4 text-sm text-base-content/70 justify-between">
                                        <label class="input input-sm w-fit">
                                            <span class="iconify lucide--search text-base-content/80 size-3.5"></span>
                                            <input class="w-24 sm:w-36" placeholder="Search pets"
                                                aria-label="Search pets" type="search" id="pet_details_search" />
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span id="pet_count_current_step">0</span> pets in this step
                                            <span class="text-base-content/50">|</span>
                                            <span id="pet_count_total">0</span> total on property
                                        </div>
                                        <div id="rest_nose_to_tail_inline" class="flex flex-wrap items-center gap-2"
                                            style="display: none;">
                                            <span>Time: <span id="rest_tlr_check_pet_time">—</span></span>
                                            <span>Employee: <span id="rest_tlr_check_pet_employee">—</span></span>
                                        </div>
                                    </div>
                                    <div class="overflow-auto">
                                        <table class="rounded-box mt-2 table" id="pet_details_table"
                                            style="display: none;">
                                            <thead>
                                                <tr>
                                                    <th class="pet-details-checkbox-col">
                                                        <input class="checkbox checkbox-sm" id="select_all_pets"
                                                            type="checkbox" />
                                                    </th>
                                                    <th>Pet Name</th>
                                                    <th>Customer</th>
                                                    <th class="food-column">Dry Food</th>
                                                    <th class="food-column">Wet Food</th>
                                                    <th class="meds-column">Meds</th>
                                                    <th class="issue-column" style="display:none;">Issue</th>
                                                </tr>
                                            </thead>
                                            <tbody id="pet_details_tbody">
                                                {{-- Pet rows will be dynamically loaded here --}}
                                            </tbody>
                                        </table>
                                        <div id="empty_state_message" class="p-8 text-center" style="display: none;">
                                            <p class="text-base-content/70 mt-0.5 text-xs" id="empty_state_text"></p>
                                        </div>
                                        <div id="no_details_message" class="p-8 text-center text-base-content/70">
                                            <p>Click on a process item to view pet details</p>
                                        </div>
                                        {{-- Check Pet Form --}}
                                        {{-- Check Pet Form Table --}}
                                        <div id="check_pet_form_container" class="p-4 overflow-auto"
                                            style="display: none;">
                                            <table class="table" id="check_pet_table">
                                                <thead id="check_pet_thead">
                                                    {{-- Table headers will be dynamically generated here --}}
                                                </thead>
                                                <tbody id="check_pet_tbody">
                                                    {{-- Table rows will be dynamically generated here --}}
                                                </tbody>
                                            </table>
                                        </div>
                                        {{-- Treatment Plan Form Table --}}
                                        <div id="treatment_plan_form_container" class="p-4 overflow-auto"
                                            style="display: none;">
                                            <table class="table" id="treatment_plan_table">
                                                <thead id="treatment_plan_thead">
                                                    {{-- Table headers will be dynamically generated here --}}
                                                </thead>
                                                <tbody id="treatment_plan_tbody">
                                                    {{-- Table rows will be dynamically generated here --}}
                                                </tbody>
                                            </table>
                                        </div>
                                        {{-- Treatment Lunch Rest: shared table for Treatment List / Treatments / Next Day's Treatment List / DNE List / Treatment Concern --}}
                                        <div id="treatment_lunch_rest_form_container" class="p-4 overflow-auto"
                                            style="display: none;">
                                            <div id="dne_list_search_bar"
                                                class="mb-3 flex flex-wrap items-center gap-3 justify-between"
                                                style="display: none;">
                                                <label class="input input-sm w-fit flex items-center gap-2">
                                                    <span
                                                        class="iconify lucide--search text-base-content/80 size-3.5"></span>
                                                    <input type="search" id="dne_list_search" class="w-36"
                                                        placeholder="Search pets" aria-label="Search pets" />
                                                </label>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <span class="text-sm text-base-content/70"><span
                                                            class="font-medium">Time:</span> <span
                                                            id="dne_list_time">—</span></span>
                                                    <span class="text-sm text-base-content/70"><span
                                                            class="font-medium">Employee:</span> <span
                                                            id="dne_list_employee">—</span></span>
                                                </div>
                                            </div>
                                            <table class="table" id="treatment_lunch_rest_table">
                                                <thead id="treatment_lunch_rest_thead">
                                                    {{-- Headers set by JS per step --}}
                                                </thead>
                                                <tbody id="treatment_lunch_rest_tbody">
                                                    {{-- Rows set by JS per step --}}
                                                </tbody>
                                            </table>
                                        </div>
                                        {{-- PRN Meds Form Table --}}
                                        <div id="prn_form_container" class="p-4 overflow-auto" style="display: none;">
                                          <table class="table" id="prn_table">
                                            <thead id="prn_thead"></thead>
                                            <tbody id="prn_tbody"></tbody>
                                          </table>
                                        </div>
                                        {{-- End of Day: report tables (loaded via AJAX) --}}
                                        <div id="end_of_day_form_container" class="p-4 overflow-auto"
                                            style="display: none;">
                                            <div id="end_of_day_report_content" class="min-h-[200px]">
                                                <p class="text-base-content/70 text-sm">Loading End of Day report…</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4" id="staff_sign_off_container" style="display: none;">
                                        <div class="fieldset grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <label class="fieldset-label" for="process_time">Time*</label>
                                                <input id="process_time" type="time"
                                                    class="input input-bordered w-full md:w-40" />
                                            </div>
                                            <div class="space-y-2">
                                                <label class="fieldset-label" for="staff_sign_off">Employee Sign
                                                    Off*</label>
                                                <select id="staff_sign_off" name="staff_sign_off" class="select w-full">
                                                    @php
                                                    $allStaffs = \App\Models\User::whereHas('roles', function ($query) {
                                                    $query->whereNot('title', 'customer');
                                                    })->with('profile')->get();
                                                    @endphp
                                                    @foreach($allStaffs as $staff)
                                                    <option value="{{ $staff->id }}">
                                                        {{ $staff->profile ? $staff->profile->first_name . ' ' . $staff->profile->last_name : $staff->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 p-4" id="save_details_btn_container"
                                        style="display: none;">
                                        <button type="button" id="save_pet_details_btn" class="btn btn-primary btn-sm">
                                            <span class="btn-text">Save</span>
                                            <span class="loading loading-spinner loading-sm hidden"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Sidebar: Overview --}}
                    <div class="hidden xl:col-span-1 xl:block 2xl:col-span-1">
                        <div class="card bg-base-100 card-border">
                            <div class="card-body gap-0">
                                <div class="flex items-center justify-between">
                                    <p class="font-medium">Overview</p>
                                </div>
                                <div class="card card-border bg-primary/5 border-primary/10 mt-3">
                                    <div class="card-body p-4">
                                        <p class="text-sm font-medium mb-2">Workflow Progress</p>
                                        <div id="workflow_progress" class="text-sm text-base-content/70">
                                            <p>On Property</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <p class="text-sm font-medium mb-2">Process Steps</p>
                                    <div class="card card-border bg-base-100">
                                        <div class="card-body p-4">
                                            <div class="mt-3 overflow-hidden">
                                                <ul id="file_activity_content"
                                                    class="timeline timeline-vertical timeline-snap-icon timeline-hr-sm -ms-[100%] ps-10">
                                                    <li>
                                                        <div class="timeline-end mx-5 my-2">
                                                            <p class="text-sm text-base-content/70">Select a tab to view
                                                                processes</p>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<dialog id="alert_modal" class="modal">
    <div class="modal-box">
        <div class="flex items-center justify-between text-lg font-medium">
            <span>Alert</span>
            <form method="dialog">
                <button class="btn btn-sm btn-ghost btn-circle" aria-label="Close modal">
                    <span class="iconify lucide--x size-4"></span>
                </button>
            </form>
        </div>
        <p class="py-4" id="alert_message"></p>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn btn-primary btn-sm">OK</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
@endsection

@section('page-js')
<script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>
<script>
const alert_modal = document.getElementById('alert_modal');
let currentTab = 'am-feeding-meds';
let currentProcessItem = null;
let selectedAppointmentIds = [];
let appointmentToPetMap = {};
let workflowData = @json($flows);
let lastLunchCheckinData = null;
let lastRestCheckinData = null;
let checkinRestMetaByAppointmentId = {};
let isLoadingCheckinRestMeta = false;
let yesterdayNextDayPetIds = [];
let yesterdayReportsPmIssues = {};

function isTruthyBoardingValue(value) {
    return value === true || value === 'true' || value === 1 || value === '1';
}

function getWorkflowItemId(item) {
    if (!item) return null;
    const workflowId = parseInt(item.workflow_id ?? item.pet_id ?? item.appointment_id, 10);
    return isNaN(workflowId) ? null : workflowId;
}

function getSelectedRequestAppointmentIds() {
    const appointmentIds = (selectedAppointmentIds || []).map(function(workflowId) {
        const pet = appointmentToPetMap[workflowId] || appointmentToPetMap[String(workflowId)] || null;
        if (pet && pet.appointment_id) {
            return parseInt(pet.appointment_id, 10);
        }

        const fallbackId = parseInt(workflowId, 10);
        return isNaN(fallbackId) ? null : fallbackId;
    }).filter(function(id) {
        return !isNaN(id) && id > 0;
    });

    return [...new Set(appointmentIds)];
}

function updateCheckinRestMetaFromData(checkinData) {
    if (!Array.isArray(checkinData)) return;
    checkinData.forEach(function(item) {
        const aid = getWorkflowItemId(item);
        if (aid === null) return;
        const restRequired = item.rest_required === true || item.rest_required === 'true' || item.rest_required === 1 ||
            item.rest_required === '1';
        const scheduledRest = item.scheduled_rest === true || item.scheduled_rest === 'true' || item.scheduled_rest ===
            1 || item.scheduled_rest === '1';
        checkinRestMetaByAppointmentId[String(aid)] = {
            is_assigned: restRequired || scheduledRest,
            rest_note: ((item.rest_note || '') + '').trim()
        };
    });
}

function ensureCheckinRestMetaLoaded() {
    if (isLoadingCheckinRestMeta) return;
    if (!selectedAppointmentIds || selectedAppointmentIds.length === 0) return;
    const hasMissing = selectedAppointmentIds.some(function(id) {
        return !(String(id) in checkinRestMetaByAppointmentId);
    });
    if (!hasMissing) return;

    isLoadingCheckinRestMeta = true;
    $.ajax({
        url: '{{ route("boarding-process-log-get-checkin-data") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            appointment_ids: getSelectedRequestAppointmentIds()
        },
        success: function(response) {
            const data = response && response.success && Array.isArray(response.data) ? response.data : [];
            updateCheckinRestMetaFromData(data);
            if (currentProcessItem === 'treatment_plan') {
                renderTreatmentPlanForm();
            }
        },
        complete: function() {
            isLoadingCheckinRestMeta = false;
        }
    });
}

const tabProcesses = {
    'am-feeding-meds': [{
            id: 'food_prep_am',
            name: 'Food Prep (AM)',
            icon: 'lucide--package'
        },
        {
            id: 'meds_prep_am',
            name: 'Meds Prep (AM)',
            icon: 'lucide--heart-pulse'
        },
        {
            id: 'feeding_am',
            name: 'Feeding Dispense (AM)',
            icon: 'lucide--cookie'
        },
        {
            id: 'meds_dispense_am',
            name: 'Meds Dispense (AM)',
            icon: 'lucide--check'
        },
        {
            id: 'reports_am',
            name: 'Reports',
            icon: 'lucide--file-text'
        }
    ],
    'nose-to-tail': [{
            id: 'check_pet',
            name: 'Check Pet',
            icon: 'lucide--search'
        },
        {
            id: 'treatment_plan',
            name: 'Treatment Plan',
            icon: 'lucide--heart'
        }
    ],
    'treatment-lunch-rest': [{
            id: 'treatments_tlr',
            name: 'Treatments',
            icon: 'lucide--heart'
        },
        {
            id: 'next_day_treatment_list_tlr',
            name: "Next Day's Treatment List",
            icon: 'lucide--calendar'
        },
        {
            id: 'lunch_tlr',
            name: 'Lunch',
            icon: 'lucide--book-open-text'
        },
        {
            id: 'rest_tlr',
            name: 'Rest',
            icon: 'lucide--moon'
        }
    ],
    'pm-feeding-meds': [{
            id: 'food_prep_pm',
            name: 'Food Prep (PM)',
            icon: 'lucide--package'
        },
        {
            id: 'meds_prep_pm',
            name: 'Meds Prep (PM)',
            icon: 'lucide--heart-pulse'
        },
        {
            id: 'feeding_pm',
            name: 'Feeding Dispense (PM)',
            icon: 'lucide--cookie'
        },
        {
            id: 'meds_dispense_pm',
            name: 'Meds Dispense (PM)',
            icon: 'lucide--check'
        },
        {
            id: 'reports_pm',
            name: 'Reports',
            icon: 'lucide--file-text'
        }
    ],
    'prn': [{
        id: 'prn_meds',
        name: 'PRN Meds',
        icon: 'lucide--heart-pulse'
    }],
    'reports': [{
            id: 'dne_list_am',
            name: 'DNE list (AM)',
            icon: 'lucide--ban'
        },
        {
            id: 'treatment_concern',
            name: 'Nose to Tail Issues/Concerns',
            icon: 'lucide--check'
        },
        {
            id: 'report_lunch',
            name: 'Lunch',
            icon: 'lucide--book-open-text'
        },
        {
            id: 'report_rest',
            name: 'Rest',
            icon: 'lucide--moon'
        },
        {
            id: 'dne_list_pm',
            name: 'DNE list (PM)',
            icon: 'lucide--ban'
        },
        {
            id: 'report_prn',
            name: 'PRN',
            icon: 'lucide--heart-pulse'
        },
        {
            id: 'end_of_day',
            name: 'End of Day',
            icon: 'lucide--file-text'
        }
    ]
};

function updateProcessDetailTitle(stepTitle = null) {
    const baseTitle = 'Process Detail';
    const title = stepTitle ? `${baseTitle}: ${stepTitle}` : baseTitle;
    $('#process_detail_title').text(title);
}

@foreach($processes as $proc)
    @if($proc->appointment)
        @php
            $workflowPets = $proc->appointment->familyPets ?? collect();
            if ($workflowPets->isEmpty() && $proc->appointment->pet) {
                $workflowPets = collect([$proc->appointment->pet]);
            }
            $isFamilyAppointment = $workflowPets->count() > 1;
        @endphp
        @foreach($workflowPets as $workflowPet)
            @if($workflowPet)
                @php
                    $workflowId = $isFamilyAppointment ? (int) $workflowPet->id : (int) $proc->appointment_id;
                @endphp
                appointmentToPetMap[{{ $workflowId }}] = {
                    workflow_id: {{ $workflowId }},
                    pet_id: {{ $workflowPet->id }},
                    pet_name: @json($workflowPet->name ?? 'N/A'),
                    pet_img: @json($workflowPet->pet_img ?? ''),
                    customer_name: @json(
                        $proc->appointment->customer && $proc->appointment->customer->profile
                            ? ($proc->appointment->customer->profile->first_name . ' ' . $proc->appointment->customer->profile->last_name)
                            : 'N/A'
                    ),
                    customer_avatar: @json(
                        $proc->appointment->customer && $proc->appointment->customer->profile
                            ? ($proc->appointment->customer->profile->avatar_img ?? '')
                            : ''
                    ),
                    appointment_id: {{ $proc->appointment_id }}
                };
                selectedAppointmentIds.push({{ $workflowId }});
            @endif
        @endforeach
    @endif
@endforeach

selectedAppointmentIds = [...new Set(selectedAppointmentIds.map(function(id) { return parseInt(id, 10); }).filter(function(id) { return !isNaN(id); }))];

function fetchYesterdayNextDayPetIds() {
    const date = $('#workflow_date').val();
    if (!date || selectedAppointmentIds.length === 0) {
        yesterdayNextDayPetIds = [];
        yesterdayReportsPmIssues = {};
        return;
    }
    $.ajax({
        url: '{{ route("boarding-process-log-treatment-list-yesterday-pet-ids") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            date: date,
            appointment_ids: getSelectedRequestAppointmentIds(),
            workflow_ids: selectedAppointmentIds
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && Array.isArray(response.yesterday_pet_ids)) {
                yesterdayNextDayPetIds = response.yesterday_pet_ids;
                yesterdayReportsPmIssues = response.yesterday_reports_pm_issues || {};
            } else {
                yesterdayNextDayPetIds = [];
                yesterdayReportsPmIssues = {};
            }
            // Re-render current TLR step so Treatment List includes yesterday's Next Day pets
            if (currentTab === 'treatment-lunch-rest' && currentProcessItem) {
                if (currentProcessItem === 'lunch_tlr') loadPetDetails();
                else if (currentProcessItem === 'rest_tlr') renderRestForm(lastRestCheckinData);
                else if (currentProcessItem === 'treatment_list_tlr') renderTreatmentListTLRForm();
                else if (currentProcessItem === 'treatments_tlr') renderTreatmentsTLRForm();
                else if (currentProcessItem === 'next_day_treatment_list_tlr')
                    renderNextDayTreatmentListTLRForm();
            }
        },
        error: function() {
            yesterdayNextDayPetIds = [];
            yesterdayReportsPmIssues = {};
        }
    });
}

function getTreatmentListBasePetIds() {
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const todayIds = (treatmentPlanData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmData = workflowData['reports_am'] || {};
    const reportsAmIds = (reportsAmData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const merged = [...new Set([...todayIds, ...yesterdayIds, ...reportsAmIds])];
    return merged.filter(id => appointmentToPetMap[id]);
}

/** Pet IDs for Treatment/Concern step only: nose-to-tail issues (treatment plan + yesterday next day). Excludes DNE AM/PM. */
function getTreatmentConcernPetIds() {
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const todayIds = (treatmentPlanData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const merged = [...new Set([...todayIds, ...yesterdayIds])];
    return merged.filter(id => appointmentToPetMap[id]);
}

function getLunchStepPetIds(checkinData) {
    const reportsAmData = workflowData['reports_am'] || {};
    const reportsAmIds = (reportsAmData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    let alwaysLunchIds = [];
    if (checkinData && Array.isArray(checkinData)) {
        checkinData.forEach(function(item) {
            const aid = getWorkflowItemId(item);
            if (aid === null) return;
            if ((item.lunch_dry === true || item.lunch_dry === 'true') || (item.lunch_wet === true || item
                    .lunch_wet === 'true')) {
                alwaysLunchIds.push(aid);
            }
        });
    }
    const merged = [...new Set([...reportsAmIds, ...yesterdayIds, ...alwaysLunchIds])];
    const selectedSet = new Set((selectedAppointmentIds || []).map(id => parseInt(id, 10)));
    return merged.filter(id => selectedSet.has(parseInt(id, 10)) && appointmentToPetMap[id]);
}

function updatePetCountsDisplay(stepCount, totalCount) {
    $('#pet_count_current_step').text(stepCount != null ? stepCount : 0);
    $('#pet_count_total').text(totalCount != null ? totalCount : selectedAppointmentIds.length);
}

$('.workflow-tab').on('click', function() {
    $('.workflow-tab').removeClass('active');
    $(this).addClass('active');
    currentTab = $(this).data('tab');
    if (currentTab === 'reports' || currentTab === 'prn') {
        $('#process_detail_search_bar').hide();
    } else {
        $('#process_detail_search_bar').show();
    }
    if (currentTab === 'treatment-lunch-rest') fetchYesterdayNextDayPetIds();
    loadProcessItems(currentTab);
    currentProcessItem = null;
    updateProcessDetailTitle();
    updatePetCountsDisplay(0, null);
    $('#pet_details_table').hide();
    $('#empty_state_message').hide();
    $('#check_pet_form_container').hide();
    $('#treatment_plan_form_container').hide();
    $('#treatment_lunch_rest_form_container').hide();
    $('#prn_form_container').hide();
    $('#end_of_day_form_container').hide();
    $('#no_details_message').show();
    $('#save_details_btn_container').hide();
    $('#staff_sign_off_container').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('.food-column').show();
    $('.meds-column').show();
});

function hasRestStepToday() {
    const checkPetData = workflowData['check_pet'] || {};
    const checkData = checkPetData.check_data || {};
    return selectedAppointmentIds.some(function(aid) {
        const petData = checkData[aid] || {};
        return Object.values(petData).some(function(p) {
            return p.status === 'issue';
        });
    });
}

function updateProcessStatus(processId, statusText) {
    const $processItem = $(`.process-item[data-process-id="${processId}"]`);
    $processItem.removeClass('opacity-70').addClass('opacity-100');
    const $statusElement = $processItem.find('.timeline-end p');
    if ($statusElement.length) {
        $statusElement.text(statusText);
    } else {
        $processItem.find('.timeline-end div:first').after(
            `<p class="text-base-content/70 mt-0.5 text-xs">${statusText}</p>`);
    }
}

function loadProcessItems(tab) {
    let processes = tabProcesses[tab] || [];
    if (tab === 'reports') {
        var treatmentListData = workflowData['treatment_plan'] || {};
        var treatmentListSelectedIds = treatmentListData.selected_pet_ids || [];
        if (!treatmentListSelectedIds.length) {
            processes = processes.filter(function(p) {
                return p.id !== 'treatment_concern';
            });
        }
    }
    const $content = $('#file_activity_content');

    $('#end_of_day_form_container').hide();

    if (processes.length === 0) {
        $content.html(`
        <li>
          <div class="timeline-end mx-5 my-2">
            <p class="text-sm text-base-content/70">No processes available for this tab</p>
          </div>
        </li>
      `);
        return;
    }

    const getProcessColor = (processId) => {
        // Feeding / food prep
        if (processId.includes('food_prep') || processId.includes('feeding')) {
            return 'bg-success/20 text-success';
        }
        // Meds prep / dispense
        if (processId.includes('meds_prep') || processId.includes('meds_dispense')) {
            return 'bg-success/20 text-warning';
        }
        // Nose-to-tail check
        if (processId === 'check_pet') {
            return 'bg-success/20 text-info';
        }
        // Treatment related
        if (processId === 'treatment' || processId === 'treatment_plan' || processId === 'treatment_list') {
            return 'bg-success/20 text-error';
        }
        // Treatment Lunch Rest
        if (processId === 'lunch_tlr' || processId === 'rest_tlr' || processId === 'treatment_list_tlr' ||
            processId === 'treatments_tlr' || processId === 'next_day_treatment_list_tlr') {
            return 'bg-success/20 text-primary';
        }
        // Reports
        if (
            processId === 'reports_am' ||
            processId === 'reports_pm' ||
            processId === 'dne_list_am' ||
            processId === 'dne_list_pm' ||
            processId === 'report_lunch' ||
            processId === 'report_rest' ||
            processId === 'report_prn' ||
            processId === 'treatment_concern' ||
            processId === 'end_of_day'
        ) {
            return 'bg-success/20 text-secondary';
        }
        if (processId === 'prn_meds') {
            return 'bg-error/20 text-error';
        }
        // Fallback: still a visible grey circle, not white
        return 'bg-base-300 text-base-content';
    };

    let html = '';
    processes.forEach((process, index) => {
        const colorClass = getProcessColor(process.id);
        const processData = workflowData[process.id];
        const isActive = processData ? 'opacity-100' : 'opacity-70';
        const showCompleted = processData && !(process.id === 'end_of_day' && tab === 'reports') && !(process.id === 'report_prn' && tab === 'reports');

        html += `
        <li class="process-item ${isActive}" data-process-id="${process.id}">
          ${index > 0 ? '<hr />' : ''}
          <div class="timeline-middle">
            <div class="${colorClass} flex items-center rounded-full p-2">
              <span class="iconify ${process.icon} size-4"></span>
            </div>
          </div>
          <div class="timeline-end my-2.5 w-full px-4">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium cursor-pointer">${process.name}</span>
            </div>
            ${showCompleted ? `<p class="text-base-content/70 mt-0.5 text-xs">Completed</p>` : ''}
          </div>
          <hr />
        </li>
      `;
    });

    $content.html(html);

    $('.process-item').on('click', function() {
        $('.process-item').removeClass('active');
        $(this).addClass('active');
        currentProcessItem = $(this).data('process-id');
        const stepTitle = $(this).find('.timeline-end span').first().text().trim();
        updateProcessDetailTitle(stepTitle || null);
        toggleTableColumns(currentProcessItem);
        loadPetDetails();
    });
}

function toggleTableColumns(processId) {
    const reportsTabStepsWithIssue = ['dne_list_am', 'dne_list_pm', 'report_lunch', 'report_rest', 'report_prn', 'treatment_concern',
        'end_of_day'
    ];
    if (processId === 'reports_am' || processId === 'reports_pm') {
        $('.pet-details-checkbox-col').hide();
    } else {
        $('.pet-details-checkbox-col').show();
    }
    if (processId === 'reports_am' || processId === 'reports_pm') {
        // AM/PM Feeding Meds → Reports: show only Issue column
        $('.food-column').hide();
        $('.meds-column').hide();
        $('.issue-column').show();
    } else if (currentTab === 'reports' && reportsTabStepsWithIssue.includes(processId)) {
        $('.food-column').hide();
        $('.meds-column').hide();
        $('.issue-column').show();
    } else if (processId === 'food_prep_am' || processId === 'food_prep_pm' || processId === 'feeding_am' ||
        processId === 'feeding_pm') {
        $('.food-column').show();
        $('.meds-column').hide();
        $('.issue-column').hide();
    } else if (processId === 'meds_prep_am' || processId === 'meds_prep_pm' || processId === 'meds_dispense_am' ||
        processId === 'meds_dispense_pm') {
        $('.food-column').hide();
        $('.meds-column').show();
        $('.issue-column').hide();
    } else {
        $('.food-column').show();
        $('.meds-column').show();
        $('.issue-column').hide();
    }
}

function loadPetDetails() {
    if (!currentProcessItem || selectedAppointmentIds.length === 0) {
        return;
    }

    $('#pet_details_table').hide();
    $('#empty_state_message').hide();
    $('#no_details_message').hide();
    $('#check_pet_form_container').hide();
    $('#treatment_plan_form_container').hide();
    $('#treatment_lunch_rest_form_container').hide();
    $('#prn_form_container').hide();
    $('#end_of_day_form_container').hide();
    $('#dne_list_search_bar').hide();
    $('#save_details_btn_container').hide();
    $('#staff_sign_off_container').hide();

    if (currentProcessItem === 'check_pet') {
        $('#check_pet_form_container').show();
        $('#check_pet_tbody').html('<tr><td colspan="12" class="text-center p-4">Loading...</td></tr>');
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                appointment_ids: getSelectedRequestAppointmentIds()
            },
            success: function(response) {
                const checkinData = response.success && response.data ? response.data : [];
                updateCheckinRestMetaFromData(checkinData);
                renderCheckPetForm();
                loadStaffSignOff();
                $('#save_details_btn_container').show();
                $('#staff_sign_off_container').show();
            },
            error: function() {
                renderCheckPetForm();
                loadStaffSignOff();
                $('#save_details_btn_container').show();
                $('#staff_sign_off_container').show();
            }
        });
        return;
    }

    if (currentProcessItem === 'treatment_plan') {
        const hasTreatmentPlanData = renderTreatmentPlanForm();
        if (hasTreatmentPlanData) {
            $('#treatment_plan_form_container').show();
            loadStaffSignOff();
            $('#save_details_btn_container').show();
            $('#staff_sign_off_container').show();
        } else {
            $('#save_details_btn_container').hide();
            $('#staff_sign_off_container').hide();
        }
        return;
    }

    if (currentProcessItem === 'lunch_tlr') {
        $('#treatment_lunch_rest_form_container').show();
        $('#treatment_lunch_rest_thead').html('');
        $('#treatment_lunch_rest_tbody').html('<tr><td colspan="6" class="text-center p-4">Loading...</td></tr>');
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                appointment_ids: getSelectedRequestAppointmentIds()
            },
            success: function(response) {
                const data = response.success && response.data ? response.data : null;
                lastLunchCheckinData = data;
                const hasLunchData = renderLunchForm(data);
                if (hasLunchData) {
                    loadStaffSignOff();
                    $('#save_details_btn_container').show();
                    $('#staff_sign_off_container').show();
                } else {
                    $('#save_details_btn_container').hide();
                    $('#staff_sign_off_container').hide();
                }
            },
            error: function() {
                lastLunchCheckinData = null;
                const hasLunchData = renderLunchForm(null);
                if (hasLunchData) {
                    loadStaffSignOff();
                    $('#save_details_btn_container').show();
                    $('#staff_sign_off_container').show();
                } else {
                    $('#save_details_btn_container').hide();
                    $('#staff_sign_off_container').hide();
                }
            }
        });
        return;
    }
    if (currentProcessItem === 'rest_tlr') {
        $('#treatment_lunch_rest_form_container').show();
        $('#treatment_lunch_rest_thead').html('');
        $('#treatment_lunch_rest_tbody').html('<tr><td colspan="3" class="text-center p-4">Loading...</td></tr>');
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                appointment_ids: getSelectedRequestAppointmentIds()
            },
            success: function(response) {
                const data = response.success && response.data ? response.data : null;
                lastRestCheckinData = data;
                const hasRestData = renderRestForm(data);
                if (hasRestData) {
                    loadStaffSignOff();
                    $('#save_details_btn_container').show();
                    $('#staff_sign_off_container').show();
                } else {
                    $('#save_details_btn_container').hide();
                    $('#staff_sign_off_container').hide();
                }
            },
            error: function() {
                lastRestCheckinData = null;
                const hasRestData = renderRestForm(null);
                if (hasRestData) {
                    loadStaffSignOff();
                    $('#save_details_btn_container').show();
                    $('#staff_sign_off_container').show();
                } else {
                    $('#save_details_btn_container').hide();
                    $('#staff_sign_off_container').hide();
                }
            }
        });
        return;
    }
    if (currentProcessItem === 'treatment_list_tlr') {
        renderTreatmentListTLRForm();
        $('#treatment_lunch_rest_form_container').show();
        loadStaffSignOff();
        $('#save_details_btn_container').show();
        $('#staff_sign_off_container').show();
        return;
    }
    if (currentProcessItem === 'treatments_tlr') {
        const hasTreatmentsData = renderTreatmentsTLRForm();
        if (hasTreatmentsData) {
            loadStaffSignOff();
            $('#save_details_btn_container').show();
            $('#staff_sign_off_container').show();
        } else {
            $('#save_details_btn_container').hide();
            $('#staff_sign_off_container').hide();
        }
        return;
    }
    if (currentProcessItem === 'next_day_treatment_list_tlr') {
        const hasNextDayData = renderNextDayTreatmentListTLRForm();
        if (hasNextDayData) {
            loadStaffSignOff();
            $('#save_details_btn_container').show();
            $('#staff_sign_off_container').show();
        } else {
            $('#save_details_btn_container').hide();
            $('#staff_sign_off_container').hide();
        }
        return;
    }

    if (currentProcessItem === 'dne_list_am' || currentProcessItem === 'dne_list_pm') {
        const amOrPm = currentProcessItem === 'dne_list_am' ? 'am' : 'pm';
        $('#treatment_lunch_rest_form_container').show();
        $('#treatment_lunch_rest_thead').html('');
        $('#treatment_lunch_rest_tbody').html('<tr><td colspan="5" class="text-center p-4">Loading...</td></tr>');
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                appointment_ids: getSelectedRequestAppointmentIds()
            },
            success: function(response) {
                renderDneListForm(amOrPm, response.success && response.data ? response.data : null);
                loadStaffSignOff();
            },
            error: function() {
                renderDneListForm(amOrPm, null);
                loadStaffSignOff();
            }
        });
        return;
    }
    if (currentProcessItem === 'report_lunch') {
        $('#treatment_lunch_rest_form_container').show();
        $('#dne_list_search_bar').show();
        $('#dne_list_search').val('');
        const lunchTlrData = workflowData['lunch_tlr'] || {};
        const lunchTime = lunchTlrData.process_time || '—';
        const staffIdToName = {};
        $('#staff_sign_off option').each(function() {
            const v = $(this).val();
            if (v) staffIdToName[v] = $(this).text();
        });
        const staffIds = lunchTlrData.staff_sign_off || [];
        const lunchEmployee = (staffIds[0] != null ? (staffIdToName[String(staffIds[0])] || '—') : '—');
        $('#dne_list_time').text(lunchTime);
        $('#dne_list_employee').text(lunchEmployee);
        const lunchPetIds = (lunchTlrData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
        $('#treatment_lunch_rest_thead').html(
            '<tr><th style="min-width: 180px;">Pet</th><th style="min-width: 180px;">Customer</th></tr>');
        let bodyHtml = '';
        if (lunchPetIds.length === 0) {
            bodyHtml =
                '<tr data-empty><td colspan="2" class="text-center p-4 text-base-content/70">No pets listed. Complete Lunch step in Treatment Lunch Rest first.</td></tr>';
        } else {
            lunchPetIds.forEach(appointmentId => {
                const pet = appointmentToPetMap[appointmentId];
                if (!pet) return;
                const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                    '{{ asset("images/no_image.jpg") }}';
                const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                    .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
                const petName = (pet.pet_name || '').toLowerCase();
                const customerName = (pet.customer_name || '').toLowerCase();
                bodyHtml += '<tr class="hover:bg-base-200 report-lunch-row" data-appointment-id="' +
                    appointmentId + '" data-pet-name="' + petName.replace(/"/g, '&quot;') +
                    '" data-customer-name="' + customerName.replace(/"/g, '&quot;') + '">';
                bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + petAvatarUrl +
                    '" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet.pet_name ||
                        'N/A') + '</span></div></td>';
                bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + customerAvatarUrl +
                    '" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet
                        .customer_name || 'N/A') + '</span></div></td>';
                bodyHtml += '</tr>';
            });
        }
        $('#treatment_lunch_rest_tbody').html(bodyHtml);
        $('#dne_list_search').off('input').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#treatment_lunch_rest_tbody tr.report-lunch-row').each(function() {
                const $row = $(this);
                if ($row.find('td[colspan]').length) {
                    $row.show();
                    return;
                }
                const match = !term || ($row.data('pet-name') || '').indexOf(term) !== -1 || ($row.data(
                    'customer-name') || '').indexOf(term) !== -1;
                $row.toggle(match);
            });
        });
        return;
    }
    if (currentProcessItem === 'report_rest') {
        $('#treatment_lunch_rest_form_container').show();
        $('#treatment_lunch_rest_thead').html('');
        $('#treatment_lunch_rest_tbody').html('<tr><td colspan="3" class="text-center p-4">Loading...</td></tr>');
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                appointment_ids: getSelectedRequestAppointmentIds()
            },
            success: function(response) {
                const checkinData = response.success && response.data ? response.data : null;
                renderReportRestForm(checkinData);
            },
            error: function() {
                renderReportRestForm(null);
            }
        });
        return;
    }
    if (currentProcessItem === 'treatment_concern') {
        renderTreatmentConcernForm();
        $('#treatment_lunch_rest_form_container').show();
        loadStaffSignOff();
        return;
    }
    if (currentProcessItem === 'prn_meds') {
        $('#prn_form_container').show();
        updatePetCountsDisplay(null, selectedAppointmentIds.length);
        loadStaffSignOff();
        $.ajax({
            url: '{{ route("boarding-process-log-get-checkin-data") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', appointment_ids: getSelectedRequestAppointmentIds() },
            success: function(response) {
                const checkinData = response.success && response.data ? response.data : null;
                renderPrnForm(checkinData);
            },
            error: function() {
                renderPrnForm(null);
            }
        });
        return;
    }
    if (currentProcessItem === 'report_prn') {
        $('#prn_form_container').show();
        updatePetCountsDisplay(null, selectedAppointmentIds.length);
        renderReportPrnForm();
        return;
    }
    if (currentProcessItem === 'end_of_day') {
        renderEndOfDayForm();
        $('#end_of_day_form_container').show();
        loadStaffSignOff();
        return;
    }

    // Default: pet details table
    const totalCols = $('.food-column, .meds-column, .issue-column').length + 3;
    $('#pet_details_tbody').html(`<tr><td colspan="${totalCols}" class="text-center p-4">Loading...</td></tr>`);
    $('#pet_details_table').show();
    toggleTableColumns(currentProcessItem);

    $.ajax({
        url: '{{ route("boarding-process-log-get-checkin-data") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            appointment_ids: getSelectedRequestAppointmentIds()
        },
        success: function(response) {
            if (response.success && response.data) {
                renderPetDetailsTable(response.data);
                toggleTableColumns(currentProcessItem);
                loadStaffSignOff();
                const isFoodStep =
                    (currentTab === 'am-feeding-meds' && (currentProcessItem === 'food_prep_am' ||
                        currentProcessItem === 'feeding_am')) ||
                    (currentTab === 'pm-feeding-meds' && (currentProcessItem === 'food_prep_pm' ||
                        currentProcessItem === 'feeding_pm'));
                const isFoodNoRecord = isFoodStep && $('#pet_details_tbody tr[data-appointment-id]')
                    .length === 0;
                const isMedsStep = ['meds_prep_am', 'meds_dispense_am', 'meds_prep_pm', 'meds_dispense_pm']
                    .includes(currentProcessItem);
                const isMedsNoRecord = isMedsStep && $('#pet_details_tbody tr[data-appointment-id]')
                    .length === 0;
                const isReportsNoIssue = (currentProcessItem === 'reports_am' || currentProcessItem ===
                    'reports_pm') && $('#pet_details_tbody tr[data-appointment-id]').length === 0;
                if (isFoodNoRecord || isMedsNoRecord || isReportsNoIssue) {
                    $('#save_details_btn_container').hide();
                    $('#staff_sign_off_container').hide();
                } else {
                    $('#save_details_btn_container').show();
                    $('#staff_sign_off_container').show();
                }
            } else {
                const totalCols = $('.food-column, .meds-column, .issue-column').length + 3;
                $('#pet_details_tbody').html(
                    `<tr><td colspan="${totalCols}" class="text-center p-4 text-base-content/70">No checkin data available</td></tr>`
                );
                $('#save_details_btn_container').hide();
                $('#staff_sign_off_container').hide();
            }
        },
        error: function() {
            const totalCols = $('.food-column, .meds-column, .issue-column').length + 3;
            $('#pet_details_tbody').html(
                `<tr><td colspan="${totalCols}" class="text-center p-4 text-error">Error loading data</td></tr>`
            );
            $('#save_details_btn_container').hide();
            $('#staff_sign_off_container').hide();
        }
    });
}

function isFlowChecked(value) {
    return value === true || value === 'true';
}

function getFoodRowsFromFlows(flows, type) {
    const listKey = type === 'dry' ? 'dry_food_list' : 'wet_food_list';
    const singleKey = type === 'dry' ? 'dry_food' : 'wet_food';
    const rows = Array.isArray(flows[listKey]) ? flows[listKey].filter(item => item && typeof item === 'object') : [];

    if (rows.length > 0) {
        return rows;
    }

    const fallback = flows[singleKey] || {};
    if (fallback.brand || fallback.amount || isFlowChecked(fallback.dispense_am) || isFlowChecked(fallback
            .dispense_pm) || isFlowChecked(fallback.dispense_lunch)) {
        return [fallback];
    }

    return [];
}

function getMedicationRowsFromFlows(flows) {
    const rows = Array.isArray(flows.meds_list) ? flows.meds_list.filter(item => item && typeof item === 'object') : [];
    if (rows.length > 0) {
        return rows;
    }

    const fallback = flows.meds || {};
    if (fallback.name || fallback.amount || isFlowChecked(fallback.dispense_am) || isFlowChecked(fallback
            .dispense_pm) || isFlowChecked(fallback.dispense_rest) || isFlowChecked(fallback.dispense_prn)) {
        return [fallback];
    }

    return [];
}

function hasFoodDispense(rows, period) {
    return rows.some(row => isFlowChecked(row && row[period]));
}

function hasMedicationDispense(rows, period) {
    return rows.some(row => isFlowChecked(row && row[period]));
}

function buildFoodDisplayText(rows, displayPeriod = null) {
    if (!rows || rows.length === 0) {
        return '-';
    }

    const periodKey = displayPeriod === 'am' ? 'dispense_am' : (displayPeriod === 'pm' ? 'dispense_pm' : null);
    const rowsToRender = periodKey ? rows.filter(row => isFlowChecked(row && row[periodKey])) : rows;
    if (rowsToRender.length === 0) {
        return '-';
    }

    const rowTexts = rowsToRender.map(row => {
        const labels = [];
        if (displayPeriod === 'am') {
            labels.push('AM');
        } else if (displayPeriod === 'pm') {
            labels.push('PM');
        } else {
            if (isFlowChecked(row.dispense_am)) labels.push('AM');
            if (isFlowChecked(row.dispense_pm)) labels.push('PM');
            if (isFlowChecked(row.dispense_lunch)) labels.push('Lunch');
        }

        const parts = [];
        if (row.brand) parts.push(row.brand);
        if (row.amount) parts.push(row.amount);
        if (labels.length > 0) parts.push(labels.join(' + '));

        return parts.join(' ').trim();
    }).filter(Boolean);

    return rowTexts.length > 0 ? rowTexts.join(' | ') : '-';
}

function buildMedicationDisplayText(rows, displayPeriod = null) {
    if (!rows || rows.length === 0) {
        return '-';
    }

    const periodKey = displayPeriod === 'am' ? 'dispense_am' : (displayPeriod === 'pm' ? 'dispense_pm' : null);
    const rowsToRender = periodKey ? rows.filter(row => isFlowChecked(row && row[periodKey])) : rows;
    if (rowsToRender.length === 0) {
        return '-';
    }

    const rowTexts = rowsToRender.map(row => {
        const labels = [];
        if (displayPeriod === 'am') {
            if (isFlowChecked(row.dispense_am)) labels.push('AM');
            if (isFlowChecked(row.dispense_prn)) labels.push('PRN');
        } else if (displayPeriod === 'pm') {
            if (isFlowChecked(row.dispense_pm)) labels.push('PM');
            if (isFlowChecked(row.dispense_prn)) labels.push('PRN');
        } else {
            if (isFlowChecked(row.dispense_am)) labels.push('AM');
            if (isFlowChecked(row.dispense_pm)) labels.push('PM');
            if (isFlowChecked(row.dispense_rest)) labels.push('Rest');
            if (isFlowChecked(row.dispense_before_bed)) labels.push('Before Bed');
            if (isFlowChecked(row.dispense_prn)) labels.push('PRN');
            if (isFlowChecked(row.dispense_custom_time)) {
                labels.push(row.custom_time ? `Custom Time (${row.custom_time})` : 'Custom Time');
            }
        }

        const parts = [];
        if (row.name) parts.push(row.name);
        if (row.amount) parts.push(row.amount);
        if (labels.length > 0) parts.push(labels.join(' + '));

        return parts.join(' ').trim();
    }).filter(Boolean);

    return rowTexts.length > 0 ? rowTexts.join(' | ') : '-';
}

function renderPetDetailsTable(data) {
    let html = '';

    const isAmFeedingReport = currentProcessItem === 'reports_am';
    const isPmFeedingReport = currentProcessItem === 'reports_pm';
    const isFeedingReport = isAmFeedingReport || isPmFeedingReport;
    const currentData = workflowData[currentProcessItem] || {};
    const savedIssues = currentData.issues || {};

    // Filter data for Reports: only show pets NOT checked in Feeding Dispense (AM or PM)
    let filteredData = data;
    if (isAmFeedingReport) {
        const feedingAmData = workflowData['feeding_am'] || {};
        const feedingAmPetIds = feedingAmData.selected_pet_ids ? feedingAmData.selected_pet_ids.map(id => parseInt(
            id)) : [];
        filteredData = data.filter(item => {
            const workflowId = getWorkflowItemId(item);
            return workflowId === null || !feedingAmPetIds.includes(workflowId);
        });
    }
    if (isPmFeedingReport) {
        const feedingPmData = workflowData['feeding_pm'] || {};
        const feedingPmPetIds = feedingPmData.selected_pet_ids ? feedingPmData.selected_pet_ids.map(id => parseInt(
            id)) : [];
        filteredData = data.filter(item => {
            const workflowId = getWorkflowItemId(item);
            return workflowId === null || !feedingPmPetIds.includes(workflowId);
        });
    }

    // Meal/Meds preparation: only show pets that have the corresponding AM/PM dispense checked at check-in
    const isAmFood = (currentTab === 'am-feeding-meds' && (currentProcessItem === 'food_prep_am' ||
        currentProcessItem === 'feeding_am'));
    const isPmFood = (currentTab === 'pm-feeding-meds' && (currentProcessItem === 'food_prep_pm' ||
        currentProcessItem === 'feeding_pm'));
    const isAmMeds = (currentTab === 'am-feeding-meds' && (currentProcessItem === 'meds_prep_am' ||
        currentProcessItem === 'meds_dispense_am'));
    const isPmMeds = (currentTab === 'pm-feeding-meds' && (currentProcessItem === 'meds_prep_pm' ||
        currentProcessItem === 'meds_dispense_pm'));
    if (isAmFood || isPmFood || isAmMeds || isPmMeds) {
        filteredData = filteredData.filter(item => {
            const flows = (item.checkin || {}).flows || {};
            const dryFoodRows = getFoodRowsFromFlows(flows, 'dry');
            const wetFoodRows = getFoodRowsFromFlows(flows, 'wet');
            const medicationRows = getMedicationRowsFromFlows(flows);
            const dryAm = hasFoodDispense(dryFoodRows, 'dispense_am');
            const dryPm = hasFoodDispense(dryFoodRows, 'dispense_pm');
            const wetAm = hasFoodDispense(wetFoodRows, 'dispense_am');
            const wetPm = hasFoodDispense(wetFoodRows, 'dispense_pm');
            const medsAm = hasMedicationDispense(medicationRows, 'dispense_am');
            const medsPm = hasMedicationDispense(medicationRows, 'dispense_pm');
            if (isAmFood) return dryAm || wetAm;
            if (isPmFood) return dryPm || wetPm;
            if (isAmMeds) return medsAm;
            if (isPmMeds) return medsPm;
            return true;
        });
    }

    if (filteredData.length === 0 && (isAmFood || isPmFood || isAmMeds || isPmMeds)) {
        const emptyMsg = ((currentProcessItem === 'meds_prep_am' || currentProcessItem === 'meds_dispense_am')) ?
            'No Meds' :
            isAmFood ? 'No Feeding' :
            isPmFood ? 'No Feeding' :
            isAmMeds ? 'No Meds' :
            'No Meds';
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text(emptyMsg);
        $('#pet_details_tbody').html('');
        $('#select_all_pets').off('change');
        updatePetCountsDisplay(0, null);
        updateProcessStatus(currentProcessItem, emptyMsg);
        return;
    }

    if (filteredData.length === 0 && isFeedingReport) {
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No issue');
        $('#pet_details_tbody').html('');
        $('#select_all_pets').off('change');
        updatePetCountsDisplay(0, null);
        updateProcessStatus(currentProcessItem, 'No issue');
        return;
    }

    filteredData.forEach(item => {
        const workflowId = getWorkflowItemId(item);
        if (workflowId === null) return;
        const checkin = item.checkin || {};
        const flows = checkin.flows || {};
        const dryFoodRows = getFoodRowsFromFlows(flows, 'dry');
        const wetFoodRows = getFoodRowsFromFlows(flows, 'wet');
        const medicationRows = getMedicationRowsFromFlows(flows);
        const displayPeriod = currentTab === 'am-feeding-meds' ? 'am' : (currentTab === 'pm-feeding-meds' ?
            'pm' : null);

        const dryFoodHtml = buildFoodDisplayText(dryFoodRows, displayPeriod);
        const wetFoodHtml = buildFoodDisplayText(wetFoodRows, displayPeriod);
        const medsHtml = buildMedicationDisplayText(medicationRows, displayPeriod);

        const savedData = workflowData[currentProcessItem];
        const savedPetIds = savedData && savedData.selected_pet_ids ? savedData.selected_pet_ids.map(id =>
            parseInt(id)) : [];
        const isChecked = (isAmFeedingReport || isPmFeedingReport) ? true : savedPetIds.includes(workflowId);

        const petAvatarUrl = item.pet_img ?
            '{{ asset("storage/pets/") }}/' + item.pet_img :
            '{{ asset("images/no_image.jpg") }}';

        const customerAvatarUrl = item.customer_avatar ?
            '{{ asset("storage/profiles/") }}/' + item.customer_avatar :
            '{{ asset("images/default-user-avatar.png") }}';

        const issueValue = savedIssues[workflowId] || savedIssues[String(workflowId)] || '';
        const issueCell = isFeedingReport ?
            `<textarea class="textarea textarea-bordered textarea-xs w-full issue-input" rows="2" style="min-height: 2rem;" data-appointment-id="${workflowId}">${issueValue ? issueValue.replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''}</textarea>` :
            (issueValue || '');

        const checkboxCell = (isAmFeedingReport || isPmFeedingReport) ? '' : `
          <td>
                        <input class="checkbox checkbox-sm pet-checkbox" type="checkbox" data-appointment-id="${workflowId}" ${isChecked ? 'checked' : ''} />
          </td>`;
        html += `
                <tr class="hover:bg-base-200" data-appointment-id="${workflowId}">
          ${checkboxCell}
          <td>
            <div class="flex items-center space-x-3">
              <img src="${petAvatarUrl}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-10" />
              <span>${item.pet_name || 'N/A'}</span>
            </div>
          </td>
          <td>
            <div class="flex items-center space-x-3">
              <img src="${customerAvatarUrl}" alt="Customer Avatar" class="mask mask-squircle bg-base-200 size-10" />
              <span>${item.customer_name || 'N/A'}</span>
            </div>
          </td>
          <td class="food-column">${dryFoodHtml}</td>
          <td class="food-column">${wetFoodHtml}</td>
          <td class="meds-column">${medsHtml}</td>
          <td class="issue-column">${issueCell}</td>
        </tr>
      `;
    });

    $('#pet_details_tbody').html(html);
    $('#pet_details_table').show();
    $('#empty_state_message').hide();
    $('#no_details_message').hide();

    if (!isAmFeedingReport && !isPmFeedingReport) {
        $('#select_all_pets').off('change').on('change', function() {
            $('.pet-checkbox').prop('checked', $(this).is(':checked'));
        });
        const savedDataForReport = workflowData[currentProcessItem];
        const savedPetIds = savedDataForReport && savedDataForReport.selected_pet_ids ? savedDataForReport
            .selected_pet_ids.map(id => parseInt(id)) : [];
        const totalCheckboxes = $('.pet-checkbox').length;
        const checkedCount = $('.pet-checkbox:checked').length;
        $('#select_all_pets').prop('checked', totalCheckboxes > 0 && checkedCount === totalCheckboxes);
    }

    updatePetCountsDisplay(filteredData.length, null);
}

$('#pet_details_search').on('input', function() {
    const searchTerm = $(this).val().toLowerCase();
    const rows = $('#pet_details_tbody tr');
    if (searchTerm === '') {
        rows.show();
        return;
    }
    rows.each(function() {
        const $row = $(this);
        if ($row.find('td[colspan]').length) {
            $row.show();
            return;
        }
        const $cells = $row.find('td');
        const petName = ($cells.length === 6 ? $cells.eq(0) : $cells.eq(1)).text().toLowerCase();
        const customerName = ($cells.length === 6 ? $cells.eq(1) : $cells.eq(2)).text().toLowerCase();
        if (petName.includes(searchTerm) || customerName.includes(searchTerm)) {
            $row.show();
        } else {
            $row.hide();
        }
    });
});

function renderCheckPetForm() {
    const bodyParts = [
        'Nose', 'Ears', 'Eyes', 'Mouth', 'Body/Coat', 'Paws/Feet', 'Abdomen', 'Digestive', 'Diarrhea'
    ];

    // Get saved check data
    const currentData = workflowData[currentProcessItem] || {};
    const savedCheckData = currentData.check_data || {};
    const savedFleaTickData = currentData.flea_tick_data || {};

    // Build table header
    let headerHtml = '<tr><th style="min-width: 200px;">Pet Name</th><th style="min-width: 200px;">Customer</th>';
    bodyParts.forEach(part => {
        headerHtml += `<th style="text-align: center; min-width: 90px;">${part}</th>`;
    });
    headerHtml += '<th style="text-align: center; min-width: 150px;">Fleas/Ticks</th>';
    headerHtml += '</tr>';
    $('#check_pet_thead').html(headerHtml);

    // Build table body
    let bodyHtml = '';
    selectedAppointmentIds.forEach(appointmentId => {
        const pet = appointmentToPetMap[appointmentId];
        if (!pet) return;

        const petAvatarUrl = pet.pet_img ?
            '{{ asset("storage/pets/") }}/' + pet.pet_img :
            '{{ asset("images/no_image.jpg") }}';

        const customerAvatarUrl = pet.customer_avatar ?
            '{{ asset("storage/profiles/") }}/' + pet.customer_avatar :
            '{{ asset("images/default-user-avatar.png") }}';

        const savedPetData = savedCheckData[appointmentId] || {};
        const savedFleaTick = isTruthyBoardingValue(savedFleaTickData[appointmentId]);
        const fleaTickChecked = savedFleaTick;

        bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;

        // Pet Name column
        bodyHtml += `
        <td>
          <div class="flex items-center space-x-3">
            <img src="${petAvatarUrl}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-10" />
            <span>${pet.pet_name || 'N/A'}</span>
          </div>
        </td>
      `;

        // Customer column
        bodyHtml += `
        <td>
          <div class="flex items-center space-x-3">
            <img src="${customerAvatarUrl}" alt="Customer Avatar" class="mask mask-squircle bg-base-200 size-10" />
            <span>${pet.customer_name || 'N/A'}</span>
          </div>
        </td>
      `;

        // Body parts columns
        bodyParts.forEach(part => {
            const partKey = part.toLowerCase().replace(/\s+/g, '_');
            const fieldId = `check_${appointmentId}_${partKey}`;
            const savedPartData = savedPetData[partKey] || {};
            const savedStatus = savedPartData.status || '';

            bodyHtml += `
          <td style="text-align: center;">
            <div class="flex items-center justify-center gap-1">
              <label class="label cursor-pointer gap-1 py-0 min-h-0">
                <input type="radio" name="${fieldId}" value="okay" class="radio radio-xs radio-primary" ${savedStatus === 'okay' ? 'checked' : ''} />
                <span class="label-text text-[10px]">Okay</span>
              </label>
              <label class="label cursor-pointer gap-1 py-0 min-h-0">
                <input type="radio" name="${fieldId}" value="issue" class="radio radio-xs radio-error" ${savedStatus === 'issue' ? 'checked' : ''} />
                <span class="label-text text-[10px]">Issue</span>
              </label>
            </div>
          </td>
        `;
        });

                bodyHtml += `
                    <td style="text-align: center;">
                        <label class="label cursor-pointer justify-center gap-2">
                            <input class="checkbox checkbox-sm flea-tick-checkbox" type="checkbox" data-appointment-id="${appointmentId}" ${fleaTickChecked ? 'checked' : ''} />
                            <span class="label-text text-xs">Detected</span>
                        </label>
                    </td>
                `;

        bodyHtml += '</tr>';
    });

    $('#check_pet_tbody').html(bodyHtml);
}

function renderTreatmentPlanForm() {
    ensureCheckinRestMetaLoaded();

    // Get saved treatment plan data
    const currentData = workflowData[currentProcessItem] || {};
    const savedTreatmentData = currentData.treatment_data || {};

    // Get check_pet data to filter pets with issues
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};

    // Body parts mapping for display
    const bodyPartsMap = {
        'nose': 'Nose',
        'ears': 'Ears',
        'eyes': 'Eyes',
        'mouth': 'Mouth',
        'body_coat': 'Body/Coat',
        'flea_tick': 'Flea/Tick',
        'paws_feet': 'Paws/Feet',
        'abdomen': 'Abdomen',
        'digestive': 'Digestive',
        'diarrhea': 'Diarrhea'
    };

    const treatmentMultiOptions = [
        'Ear Rinse',
        'Ear Drops Applied',
        'Eye Clean',
        'Eye Drops Applied',
        'Face Clean',
        'Wound Cleaned',
        'Apply Ointment',
        'Hot Spot Treatment',
        'Tick Removed',
        'Medicated Spray',
        'Paw Clean',
        'Bandage Applied',
        'Nail Trim',
        'Limping Observed',
        'Medicine Given',
        'Rest Required',
        'Isolation Required',
        'Monitor Eating',
        'Monitor Stool',
        'Monitor Urine',
        'Vomiting Observed',
        'Diarrhea Observed',
        'Temperature Check',
        'Weight Check',
        'Dry Food Given',
        'Wet Food Given',
        'Owner Food Given',
        'Special Diet',
        'No Appetite'
    ];

    // Filter pets that have at least one issue
    const petsWithIssues = selectedAppointmentIds.filter(appointmentId => {
        const petCheckData = checkPetCheckData[appointmentId] || {};
        return Object.values(petCheckData).some(partData => partData.status === 'issue');
    });

    // Build table header
    const headerHtml =
        '<tr><th style="min-width: 200px;">Pet Name</th><th style="min-width: 200px;">Customer</th><th style="min-width: 200px;">Issue</th><th style="min-width: 200px;">Option</th><th style="min-width: 200px;">Treatment</th><th style="min-width: 300px;">Detail</th><th style="min-width: 110px;">Assign Rest</th><th style="min-width: 300px;">Rest Detail</th></tr>';
    $('#treatment_plan_thead').html(headerHtml);

    // Build table body
    let bodyHtml = '';
    if (petsWithIssues.length === 0) {
        $('#treatment_plan_form_container').hide();
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No Treatment Plan');
        $('#treatment_plan_tbody').html('');
        updatePetCountsDisplay(0, null);
        updateProcessStatus('treatment_plan', 'No Plan');
        return false;
    } else {
        $('#empty_state_message').hide();
        $('#treatment_plan_form_container').show();
        petsWithIssues.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;

            const petAvatarUrl = pet.pet_img ?
                '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';

            const customerAvatarUrl = pet.customer_avatar ?
                '{{ asset("storage/profiles/") }}/' + pet.customer_avatar :
                '{{ asset("images/default-user-avatar.png") }}';

            // Get issues for this pet
            const petCheckData = checkPetCheckData[appointmentId] || {};
            const issues = [];
            Object.keys(petCheckData).forEach(partKey => {
                if (petCheckData[partKey].status === 'issue') {
                    issues.push(bodyPartsMap[partKey] || partKey);
                }
            });
            const issuesText = issues.join(', ') || 'No issues';

            const savedPetData = savedTreatmentData[appointmentId] || {};
            const savedOption = savedPetData.option || '';
            const savedDetail = savedPetData.detail || '';
            const savedAssignRest = savedPetData.assign_rest === true || savedPetData.assign_rest === 'true' ||
                savedPetData.assign_rest === 1 || savedPetData.assign_rest === '1';
            const savedRestDetail = savedPetData.rest_detail || '';
            const checkinRestData = checkinRestMetaByAppointmentId[String(appointmentId)] || {};
            const isRestAssignedFromCheckin = checkinRestData.is_assigned === true;
            const effectiveAssignRest = isRestAssignedFromCheckin ? true : savedAssignRest;
            const effectiveRestDetail = isRestAssignedFromCheckin ? (savedRestDetail || checkinRestData.rest_note ||
                '') : savedRestDetail;
            const savedAdditionalOptions = Array.isArray(savedPetData.additional_options) ?
                savedPetData.additional_options :
                (savedPetData.additional_option ? [savedPetData.additional_option] : []);

            bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;

            // Pet Name column
            bodyHtml += `
          <td>
            <div class="flex items-center space-x-3">
              <img src="${petAvatarUrl}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-10" />
              <span>${pet.pet_name || 'N/A'}</span>
            </div>
          </td>
        `;

            // Customer column
            bodyHtml += `
          <td>
            <div class="flex items-center space-x-3">
              <img src="${customerAvatarUrl}" alt="Customer Avatar" class="mask mask-squircle bg-base-200 size-10" />
              <span>${pet.customer_name || 'N/A'}</span>
            </div>
          </td>
        `;

            // Issue column
            bodyHtml += `
          <td>
            <span class="text-sm">${issuesText}</span>
          </td>
        `;

            // Option column (radio buttons)
            bodyHtml += `
          <td>
            <div class="flex items-center gap-4">
              <label class="label cursor-pointer gap-2">
                <input type="radio" name="treatment_option_${appointmentId}" value="in-house" class="radio radio-sm radio-primary" ${savedOption === 'in-house' ? 'checked' : ''} />
                <span class="label-text text-sm">In-house</span>
              </label>
              <label class="label cursor-pointer gap-2">
                <input type="radio" name="treatment_option_${appointmentId}" value="vet-watch" class="radio radio-sm radio-primary" ${savedOption === 'vet-watch' ? 'checked' : ''} />
                <span class="label-text text-sm">Vet</span>
              </label>
            </div>
          </td>
        `;

            const additionalOptionsHtml = treatmentMultiOptions.map(option => {
                const selected = savedAdditionalOptions.includes(option) ? 'selected' : '';
                return `<option value="${option}" ${selected}>${option}</option>`;
            }).join('');

            bodyHtml += `
          <td>
            <select id="treatment_multi_${appointmentId}" class="select select-bordered select-sm w-full treatment-plan-select" multiple>
              ${additionalOptionsHtml}
            </select>
          </td>
        `;

            // Detail column (textarea)
            bodyHtml += `
          <td>
            <textarea id="treatment_detail_${appointmentId}" class="textarea textarea-bordered textarea-sm w-full" rows="2" style="min-height: 2rem;" placeholder="Enter treatment details...">${savedDetail ? savedDetail.replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''}</textarea>
          </td>
        `;

            // Assign Rest column (checkbox)
            bodyHtml += `
          <td style="text-align: center;">
            <label class="label cursor-pointer justify-center py-0">
                            <input type="checkbox" id="assign_rest_${appointmentId}" class="checkbox checkbox-sm" ${effectiveAssignRest ? 'checked' : ''} ${isRestAssignedFromCheckin ? 'disabled' : ''} />
            </label>
          </td>
        `;

            bodyHtml += `
          <td>
                        <textarea id="rest_detail_${appointmentId}" class="textarea textarea-bordered textarea-sm w-full" rows="2" style="min-height: 2rem;" placeholder="Enter rest details..." ${isRestAssignedFromCheckin ? 'disabled' : ''}>${effectiveRestDetail ? effectiveRestDetail.replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''}</textarea>
          </td>
        `;
            bodyHtml += '</tr>';
        });
    }

    $('#treatment_plan_tbody').html(bodyHtml);

    $('.treatment-plan-select').select2({
        placeholder: 'Select treatments',
        allowClear: true,
        multiple: true,
        width: '100%'
    });
    // Restore saved Select2 values (Select2 doesn't honour 'selected' attr after init)
    petsWithIssues.forEach(function(appointmentId) {
        const savedPetDataRestore = savedTreatmentData[appointmentId] || {};
        const savedAdditionalOptionsRestore = Array.isArray(savedPetDataRestore.additional_options) ?
            savedPetDataRestore.additional_options :
            (savedPetDataRestore.additional_option ? [savedPetDataRestore.additional_option] : []);
        if (savedAdditionalOptionsRestore.length) {
            $('#treatment_multi_' + appointmentId).val(savedAdditionalOptionsRestore).trigger('change');
        }
    });
    return true;
}

function renderTreatmentListForm() {
    // Get saved treatment list data (checkbox states)
    const currentData = workflowData[currentProcessItem] || {};
    const savedTreatmentListData = currentData.completed_treatments || {};

    // Get treatment plan data to display (option/detail)
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const treatmentPlanTreatmentData = treatmentPlanData.treatment_data || {};

    // Get check_pet data to filter pets with issues
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};

    // Body parts mapping for display
    const bodyPartsMap = {
        'nose': 'Nose',
        'ears': 'Ears',
        'eyes': 'Eyes',
        'mouth': 'Mouth',
        'body_coat': 'Body/Coat',
        'flea_tick': 'Flea/Tick',
        'paws_feet': 'Paws/Feet',
        'abdomen': 'Abdomen',
        'digestive': 'Digestive',
        'diarrhea': 'Diarrhea'
    };

    // Show only pets that have at least one issue from nose-to-tail check
    const petsWithIssues = selectedAppointmentIds.filter(function(appointmentId) {
        const petCheckData = checkPetCheckData[appointmentId] || {};
        return Object.values(petCheckData).some(function(partData) {
            return partData.status === 'issue';
        });
    });

    // Build table header
    const headerHtml =
        '<tr><th style="width: 50px;"><input class="checkbox checkbox-sm" id="select_all_treatments" type="checkbox" /></th><th style="min-width: 200px;">Pet Name</th><th style="min-width: 200px;">Customer</th><th style="min-width: 200px;">Issue</th><th style="min-width: 200px;">Option</th><th style="min-width: 300px;">Detail</th></tr>';
    $('#treatment_list_thead').html(headerHtml);

    // Build table body
    let bodyHtml = '';
    if (petsWithIssues.length === 0) {
        bodyHtml =
            '<tr><td colspan="6" class="text-center p-4 text-base-content/70">No pets with issues from nose-to-tail check. Please complete the Check Pet step first.</td></tr>';
    } else {
        petsWithIssues.forEach(function(appointmentId) {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;

            const petAvatarUrl = pet.pet_img ?
                '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';

            const customerAvatarUrl = pet.customer_avatar ?
                '{{ asset("storage/profiles/") }}/' + pet.customer_avatar :
                '{{ asset("images/default-user-avatar.png") }}';

            // Get issues for this pet
            const petCheckData = checkPetCheckData[appointmentId] || {};
            const issues = [];
            Object.keys(petCheckData).forEach(partKey => {
                if (petCheckData[partKey].status === 'issue') {
                    issues.push(bodyPartsMap[partKey] || partKey);
                }
            });
            const issuesText = issues.join(', ') || 'No issues';

            // Get treatment plan data for this pet
            const petTreatmentData = treatmentPlanTreatmentData[appointmentId] || {};
            const option = petTreatmentData.option || '';
            const detail = petTreatmentData.detail || '';

            // Get option display text
            const optionText = option === 'in-house' ? 'In-house' : option === 'vet-watch' ? 'Vet' : '-';

            // Check if this treatment is completed (handle both true/false and undefined)
            const isCompleted = savedTreatmentListData[appointmentId] === true || savedTreatmentListData[
                appointmentId] === 'true';

            bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;

            // Checkbox column
            bodyHtml += `
          <td>
            <input class="checkbox checkbox-sm treatment-checkbox" type="checkbox" data-appointment-id="${appointmentId}" ${isCompleted ? 'checked' : ''} />
          </td>
        `;

            // Pet Name column
            bodyHtml += `
          <td>
            <div class="flex items-center space-x-3">
              <img src="${petAvatarUrl}" alt="Pet Image" class="mask mask-squircle bg-base-200 size-10" />
              <span>${pet.pet_name || 'N/A'}</span>
            </div>
          </td>
        `;

            // Customer column
            bodyHtml += `
          <td>
            <div class="flex items-center space-x-3">
              <img src="${customerAvatarUrl}" alt="Customer Avatar" class="mask mask-squircle bg-base-200 size-10" />
              <span>${pet.customer_name || 'N/A'}</span>
            </div>
          </td>
        `;

            // Issue column (static)
            bodyHtml += `
          <td>
            <span class="text-sm">${issuesText}</span>
          </td>
        `;

            // Option column (static)
            bodyHtml += `
          <td>
            <span class="text-sm">${optionText}</span>
          </td>
        `;

            // Detail column (static)
            bodyHtml += `
          <td>
            <span class="text-sm">${detail ? detail.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-'}</span>
          </td>
        `;

            bodyHtml += '</tr>';
        });
    }

    $('#treatment_list_tbody').html(bodyHtml);

    // Handle select all checkbox
    $('#select_all_treatments').off('change').on('change', function() {
        $('.treatment-checkbox').prop('checked', $(this).is(':checked'));
    });
}

const bodyPartsMapTLR = {
    'nose': 'Nose',
    'ears': 'Ears',
    'eyes': 'Eyes',
    'mouth': 'Mouth',
    'body_coat': 'Body/Coat',
    'flea_tick': 'Flea/Tick',
    'paws_feet': 'Paws/Feet',
    'abdomen': 'Abdomen',
    'digestive': 'Digestive',
    'diarrhea': 'Diarrhea'
};

function getTreatmentPlanSelectionValues(petTreatmentData) {
    if (!petTreatmentData || typeof petTreatmentData !== 'object') {
        return [];
    }

    if (Array.isArray(petTreatmentData.additional_options)) {
        return petTreatmentData.additional_options.filter(Boolean);
    }

    if (Array.isArray(petTreatmentData.selected_treatments)) {
        return petTreatmentData.selected_treatments.filter(Boolean);
    }

    if (Array.isArray(petTreatmentData.selected_treatment)) {
        return petTreatmentData.selected_treatment.filter(Boolean);
    }

    if (Array.isArray(petTreatmentData.treatment)) {
        return petTreatmentData.treatment.filter(Boolean);
    }

    const singleValue =
        petTreatmentData.additional_option ||
        petTreatmentData.selected_treatment ||
        petTreatmentData.selected_treatments ||
        petTreatmentData.treatment ||
        '';

    return singleValue ? [singleValue] : [];
}

function renderTreatmentListTLRForm() {
    const treatmentListBasePetIds = getTreatmentListBasePetIds();
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const treatmentPlanPetIds = treatmentPlanData.selected_pet_ids || [];
    const treatmentPlanTreatmentData = treatmentPlanData.treatment_data || {};
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const checkPetDataForTime = workflowData['check_pet'] || {};
    const treatmentPlanDataForTime = workflowData['treatment_plan'] || {};
    const prevStepProcessTime = checkPetDataForTime.process_time || checkPetDataForTime.processTime ||
        treatmentPlanDataForTime.process_time || treatmentPlanDataForTime.processTime || '';
    const reportedDisplay = prevStepProcessTime || '-';
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmIdsRender = ((workflowData['reports_am'] || {}).selected_pet_ids || []).map(id => parseInt(id, 10))
        .filter(id => !isNaN(id));

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 200px;">Issue</th><th style="min-width: 180px;">Reported</th><th style="min-width: 300px;">Treatment</th></tr>'
    );
    let bodyHtml = '';
    if (treatmentListBasePetIds.length === 0) {
        bodyHtml =
            '<tr><td colspan="5" class="text-center p-4 text-base-content/70">No treatment plans found. Complete Nose to Tail Treatment Plan first, or no pets carried from yesterday or Do not eat AM Meals.</td></tr>';
    } else {
        treatmentListBasePetIds.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;
            const aid = parseInt(appointmentId, 10);
            const inTreatmentPlan = treatmentPlanPetIds.indexOf(aid) !== -1 || treatmentPlanPetIds.indexOf(
                String(appointmentId)) !== -1;
            const isFromYesterday = !inTreatmentPlan;
            const fromYesterdayList = !inTreatmentPlan && yesterdayIds.indexOf(aid) !== -1;
            const fromReportsAmOnly = !inTreatmentPlan && reportsAmIdsRender.indexOf(aid) !== -1 && !
                fromYesterdayList;
            const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';
            const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
            const petCheckData = checkPetCheckData[appointmentId] || {};
            const issues = [];
            if (inTreatmentPlan) {
                Object.keys(petCheckData).forEach(partKey => {
                    if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[
                        partKey] || partKey);
                });
            }
            const issuesText = inTreatmentPlan ? (issues.join(', ') || 'No issues') : (fromReportsAmOnly ?
                'Do not eat AM Meals' : 'Carried from previous day');
            const petTreatmentData = treatmentPlanTreatmentData[appointmentId] || {};
            const treatmentDetail = isFromYesterday ? '-' : (petTreatmentData.detail || '-');
            bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;
            bodyHtml +=
                `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
            bodyHtml +=
                `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
            bodyHtml += `<td><span class="text-sm">${issuesText}</span></td>`;
            bodyHtml += `<td><span class="text-sm text-base-content/80">${reportedDisplay}</span></td>`;
            bodyHtml +=
                `<td><span class="text-sm">${treatmentDetail !== '-' ? treatmentDetail.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-'}</span></td>`;
            bodyHtml += '</tr>';
        });
    }
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
}

function renderTreatmentsTLRForm() {
    const treatmentListBasePetIds = getTreatmentListBasePetIds();
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const treatmentPlanPetIds = treatmentPlanData.selected_pet_ids || [];
    const treatmentPlanTreatmentData = treatmentPlanData.treatment_data || {};
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const currentData = workflowData['treatments_tlr'] || {};
    const savedResults = currentData.results || {};
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmIdsRender = ((workflowData['reports_am'] || {}).selected_pet_ids || []).map(id => parseInt(id, 10))
        .filter(id => !isNaN(id));

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#empty_state_message').hide();
    if (treatmentListBasePetIds.length === 0) {
        $('#treatment_lunch_rest_form_container').hide();
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No Treatments Today');
        updatePetCountsDisplay(0, null);
        updateProcessStatus('treatments_tlr', 'No Treatment');
        return false;
    }
    $('#treatment_lunch_rest_form_container').show();
    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 200px;">Issue</th><th style="min-width: 220px;">Treatment</th><th style="min-width: 280px;">Treatment Plan Detail</th><th style="min-width: 220px;">Result</th></tr>'
    );
    let bodyHtml = '';
    treatmentListBasePetIds.forEach(appointmentId => {
        const pet = appointmentToPetMap[appointmentId];
        if (!pet) return;
        const aid = parseInt(appointmentId, 10);
        const inTreatmentPlan = treatmentPlanPetIds.indexOf(aid) !== -1 || treatmentPlanPetIds.indexOf(String(
            appointmentId)) !== -1;
        const isFromYesterday = !inTreatmentPlan;
        const fromYesterdayList = !inTreatmentPlan && yesterdayIds.indexOf(aid) !== -1;
        const fromReportsAmOnly = !inTreatmentPlan && reportsAmIdsRender.indexOf(aid) !== -1 && !
            fromYesterdayList;
        const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
            '{{ asset("images/no_image.jpg") }}';
        const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
            .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
        const petCheckData = checkPetCheckData[appointmentId] || {};
        const issues = [];
        if (inTreatmentPlan) {
            Object.keys(petCheckData).forEach(partKey => {
                if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[partKey] ||
                    partKey);
            });
        }
        const issuesText = inTreatmentPlan ? (issues.join(', ') || 'No issues') : (fromReportsAmOnly ?
            'Do not eat AM Meals' : 'Carried from previous day');
        const petTreatmentData = treatmentPlanTreatmentData[appointmentId] || {};
        const treatmentSelections = getTreatmentPlanSelectionValues(petTreatmentData);
        const treatmentSelectionText = treatmentSelections.length > 0 ? treatmentSelections.join(', ') : '-';
        const treatmentPlanDetail = (petTreatmentData.detail || petTreatmentData.details || '').trim() || '-';
        const saved = savedResults[appointmentId] || {};
        const resultVal = saved.result || '';
        const escalateDetailVal = saved.detail || '';
        bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;
        bodyHtml +=
            `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
        bodyHtml +=
            `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
        bodyHtml += `<td><span class="text-sm">${issuesText}</span></td>`;
        bodyHtml +=
            `<td><span class="text-sm">${treatmentSelectionText !== '-' ? treatmentSelectionText.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-'}</span></td>`;
        bodyHtml +=
            `<td><span class="text-sm">${treatmentPlanDetail !== '-' ? treatmentPlanDetail.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-'}</span></td>`;
        bodyHtml += `<td><div class="flex flex-wrap gap-2 items-center">`;
        bodyHtml +=
            `<label class="label cursor-pointer gap-1 py-0 min-h-0"><input type="radio" name="result_tlr_${appointmentId}" value="continue" class="radio radio-xs radio-primary" ${resultVal === 'continue' ? 'checked' : ''} /><span class="label-text text-xs">Continue</span></label>`;
        bodyHtml +=
            `<label class="label cursor-pointer gap-1 py-0 min-h-0"><input type="radio" name="result_tlr_${appointmentId}" value="resolved" class="radio radio-xs radio-primary" ${resultVal === 'resolved' ? 'checked' : ''} /><span class="label-text text-xs">Resolved</span></label>`;
        bodyHtml +=
            `<label class="label cursor-pointer gap-1 py-0 min-h-0"><input type="radio" name="result_tlr_${appointmentId}" value="escalate" class="radio radio-xs radio-primary" ${resultVal === 'escalate' ? 'checked' : ''} /><span class="label-text text-xs">Escalate</span></label>`;
        bodyHtml +=
            `</div><div class="escalate-detail-wrap mt-2 ${resultVal === 'escalate' ? '' : 'hidden'}" data-appointment-id="${appointmentId}"><textarea class="textarea textarea-bordered textarea-sm w-full escalate-detail-tlr" rows="2" style="min-height: 2rem;" data-appointment-id="${appointmentId}" placeholder="Escalate detail...">${(escalateDetailVal || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea></div></td>`;
        bodyHtml += '</tr>';
    });
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
    $('input[type="radio"][name^="result_tlr_"]').off('change').on('change', function() {
        const match = (this.name || '').match(/^result_tlr_(.+)$/);
        if (!match) return;
        const appointmentId = match[1];
        const isEscalate = $(this).val() === 'escalate' && $(this).is(':checked');
        const $wrap = $(`.escalate-detail-wrap[data-appointment-id="${appointmentId}"]`);
        $wrap.toggleClass('hidden', !isEscalate);
        if (!isEscalate) {
            $wrap.find('.escalate-detail-tlr').val('');
        }
    });
    return true;
}

function renderNextDayTreatmentListTLRForm() {
    const treatmentListBasePetIds = getTreatmentListBasePetIds();
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const treatmentPlanPetIds = treatmentPlanData.selected_pet_ids || [];
    const treatmentsTlrData = workflowData['treatments_tlr'] || {};
    const treatmentsTlrResults = treatmentsTlrData.results || {};
    // Include only pets marked as continue/escalate in Treatments step
    const nextDayPetIds = treatmentListBasePetIds.filter(appointmentId => {
        const resultData = treatmentsTlrResults[appointmentId];
        return resultData && (resultData.result === 'continue' || resultData.result === 'escalate');
    });
    const checkPetDataForTime = workflowData['check_pet'] || {};
    const treatmentPlanDataForTime = workflowData['treatment_plan'] || {};
    const prevStepProcessTime = checkPetDataForTime.process_time || checkPetDataForTime.processTime ||
        treatmentPlanDataForTime.process_time || treatmentPlanDataForTime.processTime || '';
    const reportedDisplay = prevStepProcessTime || '-';
    const currentData = workflowData['next_day_treatment_list_tlr'] || {};
    const savedSelected = currentData.selected_pet_ids || [];
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const treatmentPlanTreatmentData = treatmentPlanData.treatment_data || {};
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmIdsRender = ((workflowData['reports_am'] || {}).selected_pet_ids || []).map(id => parseInt(id, 10))
        .filter(id => !isNaN(id));

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#empty_state_message').hide();
    if (nextDayPetIds.length === 0) {
        $('#treatment_lunch_rest_form_container').hide();
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No Treatment');
        updatePetCountsDisplay(0, null);
        updateProcessStatus('next_day_treatment_list_tlr', 'No Treatment');
        return false;
    }
    $('#treatment_lunch_rest_form_container').show();
    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="width: 50px;"><input class="checkbox checkbox-sm" id="select_all_next_day_tlr" type="checkbox" /></th><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 180px;">Reported</th><th style="min-width: 200px;">Issue</th><th style="min-width: 220px;">Treatment</th><th style="min-width: 260px;">Notes</th></tr>'
    );
    let bodyHtml = '';
    nextDayPetIds.forEach(appointmentId => {
        const pet = appointmentToPetMap[appointmentId];
        if (!pet) return;
        const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
            '{{ asset("images/no_image.jpg") }}';
        const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
            .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
        const resultData = treatmentsTlrResults[appointmentId] || {};
        const resultVal = resultData.result || '';
        const aid = parseInt(appointmentId, 10);
        const inTreatmentPlan = treatmentPlanPetIds.indexOf(aid) !== -1 || treatmentPlanPetIds.indexOf(String(
            appointmentId)) !== -1;
        const fromYesterdayList = !inTreatmentPlan && yesterdayIds.indexOf(aid) !== -1;
        const fromReportsAmOnly = !inTreatmentPlan && reportsAmIdsRender.indexOf(aid) !== -1 && !
            fromYesterdayList;
        const petCheckData = checkPetCheckData[appointmentId] || {};
        const issues = [];
        if (inTreatmentPlan) {
            Object.keys(petCheckData).forEach(partKey => {
                if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[partKey] || partKey);
            });
        }
        const issuesText = inTreatmentPlan ? (issues.join(', ') || 'No issues') : (fromReportsAmOnly ?
            'Do not eat AM Meals' : 'Carried from previous day');
        const petTreatmentData = treatmentPlanTreatmentData[appointmentId] || {};
        const treatmentSelections = getTreatmentPlanSelectionValues(petTreatmentData);
        const treatmentSelectionText = treatmentSelections.length > 0 ? treatmentSelections.join(', ') : '-';
        const notesText = resultVal === 'continue' ? 'Continue monitoring' : ((resultData.detail || '').trim() ||
            '—');
        const rowChecked = savedSelected.length ? savedSelected.includes(parseInt(appointmentId)) : false;
        bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;
        bodyHtml +=
            `<td><input class="checkbox checkbox-sm next-day-row-tlr" type="checkbox" data-appointment-id="${appointmentId}" ${rowChecked ? 'checked' : ''} /></td>`;
        bodyHtml +=
            `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
        bodyHtml +=
            `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
        bodyHtml += `<td><span class="text-sm text-base-content/80">${reportedDisplay}</span></td>`;
        bodyHtml += `<td><span class="text-sm">${issuesText}</span></td>`;
        bodyHtml +=
            `<td><span class="text-sm">${treatmentSelectionText !== '-' ? treatmentSelectionText.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-'}</span></td>`;
        bodyHtml +=
            `<td><span class="text-sm">${notesText !== '—' ? notesText.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '—'}</span></td>`;
        bodyHtml += '</tr>';
    });
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
    $('#select_all_next_day_tlr').off('change').on('change', function() {
        $('.next-day-row-tlr').prop('checked', $(this).is(':checked'));
    });
    updatePetCountsDisplay(nextDayPetIds.length, null);
    return true;
}

function renderDneListForm(amOrPm, checkinData) {
    const key = amOrPm === 'am' ? 'reports_am' : 'reports_pm';
    const stepKey = amOrPm === 'am' ? 'dne_list_am' : 'dne_list_pm';
    const reportData = workflowData[key] || {};
    const petIds = (reportData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportIssues = reportData.issues || {};
    const checkinMap = {};
    if (checkinData && Array.isArray(checkinData)) {
        checkinData.forEach(item => {
            const workflowId = getWorkflowItemId(item);
            if (workflowId !== null) {
                checkinMap[workflowId] = item;
            }
        });
    }

    const isAm = amOrPm === 'am';
    $('#dne_list_search_bar').show();
    $('#rest_nose_to_tail_inline').hide();
    $('#dne_list_search').val('');
    if (isAm) {
        const staffIdToName = {};
        $('#staff_sign_off option').each(function() {
            const v = $(this).val();
            if (v) staffIdToName[v] = $(this).text();
        });
        const feedingAmData = workflowData['feeding_am'] || {};
        const feedingTime = feedingAmData.process_time || '—';
        const staffSignOffIds = feedingAmData.staff_sign_off || [];
        const employeeName = (staffSignOffIds[0] != null ? (staffIdToName[String(staffSignOffIds[0])] || '—') : '—');
        $('#dne_list_time').text(feedingTime);
        $('#dne_list_employee').text(employeeName);

        $('#treatment_lunch_rest_thead').html(
            '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 160px;">Dry Food</th><th style="min-width: 160px;">Wet Food</th><th style="min-width: 200px;">Issue</th></tr>'
        );
        let bodyHtml = '';
        if (petIds.length === 0) {
            bodyHtml =
                '<tr data-empty><td colspan="5" class="text-center p-4 text-base-content/70">No pets selected in AM Reports. Complete Reports in AM Feeding Meds first.</td></tr>';
        } else {
            petIds.forEach(appointmentId => {
                const pet = appointmentToPetMap[appointmentId];
                if (!pet) return;
                const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                    '{{ asset("images/no_image.jpg") }}';
                const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                    .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
                const item = checkinMap[appointmentId];
                const flows = (item && item.checkin) ? (item.checkin.flows || {}) : {};
                const dryFoodRows = getFoodRowsFromFlows(flows, 'dry');
                const wetFoodRows = getFoodRowsFromFlows(flows, 'wet');
                const dryFoodHtml = buildFoodDisplayText(dryFoodRows);
                const wetFoodHtml = buildFoodDisplayText(wetFoodRows);
                const issueVal = (reportIssues[appointmentId] || '').replace(/</g, '&lt;').replace(/>/g,
                    '&gt;');
                bodyHtml +=
                    `<tr class="hover:bg-base-200 dne-list-am-row" data-appointment-id="${appointmentId}" data-pet-name="${(pet.pet_name || '').toLowerCase()}" data-customer-name="${(pet.customer_name || '').toLowerCase()}">`;
                bodyHtml +=
                    `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
                bodyHtml +=
                    `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
                bodyHtml += `<td><span class="text-sm">${dryFoodHtml}</span></td>`;
                bodyHtml += `<td><span class="text-sm">${wetFoodHtml}</span></td>`;
                bodyHtml += `<td><span class="text-sm">${issueVal || '—'}</span></td>`;
                bodyHtml += '</tr>';
            });
        }
        $('#treatment_lunch_rest_tbody').html(bodyHtml);
        $('#dne_list_search').off('input').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#treatment_lunch_rest_tbody tr.dne-list-am-row').each(function() {
                const $row = $(this);
                if ($row.find('td[colspan]').length) {
                    $row.show();
                    return;
                }
                const match = !term || $row.data('pet-name').indexOf(term) !== -1 || $row.data(
                    'customer-name').indexOf(term) !== -1;
                $row.toggle(match);
            });
        });
    } else {
        const staffIdToName = {};
        $('#staff_sign_off option').each(function() {
            const v = $(this).val();
            if (v) staffIdToName[v] = $(this).text();
        });
        const feedingPmData = workflowData['feeding_pm'] || {};
        const feedingTime = feedingPmData.process_time || '—';
        const staffSignOffIds = feedingPmData.staff_sign_off || [];
        const employeeName = (staffSignOffIds[0] != null ? (staffIdToName[String(staffSignOffIds[0])] || '—') : '—');
        $('#dne_list_time').text(feedingTime);
        $('#dne_list_employee').text(employeeName);

        $('#treatment_lunch_rest_thead').html(
            '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 160px;">Dry Food</th><th style="min-width: 160px;">Wet Food</th><th style="min-width: 200px;">Issue</th></tr>'
        );
        let bodyHtml = '';
        if (petIds.length === 0) {
            bodyHtml =
                '<tr data-empty><td colspan="5" class="text-center p-4 text-base-content/70">No pets selected in PM Reports. Complete Reports in PM Feeding Meds first.</td></tr>';
        } else {
            petIds.forEach(appointmentId => {
                const pet = appointmentToPetMap[appointmentId];
                if (!pet) return;
                const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                    '{{ asset("images/no_image.jpg") }}';
                const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                    .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
                const item = checkinMap[appointmentId];
                const flows = (item && item.checkin) ? (item.checkin.flows || {}) : {};
                const dryFoodRows = getFoodRowsFromFlows(flows, 'dry');
                const wetFoodRows = getFoodRowsFromFlows(flows, 'wet');
                const dryFoodHtml = buildFoodDisplayText(dryFoodRows);
                const wetFoodHtml = buildFoodDisplayText(wetFoodRows);
                const issueVal = (reportIssues[appointmentId] || '').replace(/</g, '&lt;').replace(/>/g,
                    '&gt;');
                bodyHtml +=
                    `<tr class="hover:bg-base-200 dne-list-pm-row" data-appointment-id="${appointmentId}" data-pet-name="${(pet.pet_name || '').toLowerCase()}" data-customer-name="${(pet.customer_name || '').toLowerCase()}">`;
                bodyHtml +=
                    `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
                bodyHtml +=
                    `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
                bodyHtml += `<td><span class="text-sm">${dryFoodHtml}</span></td>`;
                bodyHtml += `<td><span class="text-sm">${wetFoodHtml}</span></td>`;
                bodyHtml += `<td><span class="text-sm">${issueVal || '—'}</span></td>`;
                bodyHtml += '</tr>';
            });
        }
        $('#treatment_lunch_rest_tbody').html(bodyHtml);
        $('#dne_list_search').off('input').on('input', function() {
            const term = $(this).val().toLowerCase();
            $('#treatment_lunch_rest_tbody tr.dne-list-pm-row').each(function() {
                const $row = $(this);
                if ($row.find('td[colspan]').length) {
                    $row.show();
                    return;
                }
                const match = !term || $row.data('pet-name').indexOf(term) !== -1 || $row.data(
                    'customer-name').indexOf(term) !== -1;
                $row.toggle(match);
            });
        });
    }
}

function renderLunchForm(checkinData) {
    const reportsAmData = workflowData['reports_am'] || {};
    const reportsAmIds = ((reportsAmData.selected_pet_ids || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id)));
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmIssues = reportsAmData.issues || {};
    const lunchIds = getLunchStepPetIds(checkinData);
    const checkinMap = {};
    if (checkinData && Array.isArray(checkinData)) {
        checkinData.forEach(item => {
            const workflowId = getWorkflowItemId(item);
            if (workflowId !== null) {
                checkinMap[workflowId] = item;
            }
        });
    }

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#empty_state_message').hide();
    const savedLunchNotes = (workflowData['lunch_tlr'] || {}).notes || {};
    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 180px;">Pet</th><th style="min-width: 180px;">Customer</th><th style="min-width: 200px;">Source</th><th style="min-width: 120px;">Meals (Dry or Wet)</th><th style="min-width: 80px;">Amount</th><th style="min-width: 200px;">Issue</th><th style="min-width: 220px;">Notes</th></tr>'
    );
    let bodyHtml = '';
    if (lunchIds.length === 0) {
        $('#treatment_lunch_rest_form_container').hide();
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No Lunch Today');
        $('#treatment_lunch_rest_tbody').html('');
        updatePetCountsDisplay(0, null);
        updateProcessStatus('lunch_tlr', 'No Lunch');
        return false;
    } else {
        $('#treatment_lunch_rest_form_container').show();
        lunchIds.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;
            const aid = parseInt(appointmentId, 10);
            const fromReportsAm = reportsAmIds.indexOf(aid) !== -1;
            const fromYesterday = yesterdayIds.indexOf(aid) !== -1;
            const checkinItem = checkinMap[appointmentId];
            const lunchDry = checkinItem && (checkinItem.lunch_dry === true || checkinItem.lunch_dry ===
                'true');
            const lunchWet = checkinItem && (checkinItem.lunch_wet === true || checkinItem.lunch_wet ===
                'true');
            const isScheduledLunch = lunchDry || lunchWet;
            let lunchType = '';
            if (isScheduledLunch) {
                lunchType = lunchDry && lunchWet ? ' (Dry, Wet)' : (lunchDry ? ' (Dry)' : ' (Wet)');
            }
            let sourceText = fromReportsAm ? 'Do not eat AM meals' : (fromYesterday ?
                'Do not eat yesterday\'s PM Meals' : (isScheduledLunch ? 'Scheduled for lunch' + lunchType :
                    '-'));
            const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';
            const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';

            const item = checkinMap[appointmentId];
            const flows = (item && item.checkin) ? (item.checkin.flows || {}) : {};
            const dryFoodRows = getFoodRowsFromFlows(flows, 'dry');
            const wetFoodRows = getFoodRowsFromFlows(flows, 'wet');
            const mealTypes = [];
            const amounts = [];
            if (dryFoodRows.length > 0) {
                mealTypes.push('Dry');
                dryFoodRows.forEach(row => {
                    if (row.amount) amounts.push(row.amount);
                });
            }
            if (wetFoodRows.length > 0) {
                mealTypes.push('Wet');
                wetFoodRows.forEach(row => {
                    if (row.amount) amounts.push(row.amount);
                });
            }
            const mealsText = mealTypes.length > 0 ? mealTypes.join(' or ') : '-';
            const amountText = amounts.length > 0 ? amounts.join(' / ') : '-';
            const issueVal = fromReportsAm ?
                (reportsAmIssues[appointmentId] || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') :
                (fromYesterday ? (yesterdayReportsPmIssues[appointmentId] || yesterdayReportsPmIssues[String(
                    appointmentId)] || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') : '');

            bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;
            bodyHtml +=
                `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
            bodyHtml +=
                `<td><div class="flex items-center space-x-3"><img src="${customerAvatarUrl}" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.customer_name || 'N/A'}</span></div></td>`;
            bodyHtml += `<td><span class="text-sm">${sourceText}</span></td>`;
            bodyHtml += `<td><span class="text-sm">${mealsText}</span></td>`;
            bodyHtml += `<td><span class="text-sm">${amountText}</span></td>`;
            const savedNote = (savedLunchNotes[appointmentId] || savedLunchNotes[String(appointmentId)] || '')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
            bodyHtml += `<td><span class="text-sm">${issueVal || '—'}</span></td>`;
            bodyHtml +=
                `<td><textarea id="lunch_note_${appointmentId}" class="textarea textarea-bordered textarea-sm w-full" rows="2" style="min-height: 2rem;" placeholder="Notes...">${savedNote}</textarea></td>`;
            bodyHtml += '</tr>';
        });
    }
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
    updatePetCountsDisplay(lunchIds.length, null);
    return true;
}

function renderRestForm(checkinData) {
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const staffIdToName = {};
    $('#staff_sign_off option').each(function() {
        const v = $(this).val();
        if (v) staffIdToName[v] = $(this).text();
    });
    const checkPetTime = checkPetData.process_time || '—';
    const checkPetStaffIds = checkPetData.staff_sign_off || [];
    const checkPetEmployee = (checkPetStaffIds[0] != null ? (staffIdToName[String(checkPetStaffIds[0])] || '—') : '—');

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#empty_state_message').hide();
    $('#rest_tlr_check_pet_time').text(checkPetTime);
    $('#rest_tlr_check_pet_employee').text(checkPetEmployee);

    // Only include pets where Assign Rest is checked in Treatment Plan
    const treatmentPlanDataForRest = workflowData['treatment_plan'] || {};
    const treatmentDataForRest = treatmentPlanDataForRest.treatment_data || {};
    const assignRestIds = (treatmentPlanDataForRest.selected_pet_ids || [])
        .map(function(id) {
            return parseInt(id, 10);
        })
        .filter(function(id) {
            if (isNaN(id)) return false;
            const petTreatmentData = treatmentDataForRest[id] || treatmentDataForRest[String(id)] || {};
            return petTreatmentData.assign_rest === true || petTreatmentData.assign_rest === 'true' ||
                petTreatmentData.assign_rest === 1 || petTreatmentData.assign_rest === '1';
        });
    let restScheduledIds = [];
    const checkinRestMeta = {};
    if (checkinData && Array.isArray(checkinData)) {
        checkinData.forEach(function(item) {
            const aid = getWorkflowItemId(item);
            if (aid !== null) {
                checkinRestMeta[String(aid)] = {
                    rest_required: item.rest_required === true || item.rest_required === 'true' || item
                        .rest_required === 1 || item.rest_required === '1',
                    rest_note: ((item.rest_note || '') + '').trim()
                };
            }
            if (item.scheduled_rest === true || item.scheduled_rest === 'true') {
                if (aid !== null && appointmentToPetMap[aid]) restScheduledIds.push(aid);
            }
        });
    }
    const allRestIds = [...new Set([...assignRestIds, ...restScheduledIds])];
    const assignRestSet = new Set(assignRestIds.map(function(id) {
        return String(id);
    }));

    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 280px;">Issue</th><th style="min-width: 300px;">Rest Detail</th></tr>'
    );
    let bodyHtml = '';
    if (allRestIds.length === 0) {
        $('#treatment_lunch_rest_form_container').hide();
        $('#pet_details_table').hide();
        $('#no_details_message').hide();
        $('#empty_state_message').show();
        $('#empty_state_text').text('No Rest Today');
        $('#treatment_lunch_rest_tbody').html('');
        updatePetCountsDisplay(0, null);
        updateProcessStatus('rest_tlr', 'No Rest');
        return false;
    } else {
        $('#treatment_lunch_rest_form_container').show();
        allRestIds.forEach(function(appointmentId) {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;
            const fromAssignRest = assignRestSet.has(String(appointmentId));
            const petTreatmentData = treatmentDataForRest[appointmentId] || treatmentDataForRest[String(
                appointmentId)] || {};
            const checkinRestData = checkinRestMeta[String(appointmentId)] || {};
            const petCheckData = checkPetCheckData[appointmentId] || {};
            let issuesText = '—';
            let restDetailText = '—';
            if (fromAssignRest) {
                const issues = [];
                Object.keys(petCheckData).forEach(function(partKey) {
                    if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[
                        partKey] || partKey);
                });
                issuesText = issues.join(', ') || '—';
                restDetailText = ((petTreatmentData.rest_detail || '') + '').trim() || '—';
            } else {
                issuesText = 'Scheduled rest';
                restDetailText = checkinRestData.rest_note || '—';
            }
            const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';
            const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
            bodyHtml += '<tr class="hover:bg-base-200" data-appointment-id="' + appointmentId + '">';
            bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + petAvatarUrl +
                '" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet.pet_name ||
                    'N/A') + '</span></div></td>';
            bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + customerAvatarUrl +
                '" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet
                    .customer_name || 'N/A') + '</span></div></td>';
            bodyHtml += '<td><span class="text-sm">' + (issuesText.replace(/</g, '&lt;').replace(/>/g,
                '&gt;')) + '</span></td>';
            bodyHtml += '<td><span class="text-sm">' + (restDetailText.replace(/</g, '&lt;').replace(/>/g,
                '&gt;')) + '</span></td>';
            bodyHtml += '</tr>';
        });
    }
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
    updatePetCountsDisplay(allRestIds.length, null);
    return true;
}

function renderReportRestForm(checkinData) {
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const staffIdToName = {};
    $('#staff_sign_off option').each(function() {
        const v = $(this).val();
        if (v) staffIdToName[v] = $(this).text();
    });
    const checkPetTime = checkPetData.process_time || '—';
    const checkPetStaffIds = checkPetData.staff_sign_off || [];
    const checkPetEmployee = (checkPetStaffIds[0] != null ? (staffIdToName[String(checkPetStaffIds[0])] || '—') : '—');
    $('#dne_list_search_bar').show();
    $('#dne_list_search').val('');
    $('#dne_list_time').text(checkPetTime);
    $('#dne_list_employee').text(checkPetEmployee);

    const treatmentPlanDataForRest = workflowData['treatment_plan'] || {};
    const treatmentDataForRest = treatmentPlanDataForRest.treatment_data || {};
    const assignRestIds = (treatmentPlanDataForRest.selected_pet_ids || [])
        .map(function(id) {
            return parseInt(id, 10);
        })
        .filter(function(id) {
            if (isNaN(id)) return false;
            const petTreatmentData = treatmentDataForRest[id] || treatmentDataForRest[String(id)] || {};
            return petTreatmentData.assign_rest === true || petTreatmentData.assign_rest === 'true' ||
                petTreatmentData.assign_rest === 1 || petTreatmentData.assign_rest === '1';
        });
    let restScheduledIds = [];
    const checkinRestMeta = {};
    if (checkinData && Array.isArray(checkinData)) {
        checkinData.forEach(function(item) {
            const aid = getWorkflowItemId(item);
            if (aid !== null) {
                checkinRestMeta[String(aid)] = {
                    rest_required: item.rest_required === true || item.rest_required === 'true' || item
                        .rest_required === 1 || item.rest_required === '1',
                    rest_note: ((item.rest_note || '') + '').trim()
                };
            }
            if (item.scheduled_rest === true || item.scheduled_rest === 'true') {
                if (aid !== null && appointmentToPetMap[aid]) restScheduledIds.push(aid);
            }
        });
    }
    const allRestIds = [...new Set([...assignRestIds, ...restScheduledIds])];
    const assignRestSet = new Set(assignRestIds.map(function(id) {
        return String(id);
    }));

    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 200px;">Pet</th><th style="min-width: 200px;">Customer</th><th style="min-width: 280px;">Issue</th><th style="min-width: 300px;">Rest Detail</th></tr>'
    );
    let bodyHtml = '';
    if (allRestIds.length === 0) {
        bodyHtml =
            '<tr data-empty><td colspan="4" class="text-center p-4 text-base-content/70">No pets with Assign Rest selected in Treatment Plan and no pets scheduled for Rest.</td></tr>';
    } else {
        allRestIds.forEach(function(appointmentId) {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;
            const fromAssignRest = assignRestSet.has(String(appointmentId));
            const petTreatmentData = treatmentDataForRest[appointmentId] || treatmentDataForRest[String(
                appointmentId)] || {};
            const checkinRestData = checkinRestMeta[String(appointmentId)] || {};
            const petCheckData = checkPetCheckData[appointmentId] || {};
            let issuesText = '—';
            let restDetailText = '—';
            if (fromAssignRest) {
                const issues = [];
                Object.keys(petCheckData).forEach(function(partKey) {
                    if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[
                        partKey] || partKey);
                });
                issuesText = issues.join(', ') || '—';
                restDetailText = ((petTreatmentData.rest_detail || '') + '').trim() || '—';
            } else {
                issuesText = 'Scheduled rest';
                restDetailText = checkinRestData.rest_note || '—';
            }
            const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';
            const customerAvatarUrl = pet.customer_avatar ? '{{ asset("storage/profiles/") }}/' + pet
                .customer_avatar : '{{ asset("images/default-user-avatar.png") }}';
            const petName = (pet.pet_name || '').toLowerCase();
            const customerName = (pet.customer_name || '').toLowerCase();
            bodyHtml += '<tr class="hover:bg-base-200 report-rest-row" data-appointment-id="' + appointmentId +
                '" data-pet-name="' + petName.replace(/"/g, '&quot;') + '" data-customer-name="' + customerName
                .replace(/"/g, '&quot;') + '">';
            bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + petAvatarUrl +
                '" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet.pet_name ||
                    'N/A') + '</span></div></td>';
            bodyHtml += '<td><div class="flex items-center space-x-3"><img src="' + customerAvatarUrl +
                '" alt="Customer" class="mask mask-squircle bg-base-200 size-10" /><span>' + (pet
                    .customer_name || 'N/A') + '</span></div></td>';
            bodyHtml += '<td><span class="text-sm">' + (issuesText.replace(/</g, '&lt;').replace(/>/g,
                '&gt;')) + '</span></td>';
            bodyHtml += '<td><span class="text-sm">' + (restDetailText.replace(/</g, '&lt;').replace(/>/g,
                '&gt;')) + '</span></td>';
            bodyHtml += '</tr>';
        });
    }
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
    $('#dne_list_search').off('input').on('input', function() {
        const term = $(this).val().toLowerCase();
        $('#treatment_lunch_rest_tbody tr.report-rest-row').each(function() {
            const $row = $(this);
            if ($row.find('td[colspan]').length) {
                $row.show();
                return;
            }
            const match = !term || ($row.data('pet-name') || '').indexOf(term) !== -1 || ($row.data(
                'customer-name') || '').indexOf(term) !== -1;
            $row.toggle(match);
        });
    });
}

function renderTreatmentConcernForm() {
    const treatmentListBasePetIds = getTreatmentConcernPetIds();
    const treatmentPlanData = workflowData['treatment_plan'] || {};
    const treatmentPlanPetIds = treatmentPlanData.selected_pet_ids || [];
    const checkPetData = workflowData['check_pet'] || {};
    const checkPetCheckData = checkPetData.check_data || {};
    const treatmentsTlrData = workflowData['treatments_tlr'] || {};
    const treatmentsTlrResults = treatmentsTlrData.results || {};
    const currentData = workflowData['treatment_concern'] || {};
    const savedResults = currentData.results || {};
    const nextDayTlrData = workflowData['next_day_treatment_list_tlr'] || {};
    const nextDayVetVisitMap = nextDayTlrData.vet_visit || {};
    const yesterdayIds = (yesterdayNextDayPetIds || []).map(id => parseInt(id, 10)).filter(id => !isNaN(id));
    const reportsAmIdsRender = ((workflowData['reports_am'] || {}).selected_pet_ids || []).map(id => parseInt(id, 10))
        .filter(id => !isNaN(id));

    $('#dne_list_search_bar').hide();
    $('#rest_nose_to_tail_inline').hide();
    $('#treatment_lunch_rest_thead').html(
        '<tr><th style="min-width: 180px;">Dog Name</th><th style="min-width: 200px;">Issue</th><th style="min-width: 120px;">In-house/Vet visit</th><th style="min-width: 220px;">Detail</th><th style="min-width: 180px;">Status</th></tr>'
    );
    let bodyHtml = '';
    if (treatmentListBasePetIds.length === 0) {
        bodyHtml =
            '<tr data-empty><td colspan="5" class="text-center p-4 text-base-content/70">No pets with issues from nose-to-tail check. Complete Treatment List and Treatments (TLR) first. (Pets who did not eat AM/PM meals are listed in Issues and Concerns on the End of Day report.)</td></tr>';
    } else {
        treatmentListBasePetIds.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;
            const aid = parseInt(appointmentId, 10);
            const inTreatmentPlan = treatmentPlanPetIds.indexOf(aid) !== -1 || treatmentPlanPetIds.indexOf(
                String(appointmentId)) !== -1;
            const fromYesterday = !inTreatmentPlan && yesterdayIds.indexOf(aid) !== -1;
            const petCheckData = checkPetCheckData[appointmentId] || {};
            const issues = [];
            if (inTreatmentPlan) {
                Object.keys(petCheckData).forEach(partKey => {
                    if (petCheckData[partKey].status === 'issue') issues.push(bodyPartsMapTLR[
                        partKey] || partKey);
                });
            }
            const issuesText = inTreatmentPlan ? (issues.join(', ') || 'No issues') : (fromYesterday ?
                'Carried from previous day' : '—');
            const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img :
                '{{ asset("images/no_image.jpg") }}';
            const saved = savedResults[appointmentId] || treatmentsTlrResults[appointmentId] || {};
            const vetVisitFromNextDay = nextDayVetVisitMap[appointmentId] === true || nextDayVetVisitMap[
                appointmentId] === 'true';
            const vetVisitFromSaved = saved.vet_visit === true || saved.vet_visit === 'true';
            const hasNextDayVetVisit = (String(appointmentId) in nextDayVetVisitMap) || (appointmentId in
                nextDayVetVisitMap);
            const vetVisit = hasNextDayVetVisit ? vetVisitFromNextDay : vetVisitFromSaved;
            const detailVal = (saved.detail || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const resultVal = saved.result || '';
            const statusLabel = resultVal === 'continue' ? 'Continue' : (resultVal === 'resolved' ? 'Resolved' :
                (resultVal === 'escalate' ? 'Escalate' : '—'));
            bodyHtml += `<tr class="hover:bg-base-200" data-appointment-id="${appointmentId}">`;
            bodyHtml +=
                `<td><div class="flex items-center space-x-3"><img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" /><span>${pet.pet_name || 'N/A'}</span></div></td>`;
            bodyHtml += `<td><span class="text-sm">${issuesText}</span></td>`;
            bodyHtml += `<td><span class="text-sm">${vetVisit ? 'Yes' : 'No'}</span></td>`;
            bodyHtml += `<td><span class="text-sm">${detailVal || '—'}</span></td>`;
            bodyHtml += `<td><span class="text-sm">${statusLabel}</span></td>`;
            bodyHtml += '</tr>';
        });
    }
    $('#treatment_lunch_rest_tbody').html(bodyHtml);
}

function renderPrnForm(checkinData) {
    const savedPrnData = (workflowData['prn_meds'] && workflowData['prn_meds'].prn_records) ? workflowData['prn_meds'].prn_records : {};

    // Only show pets that have at least one medication with dispense_prn checked at check-in
    const prnPets = (checkinData && checkinData.length > 0)
        ? checkinData.filter(function(pet) {
              const flows = (pet.checkin || {}).flows || {};
              const medRows = getMedicationRowsFromFlows(flows);
              return medRows.some(function(row) { return isFlowChecked(row && row.dispense_prn); });
          })
        : [];

    let theadHtml = `<tr>
      <th>Pet Name</th>
      <th>Customer</th>
      <th>Medication Name</th>
      <th>Amount / Dose</th>
    </tr>`;
    $('#prn_thead').html(theadHtml);

    let tbodyHtml = '';
    if (prnPets.length > 0) {
        prnPets.forEach(function(pet) {
            const workflowId = pet.workflow_id || pet.appointment_id;
            const saved = savedPrnData[workflowId] || {};
            const medicationName = saved.medication_name || '';
            const amount = saved.amount || '';
            const petName = pet.pet_name || 'Unknown Pet';
            const customerName = pet.customer_name || '';
                        const petAvatarUrl = pet.pet_img ? '{{ asset("storage/pets/") }}/' + pet.pet_img : '{{ asset("images/no_image.jpg") }}';
            tbodyHtml += `<tr>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" />
                                    <span class="font-medium">${petName}</span>
                                </div>
                            </td>
              <td class="text-base-content/70">${customerName}</td>
              <td><input type="text" class="input input-sm prn-medication-name" data-workflow-id="${workflowId}" value="${medicationName.replace(/"/g, '&quot;')}" placeholder="Medication name" /></td>
              <td><input type="text" class="input input-sm prn-amount" data-workflow-id="${workflowId}" value="${amount.replace(/"/g, '&quot;')}" placeholder="e.g. 1 tablet" /></td>
            </tr>`;
        });
    } else {
        tbodyHtml = '<tr><td colspan="4" class="text-center p-4 text-base-content/70">No pets with PRN medications found for this date.</td></tr>';
    }
    $('#prn_tbody').html(tbodyHtml);

    $('#prn_tbody').off('input.prn').on('input.prn', '.prn-medication-name, .prn-amount', function() {
        savePrnRecords();
    });

    if (prnPets.length > 0) {
        $('#save_details_btn_container').show();
        $('#staff_sign_off_container').show();
    } else {
        $('#save_details_btn_container').hide();
        $('#staff_sign_off_container').hide();
    }
}

function savePrnRecords() {
    const prn_records = {};
    $('#prn_tbody tr').each(function() {
        const $medInput = $(this).find('.prn-medication-name');
        const $amtInput = $(this).find('.prn-amount');
        if ($medInput.length) {
            const workflowId = $medInput.data('workflow-id');
            prn_records[workflowId] = {
                medication_name: $medInput.val(),
                amount: $amtInput.val()
            };
        }
    });
    if (!workflowData['prn_meds']) workflowData['prn_meds'] = {};
    workflowData['prn_meds'].prn_records = prn_records;
}

function renderReportPrnForm() {
    const savedPrnData = (workflowData['prn_meds'] && workflowData['prn_meds'].prn_records) ? workflowData['prn_meds'].prn_records : {};

    let theadHtml = `<tr>
      <th>Pet Name</th>
      <th>Medication Name</th>
      <th>Amount / Dose</th>
    </tr>`;
    $('#prn_thead').html(theadHtml);

    const entries = Object.entries(savedPrnData);
    let tbodyHtml = '';
    if (entries.length > 0) {
        entries.forEach(function([workflowId, rec]) {
            const medName = rec.medication_name || '—';
            const amount = rec.amount || '—';
                        const pet = appointmentToPetMap[workflowId] || appointmentToPetMap[String(workflowId)] || null;
                        const petName = pet && pet.pet_name ? pet.pet_name : ('Pet #' + workflowId);
                        const petImg = pet && pet.pet_img ? pet.pet_img : '';
                        const petAvatarUrl = petImg ? '{{ asset("storage/pets/") }}/' + petImg : '{{ asset("images/no_image.jpg") }}';
            tbodyHtml += `<tr>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <img src="${petAvatarUrl}" alt="Pet" class="mask mask-squircle bg-base-200 size-10" />
                                    <span class="font-medium">${petName}</span>
                                </div>
                            </td>
              <td>${medName}</td>
              <td>${amount}</td>
            </tr>`;
        });
    } else {
        tbodyHtml = '<tr><td colspan="3" class="text-center p-4 text-base-content/70">No PRN records saved for this date.</td></tr>';
    }
    $('#prn_tbody').html(tbodyHtml);
}

function renderEndOfDayForm() {
    const date = $('#workflow_date').val() || '{{ \Carbon\Carbon::parse($process->date ?? "now")->format("Y-m-d") }}';
    const reportUrl = '{{ url("/reports/end-of-day") }}?date=' + encodeURIComponent(date) + '&embed=1';
    $('#end_of_day_report_content').html('<p class="text-base-content/70 text-sm">Loading End of Day report…</p>');
    $.get(reportUrl).done(function(html) {
        $('#end_of_day_report_content').html(html);
    }).fail(function() {
        $('#end_of_day_report_content').html(
            '<p class="text-error text-sm">Could not load report. Try again or open <a href="' + reportUrl
            .replace('&embed=1', '') +
            '" target="_blank" class="link">End of Day report</a> in a new tab.</p>');
    });
}

$('#save_pet_details_btn').on('click', function() {
    const isFoodStep =
        (currentTab === 'am-feeding-meds' && (currentProcessItem === 'food_prep_am' || currentProcessItem ===
            'feeding_am')) ||
        (currentTab === 'pm-feeding-meds' && (currentProcessItem === 'food_prep_pm' || currentProcessItem ===
            'feeding_pm'));
    const isFoodNoRecord = isFoodStep && $('#pet_details_tbody tr[data-appointment-id]').length === 0;
    const isMedsStep = ['meds_prep_am', 'meds_dispense_am', 'meds_prep_pm', 'meds_dispense_pm'].includes(
        currentProcessItem);
    const isMedsNoRecord = isMedsStep && $('#pet_details_tbody tr[data-appointment-id]').length === 0;
    const isReportsNoIssue = (currentProcessItem === 'reports_am' || currentProcessItem === 'reports_pm') && $(
        '#pet_details_tbody tr[data-appointment-id]').length === 0;

    if (currentProcessItem === 'check_pet') {
        const checkPetData = {};
        const fleaTickData = {};
        selectedAppointmentIds.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;

            const bodyParts = [
                'Nose', 'Ears', 'Eyes', 'Mouth', 'Body/Coat', 'Paws/Feet', 'Abdomen', 'Digestive',
                'Diarrhea'
            ];

            checkPetData[appointmentId] = {};
            bodyParts.forEach(part => {
                const fieldName =
                    `check_${appointmentId}_${part.toLowerCase().replace(/\s+/g, '_')}`;
                const selectedValue = $(`input[name="${fieldName}"]:checked`).val();

                checkPetData[appointmentId][part.toLowerCase().replace(/\s+/g, '_')] = {
                    status: selectedValue || ''
                };
            });

            fleaTickData[appointmentId] = $(`.flea-tick-checkbox[data-appointment-id="${appointmentId}"]`).is(':checked');
            if (fleaTickData[appointmentId]) {
                checkPetData[appointmentId].flea_tick = {
                    status: 'issue'
                };
            } else {
                checkPetData[appointmentId].flea_tick = {
                    status: ''
                };
            }
        });

        // Store workflow data
        if (!workflowData[currentProcessItem]) {
            workflowData[currentProcessItem] = {};
        }
        workflowData[currentProcessItem].selected_pet_ids = selectedAppointmentIds;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].check_data = checkPetData;
        workflowData[currentProcessItem].flea_tick_data = fleaTickData;
    } else if (currentProcessItem === 'treatment_plan') {
        // Get check_pet data to filter pets with issues
        const checkPetData = workflowData['check_pet'] || {};
        const checkPetCheckData = checkPetData.check_data || {};

        // Filter pets that have at least one issue
        const petsWithIssues = selectedAppointmentIds.filter(appointmentId => {
            const petCheckData = checkPetCheckData[appointmentId] || {};
            return Object.values(petCheckData).some(partData => partData.status === 'issue');
        });

        const treatmentPlanData = {};
        petsWithIssues.forEach(appointmentId => {
            const pet = appointmentToPetMap[appointmentId];
            if (!pet) return;

            const option = $(`input[name="treatment_option_${appointmentId}"]:checked`).val() || '';
            const additionalOptions = $(`#treatment_multi_${appointmentId}`).val() || [];
            const detail = $(`#treatment_detail_${appointmentId}`).val() || '';
            let restDetail = $(`#rest_detail_${appointmentId}`).val() || '';
            let assignRest = $(`#assign_rest_${appointmentId}`).is(':checked');
            const checkinRestData = checkinRestMetaByAppointmentId[String(appointmentId)] || {};
            if (checkinRestData.is_assigned === true) {
                assignRest = true;
                if (!restDetail.trim()) {
                    restDetail = checkinRestData.rest_note || '';
                }
            }

            treatmentPlanData[appointmentId] = {
                option: option,
                additional_options: Array.isArray(additionalOptions) ? additionalOptions : (
                    additionalOptions ? [additionalOptions] : []),
                selected_treatments: Array.isArray(additionalOptions) ? additionalOptions : (
                    additionalOptions ? [additionalOptions] : []),
                detail: detail,
                rest_detail: restDetail,
                assign_rest: assignRest
            };
        });

        if (!workflowData[currentProcessItem]) {
            workflowData[currentProcessItem] = {};
        }
        workflowData[currentProcessItem].selected_pet_ids = petsWithIssues;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].treatment_data = treatmentPlanData;
        // Auto-populate treatment_list so TLR and reports tab work without a separate step
        if (!workflowData['treatment_list']) workflowData['treatment_list'] = {};
        workflowData['treatment_list'].selected_pet_ids = petsWithIssues;
        workflowData['treatment_list'].process_type = 'treatment_list';
        const autoCompletedTreatments = {};
        petsWithIssues.forEach(function(aid) {
            autoCompletedTreatments[aid] = true;
        });
        workflowData['treatment_list'].completed_treatments = autoCompletedTreatments;
    } else if (currentProcessItem === 'lunch_tlr') {
        const lunchPetIds = getLunchStepPetIds(lastLunchCheckinData);
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].selected_pet_ids = lunchPetIds;
        workflowData[currentProcessItem].process_type = 'lunch_tlr';
        const lunchNotes = {};
        lunchPetIds.forEach(function(aid) {
            const note = $('#lunch_note_' + aid).val() || '';
            if (note) lunchNotes[aid] = note;
        });
        workflowData[currentProcessItem].notes = lunchNotes;
    } else if (currentProcessItem === 'rest_tlr') {
        const treatmentPlanDataForRest = workflowData['treatment_plan'] || {};
        const treatmentDataForRest = treatmentPlanDataForRest.treatment_data || {};
        const assignRestIds = (treatmentPlanDataForRest.selected_pet_ids || [])
            .map(function(id) {
                return parseInt(id, 10);
            })
            .filter(function(id) {
                if (isNaN(id)) return false;
                const petTreatmentData = treatmentDataForRest[id] || treatmentDataForRest[String(id)] || {};
                return petTreatmentData.assign_rest === true || petTreatmentData.assign_rest === 'true' ||
                    petTreatmentData.assign_rest === 1 || petTreatmentData.assign_rest === '1';
            });
        let restScheduledIds = [];
        const restNotes = {};
        assignRestIds.forEach(function(aid) {
            const petTreatmentData = treatmentDataForRest[aid] || treatmentDataForRest[String(aid)] ||
                {};
            const detailText = ((petTreatmentData.rest_detail || '') + '').trim();
            if (detailText) restNotes[aid] = detailText;
        });
        if (lastRestCheckinData && Array.isArray(lastRestCheckinData)) {
            lastRestCheckinData.forEach(function(item) {
                if (item.scheduled_rest === true || item.scheduled_rest === 'true') {
                    const aid = getWorkflowItemId(item);
                    if (aid !== null && appointmentToPetMap[aid]) {
                        restScheduledIds.push(aid);
                        const checkinRestNote = ((item.rest_note || '') + '').trim();
                        if (!restNotes[aid] && checkinRestNote) restNotes[aid] = checkinRestNote;
                    }
                }
            });
        }
        const allRestIds = [...new Set([...assignRestIds, ...restScheduledIds])];
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].selected_pet_ids = allRestIds;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].notes = restNotes;
    } else if (currentProcessItem === 'treatment_list_tlr') {
        const treatmentListBasePetIds = getTreatmentListBasePetIds();
        const checkPetDataForTime = workflowData['check_pet'] || {};
        const treatmentPlanDataForTime = workflowData['treatment_plan'] || {};
        const workflowDate = $('#workflow_date').val() || '';
        const prevProcessTime = checkPetDataForTime.process_time || checkPetDataForTime.processTime ||
            treatmentPlanDataForTime.process_time || treatmentPlanDataForTime.processTime || '00:00';
        const reported = {};
        treatmentListBasePetIds.forEach(appointmentId => {
            reported[appointmentId] = workflowDate && prevProcessTime ? (workflowDate + 'T' +
                prevProcessTime) : '';
        });
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].selected_pet_ids = treatmentListBasePetIds;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].reported = reported;
    } else if (currentProcessItem === 'treatments_tlr') {
        const treatmentListBasePetIds = getTreatmentListBasePetIds();
        const unselectedPets = treatmentListBasePetIds.filter(appointmentId => !$(
            `input[name="result_tlr_${appointmentId}"]:checked`).val());
        if (unselectedPets.length > 0) {
            $('#alert_message').text('Please select the status for all pets before saving.');
            alert_modal.showModal();
            return;
        }
        const missingEscalateDetails = [];
        treatmentListBasePetIds.forEach(appointmentId => {
            const result = $(`input[name="result_tlr_${appointmentId}"]:checked`).val() || '';
            if (result === 'escalate') {
                const detail = $(`.escalate-detail-tlr[data-appointment-id="${appointmentId}"]`).val() || '';
                if (!detail.trim()) missingEscalateDetails.push(appointmentId);
            }
        });
        if (missingEscalateDetails.length > 0) {
            $('#alert_message').text('Please enter escalate detail for all pets marked as Escalate.');
            alert_modal.showModal();
            return;
        }
        const results = {};
        treatmentListBasePetIds.forEach(appointmentId => {
            const result = $(`input[name="result_tlr_${appointmentId}"]:checked`).val() || '';
            const detail = result === 'escalate' ? ($(`.escalate-detail-tlr[data-appointment-id="${appointmentId}"]`)
                .val() || '').trim() : '';
            results[appointmentId] = {
                result,
                detail
            };
        });
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].selected_pet_ids = treatmentListBasePetIds;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].results = results;
    } else if (currentProcessItem === 'next_day_treatment_list_tlr') {
        const treatmentListBasePetIds = getTreatmentListBasePetIds();
        const treatmentsTlrResults = (workflowData['treatments_tlr'] || {}).results || {};
        const nextDayPetIds = treatmentListBasePetIds.filter(appointmentId => {
            const resultData = treatmentsTlrResults[appointmentId];
            return resultData && (resultData.result === 'continue' || resultData.result === 'escalate');
        });
        const checkPetDataForTime = workflowData['check_pet'] || {};
        const treatmentPlanDataForTime = workflowData['treatment_plan'] || {};
        const workflowDate = $('#workflow_date').val() || '';
        const prevProcessTime = checkPetDataForTime.process_time || checkPetDataForTime.processTime ||
            treatmentPlanDataForTime.process_time || treatmentPlanDataForTime.processTime || '00:00';
        const selectedIds = [];
        const reported = {};
        const results = {};
        $('.next-day-row-tlr').each(function() {
            const appointmentId = $(this).data('appointment-id');
            if ($(this).is(':checked')) {
                selectedIds.push(parseInt(appointmentId));
                reported[appointmentId] = prevProcessTime ? (workflowDate ? workflowDate + 'T' +
                    prevProcessTime : prevProcessTime) : '';
            }
        });
        nextDayPetIds.forEach(function(appointmentId) {
            const sourceResult = treatmentsTlrResults[appointmentId] || {};
            results[appointmentId] = {
                result: sourceResult.result || '',
                detail: sourceResult.detail || ''
            };
        });
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].selected_pet_ids = selectedIds;
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].reported = reported;
        workflowData[currentProcessItem].results = results;
    } else if (currentProcessItem === 'dne_list_am' || currentProcessItem === 'dne_list_pm') {
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].process_type = currentProcessItem;
    } else if (currentProcessItem === 'report_lunch' || currentProcessItem === 'report_rest') {
        // Read-only steps: no payload; time/employee shown from lunch_tlr / check_pet
        const key = currentProcessItem;
        if (!workflowData[key]) workflowData[key] = {};
        workflowData[key].process_type = key;
    } else if (currentProcessItem === 'treatment_concern') {
        const treatmentConcernPetIds = getTreatmentConcernPetIds();
        if (!workflowData[currentProcessItem]) workflowData[currentProcessItem] = {};
        workflowData[currentProcessItem].process_type = currentProcessItem;
        workflowData[currentProcessItem].selected_pet_ids = treatmentConcernPetIds;
    } else if (currentProcessItem === 'prn_meds') {
        savePrnRecords();
        if (!workflowData['prn_meds']) workflowData['prn_meds'] = {};
        workflowData['prn_meds'].process_type = 'prn_meds';
    } else if (currentProcessItem === 'end_of_day') {
        if (!workflowData['end_of_day']) workflowData['end_of_day'] = {};
        workflowData['end_of_day'].process_type = 'end_of_day';
    } else {
        const isReportsAmPm = currentProcessItem === 'reports_am' || currentProcessItem === 'reports_pm';
        if (!isReportsAmPm) {
            const checkedIds = $('.pet-checkbox:checked').map(function() {
                return $(this).data('appointment-id');
            }).get();

            if (!workflowData[currentProcessItem]) {
                workflowData[currentProcessItem] = {};
            }
            workflowData[currentProcessItem].selected_pet_ids = checkedIds;
            workflowData[currentProcessItem].process_type = currentProcessItem;
        }
    }

    // Save AM/PM Reports (reports_am / reports_pm)
    if (currentProcessItem === 'reports_am' || currentProcessItem === 'reports_pm') {
        const issues = {};
        $('#pet_details_tbody .issue-input').each(function() {
            const apptId = $(this).data('appointment-id');
            issues[apptId] = $(this).val() || '';
        });
        const reportSelectedIds = $('#pet_details_tbody tr[data-appointment-id]').map(function() {
            return $(this).data('appointment-id');
        }).get();
        const staffValue = isReportsNoIssue ? '' : $('#staff_sign_off').val();
        const staffSignOff = staffValue ? [staffValue] : [];
        const processTime = isReportsNoIssue ? '' : ($('#process_time').val() || '');
        workflowData[currentProcessItem] = {
            selected_pet_ids: reportSelectedIds,
            issues: issues,
            process_type: currentProcessItem,
            staff_sign_off: staffSignOff,
            process_time: processTime
        };
    }

    const noSignOffSteps = ['dne_list_am', 'dne_list_pm', 'report_lunch', 'report_rest', 'report_prn', 'treatment_concern',
        'end_of_day'
    ];
    const skipSignOff = noSignOffSteps.includes(currentProcessItem) || isFoodNoRecord || isMedsNoRecord ||
        isReportsNoIssue;

    if (skipSignOff) {
        if (!workflowData[currentProcessItem]) {
            workflowData[currentProcessItem] = {};
        }
        workflowData[currentProcessItem].staff_sign_off = [];
        workflowData[currentProcessItem].process_time = '';
    } else {
        const staffValue = $('#staff_sign_off').val();
        const staffSignOff = staffValue ? [staffValue] : [];
        const processTime = $('#process_time').val() || '';

        if (staffSignOff.length === 0 || !processTime) {
            $('#alert_message').text('Please select at least one employee and time for this step.');
            alert_modal.showModal();
            return;
        }

        const isReportsAmPmSignOff = currentProcessItem === 'reports_am' || currentProcessItem === 'reports_pm';
        if (!isReportsAmPmSignOff) {
            if (!workflowData[currentProcessItem]) {
                workflowData[currentProcessItem] = {};
            }
            workflowData[currentProcessItem].staff_sign_off = staffSignOff;
            workflowData[currentProcessItem].process_time = processTime;
        }
    }

    updateWorkflowProgress();

    const $btn = $('#save_pet_details_btn');
    const $loading = $btn.find('.loading');
    const $btnText = $btn.find('.btn-text');
    const originalText = $btnText.text();
    $loading.removeClass('hidden');
    $btnText.text('Saving...');
    $btn.prop('disabled', true);

    // Submit form to update flows
    $.ajax({
        url: '{{ route("boarding-process-log-update", $process->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            flows: workflowData
        },
        dataType: 'json',
        success: function(response) {
            $loading.addClass('hidden');
            $btnText.text(originalText);
            $btn.prop('disabled', false);

            if (response.success) {
                // Reload process items to update completion status
                loadProcessItems(currentTab);
                // Update workflow progress after successful save
                updateWorkflowProgress();
                $('#alert_message').text(response.message || 'Successfully saved workflow data.');
                alert_modal.showModal();
            } else {
                $('#alert_message').text(response.message || 'Error updating workflow.');
                alert_modal.showModal();
            }
        },
        error: function(xhr) {
            $loading.addClass('hidden');
            $btnText.text(originalText);
            $btn.prop('disabled', false);

            const errorMessage = xhr.responseJSON && xhr.responseJSON.message ?
                xhr.responseJSON.message :
                'Error updating workflow. Please try again.';
            $('#alert_message').text(errorMessage);
            alert_modal.showModal();
        }
    });
});

// Update workflow progress
function updateWorkflowProgress() {
    // Exclude the 4 Report tab steps from progress count (total = 16, or 15 when Rest is hidden)
    const progressExcludeIds = ['dne_list_am', 'dne_list_pm', 'report_lunch', 'report_rest', 'report_prn', 'treatment_concern',
        'end_of_day'
    ];
    const totalProcesses = Object.keys(tabProcesses).reduce((sum, tab) => {
        const count = tabProcesses[tab].filter(p => !progressExcludeIds.includes(p.id)).length;
        return sum + count;
    }, 0);
    const completedProcesses = Object.keys(workflowData).filter(id => !progressExcludeIds.includes(id)).length;
    const progress = totalProcesses > 0 ? Math.round((completedProcesses / totalProcesses) * 100) : 0;

    $('#workflow_progress').html(`
      <p>${completedProcesses} of ${totalProcesses} processes completed</p>
      <progress class="progress progress-primary mt-2" value="${progress}" max="100"></progress>
      <p class="text-xs mt-1">${progress}%</p>
    `);
}

// Load staff sign off for current process item
function loadStaffSignOff() {
    if (!currentProcessItem) return;

    const processData = workflowData[currentProcessItem];
    if (processData && processData.staff_sign_off) {
        $('#staff_sign_off').val(processData.staff_sign_off).trigger('change');
    } else {
        $('#staff_sign_off').val(null).trigger('change');
    }

    if (processData && processData.process_time) {
        $('#process_time').val(processData.process_time);
    } else {
        $('#process_time').val('');
    }
}

// Initialize Select2 for staff sign off
$('#staff_sign_off').select2({
    placeholder: 'Select an employee',
    allowClear: false,
    width: '100%'
});

fetchYesterdayNextDayPetIds();
loadProcessItems(currentTab);
updateProcessDetailTitle();
updateWorkflowProgress();
updatePetCountsDisplay(0, null);
</script>
@endsection