<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BibliographicReference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'workspace_id',
        'reference_type_id',
        'title',
        'institutional_author',
        'publication_year',
        'publication_date',
        'reference_number',
        'publisher',
        'organization',
        'country',
        'language',
        'abstract',
        'source_url',
        'source_title',
        'accessed_at',
        'doi',
        'isbn',
        'issn',
        'external_id',
        'visibility',
        'document_id',
        'content_hash',
        'publication_status',
        'proposed_at',
        'proposed_by',
        'reviewed_at',
        'reviewed_by',
        'review_note',
        'structure_id',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'publication_date' => 'date',
            'accessed_at' => 'datetime',
            'proposed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ReferenceType::class, 'reference_type_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(ReferenceAuthor::class, 'reference_author_links', 'reference_id', 'author_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ReferenceCollection::class, 'reference_collection_links', 'reference_id', 'collection_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ReferenceTag::class, 'reference_tag_links', 'reference_id', 'tag_id')
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ReferenceNote::class, 'reference_id');
    }
}
