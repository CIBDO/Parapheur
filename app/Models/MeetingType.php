<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingType extends Model
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
            'is_active' => 'boolean',
        ];
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
