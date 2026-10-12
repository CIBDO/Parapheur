<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionFolderItem extends Model
{
    protected $fillable = [
        'production_folder_id',
        'production_occurrence_id',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProductionFolder::class, 'production_folder_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }
}
