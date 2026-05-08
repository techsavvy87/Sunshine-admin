<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Kennel;
use App\Models\Appointment;

class KennelController extends Controller
{
    public function listKennels(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        $type = $request->get('type');
        $status = $request->get('status');
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today();
        $dateColumns = collect(range(0, 6))->map(function ($offset) use ($startDate) {
            return $startDate->copy()->addDays($offset);
        });
        $endDate = $dateColumns->last();

        $query = Kennel::query()->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $kennels = $query->paginate($perPage)->withQueryString();
        $kennelIds = $kennels->getCollection()->pluck('id')->values();

        $collectAppointmentPets = function ($appointment) {
            $familyPets = collect($appointment->family_pets ?? [])->filter();

            if ($familyPets->isNotEmpty()) {
                return $familyPets;
            }

            return collect([$appointment->pet])->filter();
        };

        $boardingAppointmentsByKennel = Appointment::with(['pet', 'customer'])
            ->whereIn('kennel_id', $kennelIds)
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->whereDate('date', '<=', $endDate->toDateString())
            ->whereRaw('COALESCE(end_date, date) >= ?', [$startDate->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->groupBy('kennel_id');

        $kennels->getCollection()->transform(function ($kennel) use ($boardingAppointmentsByKennel, $collectAppointmentPets) {
            $appointments = $boardingAppointmentsByKennel->get($kennel->id, collect());

            $kennel->assigned_pet_bookings = $appointments->map(function ($appointment) use ($collectAppointmentPets) {
                return (object) [
                    'appointment_id' => $appointment->id,
                    'start_date' => Carbon::parse($appointment->date)->toDateString(),
                    'end_date' => $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : Carbon::parse($appointment->date)->toDateString(),
                    'pets' => $collectAppointmentPets($appointment)->unique('id')->values(),
                ];
            })->values();

            $kennel->current_pets = $kennel->assigned_pet_bookings
                ->flatMap(fn ($booking) => $booking->pets)
                ->filter()
                ->unique('id')
                ->values();
            $kennel->current_pet = $kennel->current_pets->first();

            return $kennel;
        });

        $availabilityMatrix = [];

        foreach ($kennels->getCollection() as $kennel) {
            $availabilityMatrix[$kennel->id] = [];
            $appointments = $boardingAppointmentsByKennel->get($kennel->id, collect());

            foreach ($dateColumns as $columnDate) {
                $dateString = $columnDate->toDateString();

                if ($kennel->status === 'Out of Service') {
                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'out_of_service',
                        'text' => 'Out of Service',
                    ];
                    continue;
                }

                $dayAppointments = $appointments->filter(function ($appointment) use ($dateString) {
                    $start = Carbon::parse($appointment->date)->toDateString();
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : $start;

                    return $start <= $dateString && $end >= $dateString;
                })->values();

                if ($dayAppointments->isEmpty()) {
                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'empty',
                        'text' => 'Empty',
                    ];
                    continue;
                }

                $checkins = $dayAppointments->filter(function ($appointment) use ($dateString) {
                    return Carbon::parse($appointment->date)->toDateString() === $dateString;
                })->values();

                $checkouts = $dayAppointments->filter(function ($appointment) use ($dateString) {
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : Carbon::parse($appointment->date)->toDateString();
                    return $end === $dateString;
                })->values();

                $collectPetsFromAppointments = function ($appointments) {
                    $pets = collect($appointments)->flatMap(function ($appointment) {
                        return collect($appointment->family_pets ?? [])->filter();
                    })->filter()->unique('id')->values();

                    if ($pets->isEmpty()) {
                        $pets = collect($appointments)->map(fn ($appointment) => $appointment->pet)->filter()->unique('id')->values();
                    }

                    return $pets;
                };

                $checkoutForTurnover = $checkouts->sortBy('end_time')->first();
                $checkinForTurnover = $checkins
                    ->reject(fn ($appointment) => $checkoutForTurnover && $appointment->id === $checkoutForTurnover->id)
                    ->sortBy('start_time')
                    ->first();

                if ($checkoutForTurnover && $checkinForTurnover) {
                    $checkoutTurnoverPets = $collectPetsFromAppointments([$checkoutForTurnover]);
                    $checkinTurnoverPets = $collectPetsFromAppointments([$checkinForTurnover]);

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'turnover',
                        'text' => $checkoutTurnoverPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + ')
                            . ' -> '
                            . $checkinTurnoverPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $checkoutTurnoverPets
                            ->concat($checkinTurnoverPets)
                            ->map(fn ($pet) => $pet->pet_img ?? null)
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),
                    ];
                    continue;
                }

                if ($checkins->isNotEmpty()) {
                    $checkinPets = $collectPetsFromAppointments($checkins);

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'checkin',
                        'text' => $checkinPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $checkinPets->map(fn ($pet) => $pet->pet_img ?? null)->filter()->unique()->values()->all(),
                    ];
                    continue;
                }

                $stays = $dayAppointments->filter(function ($appointment) use ($dateString) {
                    $start = Carbon::parse($appointment->date)->toDateString();
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : $start;

                    return $start < $dateString && $end > $dateString;
                })->values();

