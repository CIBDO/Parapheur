<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Correspondent extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'function',
        'organization',
        'address',
        'postal_code',
        'city',
        'country',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CorrespondentContact::class);
    }
}
