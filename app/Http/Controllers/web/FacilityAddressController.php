<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\FacilityAddress as FacilityAddressModel;
use App\Models\Appointment;
use App\Services\LateFeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacilityAddressController extends Controller
{
    public function index()
    {
        $facilityAddress = FacilityAddressModel::query()->first();
        $states = config('us_states', []);

        $selectedStateRaw = old('state', $facilityAddress->state ?? '');
        $selectedState = isset($states[$selectedStateRaw])
            ? $selectedStateRaw
            : (array_search($selectedStateRaw, $states, true) ?: '');

        return view('facility-address.index', compact('facilityAddress', 'states', 'selectedState'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s\.-]+$/'],
            'state' => ['required', 'string', Rule::in(array_keys(config('us_states', [])))],
            'zip' => ['required', 'string', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'late_fees_enabled' => ['nullable', 'boolean'],
        ], [
            'address.required' => 'Please fill in address.',
            'city.required' => 'Please fill in city.',
            'city.regex' => 'City can only contain letters, spaces, dots, and hyphens.',
            'state.required' => 'Please select a state.',
            'state.in' => 'Selected state is invalid.',
            'zip.required' => 'Please fill in zip code.',
            'zip.regex' => 'Zip code must be in 5-digit format or ZIP+4 format (e.g. 12345 or 12345-6789).',
        ]);

        $data = [
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'zip_code' => $validated['zip'] ?? null,
            'late_fees_enabled' => $request->boolean('late_fees_enabled'),
        ];

        $facilityAddress = FacilityAddressModel::query()->orderBy('id')->first();

        if ($facilityAddress) {
            $facilityAddress->update($data);

            // Keep only one record in the table.
            FacilityAddressModel::query()
                ->where('id', '!=', $facilityAddress->id)
                ->delete();
        } else {
            FacilityAddressModel::query()->create($data);
        }

        Appointment::query()
            ->whereHas('service.category', fn ($query) => $query->where('name', 'like', '%boarding%'))
            ->with('service.category')
            ->each(fn (Appointment $appointment) => app(LateFeeService::class)->reconcile($appointment));

        return redirect()->route('facility-address')->with([
            'message' => 'Facility address updated successfully.',
            'status' => 'success',
        ]);
    }
}
