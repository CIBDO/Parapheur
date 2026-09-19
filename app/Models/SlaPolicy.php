<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'priority_id',
        'sla_calendar_id',
        'response_minutes',
        'resolution_minutes',
        'warning_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'warning_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'sla_calendar_id');
    }

    public function ticketSlas(): HasMany
    {
        return $this->hasMany(TicketSla::class);
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }
}
