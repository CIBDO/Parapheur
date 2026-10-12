<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTransmissionFile extends Model
{
    protected $fillable = [
        'production_transmission_id',
        'production_file_id',
        'document_version_id',
    ];

    public function transmission(): BelongsTo
    {
        return $this->belongsTo(ProductionTransmission::class, 'production_transmission_id');
    }

    public function productionFile(): BelongsTo
    {
        return $this->belongsTo(ProductionFile::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
