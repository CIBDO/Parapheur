<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionFolder extends Model
{
    protected $fillable = [
        'reference',
        'title',
        'kind',
        'period_date',
        'period_key',
        'structure_id',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionFolderItem::class);
    }

    public function occurrences(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionOccurrence::class,
            'production_folder_items',
            'production_folder_id',
            'production_occurrence_id'
        )->withTimestamps();
    }
}
