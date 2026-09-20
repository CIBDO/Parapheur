<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlaPolicy extends Model
{
    protected $fillable = [
        'code',
        'name',
        'support_team_id',
        'sla_calendar_id',
        'response_minutes',
        'resolution_minutes',
        'warning_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'support_team_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'sla_calendar_id');
    }

    public function ticketOlas(): HasMany
    {
        return $this->hasMany(TicketOla::class);
    }
}
