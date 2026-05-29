<?php

if (!function_exists('getPriceForDate')) {
    function getPriceForDate($service, $date = null) {
        $referenceDate = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::now();
        
        // Check if future price is set and effective date has passed
        if (!is_null($service->future_price) && !is_null($service->future_price_effective_date)) {
            $effectiveDate = \Carbon\Carbon::parse($service->future_price_effective_date);
            if ($referenceDate->greaterThanOrEqualTo($effectiveDate)) {
                return floatval($service->future_price);
            }
        }
        
        // Otherwise return current price
        return floatval($service->price ?? 0);
    }
}

if (!function_exists('getHolidayPriceAddition')) {
    /**
     * Check if a given date falls within a holiday period and return the holiday price addition
     * 
     * @param \Carbon\Carbon|string $date The date to check
     * @return float The holiday price to add (0 if no holiday applies)
     */
    function getHolidayPriceAddition($date) {
        $checkDate = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        
        // Find holidays that apply to this date
        $holiday = \App\Models\Holiday::where(function ($query) {
                $query->where('restrict_bookings', 'no')
                    ->orWhere('restrict_bookings', '0')
                    ->orWhere('restrict_bookings', 0)
                    ->orWhereNull('restrict_bookings');
            })
            ->where(function ($query) use ($checkDate) {
                // For one-day holidays, match exact date
                $query->where(function ($q) use ($checkDate) {
                    $q->where('application_type', 'one_day')
                                            ->whereDate('date', $checkDate->toDateString());
                })
                // For period holidays, check if date falls within the range
                ->orWhere(function ($q) use ($checkDate) {
                    $q->where('application_type', 'period_days')
                                            ->whereDate('date', '<=', $checkDate->toDateString())
                                            ->whereDate('end_date', '>=', $checkDate->toDateString());
                });
            })
            ->first();
        
        return $holiday ? floatval($holiday->fixed_price) : 0;
    }
}

if (!function_exists('getServicePrice')) {
    function getServicePrice($service, $petSize, $metadata = null, $referenceDate = null) {
        // Determine which price to use (current or future based on date)
        $base = getPriceForDate($service, $referenceDate);
        $priceSmall = isset($service->price_small) ? floatval($service->price_small) : null;
        $priceMedium = isset($service->price_medium) ? floatval($service->price_medium) : null;
        $priceLarge = isset($service->price_large) ? floatval($service->price_large) : null;
        $priceXL = isset($service->price_xlarge) ? floatval($service->price_xlarge) : null;

        $category = strtolower($service->category->name ?? '');

        if (str_contains($category, 'groom')) {
            switch ($petSize) {
                case 'small':  return $priceSmall  ?? $base;
                case 'medium': return $priceMedium ?? $base;
                case 'large':  return $priceLarge  ?? $base;
                case 'xlarge': return $priceXL      ?? $base;
                default:       return $base;
            }
        }

        if (str_contains($category, 'daycare')) {
            if ($metadata && isset($metadata['daycare_duration'])) {
                if ($metadata['daycare_duration'] === 'half_day' && $priceSmall !== null) {
                    return $priceSmall;
                }
                if ($metadata['daycare_duration'] === 'full_day' && $priceMedium !== null) {
                    return $priceMedium;
                }
            }
            return $priceSmall;
        }

        if (str_contains($category, 'training')) {
            // Check if this is a group class service (price_small is null)
            if ($priceSmall === null) {
                // This is a group class service
                // For group classes, price should be calculated from selected group classes in metadata
                if ($metadata && isset($metadata['group_class_ids'])) {
                    $groupClassIds = explode(',', $metadata['group_class_ids']);
                    $totalPrice = 0;
                    foreach ($groupClassIds as $classId) {
                        if (!empty($classId)) {
                            $groupClass = \App\Models\GroupClass::find($classId);
                            if ($groupClass) {
                                $totalPrice += floatval($groupClass->price ?? 0);
                            }
                        }
                    }
                    return $totalPrice > 0 ? $totalPrice : $base;
                }
                return $base;
            }

            // This is private training (price_small is not null)
            if ($metadata && isset($metadata['private_training_duration'])) {
                if ($metadata['private_training_duration'] === 'half_hour' && $priceSmall !== null) {
                    return $priceLarge ? $priceSmall + $priceLarge : $priceSmall;
                }
                if ($metadata['private_training_duration'] === 'one_hour' && $priceMedium !== null) {
                    return $priceLarge ? $priceMedium + $priceLarge : $priceMedium;
                }
            }
            return $priceLarge ? $priceSmall + $priceLarge : $priceMedium;
        }

        return $base;
    }
}

if (!function_exists('isGroomingService')) {
    function isGroomingService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'groom');
    }
}

if (!function_exists('isDaycareService')) {
    function isDaycareService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'daycare');
    }
}

