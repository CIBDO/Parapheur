<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveSeries extends Model
{
    use HasFactory;

    protected $table = 'archive_series';

    protected $fillable = [
        'fonds_id',
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function fonds(): BelongsTo
    {
        return $this->belongsTo(ArchiveFonds::class, 'fonds_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class, 'series_id');
    }
}
