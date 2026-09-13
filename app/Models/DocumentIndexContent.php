<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentIndexContent extends Model
{
    protected $fillable = [
        'document_id',
        'document_version_id',
        'content',
        'extraction_method',
        'extracted_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
