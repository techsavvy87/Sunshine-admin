<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Room;

class Appointment extends Model
{
    protected $casts = [
        'metadata' => 'array'
    ];

    public function getFamilyPetIdsAttribute(): array
    {
        $metadata = $this->metadata;

        if (is_string($metadata) && trim($metadata) !== '') {
            $decodedMetadata = json_decode($metadata, true);

            if (is_string($decodedMetadata) && trim($decodedMetadata) !== '') {
                $decodedMetadata = json_decode($decodedMetadata, true);
            }

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $metadata = is_array($metadata) ? $metadata : [];

        $petIds = $metadata['family_pet_ids'] ?? ($metadata['family_pets'] ?? ($metadata['pet_ids'] ?? []));

        if (is_array($petIds) && !empty($petIds) && is_array($petIds[0] ?? null)) {
            $petIds = collect($petIds)
                ->map(fn ($pet) => $pet['id'] ?? null)
                ->filter()
                ->values()
                ->all();
        }

        if (is_string($petIds)) {
            $petIds = explode(',', $petIds);
        }

        $ids = collect(is_array($petIds) ? $petIds : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($ids->isEmpty() && $this->pet_id) {
            $ids = collect([(int) $this->pet_id]);
        }

        return $ids->all();
    }

    public function getFamilyPetsAttribute()
    {
        $petIds = $this->family_pet_ids;

        if (empty($petIds)) {
            return collect();
        }

        $pets = PetProfile::whereIn('id', $petIds)->get()->keyBy('id');

        return collect($petIds)
            ->map(fn ($id) => $pets->get((int) $id))
            ->filter()
            ->values();
    }

    public function getAdditionalServicesByPetAttribute(): array
    {
        $metadata = $this->metadata;

        if (is_string($metadata) && trim($metadata) !== '') {
            $decodedMetadata = json_decode($metadata, true);

            if (is_string($decodedMetadata) && trim($decodedMetadata) !== '') {
                $decodedMetadata = json_decode($decodedMetadata, true);
            }

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $metadata = is_array($metadata) ? $metadata : [];
        $petIds = collect($this->family_pet_ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($petIds->isEmpty() && $this->pet_id) {
            $petIds = collect([(int) $this->pet_id]);
        }

        $normalizedMap = [];
        $rawMap = $metadata['additional_services_by_pet'] ?? [];

        if (is_array($rawMap)) {
            foreach ($rawMap as $petId => $serviceIds) {
                $normalizedPetId = (int) $petId;
                if ($normalizedPetId <= 0) {
                    continue;
                }

                if (is_string($serviceIds)) {
                    $serviceIds = explode(',', $serviceIds);
                }

                $normalizedServiceIds = collect(is_array($serviceIds) ? $serviceIds : [])
                    ->map(fn ($serviceId) => (int) $serviceId)
                    ->filter(fn ($serviceId) => $serviceId > 0)
                    ->unique()
                    ->values()
                    ->all();

                $normalizedMap[$normalizedPetId] = $normalizedServiceIds;
            }
        }

        if (empty($normalizedMap) && !empty($this->additional_service_ids)) {
            $legacyServiceIds = collect(explode(',', (string) $this->additional_service_ids))
                ->map(fn ($serviceId) => (int) trim($serviceId))
                ->filter(fn ($serviceId) => $serviceId > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($legacyServiceIds)) {
                if ($petIds->count() > 1) {
                    foreach ($petIds as $petId) {
                        $normalizedMap[(int) $petId] = $legacyServiceIds;
                    }
                } else {
                    $singlePetId = (int) ($petIds->first() ?? $this->pet_id);
                    if ($singlePetId > 0) {
                        $normalizedMap[$singlePetId] = $legacyServiceIds;
                    }
                }
            }
        }

        if ($petIds->isNotEmpty()) {
            $orderedMap = [];

            foreach ($petIds as $petId) {
                $orderedMap[(int) $petId] = $normalizedMap[(int) $petId] ?? [];
            }

            return $orderedMap;
        }

        ksort($normalizedMap);

        return $normalizedMap;
    }

    public function getAdditionalServiceIdsFlatAttribute(): array
    {
        return collect($this->additional_services_by_pet)
            ->flatten()
            ->map(fn ($serviceId) => (int) $serviceId)
            ->filter(fn ($serviceId) => $serviceId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function getFamilyKennelAssignmentsAttribute(): array
    {
        $metadata = $this->metadata;

        if (is_string($metadata) && trim($metadata) !== '') {
            $decodedMetadata = json_decode($metadata, true);

            if (is_string($decodedMetadata) && trim($decodedMetadata) !== '') {
                $decodedMetadata = json_decode($decodedMetadata, true);
            }

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $metadata = is_array($metadata) ? $metadata : [];
        $rawAssignments = $metadata['family_kennel_assignments'] ?? [];

        if (!is_array($rawAssignments)) {
            return [];
        }

        $normalizedAssignments = [];

        foreach ($rawAssignments as $petId => $kennelId) {
            $normalizedPetId = (int) $petId;
            $normalizedKennelId = is_array($kennelId)
                ? (int) ($kennelId['kennel_id'] ?? 0)
                : (int) $kennelId;

            if ($normalizedPetId <= 0 || $normalizedKennelId <= 0) {
                continue;
            }

            $normalizedAssignments[$normalizedPetId] = $normalizedKennelId;
        }

        if (empty($normalizedAssignments)) {
            $petAssignments = $metadata['family_pet_assignments'] ?? [];

            if (is_array($petAssignments)) {
                foreach ($petAssignments as $petId => $assignment) {
                    $normalizedPetId = (int) $petId;
                    $normalizedKennelId = is_array($assignment)
                        ? (int) ($assignment['kennel_id'] ?? 0)
                        : 0;

                    if ($normalizedPetId <= 0 || $normalizedKennelId <= 0) {
                        continue;
                    }

                    $normalizedAssignments[$normalizedPetId] = $normalizedKennelId;
                }
            }
        }

        if (empty($normalizedAssignments) && $this->kennel_id && count($this->family_pet_ids) > 0) {
            foreach ($this->family_pet_ids as $petId) {
                $normalizedAssignments[(int) $petId] = (int) $this->kennel_id;
            }
        }

        ksort($normalizedAssignments);

        return $normalizedAssignments;
    }

    public function getFamilyPetAssignmentsAttribute(): array
    {
        $metadata = $this->metadata;

        if (is_string($metadata) && trim($metadata) !== '') {
            $decodedMetadata = json_decode($metadata, true);

            if (is_string($decodedMetadata) && trim($decodedMetadata) !== '') {
                $decodedMetadata = json_decode($decodedMetadata, true);
            }

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $metadata = is_array($metadata) ? $metadata : [];
        $rawAssignments = $metadata['family_pet_assignments'] ?? [];

        if (!is_array($rawAssignments)) {
            $rawAssignments = [];
        }

        $normalizedAssignments = [];

        foreach ($rawAssignments as $petId => $assignment) {
            $normalizedPetId = (int) $petId;
            $roomId = is_array($assignment) ? (int) ($assignment['room_id'] ?? 0) : 0;
            $kennelId = is_array($assignment) ? (int) ($assignment['kennel_id'] ?? 0) : 0;

            if ($normalizedPetId <= 0 || $roomId <= 0) {
                continue;
            }

            $normalizedAssignments[$normalizedPetId] = [
                'room_id' => $roomId,
                'kennel_id' => $kennelId > 0 ? $kennelId : null,
            ];
        }

        if (empty($normalizedAssignments)) {
            $legacyKennelAssignments = $this->family_kennel_assignments;

            if (!empty($legacyKennelAssignments)) {
                $fallbackRoomId = (int) ($metadata['assignment_room_id'] ?? $this->cat_room_id ?? 0);

                if ($fallbackRoomId > 0) {
                    foreach ($legacyKennelAssignments as $petId => $kennelId) {
                        $normalizedAssignments[(int) $petId] = [
                            'room_id' => $fallbackRoomId,
                            'kennel_id' => (int) $kennelId > 0 ? (int) $kennelId : null,
                        ];
                    }
                }
            }
        }

        ksort($normalizedAssignments);

        return $normalizedAssignments;
    }

    public function getFamilyKennelModeAttribute(): string
    {
        $metadata = $this->metadata;

        if (is_string($metadata) && trim($metadata) !== '') {
            $decodedMetadata = json_decode($metadata, true);

            if (is_string($decodedMetadata) && trim($decodedMetadata) !== '') {
                $decodedMetadata = json_decode($decodedMetadata, true);
            }

            if (is_array($decodedMetadata)) {
                $metadata = $decodedMetadata;
            }
        }

        $metadata = is_array($metadata) ? $metadata : [];
        $mode = strtolower((string) ($metadata['family_kennel_mode'] ?? ''));

        if (in_array($mode, ['shared', 'individual'], true)) {
            return $mode;
        }

        $pets = $this->familyPets;

        if ($pets->isEmpty()) {
            return count($this->family_pet_ids) > 1 ? 'individual' : 'shared';
        }

        $hasNonSmall = $pets->contains(function ($pet) {
            return strtolower((string) ($pet->size ?? 'medium')) !== 'small';
        });

        return $hasNonSmall ? 'individual' : 'shared';
    }

    public function getFamilyPetAssignmentDetailsAttribute(): array
    {
        $petIds = collect($this->family_pet_ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($petIds->isEmpty()) {
            return [];
        }

        $petNamesById = $this->family_pets
            ->keyBy('id')
            ->map(fn ($pet) => (string) ($pet->name ?? ''))
            ->all();

        if (count($petNamesById) !== $petIds->count()) {
            $fallbackPetNames = PetProfile::whereIn('id', $petIds->all())
                ->pluck('name', 'id')
                ->map(fn ($name) => (string) $name)
                ->all();

            $petNamesById = array_replace($fallbackPetNames, $petNamesById);
        }

        $petAssignments = $this->family_pet_assignments;
        $legacyKennelAssignments = $this->family_kennel_assignments;
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        $defaultRoomId = (int) ($metadata['assignment_room_id'] ?? $this->cat_room_id ?? 0);
        $defaultRoomName = trim((string) ($metadata['assignment_room_name'] ?? optional($this->catRoom)->name ?? ''));
        $defaultKennelName = trim((string) ($metadata['assignment_kennel_name'] ?? optional($this->kennel)->name ?? ''));

        static $roomNamesById = null;
        static $kennelNamesById = null;

        if ($roomNamesById === null) {
            $roomNamesById = Room::pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                ->all();
        }

        if ($kennelNamesById === null) {
            $kennelNamesById = Kennel::pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                ->all();
        }

        $details = [];

        foreach ($petIds as $petId) {
            $petId = (int) $petId;
            $assignment = is_array($petAssignments[$petId] ?? null) ? $petAssignments[$petId] : [];

            $roomId = (int) ($assignment['room_id'] ?? 0);
            if ($roomId <= 0 && $defaultRoomId > 0) {
                $roomId = $defaultRoomId;
            }

            $kennelId = (int) ($assignment['kennel_id'] ?? ($legacyKennelAssignments[$petId] ?? 0));
            if ($kennelId <= 0) {
                $kennelId = 0;
            }

            $roomName = $roomId > 0 ? trim((string) ($roomNamesById[$roomId] ?? '')) : '';
            if ($roomName === '' && $roomId > 0 && $roomId === $defaultRoomId) {
                $roomName = $defaultRoomName;
            }

            $kennelName = $kennelId > 0 ? trim((string) ($kennelNamesById[$kennelId] ?? '')) : '';
            if ($kennelName === '' && $kennelId > 0 && $kennelId === (int) ($this->kennel_id ?? 0)) {
                $kennelName = $defaultKennelName;
            }

            $label = $roomName;
            if ($kennelName !== '') {
                $label = $label !== '' ? ($label . ' / ' . $kennelName) : $kennelName;
            }

            if ($label === '') {
                $label = 'Not assigned';
            }

            $details[] = [
                'pet_id' => $petId,
                'pet_name' => (string) ($petNamesById[$petId] ?? ''),
                'room_id' => $roomId > 0 ? $roomId : null,
                'room_name' => $roomName,
                'kennel_id' => $kennelId > 0 ? $kennelId : null,
                'kennel_name' => $kennelName,
                'label' => $label,
            ];
        }

        return $details;
    }

    public function getFamilyPetAssignmentLabelAttribute(): string
    {
        $details = $this->family_pet_assignment_details;

        if (empty($details)) {
            $roomName = trim((string) (optional($this->catRoom)->name ?? ''));
            $kennelName = trim((string) (optional($this->kennel)->name ?? ''));

            if ($roomName !== '' && $kennelName !== '') {
                return $roomName . ' / ' . $kennelName;
            }

            if ($roomName !== '') {
                return $roomName;
            }

            if ($kennelName !== '') {
                return $kennelName;
            }

            return 'Not assigned';
        }

        if (count($details) === 1) {
            return (string) ($details[0]['label'] ?? 'Not assigned');
        }

        return collect($details)
            ->map(function ($detail) {
                $petName = trim((string) ($detail['pet_name'] ?? ''));
                $label = trim((string) ($detail['label'] ?? 'Not assigned'));

                return $petName !== '' ? ($petName . ': ' . $label) : $label;
            })
            ->implode('; ');
    }

    public function pet()
    {
        return $this->belongsTo(PetProfile::class, 'pet_id', 'id');
    }

    public function kennel()
    {
        return $this->belongsTo(Kennel::class, 'kennel_id', 'id');
    }

    public function catRoom()
    {
        return $this->belongsTo(Room::class, 'cat_room_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'appointment_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'appointment_id', 'id');
    }

    public function checkin()
    {
        return $this->hasOne(Checkin::class, 'appointment_id', 'id');
    }

    public function process()
    {
        return $this->hasOne(Process::class, 'appointment_id', 'id');
    }

    public function checkout()
    {
        return $this->hasOne(Checkout::class, 'appointment_id', 'id');
    }

    public function cancellations()
    {
        return $this->hasMany(AppointmentCancellation::class, 'appointment_id', 'id');
    }
}
