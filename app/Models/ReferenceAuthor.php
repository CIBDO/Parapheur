<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ReferenceAuthor extends Model
{
    protected $fillable = [
        'name',
        'normalized_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $author): void {
            if (! $author->normalized_name) {
                $author->normalized_name = Str::lower(Str::ascii(trim($author->name)));
            }
        });
    }

    public function references(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicReference::class, 'reference_author_links', 'author_id', 'reference_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }
}
