<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'box_id',
        'expected_location_id',
        'found_location_id',
        'scanned_code',
        'result',
        'scanned_by',
        'scanned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(ArchiveInventory::class, 'inventory_id');
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(ArchiveBox::class, 'box_id');
    }

    public function expectedLocation(): BelongsTo
    {
        return $this->belongsTo(ArchiveStorageLocation::class, 'expected_location_id');
    }

    public function foundLocation(): BelongsTo
    {
        return $this->belongsTo(ArchiveStorageLocation::class, 'found_location_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
