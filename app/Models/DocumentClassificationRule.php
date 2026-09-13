<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentClassificationRule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'document_type_id',
        'structure_id',
        'target_classification_node_id',
        'trigger_status',
        'is_active',
        'priority',
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(ClassificationNode::class, 'target_classification_node_id');
    }
}
