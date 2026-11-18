<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'structure_id',
        'producer_unit_id',
        'fonds_id',
        'series_id',
        'location_id',
        'typology',
        'physical_state',
        'status',
        'date_start',
        'date_end',
        'folder_count',
        'linear_meters',
        'width_cm',
        'height_cm',
        'depth_cm',
        'responsible_id',
        'deposited_at',
        'retention_years',
        'final_disposition',
        'qr_token',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'deposited_at' => 'date',
            'linear_meters' => 'decimal:2',
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

    public function fonds(): BelongsTo
    {
        return $this->belongsTo(ArchiveFonds::class, 'fonds_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ArchiveSeries::class, 'series_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ArchiveStorageLocation::class, 'location_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(ArchiveBoxContent::class, 'box_id')->orderBy('sort_order');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ArchiveMovement::class, 'box_id')->orderByDesc('moved_at');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(ArchiveLoan::class, 'box_id')->orderByDesc('id');
    }
}