if (!function_exists('isPrivateTrainingService')) {
    function isPrivateTrainingService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'training');
    }
}

if (!function_exists('isGroupClassService')) {
    function isGroupClassService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'group');
    }
}

if (!function_exists('isAlaCarteService')) {
    function isAlaCarteService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'carte');
    }
}

if (!function_exists('isBoardingService')) {
    function isBoardingService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'boarding');
    }
}

if (!function_exists('dedupeBoardingAutoFeeInvoiceItems')) {
    function dedupeBoardingAutoFeeInvoiceItems($items): array
    {
        $dedupeNames = [
            'late checkout daycare fee',
            'flea/tick detection fee',
        ];

        $normalizedItems = [];
        $seenNames = [];

        foreach (collect($items ?? [])->values() as $item) {
            $itemName = trim((string) data_get($item, 'item_name', data_get($item, 'description', '')));
            $normalizedName = strtolower($itemName);

            if (in_array($normalizedName, $dedupeNames, true)) {
                if (in_array($normalizedName, $seenNames, true)) {
                    continue;
                }

                $seenNames[] = $normalizedName;
            }

            $normalizedItems[] = $item;
        }

        return $normalizedItems;
    }
}

if (!function_exists('isPackageService')) {
    function isPackageService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'package');
    }
}

if (!function_exists('getBoardingNightCount')) {
    function getBoardingNightCount($appointment): int
    {
        if (empty($appointment?->date) || empty($appointment?->end_date)) {
            return 0;
        }

        $checkInDate = \Carbon\Carbon::parse($appointment->date)->startOfDay();
        $pickupDate = \Carbon\Carbon::parse($appointment->end_date)->startOfDay();

        if ($pickupDate->lte($checkInDate)) {
            return 0;
        }

        return $checkInDate->diffInDays($pickupDate);
    }
}

if (!function_exists('getBoardingFamilyPetCount')) {
    function getBoardingFamilyPetCount($appointment, ?int $petCountOverride = null): int
    {
        if (!is_null($petCountOverride)) {
            return max(0, $petCountOverride);
        }

        $familyPets = collect($appointment->family_pets ?? [])->filter();
        if ($familyPets->isNotEmpty()) {
            return $familyPets->count();
        }

        $metadata = $appointment->metadata ?? [];
        $familyPetIds = $metadata['family_pet_ids'] ?? ($metadata['family_pets'] ?? ($metadata['pet_ids'] ?? []));

        if (is_string($familyPetIds)) {
            $familyPetIds = array_filter(array_map('trim', explode(',', $familyPetIds)));
        }

        if (is_array($familyPetIds) && !empty($familyPetIds)) {
            return count($familyPetIds);
        }

        return !empty($appointment?->pet_id) ? 1 : 0;
    }
}

if (!function_exists('getBoardingPricingBreakdown')) {
    function getBoardingPricingBreakdown($appointment, ?int $petCountOverride = null, $service = null): array
    {
        // Get the service if not provided
        if (!$service) {
            $service = $appointment->service ?? \App\Models\Service::find($appointment->service_id);
        }

        $nightlyRate = $service ? getPriceForDate($service, $appointment->date) : 45.0;
        $nights = getBoardingNightCount($appointment);
        $petCount = getBoardingFamilyPetCount($appointment, $petCountOverride);

        // Calculate per-night boarding subtotal and holiday adjustment.
        // Holiday fixed price replaces the nightly base for matching nights.
        $boardingSubtotal = 0;
        $holidaySurcharge = 0;

        if ($nights > 0) {
            $currentDate = \Carbon\Carbon::parse($appointment->date)->startOfDay();
            $pickupDate = \Carbon\Carbon::parse($appointment->end_date)->startOfDay();

            while ($currentDate->lessThan($pickupDate)) {
                $baseNightlyRate = $service
                    ? getPriceForDate($service, $currentDate)
                    : $nightlyRate;

                $boardingSubtotal += round($petCount * $baseNightlyRate, 2);

                $holidayFixedPrice = getHolidayPriceAddition($currentDate);
                if ($holidayFixedPrice > 0) {
                    $holidaySurcharge += round($petCount * ($holidayFixedPrice - $baseNightlyRate), 2);
                }

                $currentDate->addDay();
            }
        }

        // Calculate family discount
        $discountPerNight = match ($petCount) {
            2 => 10.0,
            3 => 20.0,
            default => 0.0,
        };

        $familyDiscount = round($discountPerNight * $nights, 2);

        return [
            'nightly_rate' => $nightlyRate,
            'nights' => $nights,
            'pet_count' => $petCount,
            'boarding_subtotal' => $boardingSubtotal,
            'family_discount_title' => $familyDiscount > 0 ? 'Multi-Pet Discount' : null,
            'family_discount_amount' => $familyDiscount,
            'holiday_surcharge_title' => $holidaySurcharge > 0 ? 'Holiday Surcharge' : null,
            'holiday_surcharge_amount' => $holidaySurcharge,
            'total' => round(max(0, $boardingSubtotal - $familyDiscount + $holidaySurcharge), 2),
        ];
    }
}

