<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StructurePosition extends Model
{
    protected $fillable = [
        'structure_id',
        'code',
        'name',
        'can_sign',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'can_sign' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserStructureAssignment::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(PositionDelegation::class);
    }
}
