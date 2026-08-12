<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetProfile extends Model
{
    public const VACCINE_STATUS_EXPIRED = 'expired';

    protected $casts = [
        'pet_behavior_id' => 'array',
    ];

    public function certificates()
    {
        return $this->hasMany(PetCertificate::class, 'pet_profile_id', 'id');
    }

    public function vaccinations()
    {
        return $this->hasMany(PetVaccination::class, 'pet_profile_id', 'id');
    }

    public function veterinarians()
    {
        return $this->hasMany(PetVeterinarian::class, 'pet_profile_id', 'id');
    }

    public function getVeterinarianRecordsAttribute()
    {
        $records = $this->relationLoaded('veterinarians')
            ? $this->veterinarians
            : $this->veterinarians()->get();

        if ($records->isNotEmpty()) {
            return $records;
        }

        $name = trim((string) ($this->veterinarian_name ?? ''));
        $phone = trim((string) ($this->veterinarian_phone ?? ''));

        if ($name === '' && $phone === '') {
            return collect();
        }

        return collect([(object) [
            'name' => $name,
            'phone' => $phone,
        ]]);
    }

    public function vaccinationAlerts()
    {
        return $this->hasMany(PetVaccinationAlert::class, 'pet_profile_id', 'id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function breed()
    {
        return $this->belongsTo(Breed::class, 'breed_id', 'id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id', 'id');
    }

    public function coatType()
    {
        return $this->belongsTo(CoatType::class, 'coat_type_id', 'id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'pet_id', 'id');
    }

    public function questionnaires()
    {
        return $this->hasMany(Questionnaire::class, 'pet_id', 'id');
    }

    public function initialTemperament()
    {
        return $this->hasOne(PetInitialTemperament::class, 'pet_id', 'id');
    }
}
