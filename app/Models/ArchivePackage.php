<?php

namespace App\Models;

use App\Enums\ArchivePackageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchivePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'package_type',
        'status',
        'structure_id',
        'transfer_id',
        'record_id',
        'created_by',
        'disk',
        'path',
        'manifest_checksum',
        'package_checksum',
        'size_bytes',
        'format_profile',
        'is_immutable',
        'sealed_at',
        'replicated_at',
        'replica_disk',
        'replica_path',
        'manifest',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_type' => ArchivePackageType::class,
            'is_immutable' => 'boolean',
            'sealed_at' => 'datetime',
            'replicated_at' => 'datetime',
            'manifest' => 'array',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ArchiveTransfer::class, 'transfer_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ArchiveRecord::class, 'record_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchivePackageItem::class, 'package_id');
    }

    public function integrityChecks(): HasMany
    {
        return $this->hasMany(ArchiveIntegrityCheck::class, 'package_id');
    }
}
