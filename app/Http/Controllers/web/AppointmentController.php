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
        // Get all additional services except the main service if selected
        if (isset($serviceId)) {
            $additionalServices = Service::where('id', '!=', $serviceId)->where('status', 'active')->get();
        } else {
            $additionalServices = Service::where('status', 'active')->get();
        }

        $services = Service::where('status', 'active')->where('level', 'primary')->get();

        // Secondary grooming services for selection (for dropdown etc)
        $secondaryServices = Service::where('status', 'active')
            ->where('level', 'secondary')
            ->whereHas('category', function($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%groom%']);
            })
            ->with('category')
            ->get();

        $kennels = Kennel::where('status', 'In Service')
            ->orderBy('name')
            ->get();

        $rooms = Room::where('status', 'Available')
            ->where('type', 'cat')
            ->orderBy('name')
            ->get();

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

    private function calculateFamilyBoardingEstimatedPrice(Service $service, Appointment $appointment, array $petIds, array $additionalServiceIds, int $customerId): float
    {
        if (!isBoardingService($service) || empty($petIds)) {
            return 0;
        }

        $selectedAdditionalServices = !empty($additionalServiceIds)
            ? Service::whereIn('id', $additionalServiceIds)->get()
            : collect();

        $estimatedTotal = 0;

        foreach ($petIds as $petId) {
            $pet = PetProfile::find($petId);
            $petSize = $pet ? $pet->size : 'medium';

            $priceAppointment = clone $appointment;
            $priceAppointment->pet_id = $petId;

            $petBoardingTotal = getBoardingServicePrice($service, $priceAppointment);
            $petTotal = $petBoardingTotal === null ? 0 : $petBoardingTotal;

            foreach ($selectedAdditionalServices as $additionalService) {
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
            'date' => $date,
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
            'pet_id' => 'required|exists:pet_profiles,id',
            'service_id' => 'required|exists:services,id',
            'package_id' => 'nullable|exists:packages,id',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
        ]);

        $capacity  = Kennel::count() + Room::where('type', '!=', 'dog')->count();
        $occupiedKennelCount = Appointment::whereNotNull('kennel_id')
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->whereHas('service.category', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%boarding%']))
            ->distinct('kennel_id')
            ->count('kennel_id');
        $occupied = $occupiedKennelCount + Room::where('status', '!=', 'Available')->where('type', '!=', 'dog')->count();
        $available_status = $occupied / $capacity < 0.5? true : false;

        $pet = PetProfile::find($request->pet_id);
        $service = Service::find($request->service_id);
        $additionalServiceIds = collect($request->input('additional_services', []))
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

        // Pet vaccine status check
        if ($pet->vaccine_status === 'approved') {
            $vaccineStatus = 'approved';
        } else if ($pet->vaccine_status === 'expired') {
            $vaccineStatus = 'expired';
        } else {
            $vaccineStatus = false;
        }

        $questionnaire = Questionnaire::where('pet_id', $pet->id)
            ->where('user_id', $pet->user_id)
            ->where('service_category_id', $service->category->id)
            ->orderBy('created_at', 'desc')
            ->first();
        $questionnaireStatus = $questionnaire && $questionnaire->status === 'approved' ? true : false;

        // Pet Owner profile status
        $ownerStatus = (bool)$pet->owner->status;

        return response()->json([
            'owner_status' => $ownerStatus,
            'vaccine_status' => $vaccineStatus,
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
            'date' => 'nullable|date',
            'time_slot' => 'nullable|exists:time_slots,id',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
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
        $allSelectedPetsAreCats = $selectedPets->isNotEmpty()
            && $selectedPets->every(fn ($pet) => strtolower((string) $pet->type) === 'cat');

        $assignedKennelId = $request->filled('kennel') ? (int) $request->kennel : null;
        $selectedRoom = null;

        if (isBoardingService($service)) {
            if ($allSelectedPetsAreCats) {
                if (!$request->filled('room')) {
                    return back()->withErrors([
                        'room' => 'Please select a cat room for the boarding appointment.'
                    ])->withInput();
                }

                $selectedRoom = Room::where('status', 'Available')
                    ->where('type', 'cat')
                    ->find($request->room);

                if (!$selectedRoom) {
                    return back()->withErrors([
                        'room' => 'The selected room is not available for cat boarding.'
                    ])->withInput();
                }

                $assignedKennelId = null;
            } elseif (!$assignedKennelId) {
                return back()->withErrors([
                    'kennel' => 'Please select a kennel for the boarding appointment.'
                ])->withInput();
            } elseif ($request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
                $newStart = Carbon::parse($request->boarding_start_datetime);
                $newEnd = Carbon::parse($request->boarding_end_datetime);

                $hasOverlap = $this->getOverlappingBoardingAppointmentsQuery($newStart, $newEnd)
                    ->where('kennel_id', $assignedKennelId)
                    ->exists();

                if ($hasOverlap) {
                    return back()->withErrors([
                        'kennel' => 'The selected kennel is already booked during this time period.'
                    ])->withInput();
                }
            }
        }

        $selectedAdditionalServiceIds = $request->input('additional_services', []);
        $selectedAdditionalServices = !empty($selectedAdditionalServiceIds)
            ? Service::with('category')->whereIn('id', $selectedAdditionalServiceIds)->get()
            : collect([]);

        $requiresBoardingAdditionalService = isBoardingService($service);

        $requiresAdditionalServiceTimeSlot = $requiresBoardingAdditionalService && $selectedAdditionalServices->isNotEmpty();

        if ($requiresAdditionalServiceTimeSlot && !$request->filled('time_slot')) {
            return back()->withErrors([
                'time_slot' => 'Please select a valid time slot for the additional service.'
            ])->withInput();
        }

        if ($request->has('secondary_services')) {
            $metadata['secondary_service_ids'] = implode(',', $request->secondary_services);
        }

        if ($selectedRoom) {
            $metadata['room_id'] = $selectedRoom->id;
            $metadata['room_name'] = $selectedRoom->name;
        }

        if ($requiresAdditionalServiceTimeSlot && $timeSlot) {
            $metadata['additional_service_time_slot_id'] = $timeSlot->id;
            $metadata['additional_service_time_slot_service_id'] = $timeSlot->service_id;
            $metadata['additional_service_time_slot_date'] = $timeSlot->date;
            $metadata['additional_service_time_slot_start_time'] = $timeSlot->start_time;
            $metadata['additional_service_time_slot_end_time'] = $timeSlot->end_time;
        }

        $usedSlotIds = [];

        if ($timeSlot && !is_null($timeSlot->capacity) && ($timeSlot->booked_count + count($petIds)) > $timeSlot->capacity) {
            return back()->withErrors([
                'time_slot' => 'The selected time slot does not have enough capacity for all selected pets.'
            ])->withInput();
        }

        $primaryPetId = $petIds[0] ?? null;

        if (count($petIds) > 1) {
            $metadata['family_pet_ids'] = $petIds;
        } else {
            unset($metadata['family_pet_ids']);
        }

        $appointment = new Appointment;
        $appointment->customer_id = $request->customer;
        $appointment->pet_id = $primaryPetId;
        $appointment->service_id = $request->service;
        $appointment->kennel_id = $assignedKennelId;
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

            if ($requiresAdditionalServiceTimeSlot && $timeSlot) {
                $timeSlotStart = Carbon::parse($timeSlot->date . ' ' . $timeSlot->start_time);
                $timeSlotEnd = Carbon::parse($timeSlot->date . ' ' . $timeSlot->end_time);

                if ($timeSlotEnd->gt($endDateTime) || ($endDateTime->gt($timeSlotStart) && $endDateTime->lt($timeSlotEnd))) {
                    return back()->withErrors([
                        'time_slot' => 'The selected additional service time slot must end before the pick up time.'
                    ])->withInput();
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
                    $request->input('additional_services', []),
                    (int) $request->customer
                );
            }
        }

        $appointment->status = 'checked_in';
        $appointment->additional_service_ids = $request->filled('additional_services')
            ? implode(',', $request->additional_services)
            : null;
        $appointment->metadata = !empty($metadata) ? $metadata : null;
        $appointment->save();

        $bookingNotifier->sendConfirmation($appointment, Auth::id());

        appointment_audit_log($appointment->id, 'Appointment is created.');

        if ($selectedRoom && isBoardingService($service)) {
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
        $additionalServices = Service::where('status', 'active')->whereNot('id', $appointment->service_id)->get();

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

        $kennels = Kennel::where('status', 'In Service')->orderBy('name')->get();
        $selectedKennel = $appointment->kennel_id ? Kennel::find($appointment->kennel_id) : null;
        if ($selectedKennel) {
            $kennels = $kennels->push($selectedKennel)->unique('id')->values();
        }

        $rooms = Room::where('type', 'cat')
            ->where('status', 'Available')
            ->orderBy('name')
            ->get();
        $selectedRoom = $appointment->cat_room_id ? Room::find($appointment->cat_room_id) : null;
        if ($selectedRoom) {
            $rooms = $rooms->push($selectedRoom)->unique('id')->values();
        }

        return view('appointments.update', compact('appointment', 'services', 'additionalServices', 'timeSlots', 'kennels', 'rooms'));
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
            'date' => 'nullable|date',
            'time_slot' => 'nullable',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'exists:services,id',
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
        $selectedPets = PetProfile::whereIn('id', $petIds)->get();
        $selectedPetsAreCats = $selectedPets->isNotEmpty()
            && $selectedPets->every(fn ($pet) => strtolower((string) ($pet->type ?? '')) === 'cat');
        $selectedRoom = null;

        if (isBoardingService($service) && $selectedPetsAreCats) {
            if (!$request->filled('room')) {
                return back()->withErrors([
                    'room' => 'Please select a cat room for the boarding appointment.'
                ])->withInput();
            }

            $selectedRoom = Room::where('type', 'cat')
                ->where(function ($query) use ($appointment) {
                    $query->where('status', 'Available')
                        ->orWhere('id', $appointment->cat_room_id);
                })
                ->find($request->room);

            if (!$selectedRoom) {
                return back()->withErrors([
                    'room' => 'The selected room is not available for cat boarding.'
                ])->withInput();
            }
        } elseif (isBoardingService($service) && !$request->filled('kennel')) {
            return back()->withErrors([
                'kennel' => 'Please select a kennel for the boarding appointment.'
            ])->withInput();
        } elseif (isBoardingService($service) && $request->filled('kennel') && $request->filled('boarding_start_datetime') && $request->filled('boarding_end_datetime')) {
            $newStart = Carbon::parse($request->boarding_start_datetime);
            $newEnd = Carbon::parse($request->boarding_end_datetime);
            $checkKennelId = (int) $request->kennel;

            $hasOverlap = $this->getOverlappingBoardingAppointmentsQuery($newStart, $newEnd, (int) $appointment->id)
                ->where('kennel_id', $checkKennelId)
                ->exists();

            if ($hasOverlap) {
                return back()->withErrors([
                    'kennel' => 'The selected kennel is already booked during this time period.'
                ])->withInput();
            }
        }

        $primaryPetId = $petIds[0] ?? null;

        if (count($petIds) > 1) {
            $metadata['family_pet_ids'] = $petIds;
        } else {
            unset($metadata['family_pet_ids']);
        }

        $selectedAdditionalServiceIds = $request->input('additional_services', []);
        $selectedAdditionalServices = !empty($selectedAdditionalServiceIds)
            ? Service::with('category')->whereIn('id', $selectedAdditionalServiceIds)->get()
            : collect([]);

        $requiresBoardingAdditionalService = isBoardingService($service);

        $requiresAdditionalServiceTimeSlot = $requiresBoardingAdditionalService && $selectedAdditionalServices->isNotEmpty();

        if ($requiresAdditionalServiceTimeSlot && !$request->filled('time_slot')) {
            return back()->withErrors([
                'time_slot' => 'Please select a valid time slot for the additional service.'
            ])->withInput();
        }

        if ($selectedRoom) {
            $metadata['room_id'] = $selectedRoom->id;
            $metadata['room_name'] = $selectedRoom->name;
        } else {
            unset($metadata['room_id'], $metadata['room_name']);
        }

        if ($requiresAdditionalServiceTimeSlot && $timeSlot) {
            $metadata['additional_service_time_slot_id'] = $timeSlot->id;
            $metadata['additional_service_time_slot_service_id'] = $timeSlot->service_id;
            $metadata['additional_service_time_slot_date'] = $timeSlot->date;
            $metadata['additional_service_time_slot_start_time'] = $timeSlot->start_time;
            $metadata['additional_service_time_slot_end_time'] = $timeSlot->end_time;
        } else {
            unset(
                $metadata['additional_service_time_slot_id'],
                $metadata['additional_service_time_slot_service_id'],
                $metadata['additional_service_time_slot_date'],
                $metadata['additional_service_time_slot_start_time'],
                $metadata['additional_service_time_slot_end_time']
            );
        }

        $oldStatus = $appointment->status;
        $oldKennelId = $appointment->kennel_id;
        $oldRoomId = $appointment->cat_room_id;
        $newKennelId = isBoardingService($service) && !$selectedPetsAreCats && $request->filled('kennel')
            ? (int) $request->kennel
            : null;
        $newRoomId = isBoardingService($service) && $selectedPetsAreCats && $selectedRoom
            ? (int) $selectedRoom->id
            : null;

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

            if ($requiresAdditionalServiceTimeSlot && $timeSlot) {
                $timeSlotStart = Carbon::parse($timeSlot->date . ' ' . $timeSlot->start_time);
                $timeSlotEnd = Carbon::parse($timeSlot->date . ' ' . $timeSlot->end_time);

                if ($timeSlotEnd->gt($endDateTime) || ($endDateTime->gt($timeSlotStart) && $endDateTime->lt($timeSlotEnd))) {
                    return back()->withErrors([
                        'time_slot' => 'The selected additional service time slot must end before the pick up time.'
                    ])->withInput();
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
                $selectedAdditionalServiceIds,
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

        if ($newRoomId && $isActiveBoardingAppointment) {
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
            'status' => 'required|in:draft,sent,paid,void',
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

        $invoice->customer_id = $appointment->customer_id;
        $invoice->invoice_number = $request->invoice_number;
        $invoice->first_name = $request->first_name;
        $invoice->last_name = $request->last_name;
        $invoice->email = $request->email;
        $invoice->issued_at = $request->issued_at ? Carbon::parse($request->issued_at) : null;
        $invoice->due_date = $request->due_date ? Carbon::parse($request->due_date) : null;
        if ($request->status !== "draft") {
            $invoice->discount_amount = $request->discount_amount ?? 0;
            $invoice->discount_title = $request->discount_title ?? '';
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
        $items = $request->items;
        $itemsForEmail = [];
        if ($items && is_array($items)) {
            // Delete existing items
            InvoiceItem::where('invoice_id', $invoice->id)->delete();
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

        $discountInfo = [
            'discount_title' => $request->discount_title,
            'discount_amount' => $request->discount_amount ?? 0
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
            $this->sendInvoiceEmail($invoice, $appointment, $request->items, $discountInfo);
        }

        if ($request->status === 'sent' || ($request->status === 'paid' && $request->payment_amount)) {
            $this->sendInvoiceEmail($invoice, $appointment, $request->items, $discountInfo);
        }

        return response()->json([
            'status' => true,
            'message' => $request->status === 'sent' ? 'Invoice saved and sent successfully.' : ($request->status === 'paid' && $request->payment_amount ? 'Invoice saved and payment recorded successfully.' : 'Invoice saved successfully.'),
            'invoice_id' => $invoice->id
        ]);
    }

    private function sendInvoiceEmail($invoice, $appointment, $items, $discountInfo = [])
    {
        $mainServiceItems = [];
        $additionalServiceItems = [];
        $inventoryItems = [];

        $appointment->load('service.category');
        $mainServiceName = $appointment->service->name ?? '';

        $additionalServiceNames = [];
        if ($appointment->additional_service_ids) {
            $additionalIds = explode(',', $appointment->additional_service_ids);
            $additionalServices = Service::whereIn('id', $additionalIds)->get();
            $additionalServiceNames = $additionalServices->pluck('name')->toArray();
        }

        $groupClassNames = [];
        if (isGroupClassService($appointment->service) && $appointment->metadata && isset($appointment->metadata['group_class_ids'])) {
            $groupClassIds = explode(',', $appointment->metadata['group_class_ids']);
            $groupClasses = GroupClass::whereIn('id', $groupClassIds)->get();
            $groupClassNames = $groupClasses->pluck('name')->toArray();
        }

        $totalServicePrice = 0;
        $totalInventoryAmount = 0;

        if ($items && is_array($items)) {
            foreach ($items as $itemData) {
                $itemType = $itemData['type'] ?? 'service';
                $itemDescription = $itemData['description'] ?? '';
                $itemPrice = floatval($itemData['price'] ?? 0);

                if ($itemType === 'inventory') {
                    $inventoryItems[] = [
                        'description' => $itemDescription,
                        'price' => $itemPrice
                    ];
                    $totalInventoryAmount += $itemPrice;
                } elseif ($itemType === 'service') {
                    if (isGroupClassService($appointment->service) && in_array($itemDescription, $groupClassNames)) {
                        $mainServiceItems[] = [
                            'description' => $itemDescription,
                            'price' => $itemPrice
                        ];
                    } elseif ($itemDescription === $mainServiceName) {
                        $mainServiceItems[] = [
                            'description' => $itemDescription,
                            'price' => $itemPrice
                        ];
                    } elseif (in_array($itemDescription, $additionalServiceNames)) {
                        $additionalServiceItems[] = [
                            'description' => $itemDescription,
                            'price' => $itemPrice
                        ];
                    } else {
                        $mainServiceItems[] = [
                            'description' => $itemDescription,
                            'price' => $itemPrice
                        ];
                    }
                    $totalServicePrice += $itemPrice;
                }
            }
        }

        $estimatedPrice = floatval($appointment->estimated_price ?? 0);
        $discountAmount = floatval($discountInfo['discount_amount'] ?? 0);
        $subtotalAmount = max(0, $estimatedPrice - $discountAmount + $totalInventoryAmount);
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
            'inventory_items' => $inventoryItems,
            'total_service_price' => $totalServicePrice,
            'estimated_price' => $estimatedPrice,
            'discount_title' => $discountInfo['discount_title'] ?? null,
            'discount_amount' => $discountAmount,
            'total_inventory_amount' => $totalInventoryAmount,
            'subtotal_amount' => $subtotalAmount,
            'state_tax_rate' => $stateTaxRate,
            'state_tax_amount' => $stateTaxAmount,
            'total' => $totalAmount,
            'total_amount' => $totalAmount
        ];

        Mail::to($invoice->email)->send(new InvoiceMail($emailData));
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

        $checkout->date = $request->date;
        $checkout->notes = $request->notes;

        // For package appointments, save pickup time to appointment end_time
        if ($isPackageAppointment && $request->filled('pickup_time')) {
            $appointment->end_time = $request->pickup_time;
            $appointment->save();
        }

        // Handle flows and pictures
        $flowsData = $request->flows ? json_decode($request->flows, true) : [];

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

        $checkout->flows = json_encode($flowsData);
        $checkout->save();

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