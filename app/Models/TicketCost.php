<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCost extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'ticket_worklog_id',
        'name',
        'cost_type',
        'amount',
        'currency',
        'note',
        'cost_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cost_date' => 'date',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function worklog(): BelongsTo
    {
        return $this->belongsTo(TicketWorklog::class, 'ticket_worklog_id');
    }
}
