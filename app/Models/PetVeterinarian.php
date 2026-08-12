<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetVeterinarian extends Model
{
    protected $fillable = [
        'pet_profile_id',
        'name',
        'phone',
    ];

    public function petProfile()
    {
        return $this->belongsTo(PetProfile::class, 'pet_profile_id', 'id');
    }
}
