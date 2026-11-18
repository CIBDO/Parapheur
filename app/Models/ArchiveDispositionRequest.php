<?php

namespace App\Models;

use App\Enums\ArchiveDispositionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveDispositionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'status',
        'disposition_type',
        'structure_id',
        'proposed_by',
        'authorized_by',
        'executed_by',
        'title',
        'justification',
        'legal_basis',
        'pv_reference',
        'evidence_path',
        'proposed_at',
        'authorized_at',
        'executed_at',
        'refused_at',
        'refusal_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArchiveDispositionStatus::class,
            'proposed_at' => 'datetime',
            'authorized_at' => 'datetime',
            'executed_at' => 'datetime',
            'refused_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchiveDispositionItem::class, 'disposition_request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ArchiveDispositionApproval::class, 'disposition_request_id');
    }
}
