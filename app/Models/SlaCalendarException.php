<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaCalendarException extends Model
{
    use HasFactory;

    protected $fillable = [
        'sla_calendar_id',
        'date',
        'is_closed',
        'start_time',
        'end_time',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_closed' => 'boolean',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'sla_calendar_id');
    }
}
