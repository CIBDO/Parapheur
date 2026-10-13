<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'scope', 'metadata_schema', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata_schema' => 'array',
        ];
    }

    public function scopeInstitutional($query)
    {
        return $query->whereIn('scope', ['institutional', 'general']);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
