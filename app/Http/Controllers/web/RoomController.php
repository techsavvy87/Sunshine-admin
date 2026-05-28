<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Room;
use App\Models\Kennel;
use App\Models\Appointment;

class RoomController extends Controller
{
    public function listRooms(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');

        $query = Room::query()->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('kennel_ids', 'like', '%' . $search . '%');
            });
        }

        $rooms = $query->paginate($perPage)->withQueryString();
        $kennels = Kennel::orderBy('name')->get();
        $kennelLookup = $kennels->keyBy('id');

        $today = Carbon::today()->toDateString();

        $activeBoardingAppointments = Appointment::with(['pet', 'catRoom', 'customer'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereHas('service.category', function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%boarding%']);
            })
            ->whereDate('date', '<=', $today)
            ->whereRaw('COALESCE(end_date, date) >= ?', [$today])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $collectAppointmentPets = function ($appointment) {
            $familyPets = collect($appointment->family_pets ?? [])->filter();

            if ($familyPets->isNotEmpty()) {
                return $familyPets;
            }

            return collect([$appointment->pet])->filter();
        };

        $activeBoardingEntriesByKennel = collect();
        $activeBoardingEntriesByRoom = collect();

        foreach ($activeBoardingAppointments as $appointment) {
            $appointmentPetsById = $collectAppointmentPets($appointment)
                ->keyBy('id');

            $assignmentDetails = collect($appointment->family_pet_assignment_details ?? []);

            if ($assignmentDetails->isNotEmpty()) {
                $assignmentDetails
                    ->filter(fn ($detail) => (int) ($detail['kennel_id'] ?? 0) > 0)
                    ->groupBy(fn ($detail) => (int) ($detail['kennel_id'] ?? 0))
                    ->each(function ($detailsForKennel, $kennelId) use (&$activeBoardingEntriesByKennel, $appointment, $appointmentPetsById) {
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
                            return;
                        }

                        $activeBoardingEntriesByKennel->put(
                            (int) $kennelId,
                            $activeBoardingEntriesByKennel->get((int) $kennelId, collect())->push((object) [
                                'appointment' => $appointment,
                                'pets' => $kennelPets,
                            ])
                        );
                    });

                $assignmentDetails
                    ->filter(fn ($detail) => (int) ($detail['room_id'] ?? 0) > 0)
                    ->groupBy(fn ($detail) => (int) ($detail['room_id'] ?? 0))
                    ->each(function ($detailsForRoom, $roomId) use (&$activeBoardingEntriesByRoom, $appointment, $appointmentPetsById) {
                        $roomPets = $detailsForRoom
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

                        if ($roomPets->isEmpty()) {
                            return;
                        }

                        $activeBoardingEntriesByRoom->put(
                            (int) $roomId,
                            $activeBoardingEntriesByRoom->get((int) $roomId, collect())->push((object) [
                                'appointment' => $appointment,
                                'pets' => $roomPets,
                            ])
                        );
                    });

                continue;
            }

            $legacyKennelId = (int) ($appointment->kennel_id ?? 0);
            if ($legacyKennelId > 0) {
                $activeBoardingEntriesByKennel->put(
                    $legacyKennelId,
                    $activeBoardingEntriesByKennel->get($legacyKennelId, collect())->push((object) [
                        'appointment' => $appointment,
                        'pets' => $collectAppointmentPets($appointment)->unique('id')->values(),
                    ])
                );
            }

            $legacyRoomId = (int) ($appointment->cat_room_id ?? 0);
            if ($legacyRoomId > 0) {
                $activeBoardingEntriesByRoom->put(
                    $legacyRoomId,
                    $activeBoardingEntriesByRoom->get($legacyRoomId, collect())->push((object) [
                        'appointment' => $appointment,
                        'pets' => $collectAppointmentPets($appointment)->unique('id')->values(),
                    ])
                );
            }
        }

        $rooms->getCollection()->transform(function ($room) use ($kennelLookup, $activeBoardingEntriesByKennel, $activeBoardingEntriesByRoom) {
            $room->assigned_kennels = collect($room->kennel_id_array)
                ->map(fn ($id) => $kennelLookup->get((int) $id))
                ->filter()
                ->values();

            $room->assigned_kennel_names = $room->assigned_kennels->pluck('name')->implode(', ');
            $isSpaceRoom = in_array('space', $room->room_type_array, true);
            $roomEntries = $activeBoardingEntriesByRoom->get((int) $room->id, collect());
            $room->current_room_pets = $isSpaceRoom
                ? $roomEntries->flatMap(fn ($entry) => collect($entry->pets ?? []))->filter()->unique('id')->values()
                : collect();
            $room->kennel_pet_assignments = $room->assigned_kennels->map(function ($kennel) use ($activeBoardingEntriesByKennel) {
                $kennelEntries = $activeBoardingEntriesByKennel->get((int) $kennel->id, collect());
                $pets = $kennelEntries
                    ->flatMap(fn ($entry) => collect($entry->pets ?? []))
                    ->filter()
                    ->unique('id')
                    ->values();
                $appointments = $kennelEntries
                    ->map(fn ($entry) => $entry->appointment)
                    ->filter()
                    ->unique('id')
                    ->values();

                return (object) [
                    'kennel' => $kennel,
                    'pets' => $pets,
                    'appointments' => $appointments,
                ];
            })->values();

            return $room;
        });

        return view('rooms.index', compact('rooms', 'kennels', 'search'));
    }

    public function addRoom()
    {
        // Get all assigned kennel_ids from rooms
        $assignedIds = Room::whereNotNull('kennel_ids')
            ->pluck('kennel_ids')
            ->flatMap(function ($ids) {
                return explode(',', $ids);
            })
            ->filter()
            ->unique()
            ->toArray();

        // Get only unassigned kennels
        $kennels = Kennel::whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        return view('rooms.create', compact('kennels'));
    }

    public function editRoom($id)
    {
        $room = Room::findOrFail($id);

        // Get kennel assignments from other rooms only so current room selections remain visible.
        $assignedIds = Room::where('id', '!=', $room->id)
            ->whereNotNull('kennel_ids')
            ->pluck('kennel_ids')
            ->flatMap(function ($ids) {
                return explode(',', $ids);
            })
            ->filter()
            ->unique()
            ->toArray();

        $kennels = Kennel::whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        return view('rooms.update', compact('room', 'kennels'));
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

    public function createRoom(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'room_type' => 'required|in:standard,space',
            'space_option' => 'nullable|in:restrict,multi',
            'restrict_count' => 'nullable|integer|min:1',
            'pet_type_labels' => 'nullable|array',
            'pet_type_labels.*' => 'in:dog,cat',
            'status' => 'required|in:Available,Blocked,Maintenance',
            'kennel_ids' => 'nullable|array',
            'kennel_ids.*' => 'integer|exists:kennels,id',
            'temp_file' => 'nullable|string',
        ]);

        $normalizedName = trim((string) $request->name);

        if (Room::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])->exists()) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Room name already exists.'
            ]);
        }

        $roomType = strtolower(trim((string) $request->input('room_type', 'standard')));
        $isSpace = $roomType === 'space';
        $isRestrict = $isSpace && $request->input('space_option') === 'restrict';

        if ($isSpace && !$request->filled('space_option')) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Please choose Restrict or Multi when Space is selected.'
            ]);
        }

        if ($isRestrict && !$request->filled('restrict_count')) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Please enter Restrict count when Restrict is selected.'
            ]);
        }

        $room = new Room();
        $room->name = $normalizedName;
        $room->description = $request->description;
        $room->room_types = $roomType;
        $room->space_option = $isSpace ? $request->space_option : null;
        $room->restrict_count = $isRestrict ? (int) $request->restrict_count : null;
        $room->pet_type_labels = $isSpace
            ? $this->normalizeStringList($request->input('pet_type_labels', []), ['dog', 'cat'])
            : null;
        $room->status = $request->status;
        $room->kennel_ids = $roomType === 'standard'
            ? $this->normalizeKennelIds($request->input('kennel_ids', []))
            : null;

        if ($request->filled('temp_file')) {
            $tempFile = $request->temp_file;
            $tempPath = 'temp/' . $tempFile;

            if (Storage::disk('local')->exists($tempPath)) {
                $fileContents = Storage::disk('local')->get($tempPath);

                if ($fileContents !== null) {
                    $permanentPath = 'rooms/' . $tempFile;
                    Storage::disk('public')->put($permanentPath, $fileContents);
                    Storage::disk('local')->delete($tempPath);
                }
            }

            $room->img = $tempFile;
        }

        $room->save();

        return redirect()->route('rooms')->with([
            'status' => 'success',
            'message' => 'Room added successfully!'
        ]);
    }

    public function updateRoom(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:rooms,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'room_type' => 'required|in:standard,space',
            'space_option' => 'nullable|in:restrict,multi',
            'restrict_count' => 'nullable|integer|min:1',
            'pet_type_labels' => 'nullable|array',
            'pet_type_labels.*' => 'in:dog,cat',
            'status' => 'required|in:Available,Blocked,Maintenance',
            'kennel_ids' => 'nullable|array',
            'kennel_ids.*' => 'integer|exists:kennels,id',
            'img_action' => 'required|in:keep,change,delete',
            'temp_file' => 'nullable|string',
            'current_img' => 'nullable|string',
        ]);

        $normalizedName = trim((string) $request->name);

        if (Room::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])
            ->where('id', '!=', $request->id)
            ->exists()) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Room name already exists.'
            ]);
        }

        $roomType = strtolower(trim((string) $request->input('room_type', 'standard')));
        $isSpace = $roomType === 'space';
        $isRestrict = $isSpace && $request->input('space_option') === 'restrict';

        if ($isSpace && !$request->filled('space_option')) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Please choose Restrict or Multi when Space is selected.'
            ]);
        }

        if ($isRestrict && !$request->filled('restrict_count')) {
            return redirect()->back()->withInput()->with([
                'status' => 'fail',
                'message' => 'Please enter Restrict count when Restrict is selected.'
            ]);
        }

        $room = Room::findOrFail($request->id);
        $room->name = $normalizedName;
        $room->description = $request->description;
        $room->room_types = $roomType;
        $room->space_option = $isSpace ? $request->space_option : null;
        $room->restrict_count = $isRestrict ? (int) $request->restrict_count : null;
        $room->pet_type_labels = $isSpace
            ? $this->normalizeStringList($request->input('pet_type_labels', []), ['dog', 'cat'])
            : null;
        $room->status = $request->status;
        $room->kennel_ids = $roomType === 'standard'
            ? $this->normalizeKennelIds($request->input('kennel_ids', []))
            : null;

        switch ($request->img_action) {
            case 'change':
                if ($room->img && Storage::disk('public')->exists('rooms/' . $room->img)) {
                    Storage::disk('public')->delete('rooms/' . $room->img);
                }

                if ($request->filled('temp_file')) {
                    $tempFile = $request->temp_file;
                    $tempPath = 'temp/' . $tempFile;

                    if (Storage::disk('local')->exists($tempPath)) {
                        $fileContents = Storage::disk('local')->get($tempPath);

                        if ($fileContents !== null) {
                            $permanentPath = 'rooms/' . $tempFile;
                            Storage::disk('public')->put($permanentPath, $fileContents);
                            Storage::disk('local')->delete($tempPath);
                            $room->img = $tempFile;
                        }
                    }
                }
                break;

            case 'delete':
                if ($room->img && Storage::disk('public')->exists('rooms/' . $room->img)) {
                    Storage::disk('public')->delete('rooms/' . $room->img);
                }
                $room->img = null;
                break;

            case 'keep':
            default:
                break;
        }

        $room->save();

        return redirect()->route('rooms')->with([
            'status' => 'success',
            'message' => 'Room updated successfully!'
        ]);
    }

    public function deleteRoom(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:rooms,id',
        ]);

        $room = Room::findOrFail($request->id);

        if ($room->img && Storage::disk('public')->exists('rooms/' . $room->img)) {
            Storage::disk('public')->delete('rooms/' . $room->img);
        }

        $room->delete();

        return redirect()->route('rooms')->with([
            'status' => 'success',
            'message' => 'Room deleted successfully!'
        ]);
    }

    private function normalizeKennelIds($kennelIds): ?string
    {
        $ids = collect(is_array($kennelIds) ? $kennelIds : explode(',', (string) $kennelIds))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $existingIds = Kennel::whereIn('id', $ids)->pluck('id')->all();

        return empty($existingIds) ? null : implode(',', $existingIds);
    }

    private function normalizeStringList($values, array $allowed): ?string
    {
        $allowedMap = array_fill_keys($allowed, true);

        $normalized = collect(is_array($values) ? $values : explode(',', (string) $values))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => isset($allowedMap[$value]))
            ->unique()
            ->values();

        return $normalized->isEmpty() ? null : $normalized->implode(',');
    }
}
