<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRecurrence extends Model
{
    protected $fillable = [
        'frequency',
        'interval',
        'weekday',
        'starts_on',
        'ends_on',
        'occurrences_limit',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'meta' => 'array',
        ];
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'recurrence_id');
    }
}
