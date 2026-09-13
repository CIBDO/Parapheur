<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRetentionRule extends Model
{
    protected $fillable = [
        'document_type_id',
        'category_id',
        'code',
        'name',
        'retention_years',
        'final_disposition',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'retention_rule_id');
    }
}
