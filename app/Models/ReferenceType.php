<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferenceType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function references(): HasMany
    {
        return $this->hasMany(BibliographicReference::class, 'reference_type_id');
    }
}
