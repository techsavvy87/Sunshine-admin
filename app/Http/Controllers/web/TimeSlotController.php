<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Models\TimeSlot;
use App\Models\Service;
use App\Models\User;
use App\Models\Capacity;
use App\Models\Holiday;
use App\Models\HolidayService;

class TimeSlotController extends Controller
{
    public function listTimeSlots(Request $request)
    {
        // Delete all TimeSlot records created until yesterday
        $yesterday = Carbon::yesterday()->toDateString();
        TimeSlot::where('date', '<=', $yesterday)->delete();

        $allServices = Service::where('status', 'active')->get();
        $services = $allServices->filter(function ($service) {
            return !isBoardingService($service) && !isAlaCarteService($service) && !isGroupClassService($service);
        });
        $perPage = $request->get('per_page', 20);

        // Determine the service ID from search parameters
        if ($request->filled('serviceId'))
            $serviceId = $request->get('serviceId');
        else 
            $serviceId = Service::where('status', 'active')->whereIn('level', ['secondary'])->first()->id ?? null;

        // Determine the date from search parameters
        if ($request->filled('date'))
            $date = $request->get('date');
        else
            $date = Carbon::today()->toDateString();

        // get timeslots
        $timeSlots = TimeSlot::where('service_id', $serviceId)
            ->whereDate('date', $date)
            ->orderBy('start_time');

        $timeSlots = $timeSlots->paginate($perPage);

        return view('timeslots.index', compact('timeSlots', 'services', 'serviceId', 'date'));
    }

    public function getHolidaysInRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $holidays = Holiday::with('holidayServices.service')
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date')
            ->get()
            ->map(function($holiday) {
                return [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'date' => $holiday->date,
                    'percent_increase' => $holiday->percent_increase,
                    'restrict_bookings' => $holiday->restrict_bookings,
                    'services' => $holiday->holidayServices->map(function($hs) {
                        return [
                            'service_id' => $hs->service_id,
                            'service_name' => $hs->service ? $hs->service->name : 'Unknown',
                            'max_value' => $hs->max_value,
                        ];
                    })->toArray(),
                ];
            });

