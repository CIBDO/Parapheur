<?php

namespace App\Models;

use App\Enums\DocumentPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'meeting_decision_id',
        'issuer_id',
        'assignee_id',
        'structure_id',
        'title',
        'body',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => DocumentPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
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

    public function updates(): HasMany
    {
        return $this->hasMany(InstructionUpdate::class)->orderByDesc('created_at');
    }
}
