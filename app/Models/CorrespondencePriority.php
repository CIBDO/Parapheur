<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrespondencePriority extends Model
{
    protected $fillable = [
        'code',
        'name',
        'color',
        'sort_order',
        'is_urgent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
