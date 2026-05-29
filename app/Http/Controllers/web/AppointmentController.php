<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Models\PetProfile;
use App\Models\TimeSlot;
use App\Models\Questionnaire;
use App\Models\Checkin;
use App\Models\Process;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Checkout;
use App\Models\GroupClass;
use App\Models\ServiceCategory;
use App\Models\PetInitialTemperament;
use App\Models\AppointmentCancellation;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\CustomerPackage;
use App\Models\Notification;
use App\Models\Kennel;
use App\Models\Room;
use App\Services\AppointmentBookingNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\Invoice as InvoiceMail;
use App\Mail\AdminCustomerMessage;

class AppointmentController extends Controller
{
    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        $customerPet = $request->get('customer');
        $serviceId = $request->get('service');
        $staffId = $request->get('staff');
        $status = $request->get('status');

        $datetimes = $request->get('datetimes');

        // get appointments
        if ($customerPet) {
            $appointments = Appointment::whereHas('customer', function ($query) use ($customerPet) {
                $query->where('email', 'like', "%{$customerPet}%")
                    ->orWhereHas('profile', function ($q) use ($customerPet) {
                        $q->where('first_name', 'like', "%{$customerPet}%")
                            ->orWhere('last_name', 'like', "%{$customerPet}%");
                    });
            })->orWhereHas('pet', function ($query) use ($customerPet) {
                $query->where('name', 'like', "%{$customerPet}%");
            });
        } else {
            $appointments = Appointment::query();
        }

        if ($serviceId)
            $appointments = $appointments->where('service_id', $serviceId);

        if ($staffId)
            $appointments = $appointments->where('staff_id', $staffId);

        if ($status)
            $appointments = $appointments->where('status', $status);

        if ($datetimes) {
            // Example: "09/05/25 12:00 PM - 09/18/25 08:00 PM"
            [$start, $end] = explode(' - ', $datetimes);

            // Parse to Carbon (assuming format: m/d/y h:i A)
            $startDateTime = Carbon::createFromFormat('m/d/y h:i A', trim($start))->format('Y-m-d H:i:s');
            $endDateTime = Carbon::createFromFormat('m/d/y h:i A', trim($end))->format('Y-m-d H:i:s');

            // Filter appointments where start_time is within the range
            $appointments = $appointments->where(function($query) use ($startDateTime, $endDateTime) {
                $query->whereRaw("CONCAT(date, ' ', start_time) >= ?", [$startDateTime])
                    ->whereRaw("CONCAT(date, ' ', end_time) <= ?", [$endDateTime]);
            });
        }
        $appointments = $appointments->orderBy('created_at', 'desc')->paginate($perPage);

        $kennelIds = $appointments->getCollection()
            ->pluck('kennel_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $roomByKennel = collect();
        if ($kennelIds->isNotEmpty()) {
            $activeKennelIds = $kennelIds->all();

            Room::select(['name', 'kennel_ids'])
                ->get()
                ->each(function ($room) use ($activeKennelIds, &$roomByKennel) {
                    collect($room->kennel_id_array)
                        ->intersect($activeKennelIds)
                        ->each(function ($kennelId) use ($room, &$roomByKennel) {
                            $roomByKennel->put((string) $kennelId, $room->name);
                        });
                });
        }

        $services = Service::where('status', 'active')->where('level', 'primary')->get();
        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->get();

        return view('appointments.index', compact('appointments', 'perPage', 'services', 'staffs', 'datetimes', 'customerPet', 'serviceId', 'staffId', 'status', 'roomByKennel'));
    }

    public function add(Request $request)
    {
        $serviceId = $request->get('service_id');
        $additionalServicesQuery = Service::where('status', 'active')
            ->whereHas('category', function($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%groom%']);
            });

        if (isset($serviceId)) {
            $additionalServicesQuery->where('id', '!=', $serviceId);
        }

        $additionalServices = $additionalServicesQuery->get();

        $services = Service::where('status', 'active')->where('level', 'primary')->get();

        // Secondary grooming services for selection (for dropdown etc)
        $secondaryServices = Service::where('status', 'active')
            ->where('level', 'secondary')
            ->whereHas('category', function($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%groom%']);
            })
            ->with('category')
            ->get();

        $kennels = Kennel::orderBy('name')->get();
        $rooms = $this->getAssignmentRooms();

        return view('appointments.create', compact(
            'services',
            'additionalServices',
            'serviceId',
            'secondaryServices',
            'kennels',
            'rooms'
        ));
    }

    public function getCustomers(Request $request)
    {
        $search = $request->get('q', '');

        $customers = User::whereHas('roles', function ($query) {
            $query->where('title', 'customer');
        })->where(function ($query) use ($search) {
            $query->where('email', 'like', "%{$search}%")
                ->orWhereHas('profile', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        })->with('profile')->limit(6)->get();

        return response()->json($customers);
    }

    public function getCustomerPets($customerId)
    {
        $pets = PetProfile::where('user_id', $customerId)->get();

        return response()->json($pets);
    }

    public function getCustomerPackages($customerId)
    {
        $customerPackages = CustomerPackage::where('customer_id', $customerId)
            ->where('remaining_days', '>', 0)
            ->with('package')
            ->get();

        $packages = $customerPackages->map(function ($cp) {
            return [
                'id' => $cp->package->id,
                'name' => $cp->package->name,
                'price' => $cp->package->price,
                'days' => $cp->package->days,
                'description' => $cp->package->description,
                'service_ids' => $cp->package->service_ids,
                'remaining_days' => $cp->remaining_days,
                'original_days' => $cp->original_days,
                'customer_package_id' => $cp->id,
            ];
        });

        return response()->json($packages);
    }

    public function getStaffs(Request $request)
    {
        $search = $request->get('q', '');

        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->where(function ($query) use ($search) {
            if ($search) {
                $query->where('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            }
        })->with('profile')->limit(6)->get();

        return response()->json($staffs);
    }

    public function getTimeSlots(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
            'pet_id' => 'required|exists:pet_profiles,id',
            'secondary_service_ids' => 'nullable|array',
            'secondary_service_ids.*' => 'exists:services,id',
            'pickup_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'is_boarding_additional_service' => 'nullable|boolean'
        ]);

        $serviceId = $request->service_id;
        $date = $request->date;
        $petId = $request->pet_id;
        $secondaryServiceIds = $request->secondary_service_ids ?? [];
        $pickupTime = $request->pickup_time;
        $isBoardingAdditionalService = $request->boolean('is_boarding_additional_service');

        $timeSlots = [];

        if ($serviceId && $date && $petId) {
            $service = Service::with('category')->find($serviceId);
            $pet = PetProfile::find($petId);
            $petSize = $pet ? $pet->size : 'medium';

            $query = TimeSlot::where('service_id', $serviceId)
                    ->whereDate('date', $date)
                    ->where('status', 'available')
                    ->whereRaw('booked_count < capacity');

            $timeSlots = $query->orderBy('start_time')->get();
        }

        if ($pickupTime) {
            $timeSlots = $this->filterTimeSlotsByPickupTime($timeSlots, $pickupTime);
        }

        return response()->json($timeSlots);
    }

    public function getAvailableKennels(Request $request)
    {
        $request->validate([
            'boarding_start_datetime' => 'required|date',
            'boarding_end_datetime' => 'required|date|after:boarding_start_datetime',
            'appointment_id' => 'nullable|exists:appointments,id',
            'selected_kennel_id' => 'nullable|exists:kennels,id',
        ]);

        $startDateTime = Carbon::parse($request->boarding_start_datetime);
        $endDateTime = Carbon::parse($request->boarding_end_datetime);
        $excludeAppointmentId = $request->filled('appointment_id') ? (int) $request->appointment_id : null;
        $selectedKennelId = $request->filled('selected_kennel_id') ? (int) $request->selected_kennel_id : null;

        $overlappingKennelIds = $this->getOverlappingBoardingAppointmentsQuery($startDateTime, $endDateTime, $excludeAppointmentId)
            ->whereNotNull('kennel_id')
            ->pluck('kennel_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $kennels = Kennel::where(function ($query) use ($selectedKennelId) {
                $query->where('status', 'In Service');

                if ($selectedKennelId) {
                    $query->orWhere('id', $selectedKennelId);
                }
            })
            ->whereNotIn('id', $overlappingKennelIds)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return response()->json($kennels);
    }

    private function getFamilyKennelMode(array $petIds): string
    {
        $pets = $this->collectPetsByIds($petIds);

        if ($pets->isEmpty()) {
            return 'shared';
        }

        if ($pets->count() <= 1) {
            return 'shared';
        }

        $hasNonSmall = $pets->contains(function ($pet) {
            return !$this->isSmallPetSize($pet['size'] ?? 'medium');
        });

        return $hasNonSmall ? 'individual' : 'shared';
    }

    private function normalizeFamilyKennelAssignmentsInput($rawAssignments, array $petIds): array
    {
        $normalizedPetIds = collect($petIds)
            ->map(fn ($petId) => (int) $petId)
            ->filter(fn ($petId) => $petId > 0)
            ->values();

        $normalizedAssignments = [];

        if (!is_array($rawAssignments)) {
            return [];
        }

        foreach ($rawAssignments as $petId => $kennelId) {
            $normalizedPetId = (int) $petId;
            $normalizedKennelId = is_array($kennelId)
                ? (int) ($kennelId['kennel_id'] ?? 0)
                : (int) $kennelId;

            if (
                $normalizedPetId <= 0
                || $normalizedKennelId <= 0
                || !$normalizedPetIds->contains($normalizedPetId)
            ) {
                continue;
            }

            $normalizedAssignments[$normalizedPetId] = $normalizedKennelId;
        }

        ksort($normalizedAssignments);

        return $normalizedAssignments;
    }

    private function normalizeFamilyPetAssignmentsInput($rawAssignments, array $petIds): array
    {
        $normalizedPetIds = collect($petIds)
            ->map(fn ($petId) => (int) $petId)
            ->filter(fn ($petId) => $petId > 0)
            ->values();

        if (!is_array($rawAssignments)) {
            return [];
        }

        $normalizedAssignments = [];

        foreach ($rawAssignments as $petId => $assignment) {
            $normalizedPetId = (int) $petId;

            if ($normalizedPetId <= 0 || !$normalizedPetIds->contains($normalizedPetId)) {
                continue;
            }

            $roomId = is_array($assignment)
                ? (int) ($assignment['room_id'] ?? 0)
                : 0;
            $kennelId = is_array($assignment)
                ? (int) ($assignment['kennel_id'] ?? 0)
                : 0;

            if ($roomId <= 0) {
                continue;
            }

            $normalizedAssignments[$normalizedPetId] = [
                'room_id' => $roomId,
                'kennel_id' => $kennelId > 0 ? $kennelId : null,
            ];
        }

        ksort($normalizedAssignments);

        return $normalizedAssignments;
    }

    private function buildFamilyKennelAssignmentsFromPerPetAssignments(array $familyPetAssignments): array
    {
        return collect($familyPetAssignments)
            ->mapWithKeys(function ($assignment, $petId) {
                $kennelId = (int) ($assignment['kennel_id'] ?? 0);

                if ($kennelId <= 0) {
                    return [];
                }

                return [(int) $petId => $kennelId];
            })
            ->all();
    }

    private function getPrimaryAssignmentFromPerPetAssignments(array $familyPetAssignments): array
    {
        $primary = collect($familyPetAssignments)
            ->sortKeys()
            ->first();

        if (!is_array($primary)) {
            return [
                'room_id' => null,
                'kennel_id' => null,
            ];
        }

        return [
            'room_id' => (int) ($primary['room_id'] ?? 0) ?: null,
            'kennel_id' => (int) ($primary['kennel_id'] ?? 0) ?: null,
        ];
    }

    private function buildFamilyKennelAssignmentsForMode(array $petIds, string $familyKennelMode, ?int $sharedKennelId, $rawAssignments = []): array
    {
        $normalizedPetIds = collect($petIds)
            ->map(fn ($petId) => (int) $petId)
            ->filter(fn ($petId) => $petId > 0)
            ->values();

        if ($normalizedPetIds->isEmpty()) {
            return [];
        }

        if ($familyKennelMode === 'shared') {
            if (!$sharedKennelId) {
                return [];
            }

            return $normalizedPetIds->mapWithKeys(function ($petId) use ($sharedKennelId) {
                return [(int) $petId => (int) $sharedKennelId];
            })->all();
        }

        return $this->normalizeFamilyKennelAssignmentsInput($rawAssignments, $normalizedPetIds->all());
    }

    private function buildFamilyKennelConflictPayload(Room $room, array $familyKennelAssignments, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null): array
    {
        $normalizedAssignments = collect($familyKennelAssignments)
            ->map(function ($kennelId, $petId) {
                return [
                    'pet_id' => (int) $petId,
                    'kennel_id' => (int) $kennelId,
                ];
            })
            ->filter(function ($assignment) {
                return $assignment['pet_id'] > 0 && $assignment['kennel_id'] > 0;
            })
            ->values();

        if ($normalizedAssignments->isEmpty()) {
            return ['conflict' => false];
        }

        $petIds = $normalizedAssignments->pluck('pet_id')->all();
        $petsById = $this->collectPetsByIds($petIds)->keyBy('id');

        $duplicateKennelGroups = $normalizedAssignments
            ->groupBy('kennel_id')
            ->filter(function ($group) {
                return $group->count() > 1;
            });

        if ($duplicateKennelGroups->isNotEmpty()) {
            $sharedKennelId = (int) $duplicateKennelGroups->keys()->first();
            $duplicateAssignments = $duplicateKennelGroups->first();
            $duplicatePetIds = $duplicateAssignments->pluck('pet_id')->map(fn ($petId) => (int) $petId)->values()->all();
            $duplicatePets = collect($duplicatePetIds)
                ->map(fn ($petId) => $petsById->get($petId))
                ->filter()
                ->values();
            $kennel = Kennel::find($sharedKennelId);
            $kennelName = $kennel ? $kennel->name : 'the selected kennel';
            $petNames = $duplicatePets->pluck('name')->filter()->values()->all();

            return [
                'conflict' => true,
                'conflict_type' => 'size_sharing',
                'warning_codes' => ['size_sharing'],
                'message' => 'Medium, large, and xlarge dogs must have their own kennel. The selected pets would share <strong>"' . $kennelName . '"</strong>.<br><br>Please change the kennel assignment or continue anyway.',
                'current_occupants' => [],
                'room_name' => $room->name,
                'kennel_name' => $kennelName,
                'selected_pet_sizes' => $duplicatePets->pluck('size')->values()->all(),
                'selected_pet_names' => $petNames,
                'duplicate_pet_ids' => $duplicatePetIds,
            ];
        }

        foreach ($normalizedAssignments->groupBy('kennel_id') as $kennelId => $petAssignments) {
            $petIdsForKennel = $petAssignments->pluck('pet_id')->map(fn ($petId) => (int) $petId)->values()->all();
            $conflict = $this->buildKennelConflictPayload($room, (int) $kennelId, $newStart, $newEnd, $excludeAppointmentId, $petIdsForKennel);

            if (!empty($conflict['conflict'])) {
                return $conflict;
            }
        }

        return [
            'conflict' => false,
            'room_name' => $room->name,
        ];
    }

    private function buildFamilyPetAssignmentConflictPayload(array $familyPetAssignments, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null): array
    {
        $normalizedAssignments = collect($familyPetAssignments)
            ->map(function ($assignment, $petId) {
                return [
                    'pet_id' => (int) $petId,
                    'room_id' => (int) ($assignment['room_id'] ?? 0),
                    'kennel_id' => (int) ($assignment['kennel_id'] ?? 0),
                ];
            })
            ->filter(function ($assignment) {
                return $assignment['pet_id'] > 0 && $assignment['room_id'] > 0;
            })
            ->values();

        if ($normalizedAssignments->isEmpty()) {
            return ['conflict' => false];
        }

        $roomIds = $normalizedAssignments->pluck('room_id')->unique()->values()->all();
        $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        $petIds = $normalizedAssignments->pluck('pet_id')->unique()->values()->all();
        $petsById = $this->collectPetsByIds($petIds)->keyBy('id');

        foreach ($normalizedAssignments as $assignment) {
            $petId = (int) $assignment['pet_id'];
            $roomId = (int) $assignment['room_id'];
            $kennelId = (int) $assignment['kennel_id'];
            $room = $roomsById->get($roomId);

            if (!$room) {
                return [
                    'conflict' => true,
                    'conflict_type' => 'room',
                    'message' => 'One or more selected rooms are invalid.',
                ];
            }

            $petTypeValidation = $this->validateRoomPetTypes($room, [$petId]);
            if ($petTypeValidation['valid'] === false) {
                return [
                    'conflict' => true,
                    'conflict_type' => 'pet_type',
                    'message' => $petTypeValidation['message'] ?? 'The selected room does not support this pet.',
                    'room_name' => $room->name,
                ];
            }

            $roomType = $this->getRoomAssignmentType($room);

            if ($roomType === 'standard') {
                if ($kennelId <= 0) {
                    return [
                        'conflict' => true,
                        'conflict_type' => 'kennel',
                        'message' => 'Please choose a kennel for each pet assigned to a standard room.',
                        'room_name' => $room->name,
                    ];
                }

                if (!in_array($kennelId, $room->kennel_id_array, true)) {
                    return [
                        'conflict' => true,
                        'conflict_type' => 'kennel',
                        'message' => 'One or more selected kennels do not belong to the assigned room.',
                        'room_name' => $room->name,
                    ];
                }

                $pet = $petsById->get($petId);
                $petType = strtolower((string) ($pet['type'] ?? 'dog'));
                if ($petType === 'cat') {
                    return [
                        'conflict' => true,
                        'conflict_type' => 'cat_kennel',
                        'message' => 'Kennels are for dogs only. Cats cannot be assigned to kennels. Please use a cat room instead.',
                        'room_name' => $room->name,
                    ];
                }
            }
        }

        $duplicateKennelGroups = $normalizedAssignments
            ->filter(fn ($assignment) => (int) $assignment['kennel_id'] > 0)
            ->groupBy('kennel_id')
            ->filter(fn ($group) => $group->count() > 1);

        if ($duplicateKennelGroups->isNotEmpty()) {
            $sharedKennelId = (int) $duplicateKennelGroups->keys()->first();
            $duplicateAssignments = $duplicateKennelGroups->first();
            $duplicatePetIds = $duplicateAssignments->pluck('pet_id')->map(fn ($id) => (int) $id)->values()->all();
            $duplicatePets = collect($duplicatePetIds)
                ->map(fn ($petId) => $petsById->get($petId))
                ->filter()
                ->values();
            $kennel = Kennel::find($sharedKennelId);

            return [
                'conflict' => true,
                'conflict_type' => 'size_sharing',
                'warning_codes' => ['size_sharing'],
                'message' => 'Medium, large, and xlarge dogs must have their own kennel. The selected pets would share <strong>"' . ($kennel?->name ?? 'the selected kennel') . '"</strong>.<br><br>Please change the kennel assignment or continue anyway.',
                'selected_pet_sizes' => $duplicatePets->pluck('size')->values()->all(),
                'selected_pet_names' => $duplicatePets->pluck('name')->values()->all(),
                'duplicate_pet_ids' => $duplicatePetIds,
            ];
        }

        foreach ($normalizedAssignments as $assignment) {
            $room = $roomsById->get((int) $assignment['room_id']);
            if (!$room) {
                continue;
            }

            $roomType = $this->getRoomAssignmentType($room);

            if ($roomType === 'space') {
                $roomConflict = $this->buildSpaceRoomConflictPayload($room, $newStart, $newEnd, $excludeAppointmentId);
                if (!empty($roomConflict['conflict'])) {
                    return $roomConflict;
                }
                continue;
            }

            $kennelId = (int) ($assignment['kennel_id'] ?? 0);
            if ($kennelId <= 0) {
                continue;
            }

            $kennelConflict = $this->buildKennelConflictPayload(
                $room,
                $kennelId,
                $newStart,
                $newEnd,
                $excludeAppointmentId,
                [(int) $assignment['pet_id']]
            );

            if (!empty($kennelConflict['conflict'])) {
                return $kennelConflict;
            }
        }

        return ['conflict' => false];
    }

