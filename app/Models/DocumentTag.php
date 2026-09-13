<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DocumentTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'usage_count',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_tag_document')
            ->withTimestamps();
    }
}
