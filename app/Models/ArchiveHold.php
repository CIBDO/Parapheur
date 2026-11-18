<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_id',
        'reason',
        'hold_type',
        'placed_by',
        'lifted_by',
        'placed_at',
        'lifted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }
}