    private function buildBoardingAssignmentConflictPayload(Room $room, string $familyKennelMode, ?int $sharedKennelId, array $familyKennelAssignments, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null, array $petIds = []): array
    {
        $roomType = $this->getRoomAssignmentType($room);

        if ($roomType === 'space') {
            return $this->buildSpaceRoomConflictPayload($room, $newStart, $newEnd, $excludeAppointmentId);
        }

        if ($familyKennelMode === 'individual') {
            return $this->buildFamilyKennelConflictPayload($room, $familyKennelAssignments, $newStart, $newEnd, $excludeAppointmentId);
        }

        return $this->buildKennelConflictPayload($room, $sharedKennelId, $newStart, $newEnd, $excludeAppointmentId, $petIds);
    }

    private function getAssignmentKennelIdForMode(string $familyKennelMode, ?int $sharedKennelId, array $familyKennelAssignments): ?int
    {
        if ($familyKennelMode === 'shared') {
            return $sharedKennelId;
        }

        return (int) (collect($familyKennelAssignments)->first() ?? 0) ?: null;
    }

    public function validateAssignment(Request $request)
    {
        $request->validate([
            'room_id' => 'nullable|exists:rooms,id',
            'kennel_id' => 'nullable|exists:kennels,id',
            'pet_ids' => 'nullable|array',
            'pet_ids.*' => 'exists:pet_profiles,id',
            'family_kennel_assignments' => 'nullable|array',
            'family_kennel_assignments.*' => 'nullable|exists:kennels,id',
            'family_pet_assignments' => 'nullable|array',
            'family_pet_assignments.*.room_id' => 'nullable|exists:rooms,id',
            'family_pet_assignments.*.kennel_id' => 'nullable|exists:kennels,id',
            'boarding_start_datetime' => 'required|date',
            'boarding_end_datetime' => 'required|date|after:boarding_start_datetime',
            'appointment_id' => 'nullable|exists:appointments,id',
        ]);

        $startDateTime = Carbon::parse($request->boarding_start_datetime);
        $endDateTime = Carbon::parse($request->boarding_end_datetime);
        $excludeAppointmentId = $request->filled('appointment_id') ? (int) $request->appointment_id : null;
        $kennelId = $request->filled('kennel_id') ? (int) $request->kennel_id : null;
        $petIds = collect($request->input('pet_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $familyKennelMode = $this->getFamilyKennelMode($petIds);

        if ($familyKennelMode === 'individual') {
            $familyPetAssignments = $this->normalizeFamilyPetAssignmentsInput(
                $request->input('family_pet_assignments', []),
                $petIds
            );

            if (count($familyPetAssignments) !== count($petIds)) {
                return response()->json([
                    'conflict' => true,
                    'conflict_type' => 'room',
                    'valid' => false,
                    'room_type' => 'mixed',
                    'message' => 'Please choose a room for each selected pet.',
                    'family_kennel_mode' => $familyKennelMode,
                    'family_pet_assignments' => $familyPetAssignments,
                ]);
            }

            $familyKennelAssignments = $this->buildFamilyKennelAssignmentsFromPerPetAssignments($familyPetAssignments);
            $conflict = $this->buildFamilyPetAssignmentConflictPayload(
                $familyPetAssignments,
                $startDateTime,
                $endDateTime,
                $excludeAppointmentId
            );

            return response()->json(array_merge([
                'conflict' => false,
                'room_type' => 'mixed',
                'room_id' => null,
                'room_name' => null,
                'family_kennel_mode' => $familyKennelMode,
                'family_kennel_assignments' => $familyKennelAssignments,
                'family_pet_assignments' => $familyPetAssignments,
            ], $conflict));
        }

        if (!$request->filled('room_id')) {
            return response()->json([
                'conflict' => true,
                'conflict_type' => 'room',
                'valid' => false,
                'message' => 'Please select a room for the boarding appointment.',
            ]);
        }

        $room = Room::findOrFail($request->room_id);
        $roomType = $this->getRoomAssignmentType($room);

        $familyKennelAssignments = $this->buildFamilyKennelAssignmentsForMode(
            $petIds,
            $familyKennelMode,
            $kennelId,
            $request->input('family_kennel_assignments', [])
        );

        // Kennels are dogs-only: block cats from being assigned to a kennel
        if ($roomType === 'standard' && !empty($petIds)) {
            $cats = PetProfile::whereIn('id', $petIds)
                ->whereRaw('LOWER(type) = ?', ['cat'])
                ->get();

            if ($cats->isNotEmpty()) {
                $assignedKennelId = $this->getAssignmentKennelIdForMode($familyKennelMode, $kennelId, $familyKennelAssignments);
                $kennel = $assignedKennelId ? Kennel::find($assignedKennelId) : null;
                $kennelName = $kennel ? $kennel->name : 'the selected kennel';

                $catNames = $cats->pluck('name')->map(fn ($n) => "<strong>{$n}</strong>")->implode(', ');
                $plural = $cats->count() > 1;

                $catRooms = Room::get()
                    ->filter(fn ($r) => in_array('cat', $r->pet_type_label_array))
                    ->pluck('name')
                    ->values();

                $catRoomSuggestion = $catRooms->isNotEmpty()
                    ? 'Please use one of these cat rooms instead: <strong>' . $catRooms->implode('</strong>, <strong>') . '</strong>.'
                    : 'Please choose a cat room instead.';

                $verb = $plural ? 'are cats' : 'is a cat';

                return response()->json([
                    'conflict' => true,
                    'conflict_type' => 'cat_kennel',
                    'valid' => false,
                    'room_type' => $roomType,
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'message' => "Kennels are for dogs only. {$catNames} {$verb} and cannot stay in kennel <strong>\"{$kennelName}\"</strong>.<br><br>{$catRoomSuggestion}",
                ]);
            }
        }

        if ($roomType !== 'standard') {
            $petTypeValidation = $this->validateRoomPetTypes($room, $petIds);
            if ($petTypeValidation['valid'] === false) {
                return response()->json(array_merge([
                    'conflict' => true,
                    'conflict_type' => 'pet_type',
                    'room_type' => $roomType,
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                ], $petTypeValidation));
            }
        }

        $conflict = $roomType === 'space'
            ? $this->buildSpaceRoomConflictPayload($room, $startDateTime, $endDateTime, $excludeAppointmentId)
            : $this->buildBoardingAssignmentConflictPayload(
                $room,
                $familyKennelMode,
                $kennelId,
                $familyKennelAssignments,
                $startDateTime,
                $endDateTime,
                $excludeAppointmentId,
                $petIds
            );

        return response()->json(array_merge([
            'conflict' => false,
            'room_type' => $roomType,
            'room_id' => $room->id,
            'room_name' => $room->name,
            'family_kennel_mode' => $familyKennelMode,
            'family_kennel_assignments' => $familyKennelAssignments,
        ], $conflict));
    }

    private function getOverlappingBoardingAppointmentsQuery(Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null)
    {
        $query = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereHas('service.category', function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->where(function ($q) use ($newStart, $newEnd) {
                $q->whereRaw("CONCAT(end_date, ' ', end_time) > ?", [$newStart->format('Y-m-d H:i:s')])
                    ->whereRaw("CONCAT(date, ' ', start_time) < ?", [$newEnd->format('Y-m-d H:i:s')]);
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query;
    }

    private function validateRoomPetTypes(Room $room, array $petIds): array
    {
        if (empty($petIds)) {
            return ['valid' => true];
        }

        $pets = PetProfile::whereIn('id', $petIds)->get();
        if ($pets->isEmpty()) {
            return ['valid' => true];
        }

        $petTypes = $pets->map(fn ($pet) => strtolower((string) ($pet->type ?? '')))
            ->filter(fn ($type) => $type !== '')
            ->unique()
            ->values()
            ->all();

        $roomPetTypeLabels = $room->pet_type_label_array;
        $roomName = $room->name;
        $roomType = $this->getRoomAssignmentType($room);

        if (empty($roomPetTypeLabels)) {
            // Legacy standard kennel rooms may not have explicit pet-type labels configured.
            // Treat those as valid here and rely on kennel/cat-specific validation elsewhere.
            if ($roomType === 'standard') {
                return ['valid' => true];
            }

            return [
                'valid' => false,
                'message' => "\"{$roomName}\" has not been set up — no animal type has been configured for this room. Please contact an administrator.",
            ];
        }

        $unsupportedTypes = array_diff($petTypes, $roomPetTypeLabels);

        if (!empty($unsupportedTypes)) {
            $allowedStr = implode(' and ', array_map('ucfirst', $roomPetTypeLabels));
            $unsupportedStr = implode(' and ', array_map('ucfirst', $unsupportedTypes));

            // Find rooms that support the unsupported types
            $alternativeRooms = Room::where('id', '!=', $room->id)
                ->get()
                ->filter(function ($r) use ($unsupportedTypes) {
                    $labels = $r->pet_type_label_array;
                    return !empty(array_intersect($unsupportedTypes, $labels));
                })
                ->pluck('name')
                ->values();

            $suggestionLine = $alternativeRooms->isNotEmpty()
                ? 'Please assign the ' . $unsupportedStr . ' to: <strong>' . $alternativeRooms->implode('</strong>, <strong>') . '</strong>.'
                : 'Please choose a room that accepts ' . $unsupportedStr . 's.';

            return [
                'valid' => false,
                'message' => "<strong>\"{$roomName}\"</strong> is for {$allowedStr}s only — it does not accept {$unsupportedStr}s.<br><br>{$suggestionLine}",
            ];
        }

        return ['valid' => true];
    }

    private function validateCatToKennelAssignment(?int $kennelId, array $petIds): array
    {
        if (!$kennelId || empty($petIds)) {
            return ['valid' => true];
        }

        // Check if any selected pets are cats
        $cats = PetProfile::whereIn('id', $petIds)
            ->whereRaw('LOWER(type) = ?', ['cat'])
            ->get();

        if ($cats->isEmpty()) {
            return ['valid' => true];
        }

        // Cats cannot be assigned to kennels
        return [
            'valid' => false,
            'message' => 'Kennels are for dogs only. Cats cannot be assigned to kennels. Please use a cat room instead.',
        ];
    }

    private function getAssignmentRooms()
    {
        return Room::orderBy('name')->get();
    }

    private function getRoomAssignmentType(?Room $room): ?string
    {
        if (!$room) {
            return null;
        }

        $roomTypes = $room->room_type_array;

        return in_array('space', $roomTypes, true) ? 'space' : 'standard';
    }

    private function getRoomKennels(Room $room)
    {
        $kennelIds = $room->kennel_id_array;

        if (empty($kennelIds)) {
            return collect();
        }

        return Kennel::whereIn('id', $kennelIds)->orderBy('name')->get();
    }

    private function buildAssignmentConflictPayload(Room $room, string $familyKennelMode, ?int $sharedKennelId, array $familyKennelAssignments, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null, array $selectedPetIds = []): array
    {
        return $this->buildBoardingAssignmentConflictPayload(
            $room,
            $familyKennelMode,
            $sharedKennelId,
            $familyKennelAssignments,
            $newStart,
            $newEnd,
            $excludeAppointmentId,
            $selectedPetIds
        );
    }

    private function buildKennelConflictPayload(Room $room, ?int $kennelId, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null, array $selectedPetIds = []): array
    {
        if (!$kennelId) {
            return ['conflict' => false];
        }

        $kennel = Kennel::find($kennelId);
        if (!$kennel) {
            return ['conflict' => false];
        }

        $overlappingAppointments = $this->getOverlappingBoardingAppointmentsQuery($newStart, $newEnd, $excludeAppointmentId)
            ->where('kennel_id', $kennelId)
            ->with(['pet', 'customer.profile'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $existingPets = $this->collectAssignmentPetsFromAppointments($overlappingAppointments);
        $incomingPets = $this->collectPetsByIds($selectedPetIds);

        $occupiedCount = $existingPets->count();
        $incomingCount = $incomingPets->count();
        $projectedCount = $occupiedCount + $incomingCount;
        $capacity = (int) ($kennel->capacity ?? 0);
        $capacityExceeded = $capacity > 0
            && ($projectedCount > $capacity || ($incomingCount === 0 && $occupiedCount >= $capacity));

        $combinedPets = $existingPets->concat($incomingPets);
        $combinedHasNonSmall = $combinedPets->contains(function ($pet) {
            return !$this->isSmallPetSize($pet['size'] ?? 'medium');
        });
        $sizeRuleWarning = $incomingCount > 0 && $combinedHasNonSmall && $projectedCount > 1;

        if ($capacityExceeded || $sizeRuleWarning) {
            $warningLines = [];

            if ($capacityExceeded) {
                $warningLines[] = 'Capacity warning: this kennel has capacity <strong>' . $capacity . '</strong>, but this assignment would place <strong>' . $projectedCount . '</strong> pet(s) in it.';
            }

            if ($sizeRuleWarning) {
                $warningLines[] = 'Size rule warning: medium, large, and xlarge dogs should occupy a kennel alone and should not share with other pets.';
            }

            $warningLines[] = 'You can choose another kennel or continue anyway.';

            return [
                'conflict' => true,
                'conflict_type' => 'kennel',
                'message' => implode('<br><br>', $warningLines),
                'current_occupants' => $this->formatAssignmentOccupants($overlappingAppointments),
                'occupied_count' => $occupiedCount,
                'incoming_count' => $incomingCount,
                'projected_count' => $projectedCount,
                'capacity' => $capacity,
                'room_name' => $room->name,
                'kennel_name' => $kennel->name,
                'selected_pet_sizes' => $incomingPets->pluck('size')->values()->all(),
                'occupant_pet_sizes' => $existingPets->pluck('size')->values()->all(),
                'warning_codes' => array_values(array_filter([
                    $capacityExceeded ? 'capacity_exceeded' : null,
                    $sizeRuleWarning ? 'size_sharing' : null,
                ])),
            ];
        }

        return [
            'conflict' => false,
            'occupied_count' => $occupiedCount,
            'incoming_count' => $incomingCount,
            'projected_count' => $projectedCount,
            'capacity' => $capacity,
            'room_name' => $room->name,
            'kennel_name' => $kennel->name,
        ];
    }

    private function collectAssignmentPetsFromAppointments($appointments)
    {
        $petIds = collect($appointments)
            ->flatMap(function ($appointment) {
                return collect($appointment->family_pet_ids ?: [$appointment->pet_id])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0);
            })
            ->unique()
            ->values();

        return $this->collectPetsByIds($petIds->all());
    }

    private function collectPetsByIds(array $petIds)
    {
        $normalizedPetIds = collect($petIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedPetIds->isEmpty()) {
            return collect();
        }

        return PetProfile::whereIn('id', $normalizedPetIds->all())
            ->get(['id', 'name', 'size', 'type'])
            ->map(function ($pet) {
                return [
                    'id' => (int) $pet->id,
                    'name' => (string) ($pet->name ?? ''),
                    'size' => $this->normalizePetSize($pet->size),
                    'type' => strtolower((string) ($pet->type ?? 'dog')),
                ];
            })
            ->values();
    }

    private function normalizePetSize($size): string
    {
        $normalized = strtolower(trim((string) $size));

        return in_array($normalized, ['small', 'medium', 'large', 'xlarge'], true)
            ? $normalized
            : 'medium';
    }

    private function isSmallPetSize($size): bool
    {
        return $this->normalizePetSize($size) === 'small';
    }

    private function buildSpaceRoomConflictPayload(Room $room, Carbon $newStart, Carbon $newEnd, ?int $excludeAppointmentId = null): array
    {
        $restrictCount = $room->restrict_count;
        if ($restrictCount === null) {
            return ['conflict' => false, 'room_name' => $room->name];
        }

        $overlappingAppointments = $this->getOverlappingBoardingAppointmentsQuery($newStart, $newEnd, $excludeAppointmentId)
            ->where('cat_room_id', $room->id)
            ->with(['pet', 'customer.profile'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $occupiedCount = $overlappingAppointments->count();

        if ($occupiedCount >= (int) $restrictCount) {
            return [
                'conflict' => true,
                'conflict_type' => 'room',
                'message' => 'Room already has an active assignment.',
                'current_occupants' => $this->formatAssignmentOccupants($overlappingAppointments),
                'occupied_count' => $occupiedCount,
                'capacity' => (int) $restrictCount,
                'room_name' => $room->name,
            ];
        }

        return [
            'conflict' => false,
            'occupied_count' => $occupiedCount,
            'capacity' => (int) $restrictCount,
            'room_name' => $room->name,
        ];
    }

    private function formatAssignmentOccupants($appointments): array
    {
        return collect($appointments)->map(function ($appointment) {
            $pets = collect($appointment->familyPets ?? [$appointment->pet])->filter();
            $petNames = $pets->pluck('name')->filter()->values()->all();
            $firstPet = $pets->first();

            return [
                'pet_names' => $petNames,
                'pet_type' => strtolower((string) optional($firstPet)->type),
                'start_date' => $appointment->date ? Carbon::parse($appointment->date)->toDateString() : null,
                'end_date' => $appointment->end_date ? Carbon::parse($appointment->end_date)->toDateString() : ($appointment->date ? Carbon::parse($appointment->date)->toDateString() : null),
            ];
        })->values()->all();
    }

    private function serviceRequiresScheduledSlot($service): bool
    {
        if (!$service) {
            return false;
        }

        $serviceName = strtolower($service->name ?? '');
        $categoryName = strtolower(optional($service->category)->name ?? '');

        return str_contains($categoryName, 'groom')
            || str_contains($categoryName, 'training')
            || str_contains($serviceName, 'bath');
    }

    private function filterTimeSlotsByPickupTime($timeSlots, $pickupTime)
    {
        if (!$pickupTime) {
            return collect($timeSlots)->values();
        }

        $normalizedPickupTime = strlen($pickupTime) === 5 ? $pickupTime . ':00' : $pickupTime;
        $pickupTimeCarbon = Carbon::createFromFormat('H:i:s', $normalizedPickupTime);

        return collect($timeSlots)->filter(function ($slot) use ($pickupTimeCarbon) {
            $slotStartTime = Carbon::createFromFormat('H:i:s', $slot->start_time);
            $slotEndTime = Carbon::createFromFormat('H:i:s', $slot->end_time);

            $endsAfterPickup = $slotEndTime->gt($pickupTimeCarbon);
            $pickupFallsWithinSlot = $pickupTimeCarbon->gt($slotStartTime) && $pickupTimeCarbon->lt($slotEndTime);

            return !$endsAfterPickup && !$pickupFallsWithinSlot;
        })->values();
    }

    private function calculateFamilyBoardingEstimatedPrice(Service $service, Appointment $appointment, array $petIds, array $additionalServicesByPet, int $customerId): float
    {
        if (!isBoardingService($service) || empty($petIds)) {
            return 0;
        }

        $allAdditionalServiceIds = collect($additionalServicesByPet)
            ->flatten()
            ->map(fn ($serviceId) => (int) $serviceId)
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->unique()
            ->values();

        $additionalServiceMap = $allAdditionalServiceIds->isNotEmpty()
            ? Service::whereIn('id', $allAdditionalServiceIds->all())->get()->keyBy('id')
            : collect();

        $estimatedTotal = 0;

        foreach ($petIds as $petId) {
            $pet = PetProfile::find($petId);
            $petSize = $pet ? $pet->size : 'medium';

            $priceAppointment = clone $appointment;
            $priceAppointment->pet_id = $petId;

            $petBoardingTotal = getBoardingServicePrice($service, $priceAppointment);
            $petTotal = $petBoardingTotal === null ? 0 : $petBoardingTotal;

            $petAdditionalServiceIds = collect($additionalServicesByPet[$petId] ?? [])
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->unique()
                ->values();

            foreach ($petAdditionalServiceIds as $additionalServiceId) {
                $additionalService = $additionalServiceMap->get($additionalServiceId);
                if (!$additionalService) {
                    continue;
                }

                if (isChauffeurService($additionalService)) {
                    $petTotal += calculateChauffeurServicePrice($additionalService, $customerId);
                } else {
                    $petTotal += getServicePrice($additionalService, $petSize);
                }
            }

            $estimatedTotal += $petTotal;
        }

        return round($estimatedTotal, 2);
    }

    private function normalizeAdditionalServicesByPetInput(array $petIds, $rawAdditionalServicesByPet, $legacyAdditionalServices = []): array
    {
        $normalizedPetIds = collect($petIds)
            ->map(fn ($petId) => (int) $petId)
            ->filter(fn ($petId) => $petId > 0)
            ->values();

        $normalizedByPet = [];

        if (is_array($rawAdditionalServicesByPet)) {
            foreach ($rawAdditionalServicesByPet as $petId => $serviceIds) {
                $normalizedPetId = (int) $petId;

                if ($normalizedPetId <= 0 || !$normalizedPetIds->contains($normalizedPetId)) {
                    continue;
                }

                if (is_string($serviceIds)) {
                    $serviceIds = explode(',', $serviceIds);
                }

                $normalizedByPet[$normalizedPetId] = collect(is_array($serviceIds) ? $serviceIds : [])
                    ->map(fn ($serviceId) => (int) $serviceId)
                    ->filter(fn ($serviceId) => $serviceId > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if (empty($normalizedByPet)) {
            $legacyIds = collect(is_array($legacyAdditionalServices) ? $legacyAdditionalServices : [])
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($legacyIds)) {
                if ($normalizedPetIds->count() > 1) {
                    foreach ($normalizedPetIds as $petId) {
                        $normalizedByPet[(int) $petId] = $legacyIds;
                    }
                } elseif ($normalizedPetIds->isNotEmpty()) {
                    $normalizedByPet[(int) $normalizedPetIds->first()] = $legacyIds;
                }
            }
        }

        if ($normalizedPetIds->count() > 1) {
            foreach ($normalizedPetIds as $petId) {
                if (!array_key_exists((int) $petId, $normalizedByPet)) {
                    $normalizedByPet[(int) $petId] = [];
                }
            }
        }

        ksort($normalizedByPet);

        return $normalizedByPet;
    }

    private function normalizeAdditionalServiceTimeSlotsInput($rawAdditionalServiceTimeSlots, array $selectedAdditionalServiceIds, ?int $legacyTimeSlotId = null): array
    {
        $normalizedSelectedServiceIds = collect($selectedAdditionalServiceIds)
            ->map(fn ($serviceId) => (int) $serviceId)
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->unique()
            ->values();

        $normalizedMap = [];

        if (is_array($rawAdditionalServiceTimeSlots)) {
            foreach ($rawAdditionalServiceTimeSlots as $serviceId => $timeSlotId) {
                $normalizedServiceId = (int) $serviceId;
                $normalizedTimeSlotId = (int) $timeSlotId;

                if (
                    $normalizedServiceId <= 0
                    || $normalizedTimeSlotId <= 0
                    || !$normalizedSelectedServiceIds->contains($normalizedServiceId)
                ) {
                    continue;
                }

                $normalizedMap[$normalizedServiceId] = $normalizedTimeSlotId;
            }
        }

        if (empty($normalizedMap) && $legacyTimeSlotId && $normalizedSelectedServiceIds->count() === 1) {
            $normalizedMap[(int) $normalizedSelectedServiceIds->first()] = (int) $legacyTimeSlotId;
        }

        ksort($normalizedMap);

        return $normalizedMap;
    }

    private function normalizeAdditionalServiceTimeSlotsByPetInput($rawAdditionalServiceTimeSlotsByPet, array $additionalServicesByPet, ?int $legacyTimeSlotId = null, $rawAdditionalServiceTimeSlots = []): array
    {
        $normalizedAdditionalServicesByPet = [];

        foreach ($additionalServicesByPet as $petId => $serviceIds) {
            $normalizedPetId = (int) $petId;
            if ($normalizedPetId <= 0 || !is_array($serviceIds)) {
                continue;
            }

            $normalizedServiceIds = collect($serviceIds)
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->unique()
                ->values()
                ->all();

            $normalizedAdditionalServicesByPet[$normalizedPetId] = $normalizedServiceIds;
        }

        $assignmentsByKey = [];

        if (is_array($rawAdditionalServiceTimeSlotsByPet)) {
            foreach ($rawAdditionalServiceTimeSlotsByPet as $petId => $serviceSlotMap) {
                $normalizedPetId = (int) $petId;
                if ($normalizedPetId <= 0 || !isset($normalizedAdditionalServicesByPet[$normalizedPetId]) || !is_array($serviceSlotMap)) {
                    continue;
                }

                foreach ($serviceSlotMap as $serviceId => $timeSlotId) {
                    $normalizedServiceId = (int) $serviceId;
                    $normalizedTimeSlotId = (int) $timeSlotId;

                    if (
                        $normalizedServiceId <= 0
                        || $normalizedTimeSlotId <= 0
                        || !in_array($normalizedServiceId, $normalizedAdditionalServicesByPet[$normalizedPetId], true)
                    ) {
                        continue;
                    }

                    $assignmentKey = $normalizedPetId . ':' . $normalizedServiceId;
                    $assignmentsByKey[$assignmentKey] = [
                        'pet_id' => $normalizedPetId,
                        'service_id' => $normalizedServiceId,
                        'time_slot_id' => $normalizedTimeSlotId,
                    ];
                }
            }
        }

        if (empty($assignmentsByKey) && is_array($rawAdditionalServiceTimeSlots)) {
            foreach ($rawAdditionalServiceTimeSlots as $serviceId => $timeSlotId) {
                $normalizedServiceId = (int) $serviceId;
                $normalizedTimeSlotId = (int) $timeSlotId;

                if ($normalizedServiceId <= 0 || $normalizedTimeSlotId <= 0) {
                    continue;
                }

                foreach ($normalizedAdditionalServicesByPet as $normalizedPetId => $serviceIds) {
                    if (!in_array($normalizedServiceId, $serviceIds, true)) {
                        continue;
                    }

                    $assignmentKey = $normalizedPetId . ':' . $normalizedServiceId;
                    $assignmentsByKey[$assignmentKey] = [
                        'pet_id' => $normalizedPetId,
                        'service_id' => $normalizedServiceId,
                        'time_slot_id' => $normalizedTimeSlotId,
                    ];
                }
            }
        }

        if (empty($assignmentsByKey) && $legacyTimeSlotId) {
            $singlePetId = array_key_first($normalizedAdditionalServicesByPet);
            $singlePetServiceIds = $singlePetId ? ($normalizedAdditionalServicesByPet[$singlePetId] ?? []) : [];

            if ($singlePetId && count($normalizedAdditionalServicesByPet) === 1 && count($singlePetServiceIds) === 1) {
                $singleServiceId = (int) $singlePetServiceIds[0];
                $assignmentKey = $singlePetId . ':' . $singleServiceId;
                $assignmentsByKey[$assignmentKey] = [
                    'pet_id' => (int) $singlePetId,
                    'service_id' => $singleServiceId,
                    'time_slot_id' => (int) $legacyTimeSlotId,
                ];
            }
        }

        $assignments = array_values($assignmentsByKey);
        usort($assignments, function ($left, $right) {
            return [$left['pet_id'], $left['service_id']] <=> [$right['pet_id'], $right['service_id']];
        });

        return $assignments;
    }

    private function getAdditionalServicePetCounts(array $additionalServicesByPet): array
    {
        $counts = [];

        foreach ($additionalServicesByPet as $petId => $serviceIds) {
            if (!is_array($serviceIds)) {
                continue;
            }

            $normalizedServiceIds = collect($serviceIds)
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->unique()
                ->values();

            foreach ($normalizedServiceIds as $serviceId) {
                $counts[$serviceId] = ($counts[$serviceId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function findSchedulingSolutionsByNearestSlot($serviceTimeslots, $serviceDurations, $date)
    {
        $solutions = collect([]);

        if (empty($serviceTimeslots)) {
            return $solutions;
        }

        $serviceIds = array_keys($serviceTimeslots);
        $firstServiceId = $serviceIds[0];
        $remainingServiceIds = array_slice($serviceIds, 1);

        $firstServiceSlots = $serviceTimeslots[$firstServiceId];

        foreach ($firstServiceSlots as $firstSlot) {
            if ($firstSlot->booked_count >= $firstSlot->capacity) {
                continue;
            }

            $solution = $this->tryScheduleFromFirstSlot(
                $firstSlot,
                $remainingServiceIds,
                $serviceTimeslots,
                $serviceDurations,
                $date
            );

            if ($solution) {
                $solutions->push($solution);
            }
        }

        return $solutions->sortBy('start_time')->values();
    }

    private function tryScheduleFromFirstSlot($firstSlot, $remainingServiceIds, $serviceTimeslots, $serviceDurations, $date)
    {
        $scheduledServices = [];
        $allUsedSlots = [];
        $slotUsageCount = [];

        $allSlotIds = [];
        foreach ($serviceTimeslots as $slots) {
            foreach ($slots as $slot) {
                $allSlotIds[] = $slot->id;
            }
        }
        $allSlotIds = array_unique($allSlotIds);

        $slotCapacities = [];
        if (!empty($allSlotIds)) {
            $slots = TimeSlot::whereIn('id', $allSlotIds)->get(['id', 'capacity', 'booked_count']);
            foreach ($slots as $slot) {
                $slotCapacities[$slot->id] = [
                    'capacity' => $slot->capacity,
                    'booked_count' => $slot->booked_count,
                ];
            }
        }

        $firstServiceId = $firstSlot->service_id;
        $firstServiceDuration = $serviceDurations[$firstServiceId]['duration_minutes'];
        $firstServiceSlots = $serviceTimeslots[$firstServiceId];

        $firstServiceSlotResult = $this->findSlotsForService(
            $firstSlot,
            $firstServiceSlots,
            $firstServiceDuration,
            $date,
            $slotCapacities,
            $slotUsageCount
        );

        if (!$firstServiceSlotResult) {
            return null;
        }

        $firstStartTime = $firstServiceSlotResult['start_time'];
        $firstEndTime = $firstServiceSlotResult['end_time'];
        $firstUsedSlotIds = $firstServiceSlotResult['used_slot_ids'];

        foreach ($firstUsedSlotIds as $slotId) {
            $slotUsageCount[$slotId] = ($slotUsageCount[$slotId] ?? 0) + 1;
            $allUsedSlots[] = $slotId;
        }

        $scheduledServices[] = [
            'service_id' => $firstServiceId,
            'service_name' => $serviceDurations[$firstServiceId]['service']->name,
            'start_time' => $firstStartTime->toTimeString(),
            'end_time' => $firstEndTime->toTimeString(),
            'duration_minutes' => $firstServiceDuration,
            'used_slot_ids' => $firstUsedSlotIds,
        ];

        $earliestStart = $firstStartTime;
        $latestEnd = $firstEndTime;
        $lastServiceEndTime = $firstEndTime;

        foreach ($remainingServiceIds as $serviceId) {
            if (!isset($serviceTimeslots[$serviceId]) || $serviceTimeslots[$serviceId]->isEmpty()) {
                return null;
            }

            $requiredMinutes = $serviceDurations[$serviceId]['duration_minutes'];
            $serviceSlots = $serviceTimeslots[$serviceId];

            $nearestSlot = $this->findNearestSlot($serviceSlots, $lastServiceEndTime, $date, $slotCapacities, $slotUsageCount);

            if (!$nearestSlot) {
                return null;
            }

            $serviceSlotResult = $this->findSlotsForService(
                $nearestSlot,
                $serviceSlots,
                $requiredMinutes,
                $date,
                $slotCapacities,
                $slotUsageCount,
                $lastServiceEndTime
            );

            if (!$serviceSlotResult) {
                return null;
            }

            $actualStartTime = $serviceSlotResult['start_time'];
            $actualEndTime = $serviceSlotResult['end_time'];
            $usedSlotIds = $serviceSlotResult['used_slot_ids'];

            foreach ($usedSlotIds as $slotId) {
                $slotUsageCount[$slotId] = ($slotUsageCount[$slotId] ?? 0) + 1;
                $allUsedSlots[] = $slotId;
            }

            $scheduledServices[] = [
                'service_id' => $serviceId,
                'service_name' => $serviceDurations[$serviceId]['service']->name,
                'start_time' => $actualStartTime->toTimeString(),
                'end_time' => $actualEndTime->toTimeString(),
                'duration_minutes' => $requiredMinutes,
                'used_slot_ids' => $usedSlotIds,
            ];

            if ($actualStartTime->lt($earliestStart)) {
                $earliestStart = $actualStartTime;
            }
            if ($actualEndTime->gt($latestEnd)) {
                $latestEnd = $actualEndTime;
            }

            $lastServiceEndTime = $actualEndTime;
        }

        return (object)[
            'id' => null,
            'service_id' => null,
            'start_time' => $earliestStart->toTimeString(),
            'end_time' => $latestEnd->toTimeString(),
            'capacity' => 1,
            'booked_count' => 0,
            'status' => 'available',
            'is_virtual' => true,
            'optimized_service_order' => $scheduledServices,
            'used_slot_ids' => array_unique($allUsedSlots),
        ];
    }

    private function findNearestSlot($serviceSlots, $afterTime, $date, $slotCapacities, $slotUsageCount)
    {
        foreach ($serviceSlots as $slot) {
            if ($slot->booked_count >= $slot->capacity) {
                continue;
            }

            $slotStartTime = Carbon::parse($date . ' ' . $slot->start_time);

            if ($slotStartTime->gte($afterTime)) {
                $currentUsage = $slotUsageCount[$slot->id] ?? 0;
                $availableCapacity = $slotCapacities[$slot->id]['capacity'] - $slotCapacities[$slot->id]['booked_count'] - $currentUsage;

                if ($availableCapacity > 0) {
                    return $slot;
                }
            }
        }

        return null;
    }

    private function findSlotsForService($startSlot, $serviceSlots, $requiredMinutes, $date, $slotCapacities, $slotUsageCount, $minStartTime = null)
    {
        $startTime = Carbon::parse($date . ' ' . $startSlot->start_time);
        $endTime = Carbon::parse($date . ' ' . $startSlot->end_time);

        if ($minStartTime && $minStartTime->gt($startTime)) {
            $startTime = $minStartTime;
        }

        $slotDuration = $startTime->diffInMinutes($endTime);
        $usedSlots = collect([$startSlot]);

        if ($slotDuration >= $requiredMinutes) {
            $canUse = true;
            foreach ($usedSlots as $slot) {
                $currentUsage = $slotUsageCount[$slot->id] ?? 0;
                $availableCapacity = $slotCapacities[$slot->id]['capacity'] - $slotCapacities[$slot->id]['booked_count'] - $currentUsage;
                if ($availableCapacity <= 0) {
                    $canUse = false;
                    break;
                }
            }

            if ($canUse) {
                $actualEndTime = $startTime->copy()->addMinutes($requiredMinutes);
                return [
                    'start_time' => $startTime,
                    'end_time' => $actualEndTime,
                    'used_slot_ids' => $usedSlots->pluck('id')->toArray(),
                ];
            }
        }

        $currentEndTime = $endTime;
        $slotIndex = $serviceSlots->search(function($slot) use ($startSlot) {
            return $slot->id === $startSlot->id;
        });

        for ($i = $slotIndex + 1; $i < $serviceSlots->count(); $i++) {
            $nextSlot = $serviceSlots[$i];

            if ($nextSlot->booked_count >= $nextSlot->capacity) {
                continue;
            }

            $nextStart = Carbon::parse($date . ' ' . $nextSlot->start_time);
            $nextEnd = Carbon::parse($date . ' ' . $nextSlot->end_time);

            $gap = $nextStart->diffInMinutes($currentEndTime);

            if ($gap == 0) {
                $currentUsage = $slotUsageCount[$nextSlot->id] ?? 0;
                $availableCapacity = $slotCapacities[$nextSlot->id]['capacity'] - $slotCapacities[$nextSlot->id]['booked_count'] - $currentUsage;

                if ($availableCapacity > 0) {
                    $usedSlots->push($nextSlot);
                    if ($nextEnd->gt($currentEndTime)) {
                        $currentEndTime = $nextEnd;
                    }

                    $totalDuration = $startTime->diffInMinutes($currentEndTime);

                    if ($totalDuration >= $requiredMinutes) {
                        $actualEndTime = $startTime->copy()->addMinutes($requiredMinutes);
                        return [
                            'start_time' => $startTime,
                            'end_time' => $actualEndTime,
                            'used_slot_ids' => $usedSlots->pluck('id')->toArray(),
                        ];
                    }
                }
            } else {
                break;
            }
        }

        return null;
    }

    private function getServiceDuration($service, $petSize)
    {
        switch ($petSize) {
            case 'small':
                return $service->duration_small ?? $service->duration;
            case 'medium':
                return $service->duration_medium ?? $service->duration;
            case 'large':
                return $service->duration_large ?? $service->duration;
            case 'xlarge':
                return $service->duration_xlarge ?? $service->duration;
            default:
                return $service->duration;
        }
    }

    public function getValidationInfo(Request $request)
    {
        $request->validate([
            'pet_id' => 'required_without:pet_ids|nullable|exists:pet_profiles,id',
            'pet_ids' => 'required_without:pet_id|nullable|array|min:1',
            'pet_ids.*' => 'exists:pet_profiles,id',
            'service_id' => 'required|exists:services,id',
            'package_id' => 'nullable|exists:packages,id',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
            'additional_services_by_pet' => 'nullable|array',
            'additional_services_by_pet.*' => 'nullable|array',
            'additional_services_by_pet.*.*' => 'exists:services,id',
            'additional_service_time_slots' => 'nullable|array',
            'additional_service_time_slots.*' => 'nullable|exists:time_slots,id',
            'additional_service_time_slots_by_pet' => 'nullable|array',
            'additional_service_time_slots_by_pet.*' => 'nullable|array',
            'additional_service_time_slots_by_pet.*.*' => 'nullable|exists:time_slots,id',
        ]);

        $capacity  = Kennel::count() + $this->spaceRoomsQuery()->count();
        $occupiedKennelCount = Appointment::whereNotNull('kennel_id')
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->whereHas('service.category', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%boarding%']))
            ->distinct('kennel_id')
            ->count('kennel_id');
        $occupied = $occupiedKennelCount + $this->spaceRoomsQuery()->where('status', '!=', 'Available')->count();
        $available_status = $occupied / $capacity < 0.5? true : false;

        $selectedPetIds = collect($request->input('pet_ids', []))
            ->filter(fn ($id) => !empty($id))
            ->map(fn ($id) => intval($id))
            ->unique()
            ->values();

        if ($selectedPetIds->isEmpty() && $request->filled('pet_id')) {
            $selectedPetIds = collect([(int) $request->pet_id]);
        }

        $pets = PetProfile::with(['vaccinations', 'owner'])
            ->whereIn('id', $selectedPetIds)
            ->get()
            ->keyBy('id');

        $primaryPet = $pets->get($selectedPetIds->first());

        $service = Service::find($request->service_id);
        $additionalServiceIds = collect($request->input('additional_services', []))
            ->merge(collect($request->input('additional_services_by_pet', []))->flatten(1))
            ->filter(fn ($id) => !empty($id))
            ->map(fn ($id) => intval($id))
            ->unique()
            ->values();

        $chauffeurSelectedInAdditionalServices = false;
        $ownerAddressValid = true;
        $facilityAddressValid = true;

        if ($additionalServiceIds->isNotEmpty()) {
            $additionalServices = Service::with('category')
                ->whereIn('id', $additionalServiceIds)
                ->get();

            $chauffeurSelectedInAdditionalServices = $additionalServices->contains(function ($additionalService) {
                return isChauffeurService($additionalService);
            });
        }

        // Required vaccine validation by pet type
        $vaccineValidator = new \App\Services\PetVaccineValidator();
        $vaccineStatus = 'approved';
        $vaccineMessages = [];
        $hasExpired = false;
        $hasMissing = false;

        foreach ($selectedPetIds as $petId) {
            $pet = $pets->get($petId);
            if (!$pet) {
                continue;
            }

            $vaccineValidation = $vaccineValidator->validate($pet);
            if ($vaccineValidation['valid']) {
                continue;
            }

            if (($vaccineValidation['status'] ?? null) === 'expired') {
                $hasExpired = true;
            } else {
                $hasMissing = true;
            }

            $petMessages = $vaccineValidation['messages'] ?? [];
            if (empty($petMessages) && !empty($vaccineValidation['message'])) {
                $petMessages = [$vaccineValidation['message']];
            }

            foreach ($petMessages as $message) {
                $vaccineMessages[] = $pet->name . ': ' . $message;
            }
        }

        if ($hasMissing) {
            $vaccineStatus = false;
        } elseif ($hasExpired) {
            $vaccineStatus = 'expired';
        }

        $vaccineMessage = $vaccineMessages[0] ?? null;

        $questionnaire = Questionnaire::where('pet_id', optional($primaryPet)->id)
            ->where('user_id', optional($primaryPet)->user_id)
            ->where('service_category_id', $service->category->id)
            ->orderBy('created_at', 'desc')
            ->first();
        $questionnaireStatus = $questionnaire && $questionnaire->status === 'approved' ? true : false;

        // Pet Owner profile status
        $ownerStatus = $pets->every(function ($pet) {
            return (bool) optional($pet->owner)->status;
        });

        return response()->json([
            'owner_status' => $ownerStatus,
            'vaccine_status' => $vaccineStatus,
            'vaccine_message' => $vaccineMessage,
            'vaccine_messages' => $vaccineMessages,
            'questionnaire_status' => $questionnaireStatus,
            'available_status' => $available_status,
        ]);
    }

    public function create(Request $request, AppointmentBookingNotifier $bookingNotifier)
    {
        $request->validate([
            'customer' => 'required|exists:users,id',
            'pet' => 'required|array|min:1',
            'pet.*' => 'exists:pet_profiles,id',
            'service' => 'required|exists:services,id',
            'staff' => 'nullable|exists:users,id',
            'kennel' => 'nullable|exists:kennels,id',
            'room' => 'nullable|exists:rooms,id',
            'allow_assignment_conflict' => 'nullable|boolean',
            'assignment_conflict_info' => 'nullable|string',
            'date' => 'nullable|date',
            'time_slot' => 'nullable|exists:time_slots,id',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
            'additional_services_by_pet' => 'nullable|array',
            'additional_services_by_pet.*' => 'nullable|array',
            'additional_services_by_pet.*.*' => 'exists:services,id',
            'additional_service_time_slots' => 'nullable|array',
            'additional_service_time_slots.*' => 'nullable|exists:time_slots,id',
            'family_pet_assignments' => 'nullable|array',
            'family_pet_assignments.*.room_id' => 'nullable|exists:rooms,id',
            'family_pet_assignments.*.kennel_id' => 'nullable|exists:kennels,id',
        ]);

        $petIds = collect($request->input('pet', []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $timeSlot = TimeSlot::with('service.category')->find($request->time_slot);

        $metadata = [];
        if ($timeSlot) {
            if (isDaycareService($timeSlot->service)) {
                if ($timeSlot->daycare_type) {
                    $metadata['daycare_duration'] = $timeSlot->daycare_type === 'full' ? 'full_day' : 'half_day';

                    if ($timeSlot->daycare_type === 'half') {
                        $startTime = Carbon::parse($timeSlot->start_time);
                        $metadata['session'] = $startTime->hour < 13 ? 'morning' : 'afternoon';
                    }
                }
            }
            if (isPrivateTrainingService($timeSlot->service)) {
                if ($timeSlot->private_training_type) {
                    $metadata['private_training_duration'] = $timeSlot->private_training_type === 'one' ? 'one_hour' : 'half_hour';
                }
            }
        }

        $service = Service::with('category')->find($request->service);
        $selectedPets = PetProfile::whereIn('id', $petIds)->get();
        $selectedRoom = $request->filled('room') ? Room::find($request->room) : null;
        $roomType = $this->getRoomAssignmentType($selectedRoom);
        $assignedKennelId = $request->filled('kennel') ? (int) $request->kennel : null;
        $familyKennelMode = $this->getFamilyKennelMode($petIds);
        $familyKennelAssignments = $this->buildFamilyKennelAssignmentsForMode(
            $petIds,
            $familyKennelMode,
            $assignedKennelId,
            $request->input('family_kennel_assignments', [])
        );
        $familyPetAssignments = $this->normalizeFamilyPetAssignmentsInput(
            $request->input('family_pet_assignments', []),
            $petIds
        );
        $assignmentConflict = null;

        if (isBoardingService($service)) {
            if ($familyKennelMode !== 'individual' && !$selectedRoom) {
                return back()->withErrors([
                    'room' => 'Please select a room for the boarding appointment.'
                ])->withInput();
            }

            if ($familyKennelMode === 'individual') {
                if (count($familyPetAssignments) !== count($petIds)) {
                    return back()->withErrors([
                        'room' => 'Please assign a room to each selected pet.'
                    ])->withInput();
                }

                $roomIds = collect($familyPetAssignments)
                    ->pluck('room_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');

                foreach ($familyPetAssignments as $petId => $assignment) {
                    $roomId = (int) ($assignment['room_id'] ?? 0);
                    $kennelId = (int) ($assignment['kennel_id'] ?? 0);
                    $room = $roomsById->get($roomId);

                    if (!$room) {
                        return back()->withErrors([
                            'room' => 'One or more selected rooms are invalid.'
                        ])->withInput();
                    }

                    $petTypeValidation = $this->validateRoomPetTypes($room, [(int) $petId]);
                    if ($petTypeValidation['valid'] === false) {
                        return back()->withErrors([
                            'room' => $petTypeValidation['message'] ?? 'This room cannot be used for one or more selected pets.'
                        ])->withInput();
                    }

                    if ($this->getRoomAssignmentType($room) === 'standard') {
                        if ($kennelId <= 0) {
                            return back()->withErrors([
                                'kennel' => 'Please assign a kennel to each selected pet in a standard room.'
                            ])->withInput();
                        }

                        if (!in_array($kennelId, $room->kennel_id_array, true)) {
                            return back()->withErrors([
                                'kennel' => 'One or more kennel assignments do not belong to the selected room.'
                            ])->withInput();
                        }
                    }
                }

                $familyKennelAssignments = $this->buildFamilyKennelAssignmentsFromPerPetAssignments($familyPetAssignments);
                $primaryAssignment = $this->getPrimaryAssignmentFromPerPetAssignments($familyPetAssignments);
                $selectedRoom = $primaryAssignment['room_id'] ? Room::find($primaryAssignment['room_id']) : null;
                $roomType = $this->getRoomAssignmentType($selectedRoom);
                $assignedKennelId = $primaryAssignment['kennel_id'];
            } elseif ($roomType !== 'standard') {
                $petTypeValidation = $this->validateRoomPetTypes($selectedRoom, $petIds);
                if ($petTypeValidation['valid'] === false) {
                    return back()->withErrors([
                        'room' => $petTypeValidation['message'] ?? 'This room cannot be used for the selected pets.'
                    ])->withInput();
                }
            } elseif ($familyKennelMode === 'individual') {
                if (count($familyKennelAssignments) !== count($petIds)) {
                    return back()->withErrors([
                        'kennel' => 'Please assign a kennel to each selected pet.'
                    ])->withInput();
                }

                $roomKennelIds = collect($selectedRoom->kennel_id_array)
                    ->map(fn ($kennelId) => (int) $kennelId)
                    ->filter(fn ($kennelId) => $kennelId > 0)
                    ->values()
                    ->all();

                foreach ($familyKennelAssignments as $petId => $petKennelId) {
                    if (!in_array((int) $petKennelId, $roomKennelIds, true)) {
                        return back()->withErrors([
                            'kennel' => 'One or more kennel assignments do not belong to the selected room.'
                        ])->withInput();
                    }
                }
            } else {
                if (!$assignedKennelId) {
                    return back()->withErrors([
                        'kennel' => 'Please select a kennel for the selected room.'
                    ])->withInput();
                }

                if (!in_array($assignedKennelId, $selectedRoom->kennel_id_array, true)) {
                    return back()->withErrors([
                        'kennel' => 'The selected kennel does not belong to the selected room.'
                    ])->withInput();
                }
            }

            if ($familyKennelMode !== 'individual' && $roomType === 'standard') {
                $cats = PetProfile::whereIn('id', $petIds)
                    ->whereRaw('LOWER(type) = ?', ['cat'])
                    ->get();

                if ($cats->isNotEmpty()) {
                    return back()->withErrors([
                        'kennel' => 'Kennels are for dogs only. Cats cannot be assigned to kennels. Please use a cat room instead.'
                    ])->withInput();
                }
            }

            if ($familyKennelMode !== 'individual' && $roomType === 'standard') {
                if ($familyKennelMode === 'shared' && !$assignedKennelId) {
                    return back()->withErrors([
                        'kennel' => 'Please select a kennel for the selected room.'
                    ])->withInput();
                }
            }

            if ($request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
                if ($familyKennelMode === 'individual') {
                    $payloadConflict = $this->buildFamilyPetAssignmentConflictPayload(
                        $familyPetAssignments,
                        Carbon::parse($request->boarding_start_datetime),
                        Carbon::parse($request->boarding_end_datetime),
                        null
                    );
                } else {
                    $payloadConflict = $this->buildAssignmentConflictPayload(
                        $selectedRoom,
                        $familyKennelMode,
                        $familyKennelMode === 'shared' ? $assignedKennelId : null,
                        $familyKennelAssignments,
                        Carbon::parse($request->boarding_start_datetime),
                        Carbon::parse($request->boarding_end_datetime),
                        null,
                        $petIds
                    );
                }

                if (!empty($payloadConflict['conflict']) && empty($assignmentConflict)) {
                    $assignmentConflict = $payloadConflict;
                }

                if (!empty($assignmentConflict['conflict']) && !$request->boolean('allow_assignment_conflict')) {
                    return back()->withErrors([
                        'room' => $assignmentConflict['message'] ?? 'The selected assignment is already in use during this time period.'
                    ])->withInput();
                }
            }
        }

        $additionalServicesByPet = $this->normalizeAdditionalServicesByPetInput(
            $petIds,
            $request->input('additional_services_by_pet', []),
            $request->input('additional_services', [])
        );

        $selectedAdditionalServiceIds = collect($additionalServicesByPet)
            ->flatten()
            ->map(fn ($serviceId) => (int) $serviceId)
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->unique()
            ->values()
            ->all();

        $selectedAdditionalServices = !empty($selectedAdditionalServiceIds)
            ? Service::with('category')->whereIn('id', $selectedAdditionalServiceIds)->get()
            : collect([]);

        $hasNonGroomingAdditionalService = $selectedAdditionalServices->contains(function ($additionalService) {
            return !isGroomingService($additionalService);
        });

        if ($hasNonGroomingAdditionalService) {
            return back()->withErrors([
                'additional_services' => 'Additional services must be grooming services.'
            ])->withInput();
        }

        $requiresBoardingAdditionalService = isBoardingService($service);

        $requiresAdditionalServiceTimeSlot = $requiresBoardingAdditionalService && $selectedAdditionalServices->isNotEmpty();

        $requiredAdditionalServicePairCount = collect($additionalServicesByPet)
            ->map(function ($serviceIds) {
                if (!is_array($serviceIds)) {
                    return 0;
                }

                return collect($serviceIds)
                    ->map(fn ($serviceId) => (int) $serviceId)
                    ->filter(fn ($serviceId) => $serviceId > 0)
                    ->unique()
                    ->count();
            })
            ->sum();

        $additionalServiceTimeSlotAssignments = $this->normalizeAdditionalServiceTimeSlotsByPetInput(
            $request->input('additional_service_time_slots_by_pet', []),
            $additionalServicesByPet,
            $request->filled('time_slot') ? (int) $request->time_slot : null,
            $request->input('additional_service_time_slots', [])
        );

        $additionalTimeSlotIds = collect($additionalServiceTimeSlotAssignments)
            ->pluck('time_slot_id')
            ->map(fn ($slotId) => (int) $slotId)
            ->filter(fn ($slotId) => $slotId > 0)
            ->unique()
            ->values();

        $additionalTimeSlotsById = $additionalTimeSlotIds->isNotEmpty()
            ? TimeSlot::with('service.category')->whereIn('id', $additionalTimeSlotIds->all())->get()->keyBy('id')
            : collect();

        $additionalServiceTimeSlotDetailsByPet = [];
        $additionalServiceTimeSlotDetailsByService = [];
        foreach ($additionalServiceTimeSlotAssignments as $assignment) {
            $selectedPetId = (int) ($assignment['pet_id'] ?? 0);
            $selectedAdditionalServiceId = (int) ($assignment['service_id'] ?? 0);
            $selectedTimeSlotId = (int) ($assignment['time_slot_id'] ?? 0);

            $timeSlotForService = $additionalTimeSlotsById->get($selectedTimeSlotId);
            if (!$timeSlotForService || (int) $timeSlotForService->service_id !== $selectedAdditionalServiceId) {
                return back()->withErrors([
                    'additional_service_time_slots' => 'Please select a valid time slot for each additional service.'
                ])->withInput();
            }

            $slotDetails = [
                'time_slot_id' => (int) $timeSlotForService->id,
                'service_id' => (int) $timeSlotForService->service_id,
                'date' => $timeSlotForService->date,
                'start_time' => $timeSlotForService->start_time,
                'end_time' => $timeSlotForService->end_time,
            ];

            $additionalServiceTimeSlotDetailsByPet[$selectedPetId][$selectedAdditionalServiceId] = $slotDetails;

            if (!isset($additionalServiceTimeSlotDetailsByService[$selectedAdditionalServiceId])) {
                $additionalServiceTimeSlotDetailsByService[$selectedAdditionalServiceId] = $slotDetails;
            }
        }

        if ($requiresAdditionalServiceTimeSlot && count($additionalServiceTimeSlotAssignments) !== (int) $requiredAdditionalServicePairCount) {
            return back()->withErrors([
                'additional_service_time_slots' => 'Please select a valid time slot for each additional service.'
            ])->withInput();
        }

        if ($request->has('secondary_services')) {
            $metadata['secondary_service_ids'] = implode(',', $request->secondary_services);
        }

        if ($selectedRoom) {
            $metadata['room_id'] = $selectedRoom->id;
            $metadata['room_name'] = $selectedRoom->name;
        }

        if ($requiresAdditionalServiceTimeSlot && !empty($additionalServiceTimeSlotDetailsByPet)) {
            $metadata['additional_service_time_slots_by_pet'] = $additionalServiceTimeSlotDetailsByPet;
            $metadata['additional_service_time_slots'] = $additionalServiceTimeSlotDetailsByService;

            $firstAdditionalServiceTimeSlot = collect($additionalServiceTimeSlotDetailsByService)->first();
            if ($firstAdditionalServiceTimeSlot) {
                $metadata['additional_service_time_slot_id'] = $firstAdditionalServiceTimeSlot['time_slot_id'] ?? null;
                $metadata['additional_service_time_slot_service_id'] = $firstAdditionalServiceTimeSlot['service_id'] ?? null;
                $metadata['additional_service_time_slot_date'] = $firstAdditionalServiceTimeSlot['date'] ?? null;
                $metadata['additional_service_time_slot_start_time'] = $firstAdditionalServiceTimeSlot['start_time'] ?? null;
                $metadata['additional_service_time_slot_end_time'] = $firstAdditionalServiceTimeSlot['end_time'] ?? null;
            }
        } else {
            unset(
                $metadata['additional_service_time_slots_by_pet'],
                $metadata['additional_service_time_slots'],
                $metadata['additional_service_time_slot_id'],
                $metadata['additional_service_time_slot_service_id'],
                $metadata['additional_service_time_slot_date'],
                $metadata['additional_service_time_slot_start_time'],
                $metadata['additional_service_time_slot_end_time']
            );
        }

        if ($selectedRoom) {
            $metadata['assignment_room_id'] = $selectedRoom->id;
            $metadata['assignment_room_name'] = $selectedRoom->name;
            $metadata['assignment_room_type'] = $roomType;
            $assignmentKennelId = $this->getAssignmentKennelIdForMode($familyKennelMode, $assignedKennelId, $familyKennelAssignments);

            if ($roomType === 'standard' && $assignmentKennelId) {
                $metadata['assignment_kennel_id'] = $assignmentKennelId;
                $metadata['assignment_kennel_name'] = optional(Kennel::find($assignmentKennelId))->name;
            } else {
                unset($metadata['assignment_kennel_id'], $metadata['assignment_kennel_name']);
            }

            $metadata['family_kennel_mode'] = $familyKennelMode;
            if (!empty($familyKennelAssignments)) {
                $metadata['family_kennel_assignments'] = $familyKennelAssignments;
            } else {
                unset($metadata['family_kennel_assignments']);
            }

            if (!empty($familyPetAssignments)) {
                $metadata['family_pet_assignments'] = $familyPetAssignments;
            } else {
                unset($metadata['family_pet_assignments']);
            }

            if (!empty($assignmentConflict['conflict'])) {
                $metadata['assignment_conflict'] = true;
                $metadata['assignment_conflict_type'] = $assignmentConflict['conflict_type'] ?? null;
                $metadata['assignment_conflict_message'] = $assignmentConflict['message'] ?? null;
                $metadata['assignment_conflict_occupants'] = $assignmentConflict['current_occupants'] ?? [];
                $metadata['warning_codes'] = array_values(array_filter(
                    is_array($assignmentConflict['warning_codes'] ?? null)
                        ? $assignmentConflict['warning_codes']
                        : []
                ));
                if ($request->boolean('allow_assignment_conflict')) {
                    $metadata['was_allowed_with_conflict'] = true;
                }
            } else {
                unset(
                    $metadata['assignment_conflict'],
                    $metadata['assignment_conflict_type'],
                    $metadata['assignment_conflict_message'],
                    $metadata['assignment_conflict_occupants'],
                    $metadata['warning_codes'],
                    $metadata['was_allowed_with_conflict']
                );
            }
        }

        $usedSlotIds = [];

        if ($timeSlot && !is_null($timeSlot->capacity) && ($timeSlot->booked_count + count($petIds)) > $timeSlot->capacity) {
            return back()->withErrors([
                'time_slot' => 'The selected time slot does not have enough capacity for all selected pets.'
            ])->withInput();
        }

        if ($requiresAdditionalServiceTimeSlot) {
            $slotUsageCounts = collect($additionalServiceTimeSlotAssignments)
                ->groupBy('time_slot_id')
                ->map(fn ($rows) => $rows->count())
                ->all();

            foreach ($slotUsageCounts as $timeSlotId => $requiredCapacity) {
                $slotModel = $additionalTimeSlotsById->get((int) $timeSlotId);
                if (!$slotModel) {
                    continue;
                }

                if (!is_null($slotModel->capacity) && ($slotModel->booked_count + $requiredCapacity) > $slotModel->capacity) {
                    return back()->withErrors([
                        'additional_service_time_slots' => 'One or more selected additional service time slots does not have enough capacity.'
                    ])->withInput();
                }
            }
        }

        $primaryPetId = $petIds[0] ?? null;

        if (count($petIds) > 1) {
            $metadata['family_pet_ids'] = $petIds;
        } else {
            unset($metadata['family_pet_ids']);
        }

        if (count($petIds) > 1 || !empty(collect($additionalServicesByPet)->flatten()->all())) {
            $metadata['additional_services_by_pet'] = $additionalServicesByPet;
        } else {
            unset($metadata['additional_services_by_pet']);
        }

        $appointment = new Appointment;
        $appointment->customer_id = $request->customer;
        $appointment->pet_id = $primaryPetId;
        $appointment->service_id = $request->service;
        $appointment->kennel_id = isBoardingService($service) && $roomType === 'standard'
            ? $this->getAssignmentKennelIdForMode($familyKennelMode, $assignedKennelId, $familyKennelAssignments)
            : null;
        $appointment->cat_room_id = $selectedRoom?->id;

        if ($request->filled('staff')) {
            $appointment->staff_id = $request->staff;
        }

        if ($request->filled('date')) {
            $appointment->date = $request->date;

            if (isAlaCarteService($service) && $alaCarteStartTime && $alaCarteEndTime) {
                $appointment->start_time = $alaCarteStartTime;
                $appointment->end_time = $alaCarteEndTime;
            } else {
                $appointment->start_time = $timeSlot ? $timeSlot->start_time : null;
                $appointment->end_time = $timeSlot ? $timeSlot->end_time : null;
            }
        } else if ($request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
            $startDateTime = Carbon::parse($request->boarding_start_datetime);
            $endDateTime = Carbon::parse($request->boarding_end_datetime);

            if (isBoardingService($service)) {
                $dropOffMinutes = ((int) $startDateTime->format('H') * 60) + (int) $startDateTime->format('i');
                $businessStartMinutes = 9 * 60;
                $businessEndMinutes = 16 * 60;
                $isEarlyDropOff = $dropOffMinutes < $businessStartMinutes;
                $isLateDropOff = $dropOffMinutes > $businessEndMinutes;
                $canCreateEarlyDropOff = Auth::user()
                    ? Auth::user()->roles()->whereRaw('LOWER(title) = ?', ['owner'])->exists()
                    : false;

                if (($isEarlyDropOff && !$canCreateEarlyDropOff) || $isLateDropOff) {
                    return back()->withErrors([
                        'boarding_start_datetime' => 'Drop-off time must be between 9:00 AM and 4:00 PM.'
                    ])->withInput();
                }
            }

            if ($requiresAdditionalServiceTimeSlot) {
                foreach ($additionalServiceTimeSlotAssignments as $assignment) {
                    $slotDetails = $additionalServiceTimeSlotDetailsByPet[(int) ($assignment['pet_id'] ?? 0)][(int) ($assignment['service_id'] ?? 0)] ?? null;
                    if (!$slotDetails) {
                        continue;
                    }

                    $timeSlotStart = Carbon::parse($slotDetails['date'] . ' ' . $slotDetails['start_time']);
                    $timeSlotEnd = Carbon::parse($slotDetails['date'] . ' ' . $slotDetails['end_time']);

                    if ($timeSlotEnd->gt($endDateTime) || ($endDateTime->gt($timeSlotStart) && $endDateTime->lt($timeSlotEnd))) {
                        return back()->withErrors([
                            'additional_service_time_slots' => 'Each additional service time slot must end before the pick up time.'
                        ])->withInput();
                    }
                }
            }

            $appointment->date = $startDateTime->toDateString();
            $appointment->start_time = $startDateTime->toTimeString();
            $appointment->end_date = $endDateTime->toDateString();
            $appointment->end_time = $endDateTime->toTimeString();

            if (isBoardingService($service)) {
                $appointment->estimated_price = $this->calculateFamilyBoardingEstimatedPrice(
                    $service,
                    $appointment,
                    $petIds,
                    $additionalServicesByPet,
                    (int) $request->customer
                );
            }
        }

        $appointment->status = 'checked_in';
        $appointment->additional_service_ids = !empty($selectedAdditionalServiceIds)
            ? implode(',', $selectedAdditionalServiceIds)
            : null;
        $appointment->metadata = !empty($metadata) ? $metadata : null;
        $appointment->save();

        $bookingNotifier->sendConfirmation($appointment, Auth::id());

        appointment_audit_log($appointment->id, 'Appointment is created.');

        if ($selectedRoom && isBoardingService($service) && $roomType === 'space') {
            $this->markCatRoomOutOfService($selectedRoom->id);
        }

        if ($timeSlot && !is_null($timeSlot->capacity)) {
            $timeSlot->booked_count += count($petIds);
            if ($timeSlot->booked_count >= $timeSlot->capacity) {
                $timeSlot->status = 'full';
            }
            $timeSlot->save();
        }

        if (!empty($usedSlotIds)) {
            $timeSlots = TimeSlot::whereIn('id', $usedSlotIds)->get();
            foreach ($timeSlots as $timeSlot) {
                $timeSlot->booked_count += count($petIds);
                if ($timeSlot->booked_count >= $timeSlot->capacity) {
                    $timeSlot->status = 'full';
                }
                $timeSlot->save();
            }
        }

        if ($requiresAdditionalServiceTimeSlot && !empty($additionalServiceTimeSlotAssignments)) {
            $slotUsageCounts = collect($additionalServiceTimeSlotAssignments)
                ->groupBy('time_slot_id')
                ->map(fn ($rows) => $rows->count())
                ->all();

            foreach ($slotUsageCounts as $timeSlotId => $requiredCapacity) {
                $slotModel = $additionalTimeSlotsById->get((int) $timeSlotId);
                if (!$slotModel) {
                    continue;
                }

                $slotModel->booked_count += $requiredCapacity;
                if (!is_null($slotModel->capacity) && $slotModel->booked_count >= $slotModel->capacity) {
                    $slotModel->status = 'full';
                }
                $slotModel->save();
            }
        }

        return redirect()->route('appointments')->with([
            'message' => count($petIds) > 1 ? 'Appointments created successfully.' : 'Appointment created successfully.',
            'status' => 'success'
        ]);
    }

    public function generateInvoiceNumber()
    {
        $invoiceNumber = Invoice::generateInvoiceNumber();
        return response()->json(['invoice_number' => $invoiceNumber]);
    }

    private function createInvoiceForAppointment($appointment, $request)
    {
        $existingInvoice = Invoice::where('invoice_number', $request->invoice_number)->first();
        if ($existingInvoice) {
            return;
        }

        $invoice = new Invoice;
        $invoice->appointment_id = $appointment->id;
        $invoice->customer_id = $appointment->customer_id;
        $invoice->invoice_number = $request->invoice_number;
        $invoice->first_name = $request->first_name;
        $invoice->last_name = $request->last_name;
        $invoice->email = $request->email;
        $invoice->issued_at = $request->issued_at ? Carbon::parse($request->issued_at) : Carbon::now();
        $invoice->due_date = $request->due_date ? Carbon::parse($request->due_date) : null;

        if ($request->status === 'paid' && !$request->paid_at) {
            $invoice->paid_at = Carbon::now();
        } else {
            $invoice->paid_at = $request->paid_at ? Carbon::parse($request->paid_at) : null;
        }

        $invoice->status = $request->status;
        $invoice->notes = $request->notes;
        $invoice->save();
        appointment_audit_log($appointment->id, "Invoice status changed to " . ucfirst($invoice->status) . ". Invoice #{$invoice->invoice_number}.");

        if ($request->filled('items') && is_array($request->items)) {
            foreach ($request->items as $itemData) {
                $item = new InvoiceItem;
                $item->invoice_id = $invoice->id;
                $item->item_name = $itemData['description'] ?? '';
                $item->price = $itemData['price'] ?? 0;
                $item->item_type = $itemData['type'] ?? 'service';
                $item->save();
            }
        }

        if ($request->status === 'paid' && $request->payment_amount && $request->payment_method) {
            $transaction = new Transaction;
            $transaction->appointment_id = $appointment->id;
            $transaction->invoice_id = $invoice->id;
            $transaction->user_id = $appointment->customer_id;
            $transaction->tran_date = $invoice->paid_at ?: Carbon::now();
            $transaction->amount = $request->payment_amount;
            $transaction->payment_method = $request->payment_method;
            $transaction->notes = $request->payment_notes;
            $transaction->save();
        }
    }

    public function edit($id)
    {
        $appointment = Appointment::findOrFail($id);
        $services = Service::where('status', 'active')->where('level', 'primary')->get();
        $additionalServices = Service::where('status', 'active')
            ->whereNot('id', $appointment->service_id)
            ->whereHas('category', function($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%groom%']);
            })
            ->get();

        $service = Service::with('category')->find($appointment->service_id);
        $timeSlots = collect([]);

        if (isBoardingService($service)) {
            $slotServiceId = $appointment->metadata['additional_service_time_slot_service_id'] ?? null;
            $slotDate = $appointment->metadata['additional_service_time_slot_date'] ?? $appointment->end_date;
            $pickupTime = $appointment->end_time;

            if ($slotServiceId && $slotDate && $appointment->pet_id) {
                $timeSlots = TimeSlot::where('service_id', $slotServiceId)
                    ->whereDate('date', $slotDate)
                    ->orderBy('start_time')
                    ->get();

                if ($pickupTime) {
                    $timeSlots = $this->filterTimeSlotsByPickupTime($timeSlots, $pickupTime);
                }

                $selectedSlotId = $appointment->metadata['additional_service_time_slot_id'] ?? null;
                if ($selectedSlotId) {
                    $selectedSlot = TimeSlot::find($selectedSlotId);
                    if ($selectedSlot && !$timeSlots->contains('id', $selectedSlot->id)) {
                        $timeSlots->prepend($selectedSlot);
                    }
                }
            }
        }

        $kennels = Kennel::orderBy('name')->get();
        $rooms = $this->getAssignmentRooms();
        $selectedAssignmentRoomId = $appointment->cat_room_id;
        $selectedAssignmentKennelId = $appointment->kennel_id;

        if (!$selectedAssignmentRoomId && $selectedAssignmentKennelId) {
            $selectedAssignmentRoomId = $rooms->first(function ($room) use ($selectedAssignmentKennelId) {
                return in_array((int) $selectedAssignmentKennelId, $room->kennel_id_array, true);
            })?->id;
        }

        $appointmentAdditionalServicesByPet = $appointment->additional_services_by_pet;
        $selectedAdditionalServiceIdsFlat = $appointment->additional_service_ids_flat;

        return view('appointments.update', compact(
            'appointment',
            'services',
            'additionalServices',
            'timeSlots',
            'kennels',
            'rooms',
            'selectedAssignmentRoomId',
            'selectedAssignmentKennelId',
            'appointmentAdditionalServicesByPet',
            'selectedAdditionalServiceIdsFlat'
        ));
    }

    public function update(Request $request, AppointmentBookingNotifier $bookingNotifier)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'customer' => 'required|exists:users,id',
            'pet' => 'required|array|min:1',
            'pet.*' => 'exists:pet_profiles,id',
            'service' => 'required|exists:services,id',
            'staff' => 'nullable|exists:users,id',
            'kennel' => 'nullable|exists:kennels,id',
            'room' => 'nullable|exists:rooms,id',
            'allow_assignment_conflict' => 'nullable|boolean',
            'assignment_conflict_info' => 'nullable|string',
            'date' => 'nullable|date',
            'time_slot' => 'nullable',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
            'additional_services_by_pet' => 'nullable|array',
            'additional_services_by_pet.*' => 'nullable|array',
            'additional_services_by_pet.*.*' => 'exists:services,id',
            'additional_service_time_slots' => 'nullable|array',
            'additional_service_time_slots.*' => 'nullable|exists:time_slots,id',
            'additional_service_time_slots_by_pet' => 'nullable|array',
            'additional_service_time_slots_by_pet.*' => 'nullable|array',
            'additional_service_time_slots_by_pet.*.*' => 'nullable|exists:time_slots,id',
            'family_pet_assignments' => 'nullable|array',
            'family_pet_assignments.*.room_id' => 'nullable|exists:rooms,id',
            'family_pet_assignments.*.kennel_id' => 'nullable|exists:kennels,id',
        ]);

        $appointment = Appointment::findOrFail($request->appointment_id);
        $timeSlot = $request->filled('time_slot')
            ? TimeSlot::with('service.category')->find($request->time_slot)
            : null;

        $petIds = collect($request->input('pet', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $metadata = is_array($appointment->metadata) ? $appointment->metadata : [];
        $service = Service::with('category')->find($request->service);
        $selectedRoom = $request->filled('room') ? Room::find($request->room) : null;
        $roomType = $this->getRoomAssignmentType($selectedRoom);
        $selectedKennelId = $request->filled('kennel') ? (int) $request->kennel : null;
        $familyKennelMode = $this->getFamilyKennelMode($petIds);
        $familyKennelAssignments = $this->buildFamilyKennelAssignmentsForMode(
            $petIds,
            $familyKennelMode,
            $selectedKennelId,
            $request->input('family_kennel_assignments', [])
        );
        $familyPetAssignments = $this->normalizeFamilyPetAssignmentsInput(
            $request->input('family_pet_assignments', []),
            $petIds
        );
        $assignmentConflict = null;

        if (isBoardingService($service)) {
            if ($familyKennelMode !== 'individual' && !$selectedRoom) {
                return back()->withErrors([
                    'room' => 'Please select a room for the boarding appointment.'
                ])->withInput();
            }

            if ($familyKennelMode === 'individual') {
                if (count($familyPetAssignments) !== count($petIds)) {
                    return back()->withErrors([
                        'room' => 'Please assign a room to each selected pet.'
                    ])->withInput();
                }

                $roomIds = collect($familyPetAssignments)
                    ->pluck('room_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $roomsById = Room::whereIn('id', $roomIds)->get()->keyBy('id');

                foreach ($familyPetAssignments as $petId => $assignment) {
                    $roomId = (int) ($assignment['room_id'] ?? 0);
                    $kennelId = (int) ($assignment['kennel_id'] ?? 0);
                    $room = $roomsById->get($roomId);

                    if (!$room) {
                        return back()->withErrors([
                            'room' => 'One or more selected rooms are invalid.'
                        ])->withInput();
                    }

                    $petTypeValidation = $this->validateRoomPetTypes($room, [(int) $petId]);
                    if ($petTypeValidation['valid'] === false) {
                        return back()->withErrors([
                            'room' => $petTypeValidation['message'] ?? 'This room cannot be used for one or more selected pets.'
                        ])->withInput();
                    }

                    if ($this->getRoomAssignmentType($room) === 'standard') {
                        if ($kennelId <= 0) {
                            return back()->withErrors([
                                'kennel' => 'Please assign a kennel to each selected pet in a standard room.'
                            ])->withInput();
                        }

                        if (!in_array($kennelId, $room->kennel_id_array, true)) {
                            return back()->withErrors([
                                'kennel' => 'One or more kennel assignments do not belong to the selected room.'
                            ])->withInput();
                        }
                    }
                }

                $familyKennelAssignments = $this->buildFamilyKennelAssignmentsFromPerPetAssignments($familyPetAssignments);
                $primaryAssignment = $this->getPrimaryAssignmentFromPerPetAssignments($familyPetAssignments);
                $selectedRoom = $primaryAssignment['room_id'] ? Room::find($primaryAssignment['room_id']) : null;
                $roomType = $this->getRoomAssignmentType($selectedRoom);
                $selectedKennelId = $primaryAssignment['kennel_id'];
            } elseif ($roomType !== 'standard') {
                $petTypeValidation = $this->validateRoomPetTypes($selectedRoom, $petIds);
                if ($petTypeValidation['valid'] === false) {
                    return back()->withErrors([
                        'room' => $petTypeValidation['message'] ?? 'This room cannot be used for the selected pets.'
                    ])->withInput();
                }
            } elseif ($familyKennelMode === 'individual') {
                if (count($familyKennelAssignments) !== count($petIds)) {
                    return back()->withErrors([
                        'kennel' => 'Please assign a kennel to each selected pet.'
                    ])->withInput();
                }

                $roomKennelIds = collect($selectedRoom->kennel_id_array)
                    ->map(fn ($kennelId) => (int) $kennelId)
                    ->filter(fn ($kennelId) => $kennelId > 0)
                    ->values()
                    ->all();

                foreach ($familyKennelAssignments as $petId => $petKennelId) {
                    if (!in_array((int) $petKennelId, $roomKennelIds, true)) {
                        return back()->withErrors([
                            'kennel' => 'One or more kennel assignments do not belong to the selected room.'
                        ])->withInput();
                    }
                }
            } else {
                if (!$selectedKennelId) {
                    return back()->withErrors([
                        'kennel' => 'Please select a kennel for the selected room.'
                    ])->withInput();
                }

                if (!in_array($selectedKennelId, $selectedRoom->kennel_id_array, true)) {
                    return back()->withErrors([
                        'kennel' => 'The selected kennel does not belong to the selected room.'
                    ])->withInput();
                }
            }

            if ($familyKennelMode !== 'individual' && $roomType === 'standard') {
                $cats = PetProfile::whereIn('id', $petIds)
                    ->whereRaw('LOWER(type) = ?', ['cat'])
                    ->get();

                if ($cats->isNotEmpty()) {
                    return back()->withErrors([
                        'kennel' => 'Kennels are for dogs only. Cats cannot be assigned to kennels. Please use a cat room instead.'
                    ])->withInput();
                }
            }

            if ($familyKennelMode !== 'individual' && $roomType === 'standard') {
                if ($familyKennelMode === 'shared' && !$selectedKennelId) {
                    return back()->withErrors([
                        'kennel' => 'Please select a kennel for the selected room.'
                    ])->withInput();
                }
            }

            if ($request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
                if ($familyKennelMode === 'individual') {
                    $payloadConflict = $this->buildFamilyPetAssignmentConflictPayload(
                        $familyPetAssignments,
                        Carbon::parse($request->boarding_start_datetime),
                        Carbon::parse($request->boarding_end_datetime),
                        (int) $appointment->id
                    );
                } else {
                    $payloadConflict = $this->buildAssignmentConflictPayload(
                        $selectedRoom,
                        $familyKennelMode,
                        $familyKennelMode === 'shared' ? $selectedKennelId : null,
                        $familyKennelAssignments,
                        Carbon::parse($request->boarding_start_datetime),
                        Carbon::parse($request->boarding_end_datetime),
                        (int) $appointment->id,
                        $petIds
                    );
                }

                if (!empty($payloadConflict['conflict']) && empty($assignmentConflict)) {
                    $assignmentConflict = $payloadConflict;
                }

                if (!empty($assignmentConflict['conflict']) && !$request->boolean('allow_assignment_conflict')) {
                    return back()->withErrors([
                        'room' => $assignmentConflict['message'] ?? 'The selected assignment is already in use during this time period.'
                    ])->withInput();
                }
            }
        }

        $primaryPetId = $petIds[0] ?? null;

        if (count($petIds) > 1) {
            $metadata['family_pet_ids'] = $petIds;
        } else {
            unset($metadata['family_pet_ids']);
        }

        $additionalServicesByPet = $this->normalizeAdditionalServicesByPetInput(
            $petIds,
            $request->input('additional_services_by_pet', []),
            $request->input('additional_services', [])
        );

        if (count($petIds) > 1 || !empty(collect($additionalServicesByPet)->flatten()->all())) {
            $metadata['additional_services_by_pet'] = $additionalServicesByPet;
        } else {
            unset($metadata['additional_services_by_pet']);
        }

        $selectedAdditionalServiceIds = collect($additionalServicesByPet)
            ->flatten()
            ->map(fn ($serviceId) => (int) $serviceId)
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->unique()
            ->values()
            ->all();

        $selectedAdditionalServices = !empty($selectedAdditionalServiceIds)
            ? Service::with('category')->whereIn('id', $selectedAdditionalServiceIds)->get()
            : collect([]);

        $hasNonGroomingAdditionalService = $selectedAdditionalServices->contains(function ($additionalService) {
            return !isGroomingService($additionalService);
        });

        if ($hasNonGroomingAdditionalService) {
            return back()->withErrors([
                'additional_services' => 'Additional services must be grooming services.'
            ])->withInput();
        }

        $requiresBoardingAdditionalService = isBoardingService($service);

        $requiresAdditionalServiceTimeSlot = $requiresBoardingAdditionalService && $selectedAdditionalServices->isNotEmpty();

        $requiredAdditionalServicePairCount = collect($additionalServicesByPet)
            ->map(function ($serviceIds) {
                if (!is_array($serviceIds)) {
                    return 0;
                }

                return collect($serviceIds)
                    ->map(fn ($serviceId) => (int) $serviceId)
                    ->filter(fn ($serviceId) => $serviceId > 0)
                    ->unique()
                    ->count();
            })
            ->sum();

        $additionalServiceTimeSlotAssignments = $this->normalizeAdditionalServiceTimeSlotsByPetInput(
            $request->input('additional_service_time_slots_by_pet', []),
            $additionalServicesByPet,
            $request->filled('time_slot') ? (int) $request->time_slot : null,
            $request->input('additional_service_time_slots', [])
        );

        $additionalTimeSlotIds = collect($additionalServiceTimeSlotAssignments)
            ->pluck('time_slot_id')
            ->map(fn ($slotId) => (int) $slotId)
            ->filter(fn ($slotId) => $slotId > 0)
            ->unique()
            ->values();

        $additionalTimeSlotsById = $additionalTimeSlotIds->isNotEmpty()
            ? TimeSlot::with('service.category')->whereIn('id', $additionalTimeSlotIds->all())->get()->keyBy('id')
            : collect();

        $additionalServiceTimeSlotDetailsByPet = [];
        $additionalServiceTimeSlotDetailsByService = [];
        foreach ($additionalServiceTimeSlotAssignments as $assignment) {
            $selectedPetId = (int) ($assignment['pet_id'] ?? 0);
            $selectedAdditionalServiceId = (int) ($assignment['service_id'] ?? 0);
            $selectedTimeSlotId = (int) ($assignment['time_slot_id'] ?? 0);

            $timeSlotForService = $additionalTimeSlotsById->get($selectedTimeSlotId);
            if (!$timeSlotForService || (int) $timeSlotForService->service_id !== $selectedAdditionalServiceId) {
                return back()->withErrors([
                    'additional_service_time_slots' => 'Please select a valid time slot for each additional service.'
                ])->withInput();
            }

            $slotDetails = [
                'time_slot_id' => (int) $timeSlotForService->id,
                'service_id' => (int) $timeSlotForService->service_id,
                'date' => $timeSlotForService->date,
                'start_time' => $timeSlotForService->start_time,
                'end_time' => $timeSlotForService->end_time,
            ];

            $additionalServiceTimeSlotDetailsByPet[$selectedPetId][$selectedAdditionalServiceId] = $slotDetails;

            if (!isset($additionalServiceTimeSlotDetailsByService[$selectedAdditionalServiceId])) {
                $additionalServiceTimeSlotDetailsByService[$selectedAdditionalServiceId] = $slotDetails;
            }
        }

        if ($requiresAdditionalServiceTimeSlot && count($additionalServiceTimeSlotAssignments) !== (int) $requiredAdditionalServicePairCount) {
            return back()->withErrors([
                'additional_service_time_slots' => 'Please select a valid time slot for each additional service.'
            ])->withInput();
        }

        if ($selectedRoom) {
            $metadata['room_id'] = $selectedRoom->id;
            $metadata['room_name'] = $selectedRoom->name;
        } else {
            unset($metadata['room_id'], $metadata['room_name']);
        }

        if ($requiresAdditionalServiceTimeSlot && !empty($additionalServiceTimeSlotDetailsByPet)) {
            $metadata['additional_service_time_slots_by_pet'] = $additionalServiceTimeSlotDetailsByPet;
            $metadata['additional_service_time_slots'] = $additionalServiceTimeSlotDetailsByService;

            $firstAdditionalServiceTimeSlot = collect($additionalServiceTimeSlotDetailsByService)->first();
            if ($firstAdditionalServiceTimeSlot) {
                $metadata['additional_service_time_slot_id'] = $firstAdditionalServiceTimeSlot['time_slot_id'] ?? null;
                $metadata['additional_service_time_slot_service_id'] = $firstAdditionalServiceTimeSlot['service_id'] ?? null;
                $metadata['additional_service_time_slot_date'] = $firstAdditionalServiceTimeSlot['date'] ?? null;
                $metadata['additional_service_time_slot_start_time'] = $firstAdditionalServiceTimeSlot['start_time'] ?? null;
                $metadata['additional_service_time_slot_end_time'] = $firstAdditionalServiceTimeSlot['end_time'] ?? null;
            }
        } else {
            unset(
                $metadata['additional_service_time_slots_by_pet'],
                $metadata['additional_service_time_slots'],
                $metadata['additional_service_time_slot_id'],
                $metadata['additional_service_time_slot_service_id'],
                $metadata['additional_service_time_slot_date'],
                $metadata['additional_service_time_slot_start_time'],
                $metadata['additional_service_time_slot_end_time']
            );
        }

        if ($selectedRoom) {
            $metadata['assignment_room_id'] = $selectedRoom->id;
            $metadata['assignment_room_name'] = $selectedRoom->name;
            $metadata['assignment_room_type'] = $roomType;
            $assignmentKennelId = $this->getAssignmentKennelIdForMode($familyKennelMode, $selectedKennelId, $familyKennelAssignments);

            if ($roomType === 'standard' && $assignmentKennelId) {
                $metadata['assignment_kennel_id'] = $assignmentKennelId;
                $metadata['assignment_kennel_name'] = optional(Kennel::find($assignmentKennelId))->name;
            } else {
                unset($metadata['assignment_kennel_id'], $metadata['assignment_kennel_name']);
            }

            $metadata['family_kennel_mode'] = $familyKennelMode;
            if (!empty($familyKennelAssignments)) {
                $metadata['family_kennel_assignments'] = $familyKennelAssignments;
            } else {
                unset($metadata['family_kennel_assignments']);
            }

            if (!empty($familyPetAssignments)) {
                $metadata['family_pet_assignments'] = $familyPetAssignments;
            } else {
                unset($metadata['family_pet_assignments']);
            }

            if (!empty($assignmentConflict['conflict'])) {
                $metadata['assignment_conflict'] = true;
                $metadata['assignment_conflict_type'] = $assignmentConflict['conflict_type'] ?? null;
                $metadata['assignment_conflict_message'] = $assignmentConflict['message'] ?? null;
                $metadata['assignment_conflict_occupants'] = $assignmentConflict['current_occupants'] ?? [];
                $metadata['warning_codes'] = array_values(array_filter(
                    is_array($assignmentConflict['warning_codes'] ?? null)
                        ? $assignmentConflict['warning_codes']
                        : []
                ));
                if ($request->boolean('allow_assignment_conflict')) {
                    $metadata['was_allowed_with_conflict'] = true;
                }
            } else {
                unset(
                    $metadata['assignment_conflict'],
                    $metadata['assignment_conflict_type'],
                    $metadata['assignment_conflict_message'],
                    $metadata['assignment_conflict_occupants'],
                    $metadata['warning_codes'],
                    $metadata['was_allowed_with_conflict']
                );
            }
        }

        $oldStatus = $appointment->status;
        $oldKennelId = $appointment->kennel_id;
        $oldRoomId = $appointment->cat_room_id;
        $newKennelId = isBoardingService($service) && $roomType === 'standard'
            ? $this->getAssignmentKennelIdForMode($familyKennelMode, $selectedKennelId, $familyKennelAssignments)
            : null;
        $newRoomId = isBoardingService($service) && $selectedRoom ? (int) $selectedRoom->id : null;

        $appointment->customer_id = $request->customer;
        $appointment->pet_id = $primaryPetId;
        $appointment->service_id = $request->service;
        $appointment->kennel_id = $newKennelId;
        $appointment->cat_room_id = $newRoomId;
        $appointment->additional_service_ids = !empty($selectedAdditionalServiceIds)
            ? implode(',', $selectedAdditionalServiceIds)
            : null;

        if ($request->filled('staff')) {
            $appointment->staff_id = $request->staff;
        } else {
            $appointment->staff_id = null;
        }

        if (isBoardingService($service) && $request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
            $startDateTime = Carbon::parse($request->boarding_start_datetime);
            $endDateTime = Carbon::parse($request->boarding_end_datetime);

            $dropOffMinutes = ((int) $startDateTime->format('H') * 60) + (int) $startDateTime->format('i');
            $businessStartMinutes = 9 * 60;
            $businessEndMinutes = 16 * 60;
            $isEarlyDropOff = $dropOffMinutes < $businessStartMinutes;
            $isLateDropOff = $dropOffMinutes > $businessEndMinutes;
            $canCreateEarlyDropOff = Auth::user()
                ? Auth::user()->roles()->whereRaw('LOWER(title) = ?', ['owner'])->exists()
                : false;

            if (($isEarlyDropOff && !$canCreateEarlyDropOff) || $isLateDropOff) {
                return back()->withErrors([
                    'boarding_start_datetime' => 'Drop-off time must be between 9:00 AM and 4:00 PM.'
                ])->withInput();
            }

            if ($requiresAdditionalServiceTimeSlot) {
                foreach ($additionalServiceTimeSlotAssignments as $assignment) {
                    $slotDetails = $additionalServiceTimeSlotDetailsByPet[(int) ($assignment['pet_id'] ?? 0)][(int) ($assignment['service_id'] ?? 0)] ?? null;
                    if (!$slotDetails) {
                        continue;
                    }

                    $timeSlotStart = Carbon::parse($slotDetails['date'] . ' ' . $slotDetails['start_time']);
                    $timeSlotEnd = Carbon::parse($slotDetails['date'] . ' ' . $slotDetails['end_time']);

                    if ($timeSlotEnd->gt($endDateTime) || ($endDateTime->gt($timeSlotStart) && $endDateTime->lt($timeSlotEnd))) {
                        return back()->withErrors([
                            'additional_service_time_slots' => 'Each additional service time slot must end before the pick up time.'
                        ])->withInput();
                    }
                }
            }

            $appointment->date = $startDateTime->toDateString();
            $appointment->start_time = $startDateTime->toTimeString();
            $appointment->end_date = $endDateTime->toDateString();
            $appointment->end_time = $endDateTime->toTimeString();
        } elseif ($timeSlot) {
            $appointment->date = $timeSlot->date;
            $appointment->start_time = $timeSlot->start_time;
            $appointment->end_time = $timeSlot->end_time;

            if (!isBoardingService($service)) {
                $appointment->end_date = null;
            }
        }

        if (isBoardingService($service) && !empty($petIds)) {
            $appointment->estimated_price = $this->calculateFamilyBoardingEstimatedPrice(
                $service,
                $appointment,
                $petIds,
                $additionalServicesByPet,
                (int) $request->customer
            );
        }

        $appointment->metadata = !empty($metadata) ? $metadata : null;

        if ($request->filled('status')) {
            $newStatus = $request->status;
            if (in_array($newStatus, ['cancelled', 'no_show'])) {
                $appointment->status = $newStatus;
                if ($oldStatus !== $newStatus) {
                    appointment_audit_log($appointment->id, "Appointment status changed to " . appointment_status_label($newStatus, $appointment->service) . ".");
                    $this->saveCancellationRecord($appointment, $newStatus);
                    $this->releaseTimeSlots($appointment);
                }
            }
        }

        $isActiveBoardingAppointment = isBoardingService($service)
            && !in_array($appointment->status, ['cancelled', 'no_show']);

        if ($oldRoomId && $oldRoomId !== $newRoomId) {
            $this->releaseCatRoomIfUnused($oldRoomId, $appointment->id);
        }

        if ($newRoomId && $isActiveBoardingAppointment && $roomType === 'space') {
            $this->markCatRoomOutOfService($newRoomId);
        }

        if (!$isActiveBoardingAppointment && $oldRoomId && $oldRoomId === $newRoomId) {
            $this->releaseCatRoomIfUnused($oldRoomId, $appointment->id);
        }

        $appointment->save();
        if ($oldStatus !== $appointment->status && !in_array($appointment->status, ['cancelled', 'no_show'])) {
            $label = $appointment->status === 'checked_in' ? "Appointment is created." : "Appointment status changed to " . appointment_status_label($appointment->status, $appointment->service) . ".";
            appointment_audit_log($appointment->id, $label);
        } elseif ($oldStatus === $appointment->status) {
            appointment_audit_log($appointment->id, "Appointment updated.");
        }

        if ($appointment->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $bookingNotifier->sendCancellation($appointment, Auth::id());
        }

        return redirect()->route('appointments')->with([
            'message' => 'Appointment updated successfully.',
            'status' => 'success'
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
        ]);

        $appointment = Appointment::find($request->appointment_id);
        $appointmentId = $appointment->id;

        $this->releaseCatRoomIfUnused($appointment->cat_room_id, $appointment->id);

        $this->releaseTimeSlots($appointment);

        appointment_audit_log($appointmentId, "Appointment deleted.");
        $appointment->delete();

        return redirect()->route('appointments')->with([
            'message' => 'Appointment deleted successfully.',
            'status' => 'success'
        ]);
    }

    public function confirmPending(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:appointments,id',
            'staff_id' => 'required|exists:users,id',
            'estimated_price' => 'required|numeric|min:0',
        ]);

        // Update the appointment
        $id = $request->id;
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return redirect()->back()->with([
                'message' => 'Appointment not found.',
                'status' => 'fail'
            ]);
        }

        $appointment->staff_id = $request->staff_id;
        $appointment->estimated_price = $request->estimated_price;
        $appointment->status = 'checked_in';
        $appointment->save();

        // Update the time slot to booked if exists
        $timeSlots = TimeSlot::where('service_id', $appointment->service_id)
            ->whereDate('date', $appointment->date)
            ->where(function ($query) use ($appointment) {
                $query->where('start_time', $appointment->start_time)
                      ->orWhere('end_time', $appointment->end_time);
            })->get();
        foreach ($timeSlots as $timeSlot) {
            if ($timeSlot->booked_count < $timeSlot->capacity) {
                $timeSlot->booked_count += 1;
                if ($timeSlot->booked_count == $timeSlot->capacity) {
                    $timeSlot->status = 'full';
                }
            } else {
                $timeSlot->status = 'full';
            }
            $timeSlot->save();
        }

        // Save or update the questionnaire if provided
        $questionnaireData = $request->questionnaire;
        if ($questionnaireData) {
            $questionnaire = Questionnaire::where('appointment_id', $appointment->id)->first();
            if (!$questionnaire) {
                $questionnaire = new Questionnaire;
            }
            $questionnaire->appointment_id = $appointment->id;
            $questionnaire->questions_answers = $questionnaireData;
            $questionnaire->save();
        }

        // Create a check-in record
        $checkIn = Checkin::where('appointment_id', $appointment->id)->first();
        if (!$checkIn) {
            $checkIn = new Checkin;
            $checkIn->appointment_id = $appointment->id;
        }
        // Update appointment start_time if not already set
        if (!$appointment->start_time) {
            $appointment->start_time = Carbon::now()->format('H:i:s');
            $appointment->save();
        }
        $checkIn->save();
        appointment_audit_log($appointment->id, "Appointment is created.");

        return redirect()->route('service-dashboard', ['id' => $appointment->service_id])->with([
            'message' => 'Appointment has been confirmed successfully.',
            'status' => 'success'
        ]);
    }

    public function updateCheckinFlows(Request $request, $id)
    {
        $request->validate([
            'flows' => 'required|array'
        ]);

        $checkIn = Checkin::where('appointment_id', $id)->first();
        $existingFlows = [];
        if ($checkIn && !empty($checkIn->flows)) {
            $decodedExistingFlows = json_decode($checkIn->flows, true);
            $existingFlows = is_array($decodedExistingFlows) ? $decodedExistingFlows : [];
        }

        if (!$checkIn) {
            $checkIn = new Checkin;
            $checkIn->appointment_id = $id;
        }

        $appointment = Appointment::with('pet')->find($id);
        if (!$appointment) {
            return redirect()->back()->with([
                'message' => 'Appointment not found.',
                'status' => 'fail'
            ]);
        }

        if ((isGroomingService($appointment->service) || isAlaCarteService($appointment->service)) && $appointment->pet) {
            $temperamentFields = ['initial_greeting', 'touch_body', 'touch_legs', 'touch_feet', 'touch_tail', 'touch_face', 'touch_nails'];

            $isTemperamentData = false;
            foreach ($temperamentFields as $field) {
                if (isset($request->flows[$field])) {
                    $isTemperamentData = true;
                    break;
                }
            }

            if ($isTemperamentData) {
                $temperamentData = [];
                foreach ($temperamentFields as $field) {
                    if (isset($request->flows[$field])) {
                        $temperamentData[$field] = $request->flows[$field];
                    }
                }

                $initialTemperament = PetInitialTemperament::where('pet_id', $appointment->pet->id)->first();

                if ($initialTemperament) {
                    $initialTemperament->temperament_data = $temperamentData;
                    $initialTemperament->save();
                } else {
                    PetInitialTemperament::create([
                        'pet_id' => $appointment->pet->id,
                        'temperament_data' => $temperamentData
                    ]);
                }
            }
        }

        $checkIn->flows = json_encode($request->flows);
        $checkIn->save();

        if (isBoardingService($appointment->service)) {
            $previousFleaTickAmount = floatval(getBoardingFleaTickBreakdown($appointment, $existingFlows)['amount'] ?? 0);
            $currentFleaTickAmount = floatval(getBoardingFleaTickBreakdown($appointment, $request->flows)['amount'] ?? 0);

            $baseEstimatedPrice = max(0, floatval($appointment->estimated_price ?? 0) - $previousFleaTickAmount);
            $appointment->estimated_price = round($baseEstimatedPrice + $currentFleaTickAmount, 2);
            $appointment->save();
        }

        if (isPrivateTrainingService($appointment->service) && $checkIn->flows) {
            $flows = json_decode($checkIn->flows, true);

            if (!empty($flows['additional_services_link'])) {
                $additionalServicesLink = $flows['additional_services_link'];

                if (is_array($additionalServicesLink)) {
                    $appointment->additional_service_ids = implode(',', $additionalServicesLink);
                } else {
                    $appointment->additional_service_ids = $additionalServicesLink;
                }

                $appointment->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Temperament data saved successfully'
        ]);
    }

    public function confirmCheckedIn(Request $request, $id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return redirect()->back()->with([
                'message' => 'Appointment not found.',
                'status' => 'fail'
            ]);
        }

        $isBoardingService = $appointment->service && isBoardingService($appointment->service);

        if ($isBoardingService) {
            $checkInForAgreement = Checkin::where('appointment_id', $appointment->id)->first();
            $flows = [];

            if ($checkInForAgreement && !empty($checkInForAgreement->flows)) {
                $decodedFlows = json_decode($checkInForAgreement->flows, true);
                $flows = is_array($decodedFlows) ? $decodedFlows : [];
            }

            $isTruthy = function ($value) {
                return $value === true || $value === 'true' || $value === 1 || $value === '1';
            };

            $agreementAccepted = $isTruthy($flows['boarding_agreement_accepted'] ?? null);
            $vetAuthorized = $isTruthy($flows['boarding_vet_authorized'] ?? null);
            $ownerFullName = trim((string) ($flows['boarding_owner_full_name'] ?? ''));
            $signatureData = trim((string) ($flows['boarding_signature_data'] ?? ''));

            if (!$agreementAccepted || !$vetAuthorized || $ownerFullName === '' || $signatureData === '') {
                return redirect()->back()->with([
                    'message' => 'Boarding agreement and owner signature are required before confirming check-in.',
                    'status' => 'fail'
                ])->withInput();
            }

            $boardingPet = $appointment->pet;
            if ($boardingPet) {
                $vaccineValidator = new \App\Services\PetVaccineValidator();
                $vaccineValidation = $vaccineValidator->validate($boardingPet);
                if (!$vaccineValidation['valid']) {
                    return redirect()->back()->with([
                        'message' => 'Cannot confirm check-in: ' . ($vaccineValidation['message'] ?? 'Pet vaccination is not valid.'),
                        'status' => 'fail'
                    ]);
                }
            }
        }

        $validationRules = [
            'staff_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($isBoardingService) {
            $validationRules['estimated_price'] = 'required|numeric|min:0';
            $validationRules['pickup_time'] = 'nullable|string';
        } else {
            $validationRules['estimated_price'] = 'required|numeric|min:0';
            $validationRules['pickup_time'] = 'required|string';
        }

        $request->validate($validationRules);

        if ($request->filled('staff_id')) {
            $appointment->staff_id = $request->staff_id;
        }

        if ($request->filled('estimated_price')) {
            $appointment->estimated_price = $request->estimated_price;
        }

        $appointment->date = $request->date;
        $appointment->start_time = $request->start_time;

        if ($request->filled('pickup_time')) {
            $appointment->end_time = $request->pickup_time;
        }

        // Update appointment status to in_progress
        $appointment->status = 'in_progress';
        $appointment->save();
        appointment_audit_log($appointment->id, "Appointment status changed to " . appointment_status_label('in_progress', $appointment->service) . ".");

        // Update or create check-in record
        $checkIn = Checkin::where('appointment_id', $appointment->id)->first();
        if (!$checkIn) {
            $checkIn = new Checkin;
            $checkIn->appointment_id = $appointment->id;
        }

        $checkIn->date = $request->date;
        $checkIn->notes = $request->notes;
        $checkIn->save();

        if (!$isBoardingService) {
            $process = Process::where('appointment_id', $appointment->id)->first();
            if (!$process) {
                $process = new Process;
                $process->appointment_id = $appointment->id;
            }
            $process->date = $appointment->date;
            $process->start_time = $appointment->start_time;
            $process->save();
        }

        return redirect()->route('service-dashboard', ['id' => $appointment->service_id])->with([
            'message' => 'Appointment has been started successfully.',
            'status' => 'success'
        ]);
    }

    public function viewCalendar(Request $request)
    {
        $appointmentsQuery = Appointment::with(['pet', 'customer.profile', 'service']);

        $appointments = $appointmentsQuery->get();

        $expandedAppointments = collect();

        $appointments->each(function ($appointment) use (&$expandedAppointments) {
            $appointment->pet_name = $appointment->pet->name ?? '';

            $profile = optional(optional($appointment->customer)->profile);
            $firstName = $profile->first_name ?? '';
            $lastName = $profile->last_name ?? '';
            $appointment->customer_name = trim($firstName . ' ' . $lastName);

            $appointment->service_name = $appointment->service->name ?? '';

            if (isGroupClassService($appointment->service)) {
                $appointment->class_name = optional(GroupClass::find($appointment->metadata['group_class_ids'] ?? null))->name ?? '';
            }

            // Boarding: expand to all days in range
            if ((strtolower($appointment->service_name) === 'boarding' || strtolower($appointment->service_name) === 'package') && $appointment->date && $appointment->end_date) {
                try {
                    $start = \Carbon\Carbon::parse($appointment->date);
                    $end = \Carbon\Carbon::parse($appointment->end_date);
                } catch (\Exception $e) {
                    $expandedAppointments->push($appointment);
                    return;
                }
                if ($end->lessThan($start)) {
                    $expandedAppointments->push($appointment);
                    return;
                }
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $clone = clone $appointment;
                    $clone->date = $date->format('Y-m-d');
                    // For boarding and package, do not use end_time for daily display

                    $clone->start_time = null; 
                    $clone->end_time = null; 
                    $expandedAppointments->push($clone);
                }

                // for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                //     $clone = clone $appointment;
                //     $clone->date = $date->format('Y-m-d');

                //     if ($date->eq($end)) {
                //         // Last day
                //         $clone->start_time = "08:00:00";
                //         $clone->end_time = $appointment->end_time;
                //     } else {
                //         // Other days
                //         $clone->start_time = null;
                //         $clone->end_time = null;
                //     }

                //     $expandedAppointments->push($clone);
                // }
                
            } else {
                // All other services: single entry
                $expandedAppointments->push($appointment);
            }
        });

        return view('appointments.calendar', [
            'appointments' => $expandedAppointments,
        ]);
    }

    public function updateProcessFlows(Request $request, $id)
    {
        $request->validate([
            'flows' => 'required|array'
        ]);

        $workflowDate = $request->input('workflow_date');
        $serviceId = $request->input('service_id');

        if ($serviceId && $workflowDate) {
            $process = Process::where('appointment_id', $id)
                ->where('date', $workflowDate)
                ->where('detail_id', $serviceId)
                ->first();

            if (!$process) {
                $process = Process::where('appointment_id', $id)
                    ->where('date', $workflowDate)
                    ->whereNull('detail_id')
                    ->where(function($query) {
                        $query->whereNull('flows')
                              ->orWhere('flows', '')
                              ->orWhereRaw("(flows IS NOT NULL AND JSON_EXTRACT(flows, '$.service_id') IS NULL)");
                    })
                    ->first();
            }

            if (!$process) {
                $process = new Process;
                $process->appointment_id = $id;
                $process->date = $workflowDate;
                $process->detail_id = $serviceId;
            } else {
                if (!$process->detail_id) {
                    $process->detail_id = $serviceId;
                }
            }

            if ($request->has('start_time')) {
                $process->start_time = $request->input('start_time');
            }
            if ($request->has('pickup_time')) {
                $process->pickup_time = $request->input('pickup_time');
            }
            if ($request->has('notes')) {
                $process->notes = $request->input('notes');
            }
            if ($request->has('staff_id')) {
                $process->staff_id = $request->input('staff_id');
            }

            $existingFlows = $process->flows ? json_decode($process->flows, true) : [];
            if (!is_array($existingFlows)) {
                $existingFlows = [];
            }
            $existingFlows = array_merge($existingFlows, $request->flows);
            $process->flows = json_encode($existingFlows);
        } elseif ($workflowDate) {
            $process = Process::where('appointment_id', $id)
                ->where('date', $workflowDate)
                ->first();

            if (!$process) {
                $process = new Process;
                $process->appointment_id = $id;
                $process->date = $workflowDate;
            }

            $process->flows = json_encode($request->flows);

            if ($request->has('staff_id')) {
                $process->staff_id = $request->input('staff_id');
            }
        } else {
            $process = Process::where('appointment_id', $id)->first();
            if (!$process) {
                $process = new Process;
                $process->appointment_id = $id;
            }

            $existingFlows = $process->flows ? json_decode($process->flows, true) : [];
            if (!is_array($existingFlows)) {
                $existingFlows = [];
            }

            $existingFlows = array_merge($existingFlows, $request->flows);
            $process->flows = json_encode($existingFlows);

            if ($request->has('staff_id')) {
                $process->staff_id = $request->input('staff_id');
            }
        }

        $process->save();

        $appointment = Appointment::find($id);
        $updatedRemainingDays = null;
        if ($appointment && $appointment->metadata && isset($appointment->metadata['customer_package_id'])) {
            $customerPackageId = $appointment->metadata['customer_package_id'];
            $customerPackage = CustomerPackage::find($customerPackageId);
            
            if ($customerPackage) {
                $maxProcessCount = Process::where('appointment_id', $id)
                    ->whereNotNull('date')
                    ->whereNotNull('detail_id')
                    ->selectRaw('detail_id, COUNT(*) as count')
                    ->groupBy('detail_id')
                    ->get()
                    ->max('count') ?? 0;
                
                $uniqueDatesCount = $maxProcessCount;
                
                $newRemainingDays = max(0, $customerPackage->original_days - $uniqueDatesCount);
                
                $customerPackage->remaining_days = $newRemainingDays;
                $customerPackage->save();
                
                $updatedRemainingDays = $newRemainingDays;
            }
        }

        $response = [
            'status' => true,
            'success' => true,
            'message' => 'Process data saved successfully'
        ];
        
        if ($updatedRemainingDays !== null) {
            $response['remaining_days'] = $updatedRemainingDays;
        }
        
        return response()->json($response);
    }

    public function getProcessFlows(Request $request, $id)
    {
        $appointment = Appointment::with('service')->find($id);

        if ($request->input('get_used_dates')) {
            $usedDates = Process::where('appointment_id', $id)
                ->whereNotNull('date')
                ->select('date', 'detail_id')
                ->groupBy('date', 'detail_id')
                ->get()
                ->pluck('date')
                ->unique()
                ->values()
                ->toArray();
            
            return response()->json([
                'used_dates' => $usedDates
            ]);
        }

        $workflowDate = $request->input('date');
        $serviceId = $request->input('service_id'); // For package appointments

        if ($workflowDate) {
            // For package appointments, find process by service_id and date
            if ($serviceId) {
                $process = Process::where('appointment_id', $id)
                    ->where('date', $workflowDate)
                    ->where('detail_id', $serviceId)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $process = Process::where('appointment_id', $id)
                    ->where('date', $workflowDate)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            if (! $process) {
                return response()->json([
                    'flows' => [],
                    'staff_id' => null,
                    'staff_name' => null,
                    'staff_names' => [],
                    'start_time' => null,
                    'pickup_time' => null,
                    'notes' => null
                ]);
            }

            $flows = [];
            if ($process->flows) {
                $decodedFlows = json_decode($process->flows, true);
                if (is_array($decodedFlows)) {
                    $flows = $decodedFlows;
                }
            }

            if ($appointment && isBoardingService($appointment->service) && ! $serviceId) {
                $flows = $this->mergeBoardingWorkflowFlowsForDate($workflowDate, (int) $appointment->service_id, $flows);
            }

            // Get staff name
            $staffName = 'N/A';
            if ($process->staff_id) {
                $staff = User::with('profile')->find($process->staff_id);
                if ($staff && $staff->profile) {
                    $staffName = trim(($staff->profile->first_name ?? '') . ' ' . ($staff->profile->last_name ?? ''));
                    if (empty($staffName)) {
                        $staffName = $staff->name ?? 'N/A';
                    }
                } elseif ($staff) {
                    $staffName = $staff->name ?? 'N/A';
                }
            }

            $staffSignOffIds = [];
            foreach ($flows as $stepData) {
                if (isset($stepData['staff_sign_off']) && is_array($stepData['staff_sign_off'])) {
                    foreach ($stepData['staff_sign_off'] as $uid) {
                        if ($uid !== null && $uid !== '') {
                            $staffSignOffIds[] = is_numeric($uid) ? (int) $uid : $uid;
                        }
                    }
                }
            }
            $staffSignOffIds = array_unique($staffSignOffIds);
            $staffNames = [];
            if (!empty($staffSignOffIds)) {
                $users = User::with('profile')->whereIn('id', $staffSignOffIds)->get();
                foreach ($users as $u) {
                    $name = 'N/A';
                    if ($u->profile) {
                        $name = trim(($u->profile->first_name ?? '') . ' ' . ($u->profile->last_name ?? ''));
                        if ($name === '') {
                            $name = $u->name ?? 'N/A';
                        }
                    } else {
                        $name = $u->name ?? 'N/A';
                    }
                    $staffNames[(string) $u->id] = $name;
                }
            }

            return response()->json([
                'flows' => $flows,
                'staff_id' => $process->staff_id,
                'staff_name' => $staffName,
                'staff_names' => $staffNames,
                'start_time' => $process->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $process->start_time)->format('H:i') : null,
                'pickup_time' => $process->pickup_time ? \Carbon\Carbon::createFromFormat('H:i:s', $process->pickup_time)->format('H:i') : null,
                'notes' => $process->notes
            ]);
        } else {
            // If no date specified, get the latest process for the appointment
            if ($serviceId) {
                $process = Process::where('appointment_id', $id)
                    ->where('detail_id', $serviceId)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $process = Process::where('appointment_id', $id)
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            if (!$process) {
                return response()->json([
                    'flows' => [],
                    'staff_id' => null,
                    'staff_name' => null,
                    'staff_names' => [],
                    'start_time' => null,
                    'pickup_time' => null,
                    'notes' => null
                ]);
            }

            $flows = [];
            if ($process->flows) {
                $decodedFlows = json_decode($process->flows, true);
                if (is_array($decodedFlows)) {
                    $flows = $decodedFlows;
                }
            }

            // Get staff name
            $staffName = 'N/A';
            if ($process->staff_id) {
                $staff = User::with('profile')->find($process->staff_id);
                if ($staff && $staff->profile) {
                    $staffName = trim(($staff->profile->first_name ?? '') . ' ' . ($staff->profile->last_name ?? ''));
                    if (empty($staffName)) {
                        $staffName = $staff->name ?? 'N/A';
                    }
                } elseif ($staff) {
                    $staffName = $staff->name ?? 'N/A';
                }
            }

            $staffSignOffIds = [];
            foreach ($flows as $stepData) {
                if (isset($stepData['staff_sign_off']) && is_array($stepData['staff_sign_off'])) {
                    foreach ($stepData['staff_sign_off'] as $uid) {
                        if ($uid !== null && $uid !== '') {
                            $staffSignOffIds[] = is_numeric($uid) ? (int) $uid : $uid;
                        }
                    }
                }
            }
            $staffSignOffIds = array_unique($staffSignOffIds);
            $staffNames = [];
            if (!empty($staffSignOffIds)) {
                $users = User::with('profile')->whereIn('id', $staffSignOffIds)->get();
                foreach ($users as $u) {
                    $name = 'N/A';
                    if ($u->profile) {
                        $name = trim(($u->profile->first_name ?? '') . ' ' . ($u->profile->last_name ?? ''));
                        if ($name === '') {
                            $name = $u->name ?? 'N/A';
                        }
                    } else {
                        $name = $u->name ?? 'N/A';
                    }
                    $staffNames[(string) $u->id] = $name;
                }
            }

            return response()->json([
                'flows' => $flows,
                'staff_id' => $process->staff_id,
                'staff_name' => $staffName,
                'staff_names' => $staffNames,
                'start_time' => $process->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $process->start_time)->format('H:i') : null,
                'pickup_time' => $process->pickup_time ? \Carbon\Carbon::createFromFormat('H:i:s', $process->pickup_time)->format('H:i') : null,
                'notes' => $process->notes
            ]);
        }
    }

    private function mergeBoardingWorkflowFlowsForDate(string $workflowDate, int $serviceId, array $baseFlows): array
    {
        $mergedFlows = $baseFlows;

        $peerProcesses = Process::with('appointment.service')
            ->where('date', $workflowDate)
            ->whereHas('appointment', function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        foreach ($peerProcesses as $peerProcess) {
            if (! $peerProcess->appointment || ! isBoardingService($peerProcess->appointment->service)) {
                continue;
            }

            $peerFlows = $peerProcess->flows ? json_decode($peerProcess->flows, true) : [];
            if (! is_array($peerFlows)) {
                continue;
            }

            $mergedFlows = $this->mergeBoardingWorkflowArrays($mergedFlows, $peerFlows);
        }

        return $mergedFlows;
    }

    private function mergeBoardingWorkflowArrays(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            if ($key === 'selected_pet_ids' && is_array($value) && is_array($base[$key])) {
                $base[$key] = array_values(array_unique(array_merge($base[$key], $value), SORT_REGULAR));
                continue;
            }

            if (is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeBoardingWorkflowArrays($base[$key], $value);
                continue;
            }

            if ($base[$key] === null || $base[$key] === '' || $base[$key] === []) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public function confirmInProgress(Request $request, $id)
    {
        // Find the appointment
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return redirect()->back()->with([
                'message' => 'Appointment not found.',
                'status' => 'fail'
            ]);
        }

        $isAlaCarte = isAlaCarteService($appointment->service);
        $isBoarding = isBoardingService($appointment->service);
        $isPackage = $appointment->service && isPackageService($appointment->service);

        if (!$isAlaCarte && !$isBoarding && !$isPackage) {
            $request->validate([
                'staff_id' => 'required|exists:users,id',
                'date' => 'required|date',
                'start_time' => 'required|string',
                'pickup_time' => 'required|string',
                'notes' => 'nullable|string|max:1000',
            ]);
            $appointment->staff_id = $request->staff_id;
        }

        // Update appointment status to completed
        $appointment->status = 'completed';
        $appointment->save();
        appointment_audit_log($appointment->id, "Appointment status changed to " . appointment_status_label('completed') . ".");

        if (!$isAlaCarte && !$isBoarding && !$isPackage) {
            // Update or create process record
            $process = Process::where('appointment_id', $appointment->id)->first();
            if (!$process) {
                $process = new Process;
                $process->appointment_id = $appointment->id;
            }

            $process->staff_id = $request->staff_id;
            $process->date = $request->date;
            $process->start_time = $request->start_time;
            $process->pickup_time = $request->pickup_time;
            $process->notes = $request->notes;
            $process->save();
        }

        return redirect()->route('service-dashboard', ['id' => $appointment->service_id])->with([
            'message' => 'Grooming process has been completed successfully.',
            'status' => 'success'
        ]);
    }

    public function saveInvoice(Request $request, $id)
    {
        $request->validate([
            'invoice_number' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'issued_at' => 'nullable|date',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'status' => 'required|in:draft,sent,paid,void,finalized',
            'notes' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'discount_title' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,check,cc',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.'
            ], 404);
        }

        // Check if invoice already exists for this appointment
        $invoice = Invoice::where('appointment_id', $appointment->id)->first();
        $isExistingInvoice = (bool) $invoice;
        if ($isExistingInvoice) {
            $currentInvoiceStatus = strtolower((string) ($invoice->status ?? ''));
            $isInvoiceLocked = in_array($currentInvoiceStatus, ['paid', 'finalized'], true);
            if ($isInvoiceLocked && !$this->canEditLockedInvoice(Auth::user())) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice is finalized/paid and cannot be edited.'
                ], 403);
            }
        }

        if (!$invoice) {
            $invoice = new Invoice;
            $invoice->appointment_id = $appointment->id;
        }

        // Check if invoice number is unique (except for current invoice)
        $existingInvoice = Invoice::where('invoice_number', $request->invoice_number)
            ->where('id', '!=', $invoice->id ?? 0)
            ->first();
        if ($existingInvoice) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice number already exists.'
            ], 422);
        }

        $boardingPricing = isBoardingService($appointment->service)
            ? getBoardingPricingBreakdown($appointment, null, $appointment->service)
            : null;
        $resolvedDiscountAmount = floatval($request->discount_amount ?? 0);
        $resolvedDiscountTitle = $request->discount_title ?? '';

        if (($boardingPricing['family_discount_amount'] ?? 0) > 0) {
            $resolvedDiscountAmount = floatval($boardingPricing['family_discount_amount']);
            $resolvedDiscountTitle = $boardingPricing['family_discount_title'] ?? 'Multi-Pet Discount';
        }

        $invoice->customer_id = $appointment->customer_id;
        $invoice->invoice_number = $request->invoice_number;
        $invoice->first_name = $request->first_name;
        $invoice->last_name = $request->last_name;
        $invoice->email = $request->email;
        $invoice->issued_at = $request->issued_at ? Carbon::parse($request->issued_at) : null;
        $invoice->due_date = $request->due_date ? Carbon::parse($request->due_date) : null;
        if ($request->status !== "draft") {
            $invoice->discount_amount = $resolvedDiscountAmount;
            $invoice->discount_title = $resolvedDiscountTitle;
        }

        if ($request->status === 'paid' && !$request->paid_at) {
            $invoice->paid_at = Carbon::now();
        } else {
            $invoice->paid_at = $request->paid_at ? Carbon::parse($request->paid_at) : null;
        }
        $invoice->status = $request->status;
        $invoice->notes = $request->notes;
        $invoice->save();
        appointment_audit_log($appointment->id, "Invoice status changed to " . ucfirst($invoice->status) . ". Invoice #{$invoice->invoice_number}.");

        // Save invoice items
        $items = is_array($request->items) ? $request->items : [];
        $items = dedupeBoardingAutoFeeInvoiceItems($items);

        $itemsForEmail = [];
        // Always delete existing items so that removed items are properly cleared.
        InvoiceItem::where('invoice_id', $invoice->id)->delete();
        if (is_array($items)) {
            // Add new items
            foreach ($items as $itemData) {
                $item = new InvoiceItem;
                $item->invoice_id = $invoice->id;
                $item->item_name = $itemData['description'] ?? '';
                $item->price = $itemData['price'] ?? 0;
                $item->item_type = $itemData['type'] ?? 'service';
                $item->save();
                $itemsForEmail[] = [
                    'description' => $itemData['description'] ?? '',
                    'price' => $itemData['price'] ?? 0
                ];
            }
        }

        $invoice->load('items');
        $invoiceItemSummary = $this->summarizeInvoiceItemsFromInvoice($invoice);
        $appliedDiscountAmount = floatval($invoice->discount_amount ?? 0);
        $invoiceSubtotal = max(0, $invoiceItemSummary['total_service_price'] - $appliedDiscountAmount + $invoiceItemSummary['total_inventory_amount']);

        $appointment->estimated_price = round($invoiceSubtotal, 2);
        $appointment->save();

        $discountInfo = [
            'discount_title' => $resolvedDiscountTitle,
            'discount_amount' => $resolvedDiscountAmount,
        ];

        if ($request->status === 'paid' && $request->payment_amount && $request->payment_method) {
            $transaction = new Transaction;
            $transaction->appointment_id = $appointment->id;
            $transaction->invoice_id = $invoice->id;
            $transaction->user_id = $appointment->customer_id;
            $transaction->tran_date = $invoice->paid_at ?: Carbon::now();
            $transaction->amount = $request->payment_amount;
            $transaction->payment_method = $request->payment_method;
            $transaction->notes = $request->payment_notes;
            $transaction->save();
        }

        $emailFailed = false;
        if ($request->status === 'sent' || ($request->status === 'paid' && $request->payment_amount)) {
            try {
                $this->sendInvoiceEmail($invoice, $appointment, $items, $discountInfo);
            } catch (\Throwable $e) {
                $emailFailed = true;
                Log::error('Failed to send invoice email after saving invoice.', [
                    'appointment_id' => $appointment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_status' => $request->status,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $responseMessage = $request->status === 'sent'
            ? 'Invoice saved and sent successfully.'
            : ($request->status === 'paid' && $request->payment_amount
                ? 'Invoice saved and payment recorded successfully.'
                : 'Invoice saved successfully.');

        if ($emailFailed) {
            $responseMessage = 'Invoice saved successfully, but sending email failed.';
        }

        return response()->json([
            'status' => true,
            'message' => $responseMessage,
            'email_failed' => $emailFailed,
            'invoice_id' => $invoice->id
        ]);
    }

    private function canEditLockedInvoice(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->roles()->whereRaw('LOWER(title) in (?, ?)', ['owner', 'admin'])->exists();
    }

    private function sendInvoiceEmail($invoice, $appointment, $items, $discountInfo = [])
    {
        $invoice->loadMissing('items');
        $invoiceItemSummary = $this->summarizeInvoiceItemsFromInvoice($invoice);

        $mainServiceItems = $invoiceItemSummary['main_service_items'];
        $additionalServiceItems = [];
        $inventoryItems = $invoiceItemSummary['inventory_items'];
        $additionalServicesGroupedByPet = [];
        $totalServicePrice = $invoiceItemSummary['total_service_price'];
        $totalInventoryAmount = $invoiceItemSummary['total_inventory_amount'];
        $fleaTickFee = 0;
        $lateCheckoutFee = 0;
        $shouldApplyLateCheckoutFee = false;

        $discountAmount = floatval($discountInfo['discount_amount'] ?? 0);
        $subtotalAmount = max(0, $totalServicePrice - $discountAmount + $totalInventoryAmount);
        $stateTaxRate = isBoardingService($appointment->service) ? floatval(config('billing.state_tax_rate', 7)) : 0;
        $stateTaxAmount = round($subtotalAmount * ($stateTaxRate / 100), 2);
        $totalAmount = $subtotalAmount + $stateTaxAmount;

        $emailData = [
            'invoice_number' => $invoice->invoice_number,
            'first_name' => $invoice->first_name,
            'last_name' => $invoice->last_name,
            'issued_at' => $invoice->issued_at,
            'due_date' => $invoice->due_date,
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'main_service_items' => $mainServiceItems,
            'additional_service_items' => $additionalServiceItems,
            'additional_services_grouped_by_pet' => $additionalServicesGroupedByPet,
            'inventory_items' => $inventoryItems,
            'total_service_price' => $totalServicePrice,
            'estimated_price' => $totalServicePrice,
            'discount_title' => $discountInfo['discount_title'] ?? null,
            'discount_amount' => $discountAmount,
            'total_inventory_amount' => $totalInventoryAmount,
            'subtotal_amount' => $subtotalAmount,
            'state_tax_rate' => $stateTaxRate,
            'state_tax_amount' => $stateTaxAmount,
            'flea_tick_fee' => 0,
            'flea_tick_checked_pet_count' => 0,
            'late_checkout_hours' => 0,
            'late_checkout_daycare_fee' => 0,
            'total' => $totalAmount,
            'total_amount' => $totalAmount
        ];

        Mail::to($invoice->email)->send(new InvoiceMail($emailData));
    }

    private function summarizeInvoiceItemsFromInvoice(Invoice $invoice): array
    {
        $invoiceItems = $invoice->relationLoaded('items')
            ? $invoice->items
            : $invoice->items()->get();
        $invoiceItems = collect(dedupeBoardingAutoFeeInvoiceItems($invoiceItems))->values();

        $mainServiceItems = [];
        $inventoryItems = [];
        $totalServicePrice = 0;
        $totalInventoryAmount = 0;

        foreach ($invoiceItems as $invoiceItem) {
            $itemType = strtolower(trim((string) ($invoiceItem->item_type ?? 'service')));
            $itemDescription = trim((string) ($invoiceItem->item_name ?? ''));
            $itemPrice = floatval($invoiceItem->price ?? 0);

            if ($itemType === 'inventory') {
                $inventoryItems[] = [
                    'description' => $itemDescription,
                    'price' => $itemPrice,
                ];
                $totalInventoryAmount += $itemPrice;
                continue;
            }

            $mainServiceItems[] = [
                'description' => $itemDescription,
                'price' => $itemPrice,
            ];
            $totalServicePrice += $itemPrice;
        }

        return [
            'main_service_items' => $mainServiceItems,
            'additional_service_items' => [],
            'inventory_items' => $inventoryItems,
            'total_service_price' => $totalServicePrice,
            'total_inventory_amount' => $totalInventoryAmount,
        ];
    }

    private function resolveBoardingLateCheckoutDaycareFee(Appointment $appointment): array
    {
        $breakdown = getBoardingLateCheckoutDaycareBreakdown($appointment, null, 1);

        return [
            'scheduled_pickup_at' => $breakdown['scheduled_pickup_at'] ?? null,
            'actual_checkout_at' => $breakdown['actual_checkout_at'] ?? null,
            'is_late' => (($breakdown['late_seconds'] ?? 0) > 0),
            'late_seconds' => intval($breakdown['late_seconds'] ?? 0),
            'late_hours' => floatval($breakdown['late_hours'] ?? 0),
            'threshold_hours' => intval($breakdown['threshold_hours'] ?? 1),
            'daycare_price' => floatval($breakdown['daycare_price'] ?? 0),
            'daycare_duration' => floatval($breakdown['daycare_duration'] ?? 0),
            'hourly_rate' => floatval($breakdown['hourly_rate'] ?? 0),
            'late_fee' => floatval($breakdown['fee'] ?? 0),
            'should_apply_fee' => (bool) ($breakdown['should_apply_fee'] ?? false),
        ];
    }

    private function isFleaTickFeeDescription($description): bool
    {
        $normalized = strtolower(trim((string) $description));
        return in_array($normalized, ['flea/tick fee', 'flea/tick detection fee'], true);
    }

    private function isLateCheckoutDaycareFeeDescription($description): bool
    {
        $normalized = strtolower(trim((string) $description));
        return in_array($normalized, ['late checkout daycare fee', 'late checkout fee'], true);
    }

    private function normalizeBoardingSpecialFeeItems(Appointment $appointment, array $items, array $checkinFlows = [], ?array $lateCheckoutData = null): array
    {
        if (!isBoardingService($appointment->service)) {
            return $items;
        }

        $fleaTickFee = floatval(getBoardingFleaTickBreakdown($appointment, $checkinFlows)['amount'] ?? 0);
        $lateCheckoutData = $lateCheckoutData ?: $this->resolveBoardingLateCheckoutDaycareFee($appointment);
        $lateCheckoutFee = floatval($lateCheckoutData['late_fee'] ?? 0);
        $shouldApplyLateCheckoutFee = (bool) ($lateCheckoutData['should_apply_fee'] ?? false);

        $normalizedItems = [];
        $hasFleaTickItem = false;
        $hasLateCheckoutDaycareFeeItem = false;

        foreach ($items as $itemData) {
            $description = trim((string) ($itemData['description'] ?? ''));
            $isFleaTickItem = $this->isFleaTickFeeDescription($description);
            $isLateCheckoutDaycareFeeItem = $this->isLateCheckoutDaycareFeeDescription($description);

            if ($isFleaTickItem) {
                if ($fleaTickFee <= 0 || $hasFleaTickItem) {
                    continue;
                }

                $hasFleaTickItem = true;
                $itemData['description'] = 'Flea/Tick Detection Fee';
                $itemData['price'] = $fleaTickFee;
                $itemData['type'] = 'service';
            }

            if ($isLateCheckoutDaycareFeeItem) {
                if (!$shouldApplyLateCheckoutFee || $lateCheckoutFee <= 0 || $hasLateCheckoutDaycareFeeItem) {
                    continue;
                }

                $hasLateCheckoutDaycareFeeItem = true;
                $itemData['description'] = 'Late Checkout Daycare Fee';
                $itemData['price'] = $lateCheckoutFee;
                $itemData['type'] = 'service';
            }

            $normalizedItems[] = $itemData;
        }

        if ($fleaTickFee > 0 && !$hasFleaTickItem) {
            $normalizedItems[] = [
                'description' => 'Flea/Tick Detection Fee',
                'price' => $fleaTickFee,
                'type' => 'service',
            ];
        }

        if ($shouldApplyLateCheckoutFee && $lateCheckoutFee > 0 && !$hasLateCheckoutDaycareFeeItem) {
            $normalizedItems[] = [
                'description' => 'Late Checkout Daycare Fee',
                'price' => $lateCheckoutFee,
                'type' => 'service',
            ];
        }

        return $normalizedItems;
    }

    public function sendCustomerEmail(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $appointment = Appointment::with('customer.profile')->find($id);
        if (!$appointment || !$appointment->customer || empty($appointment->customer->email)) {
            return response()->json([
                'status' => false,
                'message' => 'Customer email not found for this appointment.'
            ], 404);
        }

        $customerEmail = $appointment->customer->email;
        $customerName = trim((($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? '')));
        $customerName = $customerName ?: ($appointment->customer->name ?? 'Customer');
        $body = trim($request->message);

        try {
            $senderName = 'PawPrints Admin Team';
            if (Auth::check()) {
                $authUser = Auth::user()->load('profile');
                $profileName = trim((($authUser->profile->first_name ?? '') . ' ' . ($authUser->profile->last_name ?? '')));
                $senderName = $profileName ?: ($authUser->name ?? $senderName);
            }

            $subject = 'Message from PawPrints Admin';
            $messageData = [
                'subject' => $subject,
                'customer_name' => $customerName,
                'message' => $body,
                'sender_name' => $senderName,
            ];

            Mail::to($customerEmail)->send(new AdminCustomerMessage($messageData));

            return response()->json([
                'status' => true,
                'message' => 'Email sent successfully.'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send email. Please try again.'
            ], 500);
        }
    }

    public function sendCustomerNotification(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $appointment = Appointment::with('customer')->find($id);
        if (!$appointment || !$appointment->customer) {
            return response()->json([
                'status' => false,
                'message' => 'Customer not found for this appointment.'
            ], 404);
        }

        $notification = new Notification;
        $notification->user_id = $appointment->customer->id;
        $notification->sender_id = Auth::id();
        $notification->title = 'New Message from Admin';
        $notification->message = trim($request->message);
        $notification->type = 'admin_message';
        $notification->is_read = false;
        $notification->save();

        return response()->json([
            'status' => true,
            'message' => 'Notification sent successfully.'
        ], 200);
    }

    public function confirmCompleted(Request $request, $id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Appointment not found.'
            ], 404);
        }

        $isPackageAppointment = $appointment->service && isPackageService($appointment->service);

        $validationRules = [
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'flows' => 'nullable|string',
            'pictures.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ];

        if ($isPackageAppointment) {
            $validationRules['pickup_time'] = 'required|string';
        }

        $request->validate($validationRules);

        // Update or create checkout record
        $checkout = Checkout::where('appointment_id', $appointment->id)->first();
        if (!$checkout) {
            $checkout = new Checkout;
            $checkout->appointment_id = $appointment->id;
        }

        $existingCheckoutFlows = [];
        if (!empty($checkout->flows)) {
            $decodedExistingCheckoutFlows = is_array($checkout->flows)
                ? $checkout->flows
                : json_decode($checkout->flows, true);
            $existingCheckoutFlows = is_array($decodedExistingCheckoutFlows) ? $decodedExistingCheckoutFlows : [];
        }
        $previousAppliedLateCheckoutFee = floatval($existingCheckoutFlows['applied_late_checkout_daycare_fee'] ?? 0);

        $checkout->date = $request->date;
        $checkout->notes = $request->notes;

        $actualCheckoutAt = null;
        if ($request->filled('actual_checkout_at')) {
            try {
                $actualCheckoutAt = Carbon::parse($request->actual_checkout_at);
            } catch (\Throwable $e) {
                $actualCheckoutAt = null;
            }
        }

        if (!$actualCheckoutAt) {
            $effectivePickupTime = $request->pickup_time ?: now()->format('H:i:s');
            try {
                $actualCheckoutAt = Carbon::parse($request->date . ' ' . $effectivePickupTime);
            } catch (\Throwable $e) {
                $actualCheckoutAt = Carbon::now();
            }
        }

        // For package appointments, save pickup time to appointment end_time
        if ($isPackageAppointment && $request->filled('pickup_time')) {
            $appointment->end_time = $request->pickup_time;
            $appointment->save();
        }

        // Handle flows and pictures
        $flowsData = $request->flows ? json_decode($request->flows, true) : [];
        if (!is_array($flowsData)) {
            $flowsData = [];
        }
        $flowsData['actual_checkout_at'] = $actualCheckoutAt->format('Y-m-d H:i:s');
        $flowsData['actual_checkout_date'] = $actualCheckoutAt->toDateString();
        $flowsData['actual_checkout_time'] = $actualCheckoutAt->format('H:i:s');

        // Handle picture uploads
        $pictureNames = [];
        if ($request->hasFile('pictures')) {
            foreach ($request->file('pictures') as $picture) {
                $fileName = time() . '_' . uniqid() . '.' . $picture->getClientOriginalExtension();
                $picture->storeAs('checkouts', $fileName, 'public');
                $pictureNames[] = $fileName;
            }
        }

        // Add pictures to flows
        if (!empty($pictureNames)) {
            $flowsData['pictures'] = $pictureNames;
        }

        // Update pet behavior selection when provided from checkout form
        if ($appointment->pet_id && isset($flowsData['behavior_ids']) && is_array($flowsData['behavior_ids'])) {
            $behaviorIds = collect($flowsData['behavior_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->toArray();

            PetProfile::where('id', $appointment->pet_id)->update([
                'pet_behavior_id' => $behaviorIds,
            ]);
        }

        $appliedLateCheckoutDaycareFee = 0;
        if (isBoardingService($appointment->service) && ($appointment->status ?? null) === 'completed') {
            $checkoutForLateFee = clone $checkout;
            $checkoutForLateFee->flows = $flowsData;
            $lateCheckoutBreakdown = getBoardingLateCheckoutDaycareBreakdown($appointment, $checkoutForLateFee, 1);
            $appliedLateCheckoutDaycareFee = floatval($lateCheckoutBreakdown['fee'] ?? 0);
        }
        $flowsData['applied_late_checkout_daycare_fee'] = $appliedLateCheckoutDaycareFee;

        $checkout->flows = json_encode($flowsData);
        $checkout->save();

        if (isBoardingService($appointment->service) && ($appointment->status ?? null) === 'completed') {
            $storedEstimatedPrice = floatval($appointment->estimated_price ?? 0);
            $baseEstimatedPrice = max(0, $storedEstimatedPrice - $previousAppliedLateCheckoutFee);
            $appointment->estimated_price = round($baseEstimatedPrice + $appliedLateCheckoutDaycareFee, 2);
            $appointment->save();
        }

        $invoice = Invoice::where('appointment_id', $appointment->id)->first();
        $isInvoicePaid = $invoice && $invoice->status === 'paid';

        if (isGroupClassService($appointment->service) || isPackageService($appointment->service) || $isInvoicePaid) {
            $appointment->status = 'finished';
            $appointment->save();
            appointment_audit_log($appointment->id, "Appointment status changed to " . appointment_status_label('finished') . ".");
        }

        if ($isPackageAppointment && $appointment->metadata && isset($appointment->metadata['package_id'])) {
            $packageId = $appointment->metadata['package_id'];
            CustomerPackage::where('customer_id', $appointment->customer_id)
                ->where('package_id', $packageId)
                ->where('remaining_days', 0)
                ->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout completed successfully.'
        ]);
    }

    public function saveAlaCarteProcess(Request $request, $id)
    {
        $request->validate([
            'secondary_service_id' => 'required|exists:services,id',
            'staff_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|string',
            'pickup_time' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.'
            ], 404);
        }

        $process = Process::where('appointment_id', $id)
            ->where('date', $request->date)
            ->where('detail_id', null)
            ->first();

        if (!$process) {
            $process = new Process;
            $process->appointment_id = $id;
        }

        $process->detail_id = $request->secondary_service_id;
        $process->staff_id = $request->staff_id;
        $process->date = $request->date;
        $process->start_time = $request->start_time;
        $process->pickup_time = $request->pickup_time;
        $process->notes = $request->notes;

        $process->save();

        return response()->json([
            'status' => true,
            'message' => 'Process saved successfully.'
        ]);
    }

    private function saveCancellationRecord(Appointment $appointment, string $status)
    {
        $existingRecord = AppointmentCancellation::where('appointment_id', $appointment->id)
            ->where('type', $status === 'cancelled' ? 'cancel' : 'noshow')
            ->first();

        if (!$existingRecord) {
            AppointmentCancellation::create([
                'appointment_id' => $appointment->id,
                'customer_id' => $appointment->customer_id,
                'service_id' => $appointment->service_id,
                'cancelled_by' => Auth::id(),
                'type' => $status === 'cancelled' ? 'cancel' : 'noshow',
                'occurred_at' => Carbon::now(),
            ]);
        }
    }

    private function releaseTimeSlots(Appointment $appointment)
    {
        $metadata = is_array($appointment->metadata) ? $appointment->metadata : [];
        $releasedAdditionalServiceSlots = false;

        if (!empty($metadata['additional_service_time_slots_by_pet']) && is_array($metadata['additional_service_time_slots_by_pet'])) {
            $slotReleaseCounts = [];

            foreach ($metadata['additional_service_time_slots_by_pet'] as $petId => $serviceSlots) {
                if (!is_array($serviceSlots)) {
                    continue;
                }

                foreach ($serviceSlots as $serviceId => $slotDetails) {
                    $slotId = (int) ($slotDetails['time_slot_id'] ?? 0);
                    if ($slotId <= 0) {
                        continue;
                    }

                    $slotReleaseCounts[$slotId] = ($slotReleaseCounts[$slotId] ?? 0) + 1;
                }
            }

            foreach ($slotReleaseCounts as $slotId => $releaseCount) {
                $slot = TimeSlot::find((int) $slotId);
                if (!$slot) {
                    continue;
                }

                $slot->booked_count = max(0, (int) $slot->booked_count - (int) $releaseCount);
                if (!is_null($slot->capacity) && $slot->booked_count < $slot->capacity) {
                    $slot->status = 'available';
                }
                $slot->save();
            }

            $releasedAdditionalServiceSlots = !empty($slotReleaseCounts);
        }

        if (!$releasedAdditionalServiceSlots && !empty($metadata['additional_service_time_slots']) && is_array($metadata['additional_service_time_slots'])) {
            $servicePetCounts = $this->getAdditionalServicePetCounts($appointment->additional_services_by_pet ?? []);

            foreach ($metadata['additional_service_time_slots'] as $serviceId => $slotDetails) {
                $slotId = (int) ($slotDetails['time_slot_id'] ?? 0);
                if ($slotId <= 0) {
                    continue;
                }

                $slot = TimeSlot::find($slotId);
                if (!$slot) {
                    continue;
                }

                $releaseCount = max(1, (int) ($servicePetCounts[(int) $serviceId] ?? 0));
                $slot->booked_count = max(0, (int) $slot->booked_count - $releaseCount);
                if (!is_null($slot->capacity) && $slot->booked_count < $slot->capacity) {
                    $slot->status = 'available';
                }
                $slot->save();
            }
        }

        if ($appointment->metadata && isset($appointment->metadata['used_slot_ids'])) {
            $usedSlotIds = is_array($appointment->metadata['used_slot_ids'])
                ? $appointment->metadata['used_slot_ids']
                : explode(',', $appointment->metadata['used_slot_ids']);

            $timeSlots = TimeSlot::whereIn('id', $usedSlotIds)->get();
            foreach ($timeSlots as $timeSlot) {
                $timeSlot->decrementBooking();
            }
        } else {
            if ($appointment->date && $appointment->start_time && $appointment->service_id) {
                $timeSlots = TimeSlot::where('service_id', $appointment->service_id)
                    ->whereDate('date', $appointment->date)
                    ->where(function ($query) use ($appointment) {
                        $query->where('start_time', $appointment->start_time)
                              ->orWhere('end_time', $appointment->end_time);
                    })->get();

                foreach ($timeSlots as $timeSlot) {
                    $timeSlot->decrementBooking();
                }
            }
        }
    }

    private function markCatRoomOutOfService(?int $roomId): void
    {
        if (!$roomId) {
            return;
        }

        Room::where('id', $roomId)->update(['status' => 'Out of Service']);
    }

    private function releaseCatRoomIfUnused(?int $roomId, ?int $excludingAppointmentId = null): void
    {
        if (!$roomId) {
            return;
        }

        $roomStillAssigned = Appointment::where('cat_room_id', $roomId)
            ->when($excludingAppointmentId, function ($query) use ($excludingAppointmentId) {
                $query->where('id', '!=', $excludingAppointmentId);
            })
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->exists();

        if (!$roomStillAssigned) {
            Room::where('id', $roomId)
                ->where('status', 'Out of Service')
                ->update(['status' => 'Available']);
        }
    }

    private function spaceRoomsQuery()
    {
        return Room::where(function ($query) {
            $query->where('room_types', 'space')
                ->orWhere('room_types', 'like', '%space%');
        });
    }

    private function catRoomsQuery()
    {
        return $this->spaceRoomsQuery()->where(function ($query) {
            $query->where('pet_type_labels', 'like', '%cat%');
        });
    }

    private function availableCatRoomsQuery()
    {
        return $this->catRoomsQuery()->where('status', 'Available');
    }

    public function updateStatus(Request $request, $id, AppointmentBookingNotifier $bookingNotifier)
    {
        $request->validate([
            'status' => 'required|in:cancelled,no_show,checked_in',
        ]);

        $appointment = Appointment::findOrFail($id);
        $oldStatus = $appointment->status;
        $newStatus = $request->status;

        $appointment->status = $newStatus;
        $appointment->save();
        $label = $newStatus === 'checked_in' ? "Appointment is created." : "Appointment status changed to " . appointment_status_label($newStatus, $appointment->service) . ".";
        appointment_audit_log($appointment->id, $label);

        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $bookingNotifier->sendCancellation($appointment, Auth::id());
        }

        if (in_array($newStatus, ['cancelled', 'no_show'])) {
            $this->saveCancellationRecord($appointment, $newStatus);
            $this->releaseTimeSlots($appointment);
            $this->releaseCatRoomIfUnused($appointment->cat_room_id, $appointment->id);
        } elseif ($newStatus === 'checked_in' && isBoardingService($appointment->service)) {
            $this->markCatRoomOutOfService($appointment->cat_room_id);
        }

        return redirect()->route('archives')->with([
            'message' => "Appointment ${newStatus} successfully.",
            'status' => 'success'
        ]);
    }
}