<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Service;
use App\Models\Checkin;
use App\Models\Process;
use App\Models\Checkout;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\PetProfile;
use App\Models\PetInitialTemperament;
use App\Models\GroupClass;
use App\Models\CustomerPackage;
use App\Models\Package;
use App\Models\Discount;
use App\Models\PetBehavior;
use App\Models\IncidentReport;
use App\Models\Kennel;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController extends Controller
{
    private function syncBoardingFleaTickInvoiceItem(Appointment $appointment, float $fleaTickFee): void
    {
        $invoice = Invoice::where('appointment_id', $appointment->id)->first();
        if ($invoice) {
            $invoice->setRelation('items', collect(dedupeBoardingAutoFeeInvoiceItems($invoice->items ?? []))->values());
        }
        if (!$invoice) {
            return;
        }

        $fleaItems = InvoiceItem::where('invoice_id', $invoice->id)
            ->where('item_type', 'service')
            ->whereIn('item_name', ['Flea/Tick Fee', 'Flea/Tick Detection Fee'])
            ->get();

        if ($fleaTickFee > 0) {
            $existingItem = $fleaItems->first();
            if ($existingItem) {
                $existingItem->item_name = 'Flea/Tick Detection Fee';
                $existingItem->price = $fleaTickFee;
                $existingItem->save();

                if ($fleaItems->count() > 1) {
                    InvoiceItem::whereIn('id', $fleaItems->skip(1)->pluck('id')->all())->delete();
                }
            } else {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => 'Flea/Tick Detection Fee',
                    'price' => $fleaTickFee,
                    'item_type' => 'service',
                ]);
            }
        } else {
            if ($fleaItems->isNotEmpty()) {
                InvoiceItem::whereIn('id', $fleaItems->pluck('id')->all())->delete();
            }
        }
    }

    private function syncBoardingFleaTickFromProcessFlows(Appointment $appointment, array $processFlows): void
    {
        if (!isBoardingService($appointment->service)) {
            return;
        }

        $fleaTickData = $processFlows['check_pet']['flea_tick_data'] ?? [];
        if (!is_array($fleaTickData)) {
            return;
        }

        $checkin = Checkin::where('appointment_id', $appointment->id)->first();
        if (!$checkin) {
            $checkin = new Checkin();
            $checkin->appointment_id = $appointment->id;
        }

        $existingCheckinFlows = [];
        if (!empty($checkin->flows)) {
            $decodedExistingCheckinFlows = json_decode($checkin->flows, true);
            $existingCheckinFlows = is_array($decodedExistingCheckinFlows) ? $decodedExistingCheckinFlows : [];
        }

        $updatedCheckinFlows = $existingCheckinFlows;
        $petSpecific = isset($updatedCheckinFlows['pet_specific']) && is_array($updatedCheckinFlows['pet_specific'])
            ? $updatedCheckinFlows['pet_specific']
            : [];

        $pets = $appointment->familyPets ?? collect();
        if ($pets->isEmpty() && $appointment->pet) {
            $pets = collect([$appointment->pet]);
        }
        if ($pets->isEmpty()) {
            return;
        }

        $isFamilyAppointment = $pets->count() > 1;

        foreach ($pets as $pet) {
            if (!$pet) {
                continue;
            }

            $petIdKey = (string) $pet->id;
            $workflowKey = $isFamilyAppointment ? $petIdKey : (string) $appointment->id;

            $workflowFleaTick = $fleaTickData[$workflowKey] ?? ($fleaTickData[(int) $workflowKey] ?? null);
            if ($workflowFleaTick === null) {
                continue;
            }

            $existingPetFlows = $petSpecific[$petIdKey] ?? ($petSpecific[$pet->id] ?? []);
            if (!is_array($existingPetFlows)) {
                $existingPetFlows = [];
            }

            $existingPetFlows['flea_tick_detected'] = boardingValueIsTruthy($workflowFleaTick);
            $petSpecific[$petIdKey] = $existingPetFlows;
        }

        $updatedCheckinFlows['pet_specific'] = $petSpecific;

        $previousFleaTickAmount = floatval(getBoardingFleaTickBreakdown($appointment, $existingCheckinFlows)['amount'] ?? 0);
        $currentFleaTickAmount = floatval(getBoardingFleaTickBreakdown($appointment, $updatedCheckinFlows)['amount'] ?? 0);

        $checkin->flows = json_encode($updatedCheckinFlows);
        $checkin->save();

        $baseEstimatedPrice = max(0, floatval($appointment->estimated_price ?? 0) - $previousFleaTickAmount);
        $appointment->estimated_price = round($baseEstimatedPrice + $currentFleaTickAmount, 2);
        $appointment->save();

        $this->syncBoardingFleaTickInvoiceItem($appointment, $currentFleaTickAmount);
    }

    private function resolveBoardingLateCheckoutDaycareFeeForDisplay(Appointment $appointment, ?Checkout $checkout = null): float
    {
        if (($appointment->status ?? null) !== 'completed') {
            return 0;
        }

        $checkoutFlows = [];
        if ($checkout && !empty($checkout->flows)) {
            $checkoutFlows = is_array($checkout->flows)
                ? $checkout->flows
                : (json_decode($checkout->flows, true) ?: []);
        }

        $persistedAppliedFee = floatval($checkoutFlows['applied_late_checkout_daycare_fee'] ?? 0);
        if ($persistedAppliedFee > 0) {
            return $persistedAppliedFee;
        }

        $breakdown = getBoardingLateCheckoutDaycareBreakdown($appointment, $checkout, 1);
        return floatval($breakdown['fee'] ?? 0);
    }

    public function index(Request $request)
    {
        $active = 'dashboard';
        
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        
        $todayRevenue = $this->calculatePaidRevenueForRange($today, $today);
        $yesterdayRevenue = $this->calculatePaidRevenueForRange($yesterday, $yesterday);
        
        $percentageChange = 0;
        if ($yesterdayRevenue > 0) {
            $percentageChange = (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;
        } elseif ($todayRevenue > 0) {
            $percentageChange = 100;
        }
        
        $percentageChange = round($percentageChange, 1);
        
        $totalCustomers = User::whereHas('roles', function ($query) {
            $query->where('title', 'customer');
        })->count();
        
        $yesterdayCustomers = User::whereHas('roles', function ($query) {
            $query->where('title', 'customer');
        })->whereDate('created_at', $yesterday)->count();
        
        $todayNewCustomers = User::whereHas('roles', function ($query) {
            $query->where('title', 'customer');
        })->whereDate('created_at', $today)->count();
        
        $customerPercentageChange = 0;
        if ($yesterdayCustomers > 0) {
            $customerPercentageChange = (($todayNewCustomers - $yesterdayCustomers) / $yesterdayCustomers) * 100;
        } elseif ($todayNewCustomers > 0) {
            $customerPercentageChange = 100;
        }
        
        $customerPercentageChange = round($customerPercentageChange, 1);
        
        $totalPets = PetProfile::count();
        
        $todayNewPets = PetProfile::whereDate('created_at', $today)->count();
        $yesterdayNewPets = PetProfile::whereDate('created_at', $yesterday)->count();
        
        $petPercentageChange = 0;
        if ($yesterdayNewPets > 0) {
            $petPercentageChange = (($todayNewPets - $yesterdayNewPets) / $yesterdayNewPets) * 100;
        } elseif ($todayNewPets > 0) {
            $petPercentageChange = 100;
        }
        
        $petPercentageChange = round($petPercentageChange, 1);
        
        $todayAppointments = Appointment::whereDate('date', $today)->count();

        $todayBoardingCheckins = $this->countBoardingPetsByDate('date', $today);
        $todayBoardingCheckouts = $this->countBoardingPetsByDate('end_date', $today);
        $dogsOnProperty = $this->countBoardingDogsOnProperty();
        $boardingServiceIds = Service::whereHas('category', function ($query) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
        })->pluck('id');
        $incidentReportServiceId = $boardingServiceIds->first();
        $todayBoardingIncidents = $boardingServiceIds->isNotEmpty()
            ? IncidentReport::whereIn('service_id', $boardingServiceIds)
                ->whereDate('created_at', $today)
                ->count()
            : 0;
        
        $yesterdayAppointments = Appointment::whereDate('date', $yesterday)->count();
        
        $appointmentPercentageChange = 0;
        if ($yesterdayAppointments > 0) {
            $appointmentPercentageChange = (($todayAppointments - $yesterdayAppointments) / $yesterdayAppointments) * 100;
        } elseif ($todayAppointments > 0) {
            $appointmentPercentageChange = 100;
        }
        
        $appointmentPercentageChange = round($appointmentPercentageChange, 1);
        
        $recentAppointments = Appointment::with(['pet', 'customer.profile', 'service', 'invoice.items'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentAppointments as $appointment) {
            $isBoarding = isBoardingService($appointment->service);
            $stateTaxRate = $isBoarding ? floatval(config('billing.state_tax_rate', 7)) : 0;

            if ($appointment->invoice && $appointment->invoice->items && $appointment->invoice->items->count() > 0) {
                $itemsSubtotal = floatval($appointment->invoice->items->sum('price'));
                $discountAmount = floatval($appointment->invoice->discount_amount ?? 0);
                $subtotalAfterDiscount = max(0, $itemsSubtotal - $discountAmount);
                $appointment->total_price = $subtotalAfterDiscount + ($subtotalAfterDiscount * ($stateTaxRate / 100));
            } else {
                $baseEstimatedPrice = floatval($appointment->estimated_price ?? 0);

                if ($isBoarding) {
                    $boardingPricing = getBoardingPricingBreakdown($appointment, null, $appointment->service);
                    $discountAmount = floatval($boardingPricing['family_discount_amount'] ?? 0);
                    $subtotalAfterDiscount = max(0, $baseEstimatedPrice - $discountAmount);
                    $appointment->total_price = $subtotalAfterDiscount + ($subtotalAfterDiscount * ($stateTaxRate / 100));
                } else {
                    $appointment->total_price = $baseEstimatedPrice;
                }
            }

            $familyPets = $appointment->familyPets ?? collect();
            if ($familyPets->isEmpty() && $appointment->pet) {
                $familyPets = collect([$appointment->pet]);
            }

            $appointment->display_pets = $familyPets->map(function ($pet) {
                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'pet_img' => $pet->pet_img,
                ];
            })->values();

            $petNames = $familyPets->pluck('name')->filter()->values();
            $appointment->display_pet_names = $petNames->isNotEmpty()
                ? $petNames->join(', ')
                : ($appointment->pet->name ?? 'N/A');

            $assignmentLocation = $this->getAppointmentAssignmentLocation($appointment);
            $appointment->assignment_label = $assignmentLocation['label'];
            $appointment->assignment_room_name = $assignmentLocation['room_name'];
            $appointment->assignment_kennel_name = $assignmentLocation['kennel_name'];
        }

        $treatmentListItems = $this->getDashboardTreatmentListItems($today);
        $checkoutDayAdditionalServiceItems = $this->getDashboardCheckoutDayAdditionalServiceItems($today);
        
        $period = $request->get('period', 'month');
        
        $revenueData = $this->getRevenueStatistics($period);
        
        return view('dashboard.index', compact('active', 'todayRevenue', 'yesterdayRevenue', 'percentageChange', 'totalCustomers', 'customerPercentageChange', 'todayNewCustomers', 'yesterdayCustomers', 'totalPets', 'petPercentageChange', 'todayNewPets', 'yesterdayNewPets', 'todayAppointments', 'todayBoardingCheckins', 'todayBoardingCheckouts', 'dogsOnProperty', 'todayBoardingIncidents', 'incidentReportServiceId', 'yesterdayAppointments', 'appointmentPercentageChange', 'recentAppointments', 'treatmentListItems', 'checkoutDayAdditionalServiceItems', 'revenueData', 'period'));
    }

    public function serviceDashboard(Request $request, $id)
    {
        $customerPet = $request->get('customer');
        $staffId = $request->get('staff');
        $service = Service::find($id);

        if ($customerPet) {
            $appointments = Appointment::where('service_id', $id)->where(function ($query) use ($customerPet) {
                $query->whereHas('customer', function ($q) use ($customerPet) {
                    $q->where('email', 'like', "%{$customerPet}%")
                        ->orWhereHas('profile', function ($q2) use ($customerPet) {
                            $q2->where('first_name', 'like', "%{$customerPet}%")
                                ->orWhere('last_name', 'like', "%{$customerPet}%");
                        });
                })->orWhereHas('pet', function ($q) use ($customerPet) {
                    $q->where('name', 'like', "%{$customerPet}%");
                });
            });
        } else {
            $appointments = Appointment::where('service_id', $id);
        }

        if ($staffId)
            $appointments = $appointments->where('staff_id', $staffId);

        $infoMessage = null;
        if (isGroupClassService($service)) {
            // Get only the first upcoming appointment per pet and all non-checked_in appointments for group classes
            $now = Carbon::now();

            // Get appointments with status != 'checked_in' (all statuses)
            $tempAppointments = clone $appointments;
            $nonCheckedInAppointments = $tempAppointments
                ->where('status', '!=', 'checked_in')
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Get future checked_in appointments
            $checkedInAppointments = $appointments
                ->where('status', 'checked_in')
                ->where('date', '>=', $now->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Group checked_in appointments by pet_id and take only the first (upcoming) per pet
            $firstCheckedInPerPet = $checkedInAppointments->groupBy('pet_id')->map(function ($petAppointments) {
                return $petAppointments->first();
            })->values();

            // Merge: all non-checked_in + first checked_in per pet
            $appointments = $nonCheckedInAppointments->merge($firstCheckedInPerPet)
                ->sortBy([
                    ['date', 'asc'],
                    ['start_time', 'asc']
                ])
                ->values();
            $infoMessage = 'Showing only the next upcoming appointment per pet in Scheduled. Additional future sessions exist and will appear at the completion of the current session.';
        } else {
            $appointments = $appointments
                ->with(['pet', 'customer.profile', 'staff.profile', 'catRoom', 'kennel'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        }

        $appointments->load(['pet', 'customer.profile', 'staff.profile', 'catRoom', 'kennel']);

        foreach ($appointments as $appointment) {
            $assignmentLocation = $this->getAppointmentAssignmentLocation($appointment);
            $appointment->assignment_label = $assignmentLocation['label'];
            $appointment->assignment_room_name = $assignmentLocation['room_name'];
            $appointment->assignment_kennel_name = $assignmentLocation['kennel_name'];
        }

        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->get();

        return view('dashboard.kanban-service', compact('appointments', 'customerPet', 'staffId', 'staffs', 'id', 'service', 'infoMessage'));
    }

    public function appointmentDetail($id)
    {
        $appointment = Appointment::with(['customer.profile', 'customer.appointmentCancellations.service', 'customer.appointmentCancellations.cancelledBy', 'pet.breed', 'pet.color', 'pet.coatType', 'pet.vaccinations', 'pet.certificates', 'service.category', 'staff.profile', 'catRoom', 'kennel'])
            ->find($id);
        $dbEstimatedPrice = $appointment->estimated_price ?? 0;

        $checkedIn = Checkin::where('appointment_id', $appointment->id)->first();
        if ($checkedIn && $checkedIn->flows) {
            $checkedIn->flows = json_decode($checkedIn->flows, true);
        }

        $resolveAppointmentServicePrice = function ($service, $petSize, $metadata = null) {
            if (!$service) {
                return 0;
            }

            return getServicePrice($service, $petSize, $metadata);
        };

        $isPetDoubleCoated = (bool) (optional(optional($appointment->pet)->coatType)->is_double_coated ?? false);

        $calculateCoatTypeExtraFee = function (array $serviceIds) use ($isPetDoubleCoated) {
            if (!$isPetDoubleCoated) {
                return 0;
            }

            $normalizedServiceIds = collect($serviceIds)
                ->filter(function ($serviceId) {
                    return !is_null($serviceId) && $serviceId !== '';
                })
                ->map(function ($serviceId) {
                    return (int) $serviceId;
                })
                ->filter(function ($serviceId) {
                    return $serviceId > 0;
                })
                ->unique()
                ->values();

            if ($normalizedServiceIds->isEmpty()) {
                return 0;
            }

            $services = Service::whereIn('id', $normalizedServiceIds)->get()->keyBy('id');
            $extraFee = 0;

            foreach ($normalizedServiceIds as $serviceId) {
                $service = $services->get($serviceId);
                if (!$service) {
                    continue;
                }

                if ((bool) $service->is_double_coated) {
                    $extraFee += floatval($service->coat_type_price ?? 0);
                }
            }

            return $extraFee;
        };

        $additionalServicesByPet = $appointment->additional_services_by_pet ?? [];
        $additionalServiceIds = $appointment->additional_service_ids_flat ?? [];
        $additionalServices = Service::whereIn('id', array_filter($additionalServiceIds))->get()->keyBy('id');
        $checkoutForLateFee = Checkout::where('appointment_id', $appointment->id)->first();
        $lateCheckoutDaycareFeeDisplay = 0;

        // Set up the estimated price of the appointment
        $computedEstimatedPrice = 0;
        if (isBoardingService($appointment->service)) {
            $pricingPets = $appointment->family_pets;

            if ($pricingPets->isEmpty() && $appointment->pet) {
                $pricingPets = collect([$appointment->pet]);
            }

            $boardingPricingBreakdown = getBoardingPricingBreakdown($appointment, null, $appointment->service);
            $boardingBaseTotal = floatval($boardingPricingBreakdown['boarding_subtotal'] ?? 0)
                + floatval($boardingPricingBreakdown['holiday_surcharge_amount'] ?? 0);
            $familyDiscountAmount = floatval($boardingPricingBreakdown['family_discount_amount'] ?? 0);

            $additionalServiceTotal = 0;
            foreach ($pricingPets as $pet) {
                $petSize = $pet->size ?? ($appointment->pet->size ?? 'medium');
                $petAdditionalServiceIds = collect($additionalServicesByPet[$pet->id ?? 0] ?? [])
                    ->map(fn ($serviceId) => (int) $serviceId)
                    ->filter(fn ($serviceId) => $serviceId > 0)
                    ->unique()
                    ->values();

                foreach ($petAdditionalServiceIds as $petAdditionalServiceId) {
                    $service = $additionalServices->get($petAdditionalServiceId);
                    if (!$service) {
                        continue;
                    }

                    $additionalServiceTotal += $resolveAppointmentServicePrice($service, $petSize);
                }
            }

            $checkedInFlowsForPricing = is_array(optional($checkedIn)->flows) ? $checkedIn->flows : [];
            $fleaTickFee = floatval(getBoardingFleaTickBreakdown($appointment, $checkedInFlowsForPricing)['amount'] ?? 0);
            $lateCheckoutDaycareFee = ($appointment->status === 'completed')
                ? $this->resolveBoardingLateCheckoutDaycareFeeForDisplay($appointment, $checkoutForLateFee)
                : 0;
            $lateCheckoutDaycareFeeDisplay = $lateCheckoutDaycareFee;

            $computedEstimatedPrice = max(0, $boardingBaseTotal + $additionalServiceTotal - $familyDiscountAmount + $fleaTickFee + $lateCheckoutDaycareFee);
        } else {
            $computedEstimatedPrice = $resolveAppointmentServicePrice($appointment->service, $appointment->pet->size, $appointment->metadata);

            foreach ($additionalServices as $service) {
                $computedEstimatedPrice += $resolveAppointmentServicePrice($service, $appointment->pet->size);
            }
        }

        $coatPricingServiceIds = [];
        $coatPricingServiceIds[] = $appointment->service_id;

        if (!empty($appointment->second_service_id ?? null)) {
            $coatPricingServiceIds[] = $appointment->second_service_id;
        }

        if (!empty($additionalServiceIds)) {
            $coatPricingServiceIds = array_merge($coatPricingServiceIds, $additionalServiceIds);
        }

        $computedEstimatedPrice += $calculateCoatTypeExtraFee($coatPricingServiceIds);

        $storedEstimatedPrice = floatval($appointment->estimated_price ?? 0);
        if (isBoardingService($appointment->service)) {
            $appointment->estimated_price = $computedEstimatedPrice;
        } else {
            $appointment->estimated_price = $storedEstimatedPrice > 0
                ? $storedEstimatedPrice
                : $computedEstimatedPrice;
        }
        $dbEstimatedPrice = $appointment->estimated_price;

        // Always compute coat extra fee for the line-item display row
        $coatFeeServiceIds = [$appointment->service_id];
        if (!empty($appointment->second_service_id ?? null)) {
            $coatFeeServiceIds[] = $appointment->second_service_id;
        }
        if (!empty($additionalServiceIds)) {
            $coatFeeServiceIds = array_merge($coatFeeServiceIds, $additionalServiceIds);
        }
        $coatExtraFee = $calculateCoatTypeExtraFee($coatFeeServiceIds);
        $appointment->coat_extra_fee = $coatExtraFee > 0 ? $coatExtraFee : null;

        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->with('profile')->get();

        // For boarding services, try to load process for the appointment date
        if (isBoardingService($appointment->service)) {
            $process = Process::where('appointment_id', $appointment->id)
                ->where('date', $appointment->date)
                ->first();
        } else {
            $process = Process::where('appointment_id', $appointment->id)->first();
        }

        if ($process && $process->flows) {
            $process->flows = json_decode($process->flows, true);
        }

        $checkout = $checkoutForLateFee;

        if ($checkout && $checkout->flows) {
            $checkout->flows = json_decode($checkout->flows, true);
        }

        $invoice = Invoice::where('appointment_id', $appointment->id)->first();

        $additionalServices = Service::where('id', '!=', $appointment->service_id)->where('status', 'active')->get();

        $lastAppointmentRatings = [];

        $invoiceDiscountRules = getScopedDiscountsForCustomerAndService($appointment->customer_id, $appointment->service_id)
            ->map(function ($discount) {
                return [
                    'id' => $discount->id,
                    'title' => $discount->title,
                    'type' => $discount->type,
                    'amount' => floatval($discount->amount ?? 0),
                    'start_date' => $discount->start_date ? Carbon::parse($discount->start_date)->toIso8601String() : null,
                    'end_date' => $discount->end_date ? Carbon::parse($discount->end_date)->toIso8601String() : null,
                ];
            })
            ->values();

        $boardingPricing = isBoardingService($appointment->service)
            ? getBoardingPricingBreakdown($appointment, null, $appointment->service)
            : null;

        if (($boardingPricing['family_discount_amount'] ?? 0) > 0) {
            $invoiceDiscountRules = collect([[
                'id' => 'boarding-family-discount',
                'title' => $boardingPricing['family_discount_title'] ?? 'Multi-Pet Discount',
                'type' => 'fixed',
                'amount' => floatval($boardingPricing['family_discount_amount']),
                'start_date' => null,
                'end_date' => null,
            ]]);
        }

        $petBehaviors = PetBehavior::with('icon')->orderBy('description')->get();
        $assignmentLocation = $this->getAppointmentAssignmentLocation($appointment);
        $assignmentLabel = $assignmentLocation['label'];
        $staySummary = $this->buildCheckoutStaySummary($appointment, $checkedIn);

        return view('dashboard.appointment', compact('appointment', 'staffs', 'checkedIn', 'process', 'checkout', 'invoice', 'additionalServices', 'lastAppointmentRatings', 'invoiceDiscountRules', 'petBehaviors', 'dbEstimatedPrice', 'assignmentLabel', 'staySummary', 'lateCheckoutDaycareFeeDisplay'));
    }

    private function buildCheckoutStaySummary(Appointment $appointment, ?Checkin $checkin): array
    {
        $pets = $appointment->family_pets;
        if ($pets->isEmpty() && $appointment->pet) {
            $pets = collect([$appointment->pet]);
        }

        if ($pets->isEmpty()) {
            return [
                'has_data' => false,
                'pet_count' => 0,
                'pets' => [],
            ];
        }

        $isFamilyAppointment = $pets->count() > 1;
        $checkinFlows = $this->decodeDashboardJsonToArray(optional($checkin)->flows);
        $petSpecificFlows = isset($checkinFlows['pet_specific']) && is_array($checkinFlows['pet_specific'])
            ? $checkinFlows['pet_specific']
            : [];

        $processes = Process::where('appointment_id', $appointment->id)
            ->orderBy('date')
            ->orderBy('updated_at')
            ->get();

        $decodedProcessFlows = $processes->map(function (Process $process) {
            return [
                'date' => $process->date,
                'notes' => trim((string) ($process->notes ?? '')),
                'flows' => $this->decodeDashboardJsonToArray($process->flows),
            ];
        });

        $incidentQuery = IncidentReport::where('service_id', $appointment->service_id)
            ->where(function ($query) use ($appointment, $pets) {
                $query->whereRaw('FIND_IN_SET(?, appointment_ids)', [(string) $appointment->id]);

                foreach ($pets as $pet) {
                    if (!empty($pet?->id)) {
                        $query->orWhereRaw('FIND_IN_SET(?, pet_ids)', [(string) $pet->id]);
                    }
                }
            });

        if (!empty($appointment->date)) {
            $rangeStart = Carbon::parse($appointment->date)->startOfDay();
            $rangeEnd = !empty($appointment->end_date)
                ? Carbon::parse($appointment->end_date)->endOfDay()
                : Carbon::parse($appointment->date)->endOfDay();

            $incidentQuery->whereBetween('created_at', [$rangeStart, $rangeEnd]);
        }

        $incidents = $incidentQuery
            ->orderByDesc('created_at')
            ->get();

        $behaviorIds = $pets->flatMap(function ($pet) {
            return $this->normalizeStaySummaryIds($pet->pet_behavior_id ?? []);
        })->unique()->values();

        $behaviorMap = $behaviorIds->isNotEmpty()
            ? PetBehavior::whereIn('id', $behaviorIds->all())->pluck('description', 'id')
            : collect();

        $petSummaries = $pets->map(function ($pet) use (
            $appointment,
            $isFamilyAppointment,
            $checkin,
            $checkinFlows,
            $petSpecificFlows,
            $decodedProcessFlows,
            $incidents,
            $behaviorMap
        ) {
            $petId = (int) ($pet->id ?? 0);
            $appointmentId = (int) $appointment->id;

            $workflowCandidates = collect([
                $isFamilyAppointment ? $petId : $appointmentId,
                $appointmentId,
                $petId,
            ])
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $petIdKey = (string) $petId;
            $petFlowOverrides = $petSpecificFlows[$petIdKey] ?? ($petSpecificFlows[$petId] ?? []);
            if (!is_array($petFlowOverrides)) {
                $petFlowOverrides = [];
            }

            $effectiveCheckinFlows = array_merge($checkinFlows, $petFlowOverrides);
            unset($effectiveCheckinFlows['pet_specific'], $effectiveCheckinFlows['pets_care']);

            $dnes = [];
            $noseToTailIssues = [];
            $treatments = [];
            $incidentRows = [];
            $notes = [];
            $prnMeds = [];

            if (!empty($checkin?->notes)) {
                $notes[] = 'Check-in: ' . trim((string) $checkin->notes);
            }

            foreach (['pet_notes', 'feeding_notes', 'medication_notes', 'rest_note'] as $notesKey) {
                $value = trim((string) ($effectiveCheckinFlows[$notesKey] ?? ''));
                if ($value !== '') {
                    $notes[] = ucfirst(str_replace('_', ' ', $notesKey)) . ': ' . $value;
                }
            }

            foreach ($decodedProcessFlows as $processData) {
                $flows = $processData['flows'] ?? [];
                if (!is_array($flows)) {
                    continue;
                }

                $reportsAm = $flows['reports_am'] ?? [];
                if ($this->staySummaryStepAppliesToWorkflow($reportsAm, $workflowCandidates)) {
                    $amIssue = trim((string) $this->staySummaryGetFlowValueForCandidates($reportsAm['issues'] ?? [], $workflowCandidates));
                    $dnes[] = $amIssue !== '' ? 'Do not eat AM meals - ' . $amIssue : 'Do not eat AM meals';
                }

                $reportsPm = $flows['reports_pm'] ?? [];
                if ($this->staySummaryStepAppliesToWorkflow($reportsPm, $workflowCandidates)) {
                    $pmIssue = trim((string) $this->staySummaryGetFlowValueForCandidates($reportsPm['issues'] ?? [], $workflowCandidates));
                    $dnes[] = $pmIssue !== '' ? 'Do not eat PM meals - ' . $pmIssue : 'Do not eat PM meals';
                }

                $checkData = $this->staySummaryGetFlowValueForCandidates(data_get($flows, 'check_pet.check_data', []), $workflowCandidates);
                if (is_array($checkData)) {
                    $noseToTailIssues = array_merge($noseToTailIssues, $this->buildStaySummaryNoseToTailIssues($checkData));
                }

                $treatmentPlanData = $this->staySummaryGetFlowValueForCandidates(data_get($flows, 'treatment_plan.treatment_data', []), $workflowCandidates);
                if (is_array($treatmentPlanData)) {
                    $treatmentPlanText = $this->buildStaySummaryTreatmentText($treatmentPlanData);
                    if ($treatmentPlanText !== '') {
                        $treatments[] = $treatmentPlanText;
                    }
                }

                $treatmentResult = $this->staySummaryGetFlowValueForCandidates(data_get($flows, 'treatments_tlr.results', []), $workflowCandidates);
                if (is_array($treatmentResult)) {
                    $resultValue = strtolower(trim((string) ($treatmentResult['result'] ?? '')));
                    $resultLabel = match ($resultValue) {
                        'continue' => 'Continue',
                        'resolved' => 'Resolved',
                        'escalate' => 'Escalate',
                        default => '',
                    };
                    $resultDetail = trim((string) ($treatmentResult['detail'] ?? ''));
                    if ($resultLabel !== '' || $resultDetail !== '') {
                        $treatments[] = trim('Outcome: ' . $resultLabel . ($resultDetail !== '' ? ' - ' . $resultDetail : ''));
                    }
                }

                $treatmentIssues = $flows['treatment_issues'] ?? [];
                if (is_array($treatmentIssues)) {
                    foreach ($treatmentIssues as $issue) {
                        if (!is_array($issue)) {
                            continue;
                        }
                        $inHouse = trim((string) ($issue['inhouse_treatment'] ?? ''));
                        $vet = trim((string) ($issue['vet_treatment'] ?? ''));
                        if ($inHouse !== '') {
                            $treatments[] = 'In-house: ' . $inHouse;
                        }
                        if ($vet !== '') {
                            $treatments[] = 'Vet: ' . $vet;
                        }
                    }
                }

                if (!empty($processData['notes'])) {
                    $processDate = trim((string) ($processData['date'] ?? ''));
                    $label = $processDate !== '' ? 'Process (' . $processDate . ')' : 'Process';
                    $notes[] = $label . ': ' . $processData['notes'];
                }

                $prnRecord = $this->staySummaryGetFlowValueForCandidates(data_get($flows, 'prn_meds.prn_records', []), $workflowCandidates);
                if (is_array($prnRecord)) {
                    $medName = trim((string) ($prnRecord['medication_name'] ?? ''));
                    $amount = trim((string) ($prnRecord['amount'] ?? ''));
                    if ($medName !== '') {
                        $prnMeds[] = $medName . ($amount !== '' ? ' - ' . $amount : '');
                    }
                }
            }

            foreach ($incidents as $incident) {
                $incidentPetIds = $this->normalizeStaySummaryIds($incident->pet_ids ?? []);
                $incidentAppointmentIds = $this->normalizeStaySummaryIds($incident->appointment_ids ?? []);

                $matchesPet = in_array($petId, $incidentPetIds, true);
                $matchesAppointment = !$matchesPet
                    && in_array($appointmentId, $incidentAppointmentIds, true)
                    && count($incidentPetIds) === 0;

                if (!$matchesPet && !$matchesAppointment) {
                    continue;
                }

                $incidentRows[] = $this->buildStaySummaryIncidentText($incident);
            }

            $behavior = collect($this->normalizeStaySummaryIds($pet->pet_behavior_id ?? []))
                ->map(fn ($behaviorId) => trim((string) ($behaviorMap->get($behaviorId) ?? '')))
                ->filter()
                ->values()
                ->all();

            $dnes = collect($dnes)->filter()->unique()->values()->all();
            $noseToTailIssues = collect($noseToTailIssues)->filter()->unique()->values()->all();
            $treatments = collect($treatments)->filter()->unique()->values()->all();
            $incidentRows = collect($incidentRows)->filter()->unique()->values()->all();
            $notes = collect($notes)->filter()->unique()->values()->all();
            $prnMeds = collect($prnMeds)->filter()->values()->all();

            $hasData = !empty($dnes)
                || !empty($noseToTailIssues)
                || !empty($treatments)
                || !empty($incidentRows)
                || !empty($behavior)
                || !empty($notes)
                || !empty($prnMeds);

            return [
                'pet_id' => $petId,
                'pet_name' => $pet->name ?? 'Pet',
                'dnes' => $dnes,
                'nose_to_tail' => $noseToTailIssues,
                'treatments' => $treatments,
                'prn_meds' => $prnMeds,
                'incidents' => $incidentRows,
                'behavior' => $behavior,
                'notes' => $notes,
                'has_data' => $hasData,
            ];
        })->values();

        return [
            'has_data' => $petSummaries->contains(fn ($summary) => !empty($summary['has_data'])),
            'pet_count' => $pets->count(),
            'pets' => $petSummaries->all(),
        ];
    }

    private function staySummaryStepAppliesToWorkflow($stepData, array $workflowCandidates): bool
    {
        if (!is_array($stepData)) {
            return false;
        }

        $selectedIds = $this->normalizeStaySummaryIds($stepData['selected_pet_ids'] ?? []);
        if (empty($selectedIds)) {
            return true;
        }

        return collect($workflowCandidates)->intersect($selectedIds)->isNotEmpty();
    }

    private function staySummaryGetFlowValueForCandidates($value, array $workflowCandidates)
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($workflowCandidates as $candidate) {
            if (array_key_exists($candidate, $value)) {
                return $value[$candidate];
            }

            $candidateKey = (string) $candidate;
            if (array_key_exists($candidateKey, $value)) {
                return $value[$candidateKey];
            }
        }

        return null;
    }

    private function buildStaySummaryNoseToTailIssues(array $checkData): array
    {
        $bodyPartLabels = [
            'nose' => 'Nose',
            'eyes' => 'Eyes',
            'ears' => 'Ears',
            'mouth' => 'Mouth',
            'body_coat' => 'Body/Coat',
            'paws_feet' => 'Paws/Feet',
            'abdomen' => 'Abdomen',
            'digestive' => 'Digestive',
            'diarrhea' => 'Diarrhea',
        ];

        $issueFieldMap = [
            'nose' => ['discharge', 'dryness', 'cracking'],
            'eyes' => ['redness', 'cloudiness', 'discharge'],
            'ears' => ['odor', 'redness', 'swelling', 'buildup'],
            'mouth' => ['bad_breath', 'broken_teeth', 'growths', 'inflammation'],
            'body_coat' => ['skin_irritation', 'flaky_skin', 'flea_dirt'],
            'paws_feet' => ['cracked_pads', 'nail_issue', 'nail_bleeding', 'splinter', 'growths', 'injury'],
            'abdomen' => ['bloating', 'skin_irritation', 'growths', 'fleas_ticks'],
            'digestive' => ['vomiting', 'dne', 'dpe'],
            'diarrhea' => ['type_1', 'type_2', 'type_3', 'type_4', 'type_5', 'type_6', 'type_7'],
        ];

        $issues = [];
        foreach ($bodyPartLabels as $partKey => $partLabel) {
            $partData = $checkData[$partKey] ?? null;
            if (!is_array($partData)) {
                continue;
            }

            $status = strtolower(trim((string) ($partData['status'] ?? '')));
            if ($status !== 'issue') {
                continue;
            }

            $details = collect($issueFieldMap[$partKey] ?? [])
                ->filter(function ($field) use ($partData) {
                    return ($partData[$field] ?? false) === true || ($partData[$field] ?? false) === 'true';
                })
                ->map(function ($field) {
                    return ucwords(str_replace('_', ' ', $field));
                })
                ->values()
                ->all();

            $issues[] = !empty($details)
                ? $partLabel . ' (' . implode(', ', $details) . ')'
                : $partLabel;
        }

        return $issues;
    }

    private function buildStaySummaryTreatmentText(array $treatmentPlanData): string
    {
        $option = trim((string) ($treatmentPlanData['option'] ?? ''));
        $optionLabel = match ($option) {
            'in-house' => 'In-house',
            'vet-watch' => 'Vet watch',
            default => $option,
        };

        $selectedTreatments = $this->extractDashboardTreatmentSelections($treatmentPlanData);
        $detail = trim((string) ($treatmentPlanData['detail'] ?? $treatmentPlanData['details'] ?? ''));

        $parts = [];
        if ($optionLabel !== '') {
            $parts[] = 'Option: ' . $optionLabel;
        }
        if (!empty($selectedTreatments)) {
            $parts[] = 'Treatment: ' . implode(', ', $selectedTreatments);
        }
        if ($detail !== '') {
            $parts[] = 'Detail: ' . $detail;
        }

        return implode(' - ', $parts);
    }

    private function buildStaySummaryIncidentText(IncidentReport $incident): string
    {
        $parts = [];

        $injuryType = trim((string) ($incident->injury_type ?? ''));
        if ($injuryType !== '') {
            $parts[] = 'Injury: ' . ucwords(str_replace('_', ' ', $injuryType));
        }

        $injuryLocation = trim((string) ($incident->injury_location ?? ''));
        if ($injuryLocation !== '') {
            $parts[] = 'Location: ' . $injuryLocation;
        }

        $description = trim((string) ($incident->incident_description ?? ''));
        if ($description !== '') {
            $parts[] = 'Description: ' . $description;
        }

        $conclusion = trim((string) ($incident->conclusion ?? ''));
        if ($conclusion !== '') {
            $parts[] = 'Conclusion: ' . $conclusion;
        }

        return !empty($parts)
            ? implode(' - ', $parts)
            : 'Incident report #' . $incident->id;
    }

    private function normalizeStaySummaryIds($raw): array
    {
        if ($raw instanceof Collection) {
            $raw = $raw->all();
        }

        if (is_string($raw)) {
            $trimmedRaw = trim($raw);
            if ($trimmedRaw === '') {
                return [];
            }

            $decoded = json_decode($trimmedRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = explode(',', $trimmedRaw);
            }
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        return collect($raw)
            ->map(function ($value) {
                if (is_array($value)) {
                    return $value['id'] ?? null;
                }

                return $value;
            })
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function decodeDashboardJsonToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decodedValue = json_decode($value, true);
        return is_array($decodedValue) ? $decodedValue : [];
    }

    public function listDashboard(Request $request, $id)
    {
        $service = Service::find($id);
        $infoMessage = null;
        if (isGroupClassService($service)) {
            // Apply same logic as serviceDashboard for group classes
            $now = Carbon::now();

            // Get future checked_in appointments for group classes
            $allCheckedInAppointments = Appointment::where('service_id', $id)
                ->where('status', 'checked_in')
                ->where('date', '>=', $now->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Group by pet_id and take only the first (upcoming) per pet
            $checkedInAppointments = $allCheckedInAppointments->groupBy('pet_id')->map(function ($petAppointments) {
                return $petAppointments->first();
            })->values();
            $infoMessage = 'Showing only the next upcoming appointment per pet in Scheduled. Additional future sessions exist and will appear at the completion of the current session.';
        } else {
            $checkedInAppointments = Appointment::where('service_id', $id)
                ->where('status', 'checked_in')
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        }
        $inProgressAppointments = Appointment::where('service_id', $id)->where('status', 'in_progress')->orderBy('date', 'desc')->orderBy('start_time', 'desc')->get();
        $completedAppointments = Appointment::where('service_id', $id)->where('status', 'completed')->orderBy('date', 'desc')->orderBy('start_time', 'desc')->limit(5)->get();
        $completedCount = Appointment::where('service_id', $id)->where('status', 'completed')->count();

        $issuedAppointments = collect();
        if ($service->category && str_contains(strtolower($service->category->name), 'daycare')) {
            $issuedAppointments = Appointment::where('service_id', $id)
                ->where('status', 'issue')
                ->with(['pet', 'customer.profile', 'staff.profile'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        }

        return view('dashboard.list-service', compact('checkedInAppointments', 'inProgressAppointments', 'completedAppointments', 'completedCount', 'id', 'service', 'issuedAppointments', 'infoMessage'));
    }


    public function completedAppointments(Request $request)
    {
        $completedAppointments = Appointment::where('status', 'completed')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate(20);

        return view('dashboard.completed', compact('completedAppointments'));
    }

    private function calculateFinalPaidInvoiceAmount(Invoice $invoice): float
    {
        $itemsSubtotal = floatval($invoice->items->sum('price'));
        $discountAmount = floatval($invoice->discount_amount ?? 0);
        $subtotalAfterDiscount = max(0, $itemsSubtotal - $discountAmount);

        $appointmentService = optional(optional($invoice->appointment)->service);
        $stateTaxRate = isBoardingService($appointmentService)
            ? floatval(config('billing.state_tax_rate', 7))
            : 0;

        return $subtotalAfterDiscount + ($subtotalAfterDiscount * ($stateTaxRate / 100));
    }

    private function calculatePaidRevenueForRange(Carbon $startDate, Carbon $endDate): float
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->endOfDay();

        // Primary source of truth: actual recorded payments.
        $transactions = Transaction::query()
            ->whereNotNull('invoice_id')
            ->whereBetween('tran_date', [$rangeStart, $rangeEnd])
            ->get();

        $transactionRevenue = floatval($transactions->sum(function (Transaction $transaction) {
            return floatval($transaction->amount ?? 0);
        }));

        // Fallback for legacy paid invoices that do not have payment transactions.
        $invoiceQuery = Invoice::with(['items', 'appointment.service'])
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$rangeStart, $rangeEnd]);

        $transactionInvoiceIds = $transactions->pluck('invoice_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($transactionInvoiceIds)) {
            $invoiceQuery->whereNotIn('id', $transactionInvoiceIds);
        }

        $legacyInvoiceRevenue = $invoiceQuery->get()->sum(function (Invoice $invoice) {
            return $this->calculateFinalPaidInvoiceAmount($invoice);
        });

        return round($transactionRevenue + floatval($legacyInvoiceRevenue), 2);
    }
    
    private function getRevenueStatistics($period = 'month')
    {
        $today = Carbon::today();
        $data = [];
        $categories = [];
        $totalRevenue = 0;
        $previousPeriodRevenue = 0;
        
        if ($period === 'day') {
            for ($i = 6; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i);
                $revenue = $this->calculatePaidRevenueForRange($date, $date);
                
                $data[] = round($revenue, 2);
                $categories[] = $date->format('M j');
                $totalRevenue += $revenue;
            }
            
            $previousStart = $today->copy()->subDays(13);
            $previousEnd = $today->copy()->subDays(7);
            $previousPeriodRevenue = $this->calculatePaidRevenueForRange($previousStart, $previousEnd);
            
        } elseif ($period === 'week') {
            for ($i = 7; $i >= 0; $i--) {
                $weekStart = $today->copy()->subWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                $revenue = $this->calculatePaidRevenueForRange($weekStart, $weekEnd);
                
                $data[] = round($revenue, 2);
                $categories[] = $weekStart->format('M j');
                $totalRevenue += $revenue;
            }
            
            $previousStart = $today->copy()->subWeeks(15)->startOfWeek();
            $previousEnd = $today->copy()->subWeeks(8)->endOfWeek();
            $previousPeriodRevenue = $this->calculatePaidRevenueForRange($previousStart, $previousEnd);
            
        } else {
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = $today->copy()->subMonths($i)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $revenue = $this->calculatePaidRevenueForRange($monthStart, $monthEnd);
                
                $data[] = round($revenue, 2);
                $categories[] = $monthStart->format('M Y');
                $totalRevenue += $revenue;
            }
            
            $previousStart = $today->copy()->subMonths(23)->startOfMonth();
            $previousEnd = $today->copy()->subMonths(12)->endOfMonth();
            $previousPeriodRevenue = $this->calculatePaidRevenueForRange($previousStart, $previousEnd);
        }
        
        $percentageChange = 0;
        if ($previousPeriodRevenue > 0) {
            $percentageChange = (($totalRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100;
        } elseif ($totalRevenue > 0) {
            $percentageChange = 100;
        }
        
        return [
            'data' => $data,
            'categories' => $categories,
            'total' => $totalRevenue,
            'percentageChange' => round($percentageChange, 2),
            'period' => $period
        ];
    }

    private function getDashboardTreatmentListItems(Carbon $date)
    {
        $processes = Process::with(['appointment.pet', 'appointment.service.category'])
            ->whereDate('date', $date)
            ->whereHas('appointment.service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->get()
            ->keyBy('appointment_id');

        if ($processes->isEmpty()) {
            return collect();
        }

        $workflowData = $processes->reduce(function ($carry, Process $process) {
            $flows = $process->flows ? json_decode($process->flows, true) : [];
            if (!is_array($flows)) {
                return $carry;
            }

            return array_replace_recursive($carry, $flows);
        }, []);

        $yesterdayWorkflowData = $this->getDashboardYesterdayTreatmentWorkflowData($date, $processes->keys()->all());

        $treatmentAppointmentIds = $this->getDashboardTreatmentAppointmentIdsFromFlows($workflowData);

        $treatmentAppointmentIds = $treatmentAppointmentIds
            ->merge($this->getDashboardYesterdayTreatmentAppointmentIds($date, $processes->keys()->all()))
            ->map(fn ($appointmentId) => (int) $appointmentId)
            ->filter(fn ($appointmentId) => $appointmentId > 0 && $processes->has($appointmentId))
            ->unique()
            ->values();

        return $treatmentAppointmentIds->map(function (int $appointmentId) use ($processes, $workflowData, $yesterdayWorkflowData) {
            $process = $processes->get($appointmentId);
            if (!$process) {
                return null;
            }

            $treatmentPlanData = $this->getDashboardAppointmentFlowData(
                data_get($workflowData, 'treatment_plan.treatment_data', []),
                $appointmentId
            );
            $treatmentsTlrResult = $this->getDashboardAppointmentFlowData(
                data_get($workflowData, 'treatments_tlr.results', []),
                $appointmentId
            );

            if (empty($treatmentPlanData)) {
                $treatmentPlanData = $this->getDashboardAppointmentFlowData(
                    data_get($yesterdayWorkflowData, 'treatment_plan.treatment_data', []),
                    $appointmentId
                );
            }

            if (empty($treatmentsTlrResult)) {
                $treatmentsTlrResult = $this->getDashboardAppointmentFlowData(
                    data_get($yesterdayWorkflowData, 'treatments_tlr.results', []),
                    $appointmentId
                );
            }

            $treatments = $this->extractDashboardTreatmentSelections($treatmentPlanData);
            $planDetail = trim((string) ($treatmentPlanData['detail'] ?? $treatmentPlanData['details'] ?? ''));
            $resultDetail = trim((string) ($treatmentsTlrResult['detail'] ?? ''));
            $status = $this->mapDashboardTreatmentStatus($treatmentsTlrResult['result'] ?? null);

            return [
                'appointment_id' => $appointmentId,
                'pet_name' => optional($process->appointment?->pet)->name ?? 'N/A',
                'pet_img' => optional($process->appointment?->pet)->pet_img,
                'treatment_label' => !empty($treatments) ? implode(', ', $treatments) : 'Treatment',
                'detail' => $resultDetail !== '' ? $resultDetail : $planDetail,
                'status' => $status,
                'time' => $this->formatDashboardTime(
                    data_get($workflowData, 'treatment_plan.process_time')
                    ?: data_get($workflowData, 'check_pet.process_time')
                    ?: $process->start_time
                ),
                'sort_time' => data_get($workflowData, 'treatment_plan.process_time')
                    ?: data_get($workflowData, 'check_pet.process_time')
                    ?: $process->start_time,
            ];
        })->filter()->sortBy(function (array $item) {
            return $item['sort_time'] ?: '23:59:59';
        })->values();
    }

    private function getDashboardAppointmentFlowData($items, int $appointmentId): array
    {
        if (!is_array($items)) {
            return [];
        }

        $value = $items[$appointmentId] ?? $items[(string) $appointmentId] ?? [];

        return is_array($value) ? $value : [];
    }

    private function getDashboardTreatmentAppointmentIdsFromFlows(array $flows)
    {
        return $this->normalizeDashboardServiceIds(data_get($flows, 'treatment_plan.selected_pet_ids', []))
            ->merge($this->normalizeDashboardServiceIds(data_get($flows, 'reports_am.selected_pet_ids', [])))
            ->merge($this->normalizeDashboardServiceIds(data_get($flows, 'treatments_tlr.selected_pet_ids', [])))
            ->unique()
            ->values();
    }

    private function getDashboardYesterdayTreatmentAppointmentIds(Carbon $date, array $appointmentIds)
    {
        if (empty($appointmentIds)) {
            return collect();
        }

        $yesterdayProcesses = $this->getDashboardYesterdayProcesses($date, $appointmentIds);

        return $yesterdayProcesses->reduce(function ($carry, Process $process) {
            $flows = $process->flows ? json_decode($process->flows, true) : [];
            if (!is_array($flows)) {
                return $carry;
            }

            return $carry
                ->merge($this->normalizeDashboardServiceIds(data_get($flows, 'next_day_treatment_list_tlr.selected_pet_ids', [])))
                ->merge($this->normalizeDashboardServiceIds(data_get($flows, 'reports_pm.selected_pet_ids', [])));
        }, collect())->unique()->values();
    }

    private function getDashboardYesterdayTreatmentWorkflowData(Carbon $date, array $appointmentIds): array
    {
        return $this->getDashboardYesterdayProcesses($date, $appointmentIds)->reduce(function ($carry, Process $process) {
            $flows = $process->flows ? json_decode($process->flows, true) : [];
            if (!is_array($flows)) {
                return $carry;
            }

            return array_replace_recursive($carry, $flows);
        }, []);
    }

    private function getDashboardYesterdayProcesses(Carbon $date, array $appointmentIds)
    {
        if (empty($appointmentIds)) {
            return collect();
        }

        return Process::whereDate('date', $date->copy()->subDay())
            ->whereIn('appointment_id', $appointmentIds)
            ->get();
    }

    private function mapDashboardTreatmentStatus($status): string
    {
        return match ((string) $status) {
            'continue' => 'Continue',
            'resolved' => 'Resolved',
            'escalate' => 'Escalate',
            default => 'Pending',
        };
    }

    private function getDashboardCheckoutDayAdditionalServiceItems(Carbon $date)
    {
        $appointments = Appointment::with(['pet', 'service.category'])
            ->whereDate('end_date', $date)
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->get();

        if ($appointments->isEmpty()) {
            return collect();
        }

        $checkinsByAppointment = Checkin::whereIn('appointment_id', $appointments->pluck('id'))
            ->get()
            ->keyBy('appointment_id');

        $serviceIds = $appointments->flatMap(function (Appointment $appointment) use ($checkinsByAppointment) {
            $checkin = $checkinsByAppointment->get($appointment->id);
            $checkinFlows = $checkin && $checkin->flows ? json_decode($checkin->flows, true) : [];
            $checkinServices = is_array($checkinFlows)
                ? $this->normalizeDashboardServiceIds($checkinFlows['additional_services_link'] ?? [])
                : collect();

            $servicesByPet = collect($appointment->additional_services_by_pet ?? [])
                ->flatten()
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0);

            $legacyServices = $this->normalizeDashboardServiceIds($appointment->additional_service_ids ?? []);

            return $checkinServices
                ->merge($servicesByPet)
                ->merge($legacyServices);
        })->unique()->values();

        if ($serviceIds->isEmpty()) {
            return collect();
        }

        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

        return $appointments->flatMap(function (Appointment $appointment) use ($checkinsByAppointment, $serviceNames) {
            $checkin = $checkinsByAppointment->get($appointment->id);
            $checkinFlows = $checkin && $checkin->flows ? json_decode($checkin->flows, true) : [];
            $checkinServices = is_array($checkinFlows)
                ? $this->normalizeDashboardServiceIds($checkinFlows['additional_services_link'] ?? [])
                : collect();

            $familyPets = $appointment->familyPets ?? collect();
            $primaryPet = $appointment->pet ?: $familyPets->first();
            $familyPetMap = $familyPets->keyBy(fn ($pet) => (int) $pet->id);

            if ($primaryPet && !$familyPetMap->has((int) $primaryPet->id)) {
                $familyPetMap->put((int) $primaryPet->id, $primaryPet);
            }

            $additionalServicesByPet = collect($appointment->additional_services_by_pet ?? [])
                ->mapWithKeys(function ($serviceIdsForPet, $petId) {
                    $normalizedPetId = (int) $petId;
                    $normalizedServiceIds = collect(is_array($serviceIdsForPet) ? $serviceIdsForPet : [])
                        ->map(fn ($serviceId) => (int) $serviceId)
                        ->filter(fn ($serviceId) => $serviceId > 0)
                        ->unique()
                        ->values()
                        ->all();

                    return $normalizedPetId > 0 ? [$normalizedPetId => $normalizedServiceIds] : [];
                })
                ->filter(fn ($serviceIdsForPet) => !empty($serviceIdsForPet));

            if ($additionalServicesByPet->isEmpty()) {
                $legacyServiceIds = $checkinServices
                    ->merge($this->normalizeDashboardServiceIds($appointment->additional_service_ids ?? []))
                    ->unique()
                    ->values();

                if ($legacyServiceIds->isNotEmpty()) {
                    $fallbackPetIds = $familyPetMap->keys();
                    if ($fallbackPetIds->isEmpty() && $primaryPet) {
                        $fallbackPetIds = collect([(int) $primaryPet->id]);
                    }

                    if ($fallbackPetIds->isNotEmpty()) {
                        $additionalServicesByPet = $fallbackPetIds->mapWithKeys(function ($petId) use ($legacyServiceIds) {
                            return [(int) $petId => $legacyServiceIds->all()];
                        });
                    }
                }
            }

            if ($additionalServicesByPet->isEmpty()) {
                return collect();
            }

            $metadata = is_array($appointment->metadata) ? $appointment->metadata : [];
            $slotsByPet = is_array($metadata['additional_service_time_slots_by_pet'] ?? null)
                ? $metadata['additional_service_time_slots_by_pet']
                : [];
            $slotsByService = is_array($metadata['additional_service_time_slots'] ?? null)
                ? $metadata['additional_service_time_slots']
                : [];
            $legacySlotStartTime = $metadata['additional_service_time_slot_start_time'] ?? null;

            return $additionalServicesByPet->flatMap(function ($serviceIdsForPet, $petId) use ($appointment, $familyPetMap, $serviceNames, $slotsByPet, $slotsByService, $legacySlotStartTime, $primaryPet) {
                $petId = (int) $petId;
                $pet = $familyPetMap->get($petId) ?? $primaryPet;
                $petName = $pet->name ?? 'N/A';
                $petImg = $pet->pet_img ?? null;

                return collect($serviceIdsForPet)->map(function ($serviceId) use ($appointment, $petId, $petName, $petImg, $serviceNames, $slotsByPet, $slotsByService, $legacySlotStartTime) {
                    $serviceId = (int) $serviceId;
                    $slotDetails = data_get($slotsByPet, $petId . '.' . $serviceId, []);

                    if (!is_array($slotDetails) || empty($slotDetails)) {
                        $slotDetails = data_get($slotsByService, (string) $serviceId, []);
                    }

                    $slotStartTime = is_array($slotDetails) ? ($slotDetails['start_time'] ?? null) : null;
                    $displayTime = $slotStartTime ?: $legacySlotStartTime ?: $appointment->start_time;

                    return [
                        'appointment_id' => $appointment->id,
                        'pet_name' => $petName,
                        'pet_img' => $petImg,
                        'service_name' => $serviceNames->get($serviceId, 'Additional Service'),
                        'time' => $this->formatDashboardTime($displayTime),
                        'sort_time' => $slotStartTime ?: $legacySlotStartTime ?: $appointment->start_time,
                    ];
                });
            });
        })
        ->sortBy(function (array $item) {
            return $item['sort_time'] ?: '23:59:59';
        })
        ->values();
    }

    private function extractDashboardTreatmentSelections(array $treatmentPlanData): array
    {
        foreach (['additional_options', 'selected_treatments', 'selected_treatment', 'treatment'] as $key) {
            $value = $treatmentPlanData[$key] ?? null;

            if (is_array($value)) {
                return array_values(array_filter(array_map('trim', $value)));
            }

            if (is_string($value) && trim($value) !== '') {
                return [trim($value)];
            }
        }

        $singleValue = trim((string) ($treatmentPlanData['additional_option'] ?? ''));

        return $singleValue !== '' ? [$singleValue] : [];
    }

    private function normalizeDashboardServiceIds($raw)
    {
        if (is_array($raw)) {
            return collect($raw)
                ->map(fn ($serviceId) => (int) $serviceId)
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->values();
        }

        return collect(explode(',', (string) $raw))
            ->map(fn ($serviceId) => (int) trim($serviceId))
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->values();
    }

    private function formatDashboardTime($value): string
    {
        if (empty($value)) {
            return 'Any time';
        }

        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('H:i', (string) $value)->format('g:i A');
            } catch (\Exception $e) {
                try {
                    return Carbon::createFromFormat('H:i:s', (string) $value)->format('g:i A');
                } catch (\Exception $e) {
                    return 'Any time';
                }
            }
        }
    }

    private function countBoardingPetsByDate(string $dateColumn, Carbon $date): int
    {
        $appointments = Appointment::with('pet:id,type')
            ->whereDate($dateColumn, $date)
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->get();

        return $appointments->sum(function (Appointment $appointment) {
            $familyPetIds = collect($appointment->family_pet_ids)
                ->map(fn ($petId) => (int) $petId)
                ->filter(fn ($petId) => $petId > 0)
                ->unique()
                ->values();

            if ($familyPetIds->isNotEmpty()) {
                return PetProfile::whereIn('id', $familyPetIds)
                    ->whereIn('type', ['Dog', 'Cat'])
                    ->count();
            }

            $petType = optional($appointment->pet)->type;
            return in_array($petType, ['Dog', 'Cat'], true) ? 1 : 0;
        });
    }

    private function countBoardingDogsOnProperty(): int
    {
        $appointments = Appointment::with('pet:id,type')
            ->where('status', 'in_progress')
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->get();

        return $appointments->sum(function (Appointment $appointment) {
            $familyPetIds = collect($appointment->family_pet_ids)
                ->map(fn ($petId) => (int) $petId)
                ->filter(fn ($petId) => $petId > 0)
                ->unique()
                ->values();

            if ($familyPetIds->isNotEmpty()) {
                return PetProfile::whereIn('id', $familyPetIds)
                    ->where('type', 'Dog')
                    ->count();
            }

            $petType = optional($appointment->pet)->type;
            return $petType === 'Dog' ? 1 : 0;
        });
    }

    public function boardingProcessLog(Request $request)
    {
        $search = $request->get('search', '');
        
        $boardingAppointments = Appointment::with(['service.category', 'pet', 'customer.profile'])
            ->whereHas('service', function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
                });
            })
            ->get();
        $appointmentIds = $boardingAppointments->pluck('id');
        
        $processesQuery = Process::with(['appointment.pet', 'appointment.customer.profile'])
            ->whereIn('appointment_id', $appointmentIds)
            ->whereNotNull('date');
        
        if (!empty($search)) {
            $processesQuery->where(function ($q) use ($search) {
                $q->whereHas('appointment.pet', function ($query) use ($search) {
                    $query->where('name', 'LIKE', '%' . $search . '%');
                })->orWhereHas('appointment.customer.profile', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%' . $search . '%')
                          ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                });
            });
        }
        
        $processes = $processesQuery
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        $groupedProcesses = $processes->groupBy(function ($process) {
            return $process->date;
        })->map(function ($group) {
            $firstProcess = $group->first();
            
            $allTimes = [];
            foreach ($group as $process) {
                $flows = $process->flows ? json_decode($process->flows, true) : [];
                if (is_array($flows)) {
                    $timeFields = [
                        'am_meal_prep_time',
                        'am_med_prep_time',
                        'nose_tail_time',
                        'rest_1200_time',
                        'am_meal_dispense_time',
                        'am_med_dispense_time',
                        'pm_meal_prep_time',
                        'pm_med_prep_time',
                        'pm_meal_dispense_time',
                        'pm_med_dispense_time'
                    ];
                    
                    foreach ($timeFields as $field) {
                        if (!empty($flows[$field])) {
                            $allTimes[] = $flows[$field];
                        }
                    }
                    
                    foreach ($flows as $key => $value) {
                        if (strpos($key, 'additional_service_') === 0 && (strpos($key, '_start_time') !== false || strpos($key, '_end_time') !== false)) {
                            if (!empty($value)) {
                                $allTimes[] = $value;
                            }
                        }
                    }
                }
            }
            
            $earliestTime = null;
            $latestTime = null;
            
            if (!empty($allTimes)) {
                $validTimes = [];
                foreach ($allTimes as $time) {
                    $timeStr = is_string($time) ? $time : '';
                    if (empty($timeStr)) continue;
                    
                    if (strlen($timeStr) >= 5) {
                        try {
                            if (strlen($timeStr) >= 8) {
                                $carbon = \Carbon\Carbon::createFromFormat('H:i:s', $timeStr);
                            } else {
                                $carbon = \Carbon\Carbon::createFromFormat('H:i', $timeStr);
                            }
                            $validTimes[] = $carbon;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
                
                if (!empty($validTimes)) {
                    $earliestTime = collect($validTimes)->min();
                    $latestTime = collect($validTimes)->max();
                }
            }
            
            return [
                'staff_id' => $firstProcess->staff_id,
                'staff_name' => $firstProcess->staff ? 
                    ($firstProcess->staff->profile->first_name ?? '') . ' ' . ($firstProcess->staff->profile->last_name ?? '') : 
                    'Unknown Staff',
                'date' => $firstProcess->date,
                'earliest_time' => $earliestTime ? $earliestTime->format('H:i:s') : null,
                'latest_time' => $latestTime ? $latestTime->format('H:i:s') : null,
                'processes' => $group->map(function ($process) {
                    $appointment = $process->appointment;
                    $petName = 'N/A';
                    $customerName = 'N/A';
                    
                    if ($appointment) {
                        if ($appointment->pet) {
                            $petName = $appointment->pet->name ?? 'N/A';
                        }
                        if ($appointment->customer && $appointment->customer->profile) {
                            $firstName = $appointment->customer->profile->first_name ?? '';
                            $lastName = $appointment->customer->profile->last_name ?? '';
                            $customerName = trim($firstName . ' ' . $lastName) ?: 'N/A';
                        }
                    }
                    
                    return [
                        'id' => $process->id,
                        'appointment_id' => $process->appointment_id,
                        'pet_name' => $petName,
                        'customer_name' => $customerName,
                        'start_time' => $process->start_time,
                        'pickup_time' => $process->pickup_time,
                        'notes' => $process->notes,
                        'flows' => $process->flows ? json_decode($process->flows, true) : null,
                        'created_at' => $process->created_at,
                        'updated_at' => $process->updated_at,
                    ];
                })->values()
            ];
        })->values();
        
        return view('dashboard.boarding-process-log', compact('groupedProcesses'));
    }

    public function createBoardingProcessLog(Request $request)
    {
        $boardingAppointments = Appointment::with(['service.category', 'pet', 'customer.profile'])
            ->whereHas('service', function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
                });
            })
            ->where('status', 'in_progress')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        
        $staffs = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->with('profile')->get();
        
        return view('dashboard.boarding-process-log-create', compact('boardingAppointments', 'staffs'));
    }

    public function saveBoardingProcessLog(Request $request)
    {
        $request->validate([
            'staff_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'appointment_ids' => 'required|array|min:1',
            'appointment_ids.*' => 'exists:appointments,id',
            'flows' => 'required|array'
        ]);

        $staffId = $request->input('staff_id');
        $date = $request->input('date');
        $appointmentIds = $request->input('appointment_ids');
        $flows = $request->input('flows');

        $savedCount = 0;
        $errors = [];

        foreach ($appointmentIds as $appointmentId) {
            try {
                $appointment = Appointment::with('service.category')->find($appointmentId);
                if (!$appointment || !isBoardingService($appointment->service)) {
                    $errors[] = "Appointment ID {$appointmentId} is not a boarding service.";
                    continue;
                }

                $process = Process::where('appointment_id', $appointmentId)
                    ->where('date', $date)
                    ->first();

                if (!$process) {
                    $process = new Process;
                    $process->appointment_id = $appointmentId;
                    $process->date = $date;
                }

                if ($staffId) {
                    $process->staff_id = $staffId;
                }
                
                $existingFlows = $process->flows ? json_decode($process->flows, true) : [];
                if (!is_array($existingFlows)) {
                    $existingFlows = [];
                }
                $mergedFlows = array_merge($existingFlows, $flows);
                $process->flows = json_encode($mergedFlows);
                
                $process->save();

                $this->syncBoardingFleaTickFromProcessFlows($appointment, $mergedFlows);
                $savedCount++;
            } catch (\Exception $e) {
                $errors[] = "Error saving process for appointment ID {$appointmentId}: " . $e->getMessage();
            }
        }

        if ($savedCount > 0) {
            $message = "Successfully saved process logs for {$savedCount} pet(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }
            return response()->json([
                'success' => true,
                'message' => $message,
                'saved_count' => $savedCount,
                'errors' => $errors
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save any process logs.',
                'errors' => $errors
            ], 400);
        }
    }

    public function getBoardingCheckinData(Request $request)
    {
        $request->validate([
            'appointment_ids' => 'required|array|min:1',
            'appointment_ids.*' => 'exists:appointments,id'
        ]);

        $appointmentIds = $request->input('appointment_ids');
        $data = [];

        foreach ($appointmentIds as $appointmentId) {
            $appointment = Appointment::with(['pet', 'customer.profile'])->find($appointmentId);
            if (!$appointment) {
                continue;
            }

            $checkin = Checkin::where('appointment_id', $appointmentId)->first();
            $flows = null;

            if ($checkin && $checkin->flows) {
                $flows = json_decode($checkin->flows, true);
            }

            if (!is_array($flows)) {
                $flows = [];
            }

            $pets = $appointment->familyPets ?? collect();
            if ($pets->isEmpty() && $appointment->pet) {
                $pets = collect([$appointment->pet]);
            }

            if ($pets->isEmpty()) {
                continue;
            }

            $isFamilyAppointment = $pets->count() > 1;
            $petSpecific = isset($flows['pet_specific']) && is_array($flows['pet_specific']) ? $flows['pet_specific'] : [];
            $legacyPetsCare = isset($flows['pets_care']) && is_array($flows['pets_care']) ? $flows['pets_care'] : [];

            foreach ($pets as $pet) {
                if (!$pet) {
                    continue;
                }

                $petIdKey = (string) $pet->id;
                $petFlow = $petSpecific[$petIdKey] ?? ($petSpecific[$pet->id] ?? []);
                if (!is_array($petFlow)) {
                    $petFlow = [];
                }

                $legacyPetFlow = $legacyPetsCare[$petIdKey] ?? ($legacyPetsCare[$pet->id] ?? []);
                if (!is_array($legacyPetFlow)) {
                    $legacyPetFlow = [];
                }

                $effectiveFlows = array_merge($flows, $legacyPetFlow, $petFlow);
                unset($effectiveFlows['pet_specific'], $effectiveFlows['pets_care']);

                $dryFood = $effectiveFlows['dry_food'] ?? [];
                $wetFood = $effectiveFlows['wet_food'] ?? [];
                $dryFoodList = is_array($effectiveFlows['dry_food_list'] ?? null) ? $effectiveFlows['dry_food_list'] : [];
                $wetFoodList = is_array($effectiveFlows['wet_food_list'] ?? null) ? $effectiveFlows['wet_food_list'] : [];
                $meds = $effectiveFlows['meds'] ?? [];
                $medsList = is_array($effectiveFlows['meds_list'] ?? null) ? $effectiveFlows['meds_list'] : [];

                $lunchDry = ! empty($dryFood['dispense_lunch']) && ($dryFood['dispense_lunch'] === true || $dryFood['dispense_lunch'] === 'true');
                $lunchWet = ! empty($wetFood['dispense_lunch']) && ($wetFood['dispense_lunch'] === true || $wetFood['dispense_lunch'] === 'true');

                if (! $lunchDry && ! empty($dryFoodList)) {
                    foreach ($dryFoodList as $foodItem) {
                        if (! is_array($foodItem)) {
                            continue;
                        }

                        if (! empty($foodItem['dispense_lunch']) && ($foodItem['dispense_lunch'] === true || $foodItem['dispense_lunch'] === 'true')) {
                            $lunchDry = true;
                            break;
                        }
                    }
                }

                if (! $lunchWet && ! empty($wetFoodList)) {
                    foreach ($wetFoodList as $foodItem) {
                        if (! is_array($foodItem)) {
                            continue;
                        }

                        if (! empty($foodItem['dispense_lunch']) && ($foodItem['dispense_lunch'] === true || $foodItem['dispense_lunch'] === 'true')) {
                            $lunchWet = true;
                            break;
                        }
                    }
                }

                if (! empty($effectiveFlows['scheduled_lunch']) && ($effectiveFlows['scheduled_lunch'] === true || $effectiveFlows['scheduled_lunch'] === 'true') && ! $lunchDry && ! $lunchWet) {
                    $lunchDry = true;
                    $lunchWet = true;
                }

                $scheduledRest = ! empty($meds['dispense_rest']) && ($meds['dispense_rest'] === true || $meds['dispense_rest'] === 'true');
                if (! $scheduledRest && ! empty($medsList)) {
                    foreach ($medsList as $medicationItem) {
                        if (! is_array($medicationItem)) {
                            continue;
                        }

                        $itemHasRest =
                            (!empty($medicationItem['dispense_rest']) && ($medicationItem['dispense_rest'] === true || $medicationItem['dispense_rest'] === 'true')) ||
                            (!empty($medicationItem['dispense_before_bed']) && ($medicationItem['dispense_before_bed'] === true || $medicationItem['dispense_before_bed'] === 'true'));
                        $condition = $medicationItem['condition'] ?? null;

                        if ($itemHasRest || $condition === 'before_sleep') {
                            $scheduledRest = true;
                            break;
                        }
                    }
                }

                if (! $scheduledRest && ! empty($effectiveFlows['rest_required']) && ($effectiveFlows['rest_required'] === true || $effectiveFlows['rest_required'] === 'true' || $effectiveFlows['rest_required'] === 1 || $effectiveFlows['rest_required'] === '1')) {
                    $scheduledRest = true;
                }

                $workflowId = $isFamilyAppointment ? (int) $pet->id : (int) $appointmentId;

                $data[] = [
                    'workflow_id' => $workflowId,
                    'appointment_id' => (int) $appointmentId,
                    'pet_id' => (int) $pet->id,
                    'pet_name' => $pet->name ?? 'N/A',
                    'pet_img' => $pet->pet_img ?? null,
                    'lunch_dry' => $lunchDry,
                    'lunch_wet' => $lunchWet,
                    'scheduled_rest' => $scheduledRest,
                    'rest_required' => ! empty($effectiveFlows['rest_required']) && ($effectiveFlows['rest_required'] === true || $effectiveFlows['rest_required'] === 'true' || $effectiveFlows['rest_required'] === 1 || $effectiveFlows['rest_required'] === '1'),
                    'rest_note' => isset($effectiveFlows['rest_note']) ? (string) $effectiveFlows['rest_note'] : '',
                    'customer_name' => $appointment->customer && $appointment->customer->profile
                        ? $appointment->customer->profile->first_name . ' ' . $appointment->customer->profile->last_name
                        : 'N/A',
                    'customer_avatar' => $appointment->customer && $appointment->customer->profile
                        ? $appointment->customer->profile->avatar_img : null,
                    'checkin' => [
                        'id' => $checkin ? $checkin->id : null,
                        'flows' => $effectiveFlows
                    ]
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getTreatmentListYesterdayPetIds(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'appointment_ids' => 'nullable|array',
            'appointment_ids.*' => 'exists:appointments,id',
            'workflow_ids' => 'nullable|array',
            'workflow_ids.*' => 'integer',
        ]);

        $date = Carbon::parse($request->input('date'));
        $yesterday = $date->copy()->subDay()->format('Y-m-d');
        $appointmentIds = $request->input('appointment_ids', []);
        $workflowIds = $request->input('workflow_ids', []);

        $process = Process::where('date', $yesterday)->first();
        if (!$process || !$process->flows) {
            return response()->json([
                'success' => true,
                'yesterday_pet_ids' => [],
                'yesterday_reports_pm_issues' => [],
            ]);
        }

        $flows = json_decode($process->flows, true);
        $nextDayData = $flows['next_day_treatment_list_tlr'] ?? [];
        $selectedIds = $nextDayData['selected_pet_ids'] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = [];
        }
        $selectedIds = array_map('intval', $selectedIds);
        // $feedingPm = $flows['feeding_pm'] ?? [];
        // $feedingPmIds = isset($feedingPm['selected_pet_ids']) && is_array($feedingPm['selected_pet_ids'])
        //     ? array_map('intval', $feedingPm['selected_pet_ids']) : [];
        $reportsPm = $flows['reports_pm'] ?? [];
        $reportsPmIds = isset($reportsPm['selected_pet_ids']) && is_array($reportsPm['selected_pet_ids'])
            ? array_map('intval', $reportsPm['selected_pet_ids']) : [];
        $selectedIds = array_values(array_unique(array_merge($selectedIds, $reportsPmIds)));

        if (!empty($workflowIds)) {
            $selectedWorkflowIds = array_values(array_unique(array_map('intval', (array) $workflowIds)));
            $selectedWorkflowSet = array_flip($selectedWorkflowIds);
            $legacySelectedSet = array_flip($selectedIds);
            $mappedWorkflowIds = [];

            if (!empty($appointmentIds)) {
                $appointments = Appointment::with('pet')->whereIn('id', array_map('intval', (array) $appointmentIds))->get();

                foreach ($appointments as $appointment) {
                    $familyPets = $appointment->familyPets ?? collect();
                    if ($familyPets->isEmpty() && $appointment->pet) {
                        $familyPets = collect([$appointment->pet]);
                    }

                    if ($familyPets->count() > 1) {
                        $hasLegacyAppointmentSelection = isset($legacySelectedSet[(int) $appointment->id]);

                        foreach ($familyPets as $pet) {
                            if (!$pet) {
                                continue;
                            }

                            $petId = (int) $pet->id;
                            $matchesWorkflowSelection = isset($selectedWorkflowSet[$petId]);
                            $matchesLegacySelection = isset($legacySelectedSet[$petId]) || $hasLegacyAppointmentSelection;

                            if ($matchesWorkflowSelection && $matchesLegacySelection) {
                                $mappedWorkflowIds[] = $petId;
                            }
                        }
                    } elseif (isset($selectedWorkflowSet[(int) $appointment->id])) {
                        $mappedWorkflowIds[] = (int) $appointment->id;
                    }
                }
            }

            foreach ($selectedIds as $selectedId) {
                if (isset($selectedWorkflowSet[(int) $selectedId])) {
                    $mappedWorkflowIds[] = (int) $selectedId;
                }
            }

            $selectedIds = array_values(array_unique($mappedWorkflowIds));
        }

        if (!empty($appointmentIds)) {
            $appointmentIdsInt = array_map('intval', (array) $appointmentIds);
            if (empty($workflowIds)) {
                $selectedIds = array_values(array_intersect($selectedIds, $appointmentIdsInt));
            }
        }

        $yesterdayReportsPmIssues = isset($reportsPm['issues']) && is_array($reportsPm['issues'])
            ? $reportsPm['issues'] : [];

        return response()->json([
            'success' => true,
            'yesterday_pet_ids' => array_values(array_unique($selectedIds)),
            'yesterday_reports_pm_issues' => $yesterdayReportsPmIssues,
        ]);
    }

    public function editBoardingProcessLog($id)
    {
        
        $process = Process::with(['appointment.pet', 'appointment.customer.profile'])
            ->findOrFail($id);
        
        $processes = Process::with(['appointment.pet', 'appointment.customer.profile'])
            ->where('date', $process->date)
            ->get();
        
        $appointmentIds = $processes->pluck('appointment_id')->toArray();
        
        $flows = $process->flows ? json_decode($process->flows, true) : [];
        
        return view('dashboard.boarding-process-log-edit', compact('process', 'processes', 'appointmentIds', 'flows'));
    }

    public function updateBoardingProcessLog(Request $request, $id)
    {
        $process = Process::findOrFail($id);
        
        $request->validate([
            'flows' => 'required|array',
        ]);

        $newFlows = $request->input('flows');
        $startTime = $request->input('start_time');
        $pickupTime = $request->input('pickup_time');

        // Get all processes for the same date (no longer filtering by staff_id)
        $processes = Process::where('date', $process->date)
            ->get();

        $updatedCount = 0;
        $errors = [];

        foreach ($processes as $proc) {
            try {
                // Merge existing flows with new flows (new flows take precedence)
                $existingFlows = $proc->flows ? json_decode($proc->flows, true) : [];
                if (!is_array($existingFlows)) {
                    $existingFlows = [];
                }
                $mergedFlows = array_merge($existingFlows, $newFlows);
                
                $proc->flows = json_encode($mergedFlows);
                if ($startTime) {
                    $proc->start_time = $startTime;
                }
                if ($pickupTime) {
                    $proc->pickup_time = $pickupTime;
                }
                $proc->save();

                $appointment = Appointment::with(['service.category', 'pet'])->find($proc->appointment_id);
                if ($appointment) {
                    $this->syncBoardingFleaTickFromProcessFlows($appointment, $mergedFlows);
                }
                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Error updating process ID {$proc->id}: " . $e->getMessage();
            }
        }

        if ($updatedCount > 0) {
            $message = "Successfully updated process logs for {$updatedCount} pet(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updatedCount,
                'errors' => $errors
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update any process logs.',
                'errors' => $errors
            ], 400);
        }
    }

    public function deleteBoardingProcessLog($id)
    {
        $process = Process::findOrFail($id);
        
        $processes = Process::where('staff_id', $process->staff_id)
            ->where('date', $process->date)
            ->get();
        
        $deletedCount = 0;
        foreach ($processes as $proc) {
            $proc->delete();
            $deletedCount++;
        }

        return redirect()->route('boarding-process-log')
            ->with('success', "Successfully deleted process logs for {$deletedCount} pet(s).");
    }

    public function groomerCalendar($id)
    {
        $groomers = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->with('profile')->get();

        $groomerId = trim((string) request()->query('groomer_id', ''));
        $date = trim((string) request()->query('date', ''));

        $groomerId = $groomerId === '' ? null : $groomerId;
        $date = $date === '' ? null : $date;

        return view('dashboard.groomer-calendar', compact('id', 'groomers', 'groomerId', 'date'));
    }

    public function groomerCalendarData(Request $request, $id)
    {
        $start = $request->query('start');
        $end = $request->query('end');
        $groomerId = trim((string) $request->query('groomer_id', ''));
        $date = trim((string) $request->query('date', ''));

        $groomerId = $groomerId === '' ? null : $groomerId;
        $date = $date === '' ? null : $date;

        $rangeStart = $start ? Carbon::parse($start) : Carbon::today()->startOfDay();
        $rangeEnd = $end ? Carbon::parse($end) : Carbon::today()->endOfDay();

        if (!empty($date)) {
            $selectedDate = Carbon::parse($date);
            $rangeStart = $selectedDate->copy()->startOfDay();
            $rangeEnd = $selectedDate->copy()->endOfDay();
        }

        $dateStart = $rangeStart->copy()->startOfDay();
        $dateEnd = $rangeEnd->copy()->subSecond()->endOfDay();

        $staffsQuery = User::whereHas('roles', function ($query) {
            $query->whereNot('title', 'customer');
        })->with('profile');

        if (!empty($groomerId)) {
            $staffsQuery->where('id', $groomerId);
        }

        $staffs = $staffsQuery->get();

        $appointmentsQuery = Appointment::where('service_id', $id)
            ->where('status', '!=', 'cancelled')
            ->whereDate('date', '>=', $dateStart->toDateString())
            ->whereDate('date', '<=', $dateEnd->toDateString())
            ->with(['pet', 'staff.profile', 'process', 'service']);

        if (!empty($groomerId)) {
            $appointmentsQuery->where(function ($query) use ($groomerId) {
                $query->where('staff_id', $groomerId)
                    ->orWhereHas('process', function ($processQuery) use ($groomerId) {
                        $processQuery->where('staff_id', $groomerId);
                    });
            });
        }

        $appointments = $appointmentsQuery
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $additionalServiceIds = $appointments->flatMap(function ($appointment) {
            $serviceIds = [];

            if (!empty($appointment->additional_service_ids)) {
                $serviceIds = array_merge($serviceIds, explode(',', $appointment->additional_service_ids));
            }

            if (!empty($appointment->metadata['secondary_service_ids'] ?? null)) {
                $serviceIds = array_merge($serviceIds, explode(',', $appointment->metadata['secondary_service_ids']));
            }

            return collect($serviceIds)
                ->map(function ($serviceId) {
                    return (int) trim($serviceId);
                })
                ->filter(function ($serviceId) {
                    return $serviceId > 0;
                });
        })->unique()->values();

        $additionalServiceNames = Service::whereIn('id', $additionalServiceIds)
            ->pluck('name', 'id');

        $resources = $staffs->map(function ($staff) {
            $firstName = optional($staff->profile)->first_name;
            $displayName = trim((string) $firstName);
            if ($displayName === '') {
                $displayName = $staff->name ?: $staff->email;
            }

            return [
                'id' => (string) $staff->id,
                'title' => $displayName,
                'extendedProps' => [
                    'img_url' => empty(optional($staff->profile)->avatar_img)
                        ? null
                        : asset('storage/profiles/' . optional($staff->profile)->avatar_img),
                ],
            ];
        })->values();

        $events = $appointments->map(function ($appointment) use ($additionalServiceNames) {
            if (!$appointment->date || !$appointment->start_time || !$appointment->end_time) {
                return null;
            }

            $resolvedStaffId = $appointment->staff_id ?: optional($appointment->process)->staff_id;
            if (!$resolvedStaffId) {
                return null;
            }

            $startAt = Carbon::parse($appointment->date . ' ' . $appointment->start_time);
            $endAt = Carbon::parse($appointment->date . ' ' . $appointment->end_time);

            if ($endAt->lessThanOrEqualTo($startAt)) {
                $endAt = $startAt->copy()->addMinutes(30);
            }

            $appointmentAdditionalServiceNames = [];

            if (!empty($appointment->additional_service_ids)) {
                $appointmentAdditionalServiceNames = array_merge(
                    $appointmentAdditionalServiceNames,
                    collect(explode(',', $appointment->additional_service_ids))
                        ->map(function ($serviceId) use ($additionalServiceNames) {
                            return $additionalServiceNames->get((int) trim($serviceId));
                        })
                        ->filter()
                        ->values()
                        ->all()
                );
            }

            $appointmentAdditionalServiceNames = array_values(array_unique($appointmentAdditionalServiceNames));

            return [
                'id' => (string) $appointment->id,
                'resourceId' => (string) $resolvedStaffId,
                'title' => optional($appointment->pet)->name ?: 'Pet Booking',
                'start' => $startAt->format('Y-m-d\TH:i:s'),
                'end' => $endAt->format('Y-m-d\TH:i:s'),
                'extendedProps' => [
                    'pet_name' => optional($appointment->pet)->name,
                    'img_url' => empty(optional($appointment->pet)->pet_img)
                        ? null
                        : asset('storage/pets/' . optional($appointment->pet)->pet_img),
                    'main_service' => optional($appointment->service)->name,
                    'additional_services' => $appointmentAdditionalServiceNames,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'resources' => $resources,
            'events' => $events,
        ]);
    }

    public function exportBoardingDetailReportPDF(Request $request, $id)
    {
        $appointment = Appointment::with([
            'pet',
            'customer.profile',
            'service',
            'staff.profile',
            'checkout'
        ])->findOrFail($id);

        $checkin = Checkin::where('appointment_id', $appointment->id)->first();
        if ($checkin && $checkin->flows) {
            $checkin->flows = json_decode($checkin->flows, true);
        }

        // Get all family pets, not just the first one
        $familyPets = $appointment->family_pets->isNotEmpty() 
            ? $appointment->family_pets 
            : collect([$appointment->pet])->filter();
        
        $customer = $appointment->customer;
        $ownerProfile = optional($appointment->customer)->profile;
        $ownerName = trim((string) ($ownerProfile->first_name ?? '') . ' ' . ($ownerProfile->last_name ?? ''));
        if ($ownerName === '') {
            $ownerName = optional($customer)->name ?? (optional($customer)->email ?? 'Not set');
        }

        $isTruthy = function ($value) {
            return $value === true || $value === 1 || $value === '1' || $value === 'true';
        };

        $stayDuration = 'Not set';
        $startDate = null;
        $endDate = null;
        try {
            $startDate = \Carbon\Carbon::parse($appointment->date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($appointment->end_date)->startOfDay();

            if ($startDate && $endDate) {
                if ($endDate->lt($startDate)) {
                    $endDate = $startDate->copy();
                }

                $totalDays = $startDate->diffInDays($endDate) + 1;
                $durationLabel = $totalDays . ' day' . ($totalDays > 1 ? 's' : '');
                $dateRangeLabel = $startDate->format('M j, Y');
                if (!$startDate->isSameDay($endDate)) {
                    $dateRangeLabel .= ' - ' . $endDate->format('M j, Y');
                }
                $stayDuration = $durationLabel . ' (' . $dateRangeLabel . ')';
            }
        } catch (\Exception $e) {
            $stayDuration = 'Not set';
        }

        $assignmentLocation = $this->getAppointmentAssignmentLocation($appointment);
        $roomName = $assignmentLocation['room_name'];
        $kennelName = $assignmentLocation['kennel_name'];
        $assignmentLabel = $assignmentLocation['label'];

        // Format check-in and pickup times
        $checkinDateTime = 'Not set';
        $pickupDateTime = 'Not set';
        try {
            if ($checkin && $checkin->created_at) {
                $checkinDateTime = \Carbon\Carbon::parse($checkin->created_at)->format('M j, Y \a\t h:i A');
            }
        } catch (\Exception $e) {
            $checkinDateTime = 'Not set';
        }
        
        try {
            if (!empty($appointment->end_date)) {
                $pickupDateTime = \Carbon\Carbon::parse($appointment->end_date)->format('M j, Y');
            }
        } catch (\Exception $e) {
            $pickupDateTime = 'Not set';
        }

        // Process care information for each pet
        $petsCareData = [];
        $checkinFlows = is_array(optional($checkin)->flows) ? $checkin->flows : [];
        $petSpecificFlows = isset($checkinFlows['pet_specific']) && is_array($checkinFlows['pet_specific'])
            ? $checkinFlows['pet_specific']
            : [];
        $legacyPetsCareFlows = isset($checkinFlows['pets_care']) && is_array($checkinFlows['pets_care'])
            ? $checkinFlows['pets_care']
            : [];

        $resolveSelectedTimes = function (array $item) use ($isTruthy) {
            $labels = [];
            if ($isTruthy($item['dispense_am'] ?? null)) {
                $labels[] = 'AM';
            }
            if ($isTruthy($item['dispense_pm'] ?? null)) {
                $labels[] = 'PM';
            }
            if ($isTruthy($item['dispense_lunch'] ?? null)) {
                $labels[] = 'Lunch';
            }
            if ($isTruthy($item['dispense_rest'] ?? null)) {
                $labels[] = 'During Rest';
            }
            if ($isTruthy($item['dispense_before_bed'] ?? null)) {
                $labels[] = 'Before Sleep';
            }
            if ($isTruthy($item['dispense_prn'] ?? null)) {
                $labels[] = 'PRN';
            }
            if ($isTruthy($item['dispense_custom_time'] ?? null)) {
                $custom = trim((string) ($item['custom_time'] ?? ''));
                $labels[] = $custom !== '' ? 'Custom Time: ' . $custom : 'Custom Time';
            }

            return array_values(array_unique($labels));
        };

        $requestCareNotes = trim((string) $request->query('care_notes', ''));
        $requestCareNotesByPetRaw = $request->query('care_notes_by_pet', '');
        $requestCareNotesByPet = [];
        if (is_string($requestCareNotesByPetRaw) && trim($requestCareNotesByPetRaw) !== '') {
            $decodedCareNotesByPet = json_decode($requestCareNotesByPetRaw, true);
            if (is_array($decodedCareNotesByPet)) {
                $requestCareNotesByPet = $decodedCareNotesByPet;
            }
        }

        foreach ($familyPets as $pet) {
            $petAge = null;
            if (!empty($pet->age)) {
                $petAge = (int) $pet->age;
            } elseif (!empty($pet->birthdate)) {
                try {
                    $petAge = \Carbon\Carbon::parse($pet->birthdate)->age;
                } catch (\Exception $e) {
                    $petAge = null;
                }
            }
            $isSenior = $petAge !== null ? $petAge >= 8 : false;

            $behaviorIds = collect(is_array($pet->pet_behavior_id ?? null) ? $pet->pet_behavior_id : [])
                ->filter()
                ->map(fn ($behaviorId) => (int) $behaviorId)
                ->unique()
                ->values();
            $behaviorLabels = $behaviorIds->isNotEmpty()
                ? PetBehavior::whereIn('id', $behaviorIds->all())->pluck('description')->filter()->values()->all()
                : [];

            // Merge shared flows with per-pet overrides.
            $petIdKey = (string) $pet->id;
            $currentPetSpecificFlows = $petSpecificFlows[$petIdKey] ?? ($petSpecificFlows[$pet->id] ?? []);
            if (!is_array($currentPetSpecificFlows)) {
                $currentPetSpecificFlows = [];
            }

            // Backward compatibility for older saved structure.
            $currentLegacyPetFlows = $legacyPetsCareFlows[$petIdKey] ?? ($legacyPetsCareFlows[$pet->id] ?? []);
            if (!is_array($currentLegacyPetFlows)) {
                $currentLegacyPetFlows = [];
            }

            $petFlows = array_merge($checkinFlows, $currentLegacyPetFlows, $currentPetSpecificFlows);
            unset($petFlows['pet_specific'], $petFlows['pets_care']);

            $careNotes = trim((string) ($requestCareNotesByPet[$petIdKey] ?? ($requestCareNotesByPet[$pet->id] ?? '')));
            if ($careNotes === '') {
                $careNotes = trim((string) ($petFlows['care_notes'] ?? ''));
            }
            if ($careNotes === '') {
                $careNotes = $requestCareNotes;
            }
            if ($careNotes === '') {
                $careNotes = trim((string) ($checkin->notes ?? ''));
            }
            if ($careNotes === '') {
                $careNotes = trim((string) ($petFlows['pet_notes'] ?? ''));
            }

            $medsFlows = is_array($petFlows['meds'] ?? null) ? $petFlows['meds'] : [];
            $medsListFlows = is_array($petFlows['meds_list'] ?? null) ? $petFlows['meds_list'] : [];

            $requestMedsAm = filter_var($request->query('meds_am', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $requestMedsPm = filter_var($request->query('meds_pm', null), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $hasRequestMedicationState = $requestMedsAm !== null || $requestMedsPm !== null;

            if ($hasRequestMedicationState) {
                $medicationRequired = ($requestMedsAm === true) || ($requestMedsPm === true);
            } else {
                $medicationRequired =
                    !empty($medsListFlows) ||
                    $isTruthy($medsFlows['dispense_am'] ?? null) ||
                    $isTruthy($medsFlows['dispense_pm'] ?? null) ||
                    $isTruthy($medsFlows['dispense_prn'] ?? null) ||
                    $isTruthy($petFlows['medications_am'] ?? null) ||
                    $isTruthy($petFlows['medications_pm'] ?? null);
            }

            // Extract feeding information
            $dryFoodList = is_array($petFlows['dry_food_list'] ?? null) ? $petFlows['dry_food_list'] : [];
            $wetFoodList = is_array($petFlows['wet_food_list'] ?? null) ? $petFlows['wet_food_list'] : [];
            $ownerFood = $petFlows['owner_food'] ?? null;
            $ownerFoodList = is_array($petFlows['owner_food_list'] ?? null) ? $petFlows['owner_food_list'] : [];
            $feedingNotes = $petFlows['feeding_notes'] ?? null;
            
            // Fallback to legacy single food structure if lists are empty
            if (empty($dryFoodList) && isset($petFlows['dry_food'])) {
                $dryFoodList = [$petFlows['dry_food']];
            }
            if (empty($wetFoodList) && isset($petFlows['wet_food'])) {
                $wetFoodList = [$petFlows['wet_food']];
            }

            // Extract medication information
            $medicationList = is_array($petFlows['meds_list'] ?? null) ? $petFlows['meds_list'] : [];
            if (empty($medicationList) && isset($petFlows['meds'])) {
                $medicationList = [$petFlows['meds']];
            }
            $medicationNotes = $petFlows['medication_notes'] ?? null;

            // Extract rest information
            $restRequired = $isTruthy($petFlows['rest_required'] ?? null);
            $restNote = $petFlows['rest_note'] ?? null;

            $dryFoodList = array_map(function ($item) use ($resolveSelectedTimes) {
                $item = is_array($item) ? $item : [];
                $item['selected_times'] = $resolveSelectedTimes($item);
                return $item;
            }, $dryFoodList);

            $wetFoodList = array_map(function ($item) use ($resolveSelectedTimes) {
                $item = is_array($item) ? $item : [];
                $item['selected_times'] = $resolveSelectedTimes($item);
                return $item;
            }, $wetFoodList);

            if (!empty($ownerFoodList)) {
                $ownerFoodList = array_values(array_map(function ($item) use ($resolveSelectedTimes) {
                    $item = is_array($item) ? $item : ['value' => (string) $item];
                    $item['selected_times'] = $resolveSelectedTimes($item);
                    return $item;
                }, $ownerFoodList));
            } elseif (is_array($ownerFood)) {
                $ownerFoodList = [[
                    'value' => trim((string) ($ownerFood['value'] ?? $ownerFood['details'] ?? $ownerFood['note'] ?? '')),
                    'selected_times' => $resolveSelectedTimes($ownerFood),
                ]];
            } elseif (is_string($ownerFood) && trim($ownerFood) !== '') {
                $ownerFoodList = [[
                    'value' => trim($ownerFood),
                    'selected_times' => [],
                ]];
            }

            $medicationList = array_map(function ($item) use ($resolveSelectedTimes) {
                $item = is_array($item) ? $item : [];
                $item['selected_times'] = $resolveSelectedTimes($item);

                $conditions = [];
                $mealCondition = trim((string) ($item['meal_condition'] ?? $item['condition'] ?? ''));
                if ($mealCondition === 'after_meal') {
                    $conditions[] = 'After Meals';
                } elseif ($mealCondition === 'before_meal') {
                    $conditions[] = 'Before Meals';
                } elseif ($mealCondition === 'empty_stomach') {
                    $conditions[] = 'Empty Stomach';
                }

                $item['conditions_display'] = $conditions;
                return $item;
            }, $medicationList);

            $petsCareData[] = [
                'pet' => $pet,
                'isSenior' => $isSenior,
                'behaviorLabels' => $behaviorLabels,
                'medicationRequired' => $medicationRequired,
                'dryFoodList' => $dryFoodList,
                'wetFoodList' => $wetFoodList,
                'ownerFood' => $ownerFood,
                'ownerFoodList' => $ownerFoodList,
                'feedingNotes' => $feedingNotes,
                'medicationList' => $medicationList,
                'medicationNotes' => $medicationNotes,
                'restRequired' => $restRequired,
                'restNote' => $restNote,
                'careNotes' => $careNotes,
            ];
        }

        $pdf = Pdf::loadView('archives.boarding-detail-report-pdf', [
            'appointment' => $appointment,
            'familyPets' => $familyPets,
            'petsCareData' => $petsCareData,
            'showPetStayInfo' => true,
            'ownerName' => $ownerName,
            'stayDuration' => $stayDuration,
            'kennelName' => $kennelName,
            'roomName' => $roomName,
            'assignmentLabel' => $assignmentLabel,
            'checkinDateTime' => $checkinDateTime,
            'pickupDateTime' => $pickupDateTime,
        ]);

        $petNames = $familyPets->pluck('name')->join(' & ');
        $fileName = 'Boarding_Report_' . $petNames . '_' . date('Y-m-d', strtotime($appointment->date)) . '.pdf';

        return $pdf->download($fileName);
    }

    private function getAppointmentAssignmentLocation(Appointment $appointment): array
    {
        $perPetDetails = $appointment->family_pet_assignment_details;
        if (!empty($perPetDetails)) {
            $firstDetail = $perPetDetails[0] ?? [];

            return [
                'label' => (string) ($appointment->family_pet_assignment_label ?? 'Not assigned'),
                'room_name' => (string) ($firstDetail['room_name'] ?? ''),
                'kennel_name' => (string) ($firstDetail['kennel_name'] ?? ''),
            ];
        }

        $metadata = is_array($appointment->metadata) ? $appointment->metadata : [];
        $roomName = trim((string) ($metadata['assignment_room_name'] ?? optional($appointment->catRoom)->name ?? ''));
        $roomType = strtolower(trim((string) ($metadata['assignment_room_type'] ?? '')));
        $kennelName = trim((string) (optional($appointment->kennel)->name ?? ''));

        if ($roomName !== '') {
            if ($roomType === 'space') {
                return [
                    'label' => $roomName,
                    'room_name' => $roomName,
                    'kennel_name' => '',
                ];
            }

            if ($kennelName !== '') {
                return [
                    'label' => $roomName . ' / ' . $kennelName,
                    'room_name' => $roomName,
                    'kennel_name' => $kennelName,
                ];
            }

            return [
                'label' => $roomName,
                'room_name' => $roomName,
                'kennel_name' => '',
            ];
        }

        if ($kennelName !== '') {
            return [
                'label' => $kennelName,
                'room_name' => '',
                'kennel_name' => $kennelName,
            ];
        }

        return [
            'label' => 'Not assigned',
            'room_name' => '',
            'kennel_name' => '',
        ];
    }
}