<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveFonds extends Model
{
    use HasFactory;

    protected $table = 'archive_fonds';

    protected $fillable = [
        'code',
        'name',
        'description',
        'structure_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(ArchiveSeries::class, 'fonds_id')->orderBy('sort_order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class, 'fonds_id');
    }
}
