<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPriorityHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'from_priority_id',
        'to_priority_id',
        'user_id',
        'reason',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'from_priority_id');
    }

    public function toPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'to_priority_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
