<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'default_duration_minutes',
        'blocks_calendar',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'blocks_calendar' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
