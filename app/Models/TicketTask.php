<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTask extends Model
{
    protected $fillable = [
        'ticket_id',
        'created_by',
        'assignee_id',
        'instruction_id',
        'title',
        'content',
        'status',
        'category',
        'duration_minutes',
        'is_private',
        'planned_start_at',
        'planned_end_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(Instruction::class);
    }
}
