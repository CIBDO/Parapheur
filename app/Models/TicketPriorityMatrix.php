<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPriorityMatrix extends Model
{
    use HasFactory;

    protected $table = 'ticket_priority_matrix';

    protected $fillable = [
        'impact_id',
        'urgency_id',
        'priority_id',
    ];

    public function impact(): BelongsTo
    {
        return $this->belongsTo(TicketImpactLevel::class, 'impact_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(TicketUrgencyLevel::class, 'urgency_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }
}
