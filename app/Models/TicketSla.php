<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSla extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'sla_policy_id',
        'response_due_at',
        'resolution_due_at',
        'paused_at',
        'paused_seconds',
        'response_met_at',
        'resolution_met_at',
        'warning_sent_at',
        'response_breached_at',
        'resolution_breached_at',
    ];

    protected function casts(): array
    {
        return [
            'paused_seconds' => 'integer',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'paused_at' => 'datetime',
            'response_met_at' => 'datetime',
            'resolution_met_at' => 'datetime',
            'warning_sent_at' => 'datetime',
            'response_breached_at' => 'datetime',
            'resolution_breached_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }
}
