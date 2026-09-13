<?php

namespace App\Models;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentTemplateKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'structure_id',
        'confidentiality',
        'is_active',
        'current_published_version_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DocumentTemplateKind::class,
            'confidentiality' => DocumentConfidentiality::class,
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'current_published_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }
}