if (!function_exists('boardingValueIsTruthy')) {
    function boardingValueIsTruthy($value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}

if (!function_exists('getBoardingFleaTickBreakdown')) {
    function getBoardingFleaTickBreakdown($appointment, ?array $flows = null): array
    {
        $pets = collect($appointment->family_pets ?? [])->filter();
        if ($pets->isEmpty() && $appointment?->pet) {
            $pets = collect([$appointment->pet]);
        }

        $decodedFlows = is_array($flows) ? $flows : [];
        $checkPetFleaTickData = [];
        if (isset($decodedFlows['check_pet']) && is_array($decodedFlows['check_pet'])) {
            $checkPetFleaTickData = isset($decodedFlows['check_pet']['flea_tick_data']) && is_array($decodedFlows['check_pet']['flea_tick_data'])
                ? $decodedFlows['check_pet']['flea_tick_data']
                : [];
        }
        $petSpecific = isset($decodedFlows['pet_specific']) && is_array($decodedFlows['pet_specific'])
            ? $decodedFlows['pet_specific']
            : [];
        $isFamilyAppointment = $pets->count() > 1;

        $checkedPetCount = 0;

        foreach ($pets as $pet) {
            if (!$pet) {
                continue;
            }

            $petIdKey = (string) $pet->id;
            $petFlows = $petSpecific[$petIdKey] ?? ($petSpecific[$pet->id] ?? []);
            if (!is_array($petFlows)) {
                $petFlows = [];
            }

            $workflowKey = $isFamilyAppointment ? $petIdKey : (string) ($appointment->id ?? '');
            $fleaTickDetectedValue = $checkPetFleaTickData[$workflowKey] ?? ($checkPetFleaTickData[(int) $workflowKey] ?? null);

            if ($fleaTickDetectedValue === null) {
                $fleaTickDetectedValue = $petFlows['flea_tick_detected'] ?? ($decodedFlows['flea_tick_detected'] ?? null);
            }

            // Legacy fallback for historical check-ins that stored a generic flea_tick flag.
            if ($fleaTickDetectedValue === null) {
                $fleaTickDetectedValue = $petFlows['flea_tick'] ?? ($decodedFlows['flea_tick'] ?? null);
            }

            if (boardingValueIsTruthy($fleaTickDetectedValue)) {
                $checkedPetCount++;
            }
        }

        return [
            'checked_pet_count' => $checkedPetCount,
            'amount' => round($checkedPetCount * 50, 2),
        ];
    }
}

if (!function_exists('getBoardingLateCheckoutDaycareBreakdown')) {
    function getBoardingLateCheckoutDaycareBreakdown($appointment, $checkout = null, int $thresholdHours = 1): array
    {
        $result = [
            'scheduled_pickup_at' => null,
            'actual_checkout_at' => null,
            'late_seconds' => 0,
            'late_hours' => 0,
            'threshold_hours' => $thresholdHours,
            'daycare_price' => 0,
            'daycare_duration' => 0,
            'hourly_rate' => 0,
            'billable_hours' => 0,
            'fee' => 0,
            'should_apply_fee' => false,
        ];

        if (!$appointment || !isBoardingService($appointment->service ?? null) || ($appointment->status ?? null) !== 'completed' || empty($appointment->end_date) || empty($appointment->end_time)) {
            return $result;
        }

        $scheduledPickupAt = \Carbon\Carbon::parse($appointment->end_date . ' ' . $appointment->end_time);
        $result['scheduled_pickup_at'] = $scheduledPickupAt;

        if (!$checkout) {
            $checkout = \App\Models\Checkout::where('appointment_id', $appointment->id)->first();
        }

        $checkoutFlows = [];
        if ($checkout && !empty($checkout->flows)) {
            if (is_array($checkout->flows)) {
                $checkoutFlows = $checkout->flows;
            } else {
                $decoded = json_decode($checkout->flows, true);
                $checkoutFlows = is_array($decoded) ? $decoded : [];
            }
        }

        $actualCheckoutAt = null;
        $flowActualCheckoutAt = trim((string) ($checkoutFlows['actual_checkout_at'] ?? ''));
        $flowActualCheckoutTime = trim((string) ($checkoutFlows['actual_checkout_time'] ?? ''));

        if ($flowActualCheckoutAt !== '') {
            try {
                $actualCheckoutAt = \Carbon\Carbon::parse($flowActualCheckoutAt);
            } catch (\Throwable $e) {
                $actualCheckoutAt = null;
            }
        }

        if (!$actualCheckoutAt && !empty($checkout?->date) && $flowActualCheckoutTime !== '') {
            try {
                $actualCheckoutAt = \Carbon\Carbon::parse($checkout->date . ' ' . $flowActualCheckoutTime);
            } catch (\Throwable $e) {
                $actualCheckoutAt = null;
            }
        }

        if (!$actualCheckoutAt) {
            $actualCheckoutAt = \Carbon\Carbon::now();
        }

        $result['actual_checkout_at'] = $actualCheckoutAt;

        $lateSeconds = $scheduledPickupAt->diffInSeconds($actualCheckoutAt, false);
        if ($lateSeconds <= 0) {
            return $result;
        }

        $result['late_seconds'] = $lateSeconds;
        $lateHours = $lateSeconds / 3600;
        $result['late_hours'] = round($lateHours, 2);

        if ($lateHours < $thresholdHours) {
            return $result;
        }

        $daycareService = \App\Models\Service::query()
            ->whereRelation('category', 'name', 'like', '%daycare%')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (!$daycareService) {
            return $result;
        }

        $daycarePrice = floatval($daycareService->price ?? $daycareService->price_medium ?? $daycareService->price_small ?? 0);
        $daycareDuration = floatval($daycareService->duration ?? $daycareService->duration_medium ?? $daycareService->duration_small ?? 0);
        $result['daycare_price'] = $daycarePrice;
        $result['daycare_duration'] = $daycareDuration;

        if ($daycarePrice <= 0 || $daycareDuration <= 0) {
            return $result;
        }

        $hourlyRate = round($daycarePrice / $daycareDuration, 2);
        $result['hourly_rate'] = $hourlyRate;

        $billableHours = (int) floor($lateHours);
        $result['billable_hours'] = $billableHours;

        if ($billableHours <= 0) {
            return $result;
        }

        $fee = round($billableHours * $hourlyRate, 2);
        $result['fee'] = $fee;
        $result['should_apply_fee'] = $fee > 0;

        return $result;
    }
}

if (!function_exists('getBoardingServicePrice')) {
    function getBoardingServicePrice($service, $appointment, $serviceOverride = null)
    {
        if (!isBoardingService($service)) {
            return null;
        }

        $pricingService = $serviceOverride ?? $service;
        $pricing = getBoardingPricingBreakdown($appointment, 1, $pricingService);

        return $pricing['total'];
    }
}

if (!function_exists('isChauffeurService')) {
    function isChauffeurService($service)
    {
        if (!$service || !$service->category) return false;
        return str_contains(strtolower($service->category->name), 'chauffeur');
    }
}

if (!function_exists('serviceCaresAboutPetSize')) {
    function serviceCaresAboutPetSize($service)
    {
        if (!$service) return false;

        $hasPetSizePricing = !is_null($service->price_small) ||
                             !is_null($service->price_medium) ||
                             !is_null($service->price_large) ||
                             !is_null($service->price_xlarge);

        $hasPetSizeDuration = !is_null($service->duration_small) ||
                              !is_null($service->duration_medium) ||
                              !is_null($service->duration_large) ||
                              !is_null($service->duration_xlarge);

        return $hasPetSizePricing || $hasPetSizeDuration;
    }
}

if (!function_exists('getScopedDiscountsForCustomerAndService')) {
    function getScopedDiscountsForCustomerAndService($customerId, $serviceId)
    {
        $customerId = intval($customerId);
        $serviceId = intval($serviceId);

        return \App\Models\Discount::query()
            ->get()
            ->filter(function ($discount) use ($customerId, $serviceId) {
                $serviceIds = is_array($discount->service_ids) ? array_map('intval', $discount->service_ids) : [];
                $customerIds = is_array($discount->customer_ids) ? array_map('intval', $discount->customer_ids) : [];

                $serviceMatch = empty($serviceIds) || in_array($serviceId, $serviceIds, true);
                $customerMatch = empty($customerIds) || in_array($customerId, $customerIds, true);

                return $serviceMatch && $customerMatch;
            })
            ->values();
    }
}

if (!function_exists('calculateBestDiscountForEstimatedPrice')) {
    function calculateBestDiscountForEstimatedPrice($estimatedPrice, $discounts, $referenceDate): array
    {
        $estimatedPrice = max(0, floatval($estimatedPrice ?? 0));
        $reference = $referenceDate ? \Carbon\Carbon::parse($referenceDate) : \Carbon\Carbon::now();

        $bestDiscount = null;
        $bestDiscountAmount = 0.0;

        foreach ($discounts as $discount) {
            $startDate = $discount->start_date ? \Carbon\Carbon::parse($discount->start_date) : null;
            $endDate = $discount->end_date ? \Carbon\Carbon::parse($discount->end_date) : null;

            if ($startDate && $reference->lt($startDate)) {
                continue;
            }

            if ($endDate && $reference->gt($endDate)) {
                continue;
            }

            $rawAmount = floatval($discount->amount ?? 0);
            $candidateAmount = $discount->type === 'percent'
                ? ($estimatedPrice * $rawAmount / 100)
                : $rawAmount;

            $candidateAmount = max(0, min($estimatedPrice, round($candidateAmount, 2)));

            if ($candidateAmount > $bestDiscountAmount) {
                $bestDiscountAmount = $candidateAmount;
                $bestDiscount = $discount;
            }
        }

        return [
            'discount_title' => $bestDiscount?->title,
            'discount_amount' => round($bestDiscountAmount, 2),
        ];
    }
}

if (!function_exists('buildDiscountPreview')) {
    function buildDiscountPreview($totalPrice, $ownerId, $serviceId): array
    {
        $discounts = getScopedDiscountsForCustomerAndService($ownerId, $serviceId);
        $discountResult = calculateBestDiscountForEstimatedPrice($totalPrice, $discounts, \Carbon\Carbon::now());

        return [
            'discount_title' => $discountResult['discount_title'] ?? null,
            'discount_amount' => floatval($discountResult['discount_amount'] ?? 0),
        ];
    }
}

if (!function_exists('handleGroupClasses')) {
    function handleGroupClasses($classIds, $customerId, $petId, $serviceId, $staffId = null)
    {
        foreach ($classIds as $classId) {
            $groupClass = \App\Models\GroupClass::find($classId);
            if (!$groupClass) continue;

            // Parse duration
            $durationAmount = intval($groupClass->duration_amount ?? 0);
            $durationUnit = strtolower($groupClass->duration_unit ?? 'weeks');

            if ($durationAmount <= 0) continue;

            // Calculate end date based on duration
            $startDate = \Carbon\Carbon::parse($groupClass->started_at);
            $endDate = clone $startDate;

            switch ($durationUnit) {
                case 'days':
                    $endDate->addDays($durationAmount);
                    break;
                case 'weeks':
                    $endDate->addWeeks($durationAmount);
                    break;
                case 'months':
                    $endDate->addMonths($durationAmount);
                    break;
                default:
                    $endDate->addWeeks($durationAmount);
            }

            // Parse schedules (e.g., "Monday 16:00,Thursday 17:00" or "Everyday 17:00")
            $schedules = array_filter(array_map('trim', explode(',', $groupClass->schedule ?? '')));

            if (empty($schedules)) continue;

            // Map day names to Carbon day numbers (0=Sunday, 1=Monday, ..., 6=Saturday)
            $dayMap = [
                'sunday' => \Carbon\Carbon::SUNDAY,
                'monday' => \Carbon\Carbon::MONDAY,
                'tuesday' => \Carbon\Carbon::TUESDAY,
                'wednesday' => \Carbon\Carbon::WEDNESDAY,
                'thursday' => \Carbon\Carbon::THURSDAY,
                'friday' => \Carbon\Carbon::FRIDAY,
                'saturday' => \Carbon\Carbon::SATURDAY,
            ];

            foreach ($schedules as $schedule) {
                // Parse schedule: "Monday 16:00" or "Everyday 17:00"
                $parts = preg_split('/\s+/', trim($schedule), 2);
                if (count($parts) < 2) continue;

                $dayPart = strtolower($parts[0]);
                $timePart = $parts[1]; // HH:MM

                // Handle "Everyday" case
                if ($dayPart === 'everyday') {
                    // Create appointments for every day in the duration
                    $currentDate = clone $startDate;
                    while ($currentDate->lt($endDate)) {
                        createGroupClassAppointment($groupClass, $currentDate, $timePart, $customerId, $petId, $serviceId, $staffId);
                        $currentDate->addDay();
                    }
                } else {
                    // Handle specific day of week
                    if (!isset($dayMap[$dayPart])) continue;

                    $targetDayOfWeek = $dayMap[$dayPart];

                    // Find first occurrence of this weekday on or after start date
                    $currentDate = clone $startDate;
                    if ($currentDate->dayOfWeek !== $targetDayOfWeek) {
                        $currentDate->next($targetDayOfWeek);
                    }

                    // Create appointments for this weekday until end date
                    while ($currentDate->lt($endDate)) {
                        createGroupClassAppointment($groupClass, $currentDate, $timePart, $customerId, $petId, $serviceId, $staffId);
                        $currentDate->addWeek(); // Move to next occurrence of same weekday
                    }
                }
            }
        }
    }
}

if (!function_exists('createGroupClassAppointment')) {
    function createGroupClassAppointment($groupClass, $date, $time, $customerId, $petId, $serviceId, $staffId = null)
    {
        // Parse time (HH:MM)
        $timeParts = explode(':', $time);
        if (count($timeParts) < 2) return;

        $hour = intval($timeParts[0]);
        $minute = intval($timeParts[1]);

        // Set the exact date and time
        $appointmentDateTime = clone $date;
        $appointmentDateTime->setTime($hour, $minute, 0);

        // Don't create appointments in the past
        if ($appointmentDateTime->lt(\Carbon\Carbon::now())) return;

        $appointment = new \App\Models\Appointment;
        $appointment->customer_id = $customerId;
        $appointment->pet_id = $petId;
        $appointment->service_id = $serviceId;
        $appointment->additional_service_ids = null;
        $appointment->staff_id = $staffId;
        $appointment->date = $appointmentDateTime->toDateString();
        $appointment->start_time = $appointmentDateTime->format('H:i:s');
        $appointment->status = 'checked_in';
        $appointment->estimated_price = floatval($groupClass->price ?? 0);
        $appointment->metadata = ['group_class_ids' => (string)$groupClass->id];

        $service = \App\Models\Service::find($serviceId);
        $durationHours = floatval($service->duration ?? 1);
        $endTime = clone $appointmentDateTime;
        $endTime->addHours($durationHours);
        $appointment->end_time = $endTime->format('H:i:s');

        $appointment->save();
        if (function_exists('appointment_audit_log')) {
            appointment_audit_log($appointment->id, "Appointment is created.");
        }
    }
}

if (!function_exists('hasPermission')) {
    /**
     * Check if the authenticated user has a specific permission with a specific can_value
     *
     * @param int $permissionId The permission ID
     * @param string $canValue The permission value to check (can_read, can_create, can_update, can_delete)
     * @return bool
     */
    function hasPermission($permissionId, $canValue)
    {
        if (!auth()->check()) {
            return false;
        }

        return auth()->user()->roles()
            ->join('roles_permissions', 'roles.id', '=', 'roles_permissions.role_id')
            ->where('roles_permissions.permission_id', $permissionId)
            ->where('roles_permissions.' . $canValue, 1)
            ->exists();
    }
}

if (!function_exists('getServicePermissionId')) {
    function getServicePermissionId($service)
    {
        if (!$service || !$service->category) {
            return null;
        }

        $categoryName = strtolower($service->category->name);

        if (str_contains($categoryName, 'boarding')) {
            return 16;
        } elseif (str_contains($categoryName, 'daycare')) {
            return 17;
        } elseif (str_contains($categoryName, 'groom')) {
            return 18;
        } elseif (str_contains($categoryName, 'training')) {
            return 19;
        } elseif (str_contains($categoryName, 'group')) {
            return 20;
        } elseif (str_contains($categoryName, 'carte')) {
            return 21;
        } elseif (str_contains($categoryName, 'package')) {
            return 22;
        }

        return null;
    }
}

if (!function_exists('isAssignmentConflict')) {
    function isAssignmentConflict($appointment)
    {
        if (!$appointment || !is_object($appointment)) {
            return false;
        }

        // Safely check if metadata property exists
        if (!isset($appointment->metadata)) {
            return false;
        }

        $metadata = $appointment->metadata;
        if (!is_array($metadata)) {
            return false;
        }

        return isset($metadata['was_allowed_with_conflict']) && $metadata['was_allowed_with_conflict'] === true;
    }
}

if (!function_exists('getAssignmentConflictLabel')) {
    function getAssignmentConflictLabel($appointment, string $fallback = 'Conflict'): string
    {
        if (!$appointment || !is_object($appointment) || !isset($appointment->metadata) || !is_array($appointment->metadata)) {
            return $fallback;
        }

        $metadata = $appointment->metadata;
        $warningCodes = $metadata['warning_codes'] ?? [];

        if (is_string($warningCodes) && trim($warningCodes) !== '') {
            $warningCodes = array_filter(array_map('trim', explode(',', $warningCodes)));
        }

        $warningCodes = collect(is_array($warningCodes) ? $warningCodes : [])
            ->map(fn ($code) => strtolower((string) $code))
            ->unique()
            ->values();

        $hasCapacity = $warningCodes->contains('capacity_exceeded');
        $hasSizeRule = $warningCodes->contains('size_sharing');

        if ($hasCapacity && $hasSizeRule) {
            return 'Over capacity + size-rule warning';
        }

        if ($hasSizeRule) {
            return 'Size-rule warning';
        }

        if ($hasCapacity) {
            return 'Over capacity';
        }

        $message = strtolower((string) ($metadata['assignment_conflict_message'] ?? ''));
        $messageHasSizeRule = str_contains($message, 'size rule warning') || str_contains($message, 'size-rule');
        $messageHasCapacity = str_contains($message, 'capacity warning') || str_contains($message, 'over capacity');

        if ($messageHasCapacity && $messageHasSizeRule) {
            return 'Over capacity + size-rule warning';
        }

        if ($messageHasSizeRule) {
            return 'Size-rule warning';
        }

        if ($messageHasCapacity) {
            return 'Over capacity';
        }

        $conflictType = strtolower((string) ($metadata['assignment_conflict_type'] ?? ''));

        return match ($conflictType) {
            'kennel' => 'Assignment warning',
            'room' => 'Room conflict',
            'pet_type', 'cat_kennel', 'cat_to_kennel' => 'Size/type warning',
            default => $fallback,
        };
    }
}

if (!function_exists('appointment_status_label')) {
    function appointment_status_label(?string $status, $service = null): string
    {
        $labels = [
            'checked_in' => 'Scheduled',
            'in_progress' => ($service && (isBoardingService($service) || isDaycareService($service))) ? 'On Property' : 'In Progress',
            'completed' => 'Completed',
            'finished' => 'Finished',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
            'no_show' => 'No Show',
            'confirmed' => 'Confirmed',
        ];

        if ($status && isset($labels[$status])) {
            return $labels[$status];
        }

        return $status ? ucfirst(str_replace('_', ' ', $status)) : '—';
    }
}

if (!function_exists('appointment_audit_log')) {
    function appointment_audit_log($appointmentId, $description)
    {
        $auth = \Illuminate\Support\Facades\Auth::getFacadeRoot();
        $employeeName = null;
        if ($auth->check()) {
            $user = $auth->user()->load('profile');
            $fullName = $user->profile
                ? trim(($user->profile->first_name ?? '') . ' ' . ($user->profile->last_name ?? ''))
                : '';
            $employeeName = $fullName ?: $user->name ?? $user->email;
        }

        $petName = null;
        $petAvatar = null;
        $ownerName = null;
        $type = null;

        $appointment = \App\Models\Appointment::with(['pet', 'customer.profile', 'service.category'])
            ->find($appointmentId);

        if ($appointment) {
            if ($appointment->pet) {
                $petName = $appointment->pet->name;
                $petAvatar = $appointment->pet->pet_img;
            }
            if ($appointment->customer) {
                if ($appointment->customer->profile) {
                    $ownerName = trim(($appointment->customer->profile->first_name ?? '') . ' ' . ($appointment->customer->profile->last_name ?? ''));
                }
                if (empty($ownerName)) {
                    $ownerName = $appointment->customer->email;
                }
            }
            if ($appointment->service) {
                $type = $appointment->service->category
                    ? $appointment->service->category->name
                    : $appointment->service->name;
            }
        }

        \App\Models\AppointmentAuditLog::create([
            'description' => $description,
            'pet_name' => $petName,
            'pet_avatar' => $petAvatar,
            'owner_name' => $ownerName,
            'type' => $type,
            'employee' => $employeeName,
        ]);
    }
}

if (!function_exists('buildOwnerAddressFromProfile')) {
    function buildOwnerAddressFromProfile($profile): ?string
    {
        if (!$profile) {
            return null;
        }

        $address = trim((string)($profile->address ?? ''));
        $city = trim((string)($profile->city ?? ''));
        $state = trim((string)($profile->state ?? ''));
        $zip = trim((string)($profile->zip_code ?? ''));

        // Chauffeur validation requires a complete address (address, city, state, zip).
        if ($address === '' || $city === '' || $state === '' || $zip === '') {
            return null;
        }

        return implode(', ', [$address, $city, $state, $zip]);
    }
}

if (!function_exists('buildFacilityAddressFromModel')) {
    function buildFacilityAddressFromModel($facility): ?string
    {
        if (!$facility) {
            return null;
        }

        $address = trim((string)($facility->address ?? ''));
        $city = trim((string)($facility->city ?? ''));
        $state = trim((string)($facility->state ?? ''));
        $zip = trim((string)($facility->zip_code ?? ''));

        if ($address === '' || $city === '' || $state === '' || $zip === '') {
            return null;
        }

        return implode(', ', [$address, $city, $state, $zip]);
    }
}

if (!function_exists('geocodeChauffeurAddress')) {
    function geocodeChauffeurAddress(string $address): ?array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'PawPrints/1.0 (admin@pawprints.local)',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'q' => $address,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
                return null;
            }

            return [
                'lat' => floatval($data[0]['lat']),
                'lon' => floatval($data[0]['lon']),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('getDrivingDistanceMiles')) {
    function getDrivingDistanceMiles(array $origin, array $destination): ?float
    {
        try {
            $coordinates = sprintf(
                '%s,%s;%s,%s',
                $origin['lon'],
                $origin['lat'],
                $destination['lon'],
                $destination['lat']
            );

            $response = \Illuminate\Support\Facades\Http::timeout(12)
                ->acceptJson()
                ->get("https://router.project-osrm.org/route/v1/driving/{$coordinates}", [
                    'overview' => 'false',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!is_array($data) || ($data['code'] ?? null) !== 'Ok' || empty($data['routes'][0]['distance'])) {
                return null;
            }

            $distanceMeters = floatval($data['routes'][0]['distance']);

            return round($distanceMeters / 1609.344, 2);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('calculateChauffeurServicePrice')) {
    function calculateChauffeurServicePrice($chauffeurService, $customerId): float
    {
        $facility = \App\Models\FacilityAddress::query()->first();
        $facilityAddress = buildFacilityAddressFromModel($facility);

        if (!$facilityAddress) {
            return 0;
        }

        $customer = \App\Models\User::find($customerId);
        $ownerAddress = buildOwnerAddressFromProfile($customer?->profile);

        if (!$ownerAddress) {
            return 0;
        }

        $ownerCoords = geocodeChauffeurAddress($ownerAddress);
        $facilityCoords = geocodeChauffeurAddress($facilityAddress);

        if (!$ownerCoords || !$facilityCoords) {
            return 0;
        }

        $distanceMiles = getDrivingDistanceMiles($ownerCoords, $facilityCoords);

        if ($distanceMiles === null) {
            return 0;
        }

        $pricePerMile = floatval($chauffeurService->price_per_mile ?? 0);

        return round($distanceMiles * $pricePerMile, 2);
    }
}

if (!function_exists('buildChauffeurPricingData')) {
    function buildChauffeurPricingData($appointment): array
    {
        $facility = \App\Models\FacilityAddress::query()->first();
        $facilityAddress = buildFacilityAddressFromModel($facility);

        $result = [
            'has_chauffeur' => false,
            'is_route_valid' => false,
            'error' => null,
            'distance_miles' => null,
            'owner_address' => null,
            'facility_address' => $facilityAddress,
            'service_prices' => [],
        ];

        // Resolve all chauffeur services from the appointment
        $serviceIds = collect();

        if ($appointment->service && isChauffeurService($appointment->service)) {
            $serviceIds->push(intval($appointment->service->id));
        }

        if (!empty($appointment->additional_service_ids)) {
            $raw = $appointment->additional_service_ids;
            $parsed = is_array($raw)
                ? array_values(array_filter($raw, fn ($id) => !empty($id)))
                : array_values(array_filter(array_map('trim', explode(',', (string)$raw)), fn ($id) => $id !== ''));
            $serviceIds = $serviceIds->merge($parsed);
        }

        if (isAlaCarteService($appointment->service) && $appointment->metadata && isset($appointment->metadata['secondary_service_ids'])) {
            $raw = $appointment->metadata['secondary_service_ids'];
            $parsed = is_array($raw)
                ? array_values(array_filter($raw, fn ($id) => !empty($id)))
                : array_values(array_filter(array_map('trim', explode(',', (string)$raw)), fn ($id) => $id !== ''));
            $serviceIds = $serviceIds->merge($parsed);
        }

        $uniqueIds = $serviceIds->filter()->map(fn ($id) => intval($id))->unique()->values();

        if ($uniqueIds->isEmpty()) {
            return $result;
        }

        $chauffeurServices = \App\Models\Service::with('category')
            ->whereIn('id', $uniqueIds)
            ->get()
            ->filter(fn ($s) => isChauffeurService($s))
            ->values();

        if ($chauffeurServices->isEmpty()) {
            return $result;
        }

        $result['has_chauffeur'] = true;

        $ownerAddress = buildOwnerAddressFromProfile($appointment->customer?->profile);
        $result['owner_address'] = $ownerAddress;

        if (!$ownerAddress) {
            $result['error'] = 'Owner address is invalid.';
            return $result;
        }

        if (!$facilityAddress) {
            $result['error'] = 'Facility address is invalid.';
            return $result;
        }

        $ownerCoords = geocodeChauffeurAddress($ownerAddress);
        $facilityCoords = geocodeChauffeurAddress($facilityAddress);

        if (!$ownerCoords) {
            $result['error'] = 'Owner address is invalid.';
            return $result;
        }

        if (!$facilityCoords) {
            $result['error'] = 'Facility address is invalid.';
            return $result;
        }

        $distanceMiles = getDrivingDistanceMiles($ownerCoords, $facilityCoords);

        if ($distanceMiles === null) {
            $result['error'] = 'Invalid address or route. OSRM could not return a valid chauffeur route.';
            return $result;
        }

        $result['is_route_valid'] = true;
        $result['distance_miles'] = $distanceMiles;

        foreach ($chauffeurServices as $chauffeurService) {
            $pricePerMile = floatval($chauffeurService->price_per_mile ?? 0);
            $result['service_prices'][$chauffeurService->id] = round($distanceMiles * $pricePerMile, 2);
        }

        return $result;
    }
}