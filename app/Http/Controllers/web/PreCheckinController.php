<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BoardingPrecheckinLink;
use App\Models\Checkin;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PreCheckinController extends Controller
{
    public function show(string $token)
    {
        $record = $this->resolveRecordByToken($token);
        if (!$record) {
            abort(404);
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return view('precheckin.expired');
        }

        $appointment = Appointment::with(['service.category', 'customer.profile', 'pet', 'checkin'])->findOrFail($record->appointment_id);
        if (!isBoardingService($appointment->service)) {
            abort(404);
        }

        $checkin = Checkin::where('appointment_id', $appointment->id)->first();
        $flows = [];
        if ($checkin && $checkin->flows) {
            $decoded = json_decode($checkin->flows, true);
            $flows = is_array($decoded) ? $decoded : [];
        }

        $pets = $appointment->family_pets;
        if ($pets->isEmpty() && $appointment->pet) {
            $pets = collect([$appointment->pet]);
        }

        $staffs = User::with('profile')
            ->whereHas('roles', function ($query) {
                $query->whereRaw('LOWER(title) <> ?', ['customer']);
            })
            ->get();

        $taxRate = (float) config('billing.state_tax_rate', 7);
        $baseEstimated = (float) ($appointment->estimated_price ?? 0);
        $computedEstimatedPrice = 0;

        $resolveAppointmentServicePrice = function ($service, $petSize, $metadata = null) {
            if (!$service) {
                return 0;
            }

            return getServicePrice($service, $petSize, $metadata);
        };

        $additionalServiceIds = explode(',', $appointment->additional_service_ids ?? '');
        $additionalServices = Service::whereIn('id', array_filter($additionalServiceIds))->get();

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

            foreach ($additionalServices as $service) {
                $petTotal += $resolveAppointmentServicePrice($service, $petSize);
            }

            $computedEstimatedPrice += $petTotal;
        }

        if ($baseEstimated <= 0) {
            $baseEstimated = (float) $computedEstimatedPrice;
        }

        $boardingPricing = getBoardingPricingBreakdown($appointment, null, $appointment->service);
        $estimatedNetPrice = max(0, $baseEstimated - floatval($boardingPricing['family_discount_amount'] ?? 0));
        $estimatedPriceWithTax = $estimatedNetPrice * (1 + ($taxRate / 100));

        return view('precheckin.form', [
            'appointment' => $appointment,
            'checkin' => $checkin,
            'flows' => $flows,
            'pets' => $pets,
            'staffs' => $staffs,
            'token' => $token,
            'taxRate' => $taxRate,
            'estimatedPriceWithTax' => $estimatedPriceWithTax,
            'submitted' => $record->submitted_at !== null,
        ]);
    }

    public function save(Request $request, string $token)
    {
        $record = $this->resolveRecordByToken($token);
        if (!$record) {
            abort(404);
        }

        if ($record->expires_at && Carbon::parse($record->expires_at)->isPast()) {
            return redirect()->route('pre-checkin.show', ['token' => $token])->with([
                'status' => 'fail',
                'message' => 'This pre check-in link has expired.',
            ]);
        }

        $appointment = Appointment::with(['service.category', 'pet'])->findOrFail($record->appointment_id);
        if (!isBoardingService($appointment->service)) {
            abort(404);
        }

        $request->validate([
            'staff_id' => 'nullable|exists:users,id',
            'estimated_price' => 'required|numeric|min:0',
            'date' => 'required|date',
            'start_time' => 'required',
            'notes' => 'nullable|string|max:2000',
            'boarding_agreement_accepted' => 'required|accepted',
            'boarding_vet_authorized' => 'required|accepted',
            'boarding_owner_full_name' => 'required|string|max:255',
            'boarding_signature_data' => 'required|string',
        ]);

        $taxRate = (float) config('billing.state_tax_rate', 7);
        $estimatedPriceWithTax = (float) $request->estimated_price;
        $baseEstimatedPrice = $taxRate > 0
            ? ($estimatedPriceWithTax / (1 + ($taxRate / 100)))
            : $estimatedPriceWithTax;
        $boardingPricing = getBoardingPricingBreakdown($appointment, null, $appointment->service);
        $grossEstimatedPrice = $baseEstimatedPrice + floatval($boardingPricing['family_discount_amount'] ?? 0);

        if ($request->filled('staff_id')) {
            $appointment->staff_id = $request->staff_id;
        }
        $appointment->estimated_price = round($grossEstimatedPrice, 2);
        $appointment->date = $request->date;
        $appointment->start_time = $request->start_time;
        $appointment->save();

        $checkin = Checkin::firstOrNew(['appointment_id' => $appointment->id]);
        $checkin->date = $request->date;
        $checkin->notes = $request->notes;

        $existingFlows = [];
        if (!empty($checkin->flows)) {
            $decoded = json_decode($checkin->flows, true);
            $existingFlows = is_array($decoded) ? $decoded : [];
        }

        $petSpecific = $this->normalizePetSpecific((array) $request->input('pet_specific', []));
        $primaryPetData = !empty($petSpecific) ? array_values($petSpecific)[0] : [];

        $allDryFood = [];
        $allWetFood = [];
        $allMeds = [];
        foreach ($petSpecific as $petData) {
            $allDryFood = array_merge($allDryFood, $petData['dry_food_list'] ?? []);
            $allWetFood = array_merge($allWetFood, $petData['wet_food_list'] ?? []);
            $allMeds = array_merge($allMeds, $petData['meds_list'] ?? []);
        }

        $flows = [
            'pickup_datetime' => $request->input('pickup_datetime'),
            'trip_location' => $request->input('trip_location'),
            'trip_phone' => $request->input('trip_phone'),
            'alternate_contact_name' => $request->input('alternate_contact_name'),
            'alternate_contact_phone' => $request->input('alternate_contact_phone'),
            'trip_notes' => $request->input('trip_notes'),
            'other_items_description' => $primaryPetData['other_items_description'] ?? null,

            'dry_food_list' => $primaryPetData['dry_food_list'] ?? [],
            'wet_food_list' => $primaryPetData['wet_food_list'] ?? [],
            'meds_list' => $primaryPetData['meds_list'] ?? [],
            'dry_food' => $primaryPetData['dry_food'] ?? [],
            'wet_food' => $primaryPetData['wet_food'] ?? [],
            'meds' => $primaryPetData['meds'] ?? [],

            'medications_am' => collect($allMeds)->contains(fn ($item) => !empty($item['dispense_am'])),
            'medications_pm' => collect($allMeds)->contains(fn ($item) => !empty($item['dispense_pm']) || !empty($item['dispense_before_bed'])),
            'feeding_am' => collect($allDryFood)->contains(fn ($item) => !empty($item['dispense_am'])) || collect($allWetFood)->contains(fn ($item) => !empty($item['dispense_am'])),
            'feeding_pm' => collect($allDryFood)->contains(fn ($item) => !empty($item['dispense_pm'])) || collect($allWetFood)->contains(fn ($item) => !empty($item['dispense_pm'])),
            'feeding_lunch' => collect($allDryFood)->contains(fn ($item) => !empty($item['dispense_lunch'])) || collect($allWetFood)->contains(fn ($item) => !empty($item['dispense_lunch'])),
            'rest_required' => $request->boolean('rest_required'),
            'rest_note' => $request->input('rest_note'),

            'pet_specific' => $petSpecific,

            'boarding_agreement_accepted' => true,
            'boarding_vet_authorized' => true,
            'boarding_owner_full_name' => trim((string) $request->input('boarding_owner_full_name')),
            'boarding_signature_data' => $request->input('boarding_signature_data'),
            'boarding_signature_date' => $request->input('boarding_signature_date') ?: Carbon::today()->toDateString(),

            'location_type' => $request->input('location_type'),
            'location_details' => $request->input('location_details'),
            'precheckin_submitted_at' => Carbon::now()->toDateTimeString(),
            'precheckin_submitted_by' => 'owner',
        ];

        $checkin->flows = json_encode(array_merge($existingFlows, $flows));
        $checkin->save();

        $record->submitted_at = Carbon::now();
        $record->save();

        return redirect()->route('pre-checkin.show', ['token' => $token])->with([
            'status' => 'success',
            'message' => 'Pre check-in saved successfully. Thank you!',
        ]);
    }

    private function resolveRecordByToken(string $token): ?BoardingPrecheckinLink
    {
        $tokenHash = hash('sha256', $token);

        return BoardingPrecheckinLink::where('token_hash', $tokenHash)->first();
    }

    private function normalizePetSpecific(array $input): array
    {
        $normalized = [];

        foreach ($input as $petId => $petData) {
            $dryFoodList = $this->normalizeFoodList((array) ($petData['dry_food_list'] ?? []));
            $wetFoodList = $this->normalizeFoodList((array) ($petData['wet_food_list'] ?? []));
            $medsList = $this->normalizeMedsList((array) ($petData['meds_list'] ?? []));

            $firstDry = $dryFoodList[0] ?? [];
            $firstWet = $wetFoodList[0] ?? [];
            $firstMed = $medsList[0] ?? [];

            $normalized[(string) $petId] = [
                'other_items_description' => $this->nullableTrim($petData['other_items_description'] ?? null),
                'flea_tick_prevention' => in_array(($petData['flea_tick_prevention'] ?? null), ['yes', 'no'], true)
                    ? $petData['flea_tick_prevention']
                    : null,
                'flea_tick_prevention_type' => ($petData['flea_tick_prevention'] ?? null) === 'yes'
                    ? $this->nullableTrim($petData['flea_tick_prevention_type'] ?? null)
                    : null,
                'dry_food_list' => $dryFoodList,
                'wet_food_list' => $wetFoodList,
                'meds_list' => $medsList,
                'dry_food' => [
                    'brand' => $firstDry['brand'] ?? null,
                    'amount' => $firstDry['amount'] ?? null,
                    'dispense_am' => collect($dryFoodList)->contains(fn ($item) => !empty($item['dispense_am'])),
                    'dispense_pm' => collect($dryFoodList)->contains(fn ($item) => !empty($item['dispense_pm'])),
                    'dispense_lunch' => collect($dryFoodList)->contains(fn ($item) => !empty($item['dispense_lunch'])),
                ],
                'wet_food' => [
                    'brand' => $firstWet['brand'] ?? null,
                    'amount' => $firstWet['amount'] ?? null,
                    'dispense_am' => collect($wetFoodList)->contains(fn ($item) => !empty($item['dispense_am'])),
                    'dispense_pm' => collect($wetFoodList)->contains(fn ($item) => !empty($item['dispense_pm'])),
                    'dispense_lunch' => collect($wetFoodList)->contains(fn ($item) => !empty($item['dispense_lunch'])),
                ],
                'meds' => [
                    'name' => $firstMed['name'] ?? null,
                    'amount' => $firstMed['amount'] ?? null,
                    'dispense_am' => collect($medsList)->contains(fn ($item) => !empty($item['dispense_am'])),
                    'dispense_pm' => collect($medsList)->contains(fn ($item) => !empty($item['dispense_pm']) || !empty($item['dispense_before_bed'])),
                    'dispense_rest' => collect($medsList)->contains(fn ($item) => !empty($item['dispense_rest']) || !empty($item['dispense_before_bed'])),
                ],
            ];
        }

        return $normalized;
    }

    private function normalizeFoodList(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $brand = $this->nullableTrim($row['brand'] ?? null);
            $amount = $this->nullableTrim($row['amount'] ?? null);
            $dispenseAm = !empty($row['dispense_am']);
            $dispensePm = !empty($row['dispense_pm']);
            $dispenseLunch = !empty($row['dispense_lunch']);

            if (!$brand && !$amount && !$dispenseAm && !$dispensePm && !$dispenseLunch) {
                continue;
            }

            $result[] = [
                'brand' => $brand,
                'amount' => $amount,
                'dispense_am' => $dispenseAm,
                'dispense_pm' => $dispensePm,
                'dispense_lunch' => $dispenseLunch,
            ];
        }

        return array_values($result);
    }

    private function normalizeMedsList(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $name = $this->nullableTrim($row['name'] ?? null);
            $amount = $this->nullableTrim($row['amount'] ?? null);
            $dispenseAm = !empty($row['dispense_am']);
            $dispensePm = !empty($row['dispense_pm']);
            $dispenseRest = !empty($row['dispense_rest']);
            $dispenseBeforeBed = !empty($row['dispense_before_bed']);
            $dispensePrn = !empty($row['dispense_prn']);
            $dispenseCustomTime = !empty($row['dispense_custom_time']);
            $customTime = $this->nullableTrim($row['custom_time'] ?? null);
            $mealCondition = $this->nullableTrim($row['meal_condition'] ?? null);

            if (!$name && !$amount && !$dispenseAm && !$dispensePm && !$dispenseRest && !$dispenseBeforeBed && !$dispensePrn && !$dispenseCustomTime && !$customTime && !$mealCondition) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'amount' => $amount,
                'dispense_am' => $dispenseAm,
                'dispense_pm' => $dispensePm,
                'dispense_rest' => $dispenseRest,
                'dispense_before_bed' => $dispenseBeforeBed,
                'dispense_prn' => $dispensePrn,
                'dispense_custom_time' => $dispenseCustomTime,
                'custom_time' => $customTime,
                'meal_condition' => $mealCondition,
            ];
        }

        return array_values($result);
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
