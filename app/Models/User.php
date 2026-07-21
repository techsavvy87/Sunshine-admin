<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    public const APPOINTMENT_QUESTIONNAIRE_REQUIRED_FROM = '2026-07-20';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function requiresAppointmentQuestionnaire(): bool
    {
        return $this->created_at === null
            || $this->created_at->gte(self::APPOINTMENT_QUESTIONNAIRE_REQUIRED_FROM);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
                    ->withTimestamps();
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }

    public function pets()
    {
        return $this->hasMany(PetProfile::class, 'user_id', 'id');
    }

    public function additionalOwners()
    {
        return $this->hasMany(AdditionalOwner::class, 'user_id', 'id');
    }

    public function appointmentCancellations()
    {
        return $this->hasMany(AppointmentCancellation::class, 'customer_id', 'id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id', 'id');
    }
}
