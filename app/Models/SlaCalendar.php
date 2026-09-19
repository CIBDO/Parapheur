<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'weekdays',
        'start_time',
        'end_time',
        'is_24_7',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'is_24_7' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(SlaCalendarException::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
