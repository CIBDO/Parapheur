<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferenceCollection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'visibility',
        'is_institutional',
    ];

    protected function casts(): array
    {
        return [
            'is_institutional' => 'boolean',
        ];
    }
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function references(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicReference::class, 'reference_collection_links', 'collection_id', 'reference_id')
            ->withTimestamps();
    }
}
