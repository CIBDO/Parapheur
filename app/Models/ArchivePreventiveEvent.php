<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivePreventiveEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'structure_id',
        'location_id',
        'box_id',
        'event_type',
        'severity',
        'title',
        'description',
        'reported_by',
        'occurred_at',
        'resolved_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ArchiveStorageLocation::class, 'location_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
