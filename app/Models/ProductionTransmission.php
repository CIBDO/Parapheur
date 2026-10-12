<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionTransmission extends Model
{
    protected $fillable = [
        'reference',
        'production_occurrence_id',
        'transmitted_by',
        'channel',
        'is_rectificative',
        'previous_transmission_id',
        'status',
        'transmitted_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_rectificative' => 'boolean',
            'transmitted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }

    public function transmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transmitted_by');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_transmission_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ProductionTransmissionRecipient::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductionTransmissionFile::class);
    }
}
