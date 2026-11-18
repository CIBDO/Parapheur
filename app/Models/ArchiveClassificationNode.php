<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveClassificationNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'fonds_id',
        'series_id',
        'structure_id',
        'version',
        'level',
        'code',
        'name',
        'path',
        'depth',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'version' => 'integer',
            'depth' => 'integer',
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

    public function fonds(): BelongsTo
    {
        return $this->belongsTo(ArchiveFonds::class, 'fonds_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(ArchiveSeries::class, 'series_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class, 'classification_node_id');
    }
}
