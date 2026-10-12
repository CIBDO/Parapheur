<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionFolderTemplate extends Model
{
    protected $fillable = [
        'code',
        'label',
        'obligation_codes',
        'frequency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'obligation_codes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
