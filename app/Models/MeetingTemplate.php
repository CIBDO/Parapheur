<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingTemplate extends Model
{
    protected $fillable = [
        'kind',
        'name',
        'body',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
