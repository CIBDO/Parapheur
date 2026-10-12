<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionFile extends Model
{
    protected $fillable = [
        'production_occurrence_id',
        'document_id',
        'document_version_id',
        'uploaded_by',
        'logical_version',
        'description',
        'status',
        'checksum',
        'transmitted_at',
    ];

    protected function casts(): array
    {
        return [
            'logical_version' => 'integer',
            'transmitted_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ProductionOccurrence::class, 'production_occurrence_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLocked(): bool
    {
        return $this->transmitted_at !== null;
    }
}