        return response()->json([
            'holidays' => $holidays
        ]);
    }

    public function getExistingTimeSlotDates(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $latestTimeSlotEndDate = TimeSlot::whereDate('date', '>=', $today)
            ->max('date');
        
        $existingDates = TimeSlot::whereDate('date', '>=', $today)
            ->selectRaw('DATE(date) as date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->toArray();

        $restrictedHolidayDates = Holiday::whereDate('date', '>=', $today)
            ->where('restrict_bookings', 'yes')
            ->pluck('date')
            ->map(function($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })
            ->toArray();

        $restrictedDates = array_unique(array_merge($existingDates, $restrictedHolidayDates));
        sort($restrictedDates);

        return response()->json([
            'existing_dates' => $restrictedDates,
            'latest_timeslot_end_date' => $latestTimeSlotEndDate
        ]);
    }

    public function generateTimeSlot(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        if ($startDate->diffInDays($endDate) + 1 > 365) {
            throw ValidationException::withMessages([
                'end_date' => 'The date range cannot exceed 12 months (365 days).',
            ]);
        }

        $holidays = Holiday::with('holidayServices.service')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(function($holiday) {
                return $holiday->date->toDateString();
            });

        $allServices = Service::where('status', 'active')->get();
        $services = $allServices->filter(function ($service) {
            return !isBoardingService($service) && !isAlaCarteService($service) && !isGroupClassService($service);
        });

        $generatedDates = 0;
        $generatedServices = 0;
        $skippedDates = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            $holiday = $holidays->get($dateString);
            
            // Skip dates with holidays that have restrict_bookings = 'yes'
            if ($holiday && $holiday->restrict_bookings === 'yes') {
                $skippedDates++;
                $currentDate->addDay();
                continue;
            }

            $dateGenerated = false;

            foreach ($services as $service) {
                $isTimeSlots = TimeSlot::whereDate('date', $dateString)->where('service_id', $service->id)->exists();
                if ($isTimeSlots) {
                    continue;
                }

                $holidayService = null;
                $maxCapacity = null;
                if ($holiday) {
                    $holidayService = $holiday->holidayServices->firstWhere('service_id', $service->id);
                    if ($holidayService && $holidayService->max_value !== null) {
                        $maxCapacity = $holidayService->max_value;
                    }
                }

                if (isDaycareService($service)) {
                    $this->generateDaycareTimeSlots($service, $dateString, $maxCapacity);
                } elseif (isPrivateTrainingService($service)) {
                    $this->generateTrainingTimeSlots($service, $dateString, $maxCapacity);
                } elseif ($this->isMultiDurationService($service)) {
                    $this->generateMultiDurationTimeSlots($service, $dateString, $maxCapacity);
                } else {
                    $this->generateRegularTimeSlots($service, $dateString, $maxCapacity);
                }
                $generatedServices++;
                $dateGenerated = true;
            }

            if ($dateGenerated) {
                $generatedDates++;
            }

            $currentDate->addDay();
        }

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $message = "Time slots generated successfully for {$generatedServices} service-date combination(s) across {$generatedDates} day(s) (out of {$totalDays} total days).";
        if ($skippedDates > 0) {
            $message .= " {$skippedDates} day(s) were skipped due to restricted bookings.";
        }

        return response()->json([
            'status' => 'success',
            'message' => $message
        ]);
    }

    public function addTimeSlot(Request $request)
    {
        $allServices = Service::where('status', 'active')->get();
        $services = $allServices->filter(function ($service) {
            return !isBoardingService($service) && !isAlaCarteService($service) && !isGroupClassService($service);
        });
        $staffs = User::whereHas('roles', function ($query) {
                    $query->whereNot('title', 'customer');
                })->get();
        return view('timeslots.create', compact('services', 'staffs'));
    }

    public function createTimeSlot(Request $request)
    {
        $validated = $request->validate([
            'service' => 'required|exists:services,id',
            'staff' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:available,blocked,full',
            'daycare_type' => 'nullable|in:half,full',
        ]);

        $timeSlot = new TimeSlot;
        $timeSlot->service_id = $request->service;
        $timeSlot->staff_id = $request->staff;
        $timeSlot->date = $request->date;
        $timeSlot->start_time = $request->start_time;
        $timeSlot->end_time = $request->end_time;
        if ($request->filled('capacity'))
            $timeSlot->capacity = $request->capacity;
        if ($request->filled('booked_count'))
            $timeSlot->booked_count = $request->booked_count;
        if ($request->filled('daycare_type'))
            $timeSlot->daycare_type = $request->daycare_type;
        $timeSlot->status = $request->status;
        $timeSlot->save();

        return redirect()->route('timeslots')->with([
            'message' => 'Time slot created successfully.',
            'status' => 'success'
        ]);
    }

    public function editTimeSlot($id)
    {
        $timeSlot = TimeSlot::findOrFail($id);
        $allServices = Service::where('status', 'active')->get();
        $services = $allServices->filter(function ($service) {
            return !isBoardingService($service) && !isAlaCarteService($service) && !isGroupClassService($service);
        });
        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->get();

        return view('timeslots.update', compact('timeSlot', 'services', 'staffs'));
    }

    public function updateTimeSlot(Request $request)
    {
        $validated = $request->validate([
            'timeslot_id' => 'required|exists:time_slots,id',
            'service' => 'required|exists:services,id',
            'staff' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:available,blocked,full',
            'daycare_type' => 'nullable|in:half,full',
        ]);

        $timeSlot = TimeSlot::findOrFail($request->timeslot_id);

        // Find and delete overlapping time slots before updating
        $overlappingSlots = TimeSlot::where('id', '!=', $request->timeslot_id)
            ->where('service_id', $timeSlot->service_id)
            ->where('date', $timeSlot->date)
            ->where('pet_size', $timeSlot->pet_size)
            ->where(function($query) use ($request) {
                $query->where(function($q) use ($request) {
                    // New slot starts during an existing slot
                    $q->where('start_time', '<=', $request->start_time)
                      ->where('end_time', '>', $request->start_time);
                })
                ->orWhere(function($q) use ($request) {
                    // New slot ends during an existing slot
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>=', $request->end_time);
                })
                ->orWhere(function($q) use ($request) {
                    // New slot completely contains an existing slot
                    $q->where('start_time', '>=', $request->start_time)
                      ->where('end_time', '<=', $request->end_time);
                });
            })
            ->get();

        $deletedCount = 0;
        if ($overlappingSlots->count() > 0) {
            $deletedCount = $overlappingSlots->count();
            // Delete all overlapping slots
            TimeSlot::whereIn('id', $overlappingSlots->pluck('id'))->delete();
        }

        // Update the time slot
        $timeSlot->service_id = $request->service;
        $timeSlot->staff_id = $request->staff;
        $timeSlot->date = $request->date;
        $timeSlot->start_time = $request->start_time;
        $timeSlot->end_time = $request->end_time;
        if ($request->filled('capacity'))
            $timeSlot->capacity = $request->capacity;
        if ($request->filled('booked_count'))
            $timeSlot->booked_count = $request->booked_count;
        if ($request->filled('daycare_type'))
            $timeSlot->daycare_type = $request->daycare_type;
        $timeSlot->status = $request->status;
        $timeSlot->save();

        $message = 'Time slot updated successfully.';
        if ($deletedCount > 0) {
            $message .= " {$deletedCount} overlapping time slot(s) were removed.";
        }

        return redirect()->route('timeslots')->with([
            'message' => $message,
            'status' => 'success'
        ]);
    }

    public function deleteTimeSlot(Request $request)
    {
        $validated = $request->validate([
            'timeslot_id' => 'required|exists:time_slots,id',
        ]);

        $timeSlot = TimeSlot::findOrFail($request->timeslot_id);
        $timeSlot->delete();

        return redirect()->route('timeslots')->with([
            'message' => 'Time slot deleted successfully.',
            'status' => 'success'
        ]);
    }

    public function checkOverlap(Request $request)
    {
        $request->validate([
            'timeslot_id' => 'required|exists:time_slots,id',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $currentTimeSlot = TimeSlot::findOrFail($request->timeslot_id);

        // Check for overlapping time slots on the same date, service, and pet_size
        $overlappingSlots = TimeSlot::where('id', '!=', $request->timeslot_id)
            ->where('service_id', $currentTimeSlot->service_id)
            ->where('date', $currentTimeSlot->date)
            ->where('pet_size', $currentTimeSlot->pet_size)
            ->where(function($query) use ($request) {
                $query->where(function($q) use ($request) {
                    // New slot starts during an existing slot
                    $q->where('start_time', '<=', $request->start_time)
                      ->where('end_time', '>', $request->start_time);
                })
                ->orWhere(function($q) use ($request) {
                    // New slot ends during an existing slot
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>=', $request->end_time);
                })
                ->orWhere(function($q) use ($request) {
                    // New slot completely contains an existing slot
                    $q->where('start_time', '>=', $request->start_time)
                      ->where('end_time', '<=', $request->end_time);
                });
            })
            ->get();

        if ($overlappingSlots->count() > 0) {
            return response()->json([
                'overlap' => true,
                'message' => 'This time slot overlaps with ' . $overlappingSlots->count() . ' existing slot(s).',
                'overlapping_slots' => $overlappingSlots->map(function($slot) {
                    return [
                        'id' => $slot->id,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'staff' => $slot->staff ? $slot->staff->name : 'Unassigned',
                    ];
                })
            ]);
        }

        return response()->json([
            'overlap' => false,
            'message' => 'No overlapping time slots found.'
        ]);
    }

    private function isMultiDurationService(Service $service): bool
    {
        if (isDaycareService($service) || isPrivateTrainingService($service)) {
            return false;
        }
        return $service->duration_small || $service->duration_medium || $service->duration_large || $service->duration_xlarge;
    }

    private function generateMultiDurationTimeSlots(Service $service, string $date, ?int $maxCapacity = null): void
    {
        $petSizes = [
            'small' => $service->duration_small ?? 1.0,
            'medium' => $service->duration_medium ?? 1.5,
            'large' => $service->duration_large ?? 2.0,
            'xlarge' => $service->duration_xlarge ?? 2.5,
        ];

        $slotStart = Carbon::createFromTime(9, 0); // 9:00 AM
        $businessClose = Carbon::createFromTime(17, 0); // 5:00 PM

        $capacity = Capacity::where('service_id', $service->id)->first();
        $capacityValue = $maxCapacity !== null ? $maxCapacity : ($capacity ? $capacity->capacity : 1);

        foreach ($petSizes as $petSize => $durationHours) {
            if ($durationHours <= 0) continue;

            $currentSlotStart = $slotStart->copy();
            $durationMinutes = (int) round($durationHours * 60);

            while ($currentSlotStart->lessThan($businessClose)) {
                $slotEnd = $currentSlotStart->copy()->addMinutes($durationMinutes);

                if ($slotEnd->gt($businessClose)) {
                    break;
                }

                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $currentSlotStart->toTimeString(),
                    'end_time' => $slotEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'pet_size' => $petSize,
                    'status' => 'available',
                ]);

                // Move to the end of the current slot (sequential, non-overlapping)
                $currentSlotStart = $slotEnd->copy();
            }
        }
    }

    private function generateDaycareTimeSlots(Service $service, string $date, ?int $maxCapacity = null): void
    {
        $halfDayHours = $service->duration_small ?? 4.0;
        $fullDayHours = $service->duration_medium ?? 8.0;

        $businessOpen = Carbon::createFromTime(9, 0);
        $businessClose = Carbon::createFromTime(17, 0);

        $capacity = Capacity::where('service_id', $service->id)->first();
        $capacityValue = $maxCapacity !== null ? $maxCapacity : ($capacity ? $capacity->capacity : 1);

        if ($halfDayHours > 0) {
            $halfDayMinutes = (int) round($halfDayHours * 60);

            $morningStart = $businessOpen->copy();
            $morningEnd = $morningStart->copy()->addMinutes($halfDayMinutes);

            if ($morningEnd->lessThanOrEqualTo($businessClose)) {
                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $morningStart->toTimeString(),
                    'end_time' => $morningEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'daycare_type' => 'half',
                    'status' => 'available',
                ]);
            }

            // Afternoon half-day slot (1 PM - 5 PM for 4 hours)
            $afternoonStart = $morningEnd->copy();
            $afternoonEnd = $afternoonStart->copy()->addMinutes($halfDayMinutes);

            if ($afternoonEnd->lessThanOrEqualTo($businessClose)) {
                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $afternoonStart->toTimeString(),
                    'end_time' => $afternoonEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'daycare_type' => 'half',
                    'status' => 'available',
                ]);
            }
        }

        // Generate Full-Day slot (9 AM - 5 PM for 8 hours)
        if ($fullDayHours > 0) {
            $fullDayMinutes = (int) round($fullDayHours * 60);
            $fullDayEnd = $businessOpen->copy()->addMinutes($fullDayMinutes);

            if ($fullDayEnd->lessThanOrEqualTo($businessClose)) {
                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $businessOpen->toTimeString(),
                    'end_time' => $fullDayEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'daycare_type' => 'full',
                    'status' => 'available',
                ]);
            }
        }
    }

    private function generateTrainingTimeSlots(Service $service, string $date, ?int $maxCapacity = null): void
    {
        $halfHourDuration = (float) ($service->duration_small ?? 0.5);
        $oneHourDuration = (float) ($service->duration_medium ?? 1.0);

        $businessOpen = Carbon::createFromTime(9, 0);
        $businessClose = Carbon::createFromTime(17, 0);

        $capacity = Capacity::where('service_id', $service->id)->first();
        $capacityValue = $maxCapacity !== null ? $maxCapacity : ($capacity ? $capacity->capacity : 1);

        $halfHourStart = $businessOpen->copy();
        $halfHourIncrement = 30;
        while ($halfHourStart->lessThan($businessClose)) {
            $halfHourEnd = $halfHourStart->copy()->addMinutes((int) round($halfHourDuration * 60));
            if ($halfHourEnd->lessThanOrEqualTo($businessClose)) {
                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $halfHourStart->toTimeString(),
                    'end_time' => $halfHourEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'private_training_type' => 'half',
                    'status' => 'available',
                ]);
            }
            $halfHourStart->addMinutes($halfHourIncrement);
        }

        $oneHourStart = $businessOpen->copy();
        $oneHourIncrement = 60;
        while ($oneHourStart->lessThan($businessClose)) {
            $oneHourEnd = $oneHourStart->copy()->addMinutes((int) round($oneHourDuration * 60));
            if ($oneHourEnd->lessThanOrEqualTo($businessClose)) {
                TimeSlot::create([
                    'service_id' => $service->id,
                    'staff_id' => null,
                    'date' => $date,
                    'start_time' => $oneHourStart->toTimeString(),
                    'end_time' => $oneHourEnd->toTimeString(),
                    'capacity' => $capacityValue,
                    'booked_count' => 0,
                    'private_training_type' => 'one',
                    'status' => 'available',
                ]);
            }
            $oneHourStart->addHours(1);
        }
    }

    private function generateRegularTimeSlots(Service $service, string $date, ?int $maxCapacity = null): void
    {
        $durationHours = $service->duration ?? 1.0;

        if ($durationHours <= 0) return;

        $slotStart = Carbon::createFromTime(9, 0);
        $businessClose = Carbon::createFromTime(17, 0);

        $capacity = Capacity::where('service_id', $service->id)->first();
        $capacityValue = $maxCapacity !== null ? $maxCapacity : ($capacity ? $capacity->capacity : 1);

        while ($slotStart->lessThan($businessClose)) {
            $slotEnd = $slotStart->copy()->addMinutes((int) round($durationHours * 60));

            if ($slotEnd->gt($businessClose)) {
                break;
            }

            TimeSlot::create([
                'service_id' => $service->id,
                'staff_id' => null,
                'date' => $date,
                'start_time' => $slotStart->toTimeString(),
                'end_time' => $slotEnd->toTimeString(),
                'capacity' => $capacityValue,
                'booked_count' => 0,
                'pet_size' => null,
                'status' => 'available',
            ]);

            $slotStart = $slotEnd;
        }
    }
}
