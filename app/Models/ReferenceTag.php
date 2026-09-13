<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ReferenceTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function references(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicReference::class, 'reference_tag_links', 'tag_id', 'reference_id')
            ->withTimestamps();
    }
}
