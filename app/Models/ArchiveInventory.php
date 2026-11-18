<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'title',
        'status',
        'structure_id',
        'location_id',
        'created_by',
        'validated_by',
        'started_at',
        'closed_at',
        'expected_count',
        'found_count',
        'missing_count',
        'unexpected_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchiveInventoryItem::class, 'inventory_id');
    }
}
