<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Kennel;
use App\Models\Appointment;

class KennelController extends Controller
{
    public function listKennels(Request $request)
    {
        $activeView = $request->get('view', 'list');
        $occupancyFilter = strtolower((string) $request->get('occupancy_filter', ''));
        if (!in_array($occupancyFilter, ['occupied', 'available'], true)) {
            $occupancyFilter = '';
        }

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

        $boardingAppointments = Appointment::with(['pet', 'customer'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->whereDate('date', '<=', $endDate->toDateString())
            ->whereRaw('COALESCE(end_date, date) >= ?', [$startDate->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $boardingAppointmentsByKennel = collect();

        foreach ($boardingAppointments as $appointment) {
            $appointmentPetsById = $collectAppointmentPets($appointment)
                ->keyBy('id');

            $assignmentDetails = collect($appointment->family_pet_assignment_details ?? [])
                ->filter(function ($detail) use ($kennelIds) {
                    $kennelId = (int) ($detail['kennel_id'] ?? 0);
                    return $kennelId > 0 && $kennelIds->contains($kennelId);
                })
                ->groupBy(function ($detail) {
                    return (int) ($detail['kennel_id'] ?? 0);
                });

            if ($assignmentDetails->isNotEmpty()) {
                foreach ($assignmentDetails as $kennelId => $detailsForKennel) {
                    $kennelPets = $detailsForKennel
                        ->map(function ($detail) use ($appointmentPetsById) {
                            $petId = (int) ($detail['pet_id'] ?? 0);
                            $pet = $appointmentPetsById->get($petId);

                            if ($pet) {
                                return $pet;
                            }

                            if ($petId <= 0) {
                                return null;
                            }

                            return (object) [
                                'id' => $petId,
                                'name' => (string) ($detail['pet_name'] ?? 'Pet'),
                                'pet_img' => null,
                            ];
                        })
                        ->filter()
                        ->unique('id')
                        ->values();

                    if ($kennelPets->isEmpty()) {
                        continue;
                    }

                    $boardingAppointmentsByKennel->put(
                        (int) $kennelId,
                        $boardingAppointmentsByKennel->get((int) $kennelId, collect())->push((object) [
                            'appointment' => $appointment,
                            'pets' => $kennelPets,
                        ])
                    );
                }

                continue;
            }

            $legacyKennelId = (int) ($appointment->kennel_id ?? 0);
            if ($legacyKennelId > 0 && $kennelIds->contains($legacyKennelId)) {
                $boardingAppointmentsByKennel->put(
                    $legacyKennelId,
                    $boardingAppointmentsByKennel->get($legacyKennelId, collect())->push((object) [
                        'appointment' => $appointment,
                        'pets' => $collectAppointmentPets($appointment)->unique('id')->values(),
                    ])
                );
            }
        }

        $kennels->getCollection()->transform(function ($kennel) use ($boardingAppointmentsByKennel) {
            $entries = $boardingAppointmentsByKennel->get($kennel->id, collect());

            $kennel->assigned_pet_bookings = $entries->map(function ($entry) {
                $appointment = $entry->appointment;

                return (object) [
                    'appointment_id' => $appointment->id,
                    'appointment' => $appointment,
                    'start_date' => Carbon::parse($appointment->date)->toDateString(),
                    'end_date' => $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : Carbon::parse($appointment->date)->toDateString(),
                    'pets' => collect($entry->pets ?? [])->filter()->unique('id')->values(),
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
            $entries = $boardingAppointmentsByKennel->get($kennel->id, collect());

            foreach ($dateColumns as $columnDate) {
                $dateString = $columnDate->toDateString();

                if ($kennel->status === 'Out of Service') {
                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'out_of_service',
                        'text' => 'Out of Service',
                    ];
                    continue;
                }

                $dayEntries = $entries->filter(function ($entry) use ($dateString) {
                    $appointment = $entry->appointment;
                    $start = Carbon::parse($appointment->date)->toDateString();
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : $start;

                    return $start <= $dateString && $end >= $dateString;
                })->values();

                if ($dayEntries->isEmpty()) {
                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'empty',
                        'text' => 'Empty',
                    ];
                    continue;
                }

                $checkins = $dayEntries->filter(function ($entry) use ($dateString) {
                    $appointment = $entry->appointment;
                    return Carbon::parse($appointment->date)->toDateString() === $dateString;
                })->values();

                $checkouts = $dayEntries->filter(function ($entry) use ($dateString) {
                    $appointment = $entry->appointment;
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : Carbon::parse($appointment->date)->toDateString();
                    return $end === $dateString;
                })->values();

                $collectPetsFromEntries = function ($sourceEntries) {
                    return collect($sourceEntries)
                        ->flatMap(function ($entry) {
                            return collect($entry->pets ?? [])->filter();
                        })
                        ->filter()
                        ->unique('id')
                        ->values();
                };

                $checkoutForTurnover = $checkouts->sortBy(function ($entry) {
                    return $entry->appointment->end_time;
                })->first();
                $checkinForTurnover = $checkins
                    ->reject(fn ($entry) => $checkoutForTurnover && $entry->appointment->id === $checkoutForTurnover->appointment->id)
                    ->sortBy(function ($entry) {
                        return $entry->appointment->start_time;
                    })
                    ->first();

                if ($checkoutForTurnover && $checkinForTurnover) {
                    $checkoutTurnoverPets = $collectPetsFromEntries([$checkoutForTurnover]);
                    $checkinTurnoverPets = $collectPetsFromEntries([$checkinForTurnover]);

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
                    $checkinPets = $collectPetsFromEntries($checkins);

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => 'checkin',
                        'text' => $checkinPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $checkinPets->map(fn ($pet) => $pet->pet_img ?? null)->filter()->unique()->values()->all(),
                    ];
                    continue;
                }

                $stays = $dayEntries->filter(function ($entry) use ($dateString) {
                    $appointment = $entry->appointment;
                    $start = Carbon::parse($appointment->date)->toDateString();
                    $end = $appointment->end_date
                        ? Carbon::parse($appointment->end_date)->toDateString()
                        : $start;

                    return $start < $dateString && $end > $dateString;
                })->values();

                if ($stays->isNotEmpty()) {
                    $stayPets = $collectPetsFromEntries($stays);

                    $isFamilyStay = $stays->contains(function ($entry) {
                        return collect($entry->pets ?? [])->filter()->count() > 1;
                    });

                    $availabilityMatrix[$kennel->id][$dateString] = [
                        'state' => $isFamilyStay ? 'occupied_family' : 'occupied',
                        'text' => $stayPets->map(fn ($pet) => $pet->name ?? 'Dog')->filter()->unique()->implode(' + '),
                        'pet_imgs' => $stayPets->map(fn ($pet) => $pet->pet_img ?? null)->filter()->unique()->values()->all(),
                    ];
                    continue;
                }

                if ($checkouts->isNotEmpty()) {
                    $checkoutPets = $collectPetsFromEntries($checkouts);

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

        if ($activeView === 'calendar' && $occupancyFilter !== '') {
            $sortedKennels = $this->sortKennelsByFilter($kennels->getCollection(), $boardingAppointmentsByKennel, $occupancyFilter);
            $kennels->setCollection($sortedKennels);
        }

        $dailyAvailabilitySummary = $this->buildDailyAvailabilitySummary($kennels->getCollection(), $dateColumns, $availabilityMatrix);

        return view('kennels.index', compact(
            'kennels',
            'search',
            'type',
            'status',
            'dateColumns',
            'availabilityMatrix',
            'startDate',
            'occupancyFilter',
            'dailyAvailabilitySummary'
        ));
    }

    private function buildDailyAvailabilitySummary(Collection $kennels, Collection $dateColumns, array $availabilityMatrix): array
    {
        $summary = [];

        foreach ($dateColumns as $columnDate) {
            $dateString = $columnDate->toDateString();
            $summary[$dateString] = [
                'available' => 0,
                'occupied' => 0,
            ];

            foreach ($kennels as $kennel) {
                $cell = $availabilityMatrix[$kennel->id][$dateString] ?? ['state' => 'empty'];
                $state = $cell['state'] ?? 'empty';

                if ($state === 'out_of_service') {
                    continue;
                }

                if ($state === 'empty') {
                    $summary[$dateString]['available']++;
                    continue;
                }

                $summary[$dateString]['occupied']++;
            }
        }

        return $summary;
    }

    private function isKennelOccupied($kennelId, Collection $boardingAppointmentsByKennel): bool
    {
        return $boardingAppointmentsByKennel->get($kennelId, collect())->isNotEmpty();
    }

    private function sortKennelsByFilter(Collection $kennels, Collection $boardingAppointmentsByKennel, string $filterType): Collection
    {
        if (!in_array($filterType, ['occupied', 'available'], true)) {
            return $kennels;
        }

        return $kennels
            ->values()
            ->map(function ($kennel, $index) use ($boardingAppointmentsByKennel, $filterType) {
                $isOccupied = $this->isKennelOccupied($kennel->id, $boardingAppointmentsByKennel);

                return [
                    'kennel' => $kennel,
                    'index' => $index,
                    'priority' => $filterType === 'occupied'
                        ? ($isOccupied ? 0 : 1)
                        : ($isOccupied ? 1 : 0),
                ];
            })
            ->sortBy([
                ['priority', 'asc'],
                ['index', 'asc'],
            ])
            ->pluck('kennel')
            ->values();
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
            'capacity' => 'required|integer|min:1',
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
        $kennel->capacity = (int) $request->capacity;
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
            'capacity' => 'required|integer|min:1',
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
        $kennel->capacity = (int) $request->capacity;
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