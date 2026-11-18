<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveClimateReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'structure_id',
        'location_id',
        'temperature_c',
        'humidity_pct',
        'source',
        'recorded_by',
        'measured_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'temperature_c' => 'float',
            'humidity_pct' => 'float',
            'measured_at' => 'datetime',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
