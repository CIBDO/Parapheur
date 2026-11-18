<?php

namespace App\Models;

use App\Enums\ArchiveLocationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveStorageLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'structure_id',
        'type',
        'code',
        'name',
        'full_code',
        'status',
        'capacity_linear_meters',
        'occupied_linear_meters',
        'capacity_boxes',
        'occupied_boxes',
        'width_cm',
        'height_cm',
        'depth_cm',
        'sort_order',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ArchiveLocationType::class,
            'is_active' => 'boolean',
            'capacity_linear_meters' => 'decimal:2',
            'occupied_linear_meters' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'depth_cm' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(ArchiveBox::class, 'location_id');
    }

    public function occupancyRate(): ?float
    {
        if ($this->capacity_boxes && $this->capacity_boxes > 0) {
            return round(($this->occupied_boxes / $this->capacity_boxes) * 100, 1);
        }

        if ($this->capacity_linear_meters && (float) $this->capacity_linear_meters > 0) {
            return round(((float) $this->occupied_linear_meters / (float) $this->capacity_linear_meters) * 100, 1);
        }

        return null;
    }
}
