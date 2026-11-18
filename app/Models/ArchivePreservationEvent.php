<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivePreservationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_id',
        'package_id',
        'event_type',
        'actor_id',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ArchivePackage::class, 'package_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