                if ($stays->isNotEmpty()) {
                    $stayPets = $stays->flatMap(function ($appointment) {
                        return collect($appointment->family_pets ?? [])->filter();
                    })->filter()->unique('id')->values();

                    if ($stayPets->isEmpty()) {
                        $stayPets = $stays->map(fn ($appointment) => $appointment->pet)->filter()->unique('id')->values();
                    }

                    $isFamilyStay = $stays->contains(function ($appointment) {
                        return collect($appointment->family_pets ?? [])->filter()->count() > 1;
                    });

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => $isFamilyStay ? 'occupied_family' : 'occupied',
                        'text' => $stayPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $stayPets->map(fn ($pet) => $pet->pet_img ?? null)->filter()->unique()->values()->all(),
                    ];
                    continue;
                }

                if ($checkouts->isNotEmpty()) {
                    $checkoutPets = $collectPetsFromAppointments($checkouts);

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'checkout',
                        'text' => $checkoutPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $checkoutPets->map(fn ($pet) => $pet->pet_img ?? null)->filter()->unique()->values()->all(),
                    ];
                    continue;
                }

                $availabilityMatrix[$kennel->id][$dateString] = [
                    'state' => 'empty',
                    'text' => 'Empty',
                ];
            }
        }

        return view('kennels.index', compact(
            'kennels',
            'search',
            'type',
            'status',
            'dateColumns',
            'availabilityMatrix',
            'startDate'
        ));
    }

    public function addKennel()
    {
        return view('kennels.create');
    }

    public function editKennel($id)
    {
        $kennel = Kennel::findOrFail($id);

        return view('kennels.update', compact('kennel'));
    }

    public function processFileUpload(Request $request)
    {
        try {
            $request->validate([
                'img' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $file = $request->file('img');
            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('temp', $fileName, 'local');

            return response()->json([
                'temp_file' => $fileName,
                'original_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File upload failed: ' . $e->getMessage()
            ], 422);
        }
    }

    public function revertFileUpload(Request $request)
    {
        try {
            $tempFile = $request->getContent();

            if ($tempFile && Storage::disk('local')->exists('temp/' . $tempFile)) {
                Storage::disk('local')->delete('temp/' . $tempFile);
            }

            return response()->json(['message' => 'File reverted successfully.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'File deletion failed: ' . $e->getMessage()
            ], 422);
        }
    }

    public function createKennel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:dog,cat',
            'status' => 'required|in:In Service,Out of Service,Cleaning',
            'temp_file' => 'nullable|string',
        ]);

        $normalizedName = trim((string) $request->name);

        if (Kennel::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])->exists()) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Kennel name already exists.'
            ]);
        }

        $kennel = new Kennel();
        $kennel->name = $normalizedName;
        $kennel->description = $request->description;
        $kennel->type = $request->type;
        $kennel->status = $request->status;

        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    $permanentPath = 'kennels/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath);
                }
            }

            $kennel->img = $tempFile;
        }

        $kennel->save();

        return redirect()->route('kennels')->with([
            'status' => 'success',
            'message' => 'Kennel added successfully!'
        ]);
    }

    public function updateKennel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:kennels,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:dog,cat',
            'status' => 'required|in:In Service,Out of Service,Cleaning',
            'img_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_img' => 'nullable|string',
        ]);

        $normalizedName = trim((string) $request->name);

        if (Kennel::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])
            ->where('id', '!=', $request->id)
            ->exists()) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Kennel name already exists.'
            ]);
        }

        $kennel = Kennel::findOrFail($request->id);

        $activeBoardingAppointment = Appointment::with('pet')
            ->where('kennel_id', $kennel->id)
            ->whereIn('status', ['checked_in', 'in_progress'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->latest('id')
            ->first();

        if (
            $kennel->status === 'Out of Service'
            && $request->status !== $kennel->status
            && $activeBoardingAppointment
        ) {
            $petName = $activeBoardingAppointment->pet->name ?? 'a pet';

            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'This kennel cannot be updated because ' . $petName . ' is currently assigned through a boarding appointment.'
            ]);
        }

        $kennel->name = $normalizedName;
        $kennel->description = $request->description;
        $kennel->type = $request->type;
        $kennel->status = $request->status;

        switch ($request->img_action) {
            case 'change':
                if ($kennel->img && Storage::disk('public')->exists('kennels/' . $kennel->img)) {
                    Storage::disk('public')->delete('kennels/' . $kennel->img);
                }

                if ($request->filled('temp_file')) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $fileContents = Storage::disk('local')->get($tempPath);

                        if ($fileContents !== null) {
                            $permanentPath = 'kennels/' . $tempFile;
                            Storage::disk('public')->put($permanentPath, $fileContents);
                            Storage::disk('local')->delete($tempPath);
                            $kennel->img = $tempFile;
                        }
                    }
                }
                break;

            case 'delete':
                if ($kennel->img && Storage::disk('public')->exists('kennels/' . $kennel->img)) {
                    Storage::disk('public')->delete('kennels/' . $kennel->img);
                }
                $kennel->img = null;
                break;

            case 'keep':
            default:
                break;
        }

        $kennel->save();

        return redirect()->route('kennels')->with([
            'status' => 'success',
            'message' => 'Kennel updated successfully!'
        ]);
    }

    public function deleteKennel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:kennels,id',
        ]);

        $kennel = Kennel::findOrFail($request->id);

        if ($kennel->img && Storage::disk('public')->exists('kennels/' . $kennel->img)) {
            Storage::disk('public')->delete('kennels/' . $kennel->img);
        }

        $kennel->delete();

        return redirect()->route('kennels')->with([
            'status' => 'success',
            'message' => 'Kennel deleted successfully!'
        ]);
    }
}