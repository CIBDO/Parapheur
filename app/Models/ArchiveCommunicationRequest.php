<?php

namespace App\Models;

use App\Enums\ArchiveCommunicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveCommunicationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'status',
        'record_id',
        'requester_id',
        'structure_id',
        'decided_by',
        'purpose',
        'access_mode',
        'decided_at',
        'expires_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArchiveCommunicationStatus::class,
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(ArchiveAccessGrant::class, 'communication_request_id');
    }
}
