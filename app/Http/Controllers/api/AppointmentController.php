<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Models\Checkin;
use App\Models\Process;
use App\Models\Checkout;
use App\Models\Service;
use App\Models\PetProfile;
use App\Models\Questionnaire;
use App\Models\GroupClass;
use App\Models\ServiceCategory;
use App\Models\AppointmentCancellation;
use App\Models\Package;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'pet_id' => 'required|exists:pet_profiles,id',
            'service_id' => 'required|exists:services,id',
            'additional_service_ids' => 'nullable|array',
            'additional_service_ids.*' => 'exists:services,id',
            'date' => 'nullable|date',
            'timeslot_id' => 'nullable|exists:time_slots,id',
            'group_class_ids' => 'nullable|array',
            'group_class_ids.*' => 'exists:group_classes,id',
            'used_slot_ids' => 'nullable|array',
            'used_slot_ids.*' => 'exists:time_slots,id',
            'secondary_service_ids' => 'nullable|array',
            'secondary_service_ids.*' => 'exists:services,id',
            'package_id' => 'nullable|exists:packages,id',
        ]);

        $pet = PetProfile::with('owner.profile')->find($request->pet_id);
        $service = Service::with('category')->find($request->service_id);
        $timeslot = TimeSlot::with('service.category')->find($request->timeslot_id);

        if (!isset($service)) {
            return response()->json([
                'status' => false,
                'message' => 'Service for package not found.',
            ], 200);
        }

        // Required vaccine validation by pet type
        $vaccineValidator = new \App\Services\PetVaccineValidator();
        $vaccineValidation = $vaccineValidator->validate($pet);
        $vaccineStatus = $vaccineValidation['valid'] ? true : ($vaccineValidation['status'] === 'expired' ? 'expired' : false);
        $vaccineMessage = $vaccineValidation['message'] ?? null;
        // Pet Questionnaire status check

        $questionnaireRequired = $pet->owner->requiresAppointmentQuestionnaire();
        $questionnaireStatus = true;
        if ($questionnaireRequired && isPackageService($service)) {
            $package = Package::find($request->package_id);
            if ($package && $package->service_ids) {
                $serviceIds = array_map('trim', explode(',', $package->service_ids));
                $packageServices = Service::whereIn('id', $serviceIds)->with('category')->get();

                $requiredCategories = [];

                foreach ($packageServices as $packageService) {
                    if (isGroupClassService($packageService)) {
                        continue;
                    }

                    if ($packageService->category) {
                        $requiredCategories[$packageService->category->id] = $packageService->category;
                    }
                }

                foreach ($requiredCategories as $category) {
                    $categoryQuestionnaire = Questionnaire::where('pet_id', $pet->id)
                        ->where('user_id', $pet->user_id)
                        ->where('service_category_id', $category->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if (!$categoryQuestionnaire || $categoryQuestionnaire->status !== 'approved') {
                        $questionnaireStatus = false;
                    }
                }
            }
        } elseif ($questionnaireRequired) {
            if (isGroomingService($service) || isAlaCarteService($service)) {
                $serviceCategory = ServiceCategory::whereRaw('LOWER(name) LIKE ?', ['%groom%'])->first();
                $questionnaire = Questionnaire::where('pet_id', $pet->id)
                    ->where('user_id', $pet->user_id)
                    ->where('service_category_id', $serviceCategory->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $questionnaire = Questionnaire::where('pet_id', $pet->id)
                    ->where('user_id', $pet->user_id)
                    ->where('service_category_id', $service->category->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // pass if Group Class service
            if (isGroupClassService($service)) {
                $questionnaireStatus = true;
            } else {
                $questionnaireStatus = $questionnaire && $questionnaire->status === 'approved' ? true : false;
            }
        }


        // Pet Owner profile status
        $ownerStatus = (bool)$pet->owner->status;

        if (!$ownerStatus) {
            return response()->json([
                'status' => false,
                'message' => 'Owner profile must be active before booking.',
            ], 200);
        }
        if ($vaccineStatus === 'expired') {
            return response()->json([
                'status' => false,
                'message' => $vaccineMessage ?? 'Pet vaccination is expired.',
            ], 200);
        }
        if (!$vaccineStatus) {
            return response()->json([
                'status' => false,
                'message' => $vaccineMessage ?? 'Pet vaccination records must be approved before booking.',
            ], 200);
        }
        if (!$questionnaireStatus) {
            return response()->json([
                'status' => false,
                'message' => 'Pet questionnaire for this service must be approved before booking.',
            ], 200);
        }

        $metadata = [];
        if ($timeslot) {
            if (isDaycareService($timeslot->service)) {
                if ($timeslot->daycare_type) {
                    $metadata['daycare_duration'] = $timeslot->daycare_type === 'full' ? 'full_day' : 'half_day';

                    if ($timeslot->daycare_type === 'half') {
                        $startTime = Carbon::parse($timeslot->start_time);
                        $metadata['session'] = $startTime->hour < 13 ? 'morning' : 'afternoon';
                    }
                }
            }
            if (isPrivateTrainingService($timeslot->service)) {
                if ($timeslot->private_training_type) {
                    $metadata['private_training_duration'] = $timeslot->private_training_type === 'one' ? 'one_hour' : 'half_hour';
                }
            }
        }

        if ($request->has('group_class_ids')) {
            $metadata['group_class_ids'] = implode(',', $request->group_class_ids);
        }
        if ($request->has('secondary_service_ids')) {
            $metadata['secondary_service_ids'] = implode(',', $request->secondary_service_ids);
        }
        if ($request->has('used_slot_ids')) {
            $metadata['used_slot_ids'] = implode(',', $request->used_slot_ids);
        }

        // Create the appointment
        $appointment = new Appointment;
        $appointment->customer_id = Auth::id();
        $appointment->pet_id = $request->pet_id;
        $appointment->service_id = $request->service_id;
        $appointment->additional_service_ids = $request->additional_service_ids;
        $appointment->date = $request->date;
        $appointment->status = 'checked_in';
        if (isPackageService($service)) {
            $appointment->additional_service_ids = $package->service_ids;
            $appointment->estimated_price = floatval($package->price ?? 0);
            $appointment->metadata = [
                'package_id' => (string)$request->package_id,
                'customer_package_id' => (string)$request->customer_package_id
            ];
        } else {
            if ($request->has('additional_service_ids')) {
                $appointment->additional_service_ids = implode(',', $request->additional_service_ids);
            }
            $appointment->start_time = $timeslot ? $timeslot->start_time : $request->start_time;
            $appointment->end_time = $timeslot ? $timeslot->end_time : $request->end_time;
            $appointment->staff_id = $timeslot ? $timeslot->staff_id : null; // Optional
            $appointment->metadata = !empty($metadata) ? $metadata : null;
            if ($request->has('estimated_price')) {
                $appointment->estimated_price = $request->estimated_price;
            }
            if ($request->has('end_date')) {
                $appointment->end_date = $request->end_date;
            }
        }
        $appointment->save();

        if ($timeslot && !is_null($timeslot->capacity)) {
            if ($timeslot->booked_count < $timeslot->capacity) {
                $timeslot->booked_count += 1;
                if ($timeslot->booked_count >= $timeslot->capacity) {
                    $timeslot->status = 'full';
                }
            } else {
                $timeslot->status = 'full';
            }
            $timeslot->save();
        }

        if ($request->has('used_slot_ids')) {
            $usedSlotIds = $request->used_slot_ids;
            $timeSlots = TimeSlot::whereIn('id', $usedSlotIds)->get();
            foreach ($timeSlots as $timeSlot) {
                $timeSlot->booked_count += 1;
                if ($timeSlot->booked_count >= $timeSlot->capacity) {
                    $timeSlot->status = 'full';
                }
                $timeSlot->save();
            }
        }

        // If appointment is for private training, create checkin record
        if (isPrivateTrainingService($service)) {
            $checkin = new Checkin;
            $checkin->appointment_id = $appointment->id;
            $checkin->date = $request->date;
            $checkin->flows = json_encode([
                'location'=>$request->location,
                'additional_services_link' => $request->additional_service_ids,
                'location_address'=>$request->address,
                'description_needs'=>$request->customer_goals
            ]);
            $checkin->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Appointment created successfully.'
        ], 200);
    }

    public function list($serviceId)
    {
        $appointments = Appointment::where('service_id', $serviceId)
            ->where('customer_id', Auth::id())
            ->with('pet', 'staff')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $service = Service::find($serviceId);
        if (isGroupClassService($service)) {
            foreach ($appointments as $appointment) {
                $appointment->class_name = optional(GroupClass::find($appointment->metadata['group_class_ids'] ?? null))->name ?? '';
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Appointments retrieved successfully.',
            'result' => $appointments
        ], 200);
    }

    public function cancel($id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.',
                'result' => null
            ], 200);
        }

        $oldStatus = $appointment->status;
        $appointment->status = 'cancelled';
        $appointment->save();

        if ($oldStatus !== 'cancelled') {
            $this->saveCancellationRecord($appointment, 'cancelled');
            $this->releaseTimeSlots($appointment);
        }

        // get appointments
        $appointments = Appointment::where('service_id', $appointment->service_id)
            ->where('customer_id', Auth::id())
            ->with('pet', 'staff')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $service = Service::find($appointment->service_id);
        if (isGroupClassService($service)) {
            foreach ($appointments as $appt) {
                $appt->class_name = optional(GroupClass::find($appt->metadata['group_class_ids'] ?? null))->name ?? '';
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Appointment cancelled successfully.',
            'result' => $appointments
        ], 200);
    }

    public function process($id)
    {
        $appointment = Appointment::with(['pet.coatType', 'service.category', 'staff.profile', 'invoice', 'customer.profile'])->find($id);
        // If group class appointment, then add the class name to the appointment object
        if (isGroupClassService($appointment->service)) {
            $appointment->class_name = optional(GroupClass::find($appointment->metadata['group_class_ids'] ?? null))->name ?? '';
        }
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.',
                'result' => null
            ], 200);
        }

        $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
        $appointment->additionalServices = Service::whereIn('id', $additionalServiceIds)->get();

        $chauffeurPricingData = buildChauffeurPricingData($appointment);
        $chauffeurServicePrices = $chauffeurPricingData['service_prices'] ?? [];

        $resolveAppointmentServicePrice = function ($service, $petSize, $metadata = null) use ($chauffeurServicePrices) {
            if (!$service) {
                return 0;
            }

            if (array_key_exists($service->id, $chauffeurServicePrices)) {
                return floatval($chauffeurServicePrices[$service->id]);
            }

            return getServicePrice($service, $petSize, $metadata);
        };

        // Always compute the response estimate from current appointment data so chauffeur
        // and coat fees are reflected even when the stored estimated_price is stale.
        $estimatedTotal = 0;
        $coatPriceApplied = false;
        $totalCoatTypePrice = 0;

        $isGroupClasses = isGroupClassService($appointment->service);
        $groupClassIds = [];
        if ($isGroupClasses && $appointment->metadata && isset($appointment->metadata['group_class_ids'])) {
            $groupClassIds = explode(',', $appointment->metadata['group_class_ids']);
        }

        $isAlaCarte = isAlaCarteService($appointment->service);
        $secondaryServiceIds = [];
        if ($isAlaCarte && $appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
            $secondaryServiceIds = explode(',', $appointment->metadata['secondary_service_ids']);
        }

        if ($isGroupClasses && !empty($groupClassIds)) {
            foreach ($groupClassIds as $classId) {
                $groupClass = GroupClass::find($classId);
                if ($groupClass) {
                    $estimatedTotal += $groupClass->price;
                }
            }
        } elseif ($isAlaCarte && !empty($secondaryServiceIds) && $appointment->pet) {
            $petSize = $appointment->pet->size ?? 'medium';

            foreach ($secondaryServiceIds as $serviceId) {
                if (!empty($serviceId)) {
                    $service = Service::find($serviceId);
                    if ($service) {
                        $estimatedTotal += $resolveAppointmentServicePrice($service, $petSize);
                    }
                }
            }

            $secondaryServiceIdSet = collect($secondaryServiceIds)
                ->map(fn ($serviceId) => (string) trim($serviceId))
                ->filter()
                ->values();

            $additionalServiceIds = collect(explode(',', $appointment->additional_service_ids ?? ''))
                ->map(fn ($serviceId) => (string) trim($serviceId))
                ->filter()
                ->reject(fn ($serviceId) => $secondaryServiceIdSet->contains($serviceId))
                ->values();

            foreach ($additionalServiceIds as $serviceId) {
                $service = Service::find($serviceId);
                if ($service) {
                    $estimatedTotal += $resolveAppointmentServicePrice($service, $petSize);
                }
            }
        } else {
            if (isBoardingService($appointment->service)) {
                $pricingPets = $appointment->family_pets;

                if ($pricingPets->isEmpty() && $appointment->pet) {
                    $pricingPets = collect([$appointment->pet]);
                }

                foreach ($pricingPets as $pet) {
                    $petSize = $pet->size ?? ($appointment->pet->size ?? 'medium');
                    $priceAppointment = clone $appointment;
                    $priceAppointment->pet_id = $pet->id ?? $appointment->pet_id;

                    $boardingPrice = getBoardingServicePrice($appointment->service, $priceAppointment);
                    $petTotal = $boardingPrice !== null
                        ? $boardingPrice
                        : $resolveAppointmentServicePrice($appointment->service, $petSize, $appointment->metadata);

                    foreach ($appointment->additionalServices as $addService) {
                        $petTotal += $resolveAppointmentServicePrice($addService, $petSize);
                    }

                    $estimatedTotal += $petTotal;
                }
            } else {
                $petSize = $appointment->pet->size ?? 'medium';
                $estimatedTotal = $resolveAppointmentServicePrice($appointment->service, $petSize, $appointment->metadata);

                foreach ($appointment->additionalServices as $addService) {
                    $estimatedTotal += $resolveAppointmentServicePrice($addService, $petSize);
                }
            }
        }

        $isPetDoubleCoated = (bool) (optional(optional($appointment->pet)->coatType)->is_double_coated ?? false);
        if ($isPetDoubleCoated) {
            $coatFeeServiceIds = array_filter(array_unique(array_merge(
                [$appointment->service_id],
                !empty($appointment->second_service_id) ? [$appointment->second_service_id] : [],
                !empty($appointment->additional_service_ids) ? explode(',', $appointment->additional_service_ids) : [],
                ($isAlaCarte && !empty($secondaryServiceIds)) ? $secondaryServiceIds : []
            )));
            $coatServices = Service::whereIn('id', $coatFeeServiceIds)->get();
            foreach ($coatServices as $coatService) {
                if ((bool) $coatService->is_double_coated) {
                    $coatPrice = floatval($coatService->coat_type_price ?? 0);
                    $estimatedTotal += $coatPrice;
                    $totalCoatTypePrice += $coatPrice;
                    $coatPriceApplied = true;
                }
            }
        }

        $appointment->estimated_price = $estimatedTotal;
        $appointment->coat_price_applied = $coatPriceApplied;
        $appointment->total_coat_type_price = floatval($totalCoatTypePrice);

        $is_paid = false;
        $appointmentService = Service::find($appointment->service_id);
        if ($appointmentService && (isGroupClassService($appointmentService) || isPackageService($appointmentService))) {
            $is_paid = true;
        }
        $appointment->is_paid = $is_paid;

        $checkin = Checkin::where('appointment_id', $appointment->id)->first();
        $appointment->checkin = $checkin;

        $process = Process::with(['staff'])->where('appointment_id', $appointment->id)->get();
        $appointment->process = $process;

        $checkout = Checkout::where('appointment_id', $appointment->id)->first();
        $appointment->checkout = $checkout;

        if (!isset($appointment->coat_price_applied)) {
            $appointment->coat_price_applied = false;
        }

        if (!isset($appointment->total_coat_type_price)) {
            $appointment->total_coat_type_price = 0;
        }

        return response()->json([
            'status' => true,
            'message' => 'Appointment status updated to in_progress.',
            'result' => $appointment
        ], 200);
    }

    public function checkout($id)
    {
        $appointment = Appointment::with(['invoice'])->find($id);
        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found.',
                'result' => null
            ], 200);
        }

        $service = Service::with(['category'])->find($appointment->service_id);
        if (!$service) {
            return response()->json([
                'status' => false,
                'message' => 'Service not found for this appointment.',
                'result' => null
            ], 200);
        }
        $service->avatar_img_url = empty($service->avatar_img) ? '' : asset('storage/services/' . $service->avatar_img);
        $appointment->service = $service;

        $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
        $appointment->additionalServices = Service::whereIn('id', $additionalServiceIds)->get();

        return response()->json([
            'status' => true,
            'message' => 'Appointment checkout details retrieved successfully.',
            'result' => $appointment
        ], 200);
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
}
