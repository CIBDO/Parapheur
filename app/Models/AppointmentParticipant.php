<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentParticipant extends Model
{
    protected $fillable = [
        'appointment_id',
        'user_id',
        'participation_type',
        'role',
        'first_name',
        'last_name',
        'organization',
        'position',
        'email',
        'phone',
        'expected_presence',
        'actual_presence',
    ];

    protected function casts(): array
    {
        return [
            'expected_presence' => 'boolean',
            'actual_presence' => 'boolean',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: ($this->email ?: 'Participant');
    }
}
