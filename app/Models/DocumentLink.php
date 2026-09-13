<?php

namespace App\Models;

use App\Enums\DocumentLinkRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_document_id',
        'target_document_id',
        'relation_type',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'relation_type' => DocumentLinkRelation::class,
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
