<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\InstructionStatus;
use App\Enums\TaskSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Instruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'document_id',
        'meeting_decision_id',
        'issuer_id',
        'assignee_id',
        'structure_id',
        'title',
        'body',
        'priority',
        'confidentiality',
        'source_kind',
        'source_type',
        'source_id',
        'status',
        'due_date',
        'started_at',
        'completed_at',
        'closed_at',
        'cancelled_at',
        'cancel_reason',
        'is_personal',
        'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'source_kind' => TaskSource::class,
            'status' => InstructionStatus::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'is_personal' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function meetingDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(InstructionUpdate::class)->orderByDesc('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderByDesc('id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(InstructionRecipient::class);
    }

    public function isOverdue(): bool
    {
        if (! $this->due_date || ! $this->status?->isOpen()) {
            return false;
        }

        return $this->due_date->endOfDay()->isPast();
    }
}
