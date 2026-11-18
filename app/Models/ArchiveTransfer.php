<?php

namespace App\Models;

use App\Enums\ArchiveTransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'status',
        'structure_id',
        'producer_unit_id',
        'created_by',
        'submitted_by',
        'controlled_by',
        'accepted_by',
        'title',
        'typology',
        'notes',
        'source_module',
        'source_ref',
        'medium',
        'rejection_reason',
        'submitted_at',
        'controlled_at',
        'accepted_at',
        'rejected_at',
        'taken_over_at',
        'conserved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArchiveTransferStatus::class,
            'submitted_at' => 'datetime',
            'controlled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'taken_over_at' => 'datetime',
            'conserved_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function producerUnit(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'producer_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function controller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }

    public function acceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchiveTransferItem::class, 'transfer_id')->orderBy('sort_order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class, 'transfer_id');
    }
}
