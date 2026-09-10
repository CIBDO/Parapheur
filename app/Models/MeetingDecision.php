<?php

namespace App\Models;

use App\Enums\DocumentPriority;
use App\Enums\MeetingDecisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'reference',
        'agenda_item_id',
        'title',
        'body',
        'assignee_id',
        'structure_id',
        'due_date',
        'priority',
        'status',
        'observations',
        'execution_comment',
        'executed_at',
        'execution_declared_by',
        'execution_validated_by',
        'execution_validated_at',
        'justification_document_id',
        'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'priority' => DocumentPriority::class,
            'status' => MeetingDecisionStatus::class,
            'executed_at' => 'datetime',
            'execution_validated_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function instruction(): HasOne
    {
        return $this->hasOne(Instruction::class);
    }

    public function instructions(): HasMany
    {
        return $this->hasMany(Instruction::class);
    }

    public function justificationDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'justification_document_id');
    }

    public function executionDeclaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_declared_by');
    }

    public function executionValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'execution_validated_by');
    }

    public function isOverdue(): bool
    {
        $status = $this->status instanceof MeetingDecisionStatus
            ? $this->status
            : MeetingDecisionStatus::tryFrom((string) $this->status);

        if (! $status || ! $status->isOpen() || ! $this->due_date) {
            return false;
        }

        return $this->due_date->endOfDay()->lt(now());
    }

    public function effectiveStatus(): MeetingDecisionStatus
    {
        $status = $this->status instanceof MeetingDecisionStatus
            ? $this->status
            : (MeetingDecisionStatus::tryFrom((string) $this->status) ?? MeetingDecisionStatus::AFaire);

        if ($this->isOverdue() && $status !== MeetingDecisionStatus::EnRetard) {
            return MeetingDecisionStatus::EnRetard;
        }

        return $status;
    }
}
