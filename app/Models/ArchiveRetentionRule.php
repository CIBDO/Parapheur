<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveRetentionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'typology',
        'structure_id',
        'series_id',
        'trigger_event',
        'current_years',
        'intermediate_years',
        'final_disposition',
        'legal_basis',
        'authority',
        'version',
        'valid_from',
        'valid_to',
        'is_active',
        'is_validated',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_validated' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'validated_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ArchiveSeries::class, 'series_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class, 'retention_rule_id');
    }

    public function totalYears(): int
    {
        return (int) $this->current_years + (int) $this->intermediate_years;
    }
}
